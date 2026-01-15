<?php
// admin/media/create_folders.php
// Compatibility bridge (created manually) to avoid 404

$base = __DIR__;

$candidates = [
    $base . '/create_folder.php',
    $base . '/folders_create.php',
    $base . '/create.php',           // أحيانًا صفحة إنشاء عامة
    $base . '/index.php',
];

foreach ($candidates as $f) {
    if (is_file($f)) {
        require_once $f;
        exit;
    }
}

http_response_code(404);
echo "create_folders.php is missing, and no fallback file was found in admin/media/.";
