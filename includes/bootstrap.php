<?php
declare(strict_types=1);

// -------------------------------------------------
// Global constants used across admin + frontend
// -------------------------------------------------
// These are defined early so standalone scripts that only include
// includes/bootstrap.php can safely rely on them.
if (!defined('GODYAR_ROOT')) {
    define('GODYAR_ROOT', dirname(__DIR__));
}

// Base URL constant used by parts of the admin layer.
// Prefer APP_URL from the environment; fall back to auto-detected URL.
if (!defined('GODYAR_BASE_URL')) {
    $base = '';
    $envAppUrl = getenv('APP_URL');
    if (is_string($envAppUrl) && $envAppUrl !== '') {
        $base = $envAppUrl;
    } else {
        $envAuto = getenv('APP_URL_AUTO');
        if (is_string($envAuto) && $envAuto !== '') {
            $base = $envAuto;
        }
    }

    if ($base === '' && !empty($_SERVER['HTTP_HOST'])) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $base = $scheme . '://' . $_SERVER['HTTP_HOST'];
    }
    define('GODYAR_BASE_URL', rtrim((string)$base, '/'));
}

/**
 * Godyar - Production Bootstrap (Final & Stable)
 * ----------------------------------------------
 * - strict_types صحيح
 * - إعدادات إنتاج آمنة
 * - اتصال قاعدة البيانات
 * - تتبع الزيارات
 * - أدوات Schema
 * - CSP موحد (مرة واحدة) + دعم unsafe-eval (لأدوات الفحص) + nonce
 */

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

require_once ROOT_PATH . '/includes/fs.php';

// Audit log helper
require_once ROOT_PATH . '/includes/audit_log.php';
// DB driver compatibility helpers (MySQL/PostgreSQL)
require_once ROOT_PATH . '/includes/db_compat.php';

// Register internal autoloader (and composer autoload if present)
$autoload = ROOT_PATH . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

// تحميل ENV/.env (مصدر واحد للإعدادات)
// Ensure ENV_FILE is available even under PHP-FPM where Apache SetEnv may not be passed through.
if (!getenv('ENV_FILE') && empty($_SERVER['ENV_FILE'])) {
    $___envFile = '/home/geqzylcq/godyar_private/.env';
    // Do not leak secrets; this only sets the file path.
    putenv('ENV_FILE=' . $___envFile);
    $_SERVER['ENV_FILE'] = $___envFile;
    $_ENV['ENV_FILE'] = $___envFile;
}

// Optional: set ENV_FILE path without relying on .htaccess (useful on shared hosting / PHP-FPM)
// Create /includes/env_path.php (not committed) to define the ENV_FILE location via putenv()/$_SERVER.
if (is_file(__DIR__ . '/env_path.php')) {
    require_once __DIR__ . '/env_path.php';
}

require_once ROOT_PATH . '/includes/env.php';

// DB helpers (PDO source of truth)
require_once ROOT_PATH . '/includes/db.php';


if (!function_exists('normalize_display_name')) {
    /**
     * Normalize display name input (Unicode-safe):
     * - remove zero-width characters
     * - normalize apostrophes
     * - strip diacritics/marks (tashkeel)
     * - collapse whitespace
     */
    function normalize_display_name(string $name): string {
        // Remove zero-width characters (ZWSP, ZWNJ, ZWJ, BOM)
        $name = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $name) ?? $name;
        // Normalize smart apostrophes to plain apostrophe
        $name = str_replace(["’", "‘", "´"], "'", $name);
        // Strip combining marks (helps avoid false rejections)
        $name = preg_replace('/[\p{M}]+/u', '', $name) ?? $name;
        $name = trim($name);
        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;
        return $name;
    }
}

if (!function_exists('is_valid_display_name')) {
    /**
     * Allowed: letters/numbers/spaces and . _ - '
     */
    function is_valid_display_name(string $name, int $min = 2, int $max = 50): bool {
        $name = normalize_display_name($name);
        $len = mb_strlen($name, 'UTF-8');
        if ($len < $min || $len > $max) return false;
        return (bool)preg_match("/^[\\p{L}\\p{N} ._\\-']+$/u", $name);
    }
}

