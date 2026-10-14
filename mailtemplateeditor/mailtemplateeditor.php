<?php
/**
 * Mail Template Editor for PrestaShop 1.7 / 8 / 9
 *
 * - Edit the content and subject of every e-mail (core, modules, theme) per language.
 * - Choose one of 3 designs (Classic, Modern, Minimal) and customise colours, fonts, sizes, layout.
 * - Safe for Greek and every other language: content is stored and written as raw UTF-8,
 *   never converted to entities or legacy codepages.
 * - Raw file editor for .html/.txt/.tpl/.twig/.css mail files with automatic backups.
 *
 * No core override is used: the design is applied through the
 * actionEmailSendBefore and actionEmailAddAfterContent hooks.
 *
 * @license MIT
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/MteEncoding.php';
require_once __DIR__ . '/classes/MteScanner.php';
require_once __DIR__ . '/classes/MteContentExtractor.php';
require_once __DIR__ . '/classes/MteDesign.php';
require_once __DIR__ . '/classes/MteRepository.php';

class MailTemplateEditor extends Module
{
    const ADMIN_CONTROLLER = 'AdminMailTemplateEditor';

    /** Off: e-mails are sent untouched. */
    const MODE_OFF = 0;
    /** Only e-mails that have customised content get the design. */
    const MODE_CUSTOM = 1;
    /** Every e-mail gets the design (non-customised ones use their original content, auto-extracted). */
    const MODE_ALL = 2;

    /** @var array|null template being sent (filled by actionEmailSendBefore) */
    protected static $sending;

    public function __construct()
    {
        $this->name = 'mailtemplateeditor';
        $this->tab = 'emailing';
        $this->version = '1.1.0';
        $this->author = 'sagramores';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = ['min' => '1.7.0.0', 'max' => '9.99.99'];

        parent::__construct();

        $this->displayName = $this->l('Mail Template Editor');
        $this->description = $this->l('Edit your e-mails safely in any language (Greek included) and choose between ready-made designs with your own colours, fonts and layout.');
        $this->confirmUninstall = $this->l('All customised e-mail contents will be deleted. Continue?');
    }

    public function install()
    {
        return parent::install()
            && MteRepository::install()
            && $this->registerHook('actionEmailSendBefore')
            && $this->registerHook('actionEmailAddAfterContent')
            && $this->installTab()
            && $this->installConfig();
    }

    public function uninstall()
    {
        foreach (['MTE_MODE', 'MTE_LAYOUT', 'MTE_SETTINGS', 'MTE_SOCIAL', 'MTE_FOOTER', 'MTE_ABOUT', 'MTE_CONTACT', 'MTE_SKIP'] as $key) {
            Configuration::deleteByName($key);
        }

        return $this->uninstallTab() && MteRepository::uninstall() && parent::uninstall();
    }

    protected function installConfig()
    {
        $settings = [];
        foreach (MteDesign::LAYOUTS as $layout) {
            $settings[$layout] = MteDesign::defaults($layout);
        }

        return Configuration::updateValue('MTE_MODE', self::MODE_CUSTOM)
            && Configuration::updateValue('MTE_LAYOUT', 'prestashop')
            && Configuration::updateValue('MTE_SETTINGS', json_encode($settings), true)
            && Configuration::updateValue('MTE_SOCIAL', json_encode([]), true)
            && Configuration::updateValue('MTE_SKIP', '', true);
    }

    protected function installTab()
    {
        if (Tab::getIdFromClassName(self::ADMIN_CONTROLLER)) {
            return true;
        }
        $tab = new Tab();
        $tab->class_name = self::ADMIN_CONTROLLER;
        $tab->module = $this->name;
        $tab->active = 1;
        $tab->id_parent = (int) Tab::getIdFromClassName('AdminParentThemes');
        $tab->name = [];
        foreach (Language::getLanguages(false) as $lang) {
            $tab->name[$lang['id_lang']] = $lang['iso_code'] === 'el' || $lang['iso_code'] === 'gr' ? 'Πρότυπα email' : 'E-mail templates';
        }

        return (bool) $tab->add();
    }

    protected function uninstallTab()
    {
        $id = (int) Tab::getIdFromClassName(self::ADMIN_CONTROLLER);
        if ($id) {
            $tab = new Tab($id);

            return (bool) $tab->delete();
        }

        return true;
    }

    public function getContent()
    {
        Tools::redirectAdmin($this->context->link->getAdminLink(self::ADMIN_CONTROLLER));
    }

    /* --------------------------------------------------------------------
     * Settings helpers (also used by the admin controller)
     * ------------------------------------------------------------------ */

    public function getLayout($idShop = null)
    {
        $layout = (string) Configuration::get('MTE_LAYOUT', null, null, $idShop);
        if (MteDesign::isCustom($layout)) {
            return $this->getCustomLayoutHtml($layout, $idShop) !== '' ? $layout : 'modern';
        }

        return in_array($layout, MteDesign::LAYOUTS, true) ? $layout : 'modern';
    }

    /**
     * Settings of every design: the built-in ones and the custom ones.
     */
    public function getAllSettings($idShop = null)
    {
        $stored = json_decode((string) Configuration::get('MTE_SETTINGS', null, null, $idShop), true);
        $layouts = MteDesign::LAYOUTS;
        foreach ($this->getCustomLayouts($idShop) as $row) {
            $layouts[] = 'custom_' . (int) $row['id_mte_layout'];
        }
        $out = [];
        foreach ($layouts as $layout) {
            $out[$layout] = MteDesign::sanitize($layout, isset($stored[$layout]) && is_array($stored[$layout]) ? $stored[$layout] : []);
        }

        return $out;
    }

    public function getCustomLayouts($idShop = null)
    {
        return MteRepository::listLayouts($idShop ? (int) $idShop : (int) $this->context->shop->id);
    }

    public function getCustomLayoutHtml($layout, $idShop = null)
    {
        static $cache = [];
        $id = (int) substr((string) $layout, 7);
        $idShop = $idShop ? (int) $idShop : (int) $this->context->shop->id;
        if (!isset($cache[$idShop][$id])) {
            $row = MteRepository::getLayout($id, $idShop);
            $cache[$idShop][$id] = $row ? (string) $row['html'] : '';
        }

        return $cache[$idShop][$id];
    }

    /**
     * Imported/auto-extracted content keeps PrestaShop's own styling only with the PrestaShop design.
     */
    public static function normalizeFor($layout)
    {
        return $layout !== 'prestashop';
    }

    public function getSocial()
    {
        $social = json_decode((string) Configuration::get('MTE_SOCIAL'), true);

        return is_array($social) ? $social : [];
    }

    /**
     * Templates excluded from the design (one "module/template" or "template" per line).
     */
    public function getSkipList()
    {
        return array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', (string) Configuration::get('MTE_SKIP')))));
    }

    public function hasLogo($idShop = null)
    {
        return (bool) $this->getLogoFile($idShop);
    }

    /**
     * Same logic as Mail::send(): PS_LOGO_MAIL first, then PS_LOGO.
     */
    public function getLogoFile($idShop = null)
    {
        foreach (['PS_LOGO_MAIL', 'PS_LOGO'] as $key) {
            $logo = Configuration::get($key, null, null, $idShop);
            if ($logo && file_exists(_PS_IMG_DIR_ . $logo)) {
                return $logo;
            }
        }

        return '';
    }

    /**
     * Builds the complete e-mail HTML from content + current design.
     *
     * @param array|null $override ['layout' => .., 'settings' => .., 'footer' => .., ...] for live preview
     */
    public function renderEmail($content, $idLang, $idShop = null, array $override = null, $title = '')
    {
        $layout = isset($override['layout']) ? $override['layout'] : $this->getLayout($idShop);
        $all = $this->getAllSettings($idShop);
        $settings = isset($override['settings']) ? $override['settings'] : (isset($all[$layout]) ? $all[$layout] : []);
        $lang = new Language((int) $idLang);
        $extra = [
            'lang_iso' => $lang->iso_code ?: 'en',
            'rtl' => (bool) $lang->is_rtl,
            'logo' => $this->hasLogo($idShop),
            'title' => $title,
            'social' => isset($override['social']) ? $override['social'] : $this->getSocial(),
            'keep_tokens' => !empty($override['keep_tokens']),
        ];
        if (MteDesign::isCustom($layout)) {
            $extra['layout_html'] = isset($override['layout_html']) ? $override['layout_html'] : $this->getCustomLayoutHtml($layout, $idShop);
        }
        foreach (['footer' => 'MTE_FOOTER', 'about' => 'MTE_ABOUT', 'contact' => 'MTE_CONTACT'] as $field => $key) {
            $extra[$field . '_html'] = isset($override[$field]) ? (string) $override[$field]
                : (string) Configuration::get($key, (int) $idLang, null, $idShop);
        }

        return MteDesign::render($layout, $settings, $content, $extra);
    }

    /* --------------------------------------------------------------------
     * Hooks
     * ------------------------------------------------------------------ */

    /**
     * Remembers which template is being sent (the next hook only gets its name)
     * and replaces the subject when a custom one is set.
     */
    public function hookActionEmailSendBefore($params)
    {
        self::$sending = null;
        if ((int) Configuration::get('MTE_MODE') === self::MODE_OFF || empty($params['template'])) {
            return true;
        }
        $idShop = !empty($params['idShop']) ? (int) $params['idShop'] : (int) $this->context->shop->id;
        $idLang = !empty($params['idLang']) ? (int) $params['idLang'] : (int) $this->context->language->id;
        $module = MteScanner::moduleFromPath(isset($params['templatePath']) ? $params['templatePath'] : '');
        self::$sending = [
            'template' => (string) $params['template'],
            'module' => $module,
            'id_lang' => $idLang,
            'id_shop' => $idShop,
            'vars' => isset($params['templateVars']) && is_array($params['templateVars']) ? $params['templateVars'] : [],
        ];

        $row = MteRepository::find($idShop, $idLang, $params['template'], $module);
        if ($row && trim($row['subject']) !== '' && array_key_exists('subject', $params)) {
            $vars = self::$sending['vars'];
            $subject = strtr($row['subject'], array_map(function ($v) {
                return is_scalar($v) ? strip_tags((string) $v) : '';
            }, $vars));
            // $params['subject'] is a reference to Mail::send()'s $subject.
            $params['subject'] = $subject;
        }

        return true;
    }

    /**
     * Wraps the e-mail in the selected design. Variables ({firstname}, {shop_logo}, ...)
     * are left in place: Mail::send() replaces them right after this hook.
     */
    public function hookActionEmailAddAfterContent($params)
    {
        $mode = (int) Configuration::get('MTE_MODE');
        if ($mode === self::MODE_OFF || empty($params['template']) || !array_key_exists('template_html', $params)) {
            return;
        }
        try {
            $template = (string) $params['template'];
            $idLang = !empty($params['id_lang']) ? (int) $params['id_lang'] : (int) $this->context->language->id;
            $ctx = self::$sending && self::$sending['template'] === $template ? self::$sending : null;
            $module = $ctx ? $ctx['module'] : null;
            $idShop = $ctx ? $ctx['id_shop'] : (int) $this->context->shop->id;

            $skip = $this->getSkipList();
            if (in_array($template, $skip, true) || ($module && in_array($module . '/' . $template, $skip, true))) {
                return;
            }

            $row = MteRepository::find($idShop, $idLang, $template, $module);
            if ($row) {
                $content = $row['content'];
            } elseif ($mode === self::MODE_ALL && trim((string) $params['template_html']) !== '') {
                $content = MteContentExtractor::extract($params['template_html'], self::normalizeFor($this->getLayout($idShop)));
                if (trim(strip_tags($content)) === '') {
                    return;
                }
            } else {
                return;
            }

            $html = $this->renderEmail($content, $idLang, $idShop);
            $params['template_html'] = $html;
            if ($row && array_key_exists('template_txt', $params)) {
                // Mail::send() applies strip_tags() on the txt version, so plain text is what it expects.
                $params['template_txt'] = MteDesign::toText($content);
            }
        } catch (Exception $e) {
            // Never block an e-mail because of the design: the original is sent.
            PrestaShopLogger::addLog('mailtemplateeditor: ' . $e->getMessage(), 2, null, 'Module', (int) $this->id);
        } catch (Throwable $e) {
            PrestaShopLogger::addLog('mailtemplateeditor: ' . $e->getMessage(), 2, null, 'Module', (int) $this->id);
        }
    }
}
