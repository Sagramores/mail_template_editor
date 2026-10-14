<?php
/**
 * Mail Template Editor - extracts the useful content of an existing PrestaShop
 * e-mail template (core 1.7/8/9 MJML templates, old 1.6 templates, module templates)
 * so it can be wrapped in one of the module's designs.
 */
if (!defined('_PS_VERSION_') && !defined('MTE_TESTING')) {
    exit;
}

class MteContentExtractor
{
    /**
     * @param string $html full template HTML (UTF-8)
     * @param bool $normalizeStyles remove inline fonts/colours/sizes so the design applies
     *
     * @return string body content
     */
    public static function extract($html, $normalizeStyles = true)
    {
        $html = MteEncoding::toUtf8($html);
        // Conditional comments (Outlook) and normal comments.
        $html = preg_replace('/<!--\[if[^\]]*\]>.*?<!\[endif\]-->/is', '', $html);
        $html = preg_replace('/<!--.*?-->/s', '', $html);
        $html = self::protectTableVars($html);

        $doc = self::load($html);
        if (!$doc) {
            return '';
        }
        $xpath = new DOMXPath($doc);

        foreach (['//script', '//style', '//head', '//title', '//meta', '//link'] as $q) {
            self::removeAll($xpath->query($q));
        }

        // Header: the logo row / block.
        foreach ($xpath->query('//img[contains(@src, "{shop_logo}")]') as $img) {
            self::removeBlock($img, ['tr', 'table', 'div'], 'shop_logo');
        }
        // The {shop_name} link is footer only when it stands alone (not inside a sentence)
        // and nothing meaningful follows it. In "account" it is part of the text and stays.
        $links = [];
        foreach ($xpath->query('//a[contains(@href, "{shop_url}") and normalize-space(.) = "{shop_name}"]') as $a) {
            $links[] = $a;
        }
        foreach ($links as $a) {
            if (!$a->parentNode || self::meaningfulText($a->parentNode, 'shop_url') !== '') {
                continue;
            }
            $after = '';
            foreach ($xpath->query('following::text()', $a) as $t) {
                $after .= ' ' . $t->nodeValue;
            }
            if (self::meaningfulText($doc->createTextNode($after), 'shop_url') === '') {
                self::removeBlock($a, ['tr', 'p', 'div', 'table'], 'shop_url');
            }
        }
        // Footer: "powered by PrestaShop" and the {shop_name} link row at the bottom.
        foreach ($xpath->query('//text()[contains(translate(., "POWERED", "powered"), "powered by")]') as $node) {
            self::removeBlock($node, ['tr', 'div', 'p', 'table'], 'powered');
        }
        // Spacer-only elements (MJML output is full of them).
        $nbsp = "\xC2\xA0";
        $empty = 'normalize-space(translate(., "' . $nbsp . '", " ")) = "" and not(.//img) and not(.//a) and not(.//table) and not(.//hr)';
        $spacers = [];
        foreach ($xpath->query('//div[not(node()) or (' . $empty . ' and string-length(@style) > 0 and contains(@style, "height"))]') as $div) {
            $spacers[] = $div;
        }
        foreach ($spacers as $div) {
            if ($div->parentNode) {
                $div->parentNode->removeChild($div);
            }
        }
        // Sections left empty once their spacers are gone.
        $spacers = [];
        foreach ($xpath->query('//body/div[' . $empty . '] | //body/*/div[' . $empty . ' and not(ancestor::table)]') as $div) {
            $spacers[] = $div;
        }
        foreach ($spacers as $div) {
            if ($div->parentNode) {
                $div->parentNode->removeChild($div);
            }
        }

        $body = $doc->getElementsByTagName('body')->item(0);
        if (!$body) {
            return '';
        }
        if ($normalizeStyles) {
            self::normalize($xpath);
        } else {
            self::removeFrame($body);
            // The card of the original layout (PrestaShop "modern"/"classic" mail themes).
            foreach ($xpath->query('//*[contains(@style, "box-shadow") or contains(concat(" ", normalize-space(@class), " "), " wrapper-container ") or contains(concat(" ", normalize-space(@class), " "), " shadow ")]') as $el) {
                self::stripFrameStyle($el);
            }
        }

        $out = '';
        foreach ($body->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }
        $out = self::fixVariables($out);
        $out = self::unwrapLayoutTables($out);
        // Collapse whitespace-only lines.
        $out = preg_replace("/\n\s*\n+/", "\n", trim($out));

        return $out;
    }