if (!function_exists('sanitize_display_name')) {
    /**
     * Sanitize to allowed character set; returns normalized output.
     */
    function sanitize_display_name(string $name, int $min = 2, int $max = 50): string {
        $name = normalize_display_name($name);
        $name = preg_replace("/[^\\p{L}\\p{N} ._\\-']+/u", '', $name) ?? $name;
        $name = normalize_display_name($name);
        if (mb_strlen($name, 'UTF-8') > $max) {
            $name = mb_substr($name, 0, $max, 'UTF-8');
            $name = rtrim($name);
        }
        if (mb_strlen($name, 'UTF-8') < $min) return '';
        return $name;
    }
}
/* =========================
   PDO driver check (mysql / pgsql)
   ========================= */
$drv = defined('DB_DRIVER') ? strtolower((string)DB_DRIVER) : 'auto';
if ($drv === 'postgres' || $drv === 'postgresql') {
    $drv = 'pgsql';
}
$needExt = ($drv === 'pgsql') ? 'pdo_pgsql' : 'pdo_mysql';

if (!extension_loaded('pdo') || !extension_loaded($needExt)) {
    http_response_code(500);
    if (function_exists('error_log')) {
        error_log('[Bootstrap] Missing PDO driver: ' . $needExt);
    }
    if ($needExt === 'pdo_pgsql') {
        exit('خطأ في إعدادات السيرفر: يلزم تفعيل إضافة PDO PostgreSQL (pdo_pgsql) من لوحة الاستضافة/إعدادات PHP ثم إعادة المحاولة.');
    }
    exit('خطأ في إعدادات السيرفر: يلزم تفعيل إضافة PDO MySQL (pdo_mysql) من لوحة الاستضافة/إعدادات PHP ثم إعادة المحاولة.');
}

/* =========================
   PHP 7.4 compatibility polyfills
   ========================= */
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        if ($needle === '') return true;
        return substr($haystack, 0, strlen($needle)) === $needle;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool
    {
        if ($needle === '') return true;
        return substr($haystack, -strlen($needle)) === $needle;
    }
}

/* =========================
   Error reporting (PRODUCTION)
   ========================= */
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');

$logDir  = ROOT_PATH . '/storage/logs';
$logFile = $logDir . '/php-error.log';

if (!is_dir($logDir)) {
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
}
ini_set('error_log', $logFile);

/* =========================
   Session (secure)
   ========================= */
if (session_status() === PHP_SESSION_NONE) {
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

    if (!headers_sent()) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $isSecure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }

    /* =========================
       Session hardening (shared hosting safe)
       - To isolate sessions from /tmp, set SESSION_SAVE_PATH in .env (recommended)
       - Example: SESSION_SAVE_PATH=/home/USER/godyar/storage/sessions
       ========================= */
    try {
        if (function_exists('ini_set')) {
            $sessPath = function_exists('env') ? (string)env('SESSION_SAVE_PATH', '') : '';
            if ($sessPath !== '' && is_dir($sessPath) && is_writable($sessPath)) {
                ini_set('session.save_path', $sessPath);
            }

            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.cookie_httponly', '1');
        }

        // SameSite support (PHP 7.3+)
        if (PHP_VERSION_ID >= 70300 && function_exists('ini_set')) {
            ini_set('session.cookie_samesite', (string)(function_exists('env') ? env('SESSION_SAMESITE', 'Lax') : 'Lax'));
        }

        // Secure cookies automatically when HTTPS is detected
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);
        if ($isHttps && function_exists('ini_set')) {
            ini_set('session.cookie_secure', '1');
        }
    } catch (Throwable $e) {
        // Keep silent in production; log if logger is available.
        if (function_exists('error_log')) {
            error_log('[bootstrap] session hardening skipped: ' . $e->getMessage());
        }
    }

	    // Avoid legacy cache headers (Expires/Pragma) emitted by PHP's default session cache limiter
	    // so that caching directives are controlled explicitly.
	    if (PHP_SAPI !== 'cli' && function_exists('session_cache_limiter')) {
	        session_cache_limiter('');
	    }

	    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
	        session_start();
	    }

    // --- Session bridge for OAuth + legacy keys (ensures consistent login state across the project) ---
    if (!empty($_SESSION['user']) && is_array($_SESSION['user'])) {
        if (empty($_SESSION['user_id']) && !empty($_SESSION['user']['id'])) {
            $_SESSION['user_id'] = (int)$_SESSION['user']['id'];
        }
        if (empty($_SESSION['user_email']) && !empty($_SESSION['user']['email'])) {
            $_SESSION['user_email'] = (string)$_SESSION['user']['email'];
        }
        if (empty($_SESSION['user_name'])) {
            $_SESSION['user_name'] = (string)($_SESSION['user']['display_name'] ?? $_SESSION['user']['username'] ?? '');
        }
        if (empty($_SESSION['user_role']) && !empty($_SESSION['user']['role'])) {
            $_SESSION['user_role'] = (string)$_SESSION['user']['role'];
        }
        if (empty($_SESSION['is_member_logged'])) {
            $_SESSION['is_member_logged'] = true;
        }
    }
    // --- end session bridge ---
}

