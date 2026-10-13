<?php
/**
 * Mail Template Editor - encoding helpers.
 *
 * Everything the module reads or writes goes through these helpers so that
 * Greek (and any other non-latin) text is always stored as raw UTF-8:
 * no BOM, no HTML entities, no legacy codepages.
 */
if (!defined('_PS_VERSION_') && !defined('MTE_TESTING')) {
    exit;
}

class MteEncoding
{
    /**
     * Converts arbitrary bytes to UTF-8.
     * Handles: UTF-8 BOM, valid UTF-8, Windows-1253 / ISO-8859-7 (Greek), Windows-1252.
     *
     * @param string $bytes
     * @param string|null $detected receives the detected source encoding
     *
     * @return string
     */
    public static function toUtf8($bytes, &$detected = null)
    {
        $bytes = (string) $bytes;
        if (strncmp($bytes, "\xEF\xBB\xBF", 3) === 0) {
            $bytes = substr($bytes, 3);
        }
        if (strncmp($bytes, "\xFF\xFE", 2) === 0 || strncmp($bytes, "\xFE\xFF", 2) === 0) {
            $detected = 'UTF-16';

            return (string) mb_convert_encoding($bytes, 'UTF-8', 'UTF-16');
        }
        if (mb_check_encoding($bytes, 'UTF-8')) {
            $detected = 'UTF-8';

            return $bytes;
        }
        // Not UTF-8: Greek single-byte codepages put the letters in 0xC1-0xFE.
        // Windows-1253 and ISO-8859-7 differ only in a few symbols (e.g. Ά = 0xA2 vs 0xB6).
        $greek = preg_match_all('/[\xC1-\xFE]/', $bytes);
        $high = preg_match_all('/[\x80-\xFF]/', $bytes);
        if ($high > 0 && $greek / $high > 0.6) {
            $detected = preg_match('/[\x80-\x9F]/', $bytes) || strpos($bytes, "\xA2") !== false ? 'Windows-1253' : 'ISO-8859-7';
            $converted = self::convert($bytes, $detected);
            if ($converted !== false) {
                return $converted;
            }
        }
        $detected = 'Windows-1252';
        $converted = self::convert($bytes, 'Windows-1252');

        return $converted !== false ? $converted : (string) mb_convert_encoding($bytes, 'UTF-8', 'ISO-8859-1');
    }

    /**
     * @return string|false
     */
    private static function convert($bytes, $from)
    {
        if (function_exists('iconv')) {
            $out = @iconv(strtoupper($from), 'UTF-8//IGNORE', $bytes);
            if ($out !== false) {
                return $out;
            }
        }
        if ($from === 'ISO-8859-7' || $from === 'Windows-1252') {
            return mb_convert_encoding($bytes, 'UTF-8', $from === 'Windows-1252' ? 'Windows-1252' : 'ISO-8859-7');
        }

        return false;
    }

    /** Windows-1252 code points in 0x80-0x9F that differ from ISO-8859-1. */
    private static $cp1252 = [
        0x20AC => 0x80, 0x201A => 0x82, 0x0192 => 0x83, 0x201E => 0x84, 0x2026 => 0x85,
        0x2020 => 0x86, 0x2021 => 0x87, 0x02C6 => 0x88, 0x2030 => 0x89, 0x0160 => 0x8A,
        0x2039 => 0x8B, 0x0152 => 0x8C, 0x017D => 0x8E, 0x2018 => 0x91, 0x2019 => 0x92,
        0x201C => 0x93, 0x201D => 0x94, 0x2022 => 0x95, 0x2013 => 0x96, 0x2014 => 0x97,
        0x02DC => 0x98, 0x2122 => 0x99, 0x0161 => 0x9A, 0x203A => 0x9B, 0x0153 => 0x9C,
        0x017E => 0x9E, 0x0178 => 0x9F,
    ];

