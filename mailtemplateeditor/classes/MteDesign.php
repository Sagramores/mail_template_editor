<?php
/**
 * Mail Template Editor - the e-mail designs (layouts) and their rendering.
 *
 * A design is an HTML file in views/layouts/ that contains [[tokens]].
 * PrestaShop variables such as {shop_name} are left untouched so Mail::send()
 * replaces them as usual.
 */
if (!defined('_PS_VERSION_') && !defined('MTE_TESTING')) {
    exit;
}

class MteDesign
{
    /** Built-in designs (views/layouts/<name>.html). Designs made with the builder are "custom_<id>". */
    const LAYOUTS = ['prestashop', 'classic', 'modern', 'minimal'];

    /** Tokens that become drop-in blocks in the visual builder. */
    const BLOCK_TOKENS = ['content', 'logo', 'social', 'footer', 'footer_columns'];

    /** Text size PrestaShop e-mails are designed for. */
    const BASE_FONT_SIZE = 14;

    const SOCIAL = ['facebook' => 'Facebook', 'instagram' => 'Instagram', 'x' => 'X', 'youtube' => 'YouTube', 'tiktok' => 'TikTok', 'linkedin' => 'LinkedIn', 'pinterest' => 'Pinterest'];

    const FONTS = [
        'opensans' => '"Open Sans", Arial, Helvetica, sans-serif',
        'arial' => 'Arial, Helvetica, sans-serif',
        'helvetica' => '"Helvetica Neue", Helvetica, Arial, sans-serif',
        'verdana' => 'Verdana, Geneva, sans-serif',
        'tahoma' => 'Tahoma, Verdana, sans-serif',
        'trebuchet' => '"Trebuchet MS", Tahoma, sans-serif',
        'segoe' => '"Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif',
        'georgia' => 'Georgia, "Times New Roman", serif',
        'times' => '"Times New Roman", Times, serif',
        'courier' => '"Courier New", Courier, monospace',
    ];

    public static function isCustom($layout)
    {
        return (bool) preg_match('/^custom_[0-9]+$/', (string) $layout);
    }

    public static function isValid($layout)
    {
        return in_array($layout, self::LAYOUTS, true) || self::isCustom($layout);
    }

    /**
     * Default settings of each design.
     */
    public static function defaults($layout)
    {
        if (self::isCustom($layout)) {
            $layout = 'modern';
        }
        $common = [
            'font_family' => 'arial',
            'font_size' => 14,
            'heading_size' => 22,
            'small_size' => 12,
            'line_height' => 1.6,
            'width' => 620,
            'radius' => 6,
            'show_logo' => 1,
            'logo_width' => 180,
            'logo_align' => 'center',
            'show_shop_name' => 0,
            'uppercase_headings' => 0,
        ];
        switch ($layout) {
            case 'prestashop':
                // Same look as the default PrestaShop 1.7.6+ / 8 / 9 "modern" mail theme.
                return array_merge($common, [
                    'font_family' => 'opensans',
                    'font_size' => 14,
                    'heading_size' => 18,
                    'small_size' => 12,
                    'line_height' => 1.8,
                    'width' => 604,
                    'radius' => 4,
                    'logo_width' => 200,
                    'logo_align' => 'center',
                    'page_bg' => '#eeeeee',
                    'header_bg' => '#ffffff',
                    'header_text' => '#363a41',
                    'accent' => '#363a41',
                    'content_bg' => '#ffffff',
                    'text_color' => '#363a41',
                    'heading_color' => '#363a41',
                    'link_color' => '#25b9d7',
                    'button_bg' => '#25b9d7',
                    'button_text' => '#ffffff',
                    'border_color' => '#dfdfdf',
                    'footer_bg' => '#eeeeee',
                    'footer_text' => '#656565',
                ]);
            case 'modern':
                return array_merge($common, [
                    'font_family' => 'segoe',
                    'font_size' => 15,
                    'heading_size' => 26,
                    'radius' => 14,
                    'page_bg' => '#eef1f6',
                    'header_bg' => '#ffffff',
                    'header_text' => '#1f2937',
                    'accent' => '#4f46e5',
                    'content_bg' => '#ffffff',
                    'text_color' => '#374151',
                    'heading_color' => '#111827',
                    'link_color' => '#4f46e5',
                    'button_bg' => '#4f46e5',
                    'button_text' => '#ffffff',
                    'border_color' => '#e5e7eb',
                    'footer_bg' => '#eef1f6',
                    'footer_text' => '#6b7280',
                ]);
            case 'minimal':
                return array_merge($common, [
                    'font_family' => 'georgia',
                    'font_size' => 16,
                    'heading_size' => 24,
                    'small_size' => 12,
                    'line_height' => 1.7,
                    'width' => 580,
                    'radius' => 0,
                    'logo_width' => 140,
                    'logo_align' => 'left',
                    'page_bg' => '#ffffff',
                    'header_bg' => '#ffffff',
                    'header_text' => '#111111',
                    'accent' => '#111111',
                    'content_bg' => '#ffffff',
                    'text_color' => '#333333',
                    'heading_color' => '#111111',
                    'link_color' => '#111111',
                    'button_bg' => '#111111',
                    'button_text' => '#ffffff',
                    'border_color' => '#e6e6e6',
                    'footer_bg' => '#ffffff',
                    'footer_text' => '#888888',
                ]);
            case 'classic':
            default:
                return array_merge($common, [
                    'font_size' => 14,
                    'heading_size' => 20,
                    'radius' => 4,
                    'uppercase_headings' => 1,
                    'page_bg' => '#f2f2f2',
                    'header_bg' => '#333333',
                    'header_text' => '#ffffff',
                    'accent' => '#e2a60c',
                    'content_bg' => '#ffffff',
                    'text_color' => '#555555',
                    'heading_color' => '#333333',
                    'link_color' => '#337ab7',
                    'button_bg' => '#e2a60c',
                    'button_text' => '#ffffff',
                    'border_color' => '#d6d4d4',
                    'footer_bg' => '#333333',
                    'footer_text' => '#cccccc',
                ]);
        }
    }