/* =========================
   Security Headers + Dynamic CSP (ONE PLACE ONLY)
   ========================= */

// Generate per-request nonce for CSP
if (!defined('GDY_CSP_NONCE')) {
    try {
        define('GDY_CSP_NONCE', rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '='));
    } catch (Throwable $e) {
        define('GDY_CSP_NONCE', 'fallbacknonce');
    }
}

if (!headers_sent()) {
    // Basic hardening headers
    if (function_exists('header_remove')) {
        if (function_exists('header_remove')) {
            header_remove('X-Powered-By');
        }
    }

	    // X-Frame-Options is intentionally omitted; we rely on CSP `frame-ancestors` for clickjacking protection.
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('X-XSS-Protection: 1; mode=block');

    // HSTS only when HTTPS (or behind proxy)
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    if ($isHttps) {
        header('Strict-Transport-Security: max-age=15552000; includeSubDomains');
    }

	    // Cache-control for HTML (prefer Cache-Control over legacy Expires/Pragma)
	    $reqUri = (string)($_SERVER['REQUEST_URI'] ?? '');
	    $isAssetReq = (bool)preg_match('~\\.(?:css|js|map|png|jpe?g|gif|webp|svg|ico|woff2?|ttf|eot|pdf)(?:\\?|$)~i', $reqUri);
	    if (!$isAssetReq) {
	        header('Cache-Control: no-store, max-age=0, must-revalidate');
	    }

	    // CSP (soft for legacy compatibility) + nonce
    $nonce = GDY_CSP_NONCE;

    $csp = "default-src 'self'; "
        . "base-uri 'self'; "
        . "object-src 'none'; "
        . "frame-ancestors 'self'; "
        . "frame-src 'self'; "
        . "img-src 'self' data: blob: https:; "
        . "style-src 'self' 'unsafe-inline' 'nonce-{$nonce}' https:; "
        . "style-src-elem 'self' 'unsafe-inline' 'nonce-{$nonce}' https: https://www.gstatic.com; "
        // Allow style attributes and JS-driven inline styles (CSSOM) even when a nonce exists.
        // Without this, browsers ignore 'unsafe-inline' once a nonce is present and will block style="...".
        . "style-src-attr 'unsafe-inline'; "
        // Keep eval() blocked (do NOT add unsafe-eval).
        . "script-src 'self' 'unsafe-inline' 'nonce-{$nonce}' https:; "
        . "connect-src 'self' https: wss:; "
        . "font-src 'self' data: https:; "
        . "media-src 'self' data: blob:; "
        . "form-action 'self'; "
        . "upgrade-insecure-requests;";

    header('Content-Security-Policy: ' . $csp);
    header('X-Robots-Tag: index, follow');
}

// Auto-inject nonce into <script> and <style> tags if missing (HTML responses only)
$uri    = (string)($_SERVER['REQUEST_URI'] ?? '');
$accept = (string)($_SERVER['HTTP_ACCEPT'] ?? '');
$isHtml = (stripos($accept, 'text/html') !== false) || (stripos($accept, 'application/xhtml+xml') !== false);
$isApi  = (strpos($uri, '/api/') !== false) || (strpos($uri, '/api') === 0);
$isAsset = (bool)preg_match('~\.(?:css|js|map|png|jpe?g|gif|webp|svg|ico|woff2?|ttf|eot|pdf)(?:\?|$)~i', $uri);