    /**
     * @return DOMDocument|null
     */
    public static function load($html)
    {
        if (!preg_match('/<body/i', $html)) {
            $html = '<html><body>' . $html . '</body></html>';
        }
        $doc = new DOMDocument('1.0', 'UTF-8');
        $prev = libxml_use_internal_errors(true);
        // The XML declaration makes libxml read the string as UTF-8 (instead of ISO-8859-1).
        $ok = $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (!$ok) {
            return null;
        }
        foreach ($doc->childNodes as $node) {
            if ($node->nodeType === XML_PI_NODE) {
                $doc->removeChild($node);
                break;
            }
        }

        return $doc;
    }

    /**
     * Variables such as {products} are placed directly inside a <table> because
     * PrestaShop replaces them with <tr> rows. Any HTML parser (and the visual
     * editor) would move such text out of the table, so while editing they are
     * kept as comments: <!--mte:{products}-->. restoreTableVars() undoes this.
     */
    public static function protectTableVars($html)
    {
        $pattern = '/(<\/tr>|<\/thead>|<t(?:able|body|head)\b[^>]*>|<!--mte:\{[a-zA-Z0-9_]+\}-->)(\s*)(\{[a-zA-Z0-9_]+\})/i';
        for ($i = 0; $i < 20; ++$i) {
            $new = preg_replace($pattern, '$1$2<!--mte:$3-->', $html);
            if ($new === null || $new === $html) {
                break;
            }
            $html = $new;
        }

        return $html;
    }

    public static function restoreTableVars($html)
    {
        return preg_replace('/<!--mte:(\{[a-zA-Z0-9_]+\})-->/', '$1', (string) $html);
    }

    /**
     * libxml URL-encodes href/src attributes: "{shop_url}" becomes "%7Bshop_url%7D".
     */
    public static function fixVariables($html)
    {
        return preg_replace('/%7B([a-zA-Z0-9_]+)%7D/', '{$1}', $html);
    }

    protected static function removeAll($nodes)
    {
        $list = [];
        foreach ($nodes as $n) {
            $list[] = $n;
        }
        foreach ($list as $n) {
            if ($n->parentNode) {
                $n->parentNode->removeChild($n);
            }
        }
    }

    /**
     * Removes the smallest ancestor block of $node that contains only "chrome"
     * (no other meaningful text), so the real content is kept.
     */
    protected static function removeBlock(DOMNode $node, array $tags, $kind)
    {
        $target = $node instanceof DOMElement ? $node : $node->parentNode;
        $cur = $target;
        while ($cur && $cur->parentNode && $cur->nodeName !== 'body') {
            $parent = $cur->parentNode;
            if ($parent->nodeName === 'body') {
                break;
            }
            $text = self::meaningfulText($parent, $kind);
            if (mb_strlen($text, 'UTF-8') > 0) {
                break;
            }
            $cur = $parent;
        }
        if ($cur && $cur->parentNode && $cur->nodeName !== 'body') {
            $cur->parentNode->removeChild($cur);
        }
    }

    protected static function meaningfulText(DOMNode $node, $kind)
    {
        $text = preg_replace('/\s+/u', ' ', $node->textContent);
        $text = str_ireplace(['{shop_name}', '{shop_url}', 'powered by', 'prestashop™', 'prestashop', '™', '|', ' '], '', $text);

        return trim($text);
    }

    /**
     * The outer "frame" of the original e-mail (page background, card with shadow,
     * fixed width) is provided by the design: remove it from the elements that wrap
     * the whole content, keep every style of the content itself.
     */
    protected static function stripFrameStyle(DOMElement $el)
    {
        $frame = '/(^|;)\s*(background-color|background|box-shadow|-webkit-box-shadow|border-radius|max-width|width|min-width|margin)\s*:[^;]*/i';
        if ($el->hasAttribute('style')) {
            $style = trim(preg_replace('/;{2,}/', ';', preg_replace($frame, '$1', $el->getAttribute('style'))), "; \t\n");
            if ($style === '') {
                $el->removeAttribute('style');
            } else {
                $el->setAttribute('style', $style);
            }
        }
        foreach (['bgcolor', 'class'] as $attr) {
            $el->removeAttribute($attr);
        }
        if ($el->hasAttribute('width') && (int) $el->getAttribute('width') > 200) {
            $el->setAttribute('width', '100%');
        }
    }

