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

        // MJML gives the column widths through CSS classes in <head>: make them inline first.
        foreach ($xpath->query('//*[contains(@class, "mj-column-")]') as $col) {
            if (preg_match('/\bmj-column-(per|px)-([0-9]+(?:-[0-9]+)?)\b/', $col->getAttribute('class'), $m)) {
                $width = str_replace('-', '.', $m[2]) . ($m[1] === 'per' ? '%' : 'px');
                $style = preg_replace('/(^|;)\s*(max-width|width)\s*:[^;]*/i', '$1', $col->getAttribute('style'));
                $col->setAttribute('style', self::cleanStyle($style . ';width:' . $width . ';max-width:' . $width));
            }
        }

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
        // Footer: "powered by PrestaShop".
        foreach ($xpath->query('//text()[contains(translate(., "POWERED", "powered"), "powered by")]') as $node) {
            self::removeBlock($node, ['tr', 'div', 'p', 'table'], 'powered');
        }

        $body = $doc->getElementsByTagName('body')->item(0);
        if (!$body) {
            return '';
        }
        // The outer frame (page background, card, fixed width) comes from the design.
        self::removeFrame($body);
        foreach ($xpath->query('//*[contains(@style, "box-shadow") or contains(concat(" ", normalize-space(@class), " "), " wrapper-container ") or contains(concat(" ", normalize-space(@class), " "), " shadow ")]') as $el) {
            self::stripFrameStyle($el);
        }
        // Wide fixed widths would overflow the design: full width instead.
        foreach ($xpath->query('//*[@style]') as $el) {
            $style = preg_replace_callback('/(^|;)(\s*)(width|min-width)\s*:\s*([0-9.]+)px/i', function ($m) {
                return (float) $m[4] > 300 ? $m[1] . $m[2] . $m[3] . ':100%' : $m[0];
            }, $el->getAttribute('style'));
            $el->setAttribute('style', $style);
        }
        foreach ($xpath->query('//*[@width]') as $el) {
            if ((int) $el->getAttribute('width') > 300 && $el->nodeName !== 'img') {
                $el->setAttribute('width', '100%');
            }
        }
        // MJML side gutters (cells with font-size:0 and padding, e.g. "padding:10px 25px"):
        // the design gives the side margins, as the card does in the original e-mail.
        foreach ($xpath->query('//*[@style and contains(translate(@style, " ", ""), "font-size:0px") and contains(@style, "padding")]') as $el) {
            // Not inside a visible box: there the padding keeps the text off the border.
            $inBox = false;
            for ($up = $el->parentNode; $up instanceof DOMElement && $up->nodeName !== 'body'; $up = $up->parentNode) {
                if (self::isVisualBox($up) && !self::isLightBackgroundOnly($up)) {
                    $inBox = true;
                    break;
                }
            }
            if (!$inBox) {
                $el->setAttribute('style', self::zeroSidePadding($el->getAttribute('style')));
            }
        }
        // Spacing before the first and after the last content is given by the design.
        self::trimEmptyEdges($xpath);

        if ($normalizeStyles) {
            self::normalize($xpath);
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
     * For the module's designs: removes the fonts and text colours of the original
     * e-mail so the design ones apply. Layout (widths, paddings, borders, spacing,
     * font sizes) is kept exactly as in the original; sizes are scaled by the design.
     */
    protected static function normalize(DOMXPath $xpath)
    {
        foreach ($xpath->query('//*[@style]') as $el) {
            $style = preg_replace('/(^|;)\s*(font-family|color|letter-spacing)\s*:[^;]*/i', '$1', $el->getAttribute('style'));
            // Light backgrounds (boxes, tables) stay; strong colours belong to the old design.
            $style = preg_replace_callback('/(^|;)\s*(background-color|background)\s*:([^;]*)/i', function ($m) {
                return self::isLightColor($m[3]) ? $m[0] : $m[1];
            }, $style);
            $style = self::cleanStyle($style);
            if ($style === '') {
                $el->removeAttribute('style');
            } else {
                $el->setAttribute('style', $style);
            }
        }
        foreach (['color', 'face'] as $attr) {
            foreach ($xpath->query('//*[@' . $attr . ']') as $el) {
                $el->removeAttribute($attr);
            }
        }
        foreach ($xpath->query('//*[@bgcolor]') as $el) {
            if (!self::isLightColor($el->getAttribute('bgcolor'))) {
                $el->removeAttribute('bgcolor');
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

    protected static function isLightColor($value)
    {
        $value = trim(strtolower((string) $value));
        if (in_array($value, ['', 'transparent', 'none', 'white', '#fff', '#ffffff', 'inherit'], true)) {
            return true;
        }
        if (preg_match('/#([0-9a-f]{3}|[0-9a-f]{6})\b/', $value, $m)) {
            $hex = strlen($m[1]) === 3 ? $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2] : $m[1];

            return hexdec(substr($hex, 0, 2)) >= 0xE6 && hexdec(substr($hex, 2, 2)) >= 0xE6 && hexdec(substr($hex, 4, 2)) >= 0xE6;
        }

        return false;
    }

    /**
     * "padding:10px 25px" -> "padding:10px 0" (vertical spacing kept).
     */
    protected static function zeroSidePadding($style)
    {
        $out = [];
        foreach (explode(';', (string) $style) as $decl) {
            $parts = explode(':', $decl, 2);
            if (count($parts) !== 2) {
                continue;
            }
            $prop = strtolower(trim($parts[0]));
            $value = trim($parts[1]);
            if ($prop === 'padding-left' || $prop === 'padding-right') {
                continue;
            }
            if ($prop === 'padding') {
                $v = preg_split('/\s+/', $value);
                $top = $v[0];
                $bottom = isset($v[2]) ? $v[2] : $top;
                $value = $top . ' 0 ' . $bottom;
            }
            $out[] = $prop . ':' . $value;
        }

        return implode(';', $out);
    }

    public static function cleanStyle($style)
    {
        return trim(preg_replace('/\s*;[\s;]*/', ';', (string) $style), "; \t\n");
    }

    /**
     * Removes empty spacer elements found before the first or after the last real content.
     */
    protected static function trimEmptyEdges(DOMXPath $xpath)
    {
        $isContent = function (DOMNode $n) {
            if ($n instanceof DOMElement && in_array($n->nodeName, ['img', 'hr', 'input'], true)) {
                return true;
            }

            return $n->nodeType === XML_TEXT_NODE && trim(str_replace("\xC2\xA0", ' ', $n->nodeValue)) !== ''
                || $n->nodeType === XML_COMMENT_NODE;
        };
        foreach (['first', 'last'] as $edge) {
            for ($i = 0; $i < 200; ++$i) {
                $body = $xpath->query('//body')->item(0);
                $node = $body;
                // Walk down the first/last element children to the first/last leaf.
                $removed = false;
                while ($node) {
                    $child = $edge === 'first' ? $node->firstChild : $node->lastChild;
                    while ($child && $child->nodeType === XML_TEXT_NODE && trim(str_replace("\xC2\xA0", ' ', $child->nodeValue)) === '') {
                        $child = $edge === 'first' ? $child->nextSibling : $child->previousSibling;
                    }
                    if (!$child) {
                        break;
                    }
                    $hasContent = $isContent($child);
                    if (!$hasContent && $child instanceof DOMElement) {
                        foreach ($xpath->query('.//text()|.//img|.//hr|.//comment()', $child) as $n) {
                            if ($isContent($n)) {
                                $hasContent = true;
                                break;
                            }
                        }
                    }
                    if (!$hasContent) {
                        $child->parentNode->removeChild($child);
                        $removed = true;
                        break;
                    }
                    if (!$child instanceof DOMElement || in_array($child->nodeName, ['table', 'p', 'img', 'a', 'span', 'strong', 'b', 'ul', 'ol'], true)) {
                        break;
                    }
                    $node = $child;
                }
                if (!$removed) {
                    break;
                }
            }
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
                if (count($real) !== 1 || self::isVisualBox($table) || self::isVisualBox($rows->item(0)) || self::isVisualBox($real[0])) {
                    continue;
                }
                $cell = $real[0];
                $keep = self::layoutStyle($cell->getAttribute('style'));
                if ($cell->getAttribute('align') && strpos($keep, 'text-align') === false) {
                    $keep = self::cleanStyle($keep . ';text-align:' . $cell->getAttribute('align'));
                }
                if ($keep === '') {
                    self::replaceWithChildren($table, $cell);
                } else {
                    // The cell's padding/alignment give the spacing of the original: keep them on a div.
                    $div = $doc->createElement('div');
                    $div->setAttribute('style', $keep);
                    while ($cell->firstChild) {
                        $div->appendChild($cell->firstChild);
                    }
                    $table->parentNode->replaceChild($div, $table);
                }
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

    /**
     * Borders or a background make a table/cell a visible box: never unwrap it.
     */
    protected static function isVisualBox(DOMNode $el)
    {
        if (!$el instanceof DOMElement) {
            return false;
        }
        if ($el->hasAttribute('bgcolor') || ($el->hasAttribute('border') && (int) $el->getAttribute('border') > 0)) {
            return true;
        }
        $style = strtolower($el->getAttribute('style'));
        if (preg_match('/(^|;)\s*border(-top|-bottom|-left|-right)?\s*:\s*(?!0|none)[^;]+/', $style)) {
            return true;
        }

        return (bool) preg_match('/(^|;)\s*background(-color)?\s*:\s*(?!transparent|none)[^;]+/', $style);
    }

    /**
     * A background without border (e.g. the light band behind the products table) is not a box.
     */
    protected static function isLightBackgroundOnly(DOMElement $el)
    {
        $style = strtolower($el->getAttribute('style'));

        return !preg_match('/(^|;)\s*border(-top|-bottom|-left|-right)?\s*:\s*(?!0|none)[^;]+/', $style)
            && !($el->hasAttribute('border') && (int) $el->getAttribute('border') > 0);
    }

    /**
     * Declarations of a layout cell worth keeping when it becomes a div.
     */
    protected static function layoutStyle($style)
    {
        $keep = [];
        foreach (explode(';', (string) $style) as $decl) {
            $parts = explode(':', $decl, 2);
            if (count($parts) !== 2) {
                continue;
            }
            $prop = strtolower(trim($parts[0]));
            $value = trim($parts[1]);
            if (preg_match('/^padding(-top|-bottom|-left|-right)?$/', $prop) && !preg_match('/^0(px)?(\s+0(px)?)*$/', $value)) {
                $keep[] = $prop . ':' . $value;
            } elseif (in_array($prop, ['text-align', 'direction', 'font-size', 'line-height', 'font-weight', 'color', 'font-family'], true)) {
                $keep[] = $prop . ':' . $value;
            }
        }

        return implode(';', $keep);
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
