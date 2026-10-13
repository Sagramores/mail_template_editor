<?php
/**
 * Stand-alone tests (no PrestaShop needed): php tests/run.php
 */
define('MTE_TESTING', true);
$dir = dirname(__DIR__) . '/mailtemplateeditor/classes/';
require $dir . 'MteEncoding.php';
require $dir . 'MteContentExtractor.php';
require $dir . 'MteDesign.php';
require $dir . 'MteScanner.php';

$fail = 0;
$count = 0;
function check($name, $cond, $info = '')
{
    global $fail, $count;
    ++$count;
    if (!$cond) {
        ++$fail;
        echo "FAIL: $name " . ($info !== '' ? "\n  " . substr(var_export($info, true), 0, 400) : '') . "\n";
    }
}

$greek = 'Γειά σου Νίκο! Η παραγγελία σας ΑΝΑ-ΩΝ είναι έτοιμη. Ά Έ Ή Ί Ό Ύ Ώ ΐ ΰ';

// --- encoding
check('utf8 passthrough', MteEncoding::toUtf8($greek) === $greek);
check('bom stripped', MteEncoding::toUtf8("\xEF\xBB\xBF" . $greek) === $greek);
check('windows-1253', MteEncoding::toUtf8(iconv('UTF-8', 'WINDOWS-1253', $greek)) === $greek, MteEncoding::toUtf8(iconv('UTF-8', 'WINDOWS-1253', $greek)));
check('iso-8859-7', MteEncoding::toUtf8(iconv('UTF-8', 'ISO-8859-7', $greek)) === $greek);
$moji = mb_convert_encoding($greek, 'UTF-8', 'Windows-1252');
check('mojibake cp1252', MteEncoding::repairMojibake($moji) === $greek, MteEncoding::repairMojibake($moji));
$moji1 = mb_convert_encoding($greek, 'UTF-8', 'ISO-8859-1');
check('mojibake latin1', MteEncoding::repairMojibake($moji1) === $greek);
check('double mojibake', MteEncoding::repairMojibake(mb_convert_encoding($moji, 'UTF-8', 'Windows-1252')) === $greek);
check('no false repair', MteEncoding::repairMojibake('Café, naïve, Zürich – “quotes” ' . $greek) === 'Café, naïve, Zürich – “quotes” ' . $greek);
check('entities decoded', MteEncoding::decodeTextEntities('&Gamma;&epsilon;&#953;&#x3AC; &lt;b&gt; &amp; &nbsp; &quot;') === 'Γειά &lt;b&gt; &amp; &nbsp; &quot;');
check('transport', MteEncoding::decodeTransport(base64_encode($greek)) === $greek);
check('transport invalid', MteEncoding::decodeTransport('###') === false);
$tmp = sys_get_temp_dir() . '/mte_test_' . getmypid() . '.html';
MteEncoding::writeFile($tmp, iconv('UTF-8', 'WINDOWS-1253', $greek));
check('write as utf8', file_get_contents($tmp) === $greek);
@unlink($tmp);

// --- extractor
$fx = __DIR__ . '/fixtures/';
$out = MteContentExtractor::extract(file_get_contents($fx . 'ps17_order_conf.html'));
check('ps17: logo removed', strpos($out, '{shop_logo}') === false);
check('ps17: powered removed', stripos($out, 'powered by') === false);
check('ps17: content kept', strpos($out, '{firstname}') !== false && strpos($out, '{products}') !== false && strpos($out, '{history_url}') !== false);
check('ps17: href vars intact', strpos($out, 'href="{history_url}"') !== false && strpos($out, '%7B') === false);

$out = MteContentExtractor::extract(file_get_contents($fx . 'mjml_greek.html'));
check('mjml: greek kept', strpos($out, 'Ευχαριστούμε για την αγορά σας') !== false, $out);
check('mjml: logo removed', strpos($out, '{shop_logo}') === false, $out);
check('mjml: footer removed', stripos($out, 'powered') === false && stripos($out, 'prestashop.com') === false, $out);
check('mjml: fonts removed', stripos($out, 'Open sans') === false && stripos($out, '#363A41') === false, $out);
check('mjml: no comments', strpos($out, '<!--') === false);
check('mjml: data table kept', strpos($out, '<th>Προϊόν</th>') !== false, $out);
$raw = MteContentExtractor::extract(file_get_contents($fx . 'mjml_greek.html'), false);
check('mjml: styles kept without normalize', stripos($raw, 'Open sans') !== false);

// real PrestaShop 8.2 templates (generated from the "modern" mail theme)
$out = MteContentExtractor::extract(file_get_contents($fx . 'ps8_order_conf.html'));
check('ps8: products protected inside table', (bool) preg_match('/<\/tr>\s*<!--mte:\{products\}-->\s*<!--mte:\{discounts\}-->\s*<tr/', $out), $out);
check('ps8: logo/footer removed', strpos($out, '{shop_logo}') === false && stripos($out, 'powered by') === false);
check('ps8: no fixed fonts', stripos($out, 'Open sans') === false);
$full = MteDesign::render('modern', MteDesign::defaults('modern'), $out, ['logo' => true]);
check('ps8: products restored on render', (bool) preg_match('/<\/tr>\s*\{products\}\s*\{discounts\}\s*<tr/', $full));
check('ps8: txt restores vars', strpos(MteDesign::toText($out), '{products_txt}') !== false);
$out = MteContentExtractor::extract(file_get_contents($fx . 'ps8_account.html'));
check('ps8 account: content', strpos($out, '{firstname}') !== false && strpos($out, '{email}') !== false && strpos($out, '{shop_logo}') === false, $out);