if ($isHtml && !$isApi && !$isAsset && PHP_SAPI !== 'cli') {
    ob_start(function ($html) {
        if (!is_string($html) || $html === '') return $html;

        $nonce = GDY_CSP_NONCE;

        $html = preg_replace_callback('~<script\b(?![^>]*\bnonce=)([^>]*)>~i', function ($m) use ($nonce) {
            return '<script nonce="' . htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8') . '"' . $m[1] . '>';
        }, $html);

        $html = preg_replace_callback('~<style\b(?![^>]*\bnonce=)([^>]*)>~i', function ($m) use ($nonce) {
            return '<style nonce="' . htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8') . '"' . $m[1] . '>';
        }, $html);

        return $html;
    });
}

// i18n (ar/en/fr)
require_once ROOT_PATH . "/includes/lang_prefix.php";
require_once ROOT_PATH . "/includes/lang.php";
require_once ROOT_PATH . "/includes/translation.php";

/* =========================
   PDO connection (single source of truth: Godyar\DB)
   ========================= */
try {
    $pdo = \Godyar\DB::pdo();

    // --- Hydrate user id/name from DB for OAuth edge cases (e.g. Google returning email but missing id) ---
    try {
        $emailForHydrate = (string)($_SESSION['user_email'] ?? $_SESSION['email'] ?? ($_SESSION['user']['email'] ?? ''));
        $uidForHydrate   = (int)($_SESSION['user_id'] ?? 0);
        if ($emailForHydrate !== '' && $uidForHydrate <= 0 && isset($pdo) && $pdo instanceof \PDO) {
            $dnCol = db_column_exists($pdo, 'users', 'display_name') ? 'display_name'
                : (db_column_exists($pdo, 'users', 'name') ? 'name'
                : (db_column_exists($pdo, 'users', 'full_name') ? 'full_name'
                : (db_column_exists($pdo, 'users', 'fullName') ? 'fullName' : 'username')));
            $stHyd = $pdo->prepare("SELECT id, username, {$dnCol} AS display_name, role, email FROM users WHERE email = :e LIMIT 1");
            $stHyd->execute([':e' => $emailForHydrate]);
            $rowHyd = $stHyd->fetch(\PDO::FETCH_ASSOC) ?: null;
            if ($rowHyd) {
                $_SESSION['user_id'] = (int)($rowHyd['id'] ?? 0);
                if (empty($_SESSION['user_name'])) {
                    $_SESSION['user_name'] = (string)($rowHyd['display_name'] ?? $rowHyd['username'] ?? '');
                }
                if (empty($_SESSION['user_role']) && !empty($rowHyd['role'])) {
                    $_SESSION['user_role'] = (string)$rowHyd['role'];
                }
                if (empty($_SESSION['user_email']) && !empty($rowHyd['email'])) {
                    $_SESSION['user_email'] = (string)$rowHyd['email'];
                }
                if (!empty($_SESSION['user']) && is_array($_SESSION['user'])) {
                    $_SESSION['user']['id'] = (int)($_SESSION['user_id'] ?? 0);
                    if (empty($_SESSION['user']['display_name']) && !empty($_SESSION['user_name'])) {
                        $_SESSION['user']['display_name'] = (string)$_SESSION['user_name'];
                    }
                    if (empty($_SESSION['user']['email']) && !empty($_SESSION['user_email'])) {
                        $_SESSION['user']['email'] = (string)$_SESSION['user_email'];
                    }
                }
                $_SESSION['is_member_logged'] = true;
            }
        }
    } catch (\Throwable $e) {
        // ignore hydration failures
    }
    // --- end hydrate ---
} catch (Throwable $e) {
    http_response_code(500);
    exit('فشل الاتصال بقاعدة البيانات.');
}

// Legacy compatibility: only if LEGACY_GLOBAL_PDO=1
gdy_register_global_pdo();

/* =========================
   DI Container + legacy DB adapter
   ========================= */
try {
    if (class_exists('Godyar\\Container')) {
        $container = new \Godyar\Container($pdo);
        $GLOBALS['container'] = $container;
    }

    // Legacy $database adapter (used by older classes in includes/classes/*.php)
    if (env('LEGACY_DATABASE_ADAPTER', '1') !== '0' && class_exists('Godyar\\Legacy\\DatabaseAdapter')) {
        $database = new \Godyar\Legacy\DatabaseAdapter($pdo);
        $GLOBALS['database'] = $database;
    }
} catch (Throwable $e) {
    if (function_exists('error_log')) {
        error_log('[Bootstrap] container/legacy adapter init: ' . $e->getMessage());
    }
}