    /**
     * Validates/normalises user settings against the defaults of a layout.
     */
    public static function sanitize($layout, array $input)
    {
        $layout = self::isValid($layout) ? $layout : 'classic';
        $out = self::defaults($layout);
        foreach ($out as $key => $default) {
            if (!array_key_exists($key, $input)) {
                continue;
            }
            $value = $input[$key];
            if (is_string($default) && $default !== '' && $default[0] === '#') {
                if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', (string) $value)) {
                    $out[$key] = strtolower($value);
                }
            } elseif ($key === 'font_family') {
                $out[$key] = isset(self::FONTS[$value]) ? $value : $default;
            } elseif ($key === 'logo_align') {
                $out[$key] = in_array($value, ['left', 'center', 'right'], true) ? $value : $default;
            } elseif (is_float($default)) {
                $out[$key] = max(1.0, min(2.5, (float) $value));
            } elseif (in_array($key, ['show_logo', 'show_shop_name', 'uppercase_headings'], true)) {
                $out[$key] = (int) (bool) $value;
            } else {
                $limits = ['font_size' => [10, 24], 'heading_size' => [14, 48], 'small_size' => [9, 18], 'width' => [400, 900], 'radius' => [0, 30], 'logo_width' => [40, 600]];
                $l = isset($limits[$key]) ? $limits[$key] : [0, 1000];
                $out[$key] = max($l[0], min($l[1], (int) $value));
            }
        }