    /**
     * Repairs "mojibake": UTF-8 text that was decoded as Windows-1252/ISO-8859-1
     * and saved again (e.g. "Î“ÎµÎ¹Î¬" instead of "Γειά"). Runs up to 3 rounds
     * because broken editors often double-encode more than once.
     *
     * @param string $text
     *
     * @return string
     */
    public static function repairMojibake($text)
    {
        $cont = '[\x{0080}-\x{00BF}';
        foreach (array_keys(self::$cp1252) as $cp) {
            $cont .= sprintf('\x{%04X}', $cp);
        }
        $cont .= ']';
        // A lead byte (C2-DF => 1 continuation, E0-EF => 2) followed by continuation bytes,
        // all seen as Latin-1/CP1252 characters.
        $pattern = '/(?:[\x{00C2}-\x{00DF}]' . $cont . '|[\x{00E0}-\x{00EF}]' . $cont . '{2})+/u';
        for ($i = 0; $i < 3; ++$i) {
            $fixed = preg_replace_callback($pattern, function ($m) {
                $bytes = '';
                foreach (preg_split('//u', $m[0], -1, PREG_SPLIT_NO_EMPTY) as $char) {
                    $cp = self::ord($char);
                    $bytes .= chr($cp < 0x100 ? $cp : self::$cp1252[$cp]);
                }

                return mb_check_encoding($bytes, 'UTF-8') ? $bytes : $m[0];
            }, $text);
            if ($fixed === null || $fixed === $text) {
                break;
            }
            $text = $fixed;
        }

        return $text;
    }

    /**
     * Code point of a UTF-8 character (mb_ord() needs PHP 7.2).
     *
     * @return int|false
     */
    public static function ord($char)
    {
        if (function_exists('mb_ord')) {
            return mb_ord($char, 'UTF-8');
        }
        $u = unpack('N', mb_convert_encoding($char, 'UCS-4BE', 'UTF-8'));

        return $u ? $u[1] : false;
    }

    /**
     * Turns HTML entities of non-ASCII characters back into real UTF-8 characters
     * (e.g. "&alpha;", "&#945;", "&#x3B1;" -> "α"), but keeps the entities that are
     * structurally meaningful in HTML (&lt; &gt; &amp; &quot; &#039; &nbsp; ...).
     *
     * @param string $html
     *
     * @return string
     */
    public static function decodeTextEntities($html)
    {
        return (string) preg_replace_callback('/&(#[0-9]{2,7}|#x[0-9a-fA-F]{2,6}|[a-zA-Z][a-zA-Z0-9]{1,31});/', function ($m) {
            $decoded = html_entity_decode($m[0], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($decoded === $m[0] || $decoded === '') {
                return $m[0];
            }
            $cp = self::ord($decoded);
            // Keep ASCII specials and invisible/space characters as entities.
            if ($cp === false || $cp < 0xA1 || mb_strlen($decoded, 'UTF-8') !== 1 || ($cp >= 0x2000 && $cp <= 0x200F) || $cp === 0xAD) {
                return $m[0];
            }

            return $decoded;
        }, (string) $html);
    }

    /**
     * Full clean-up used by the "Repair Greek" button and when importing originals.
     */
    public static function normalize($bytes)
    {
        return self::decodeTextEntities(self::repairMojibake(self::toUtf8($bytes)));
    }

    /**
     * Decodes content sent by the admin UI (base64 of UTF-8 bytes).
     * Using base64 for transport keeps the content safe from WAF/mod_security
     * filters and from any input sanitising that may alter HTML or bytes.
     *
     * @return string|false
     */
    public static function decodeTransport($value)
    {
        $value = (string) $value;
        if ($value === '') {
            return '';
        }
        $raw = base64_decode($value, true);
        if ($raw === false) {
            return false;
        }

        return self::toUtf8($raw);
    }

    public static function readFile($path, &$detected = null)
    {
        $raw = @file_get_contents($path);
        if ($raw === false) {
            return false;
        }

        return self::toUtf8($raw, $detected);
    }

    /**
     * Writes UTF-8 without BOM, atomically (temp file + rename).
     */
    public static function writeFile($path, $content)
    {
        $content = self::toUtf8($content);
        $tmp = $path . '.mte' . uniqid('', true) . '.tmp';
        if (@file_put_contents($tmp, $content, LOCK_EX) === false) {
            return false;
        }
        if (file_exists($path)) {
            @chmod($tmp, fileperms($path) & 0777);
        }
        if (!@rename($tmp, $path)) {
            @unlink($tmp);

            return @file_put_contents($path, $content, LOCK_EX) !== false;
        }

        return true;
    }
}
