<?php
declare(strict_types=1);

/**
 * includes/lang_prefix.php (R4 - NO REDIRECTS)
 *
 * Purpose:
 * - Determine current language (ar/en/fr) from:
 *   1) URL prefix (/ar, /en, /fr) if still present (e.g., direct includes)
 *   2) $_GET['lang']
 *   3) Cookie 'lang'
 *   4) Default 'ar'
 *
 * IMPORTANT:
 * - This file MUST NOT redirect to avoid ERR_TOO_MANY_REDIRECTS loops.
 * - This file MUST NOT output anything.
 */

$supported = ['ar','en','fr'];
$defaultLang = 'ar';

$uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
$path = parse_url($uri, PHP_URL_PATH);
if (!is_string($path) || $path === '') $path = '/';

// Detect base prefix if installed in subfolder
$script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
$dir = str_replace('\\', '/', dirname($script));
$basePrefix = ($dir === '/' || $dir === '.' || $dir === '\\') ? '' : rtrim($dir, '/');

$pathForLang = $path;
if ($basePrefix !== '' && str_starts_with($pathForLang, $basePrefix . '/')) {
    $pathForLang = substr($pathForLang, strlen($basePrefix));
    if ($pathForLang === '') $pathForLang = '/';
}
$pathForLang = '/' . ltrim($pathForLang, '/');

$lang = '';

// 1) URL prefix (if present)
if (preg_match('#^/(ar|en|fr)(?:/|$)#', $pathForLang, $m)) {
    $lang = (string)$m[1];
}

// 2) GET
if ($lang === '' && !empty($_GET['lang']) && in_array((string)$_GET['lang'], $supported, true)) {
    $lang = (string)$_GET['lang'];
}

// 3) Cookie
if ($lang === '' && !empty($_COOKIE['lang']) && in_array((string)$_COOKIE['lang'], $supported, true)) {
    $lang = (string)$_COOKIE['lang'];
}

// 4) Default
if ($lang === '' || !in_array($lang, $supported, true)) {
    $lang = $defaultLang;
}

// Publish to runtime
$_GET['lang'] = $lang;
$_COOKIE['lang'] = $lang;
$GLOBALS['lang'] = $lang;

if (!defined('GDY_LANG')) {
    define('GDY_LANG', $lang);
}

// Persist cookie if possible (best-effort; no redirects)
if (!headers_sent()) {
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

    // Lax to allow normal browsing; httponly false because UI may read it in JS
    setcookie('lang', $lang, [
        'expires'  => time() + 86400 * 365,
        'path'     => '/',
        'secure'   => $isSecure,
        'httponly' => false,
        'samesite' => 'Lax',
    ]);
}
