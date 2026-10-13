<?php
/**
 * Mail Template Editor - finds e-mail template files (core, theme, modules).
 */
if (!defined('_PS_VERSION_') && !defined('MTE_TESTING')) {
    exit;
}

class MteScanner
{
    const EDITABLE_EXT = ['html', 'txt', 'tpl', 'twig', 'css'];

    /** @var string */
    protected $root;
    /** @var string */
    protected $mailDir;
    /** @var string */
    protected $moduleDir;
    /** @var string */
    protected $themeDir;

    public function __construct($root = null, $mailDir = null, $moduleDir = null, $themeDir = null)
    {
        $this->root = self::slash($root ?: _PS_ROOT_DIR_);
        $this->mailDir = self::slash($mailDir ?: _PS_MAIL_DIR_);
        $this->moduleDir = self::slash($moduleDir ?: _PS_MODULE_DIR_);
        $this->themeDir = self::slash($themeDir ?: _PS_THEME_DIR_);
    }

    protected static function slash($path)
    {
        return rtrim(str_replace('\\', '/', (string) $path), '/') . '/';
    }

    /**
     * Returns the template "sources" in the order Mail::send() resolves them
     * (the theme override wins over the original).
     *
     * @return array<string, string[]> module key ('core' or module name) => list of base dirs
     */
    protected function sources()
    {
        $sources = ['core' => [$this->themeDir . 'mails/', $this->mailDir]];
        foreach ((array) @scandir($this->moduleDir) as $module) {
            if ($module === '' || $module[0] === '.' || !preg_match('/^[a-zA-Z0-9_-]+$/', $module)) {
                continue;
            }
            $dirs = [];
            if (is_dir($this->themeDir . 'modules/' . $module . '/mails/')) {
                $dirs[] = $this->themeDir . 'modules/' . $module . '/mails/';
            }
            if (is_dir($this->moduleDir . $module . '/mails/')) {
                $dirs[] = $this->moduleDir . $module . '/mails/';
            }
            if ($dirs) {
                $sources[$module] = $dirs;
            }
        }

        return $sources;
    }

    /**
     * Lists the e-mail templates (one entry per template name) available for a language.
     * Falls back to English to list templates that have not been translated yet.
     *
     * @return array[]
     */
    public function listTemplates($iso)
    {
        $iso = self::cleanIso($iso);
        $list = [];
        foreach ($this->sources() as $module => $dirs) {
            $names = [];
            foreach ($dirs as $dir) {
                foreach ([$iso, 'en'] as $lang) {
                    foreach ((array) glob($dir . $lang . '/*.html') as $file) {
                        $names[basename($file, '.html')] = true;
                    }
                }
            }
            ksort($names);
            foreach (array_keys($names) as $name) {
                $html = $this->resolve($module, $name, $iso, 'html');
                $list[] = [
                    'key' => $module . '/' . $name,
                    'module' => $module,
                    'template' => $name,
                    'file' => $html ? $this->relative($html) : '',
                    'translated' => (bool) $html,
                ];
            }
        }

        return $list;
    }

    /**
     * Path of the file Mail::send() will use, or null.
     */
    public function resolve($module, $template, $iso, $ext = 'html')
    {
        $sources = $this->sources();
        if (!isset($sources[$module]) || !preg_match('/^[a-zA-Z0-9_.-]+$/', $template)) {
            return null;
        }
        foreach ($sources[$module] as $dir) {
            $file = $dir . self::cleanIso($iso) . '/' . $template . '.' . $ext;
            if (is_file($file)) {
                return $file;
            }
        }

        return null;
    }

    /**
     * Lists all editable files inside "mails" directories (raw file editor).
     *
     * @return array[]
     */
    public function listFiles($iso)
    {
        $iso = self::cleanIso($iso);
        $files = [];
        foreach ($this->sources() as $module => $dirs) {
            foreach ($dirs as $dir) {
                foreach ((array) glob($dir . $iso . '/*.*') as $file) {
                    if (in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), self::EDITABLE_EXT, true)) {
                        $files[] = ['module' => $module, 'path' => $this->relative($file)];
                    }
                }
            }
        }
        // PrestaShop 1.7.6+ mail themes (Twig layouts used by "Design > Email theme").
        if (is_dir($this->mailDir . 'themes/')) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->mailDir . 'themes/', FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if (in_array(strtolower($file->getExtension()), self::EDITABLE_EXT, true)) {
                    $files[] = ['module' => 'mail-themes', 'path' => $this->relative($file->getPathname())];
                }
            }
        }
        usort($files, function ($a, $b) {
            return strcmp($a['path'], $b['path']);
        });

        return $files;
    }

    /**
     * Validates a path coming from the browser. Only files inside a "mails"
     * directory of the shop, with an editable extension, are accepted.
     *
     * @return string|null absolute path
     */
    public function securePath($relative)
    {
        $relative = ltrim(str_replace('\\', '/', (string) $relative), '/');
        if ($relative === '' || strpos($relative, '..') !== false || strpos($relative, "\0") !== false) {
            return null;
        }
        $abs = realpath($this->root . $relative);
        if (!$abs || !is_file($abs)) {
            return null;
        }
        $abs = str_replace('\\', '/', $abs);
        $root = str_replace('\\', '/', (string) realpath($this->root)) . '/';
        if (strpos($abs, $root) !== 0 || strpos($abs, '/mails/') === false) {
            return null;
        }
        if (!in_array(strtolower(pathinfo($abs, PATHINFO_EXTENSION)), self::EDITABLE_EXT, true)) {
            return null;
        }

        return $abs;
    }

    public function relative($abs)
    {
        $abs = str_replace('\\', '/', $abs);
        $root = str_replace('\\', '/', (string) realpath($this->root)) . '/';
        if (strpos($abs, $root) === 0) {
            return substr($abs, strlen($root));
        }

        return strpos($abs, $this->root) === 0 ? substr($abs, strlen($this->root)) : $abs;
    }

    public static function cleanIso($iso)
    {
        $iso = strtolower((string) $iso);

        return preg_match('/^[a-z]{2,3}(-[a-z]{2,4})?$/', $iso) ? $iso : 'en';
    }

    /**
     * Module key from a template path given to Mail::send().
     */
    public static function moduleFromPath($templatePath)
    {
        $path = str_replace('\\', '/', (string) $templatePath);
        if (preg_match('#/modules/([a-zA-Z0-9_-]+)/mails/?#', $path, $m)) {
            return $m[1];
        }

        return 'core';
    }
}
