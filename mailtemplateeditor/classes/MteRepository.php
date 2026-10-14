<?php
/**
 * Mail Template Editor - storage of the customised e-mail contents.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class MteRepository
{
    const TABLE = 'mte_template';
    const TABLE_LAYOUT = 'mte_layout';

    public static function install()
    {
        // utf8mb4: any language and emoji; the content is stored as raw UTF-8.
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . self::TABLE . '` (
            `id_mte_template` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_shop` INT UNSIGNED NOT NULL DEFAULT 1,
            `id_lang` INT UNSIGNED NOT NULL,
            `module` VARCHAR(64) NOT NULL DEFAULT \'core\',
            `template` VARCHAR(128) NOT NULL,
            `subject` VARCHAR(255) NOT NULL DEFAULT \'\',
            `content` MEDIUMTEXT NOT NULL,
            `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
            `date_upd` DATETIME NOT NULL,
            PRIMARY KEY (`id_mte_template`),
            UNIQUE KEY `mte_unique` (`id_shop`, `id_lang`, `module`, `template`),
            KEY `mte_template` (`template`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        return Db::getInstance()->execute($sql) && self::installLayouts();
    }

    /**
     * Designs made with the visual builder.
     */
    public static function installLayouts()
    {
        return Db::getInstance()->execute('CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . self::TABLE_LAYOUT . '` (
            `id_mte_layout` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_shop` INT UNSIGNED NOT NULL DEFAULT 1,
            `name` VARCHAR(128) NOT NULL,
            `html` MEDIUMTEXT NOT NULL,
            `project` MEDIUMTEXT NOT NULL,
            `date_upd` DATETIME NOT NULL,
            PRIMARY KEY (`id_mte_layout`),
            KEY `mte_layout_shop` (`id_shop`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    public static function uninstall()
    {
        return Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . self::TABLE . '`, `' . _DB_PREFIX_ . self::TABLE_LAYOUT . '`');
    }

    /**
     * @return array[] id, name, date_upd (no HTML)
     */
    public static function listLayouts($idShop)
    {
        return (array) Db::getInstance()->executeS('SELECT `id_mte_layout`, `name`, `date_upd` FROM `' . _DB_PREFIX_ . self::TABLE_LAYOUT . '`
            WHERE `id_shop` = ' . (int) $idShop . ' ORDER BY `id_mte_layout`', true, false);
    }

    /**
     * @return array|false
     */
    public static function getLayout($idLayout, $idShop)
    {
        return Db::getInstance()->getRow('SELECT * FROM `' . _DB_PREFIX_ . self::TABLE_LAYOUT . '`
            WHERE `id_mte_layout` = ' . (int) $idLayout . ' AND `id_shop` = ' . (int) $idShop, false);
    }

    /**
     * @return int id of the design, 0 on error
     */
    public static function saveLayout($idLayout, $idShop, $name, $html, $project)
    {
        $db = Db::getInstance();
        $data = '`name` = \'' . pSQL(Tools::substr($name, 0, 128)) . '\', `html` = \'' . pSQL($html, true) . '\',
            `project` = \'' . pSQL($project, true) . '\', `date_upd` = NOW()';
        if ($idLayout && self::getLayout($idLayout, $idShop)) {
            return $db->execute('UPDATE `' . _DB_PREFIX_ . self::TABLE_LAYOUT . '` SET ' . $data . '
                WHERE `id_mte_layout` = ' . (int) $idLayout . ' AND `id_shop` = ' . (int) $idShop) ? (int) $idLayout : 0;
        }
        if (!$db->execute('INSERT INTO `' . _DB_PREFIX_ . self::TABLE_LAYOUT . '` SET `id_shop` = ' . (int) $idShop . ', ' . $data)) {
            return 0;
        }

        return (int) $db->Insert_ID();
    }

    public static function deleteLayout($idLayout, $idShop)
    {
        return Db::getInstance()->delete(self::TABLE_LAYOUT, '`id_mte_layout` = ' . (int) $idLayout . ' AND `id_shop` = ' . (int) $idShop);
    }

    /**
     * @return array|false
     */
    public static function get($idShop, $idLang, $module, $template)
    {
        return Db::getInstance()->getRow('SELECT * FROM `' . _DB_PREFIX_ . self::TABLE . '`
            WHERE `id_shop` = ' . (int) $idShop . ' AND `id_lang` = ' . (int) $idLang . '
            AND `module` = \'' . pSQL($module) . '\' AND `template` = \'' . pSQL($template) . '\'', false);
    }

    /**
     * Used when the module of the template is unknown: prefers the given module, then core.
     *
     * @return array|false
     */
    public static function find($idShop, $idLang, $template, $module = null)
    {
        $rows = Db::getInstance()->executeS('SELECT * FROM `' . _DB_PREFIX_ . self::TABLE . '`
            WHERE `id_shop` = ' . (int) $idShop . ' AND `id_lang` = ' . (int) $idLang . '
            AND `template` = \'' . pSQL($template) . '\' AND `active` = 1', true, false);
        if (!$rows) {
            return false;
        }
        foreach ([$module, 'core'] as $wanted) {
            foreach ($rows as $row) {
                if ($wanted !== null && $row['module'] === $wanted) {
                    return $row;
                }
            }
        }

        return $module === null ? $rows[0] : false;
    }

    /**
     * @return array map "module/template" => ['active' => bool, 'date_upd' => string]
     */
    public static function status($idShop, $idLang)
    {
        $out = [];
        $rows = Db::getInstance()->executeS('SELECT `module`, `template`, `active`, `date_upd` FROM `' . _DB_PREFIX_ . self::TABLE . '`
            WHERE `id_shop` = ' . (int) $idShop . ' AND `id_lang` = ' . (int) $idLang, true, false);
        foreach ((array) $rows as $row) {
            $out[$row['module'] . '/' . $row['template']] = ['active' => (bool) $row['active'], 'date_upd' => $row['date_upd']];
        }

        return $out;
    }

    public static function save($idShop, $idLang, $module, $template, $subject, $content, $active)
    {
        // pSQL(..., true): escape for SQL but keep the HTML untouched.
        return Db::getInstance()->execute('INSERT INTO `' . _DB_PREFIX_ . self::TABLE . '`
            (`id_shop`, `id_lang`, `module`, `template`, `subject`, `content`, `active`, `date_upd`) VALUES (
            ' . (int) $idShop . ', ' . (int) $idLang . ', \'' . pSQL($module) . '\', \'' . pSQL($template) . '\',
            \'' . pSQL(Tools::substr($subject, 0, 255), true) . '\', \'' . pSQL($content, true) . '\', ' . (int) (bool) $active . ', NOW())
            ON DUPLICATE KEY UPDATE `subject` = VALUES(`subject`), `content` = VALUES(`content`),
            `active` = VALUES(`active`), `date_upd` = NOW()');
    }

    public static function delete($idShop, $idLang, $module, $template)
    {
        return Db::getInstance()->delete(self::TABLE, '`id_shop` = ' . (int) $idShop . ' AND `id_lang` = ' . (int) $idLang
            . ' AND `module` = \'' . pSQL($module) . '\' AND `template` = \'' . pSQL($template) . '\'');
    }
}
