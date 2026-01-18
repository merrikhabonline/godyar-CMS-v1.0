<?php
declare(strict_types=1);

/**
 * legacy_monitor.php
 * Lightweight logging for deprecated endpoints and suspicious legacy hits.
 *
 * Writes JSON lines to: storage/logs/legacy-access.log (outside public via .htaccess deny)
 *
 * Enable with ENV: LEGACY_MONITOR=1
 */

function legacy_monitor_enabled(): bool
{
    $v = getenv('LEGACY_MONITOR');
    if ($v === false) return false;
    $v = strtolower(trim((string)$v));
    return in_array($v, ['1','true','yes','on'], true);
}

function legacy_log(string $event, array $extra = []): void
{
    if (!legacy_monitor_enabled()) {
        return;
    }

    $root = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__);
    $logDir = $root . '/storage/logs';
    if (!is_dir($logDir)) {
        gdy_mkdir($logDir, 0755, true);
    }
    $file = $logDir . '/legacy-access.log';

    $row = [
        'ts' => gmdate('c'),
        'event' => $event,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        'method' => $_SERVER['REQUEST_METHOD'] ?? '',
        'uri' => $_SERVER['REQUEST_URI'] ?? '',
        'qs' => $_SERVER['QUERY_STRING'] ?? '',
        'ref' => $_SERVER['HTTP_REFERER'] ?? '',
        'ua' => $_SERVER['HTTP_USER_AGENT'] ?? '',
    ] + $extra;

    $line = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . PHP_EOL;
    gdy_file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
}