// --- design
foreach (MteDesign::LAYOUTS as $layout) {
    $content = '<h1>Τίτλος</h1><p>Γεια σας {firstname},</p><p><a class="mte-button" href="{shop_url}">Κουμπί</a></p><div class="mte-box">Κουτί</div>';
    $html = MteDesign::render($layout, MteDesign::defaults($layout), $content, [
        'lang_iso' => 'el', 'logo' => true, 'footer_html' => 'Υποσέλιδο <b>έντονο</b>',
        'about_html' => 'Σχετικά', 'contact_html' => '', 'social' => ['facebook' => 'https://facebook.com/x', 'x' => 'javascript:alert(1)'],
    ]);
    check("$layout: no tokens left", !preg_match('/\[\[[a-z_]+\]\]/', $html), $html);
    check("$layout: greek kept", strpos($html, 'Γεια σας {firstname}') !== false && strpos($html, 'Υποσέλιδο <b>έντονο</b>') !== false);
    check("$layout: logo", strpos($html, 'src="{shop_logo}"') !== false);
    check("$layout: vars intact", strpos($html, 'href="{shop_url}"') !== false);
    $btn = substr($html, strpos($html, 'class="mte-button"'), 400);
    check("$layout: button styled", strpos($btn, 'background-color:#') !== false && strpos($btn, ';color:#ffffff;') !== false || strpos($btn, '"color:#ffffff;') !== false || strpos($btn, "'color:#ffffff;") !== false, $btn);
    check("$layout: social", strpos($html, 'https://facebook.com/x') !== false && strpos($html, 'javascript:') === false);
    check("$layout: lang", strpos($html, 'lang="el"') !== false);
    $nologo = MteDesign::render($layout, MteDesign::defaults($layout), $content, ['logo' => false]);
    check("$layout: name instead of logo", strpos($nologo, '{shop_logo}') === false && strpos($nologo, '>{shop_name}</a>') !== false);
    check("$layout: empty footer blocks removed", strpos($nologo, 'facebook') === false);
    file_put_contents(sys_get_temp_dir() . "/mte_preview_$layout.html", strtr($html, ['{firstname}' => 'Γιώργος', '{shop_name}' => 'Το Κατάστημα', '{shop_url}' => '#', '{shop_logo}' => 'data:image/svg+xml;utf8,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="180" height="50"><rect width="180" height="50" rx="6" fill="#999"/><text x="90" y="32" font-size="18" text-anchor="middle" fill="#fff" font-family="Arial">LOGO</text></svg>')]));
}
$s = MteDesign::sanitize('modern', ['font_size' => 99, 'accent' => 'red;}', 'font_family' => 'evil', 'text_color' => '#ABCDEF']);
check('sanitize limits', $s['font_size'] === 24 && $s['accent'] === '#4f46e5' && $s['font_family'] === 'segoe' && $s['text_color'] === '#abcdef');
check('toText', MteDesign::toText('<p>Γεια <a href="http://x.gr">εδώ</a></p><ul><li>α</li></ul>') === "Γεια εδώ (http://x.gr)\n- α");

// --- scanner
check('module from path', MteScanner::moduleFromPath('/var/www/modules/ps_emailalerts/mails/') === 'ps_emailalerts' && MteScanner::moduleFromPath('/var/www/mails/') === 'core');
$root = sys_get_temp_dir() . '/mte_root_' . getmypid() . '/';
@mkdir($root . 'mails/el', 0777, true);
@mkdir($root . 'modules/foo/mails/el', 0777, true);
@mkdir($root . 'themes/t/mails/el', 0777, true);
@mkdir($root . 'secret', 0777, true);
file_put_contents($root . 'mails/el/account.html', 'core');
file_put_contents($root . 'themes/t/mails/el/account.html', 'theme');
file_put_contents($root . 'modules/foo/mails/el/alert.html', 'mod');
file_put_contents($root . 'secret/x.html', 'no');
file_put_contents($root . 'mails/el/x.php', 'no');
$sc = new MteScanner($root, $root . 'mails', $root . 'modules', $root . 'themes/t');
$list = $sc->listTemplates('el');
check('scanner lists', count($list) === 2 && $list[0]['key'] === 'core/account' && $list[1]['key'] === 'foo/alert', $list);
check('scanner theme override wins', $sc->resolve('core', 'account', 'el') === $root . 'themes/t/mails/el/account.html');
check('secure path ok', (bool) $sc->securePath('mails/el/account.html'));
check('secure path traversal', $sc->securePath('mails/el/../../secret/x.html') === null);
check('secure path outside mails', $sc->securePath('secret/x.html') === null);
check('secure path php', $sc->securePath('mails/el/x.php') === null);
exec('rm -rf ' . escapeshellarg($root));

echo ($fail ? "$fail of $count checks FAILED" : "All $count checks passed") . "\n";
exit($fail ? 1 : 0);
