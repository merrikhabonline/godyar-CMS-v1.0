<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

/** @var \PDO|null $pdo */
$pdo = $pdo ?? ($GLOBALS['pdo'] ?? null);
header('Content-Type: application/json; charset=UTF-8');
$slug = $_GET['slug'] ?? null;
if (!$slug) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'missing slug']); exit; }
if (!($pdo instanceof \PDO)) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db_unavailable']); exit; }
try {
  $st=$pdo->prepare("SELECT id,name,slug FROM tags WHERE slug=:s LIMIT 1");
  $st->execute([':s'=>$slug]); $tag=$st->fetch(PDO::FETCH_ASSOC);
  if (!$tag) { http_response_code(404); echo json_encode(['ok'=>false]); exit; }
  $lim=min(50,max(1,(int)($_GET['limit']??12)));
  $sql="SELECT n.slug,n.title,n.excerpt,COALESCE(n.featured_image,n.image_path,n.image) AS featured_image,n.publish_at
       FROM news n INNER JOIN news_tags nt ON nt.news_id=n.id
       WHERE nt.tag_id=:tid AND n.status='published'
       ORDER BY n.publish_at DESC
       LIMIT :lim";
  // Ensure placeholders are bound (query() does not support binding).
  // MySQL may not allow native prepared statements for LIMIT.
  $prevEmulate = $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES);
  $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
  $st2 = $pdo->prepare($sql);
  $st2->bindValue(':tid', (int)$tag['id'], PDO::PARAM_INT);
  $st2->bindValue(':lim', (int)$lim, PDO::PARAM_INT);
  $st2->execute();
  $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, $prevEmulate);
  $items=$st2->fetchAll(PDO::FETCH_ASSOC) ?: [];
  echo json_encode(['ok'=>true,'tag'=>$tag,'items'=> $items], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
} catch (Throwable $e){ error_log('API_TAG: '.$e->getMessage()); http_response_code(500); echo json_encode(['ok'=>false,'error'=>'internal']); }
