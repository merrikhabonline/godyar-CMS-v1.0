<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

/** @var \PDO|null $pdo */
$pdo = $pdo ?? ($GLOBALS['pdo'] ?? null);
$year=(int)($_GET['year']??0); $week=(int)($_GET['week']??0);
if (!$year||!$week) { http_response_code(404); exit('Not found'); }
$dto = new DateTime();
$dto->setISODate($year, $week);
$start = $dto->format('Y-m-d 00:00:00');
$dto->modify('+6 days');
$end = $dto->format('Y-m-d 23:59:59');

$page=max(1,(int)($_GET['page']??1)); $perPage=12; $offset=($page-1)*$perPage;

$items=[]; $total=0;
if (!($pdo instanceof \PDO)) { http_response_code(500); exit('Database connection not available'); }

try {
  $cnt=$pdo->prepare("SELECT COUNT(*) FROM news WHERE status='published' AND publish_at BETWEEN :s AND :e");
  $cnt->execute([':s'=>$start,':e'=>$end]); $total=(int)$cnt->fetchColumn();
  $lim=(int)$perPage; $off=(int)$offset;
  $sql="SELECT id,slug,COALESCE(featured_image,image_path,image) AS featured_image,title,excerpt,publish_at FROM news WHERE status='published' AND publish_at BETWEEN :s AND :e ORDER BY publish_at DESC LIMIT :lim OFFSET :off";
  // MySQL may not allow native prepared statements for LIMIT/OFFSET.
  // Enable emulation for this statement to ensure consistent behavior.
  $prevEmulate = $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES);
  $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
  $st=$pdo->prepare($sql);
  // restore after execute

  $st->bindValue(':s', $start, PDO::PARAM_STR);
  $st->bindValue(':e', $end, PDO::PARAM_STR);
  $st->bindValue(':lim', (int)$lim, PDO::PARAM_INT);
  $st->bindValue(':off', (int)$off, PDO::PARAM_INT);
  $st->execute();
  $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, $prevEmulate);
  $items=$st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e){ error_log('WEEK_ARCHIVE_ERROR: '.$e->getMessage()); }

$pages=max(1,(int)ceil($total/$perPage));
$seo_title="أرشيف الأسبوع {$year}-W{$week}"; $seo_description="جميع الأخبار خلال هذا الأسبوع"; $canonical="/archive/week/%d-W%d";
require __DIR__ . '/../views/archive_week.php';
