<?php
/**
 * BOM/Whitespace scanner for PHP files.
 *
 * Usage:
 *   php tools/scan_bom.php
 *   php tools/scan_bom.php --fix
 *
 * Notes:
 * - Detects UTF-8 BOM (0xEFBBBF)
 * - Detects leading whitespace BEFORE <?php
 * - Detects trailing whitespace AFTER closing ?> (if present)
 */
declare(strict_types=1);

$root = realpath(__DIR__ . '/..');
$fix  = in_array('--fix', $argv, true);

$rii = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

$targets = [];
foreach ($rii as $file) {
    /** @var SplFileInfo $file */
    if (!$file->isFile()) continue;
    $path = $file->getPathname();

    // Skip vendor and common binary/static assets
    $rel = str_replace($root . DIRECTORY_SEPARATOR, '', $path);
    if (strpos($rel, 'vendor' . DIRECTORY_SEPARATOR) === 0) continue;
    if (preg_match('/\.(png|jpe?g|gif|webp|avif|ico|woff2?|ttf|otf|mp4|zip)$/i', $rel)) continue;
    if (!preg_match('/\.php$/i', $rel)) continue;

    $targets[] = [$path, $rel];
}

$issues = [];
foreach ($targets as [$path, $rel]) {
    $data = file_get_contents($path);
    if ($data === false) continue;

    $original = $data;
    $changed = false;

    // BOM
    if (substr($data, 0, 3) === "\xEF\xBB\xBF") {
        $issues[] = [$rel, 'UTF-8 BOM at start'];
        if ($fix) {
            $data = substr($data, 3);
            $changed = true;
        }
    }

    // leading whitespace before <?php
    if (preg_match('/^\s+<\?php/s', $data)) {
        $issues[] = [$rel, 'Leading whitespace before <?php'];
        if ($fix) {
            $data = preg_replace('/^\s+(<\?php)/s', '$1', $data, 1);
            $changed = true;
        }
    }

    // trailing whitespace after closing ?>
    if (preg_match('/\?>\s+\z/s', $data)) {
        $issues[] = [$rel, 'Trailing whitespace after closing ?>'];
        if ($fix) {
            $data = preg_replace('/\?>\s+\z/s', '?>', $data, 1);
            $changed = true;
        }
    }

    if ($fix && $changed && $data !== $original) {
        file_put_contents($path, $data);
    }
}

if (!$issues) {
    echo "OK: No BOM/whitespace issues found.\n";
    exit(0);
}

echo "Found issues (" . count($issues) . "):\n";
foreach ($issues as [$file, $msg]) {
    echo " - {$file}: {$msg}\n";
}

if ($fix) {
    echo "\n--fix applied where possible. Re-run without --fix to confirm.\n";
} else {
    echo "\nTip: run `php tools/scan_bom.php --fix` to auto-fix most issues.\n";
}

exit(1);
