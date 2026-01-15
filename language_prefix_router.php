<?php
declare(strict_types=1);

/**
 * language_prefix_router.php (R4)
 *
 * Fixes:
 * - Prevent "headers already sent" by ensuring NO output (no BOM, no whitespace)
 * - Parse /ar /en /fr prefix WITHOUT redirect
 * - Normalize REQUEST_URI internally so app.php routes correctly
 *
 * Use:
 * - Include this at the VERY TOP of public_html/app.php (before bootstrap)
 *   require_once __DIR__ . '/language_prefix_router.php';
 */

$uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
$path = parse_url($uri, PHP_URL_PATH);
$query = parse_url($uri, PHP_URL_QUERY);

if (!is_string($path) || $path === '') {
    $path = '/';
}

if (!function_exists('godyar_router_base_prefix')) {
    function godyar_router_base_prefix(): string
    {
        $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
        $dir = str_replace('\\', '/', dirname($script));
        if ($dir === '/' || $dir === '.' || $dir === '\\') {
            return '';
        }
        return rtrim($dir, '/');
    }
}

$basePrefix = godyar_router_base_prefix(); // e.g. /godyar (if installed in subfolder)
$pathForLang = $path;

// For language detection, remove base prefix temporarily
if ($basePrefix !== '' && str_starts_with($pathForLang, $basePrefix . '/')) {
    $pathForLang = substr($pathForLang, strlen($basePrefix));
    if ($pathForLang === '') $pathForLang = '/';
}
$pathForLang = '/' . ltrim($pathForLang, '/');

if (preg_match('#^/(ar|en|fr)(?:/(.*))?$#', $pathForLang, $m)) {
    $lang = (string)$m[1];
    $rest = isset($m[2]) ? (string)$m[2] : '';
    $restPath = ($rest === '') ? '/' : ('/' . ltrim($rest, '/'));

    // Provide lang to the app runtime (no redirect / no setcookie here)
    $_GET['lang'] = $_GET['lang'] ?? $lang;
    $_COOKIE['lang'] = $_COOKIE['lang'] ?? $lang;

    // Normalize REQUEST_URI for routing (strip /{lang} prefix)
    $newPath = ($basePrefix !== '') ? ($basePrefix . $restPath) : $restPath;
    $newUri = $newPath;
    if (is_string($query) && $query !== '') {
        $newUri .= '?' . $query;
    }
    $_SERVER['GDY_ORIGINAL_REQUEST_URI'] = $uri;
    $_SERVER['GDY_LANG_PREFIX'] = $lang;
    $_SERVER['REQUEST_URI'] = $newUri;

    // Optional: expose the remaining path
    if ($rest !== '' && !isset($_GET['path'])) {
        $_GET['path'] = $rest;
    }
}
