<?php
declare(strict_types=1);

/**
 * site_settings.php (schema-tolerant)
 *
 * Goals:
 * - Avoid warnings: Undefined array key "key" (different schemas)
 * - Provide stable helpers for both new controllers and legacy pages
 * - Backward-compatible gdy_load_settings() that accepts:
 *     - gdy_load_settings(true)            => force reload
 *     - gdy_load_settings($pdo)            => use given PDO
 *     - gdy_load_settings($pdo, true)      => use PDO + force reload
 */

if (!function_exists('site_settings_load')) {
    /**
     * @param mixed $pdoOrForce  PDO instance OR bool force flag
     * @param bool  $force       Force reload (when first arg is not PDO)
     * @return array<string,string>
     */
    function site_settings_load($pdoOrForce = false, bool $force = false): array
    {
        // Resolve PDO + force flag safely
        $pdo = null;
        $resolvedForce = $force;

        if ($pdoOrForce instanceof \PDO) {
            $pdo = $pdoOrForce;
            $resolvedForce = $force; // allow (PDO, true)
        } elseif (is_bool($pdoOrForce)) {
            $resolvedForce = $pdoOrForce;
        }

        // If caller passed (false, PDO) by mistake, accept it.
        $args = func_get_args();
        if (!$pdo instanceof \PDO && isset($args[1]) && $args[1] instanceof \PDO) {
            $pdo = $args[1];
        }

        // Fallback to globals/helpers
        if (!$pdo instanceof \PDO) {
            $pdo = $GLOBALS['pdo'] ?? null;
        }
        if (!$pdo instanceof \PDO && function_exists('gdy_pdo_safe')) {
            $pdo = gdy_pdo_safe();
        }

        if (!$pdo instanceof \PDO) {
            $GLOBALS['site_settings'] = [];
            return [];
        }

        static $cache = null;
        static $cacheAt = 0;

        $ttl = defined('SITE_SETTINGS_TTL') ? (int) SITE_SETTINGS_TTL : 300; // seconds
        if (!$resolvedForce && is_array($cache) && (time() - $cacheAt) < $ttl) {
            $GLOBALS['site_settings'] = $cache;
            return $cache;
        }

        // Best-effort create table on first run (shared hosting)
        try {
            $isPg = function_exists('gdy_pdo_is_pgsql') ? gdy_pdo_is_pgsql($pdo) : false;
            if ($isPg) {
                $pdo->exec("\n                    CREATE TABLE IF NOT EXISTS settings (\n                        id BIGSERIAL PRIMARY KEY,\n                        setting_key VARCHAR(100) NOT NULL UNIQUE,\n                        setting_value TEXT NULL,\n                        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,\n                        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP\n                    )\n                ");
            } else {
                $pdo->exec("\n                    CREATE TABLE IF NOT EXISTS settings (\n                        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,\n                        setting_key VARCHAR(100) NOT NULL UNIQUE,\n                        setting_value TEXT NULL,\n                        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n                        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP\n                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4\n                ");
            }
        } catch (\Throwable $e) {
            error_log('[site_settings] create table: ' . $e->getMessage());
        }

        // Load all rows (schema tolerant)
        $rows = [];
        try {
            $st = $pdo->query('SELECT * FROM settings');
            $rows = $st ? ($st->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
        } catch (\Throwable $e) {
            error_log('[site_settings] load: ' . $e->getMessage());
            $rows = [];
        }

        $settings = [];
        foreach ($rows as $row) {
            if (!is_array($row)) continue;

            // Key column could be named differently across forks
            $k = '';
            foreach (['setting_key', 'key', 'name', 'option_key', 'option_name'] as $kk) {
                if (isset($row[$kk]) && (string) $row[$kk] !== '') {
                    $k = (string) $row[$kk];
                    break;
                }
            }
            if ($k === '') continue;

            // Value column could be named differently
            $v = null;
            foreach (['setting_value', 'value', 'val', 'option_value'] as $vk) {
                if (array_key_exists($vk, $row)) {
                    $v = $row[$vk];
                    break;
                }
            }

            if (is_array($v) || is_object($v)) {
                $settings[$k] = (string) json_encode($v, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            } elseif ($v === null) {
                $settings[$k] = '';
            } else {
                $settings[$k] = (string) $v;
            }
        }

        $GLOBALS['site_settings'] = $settings;
        $cache = $settings;
        $cacheAt = time();

        return $settings;
    }
}

if (!function_exists('gdy_load_settings')) {
    /**
     * Backward-compatible wrapper.
     * @return array<string,string>
     */
    function gdy_load_settings($pdoOrForce = false, bool $force = false): array
    {
        return site_settings_load($pdoOrForce, $force);
    }
}

if (!function_exists('site_setting')) {
    function site_setting(string $key, $default = ''): string
    {
        $all = site_settings_load(false);
        return array_key_exists($key, $all) ? (string) $all[$key] : (string) $default;
    }
}

if (!function_exists('site_settings_all')) {
    /** @return array<string,string> */
    function site_settings_all(): array
    {
        return site_settings_load(false);
    }
}


// -----------------------------------------------------------------------------
// Frontend helpers (legacy compatibility)
// -----------------------------------------------------------------------------
if (!function_exists('gdy_setting')) {
    /**
     * Safe getter from settings array.
     * @param array<string,mixed> $settings
     */
    function gdy_setting(array $settings, string $key, $default = ''): string
    {
        if ($key === '') {
            return (string) $default;
        }
        $v = $settings[$key] ?? $default;
        if (is_array($v) || is_object($v)) {
            return (string) $default;
        }
        return (string) $v;
    }
}

if (!function_exists('gdy_setting_int')) {
    /**
     * @param array<string,mixed> $settings
     */
    function gdy_setting_int(array $settings, string $key, int $default = 0): int
    {
        $v = $settings[$key] ?? $default;
        return is_numeric($v) ? (int) $v : $default;
    }
}

if (!function_exists('gdy_setting_bool')) {
    /**
     * @param array<string,mixed> $settings
     */
    function gdy_setting_bool(array $settings, string $key, bool $default = false): bool
    {
        $v = $settings[$key] ?? null;
        if ($v === null) return $default;
        if (is_bool($v)) return $v;
        $s = strtolower(trim((string)$v));
        if (in_array($s, ['1','true','yes','on'], true)) return true;
        if (in_array($s, ['0','false','no','off'], true)) return false;
        return $default;
    }
}

if (!function_exists('gdy_prepare_frontend_options')) {
    /**
     * Prepare common variables for legacy frontend controllers/views.
     *
     * Returns an array to be used with extract($frontendOptions).
     *
     * @param array<string,mixed> $settings
     * @return array<string,mixed>
     */
    function gdy_prepare_frontend_options(array $settings): array
    {
        // Common identity
        $siteName = gdy_setting($settings, 'site_name', gdy_setting($settings, 'name', ''));
        $siteTitle = gdy_setting($settings, 'site_title', $siteName);
        $siteDescription = gdy_setting($settings, 'site_description', '');

        // URLs
        $baseUrl = rtrim(gdy_setting($settings, 'base_url', gdy_setting($settings, 'app_url', '')), '/');
        $assetsUrl = rtrim(gdy_setting($settings, 'assets_url', $baseUrl . '/assets'), '/');

        // Branding
        $logo = gdy_setting($settings, 'logo', gdy_setting($settings, 'site_logo', ''));
        $favicon = gdy_setting($settings, 'favicon', '');

        // Language / direction (defaults; the real router may override)
        $lang = gdy_setting($settings, 'default_lang', 'ar');
        $dir = in_array($lang, ['ar', 'fa', 'ur'], true) ? 'rtl' : 'ltr';

        // Feature flags / UI toggles
        $showSidebar = !gdy_setting_bool($settings, 'hide_frontend_sidebar', false);
// Extended theme / UI strings used by legacy controllers
$siteTagline = gdy_setting($settings, 'site_tagline', gdy_setting($settings, 'tagline', ''));
$siteLogo = $logo;
$primaryColor = gdy_setting($settings, 'primary_color', '#0d6efd');
$primaryDark  = gdy_setting($settings, 'primary_dark', '#0b5ed7');
$themeClass   = gdy_setting($settings, 'theme_class', '');
$searchPlaceholder = gdy_setting($settings, 'search_placeholder', 'بحث...');
$homeLatestTitle        = gdy_setting($settings, 'home_latest_title', 'الأحدث');
$homeFeaturedTitle      = gdy_setting($settings, 'home_featured_title', 'مختارات');
$homeTabsTitle          = gdy_setting($settings, 'home_tabs_title', 'التبويبات');
$homeMostReadTitle      = gdy_setting($settings, 'home_most_read_title', 'الأكثر قراءة');
$homeMostCommentedTitle = gdy_setting($settings, 'home_most_commented_title', 'الأكثر تعليقاً');
$homeRecommendedTitle   = gdy_setting($settings, 'home_recommended_title', 'موصى به');
$carbonBadgeText        = gdy_setting($settings, 'carbon_badge_text', '');
$showCarbonBadge        = gdy_setting_bool($settings, 'show_carbon_badge', false);
return [
    'settings' => $settings,

    // Identity / branding
    'siteName' => $siteName,
    'siteTitle' => $siteTitle,
    'siteDescription' => $siteDescription,
    'siteTagline' => $siteTagline,
    'logo' => $logo,
    'siteLogo' => $siteLogo,
    'favicon' => $favicon,

    // Theme
    'primaryColor' => $primaryColor,
    'primaryDark' => $primaryDark,
    'themeClass' => $themeClass,

    // URLs
    'baseUrl' => $baseUrl,
    'assetsUrl' => $assetsUrl,

    // Language
    'lang' => $lang,
    'dir' => $dir,

    // UI strings
    'searchPlaceholder' => $searchPlaceholder,
    'homeLatestTitle' => $homeLatestTitle,
    'homeFeaturedTitle' => $homeFeaturedTitle,
    'homeTabsTitle' => $homeTabsTitle,
    'homeMostReadTitle' => $homeMostReadTitle,
    'homeMostCommentedTitle' => $homeMostCommentedTitle,
    'homeRecommendedTitle' => $homeRecommendedTitle,
    'carbonBadgeText' => $carbonBadgeText,
    'showCarbonBadge' => $showCarbonBadge,

    // Feature flags
    'showSidebar' => $showSidebar,
];
    }
}
