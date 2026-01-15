<?php
declare(strict_types=1);

/**
 * saved.php (legacy bookmarks page) - compatibility hotfix
 *
 * Fixes fatal:
 * - Call to undefined function gdy_load_settings()
 */
require_once __DIR__ . '/includes/bootstrap.php';

// Ensure settings helpers exist even if bootstrap order changes
if (!function_exists('gdy_load_settings')) {
    require_once __DIR__ . '/includes/site_settings.php';
}

gdy_load_settings(false);

$header = __DIR__ . '/frontend/templates/header.php';
$footer = __DIR__ . '/frontend/templates/footer.php';

$siteTitle = 'المحفوظات';
$siteDescription = '';

if (is_file($header)) require $header;

echo '<main class="container my-5">';
echo '<h1 style="margin-bottom:12px;">المحفوظات</h1>';
echo '<p>هذه صفحة توافق. سيتم لاحقاً ربطها بنظام الإشارات المرجعية (Bookmarks).</p>';
echo '</main>';

if (is_file($footer)) require $footer;