    protected static function removeFrame(DOMElement $body)
    {
        $cur = $body;
        for ($depth = 0; $depth < 40 && $cur; ++$depth) {
            $children = [];
            foreach ($cur->childNodes as $child) {
                if ($child instanceof DOMElement) {
                    $children[] = $child;
                } elseif ($child->nodeType === XML_TEXT_NODE && trim($child->nodeValue) !== '') {
                    $children = [];
                    break;
                }
            }
            if ($cur !== $body) {
                self::stripFrameStyle($cur);
            }
            if (count($children) !== 1) {
                // Sections of the content: only their fixed width and centring margin go.
                foreach ($children as $child) {
                    if ($child->hasAttribute('style')) {
                        $child->setAttribute('style', trim(preg_replace('/;{2,}/', ';', preg_replace('/(^|;)\s*(max-width|margin)\s*:[^;]*/i', '$1', $child->getAttribute('style'))), "; \t\n"));
                    }
                }
                break;
            }
            $cur = $children[0];
        }
    }

    /**
     * Removes inline font/colour declarations so the selected design rules apply.
     */
    protected static function normalize(DOMXPath $xpath)
    {
        foreach ($xpath->query('//*[@style]') as $el) {
            $style = $el->getAttribute('style');
            $style = preg_replace('/(^|;)\s*(font-family|font-size|line-height|color|background-color|background|background-image|border-color|letter-spacing|(?:-[a-z]+-)?box-shadow|filter)\s*:[^;]*/i', '$1', $style);
            // Fixed pixel widths make the content overflow on phones.
            $style = preg_replace('/(^|;)\s*(max-width|width|min-width)\s*:\s*[0-9.]+px[^;]*/i', '$1', $style);
            $style = trim(preg_replace('/;{2,}/', ';', $style), "; \t\n");
            if ($style === '') {
                $el->removeAttribute('style');
            } else {
                $el->setAttribute('style', $style);
            }
        }
        foreach (['bgcolor', 'color', 'face', 'class'] as $attr) {
            foreach ($xpath->query('//*[@' . $attr . ']') as $el) {
                $el->removeAttribute($attr);
            }
        }
        foreach ($xpath->query('//*[@width]') as $el) {
            if ((int) $el->getAttribute('width') > 200 && $el->nodeName !== 'img') {
                $el->setAttribute('width', '100%');
            }
        }
        // <font> tags carry nothing useful once attributes are gone: unwrap them.
        $fonts = [];
        foreach ($xpath->query('//font') as $f) {
            $fonts[] = $f;
        }
        foreach ($fonts as $f) {
            while ($f->firstChild) {
                $f->parentNode->insertBefore($f->firstChild, $f);
            }
            $f->parentNode->removeChild($f);
        }
    }

    /**
     * MJML/1.6 templates wrap the content in many single-cell layout tables and divs.
     * Unwraps them repeatedly (keeps tables that hold real tabular data).
     */
    public static function unwrapLayoutTables($html)
    {
        for ($round = 0; $round < 30; ++$round) {
            $doc = self::load($html);
            if (!$doc) {
                return $html;
            }
            $xpath = new DOMXPath($doc);
            $changed = false;
            foreach ($xpath->query('//table') as $table) {
                $rows = $xpath->query('./tr|./tbody/tr|./thead/tr', $table);
                if ($rows->length !== 1) {
                    continue;
                }
                // Single real cell, optionally surrounded by empty spacer cells.
                $real = [];
                foreach ($xpath->query('./td|./th', $rows->item(0)) as $cell) {
                    if (!self::isSpacer($cell)) {
                        $real[] = $cell;
                    }
                }
                if (count($real) !== 1) {
                    continue;
                }
                self::replaceWithChildren($table, $real[0]);
                $changed = true;
                break;
            }
            if (!$changed) {
                foreach ($xpath->query('//body/div | //body//div[count(*) = 1 and */self::div and normalize-space(text()) = ""]') as $div) {
                    if ($div->attributes->length === 0 || !$div->hasAttribute('style')) {
                        self::replaceWithChildren($div, $div);
                        $changed = true;
                        break;
                    }
                }
            }
            if (!$changed) {
                break;
            }
            $body = $doc->getElementsByTagName('body')->item(0);
            $html = '';
            foreach ($body->childNodes as $child) {
                $html .= $doc->saveHTML($child);
            }
            $html = self::fixVariables($html);
        }

        return $html;
    }

    protected static function isSpacer(DOMNode $cell)
    {
        if (trim(str_replace("\xC2\xA0", ' ', $cell->textContent)) !== '') {
            return false;
        }
        foreach ($cell->getElementsByTagName('*') as $el) {
            if (in_array($el->nodeName, ['img', 'a', 'hr', 'input', 'table'], true)) {
                return false;
            }
        }

        return true;
    }

    protected static function replaceWithChildren(DOMNode $node, DOMNode $source)
    {
        $parent = $node->parentNode;
        $children = [];
        foreach ($source->childNodes as $c) {
            $children[] = $c;
        }
        foreach ($children as $c) {
            $parent->insertBefore($c, $node);
        }
        $parent->removeChild($node);
    }
}
