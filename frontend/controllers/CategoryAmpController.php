<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

/** @var \PDO|null $pdo */
$pdo = $pdo ?? ($GLOBALS['pdo'] ?? null);
if (!($pdo instanceof \PDO)) {
    http_response_code(500);
    exit('Database connection not available');
}
$slug = $_GET['slug'] ?? null; if (!$slug){ http_response_code(404); exit; }
$page = max(1,(int)($_GET['page']??1)); $perPage=12; $offset=($page-1)*$perPage;
$st=$pdo->prepare("SELECT * FROM categories WHERE slug=:s AND is_active=1 LIMIT 1");
$st->execute([':s'=>$slug]); $category=$st->fetch(PDO::FETCH_ASSOC) ?: null;
if (!$category){ http_response_code(404); exit; }
$lim=(int)$perPage; $off=(int)$offset;
$sql="SELECT slug,title,excerpt,COALESCE(featured_image,image_path,image) AS featured_image,publish_at FROM news WHERE status='published' AND category_id=:cid ORDER BY publish_at DESC LIMIT :lim OFFSET :off";
// MySQL may not allow native prepared statements for LIMIT/OFFSET.
// Enable emulation for this statement to ensure consistent behavior.
$prevEmulate = (bool)$pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES);
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
$st = $pdo->prepare($sql);
$st->bindValue(':cid', (int)$category['id'], PDO::PARAM_INT);
$st->bindValue(':lim', (int)$lim, PDO::PARAM_INT);
$st->bindValue(':off', (int)$off, PDO::PARAM_INT);
$st->execute();
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, $prevEmulate);
$items=$st->fetchAll(PDO::FETCH_ASSOC);
require __DIR__ . '/../views/category_amp.php';
