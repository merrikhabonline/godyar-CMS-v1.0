<?php
// UTF-8 no BOM
require_once __DIR__ . '/../../includes/bootstrap.php';
if (session_status() === PHP_SESSION_NONE) { @session_start(); }

// Load settings (class or PDO fallback)
$settings = [];
try {
  if (class_exists('Settings')) { $settings = Settings::getAll(); }
  elseif (isset($pdo)) {
	    $st = $pdo->query("SELECT setting_key,`value` FROM settings");
	    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
	      $k = (string)($row['setting_key'] ?? '');
	      if ($k !== '') {
	        $settings[$k] = (string)($row['value'] ?? '');
	      }
	    }
  }
} catch (Throwable $e) { error_log(get_class($e).": ".$e->getMessage()); }

$site_name    = $settings['site_name'] ?? ($settings['site_title'] ?? 'Godyar');
$main_menu    = json_decode($settings['menu_main']   ?? '[]', true) ?: [];
$footer_links = json_decode($settings['menu_footer'] ?? '[]', true) ?: [];
$social_links = json_decode($settings['social_links']?? '[]', true) ?: [];
$footer_about = $settings['footer_about'] ?? '';

function view_include($path){
  $allowedFiles = ['home.php', 'search.php', 'results.php'];
  $file = basename($path);
  if (in_array($file, $allowedFiles)) {
    $filePath = realpath($file);
    if ($filePath && file_exists($filePath)) {
      require $filePath;
    } else {
      echo "<p style='padding:16px;border:1px dashed #ddd;border-radius:8px'>View not found: ".htmlspecialchars($file, ENT_QUOTES, 'UTF-8')."</p>";
    }
  } else {
    echo 'Access denied.';
  }
}
view_include(__DIR__ . '/../views/search.php');
