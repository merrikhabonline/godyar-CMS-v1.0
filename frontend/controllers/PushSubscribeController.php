<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
header('Content-Type: application/json; charset=UTF-8');
$data = json_decode(file_get_contents('php://input'), true);
$endpoint = $data['endpoint'] ?? '';
$p256dh   = $data['keys']['p256dh'] ?? '';
$auth     = $data['keys']['auth'] ?? '';
if (!$endpoint || !$p256dh || !$auth) { http_response_code(422); echo json_encode(['ok'=>false]); exit; }
try {
    $now = date('Y-m-d H:i:s');
  gdy_db_upsert(
      $pdo,
      'push_subscribers',
      [
          'endpoint'   => $endpoint,
          'p256dh'     => $p256dh,
          'auth'       => $auth,
          'updated_at' => $now,
      ],
      ['endpoint'],
      ['p256dh','auth','updated_at']
  );
echo json_encode(['ok'=>true]);
} catch (Throwable $e){ error_log('PUSH_SUBSCRIBE: '.$e->getMessage()); http_response_code(500); echo json_encode(['ok'=>false]); }
