<?php
/**
 * Mail Template Editor - back-office page (Design > E-mail templates).
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'mailtemplateeditor/mailtemplateeditor.php';

class AdminMailTemplateEditorController extends ModuleAdminController
{
    const MAX_BACKUPS = 20;

    /** @var MailTemplateEditor */
    public $module;

    /** @var MteScanner */
    protected $scanner;

    public function __construct()
    {
        $this->bootstrap = true;
        $this->display = 'view';
        parent::__construct();
        $this->scanner = new MteScanner();
        if (!$this->module) {
            $this->module = Module::getInstanceByName('mailtemplateeditor');
        }
    }

    public function setMedia($isNewTheme = false)
    {
        parent::setMedia($isNewTheme);
        // TinyMCE shipped with PrestaShop. The page falls back to a plain HTML editor if it is missing.
        if (file_exists(_PS_ROOT_DIR_ . '/js/tiny_mce/tinymce.min.js')) {
            $this->addJS(__PS_BASE_URI__ . 'js/tiny_mce/tinymce.min.js');
        }
        $this->addCSS($this->module->getPathUri() . 'views/css/admin.css?v=' . $this->module->version);
        $this->addJS($this->module->getPathUri() . 'views/js/admin.js?v=' . $this->module->version);
    }

    public function initPageHeaderToolbar()
    {
        parent::initPageHeaderToolbar();
        $this->page_header_toolbar_title = $this->l('E-mail templates');
        $this->toolbar_title = [$this->l('E-mail templates')];
    }

    public function renderView()
    {
        $languages = Language::getLanguages(false);
        $idLang = (int) $this->context->language->id;
        $fonts = [];
        foreach (MteDesign::FONTS as $key => $css) {
            $fonts[$key] = trim(explode(',', $css)[0], '"');
        }
        $multilang = [];
        foreach (['footer' => 'MTE_FOOTER', 'about' => 'MTE_ABOUT', 'contact' => 'MTE_CONTACT'] as $field => $key) {
            foreach ($languages as $lang) {
                $multilang[$field][(int) $lang['id_lang']] = (string) Configuration::get($key, (int) $lang['id_lang']);
            }
        }
        $defaults = [];
        foreach (MteDesign::LAYOUTS as $layout) {
            $defaults[$layout] = MteDesign::defaults($layout);
        }

        $config = [
            'ajaxUrl' => $this->context->link->getAdminLink('AdminMailTemplateEditor') . '&ajax=1',
            'idLang' => $idLang,
            'languages' => array_map(function ($l) {
                return ['id' => (int) $l['id_lang'], 'iso' => $l['iso_code'], 'name' => $l['name']];
            }, $languages),
            'mode' => (int) Configuration::get('MTE_MODE'),
            'layout' => $this->module->getLayout(),
            'settings' => $this->module->getAllSettings(),
            'defaults' => $defaults,
            'social' => (object) $this->module->getSocial(),
            'socialNetworks' => MteDesign::SOCIAL,
            'multilang' => $multilang,
            'skip' => (string) Configuration::get('MTE_SKIP'),
            'fonts' => $fonts,
            'hasLogo' => $this->module->hasLogo(),
            'employeeEmail' => $this->context->employee->email,
            'tinymce' => file_exists(_PS_ROOT_DIR_ . '/js/tiny_mce/tinymce.min.js'),
            'tinymceSkin' => is_file(_PS_ROOT_DIR_ . '/js/tiny_mce/skins/prestashop/skin.min.css') ? 'prestashop' : 'lightgray',
            'canEdit' => $this->canEdit(),
            'i18n' => $this->jsTranslations(),
        ];

        $this->context->smarty->assign([
            'mte_config_json' => json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
            'mte_layouts' => MteDesign::LAYOUTS,
            'mte_module_dir' => $this->module->getPathUri(),
        ]);

        return $this->context->smarty->fetch(_PS_MODULE_DIR_ . 'mailtemplateeditor/views/templates/admin/main.tpl');
    }

    protected function jsTranslations()
    {
        // l() returns HTML-escaped strings; the JS inserts them as text.
        return array_map([$this, 'plain'], [
            'saved' => $this->l('Saved'),
            'error' => $this->l('Error'),
            'confirmDelete' => $this->l('Delete the customised version? The original PrestaShop e-mail will be used again.'),
            'confirmImport' => $this->l('Replace the editor content with the original e-mail content?'),
            'confirmBulk' => $this->l('Import the original content of every e-mail that is not customised yet, for this language?'),
            'confirmRestore' => $this->l('Restore this backup? The current file will be backed up first.'),
            'confirmLeave' => $this->l('You have unsaved changes. Continue?'),
            'customised' => $this->l('Customised'),
            'disabled' => $this->l('Disabled'),
            'notTranslated' => $this->l('No file in this language'),
            'noTemplate' => $this->l('Select an e-mail from the list.'),
            'noFile' => $this->l('Select a file from the list.'),
            'sent' => $this->l('Test e-mail sent'),
            'imported' => $this->l('Imported'),
            'noBackups' => $this->l('No backups yet'),
            'restore' => $this->l('Restore'),
            'encoding' => $this->l('Detected encoding'),
            'converted' => $this->l('The file was not UTF-8; it was converted. Saving will store it as UTF-8.'),
            'repaired' => $this->l('Characters repaired. Check the result and save.'),
            'nothingToRepair' => $this->l('Nothing to repair.'),
            'variables' => $this->l('Variables of this e-mail (click to insert)'),
            'insertButton' => $this->l('Button'),
            'insertBox' => $this->l('Box'),
            'buttonText' => $this->l('Button text'),
            'buttonUrl' => $this->l('Button link (you can use variables, e.g. {shop_url})'),
            'source' => $this->l('HTML source'),
            'visual' => $this->l('Visual editor'),
        ]);
    }

    protected function plain($text)
    {
        return html_entity_decode((string) $text, ENT_QUOTES, 'UTF-8');
    }

    protected function canEdit()
    {
        return method_exists($this, 'access') ? (bool) $this->access('edit') : true;
    }

    /* --------------------------------------------------------------------
     * AJAX
     * ------------------------------------------------------------------ */

    protected function json($data, $ok = true)
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok] + (array) $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        exit;
    }

    protected function fail($message)
    {
        $this->json(['message' => $this->plain($message)], false);
    }

    protected function requireEdit()
    {
        if (!$this->canEdit()) {
            $this->fail($this->l('You do not have permission to edit.'));
        }
    }

    /**
     * Raw POST value. Tools::getValue() runs stripslashes()/urldecode() on strings,
     * which corrupts JSON and HTML, so it is not used for user content.
     */
    protected function raw($name)
    {
        return isset($_POST[$name]) && is_string($_POST[$name]) ? $_POST[$name] : '';
    }

    /**
     * Content fields are sent base64-encoded by the browser (UTF-8 bytes).
     */
    protected function content($name)
    {
        $value = MteEncoding::decodeTransport($this->raw($name));
        if ($value === false) {
            $this->fail($this->l('Invalid content received.'));
        }

        return $value;
    }

    protected function templateParams()
    {
        $module = (string) Tools::getValue('module_key');
        $template = (string) Tools::getValue('template');
        $idLang = (int) Tools::getValue('id_lang');
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $module) || !preg_match('/^[a-zA-Z0-9_.-]+$/', $template) || !Language::getLanguage($idLang)) {
            $this->fail($this->l('Invalid template.'));
        }

        return [$module, $template, $idLang, (int) $this->context->shop->id, Language::getIsoById($idLang)];
    }

    public function ajaxProcessListTemplates()
    {
        $idLang = (int) Tools::getValue('id_lang');
        $iso = Language::getIsoById($idLang);
        $status = MteRepository::status((int) $this->context->shop->id, $idLang);
        $list = $this->scanner->listTemplates($iso);
        foreach ($list as &$item) {
            $item['custom'] = isset($status[$item['key']]);
            $item['active'] = $item['custom'] && $status[$item['key']]['active'];
        }
        unset($item);
        $this->json(['templates' => $list]);
    }

    public function ajaxProcessLoadTemplate()
    {
        list($module, $template, $idLang, $idShop, $iso) = $this->templateParams();
        $row = MteRepository::get($idShop, $idLang, $module, $template);
        $original = $this->originalHtml($module, $template, $iso);
        $this->json([
            'custom' => (bool) $row,
            'subject' => $row ? $row['subject'] : '',
            'content' => $row ? $row['content'] : '',
            'active' => $row ? (bool) $row['active'] : true,
            'variables' => $this->variablesOf($original),
            'file' => ($f = $this->scanner->resolve($module, $template, $iso)) ? $this->scanner->relative($f) : '',
        ]);
    }

    protected function originalHtml($module, $template, $iso)
    {
        $file = $this->scanner->resolve($module, $template, $iso) ?: $this->scanner->resolve($module, $template, 'en');

        return $file ? MteEncoding::normalize((string) file_get_contents($file)) : '';
    }

    protected function variablesOf($html)
    {
        preg_match_all('/\{([a-z0-9_]+)\}/i', (string) $html, $m);
        $vars = array_unique(array_merge($m[0], ['{firstname}', '{lastname}', '{shop_name}', '{shop_url}', '{my_account_url}']));
        sort($vars);

        return array_values(array_diff($vars, ['{shop_logo}']));
    }

    public function ajaxProcessImportOriginal()
    {
        list($module, $template, , , $iso) = $this->templateParams();
        $html = $this->originalHtml($module, $template, $iso);
        if ($html === '') {
            $this->fail($this->l('Original template not found.'));
        }
        $this->json(['content' => MteContentExtractor::extract($html, (bool) Tools::getValue('normalize', 1))]);
    }

    public function ajaxProcessSaveTemplate()
    {
        $this->requireEdit();
        list($module, $template, $idLang, $idShop) = $this->templateParams();
        $content = $this->content('content');
        $subject = MteEncoding::toUtf8($this->raw('subject'));
        if (trim(strip_tags($content, '<img>')) === '') {
            $this->fail($this->l('The content is empty.'));
        }
        if (!MteRepository::save($idShop, $idLang, $module, $template, $subject, $content, (bool) Tools::getValue('active'))) {
            $this->fail(Db::getInstance()->getMsgError());
        }
        $this->json([]);
    }

    public function ajaxProcessDeleteTemplate()
    {
        $this->requireEdit();
        list($module, $template, $idLang, $idShop) = $this->templateParams();
        MteRepository::delete($idShop, $idLang, $module, $template);
        $this->json([]);
    }

    public function ajaxProcessBulkImport()
    {
        $this->requireEdit();
        $idLang = (int) Tools::getValue('id_lang');
        $iso = Language::getIsoById($idLang);
        if (!$iso) {
            $this->fail($this->l('Invalid language.'));
        }
        $idShop = (int) $this->context->shop->id;
        $status = MteRepository::status($idShop, $idLang);
        $count = 0;
        foreach ($this->scanner->listTemplates($iso) as $item) {
            if (isset($status[$item['key']]) || !$item['translated']) {
                continue;
            }
            $content = MteContentExtractor::extract($this->originalHtml($item['module'], $item['template'], $iso), true);
            if (trim(strip_tags($content)) !== '' && MteRepository::save($idShop, $idLang, $item['module'], $item['template'], '', $content, true)) {
                ++$count;
            }
        }
        $this->json(['count' => $count]);
    }

    /**
     * Live preview. Uses the posted (unsaved) design and/or content.
     */
    public function ajaxProcessPreview()
    {
        $idLang = (int) Tools::getValue('id_lang') ?: (int) $this->context->language->id;
        $override = $this->designFromRequest(false);
        $content = $this->content('content');
        if ($content === '') {
            $content = $this->sampleContent($idLang);
        }
        $html = $this->module->renderEmail($content, $idLang, null, $override, MteEncoding::toUtf8($this->raw('subject')));
        $this->json(['html' => $this->fillPreviewVars($html, $idLang)]);
    }

    protected function designFromRequest($strict)
    {
        $layout = (string) Tools::getValue('layout');
        if (!in_array($layout, MteDesign::LAYOUTS, true)) {
            if ($strict) {
                $this->fail($this->l('Invalid design.'));
            }

            return null;
        }
        $settings = json_decode((string) $this->raw('settings'), true);
        $social = json_decode((string) $this->raw('social'), true);
        $idLang = (int) Tools::getValue('id_lang');

        $override = [
            'layout' => $layout,
            'settings' => MteDesign::sanitize($layout, is_array($settings) ? $settings : []),
            'social' => $this->cleanSocial(is_array($social) ? $social : []),
        ];
        $ml = json_decode((string) MteEncoding::decodeTransport($this->raw('multilang')), true);
        if (is_array($ml)) {
            foreach (['footer', 'about', 'contact'] as $field) {
                if (isset($ml[$field][$idLang])) {
                    $override[$field] = (string) $ml[$field][$idLang];
                }
            }
            $override['multilang'] = $ml;
        }

        return $override;
    }

    protected function cleanSocial(array $social)
    {
        $out = [];
        foreach (array_keys(MteDesign::SOCIAL) as $key) {
            if (!empty($social[$key]) && Validate::isAbsoluteUrl($social[$key]) && preg_match('#^https?://#i', $social[$key])) {
                $out[$key] = (string) $social[$key];
            }
        }

        return $out;
    }

    public function ajaxProcessSaveDesign()
    {
        $this->requireEdit();
        $override = $this->designFromRequest(true);
        $all = $this->module->getAllSettings();
        $all[$override['layout']] = $override['settings'];
        $mode = (int) Tools::getValue('mode');
        Configuration::updateValue('MTE_LAYOUT', $override['layout']);
        Configuration::updateValue('MTE_MODE', in_array($mode, [0, 1, 2], true) ? $mode : 1);
        Configuration::updateValue('MTE_SETTINGS', json_encode($all), true);
        Configuration::updateValue('MTE_SOCIAL', json_encode($override['social']), true);
        Configuration::updateValue('MTE_SKIP', strip_tags((string) $this->raw('skip')), true);
        if (!empty($override['multilang'])) {
            foreach (['footer' => 'MTE_FOOTER', 'about' => 'MTE_ABOUT', 'contact' => 'MTE_CONTACT'] as $field => $key) {
                $values = [];
                foreach (Language::getLanguages(false) as $lang) {
                    $id = (int) $lang['id_lang'];
                    $values[$id] = isset($override['multilang'][$field][$id]) ? MteEncoding::toUtf8((string) $override['multilang'][$field][$id]) : '';
                }
                Configuration::updateValue($key, $values, true);
            }
        }
        $this->json([]);
    }

    public function ajaxProcessSendTest()
    {
        $this->requireEdit();
        list($module, $template, $idLang, $idShop, $iso) = $this->templateParams();
        $email = (string) Tools::getValue('email');
        if (!Validate::isEmail($email)) {
            $this->fail($this->l('Invalid e-mail address.'));
        }
        $path = $module === 'core' ? _PS_MAIL_DIR_ : _PS_MODULE_DIR_ . $module . '/mails/';
        if (!$this->scanner->resolve($module, $template, $iso, 'html')) {
            $this->fail($this->l('This e-mail has no file for this language, PrestaShop cannot send it.'));
        }
        $vars = [];
        foreach ($this->sampleVars($idLang) as $k => $v) {
            $vars[$k] = $v;
        }
        $subject = '[TEST] ' . $template;
        $ok = Mail::send($idLang, $template, $subject, $vars, $email, null, null, null, null, null, $path, false, $idShop);
        if (!$ok) {
            $this->fail($this->l('PrestaShop could not send the e-mail. Check Advanced Parameters > E-mail and the logs.'));
        }
        $this->json([]);
    }

    /* --------------------------------------------------------------------
     * Raw file editor
     * ------------------------------------------------------------------ */

    public function ajaxProcessListFiles()
    {
        $iso = Language::getIsoById((int) Tools::getValue('id_lang')) ?: 'en';
        $this->json(['files' => $this->scanner->listFiles($iso)]);
    }

    protected function securePath()
    {
        $abs = $this->scanner->securePath($this->raw('path'));
        if (!$abs) {
            $this->fail($this->l('Invalid file.'));
        }

        return $abs;
    }

    public function ajaxProcessLoadFile()
    {
        $abs = $this->securePath();
        $content = MteEncoding::readFile($abs, $encoding);
        $this->json([
            'content' => (string) $content,
            'encoding' => $encoding,
            'writable' => is_writable($abs),
            'backups' => $this->backups($abs),
        ]);
    }

    public function ajaxProcessSaveFile()
    {
        $this->requireEdit();
        $abs = $this->securePath();
        $content = $this->content('content');
        if (!is_writable($abs)) {
            $this->fail($this->l('The file is not writable.'));
        }
        if (!$this->backup($abs)) {
            $this->fail($this->l('Could not create a backup; the file was not saved.'));
        }
        if (!MteEncoding::writeFile($abs, $content)) {
            $this->fail($this->l('Could not write the file.'));
        }
        $this->json(['backups' => $this->backups($abs)]);
    }

    public function ajaxProcessRestoreBackup()
    {
        $this->requireEdit();
        $abs = $this->securePath();
        $name = basename((string) Tools::getValue('backup'));
        $file = $this->backupDir($abs) . $name;
        if (!preg_match('/^[0-9_]+\.bak$/', $name) || !is_file($file)) {
            $this->fail($this->l('Backup not found.'));
        }
        $content = file_get_contents($file);
        $this->backup($abs);
        if (!MteEncoding::writeFile($abs, $content)) {
            $this->fail($this->l('Could not write the file.'));
        }
        $this->json(['content' => MteEncoding::toUtf8($content), 'backups' => $this->backups($abs)]);
    }

    /**
     * Repairs broken Greek: wrong codepage, double-encoded UTF-8, HTML entities.
     */
    public function ajaxProcessRepair()
    {
        $content = $this->content('content');
        $fixed = MteEncoding::normalize($content);
        $this->json(['content' => $fixed, 'changed' => $fixed !== $content]);
    }

    protected function backupDir($abs)
    {
        return _PS_MODULE_DIR_ . 'mailtemplateeditor/backups/' . md5($this->scanner->relative($abs)) . '/';
    }

    protected function backup($abs)
    {
        $dir = $this->backupDir($abs);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            return false;
        }
        @file_put_contents($dir . 'path.txt', $this->scanner->relative($abs));
        if (!@copy($abs, $dir . date('Ymd_His') . '_' . sprintf('%06d', (int) (microtime(true) * 1000000) % 1000000) . '.bak')) {
            return false;
        }
        $files = glob($dir . '*.bak');
        rsort($files);
        foreach (array_slice($files, self::MAX_BACKUPS) as $old) {
            @unlink($old);
        }

        return true;
    }

    protected function backups($abs)
    {
        $out = [];
        $files = (array) glob($this->backupDir($abs) . '*.bak');
        rsort($files);
        foreach ($files as $file) {
            $out[] = ['name' => basename($file), 'date' => date('Y-m-d H:i:s', filemtime($file)), 'size' => filesize($file)];
        }

        return $out;
    }

    /* --------------------------------------------------------------------
     * Sample data (preview / test e-mail)
     * ------------------------------------------------------------------ */

    protected function sampleVars($idLang)
    {
        $greek = in_array(Language::getIsoById($idLang), ['el', 'gr'], true);
        $link = $this->context->link;
        $logo = $this->module->getLogoFile();

        return [
            '{firstname}' => $greek ? 'Γιώργος' : 'John',
            '{lastname}' => $greek ? 'Παπαδόπουλος' : 'Smith',
            '{email}' => $this->context->employee->email,
            '{shop_name}' => Configuration::get('PS_SHOP_NAME'),
            '{shop_url}' => $link->getPageLink('index', true, $idLang),
            '{my_account_url}' => $link->getPageLink('my-account', true, $idLang),
            '{history_url}' => $link->getPageLink('history', true, $idLang),
            '{guest_tracking_url}' => $link->getPageLink('guest-tracking', true, $idLang),
            '{shop_logo}' => $logo ? $link->getMediaLink(_PS_IMG_ . $logo) : '',
            '{order_name}' => 'XKBKNABJK',
            '{id_order}' => '42',
            '{date}' => date('Y-m-d H:i:s'),
            '{payment}' => $greek ? 'Τραπεζική κατάθεση' : 'Bank wire',
            '{carrier}' => $greek ? 'ΕΛΤΑ Courier' : 'My carrier',
            '{total_paid}' => '€ 59,90',
            '{total_products}' => '€ 54,90',
            '{total_shipping}' => '€ 5,00',
            '{total_discounts}' => '€ 0,00',
            '{total_wrapping}' => '€ 0,00',
            '{total_tax_paid}' => '€ 11,59',
            // Same shape as PrestaShop: table rows inserted inside the products table.
            '{products}' => '<tr><td style="padding:8px">demo_1</td><td style="padding:8px">T-shirt (M)</td><td align="right" style="padding:8px">€ 19,95</td><td align="center" style="padding:8px">2</td><td align="right" style="padding:8px">€ 39,90</td></tr>'
                . '<tr><td style="padding:8px">demo_2</td><td style="padding:8px">' . ($greek ? 'Κούπα' : 'Mug') . '</td><td align="right" style="padding:8px">€ 15,00</td><td align="center" style="padding:8px">1</td><td align="right" style="padding:8px">€ 15,00</td></tr>',
            '{recycled_packaging_label}' => $greek ? 'Όχι' : 'No',
            '{products_txt}' => "T-shirt (M)  2 x € 19,95  € 39,90\n" . ($greek ? 'Κούπα' : 'Mug') . '  1 x € 15,00  € 15,00',
            '{discounts}' => '',
            '{discounts_txt}' => '',
            '{delivery_block_html}' => $greek ? 'Γιώργος Παπαδόπουλος<br>Ερμού 10<br>105 63 Αθήνα' : 'John Smith<br>10 Main Street<br>London',
            '{invoice_block_html}' => $greek ? 'Γιώργος Παπαδόπουλος<br>Ερμού 10<br>105 63 Αθήνα' : 'John Smith<br>10 Main Street<br>London',
            '{delivery_block_txt}' => $greek ? "Γιώργος Παπαδόπουλος\nΕρμού 10\n105 63 Αθήνα" : "John Smith\n10 Main Street\nLondon",
            '{invoice_block_txt}' => $greek ? "Γιώργος Παπαδόπουλος\nΕρμού 10\n105 63 Αθήνα" : "John Smith\n10 Main Street\nLondon",
            '{passwd}' => '********',
            '{url}' => $link->getPageLink('password', true, $idLang),
            '{order_state}' => $greek ? 'Σε προετοιμασία' : 'Processing in progress',
            '{followup}' => '#',
            '{message}' => $greek ? 'Καλησπέρα, θα ήθελα πληροφορίες για την παραγγελία μου.' : 'Hello, I would like some information about my order.',
            '{voucher_amount}' => '€ 10,00',
            '{voucher_num}' => 'WELCOME10',
        ];
    }

    protected function fillPreviewVars($html, $idLang)
    {
        return strtr($html, $this->sampleVars($idLang));
    }

    protected function sampleContent($idLang)
    {
        if (in_array(Language::getIsoById($idLang), ['el', 'gr'], true)) {
            return '<h1>Ευχαριστούμε για την παραγγελία σας!</h1>'
                . '<p>Γεια σας {firstname} {lastname},</p>'
                . '<p>Η παραγγελία σας <strong>{order_name}</strong> καταχωρήθηκε με επιτυχία. Θα σας ενημερώσουμε μόλις αποσταλεί.</p>'
                . '<div class="mte-box"><h3>Στοιχεία παραγγελίας</h3><p>Τρόπος πληρωμής: {payment}<br>Σύνολο: {total_paid}</p></div>'
                . '<table width="100%"><tr><th>Κωδικός</th><th>Προϊόν</th><th align="right">Τιμή</th><th align="center">Ποσότητα</th><th align="right">Σύνολο</th></tr><!--mte:{products}--></table>'
                . '<p style="text-align:center"><a class="mte-button" href="{history_url}">Δείτε την παραγγελία σας</a></p>'
                . '<p>Με εκτίμηση,<br>Η ομάδα του {shop_name}</p>';
        }

        return '<h1>Thank you for your order!</h1>'
            . '<p>Hi {firstname} {lastname},</p>'
            . '<p>Your order <strong>{order_name}</strong> has been placed successfully. We will let you know as soon as it ships.</p>'
            . '<div class="mte-box"><h3>Order details</h3><p>Payment: {payment}<br>Total: {total_paid}</p></div>'
            . '<table width="100%"><tr><th>Reference</th><th>Product</th><th align="right">Price</th><th align="center">Quantity</th><th align="right">Total</th></tr><!--mte:{products}--></table>'
            . '<p style="text-align:center"><a class="mte-button" href="{history_url}">View your order</a></p>'
            . '<p>Kind regards,<br>The {shop_name} team</p>';
    }
}
