<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

/** @var \PDO|null $pdo */
$pdo = $pdo ?? ($GLOBALS['pdo'] ?? null);
if (!($pdo instanceof \PDO)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_unavailable']);
    exit;
}

$slug = (string)($_GET['slug'] ?? '');
$slug = trim($slug);
if ($slug === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'missing_slug']);
    exit;
}

try {
    $st = $pdo->prepare("SELECT id, name, slug FROM categories WHERE slug = :s AND is_active = 1 LIMIT 1");
    $st->execute([':s' => $slug]);
    $cat = $st->fetch(\PDO::FETCH_ASSOC);

    if (!$cat) {
        http_response_code(404);
        echo json_encode(['ok' => false]);
        exit;
    }

    $lim = min(50, max(1, (int)($_GET['limit'] ?? 12)));

    // Use a prepared statement to avoid injection risks; enable emulate prepares for LIMIT.
    $prevEmulate = (bool)$pdo->getAttribute(\PDO::ATTR_EMULATE_PREPARES);
    $pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, true);

    $sql = "SELECT slug, title, excerpt, COALESCE(featured_image, image_path, image) AS featured_image, publish_at
            FROM news
            WHERE status = 'published' AND category_id = :cid
            ORDER BY publish_at DESC
            LIMIT :lim";

    $st2 = $pdo->prepare($sql);
    $st2->bindValue(':cid', (int)($cat['id'] ?? 0), \PDO::PARAM_INT);
    $st2->bindValue(':lim', $lim, \PDO::PARAM_INT);
    $st2->execute();

    $pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, $prevEmulate);

    $items = $st2->fetchAll(\PDO::FETCH_ASSOC);

    echo json_encode(
        ['ok' => true, 'category' => $cat, 'items' => $items],
        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );
} catch (Throwable $e) {
    error_log('API_CAT: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'internal']);
}
