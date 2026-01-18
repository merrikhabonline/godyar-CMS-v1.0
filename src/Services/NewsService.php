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
            error_log('[NewsService] slugById: ' . $e->getMessage());
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
            error_log('[NewsService] findBySlugOrId: ' . $e->getMessage());
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
            error_log('[NewsService] latest: ' . $e->getMessage());
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
            error_log('[NewsService] mostRead: ' . $e->getMessage());
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
            error_log('[NewsService] incrementViews: ' . $e->getMessage());
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
            error_log('[NewsService] archive count: ' . $e->getMessage());
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
            error_log('[NewsService] archive list: ' . $e->getMessage());
        }

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int) ceil($total / max(1, $perPage)),
        ];
    }

    public function __version(): string
    {
        return 'NewsService r7 (archive+mostRead) 2026-01-14';
    }

/**
 * Get related published news by category.
 *
 * Flexible call patterns supported:
 * - relatedByCategory($categoryId, $excludeId = 0, $limit = 6, $lang = null)
 * - relatedByCategory($newsRowArray, $limit = 6)
 *
 * @return array<int, array<string,mixed>>
 */
public function relatedByCategory(...$args): array
{
    try {
        $categoryId = 0;
        $excludeId  = 0;
        $limit      = 6;
        $lang       = null;

        if (isset($args[0]) && is_array($args[0])) {
            $row = $args[0];
            $categoryId = (int)($row['category_id'] ?? $row['cat_id'] ?? $row['category'] ?? 0);
            $excludeId  = (int)($row['id'] ?? $row['news_id'] ?? 0);
            if (isset($args[1]) && is_numeric($args[1])) $limit = (int)$args[1];
            if (isset($row['lang']) && is_string($row['lang'])) $lang = $row['lang'];
        } else {
            if (isset($args[0]) && is_numeric($args[0])) $categoryId = (int)$args[0];
            if (isset($args[1]) && is_numeric($args[1])) $excludeId  = (int)$args[1];
            if (isset($args[2]) && is_numeric($args[2])) $limit      = (int)$args[2];
            if (isset($args[3]) && (is_string($args[3]) || $args[3] === null)) $lang = $args[3];
        }

        if ($limit <= 0) $limit = 6;
        if ($limit > 24) $limit = 24;

        $table = $this->resolveNewsTable();
        if ($table === '') return [];

        $catCol = $this->resolveCategoryColumn($table);
        if ($catCol === '') return [];
        if ($categoryId <= 0) return [];

        // Build SELECT columns that exist in current schema
        $selectCols = ['id'];
        foreach (['title','slug','image','thumbnail','excerpt','summary','category_id','cat_id','category','created_at','published_at','updated_at','views','lang'] as $c) {
            if ($this->hasColumn($table, $c)) $selectCols[] = $c;
        }
        $selectCols = array_values(array_unique($selectCols));
        $select = implode(', ', array_map(static fn($c) => "n.`{$c}`", $selectCols));

        $where = [];
        $params = [':cid' => $categoryId];

        // Published filters (schema-tolerant)
        $where[] = $this->publishedWhereForTable($table, 'n');

        // category match
        $where[] = "n.`{$catCol}` = :cid";

        // exclude current item
        if ($excludeId > 0) {
            $where[] = "n.`id` <> :ex";
            $params[':ex'] = $excludeId;
        }

        // language if supported
        if ($lang !== null && $lang !== '' && $this->hasColumn($table, 'lang')) {
            $where[] = "n.`lang` = :lang";
            $params[':lang'] = (string)$lang;
        }

        $orderCol = 'id';
        foreach (['published_at','created_at','updated_at'] as $c) {
            if ($this->hasColumn($table, $c)) { $orderCol = $c; break; }
        }

        // NOTE: table name is trusted internal (resolved from fixed allowlist)
        $sql = "SELECT {$select}
                FROM `{$table}` n
                WHERE " . implode(' AND ', array_filter($where)) . "
                ORDER BY n.`{$orderCol}` DESC
                LIMIT " . (int)$limit;

        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(\PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    } catch (\Throwable $e) {
        error_log('[NewsService] relatedByCategory: ' . $e->getMessage());
        return [];
    }
}

/**
 * Resolve the news table name in a schema-tolerant way.
 * @return string Table name without backticks, or empty string if not found.
 */
private function resolveNewsTable(): string
{
    foreach (['news','posts','articles'] as $t) {
        if ($this->hasTable($t)) return $t;
    }
    return '';
}

/**
 * Resolve category column on the given table.
 * @return string Column name, or empty string if none found.
 */
private function resolveCategoryColumn(string $table): string
{
    foreach (['category_id','cat_id','category'] as $c) {
        if ($this->hasColumn($table, $c)) return $c;
    }
    return '';
}

/**
 * Published WHERE clause for a given table, tolerant across schemas.
 */
private function publishedWhereForTable(string $table, string $alias = 'n'): string
{
    // Prefer existing logic for the canonical `news` table when available.
    if ($table === 'news' && method_exists($this, 'publishedWhere')) {
        try {
            /** @phpstan-ignore-next-line */
            return (string)$this->publishedWhere($alias);
        } catch (\Throwable) {
            // fall through
        }
    }

    $alias = preg_replace('/[^a-zA-Z0-9_]/', '', $alias) ?: 'n';

    $clauses = ["1=1"];

    if ($this->hasColumn($table, 'status')) {
        $clauses[] = "{$alias}.`status` IN ('published','publish','active')";
    } elseif ($this->hasColumn($table, 'is_published')) {
        $clauses[] = "{$alias}.`is_published` = 1";
    }

    if ($this->hasColumn($table, 'deleted_at')) {
        $clauses[] = "{$alias}.`deleted_at` IS NULL";
    } elseif ($this->hasColumn($table, 'is_deleted')) {
        $clauses[] = "{$alias}.`is_deleted` = 0";
    }

    return '(' . implode(' AND ', $clauses) . ')';
}

    /**
     * Unified search used by SearchController.
     *
     * Returns:
     *  - items: array of ['kind','title','url','image','excerpt','created_at','category_slug']
     *  - total: total rows for the selected type
     *  - total_pages: total pages for the selected type
     *  - counts: counts by section (news/pages/authors) for the same query/filters
     *
     * @param array<string,mixed> $filters
     * @return array{items: array<int,array<string,mixed>>, total:int, total_pages:int, counts: array<string,int>}
     */
    public function search(string $q, int $page = 1, int $perPage = 12, array $filters = []): array
    {
        $q = trim((string)$q);
        $page = max(1, (int)$page);
        $perPage = max(1, min(50, (int)$perPage));

        $type = (string)($filters['type'] ?? 'all'); // all|news|opinion|page|author
        $categoryId = (int)($filters['category_id'] ?? 0);
        $dateFrom = trim((string)($filters['date_from'] ?? ''));
        $dateTo   = trim((string)($filters['date_to'] ?? ''));
        $match    = (string)($filters['match'] ?? 'all'); // all|any

        // Normalize query terms
        $q = preg_replace('/[\x00-\x1F\x7F]/u', '', $q);
        if (function_exists('mb_substr')) {
            $q = (string)mb_substr($q, 0, 200, 'UTF-8');
        } else {
            $q = (string)substr($q, 0, 200);
        }

        $terms = preg_split('/\s+/u', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        // Limit terms to avoid huge WHERE clauses
        if (count($terms) > 6) $terms = array_slice($terms, 0, 6);

        $counts = ['news' => 0, 'pages' => 0, 'authors' => 0];
        $items = [];
        $total = 0;
        $totalPages = 0;

        // If empty query, return early (Search view handles it)
        if ($q === '') {
            return ['items' => [], 'total' => 0, 'total_pages' => 0, 'counts' => $counts];
        }

        // Build SELECT blocks
        $selects = [];
        $params = [];

        // These arrays are also reused to compute section counts.
        $newsCols = [];
        $pagesCols = [];
        $authCols = [];
        $newsDateCol = '';

        $wantNews   = in_array($type, ['all', 'news', 'opinion'], true);
        $wantPages  = in_array($type, ['all', 'page'], true);
        $wantAuthor = in_array($type, ['all', 'author'], true);

        // ---------- NEWS / OPINION ----------
        if ($wantNews && $this->hasTable('news')) {
            $newsCols = [];
            foreach (['title','excerpt','content','slug'] as $c) {
                if ($this->hasColumn('news', $c)) $newsCols[] = "n.`{$c}`";
            }
            if (!$newsCols) {
                $newsCols[] = "n.`id`";
            }

            $newsWhere = [];
            $newsWhere[] = $this->publishedWhere('n');

            if ($categoryId > 0 && $this->hasColumn('news', 'category_id')) {
                $newsWhere[] = 'n.`category_id` = :cat_id';
                $params[':cat_id'] = $categoryId;
            }

            if ($type === 'opinion') {
                if ($this->hasColumn('news', 'opinion_author_id')) {
                    $newsWhere[] = 'n.`opinion_author_id` IS NOT NULL';
                } else {
                    $newsWhere[] = '1=0';
                }
            } elseif ($type === 'news') {
                if ($this->hasColumn('news', 'opinion_author_id')) {
                    $newsWhere[] = 'n.`opinion_author_id` IS NULL';
                }
            }

            // date filters
            $newsDateCol = '';
            foreach (['published_at','publish_at','created_at','updated_at'] as $dc) {
                if ($this->hasColumn('news', $dc)) { $newsDateCol = "n.`{$dc}`"; break; }
            }
            if ($newsDateCol !== '' && $dateFrom !== '') {
                $newsWhere[] = "DATE({$newsDateCol}) >= :date_from";
                $params[':date_from'] = $dateFrom;
                if ($dateTo !== '') {
                    $newsWhere[] = "DATE({$newsDateCol}) <= :date_to";
                    $params[':date_to'] = $dateTo;
                }
            }

            // term where
            $termParts = [];
            foreach ($terms as $i => $t) {
                $k = ':nq' . $i;
                $params[$k] = '%' . $t . '%';
                $or = [];
                foreach ($newsCols as $col) {
                    $or[] = "{$col} LIKE {$k}";
                }
                $termParts[] = '(' . implode(' OR ', $or) . ')';
            }
            if ($termParts) {
                $newsWhere[] = '(' . implode($match === 'any' ? ' OR ' : ' AND ', $termParts) . ')';
            }

            // Image expression
            $imgCols = [];
            foreach (['featured_image','image_path','image'] as $ic) {
                if ($this->hasColumn('news', $ic)) $imgCols[] = "n.`{$ic}`";
            }
            $imgExpr = $imgCols ? ('COALESCE(' . implode(',', $imgCols) . ", '')") : "''";

            // Excerpt expression
            $excerptExpr = $this->hasColumn('news', 'excerpt') ? "n.`excerpt`" : ($this->hasColumn('news', 'content') ? "n.`content`" : "''");

            // Date output
            $dateExpr = $newsDateCol !== '' ? $newsDateCol : "n.`id`";

            $catSlugExpr = $this->hasColumn('categories', 'slug') ? 'c.slug' : "''";
            $joinCat = ($this->hasTable('categories') && $this->hasColumn('news', 'category_id'))
                ? 'LEFT JOIN categories c ON c.id = n.category_id'
                : "LEFT JOIN (SELECT NULL AS slug, NULL AS id) c ON 1=0";

            $selects[] = "SELECT 'news' AS kind,
                                 n.`title` AS title,
                                 CONCAT('/news/id/', n.`id`) AS url,
                                 {$imgExpr} AS image,
                                 {$excerptExpr} AS excerpt,
                                 {$dateExpr} AS created_at,
                                 {$catSlugExpr} AS category_slug
                          FROM news n
                          {$joinCat}
                          WHERE " . implode(' AND ', $newsWhere);
        }

        // ---------- PAGES ----------
        if ($wantPages && $this->hasTable('pages')) {
            $pagesWhere = [];
            $pagesCols = [];
            foreach (['title','content','slug'] as $c) {
                if ($this->hasColumn('pages', $c)) $pagesCols[] = "p.`{$c}`";
            }
            if (!$pagesCols) $pagesCols[] = 'p.`id`';

            $pagesWhere[] = $this->publishedWhereForTable('pages', 'p');

            $termParts = [];
            foreach ($terms as $i => $t) {
                $k = ':pq' . $i;
                $params[$k] = '%' . $t . '%';
                $or = [];
                foreach ($pagesCols as $col) {
                    $or[] = "{$col} LIKE {$k}";
                }
                $termParts[] = '(' . implode(' OR ', $or) . ')';
            }
            if ($termParts) {
                $pagesWhere[] = '(' . implode($match === 'any' ? ' OR ' : ' AND ', $termParts) . ')';
            }

            $slugCol = $this->hasColumn('pages', 'slug') ? 'p.`slug`' : "p.`id`";
            $titleCol = $this->hasColumn('pages', 'title') ? 'p.`title`' : "CONCAT('Page #', p.`id`)";
            $contentCol = $this->hasColumn('pages', 'content') ? 'p.`content`' : "''";
            $pagesDateCol = $this->hasColumn('pages', 'updated_at') ? 'p.`updated_at`' : ($this->hasColumn('pages', 'created_at') ? 'p.`created_at`' : 'NOW()');

            $selects[] = "SELECT 'page' AS kind,
                                 {$titleCol} AS title,
                                 CONCAT('/page/', {$slugCol}) AS url,
                                 '' AS image,
                                 {$contentCol} AS excerpt,
                                 {$pagesDateCol} AS created_at,
                                 '' AS category_slug
                          FROM pages p
                          WHERE " . implode(' AND ', $pagesWhere);
        }

        // ---------- OPINION AUTHORS ----------
        if ($wantAuthor && $this->hasTable('opinion_authors')) {
            $authWhere = [];
            $authCols = [];
            foreach (['name','bio','slug','page_title'] as $c) {
                if ($this->hasColumn('opinion_authors', $c)) $authCols[] = "oa.`{$c}`";
            }
            if (!$authCols) $authCols[] = 'oa.`id`';

            if ($this->hasColumn('opinion_authors', 'is_active')) {
                $authWhere[] = '(oa.`is_active` = 1 OR oa.`is_active` = "1" OR oa.`is_active` = TRUE)';
            }

            $termParts = [];
            foreach ($terms as $i => $t) {
                $k = ':aq' . $i;
                $params[$k] = '%' . $t . '%';
                $or = [];
                foreach ($authCols as $col) {
                    $or[] = "{$col} LIKE {$k}";
                }
                $termParts[] = '(' . implode(' OR ', $or) . ')';
            }
            if ($termParts) {
                $authWhere[] = '(' . implode($match === 'any' ? ' OR ' : ' AND ', $termParts) . ')';
            }

            $nameCol = $this->hasColumn('opinion_authors', 'name') ? 'oa.`name`' : "CONCAT('Author #', oa.`id`)";
            $bioCol  = $this->hasColumn('opinion_authors', 'bio') ? 'oa.`bio`' : "''";
            $avatarCol = $this->hasColumn('opinion_authors', 'avatar') ? 'oa.`avatar`' : "''";
            $dateCol = $this->hasColumn('opinion_authors', 'updated_at') ? 'oa.`updated_at`' : ($this->hasColumn('opinion_authors', 'created_at') ? 'oa.`created_at`' : 'NOW()');

            // No dedicated public route for author profile in this build; send users to the opinion writers section.
            $selects[] = "SELECT 'author' AS kind,
                                 {$nameCol} AS title,
                                 '/category/opinion-writers' AS url,
                                 {$avatarCol} AS image,
                                 {$bioCol} AS excerpt,
                                 {$dateCol} AS created_at,
                                 'opinion-writers' AS category_slug
                          FROM opinion_authors oa
                          " . ($authWhere ? ('WHERE ' . implode(' AND ', $authWhere)) : '');
        }

        if (!$selects) {
            return ['items' => [], 'total' => 0, 'total_pages' => 0, 'counts' => $counts];
        }

        // Counts per section (independent of selected type). We compute counts directly (no recursion).
        try {
            // News count (includes both regular news + opinion articles; category/date filters still apply)
            if ($this->hasTable('news')) {
                // Ensure column/date lists are available even when the current selected type isn't news.
                if (!$newsCols) {
                    foreach (['title','excerpt','content','slug'] as $c) {
                        if ($this->hasColumn('news', $c)) { $newsCols[] = "n.`{$c}`"; }
                    }
                    if (!$newsCols) { $newsCols[] = 'n.`id`'; }
                }
                if ($newsDateCol === '') {
                    foreach (['published_at','publish_at','created_at','updated_at'] as $dc) {
                        if ($this->hasColumn('news', $dc)) { $newsDateCol = "n.`{$dc}`"; break; }
                    }
                }
                $countSql = 'SELECT COUNT(*) FROM news n WHERE ';
                $countWhere = [];
                $countParams = [];
                $countWhere[] = $this->publishedWhere('n');
                if ($categoryId > 0 && $this->hasColumn('news', 'category_id')) {
                    $countWhere[] = 'n.`category_id` = :c_cat_id';
                    $countParams[':c_cat_id'] = $categoryId;
                }
                if ($newsDateCol !== '' && $dateFrom !== '') {
                    $countWhere[] = "DATE({$newsDateCol}) >= :c_date_from";
                    $countParams[':c_date_from'] = $dateFrom;
                    if ($dateTo !== '') {
                        $countWhere[] = "DATE({$newsDateCol}) <= :c_date_to";
                        $countParams[':c_date_to'] = $dateTo;
                    }
                }
                if ($terms) {
                    $tParts = [];
                    foreach ($terms as $i => $t) {
                        $k = ':c_nq' . $i;
                        $countParams[$k] = '%' . $t . '%';
                        $or = [];
                        foreach ($newsCols as $col) {
                            $or[] = "{$col} LIKE {$k}";
                        }
                        $tParts[] = '(' . implode(' OR ', $or) . ')';
                    }
                    $countWhere[] = '(' . implode($match === 'any' ? ' OR ' : ' AND ', $tParts) . ')';
                }
                $stC = $this->pdo->prepare($countSql . implode(' AND ', $countWhere));
                foreach ($countParams as $k => $v) {
                    $stC->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
                }
                $stC->execute();
                $counts['news'] = (int)($stC->fetchColumn() ?: 0);
            }

            // Pages count
            if ($this->hasTable('pages')) {
                $pCols = $pagesCols;
                if (!$pCols) {
                    foreach (['title','content','slug'] as $c) {
                        if ($this->hasColumn('pages', $c)) { $pCols[] = "p.`{$c}`"; }
                    }
                    if (!$pCols) { $pCols[] = 'p.`id`'; }
                }
                $where = [$this->publishedWhereForTable('pages', 'p')];
                $p = [];
                if ($terms) {
                    $tParts = [];
                    foreach ($terms as $i => $t) {
                        $k = ':c_pq' . $i;
                        $p[$k] = '%' . $t . '%';
                        $or = [];
                        foreach ($pCols as $col) {
                            $or[] = "{$col} LIKE {$k}";
                        }
                        $tParts[] = '(' . implode(' OR ', $or) . ')';
                    }
                    $where[] = '(' . implode($match === 'any' ? ' OR ' : ' AND ', $tParts) . ')';
                }
                $stC = $this->pdo->prepare('SELECT COUNT(*) FROM pages p WHERE ' . implode(' AND ', $where));
                foreach ($p as $k => $v) {
                    $stC->bindValue($k, $v, PDO::PARAM_STR);
                }
                $stC->execute();
                $counts['pages'] = (int)($stC->fetchColumn() ?: 0);
            }

            // Authors count
            if ($this->hasTable('opinion_authors')) {
                $aCols = $authCols;
                if (!$aCols) {
                    foreach (['name','bio','slug','page_title'] as $c) {
                        if ($this->hasColumn('opinion_authors', $c)) { $aCols[] = "oa.`{$c}`"; }
                    }
                    if (!$aCols) { $aCols[] = 'oa.`id`'; }
                }
                $where = [];
                $p = [];
                if ($this->hasColumn('opinion_authors', 'is_active')) {
                    $where[] = '(oa.`is_active` = 1 OR oa.`is_active` = "1" OR oa.`is_active` = TRUE)';
                }
                if ($terms) {
                    $tParts = [];
                    foreach ($terms as $i => $t) {
                        $k = ':c_aq' . $i;
                        $p[$k] = '%' . $t . '%';
                        $or = [];
                        foreach ($aCols as $col) {
                            $or[] = "{$col} LIKE {$k}";
                        }
                        $tParts[] = '(' . implode(' OR ', $or) . ')';
                    }
                    $where[] = '(' . implode($match === 'any' ? ' OR ' : ' AND ', $tParts) . ')';
                }
                $sql = 'SELECT COUNT(*) FROM opinion_authors oa' . ($where ? (' WHERE ' . implode(' AND ', $where)) : '');
                $stC = $this->pdo->prepare($sql);
                foreach ($p as $k => $v) {
                    $stC->bindValue($k, $v, PDO::PARAM_STR);
                }
                $stC->execute();
                $counts['authors'] = (int)($stC->fetchColumn() ?: 0);
            }
        } catch (Throwable) {
            // ignore
        }

        // Main query (union)
        $unionSql = implode("\nUNION ALL\n", $selects);
        $offset = ($page - 1) * $perPage;

        try {
            $sqlCount = "SELECT COUNT(*) FROM ( {$unionSql} ) t";
            $st = $this->pdo->prepare($sqlCount);
            foreach ($params as $k => $v) {
                $st->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $st->execute();
            $total = (int)($st->fetchColumn() ?: 0);

            $totalPages = (int)ceil($total / $perPage);

            $sql = "SELECT * FROM ( {$unionSql} ) t ORDER BY t.created_at DESC LIMIT :limit OFFSET :offset";
            $st2 = $this->pdo->prepare($sql);
            foreach ($params as $k => $v) {
                $st2->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $st2->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $st2->bindValue(':offset', $offset, PDO::PARAM_INT);
            $st2->execute();
            $items = $st2->fetchAll(PDO::FETCH_ASSOC) ?: [];

            // If excerpt is HTML-heavy, keep it as-is; view will strip tags.
        } catch (Throwable $e) {
            error_log('[NewsService] search error: ' . $e->getMessage());
            $items = [];
            $total = 0;
            $totalPages = 0;
        }

        return [
            'items' => $items,
            'total' => $total,
            'total_pages' => $totalPages,
            'counts' => $counts,
        ];
    }

}