        return $out;
    }

    /**
     * @param string $layout
     * @param array $s sanitized settings
     * @param string $content inner HTML of the e-mail
     * @param array $extra footer_html, about_html, contact_html, social[], logo (bool), title, preheader, lang_iso, rtl,
     *                     layout_html (HTML of a custom design), keep_tokens (bool, output for the visual builder)
     *
     * @return string full e-mail HTML
     */
    public static function render($layout, array $s, $content, array $extra = [])
    {
        $layout = self::isValid($layout) ? $layout : 'classic';
        if (self::isCustom($layout)) {
            $tpl = isset($extra['layout_html']) ? (string) $extra['layout_html'] : '';
            if (strpos($tpl, '[[content]]') === false) {
                // A custom design always needs the content: fall back to a built-in one.
                $layout = 'modern';
                $tpl = '';
            }
        }
        if (!self::isCustom($layout)) {
            $tpl = (string) file_get_contents(dirname(__DIR__) . '/views/layouts/' . $layout . '.html');
        }
        $s = self::sanitize($layout, $s);
        $font = self::FONTS[$s['font_family']];

        $logo = '';
        if (!empty($s['show_logo']) && !empty($extra['logo'])) {
            $logo = '<a href="{shop_url}" style="text-decoration:none;border:0;"><img src="{shop_logo}" alt="{shop_name}" width="' . (int) $s['logo_width'] . '" style="display:inline-block;max-width:' . (int) $s['logo_width'] . 'px;width:100%;height:auto;border:0;outline:none;text-decoration:none;" /></a>';
        }
        if ($logo === '' || !empty($s['show_shop_name'])) {
            $logo .= ($logo ? '<br />' : '') . '<a href="{shop_url}" style="font-family:' . self::attr($font) . ';font-size:' . ((int) $s['heading_size'] + 2) . 'px;font-weight:bold;color:' . $s['header_text'] . ';text-decoration:none;">{shop_name}</a>';
        }

        $social = [];
        foreach (self::SOCIAL as $key => $label) {
            if (!empty($extra['social'][$key]) && preg_match('#^https?://#i', $extra['social'][$key])) {
                $social[] = '<a href="' . self::attr($extra['social'][$key]) . '" style="display:inline-block;margin:0 4px 6px;padding:6px 12px;border:1px solid ' . $s['footer_text'] . ';border-radius:' . min(20, (int) $s['radius'] + 10) . 'px;color:' . $s['footer_text'] . ';font-size:' . (int) $s['small_size'] . 'px;text-decoration:none;">' . $label . '</a>';
            }
        }

        $columns = '';
        $about = isset($extra['about_html']) ? trim((string) $extra['about_html']) : '';
        $contact = isset($extra['contact_html']) ? trim((string) $extra['contact_html']) : '';
        if ($about !== '' || $contact !== '') {
            $cells = [];
            foreach (array_filter([$about, $contact], 'strlen') as $html) {
                $cells[] = '<td class="mte-col" valign="top" style="padding:0 10px 10px;font-size:' . (int) $s['small_size'] . 'px;line-height:1.6;color:' . $s['footer_text'] . ';text-align:left;">' . $html . '</td>';
            }
            $columns = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>' . implode('', $cells) . '</tr></table>';
        }

        $content = self::styleContent($content, $s, $font);

        $tokens = [
            'lang' => self::attr(isset($extra['lang_iso']) ? $extra['lang_iso'] : 'en'),
            'dir' => !empty($extra['rtl']) ? 'rtl' : 'ltr',
            'title' => htmlspecialchars(isset($extra['title']) ? $extra['title'] : '{shop_name}', ENT_QUOTES, 'UTF-8'),
            'preheader' => htmlspecialchars(isset($extra['preheader']) ? $extra['preheader'] : '', ENT_QUOTES, 'UTF-8'),
            'font' => self::attr($font),
            'logo' => $logo,
            'content' => $content,
            'social' => implode(' ', $social),
            'footer_columns' => $columns,
            'footer' => isset($extra['footer_html']) ? (string) $extra['footer_html'] : '',
            'heading_transform' => $s['uppercase_headings'] ? 'uppercase' : 'none',
            'radius_small' => (string) min((int) $s['radius'], 8),
            'width_inner' => (string) ((int) $s['width'] - 2),
            // Same web font as the PrestaShop e-mails (clients without web fonts use Arial).
            'font_import' => $s['font_family'] === 'opensans' ? '@import url(https://fonts.googleapis.com/css?family=Open+Sans:400,600,700);' : '',
        ];
        foreach ($s as $key => $value) {
            if (!isset($tokens[$key])) {
                $tokens[$key] = is_string($value) ? self::attr($value) : (string) $value;
            }
        }
        if (!empty($extra['keep_tokens'])) {
            // Starting point for the visual builder: dynamic parts stay as tokens, each
            // in its own element so the builder shows them as blocks.
            foreach (self::BLOCK_TOKENS as $name) {
                $tokens[$name] = self::tokenBlock($name);
            }
            $tpl = preg_replace('/<!--mte:(?:if [a-z_]+|endif)-->/', '', $tpl);
        }
        // Remove optional blocks whose token was empty: <!--mte:if social-->...<!--mte:endif-->
        $tpl = preg_replace_callback('/<!--mte:if ([a-z_]+)-->(.*?)<!--mte:endif-->/s', function ($m) use ($tokens) {
            return isset($tokens[$m[1]]) && trim(strip_tags($tokens[$m[1]], '<img><a>')) !== '' ? $m[2] : '';
        }, $tpl);

        $tpl = preg_replace_callback('/\[\[([a-z_]+)\]\]/', function ($m) use ($tokens) {
            return isset($tokens[$m[1]]) ? $tokens[$m[1]] : '';
        }, $tpl);

        return MteContentExtractor::restoreTableVars($tpl);
    }

    /**
     * Placeholder element of a token inside a custom design.
     */
    public static function tokenBlock($name)
    {
        return '<div data-mte-token="' . $name . '">[[' . $name . ']]</div>';
    }

    /**
     * Cleans the HTML of a design coming from the visual builder.
     *
     * @return string|false false when the [[content]] placeholder is missing
     */
    public static function cleanCustomHtml($html, $logoUrl = '')
    {
        $html = MteEncoding::toUtf8((string) $html);
        // The builder shows the real logo; e-mails must use the {shop_logo} variable.
        if ($logoUrl !== '') {
            $html = str_replace([$logoUrl, htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8')], '{shop_logo}', $html);
        }
        $html = MteContentExtractor::fixVariables($html);
        $html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html);
        $html = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\')/i', '', $html);
        if (strpos($html, '[[content]]') === false) {
            return false;
        }
        if (!preg_match('/<html/i', $html)) {
            $html = "<!DOCTYPE html>\n<html lang=\"[[lang]]\" dir=\"[[dir]]\">\n<head>\n<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" />\n"
                . "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\" />\n<title>[[title]]</title>\n"
                . "<style type=\"text/css\">body{margin:0;padding:0;-webkit-text-size-adjust:100%;} img{border:0;max-width:100%;} table{border-collapse:collapse;}</style>\n"
                . "</head>\n" . (preg_match('/^\s*<body/i', $html) ? $html : "<body style=\"margin:0;padding:0;\">\n" . $html . "\n</body>") . "\n</html>\n";
        }

        return $html;
    }

    /**
     * Styles the design gives to the content elements: [tag rules, button, box].
     */
    public static function contentRules(array $s, $font)
    {
        $transform = $s['uppercase_headings'] ? 'text-transform:uppercase;' : '';
        $rules = [
            'h1' => 'margin:0 0 16px;font-family:' . $font . ';font-size:' . $s['heading_size'] . 'px;line-height:1.3;color:' . $s['heading_color'] . ';font-weight:bold;' . $transform,
            'h2' => 'margin:24px 0 12px;font-family:' . $font . ';font-size:' . round($s['heading_size'] * 0.8) . 'px;line-height:1.3;color:' . $s['heading_color'] . ';font-weight:bold;' . $transform,
            'h3' => 'margin:0 0 10px;font-family:' . $font . ';font-size:' . round($s['heading_size'] * 0.68) . 'px;line-height:1.3;color:' . $s['heading_color'] . ';font-weight:bold;',
            'p' => 'margin:0 0 14px;font-family:' . $font . ';font-size:' . $s['font_size'] . 'px;line-height:' . $s['line_height'] . ';color:' . $s['text_color'] . ';',
            'li' => 'font-family:' . $font . ';font-size:' . $s['font_size'] . 'px;line-height:' . $s['line_height'] . ';color:' . $s['text_color'] . ';',
            'td' => 'font-family:' . $font . ';font-size:' . $s['font_size'] . 'px;line-height:' . $s['line_height'] . ';color:' . $s['text_color'] . ';',
            'th' => 'font-family:' . $font . ';font-size:' . $s['font_size'] . 'px;color:' . $s['heading_color'] . ';background-color:' . $s['page_bg'] . ';border-bottom:2px solid ' . $s['border_color'] . ';padding:8px;',
            'a' => 'color:' . $s['link_color'] . ';',
            'hr' => 'border:0;border-top:1px solid ' . $s['border_color'] . ';margin:20px 0;',
        ];
        $button = 'display:inline-block;padding:12px 26px;background-color:' . $s['button_bg'] . ';color:' . $s['button_text'] . ';border-radius:' . min(30, $s['radius'] + 2) . 'px;font-family:' . $font . ';font-size:' . $s['font_size'] . 'px;font-weight:bold;text-decoration:none;';
        $box = 'padding:16px 18px;margin:0 0 16px;border:1px solid ' . $s['border_color'] . ';border-radius:' . $s['radius'] . 'px;background-color:' . $s['content_bg'] . ';';


        return [$rules, $button, $box];
    }

    /**
     * The same rules as CSS, for the visual editor (inline styles of the content win, as in the e-mail).
     */
    public static function contentCss(array $s, $scope)
    {
        $font = isset(self::FONTS[$s['font_family']]) ? self::FONTS[$s['font_family']] : self::FONTS['arial'];
        list($rules, $button, $box) = self::contentRules($s, $font);
        $css = $scope . '{font-family:' . $font . ';font-size:' . (int) $s['font_size'] . 'px;line-height:' . $s['line_height'] . ';color:' . $s['text_color'] . ';}';
        foreach ($rules as $tag => $rule) {
            $css .= $scope . ' ' . $tag . '{' . $rule . '}';
        }
        $css .= $scope . ' .mte-button{' . $button . '}' . $scope . ' .mte-box{' . $box . '}';

        return $css;
    }

    /**
     * Adds inline styles to the content tags (many e-mail clients ignore <style>).
     * Styles already present on an element win.
     */
    public static function styleContent($html, array $s, $font)
    {
        if (trim((string) $html) === '') {
            return '';
        }
        list($rules, $button, $box) = self::contentRules($s, $font);

        $doc = MteContentExtractor::load($html);
        if (!$doc) {
            return $html;
        }
        $xpath = new DOMXPath($doc);
        // The text size of the design scales every size of the e-mail (PrestaShop e-mails
        // are made for 14px), so headings and small print keep their proportions.
        $factor = (float) $s['font_size'] / self::BASE_FONT_SIZE;
        if (abs($factor - 1) > 0.001) {
            foreach ($xpath->query('//body//*[@style]') as $el) {
                $el->setAttribute('style', self::scaleSizes($el->getAttribute('style'), $factor));
            }
        }
        // Component classes first, so their colours win over the generic tag rules below.
        foreach ($xpath->query('//body//*[contains(concat(" ", normalize-space(@class), " "), " mte-button ")]') as $el) {
            $el->setAttribute('style', self::mergeStyle($button, $el->getAttribute('style')));
        }
        foreach ($xpath->query('//body//*[contains(concat(" ", normalize-space(@class), " "), " mte-box ")]') as $el) {
            $el->setAttribute('style', self::mergeStyle($box, $el->getAttribute('style')));
        }
        foreach ($rules as $tag => $rule) {
            foreach ($xpath->query('//body//' . $tag) as $el) {
                $el->setAttribute('style', self::mergeStyle($rule, $el->getAttribute('style')));
            }
        }
        foreach ($xpath->query('/html/body/table') as $el) {
            $el->setAttribute('style', self::mergeStyle('margin:0 0 18px;', $el->getAttribute('style')));
        }
        $out = '';
        foreach ($doc->getElementsByTagName('body')->item(0)->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return MteContentExtractor::fixVariables($out);
    }

    /**
     * Multiplies the px font sizes and line heights of a style attribute.
     */
    public static function scaleSizes($style, $factor)
    {
        return preg_replace_callback('/(^|;)(\s*)(font-size|line-height)(\s*:\s*)([0-9.]+)px/i', function ($m) use ($factor) {
            return $m[1] . $m[2] . $m[3] . $m[4] . round((float) $m[5] * $factor) . 'px';
        }, (string) $style);
    }

    /**
     * Base rules + element's own rules (own rules override the base ones).
     */
    protected static function mergeStyle($base, $own)
    {
        $props = [];
        foreach ([$base, (string) $own] as $style) {
            foreach (explode(';', $style) as $decl) {
                $parts = explode(':', $decl, 2);
                if (count($parts) === 2 && trim($parts[0]) !== '') {
                    $props[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
            }
        }
        $out = '';
        foreach ($props as $k => $v) {
            $out .= $k . ':' . $v . ';';
        }

        return $out;
    }

    protected static function attr($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Plain-text version of an HTML e-mail body.
     */
    public static function toText($html)
    {
        // HTML-only variables have a plain-text twin in PrestaShop.
        $html = strtr(MteContentExtractor::restoreTableVars($html), [
            '{products}' => '{products_txt}',
            '{discounts}' => '{discounts_txt}',
            '{delivery_block_html}' => '{delivery_block_txt}',
            '{invoice_block_html}' => '{invoice_block_txt}',
        ]);
        $html = preg_replace('#<(script|style|head)[^>]*>.*?</\1>#is', '', (string) $html);
        $html = preg_replace_callback('#<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>#is', function ($m) {
            $text = trim(strip_tags($m[2]));

            return $text === '' || $text === $m[1] ? $m[1] : $text . ' (' . $m[1] . ')';
        }, $html);
        $html = preg_replace('#<br\s*/?>#i', "\n", $html);
        $html = preg_replace('#</(p|div|h[1-6]|tr|li|table|ul|ol)>#i', "\n", $html);
        $html = preg_replace('#<li[^>]*>#i', '- ', $html);
        $html = preg_replace('#</t[dh]>#i', "\t", $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t\x{00A0}]+/u", ' ', $text);
        $text = preg_replace("/ *\n */", "\n", $text);

        return trim(preg_replace("/\n{3,}/", "\n\n", $text));
    }
}