/* =========================
   DB helpers
   ========================= */
if (!function_exists('db_table_exists')) {
    function db_table_exists(PDO $pdo, string $table): bool {
        $table = trim($table);
        if ($table === '') return false;

        $isPg = function_exists('gdy_pdo_is_pgsql') ? gdy_pdo_is_pgsql($pdo) : false;

        if ($isPg) {
            $st = $pdo->prepare("
                SELECT 1
                FROM information_schema.tables
                WHERE table_catalog = current_database()
                  AND table_schema = current_schema()
                  AND table_name = :t
                LIMIT 1
            ");
        } else {
            $st = $pdo->prepare("
                SELECT 1
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                  AND table_name = :t
                LIMIT 1
            ");
        }

        $st->execute([':t' => $table]);
        return (bool)$st->fetchColumn();
    }
}

if (!function_exists('db_column_exists')) {
    function db_column_exists(PDO $pdo, string $table, string $column): bool {
        $table = trim($table);
        $column = trim($column);
        if ($table === '' || $column === '') return false;

        $isPg = function_exists('gdy_pdo_is_pgsql') ? gdy_pdo_is_pgsql($pdo) : false;

        if ($isPg) {
            $st = $pdo->prepare("
                SELECT 1
                FROM information_schema.columns
                WHERE table_catalog = current_database()
                  AND table_schema = current_schema()
                  AND table_name = :t
                  AND column_name = :c
                LIMIT 1
            ");
        } else {
            $st = $pdo->prepare("
                SELECT 1
                FROM information_schema.columns
                WHERE table_schema = DATABASE()
                  AND table_name = :t
                  AND column_name = :c
                LIMIT 1
            ");
        }

        $st->execute([':t' => $table, ':c' => $column]);
        return (bool)$st->fetchColumn();
    }
}

if (!function_exists('db_table_columns')) {
    function db_table_columns(PDO $pdo, string $table): array {
        $table = trim($table);
        if ($table === '') return [];

        $isPg = function_exists('gdy_pdo_is_pgsql') ? gdy_pdo_is_pgsql($pdo) : false;

        if ($isPg) {
            $st = $pdo->prepare("
                SELECT column_name
                FROM information_schema.columns
                WHERE table_catalog = current_database()
                  AND table_schema = current_schema()
                  AND table_name = :t
                ORDER BY ordinal_position
            ");
        } else {
            $st = $pdo->prepare("
                SELECT column_name
                FROM information_schema.columns
                WHERE table_schema = DATABASE()
                  AND table_name = :t
                ORDER BY ordinal_position
            ");
        }

        $st->execute([':t' => $table]);
        return $st->fetchAll(PDO::FETCH_COLUMN, 0) ?: [];
    }
}

/* =========================
   Visits table (auto create)
   ========================= */
if (!db_table_exists($pdo, 'visits')) {
    $pdo->exec("
        CREATE TABLE visits (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          page VARCHAR(60) NOT NULL,
          news_id INT NULL,
          source VARCHAR(20) NOT NULL DEFAULT 'direct',
          referrer VARCHAR(255) NULL,
          user_ip VARCHAR(45) NULL,
          user_agent VARCHAR(255) NULL,
          os VARCHAR(40) NULL,
          browser VARCHAR(40) NULL,
          device VARCHAR(20) NULL,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          KEY idx_created (created_at),
          KEY idx_news (news_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

try {
    if (db_table_exists($pdo, 'visits')) {
        if (!db_column_exists($pdo, 'visits', 'os')) {
            $pdo->exec("ALTER TABLE visits ADD COLUMN os VARCHAR(40) NULL AFTER user_agent");
        }
        if (!db_column_exists($pdo, 'visits', 'browser')) {
            $pdo->exec("ALTER TABLE visits ADD COLUMN browser VARCHAR(40) NULL AFTER os");
        }
        if (!db_column_exists($pdo, 'visits', 'device')) {
            $pdo->exec("ALTER TABLE visits ADD COLUMN device VARCHAR(20) NULL AFTER browser");
        }
    }
} catch (Throwable $e) {
    if (function_exists('error_log')) {
        error_log('[Bootstrap] visits schema migrate: ' . $e->getMessage());
    }
}

/* =========================
   Comments (auto create)
   ========================= */
if (!db_table_exists($pdo, 'news_comments')) {
    $pdo->exec("
        CREATE TABLE news_comments (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          news_id INT NOT NULL,
          user_id INT NULL,
          name VARCHAR(150) NULL,
          email VARCHAR(190) NULL,
          body TEXT NOT NULL,
          parent_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
          status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
          score INT NOT NULL DEFAULT 0,
          ip VARCHAR(45) NULL,
          user_agent VARCHAR(255) NULL,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME NULL,
          KEY idx_news (news_id),
          KEY idx_status (status),
          KEY idx_parent (parent_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

if (!db_table_exists($pdo, 'news_comment_votes')) {
    $pdo->exec("
        CREATE TABLE news_comment_votes (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          comment_id BIGINT UNSIGNED NOT NULL,
          user_id INT NULL,
          ip VARCHAR(45) NULL,
          value TINYINT NOT NULL,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          UNIQUE KEY uq_vote_user (comment_id, user_id),
          UNIQUE KEY uq_vote_ip (comment_id, ip),
          KEY idx_comment (comment_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

try {
    $cols = gdy_db_stmt_columns($pdo, 'users')->fetchAll(PDO::FETCH_COLUMN, 0);
    if (is_array($cols) && !in_array('github_id', $cols, true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN github_id VARCHAR(60) NULL AFTER id");
    }
} catch (Throwable $e) {
    // Ignore if users table is legacy or missing
}

/* =========================
   Visits analytics
   ========================= */
function gdy_classify_source(string $ref): string {
    if ($ref === '') return 'direct';
    $h = strtolower(parse_url($ref, PHP_URL_HOST) ?? '');
    if ($h === '') return 'referral';

    foreach (['google','bing','yahoo','duckduckgo'] as $s) {
        if (str_contains($h, $s)) return 'search';
    }

    foreach (['facebook','twitter','x.com','instagram','t.me','telegram','tiktok'] as $s) {
        if (str_contains($h, $s)) return 'social';
    }

    return 'referral';
}

function gdy_parse_device(string $ua): string {
    $u = strtolower($ua);
    if ($u === '') return 'Unknown';
    if (str_contains($u, 'tablet') || str_contains($u, 'ipad')) return 'Tablet';
    if (str_contains($u, 'mobi') || str_contains($u, 'android') || str_contains($u, 'iphone')) return 'Mobile';
    return 'Desktop';
}

function gdy_parse_os(string $ua): string {
    $u = strtolower($ua);
    if ($u === '') return 'Unknown';
    if (str_contains($u, 'windows nt')) return 'Windows';
    if (str_contains($u, 'android')) return 'Android';
    if (str_contains($u, 'iphone') || str_contains($u, 'ipad') || str_contains($u, 'ipod')) return 'iOS';
    if (str_contains($u, 'mac os x') || str_contains($u, 'macintosh')) return 'macOS';
    if (str_contains($u, 'linux')) return 'Linux';
    return 'Other';
}

function gdy_parse_browser(string $ua): string {
    $u = strtolower($ua);
    if ($u === '') return 'Unknown';
    if (str_contains($u, 'edg/')) return 'Edge';
    if (str_contains($u, 'opr/') || str_contains($u, 'opera')) return 'Opera';
    if (str_contains($u, 'firefox/')) return 'Firefox';
    if (str_contains($u, 'chrome/') || str_contains($u, 'crios/')) return 'Chrome';
    if (str_contains($u, 'safari/') && !str_contains($u, 'chrome/') && !str_contains($u, 'crios/')) return 'Safari';
    if (str_contains($u, 'msie') || str_contains($u, 'trident/')) return 'IE';
    return 'Other';
}

function gdy_track_visit(string $page, ?int $newsId=null): void {
    $pdo = gdy_pdo_safe();
    if (!$pdo instanceof PDO) return;

    if (session_status() !== PHP_SESSION_ACTIVE) {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
    }

    $key = 'visit_'.$page.'_'.($newsId ?? 0);
    if (isset($_SESSION[$key]) && time() - (int)$_SESSION[$key] < 600) return;
    $_SESSION[$key] = time();

    $ref = (string)($_SERVER['HTTP_REFERER'] ?? '');
    $ua  = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250);

    try {
        $pdo->prepare("
            INSERT INTO visits (page,news_id,source,referrer,user_ip,user_agent,os,browser,device)
            VALUES (?,?,?,?,?,?,?,?,?)
        ")->execute([
            $page,
            $newsId,
            gdy_classify_source($ref),
            $ref,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $ua,
            gdy_parse_os($ua),
            gdy_parse_browser($ua),
            gdy_parse_device($ua)
        ]);
    } catch (Throwable $e) {
        try {
            $pdo->prepare("INSERT INTO visits (page,news_id,source,referrer,user_ip,user_agent) VALUES (?,?,?,?,?,?)")
                ->execute([
                    $page,
                    $newsId,
                    gdy_classify_source($ref),
                    $ref,
                    $_SERVER['REMOTE_ADDR'] ?? null,
                    $ua,
                ]);
        } catch (Throwable $e2) {
            if (function_exists('error_log')) {
                error_log('[Visits] insert failed: ' . $e2->getMessage());
            }
        }
    }
}

function gdy_auto_track_request(): void
{
    try {
        if (PHP_SAPI === 'cli') return;
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') return;

        $uri  = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        if (!is_string($path) || $path === '') $path = '/';

        if (preg_match('#\.(css|js|png|jpg|jpeg|gif|svg|webp|ico|woff2?|ttf|map)$#i', $path)) return;

        $trim = '/' . ltrim($path, '/');
        if (preg_match('#^/(ar|en|fr)?/?admin(/|$)#i', $trim)) return;
        if (preg_match('#^/(ar|en|fr)?/?api(/|$)#i', $trim)) return;
        if (str_ends_with($trim, '/track.php') || $trim === '/track.php') return;

        $page = 'other';
        $newsId = null;

        if ($trim === '/' || preg_match('#^/(ar|en|fr)/?$#', $trim)) {
            $page = 'home';
        } elseif ($trim === '/search' || preg_match('#^/(ar|en|fr)/search$#', $trim)) {
            $page = 'search';
        } elseif (preg_match('#^/(ar|en|fr)/category/#', $trim) || str_starts_with($trim, '/category/')) {
            $page = 'category';
        } elseif (preg_match('#^/(ar|en|fr)/tag/#', $trim) || str_starts_with($trim, '/tag/')) {
            $page = 'tag';
        } elseif (preg_match('#^/(ar|en|fr)/page/#', $trim) || str_starts_with($trim, '/page/')) {
            $page = 'page';
        } elseif (preg_match('#/news/id/(\d+)#', $trim, $m)) {
            $page = 'article';
            $newsId = (int)$m[1];
        } elseif (str_ends_with($trim, '/opinion_author.php') || $trim === '/opinion_author.php') {
            $page = 'opinion_author';
        }

        gdy_track_visit($page, $newsId);
    } catch (Throwable $e) {
        if (function_exists('error_log')) {
            error_log('[Bootstrap] auto track: ' . $e->getMessage());
        }
    }
}

// نفّذ التتبع التلقائي
gdy_auto_track_request();

/* =========================
   Base URL
   ========================= */
function base_url(string $path = ''): string {
    $base = '';
    if (defined('APP_URL') && APP_URL) {
        $base = rtrim(APP_URL, '/');
    } else {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? '';
        if ($host) $base = $scheme . '://' . $host;
    }
    if ($base === '') return '';
    if ($path === '') return $base;
    return $base . '/' . ltrim($path, '/');
}

if (!defined('BASE_URL')) {
    $b = base_url();
    if ($b !== '') {
        define('BASE_URL', rtrim($b, '/') . '/');
    } elseif (defined('APP_URL_AUTO') && APP_URL_AUTO) {
        define('BASE_URL', rtrim((string)APP_URL_AUTO, '/') . '/');
    } else {
        define('BASE_URL', '/');
    }
}

date_default_timezone_set((defined('TIMEZONE') && TIMEZONE) ? TIMEZONE : 'Asia/Riyadh');

// IndexNow helper
require_once ROOT_PATH . '/includes/indexnow.php';

// Load site settings helpers (frontend options, theme, etc.)
require_once ROOT_PATH . '/includes/site_settings.php';
