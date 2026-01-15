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

    public function __version(): string
    {
        return 'NewsService r7 (archive+mostRead) 2026-01-14';
    }
}
