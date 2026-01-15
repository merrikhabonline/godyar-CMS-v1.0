<?php
declare(strict_types=1);

/**
 * Simple i18n for Godyar
 * - Supported: ar, en, fr
 * - Switch via ?lang=ar|en|fr
 * - Persist via session + cookie
 */

if (!defined('GDY_SUPPORTED_LANGS')) {
    define('GDY_SUPPORTED_LANGS', ['ar','en','fr']);
}

if (!function_exists('gdy_lang')) {
    function gdy_lang(): string
    {
        $supported = GDY_SUPPORTED_LANGS;

        $q = isset($_GET['lang']) ? strtolower(trim((string)$_GET['lang'])) : '';
        if ($q !== '' && in_array($q, $supported, true)) {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                @session_start();
            }
            $_SESSION['gdy_lang'] = $q;

            if (!headers_sent()) {
                setcookie('gdy_lang', $q, [
                    'expires'  => time() + 60*60*24*90,
                    'path'     => '/',
                    'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
                    'httponly' => false,
                    'samesite' => 'Lax',
                ]);
            }
            return $q;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        $s = isset($_SESSION['gdy_lang']) ? strtolower(trim((string)$_SESSION['gdy_lang'])) : '';
        if ($s !== '' && in_array($s, $supported, true)) {
            return $s;
        }

        $c = isset($_COOKIE['gdy_lang']) ? strtolower(trim((string)$_COOKIE['gdy_lang'])) : '';
        if ($c !== '' && in_array($c, $supported, true)) {
            $_SESSION['gdy_lang'] = $c;
            return $c;
        }

        $_SESSION['gdy_lang'] = 'ar';
        return 'ar';
    }
}

if (!function_exists('gdy_is_rtl')) {
    function gdy_is_rtl(): bool
    {
        return gdy_lang() === 'ar';
    }
}

if (!function_exists('gdy_locale_dict')) {
    function gdy_locale_dict(): array
    {
                static $cache = [];
        $lang = gdy_lang();
        
        $lang = strtolower(preg_replace('~[^a-z]~', '', (string)$lang));
        $allowed = ['ar','en','fr'];
        if (!in_array($lang, $allowed, true)) { $lang = 'ar'; }
        $langDir = realpath(ROOT_PATH . '/languages') ?: (ROOT_PATH . '/languages');
if (isset($cache[$lang])) return $cache[$lang];

        $file = rtrim($langDir, '/\\') . '/' . $lang . '.php';
        $fileReal = realpath($file);
        if ($fileReal === false || strpos($fileReal, rtrim($langDir, '/\\') . DIRECTORY_SEPARATOR) !== 0) { $fileReal = null; }
        $dict = [];
        if (is_file($file)) {
            $safeFile = gdy_i18n_safe_file($file, $langDir);
            if ($safeFile === null) { $tmp = null; } else { $tmp = require $safeFile; }
            if (is_array($tmp)) {
                $dict = $tmp;
            }
        }

        // Optional additive patch file: /languages/{lang}_patch.php
        $patch = $langDir . '/' . $lang . '_patch.php';
        $patchReal = realpath($patch);
        if ($patchReal === false || strpos($patchReal, $langDir . DIRECTORY_SEPARATOR) !== 0) {
            $patchReal = null;
        }
        $patch = $patchReal;
        if ($patch !== null && is_file($patch)) {
            $safePatch = gdy_i18n_safe_file($patch, $langDir);
            if ($safePatch === null) { $tmp2 = null; } else { $tmp2 = require $safePatch; }
            if (is_array($tmp2)) {
                // patch overrides base
                $dict = array_merge($dict, $tmp2);
            }
        }

        $cache[$lang] = $dict;
        return $dict;
    }
}

if (!function_exists('__')) {
    /**
     * Translate a UI string key.
     * If not found, returns the key.
     */
    /**
 * Translate a key.
 *
 * Supported call styles:
 *  - __('Some key')
 *  - __('Some key', ['name' => 'Ali'])
 *  - __('t_xxx', 'Arabic fallback text')   // backward compatible with earlier UI pass
 *  - __('t_xxx', 'Arabic fallback', ['name' => 'Ali'])
 */
function __(string $key, $varsOrFallback = [] , array $vars2 = []): string
{
    $dict = gdy_locale_dict();

    $fallback = null;
    $vars = [];

    // 2nd argument can be vars (array) OR fallback (string)
    if (is_array($varsOrFallback)) {
        $vars = $varsOrFallback;
    } elseif (is_string($varsOrFallback) && $varsOrFallback !== '') {
        $fallback = $varsOrFallback;
        $vars = $vars2; // optional 3rd arg vars
    }

    $text = $dict[$key] ?? ($fallback ?? $key);

    if (!empty($vars)) {
        foreach ($vars as $k => $v) {
            $text = str_replace('{' . $k . '}', (string)$v, (string)$text);
        }
    }
    return (string)$text;
}
}

if (!function_exists('gdy_lang_url')) {
    function gdy_lang_url(string $lang): string
    {
        $lang = strtolower(trim($lang));
                if (!preg_match('/^[a-z0-9_-]{2,15}$/', $lang)) {
            return [];
        }
        $langDir = realpath(ROOT_PATH . '/languages');
        if ($langDir === false) {
            return [];
        }
if (!in_array($lang, GDY_SUPPORTED_LANGS, true)) {
            $lang = 'ar';
        }

        // داخل لوحة التحكم نستخدم ?lang= (لا يوجد rewrite لمسارات /en/admin)
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $parts = parse_url($uri) ?: [];
        $path = (string)($parts['path'] ?? '/');
        $queryStr = (string)($parts['query'] ?? '');

        $query = [];
        if ($queryStr !== '') {
            parse_str($queryStr, $query);
        }
        $query['lang'] = $lang;
        $qs = http_build_query($query);
        return $path . ($qs ? ('?' . $qs) : '');
    }
}

/**
 * Resolve a translation file safely within /languages
 */
function gdy_i18n_safe_file(string $path, string $dir): ?string {
    $dirReal = realpath($dir);
    if ($dirReal === false) return null;
    $pReal = realpath($path);
    if ($pReal === false) return null;
    if (strpos($pReal, $dirReal . DIRECTORY_SEPARATOR) !== 0) return null;
    return $pReal;
}
