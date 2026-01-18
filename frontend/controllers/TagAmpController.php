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
$st=$pdo->prepare("SELECT * FROM tags WHERE slug=:s LIMIT 1");
$st->execute([':s'=>$slug]); $tag=$st->fetch(PDO::FETCH_ASSOC) ?: null;
if (!$tag){ http_response_code(404); exit; }
$lim=(int)$perPage; $off=(int)$offset;
$sql="SELECT n.slug,n.title,n.excerpt,COALESCE(n.featured_image,n.image_path,n.image) AS featured_image,n.publish_at FROM news n INNER JOIN news_tags nt ON nt.news_id=n.id WHERE nt.tag_id=:tid AND n.status='published' ORDER BY n.publish_at DESC LIMIT :lim OFFSET :off";

// MySQL may not allow native prepared statements for LIMIT/OFFSET.
$prevEmulate = $pdo->getAttribute(\PDO::ATTR_EMULATE_PREPARES);
$pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, true);
$st = $pdo->prepare($sql);
$st->bindValue(':tid', (int)$tag['id'], \PDO::PARAM_INT);
$st->bindValue(':lim', (int)$lim, \PDO::PARAM_INT);
$st->bindValue(':off', (int)$off, \PDO::PARAM_INT);
$st->execute();
$pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, $prevEmulate);
$items=$st->fetchAll(PDO::FETCH_ASSOC);
require __DIR__ . '/../views/tag_amp.php';
