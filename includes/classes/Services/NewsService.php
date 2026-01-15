<?php
declare(strict_types=1);

namespace Godyar\Services;

use PDO;
use Throwable;

/**
 * NewsService - schema-tolerant helpers used by controllers.
 *
 * This implementation is designed for shared-hosting environments and mixed/legacy schemas.
 * It includes methods referenced by:
 * - App\Http\Controllers\NewsController
 * - App\Http\Controllers\ArchiveController
 * - App\Http\Controllers\SearchController
 */
final class NewsService
{
    public function __construct(private PDO $pdo) {}

    /** @var array<string,bool> */
    private static array $colCache = [];

    /** @var array<string,bool> */
    private static array $tableCache = [];

    private function schemaExpr(): string
    {
        if (function_exists('gdy_db_schema_expr')) {
            try { return (string)gdy_db_schema_expr($this->pdo); } catch (Throwable) {}
        }
        return 'DATABASE()';
    }

    private function hasColumn(string $table, string $column): bool
    {
        $table = trim($table);
        $column = trim($column);
        if ($table === '' || $column === '') return false;

        $key = $table . ':' . $column;
        if (array_key_exists($key, self::$colCache)) {
            return (bool) self::$colCache[$key];
        }

        $exists = false;
        try {
            $schemaExpr = $this->schemaExpr();
            $stmt = $this->pdo->prepare(
                "SELECT 1
                 FROM information_schema.columns
                 WHERE table_schema = {$schemaExpr}
                   AND table_name = :t
                   AND column_name = :c
                 LIMIT 1"
            );
            $stmt->execute([':t' => $table, ':c' => $column]);
            $exists = (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            $exists = false;
        }

        self::$colCache[$key] = $exists;
        return $exists;
    }

    private function hasTable(string $table): bool
    {
        $table = trim($table);
        if ($table === '') return false;

        if (array_key_exists($table, self::$tableCache)) {
            return (bool) self::$tableCache[$table];
        }

        $exists = false;
        try {
            $schemaExpr = $this->schemaExpr();
            $stmt = $this->pdo->prepare(
                "SELECT 1
                 FROM information_schema.tables
                 WHERE table_schema = {$schemaExpr}
                   AND table_name = :t
                 LIMIT 1"
            );
            $stmt->execute([':t' => $table]);
            $exists = (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            $exists = false;
        }

        self::$tableCache[$table] = $exists;
        return $exists;
    }

    private function slugColumn(): ?string
    {
        if ($this->hasColumn('news', 'slug')) return 'slug';
        foreach (['news_slug', 'slug_title', 'slug_name', 'title_slug', 'permalink', 'url_slug'] as $alt) {
            if ($this->hasColumn('news', $alt)) return $alt;
        }
        return null;
    }

    private function looksUrlEncoded(string $s): bool
    {
        return (bool) preg_match('/%[0-9A-Fa-f]{2}/', $s);
    }

    private function decodeIfEncoded(string $s): string
    {
        $s = trim($s);
        if ($s === '') return '';
        return $this->looksUrlEncoded($s) ? rawurldecode($s) : $s;
    }

    private function publishedWhere(string $alias = 'n'): string
    {
        $clauses = [];

        $prefix = '';
        if ($alias !== '') {
            $prefix = rtrim($alias, '.') . '.';
        }

        if ($this->hasColumn('news', 'status')) {
            $col = "{$prefix}status";
            $clauses[] = "({$col} = 'published' OR {$col} = 'publish' OR {$col} = 'active' OR {$col} = 1 OR {$col} = '1')";
        }

        foreach (['is_published', 'published', 'is_active', 'active'] as $flag) {
            if ($this->hasColumn('news', $flag)) {
                $col = "{$prefix}`{$flag}`";
                $clauses[] = "({$col} = 1 OR {$col} = '1' OR {$col} = 'yes' OR {$col} = 'true')";
            }
        }

        $where = $clauses ? ('(' . implode(' OR ', $clauses) . ')') : '1=1';

        if ($this->hasColumn('news', 'publish_at')) {
            $col = "{$prefix}publish_at";
            $where .= " AND ({$col} IS NULL OR {$col} <= NOW())";
        }
        if ($this->hasColumn('news', 'unpublish_at')) {
            $col = "{$prefix}unpublish_at";
            $where .= " AND ({$col} IS NULL OR {$col} > NOW())";
        }
        if ($this->hasColumn('news', 'deleted_at')) {
            $col = "{$prefix}deleted_at";
            $where .= " AND ({$col} IS NULL)";
        }

        return $where;
    }

    public function slugById(int $id): ?string
    {
        $col = $this->slugColumn();
        if ($col === null) return null;

        try {
            $stmt = $this->pdo->prepare('SELECT `' . $col . '` FROM news WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $slug = trim((string) ($stmt->fetchColumn() ?: ''));
            return $slug !== '' ? $slug : null;
        } catch (Throwable $e) {
            @error_log('[NewsService] slugById: ' . $e->getMessage());
            return null;
        }
    }

    public function idBySlug(string $slug): ?int
    {
        $slug = trim($slug);
        if ($slug === '') return null;

        $slugDec = $this->decodeIfEncoded($slug);
        if (ctype_digit($slugDec)) return (int) $slugDec;

        $slugEnc = rawurlencode($slugDec);

        if ($this->hasTable('news_slug_map')) {
            try {
                $stmt = $this->pdo->prepare("SELECT news_id FROM news_slug_map WHERE slug = :s OR slug = :se LIMIT 1");
                $stmt->execute([':s' => $slugDec, ':se' => $slugEnc]);
                $id = (int) ($stmt->fetchColumn() ?: 0);
                if ($id > 0) return $id;
            } catch (Throwable) {
                // ignore
            }
        }

        $col = $this->slugColumn();
        if ($col === null) return null;

        try {
            $stmt = $this->pdo->prepare('SELECT id FROM news WHERE `' . $col . '` = :s OR `' . $col . '` = :se LIMIT 1');
            $stmt->execute([':s' => $slugDec, ':se' => $slugEnc]);
            $id = (int) ($stmt->fetchColumn() ?: 0);
            return $id > 0 ? $id : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** @return array<string,mixed>|null */
    public function findBySlugOrId(string $param, bool $preview = false): ?array
    {
        $param = trim($param);
        if ($param === '') return null;

        $isNumeric = ctype_digit($param);
        $id = $isNumeric ? (int) $param : 0;
        $slug = $isNumeric ? '' : $param;

        $slugCol = $this->slugColumn();

        $catNameExpr = 'c.name';
        if (!$this->hasColumn('categories', 'name')) {
            if ($this->hasColumn('categories', 'category_name')) $catNameExpr = 'c.category_name';
            elseif ($this->hasColumn('categories', 'cat_name')) $catNameExpr = 'c.cat_name';
            elseif ($this->hasColumn('categories', 'title')) $catNameExpr = 'c.title';
        }

        $catSlugExpr = 'c.slug';
        if (!$this->hasColumn('categories', 'slug')) {
            if ($this->hasColumn('categories', 'category_slug')) $catSlugExpr = 'c.category_slug';
            elseif ($this->hasColumn('categories', 'slug_name')) $catSlugExpr = 'c.slug_name';
            elseif ($this->hasColumn('categories', 'permalink')) $catSlugExpr = 'c.permalink';
            else $catSlugExpr = "''";
        }

        if ($slugCol === null) {
            if (!$isNumeric) return null;
            $where = 'n.id = :id';
        } else {
            $where = $isNumeric
                ? '(n.id = :id OR n.`' . $slugCol . '` = :slug)'
                : '(n.`' . $slugCol . '` = :slug OR n.`' . $slugCol . '` = :slug_enc)';
        }

        if (!$preview) $where .= ' AND ' . $this->publishedWhere('n');

        $sql = "SELECT n.*, {$catNameExpr} AS category_name, {$catSlugExpr} AS category_slug
                FROM news n
                LEFT JOIN categories c ON c.id = n.category_id
                WHERE {$where}
                LIMIT 1";

        try {
            $stmt = $this->pdo->prepare($sql);

            if ($slugCol === null) {
                $params = [':id' => $id];
            } else {
                $params = [':slug' => $isNumeric ? (string) $id : $slug];
                if (!$isNumeric) $params[':slug_enc'] = rawurlencode($slug);
                if ($isNumeric) $params[':id'] = $id;
            }

            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable $e) {
            @error_log('[NewsService] findBySlugOrId: ' . $e->getMessage());
            return null;
        }
    }

    /** @return array<int,array<string,mixed>> */
    public function latest(int $limit = 10): array
    {
        $limit = max(1, min(50, $limit));

        $dateCol = $this->hasColumn('news', 'published_at') ? 'published_at'
            : ($this->hasColumn('news', 'created_at') ? 'created_at'
            : ($this->hasColumn('news', 'date') ? 'date' : 'id'));

        $sql = "SELECT * FROM news WHERE " . $this->publishedWhere('') . " ORDER BY {$dateCol} DESC, id DESC LIMIT :lim";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            @error_log('[NewsService] latest: ' . $e->getMessage());
            return [];
        }
    }

    /** @return array<int,array<string,mixed>> */
    public function mostRead(int $limit = 8, string $period = 'month'): array
    {
        $limit = max(1, min(50, $limit));

        $viewsCol = $this->hasColumn('news', 'views') ? 'views'
            : ($this->hasColumn('news', 'view_count') ? 'view_count' : null);

        if ($viewsCol === null) return $this->latest($limit);

        $dateCol = $this->hasColumn('news', 'published_at') ? 'published_at'
            : ($this->hasColumn('news', 'created_at') ? 'created_at'
            : ($this->hasColumn('news', 'date') ? 'date' : null));

        $where = [];
        $where[] = $this->publishedWhere('');

        if ($dateCol !== null) {
            $days = match ($period) {
                'today' => 1,
                'week'  => 7,
                'month' => 30,
                'year'  => 365,
                default => 0,
            };
            if ($days > 0) {
                $where[] = "{$dateCol} >= (NOW() - INTERVAL {$days} DAY)";
            }
        }

        $sql = "SELECT * FROM news";
        if ($where) $sql .= " WHERE " . implode(' AND ', $where);
        $sql .= " ORDER BY {$viewsCol} DESC";
        if ($dateCol !== null) $sql .= ", {$dateCol} DESC";
        $sql .= " LIMIT :lim";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            @error_log('[NewsService] mostRead: ' . $e->getMessage());
            return $this->latest($limit);
        }
    }

    public function incrementViews(int $newsId): void
    {
        if ($newsId <= 0) return;

        $viewsCol = $this->hasColumn('news', 'views') ? 'views'
            : ($this->hasColumn('news', 'view_count') ? 'view_count' : null);

        if ($viewsCol === null) return;

        try {
            $sql = "UPDATE news SET {$viewsCol} = COALESCE({$viewsCol}, 0) + 1 WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':id' => $newsId]);
        } catch (Throwable $e) {
            @error_log('[NewsService] incrementViews: ' . $e->getMessage());
        }
    }

    /**
     * Archive listing (used by /archive).
     *
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, total_pages: int}
     */
    public function archive(int $page = 1, int $perPage = 12, ?int $year = null, ?int $month = null): array
    {
        $page = max(1, (int) $page);
        $perPage = max(1, min(100, (int) $perPage));
        $offset = ($page - 1) * $perPage;

        $dateCol = $this->hasColumn('news', 'published_at') ? 'published_at'
            : ($this->hasColumn('news', 'created_at') ? 'created_at'
            : ($this->hasColumn('news', 'date') ? 'date' : null));

        $where = [];
        $where[] = $this->publishedWhere('n');

        $bind = [];
        if ($dateCol !== null) {
            if ($year !== null) {
                $where[] = "YEAR(n.`{$dateCol}`) = :y";
                $bind[':y'] = (int) $year;
            }
            if ($month !== null) {
                $where[] = "MONTH(n.`{$dateCol}`) = :m";
                $bind[':m'] = (int) $month;
            }
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $total = 0;
        try {
            $st = $this->pdo->prepare("SELECT COUNT(*) FROM news n {$whereSql}");
            foreach ($bind as $k => $v) {
                $st->bindValue($k, $v, PDO::PARAM_INT);
            }
            $st->execute();
            $total = (int) ($st->fetchColumn() ?: 0);
        } catch (Throwable $e) {
            @error_log('[NewsService] archive count: ' . $e->getMessage());
        }

        $orderCol = $dateCol !== null ? "n.`{$dateCol}`" : "n.id";
        $dataSql = "SELECT n.* FROM news n {$whereSql} ORDER BY {$orderCol} DESC, n.id DESC LIMIT :lim OFFSET :off";

        $items = [];
        try {
            $st = $this->pdo->prepare($dataSql);
            foreach ($bind as $k => $v) {
                $st->bindValue($k, $v, PDO::PARAM_INT);
            }
            $st->bindValue(':lim', $perPage, PDO::PARAM_INT);
            $st->bindValue(':off', $offset, PDO::PARAM_INT);
            $st->execute();
            $items = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            @error_log('[NewsService] archive list: ' . $e->getMessage());
        }

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int) ceil($total / max(1, $perPage)),
        ];
    }

    /**
 * Unified search across news/pages/authors (used by /search).
 *
 * @param string $q
 * @param int $page
 * @param int $perPage
 * @param array{type?:string,category_id?:int,date_from?:string,date_to?:string,match?:string} $filters
 * @return array{items: array<int,array<string,mixed>>, total:int, page:int, per_page:int, total_pages:int, counts: array{news:int,pages:int,authors:int}}
 */
public function search(string $q, int $page = 1, int $perPage = 12, array $filters = []): array
{
    $q = trim($this->decodeIfEncoded($q));
    $page = max(1, (int)$page);
    $perPage = max(1, min(100, (int)$perPage));

    $type = (string)($filters['type'] ?? 'all'); // all|news|page|author
    $categoryId = (int)($filters['category_id'] ?? 0);
    $dateFrom = (string)($filters['date_from'] ?? '');
    $dateTo   = (string)($filters['date_to'] ?? '');
    $match    = (string)($filters['match'] ?? 'all'); // all|any

    // Empty query: return nothing but valid structure.
    if ($q === '') {
        return [
            'items' => [],
            'total' => 0,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => 0,
            'counts' => ['news' => 0, 'pages' => 0, 'authors' => 0],
        ];
    }

    // Tokenize query for "any/all" match. (Unicode-safe split)
    $qNorm = preg_replace('/\s+/u', ' ', $q);
    $terms = array_values(array_filter(explode(' ', (string)$qNorm), static fn($t) => $t !== ''));

    // For very long queries, keep it bounded.
    if (count($terms) > 8) { $terms = array_slice($terms, 0, 8); }

    $counts = ['news' => 0, 'pages' => 0, 'authors' => 0];

    // Helper: build LIKE conditions
    
$makeLike = static function (array $cols, array $terms, string $mode, string $pfx, array &$bind): string {
    $cols = array_values(array_filter($cols, static fn($c) => is_string($c) && $c !== ''));
    if (!$cols || !$terms) return '1=0';

    $termClauses = [];
    $tIndex = 0;
    foreach ($terms as $t) {
        $tIndex++;
        $colClauses = [];
        $cIndex = 0;
        foreach ($cols as $c) {
            $cIndex++;
            // IMPORTANT: PDO MySQL does not allow reusing the same named placeholder multiple times.
            // Use unique placeholder per (term, column) to avoid HY093 Invalid parameter number.
            $p = ':' . $pfx . $tIndex . '_' . $cIndex;
            $bind[$p] = '%' . $t . '%';
            $colClauses[] = "{$c} LIKE {$p}";
        }
        $termClauses[] = '(' . implode(' OR ', $colClauses) . ')';
    }

    $glue = (strtolower($mode) === 'any') ? ' OR ' : ' AND ';
    return '(' . implode($glue, $termClauses) . ')';
};

    $items = [];
    $totalAll = 0;

    try {
        // NEWS
        if ($type === 'all' || $type === 'news') {
            $bind = [];
            $where = [];
            $where[] = $this->publishedWhere('n');

            if ($categoryId > 0 && $this->hasColumn('news', 'category_id')) {
                $where[] = "n.category_id = :cat";
                $bind[':cat'] = $categoryId;
            }

            $dateCol = $this->hasColumn('news', 'published_at') ? 'published_at'
                : ($this->hasColumn('news', 'created_at') ? 'created_at'
                : ($this->hasColumn('news', 'date') ? 'date' : ''));

            if ($dateCol !== '' && $dateFrom !== '') {
                $where[] = "DATE(n.`{$dateCol}`) >= :df";
                $bind[':df'] = $dateFrom;
            }
            if ($dateCol !== '' && $dateTo !== '') {
                $where[] = "DATE(n.`{$dateCol}`) <= :dt";
                $bind[':dt'] = $dateTo;
            }

            $titleCol = $this->hasColumn('news', 'title') ? 'n.title' : ($this->hasColumn('news', 'headline') ? 'n.headline' : 'n.id');
            $bodyCol  = $this->hasColumn('news', 'content') ? 'n.content'
                : ($this->hasColumn('news', 'body') ? 'n.body'
                : ($this->hasColumn('news', 'details') ? 'n.details' : ''));

            $cols = [$titleCol];
            if ($bodyCol !== '') $cols[] = $bodyCol;

            $where[] = $makeLike($cols, $terms, $match, 'nq', $bind);
            $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

            // count
            $st = $this->pdo->prepare("SELECT COUNT(*) FROM news n {$whereSql}");
            foreach ($bind as $k => $v) {
                $st->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $st->execute();
            $counts['news'] = (int)($st->fetchColumn() ?: 0);

            // fetch (overfetch for later merge, then slice by pagination)
            $orderCol = ($dateCol !== '') ? "n.`{$dateCol}`" : "n.id";
            $sql = "SELECT n.* FROM news n {$whereSql} ORDER BY {$orderCol} DESC, n.id DESC LIMIT 200";
            $st = $this->pdo->prepare($sql);
            foreach ($bind as $k => $v) {
                $st->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $st->execute();
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($rows as $r) {
                $id = (int)($r['id'] ?? 0);
                if ($id <= 0) continue;

                $slug = $this->slugById($id);
                $url = $slug ? ('/news/' . rawurlencode($slug)) : ('/news/id/' . $id);

                $img = '';
                foreach (['image','cover','thumb','thumbnail','featured_image','og_image'] as $k) {
                    if (!empty($r[$k])) { $img = (string)$r[$k]; break; }
                }

                $excerpt = '';
                if (!empty($r['excerpt'])) {
                    $excerpt = (string)$r['excerpt'];
                } else {
                    $src = '';
                    foreach (['summary','content','body','details','description'] as $k) {
                        if (!empty($r[$k])) { $src = (string)$r[$k]; break; }
                    }
                    $src = trim(strip_tags($src));
                    if ($src !== '') {
                        $excerpt = mb_substr($src, 0, 160, 'UTF-8');
                    }
                }

                $created = '';
                if ($dateCol !== '' && !empty($r[$dateCol])) $created = (string)$r[$dateCol];
                elseif (!empty($r['created_at'])) $created = (string)$r['created_at'];

                $items[] = [
                    'kind' => 'news',
                    'title' => (string)($r['title'] ?? ($r['headline'] ?? '')),
                    'url' => $url,
                    'image' => $img,
                    'excerpt' => $excerpt,
                    'created_at' => $created,
                ];
            }
        }

        // PAGES
        if ($type === 'all' || $type === 'page') {
            if ($this->hasTable('pages')) {
                $bind = [];
                $where = [];

                // published only if column exists
                if ($this->hasColumn('pages', 'status')) {
                    $where[] = "p.status = 'published'";
                }

                if ($dateFrom !== '' && $this->hasColumn('pages','created_at')) {
                    $where[] = "DATE(p.created_at) >= :pdf";
                    $bind[':pdf'] = $dateFrom;
                }
                if ($dateTo !== '' && $this->hasColumn('pages','created_at')) {
                    $where[] = "DATE(p.created_at) <= :pdt";
                    $bind[':pdt'] = $dateTo;
                }

                $cols = ['p.title'];
                if ($this->hasColumn('pages','content')) $cols[] = 'p.content';
                $where[] = $makeLike($cols, $terms, $match, 'pq', $bind);

                $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

                $st = $this->pdo->prepare("SELECT COUNT(*) FROM pages p {$whereSql}");
                foreach ($bind as $k => $v) $st->bindValue($k, $v, PDO::PARAM_STR);
                $st->execute();
                $counts['pages'] = (int)($st->fetchColumn() ?: 0);

                $sql = "SELECT id, title, slug, content, created_at FROM pages p {$whereSql} ORDER BY p.id DESC LIMIT 200";
                $st = $this->pdo->prepare($sql);
                foreach ($bind as $k => $v) $st->bindValue($k, $v, PDO::PARAM_STR);
                $st->execute();
                $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

                foreach ($rows as $r) {
                    $id = (int)($r['id'] ?? 0);
                    if ($id <= 0) continue;
                    $slug = (string)($r['slug'] ?? '');
                    $url = $slug !== '' ? ('/page/' . rawurlencode($slug)) : ('/page/' . $id);

                    $src = trim(strip_tags((string)($r['content'] ?? '')));
                    $excerpt = $src !== '' ? mb_substr($src, 0, 160, 'UTF-8') : '';

                    $items[] = [
                        'kind' => 'page',
                        'title' => (string)($r['title'] ?? ''),
                        'url' => $url,
                        'image' => '',
                        'excerpt' => $excerpt,
                        'created_at' => (string)($r['created_at'] ?? ''),
                    ];
                }
            }
        }

        // AUTHORS (opinion)
        if ($type === 'all' || $type === 'author') {
            if ($this->hasTable('authors')) {
                $bind = [];
                $where = [];

                $cols = ['a.name'];
                if ($this->hasColumn('authors','bio')) $cols[] = 'a.bio';
                $where[] = $makeLike($cols, $terms, $match, 'aq', $bind);

                $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

                $st = $this->pdo->prepare("SELECT COUNT(*) FROM authors a {$whereSql}");
                foreach ($bind as $k => $v) $st->bindValue($k, $v, PDO::PARAM_STR);
                $st->execute();
                $counts['authors'] = (int)($st->fetchColumn() ?: 0);

                $sql = "SELECT id, name, bio, avatar FROM authors a {$whereSql} ORDER BY a.id DESC LIMIT 200";
                $st = $this->pdo->prepare($sql);
                foreach ($bind as $k => $v) $st->bindValue($k, $v, PDO::PARAM_STR);
                $st->execute();
                $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

                foreach ($rows as $r) {
                    $id = (int)($r['id'] ?? 0);
                    if ($id <= 0) continue;

                    // Some installs have slug column; if not, fallback to id
                    $slug = '';
                    if (!empty($r['slug'])) $slug = (string)$r['slug'];

                    $base = '/opinion_author.php';
                    $url = $slug !== '' ? ($base . '?slug=' . rawurlencode($slug)) : ($base . '?id=' . $id);

                    $bio = trim(strip_tags((string)($r['bio'] ?? '')));
                    $excerpt = $bio !== '' ? mb_substr($bio, 0, 160, 'UTF-8') : '';

                    $items[] = [
                        'kind' => 'author',
                        'title' => (string)($r['name'] ?? ''),
                        'url' => $url,
                        'image' => (string)($r['avatar'] ?? ''),
                        'excerpt' => $excerpt,
                        'created_at' => '',
                    ];
                }
            }
        }

    } catch (Throwable $e) {
        @error_log('[NewsService] search: ' . $e->getMessage());
    }

    // Sort by created_at/id heuristic (best effort)
    usort($items, static function (array $a, array $b): int {
        $da = (string)($a['created_at'] ?? '');
        $db = (string)($b['created_at'] ?? '');
        $ta = $da !== '' ? strtotime($da) : 0;
        $tb = $db !== '' ? strtotime($db) : 0;
        return $tb <=> $ta;
    });

    $totalAll = (int)($counts['news'] + $counts['pages'] + $counts['authors']);

    // Pagination over merged list
    $offset = ($page - 1) * $perPage;
    $pagedItems = array_slice($items, $offset, $perPage);

    return [
        'items' => $pagedItems,
        'total' => $totalAll,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => (int)ceil($totalAll / max(1, $perPage)),
        'counts' => $counts,
    ];
}


public function __version(): string
    {
        return 'NewsService r7 (archive+mostRead) 2026-01-14';
    }
}
