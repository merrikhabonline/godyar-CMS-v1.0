<?php
declare(strict_types=1);

namespace Godyar\Services;

use PDO;
use Throwable;

/**
 * CategoryService (schema-tolerant)
 *
 * Used by CategoryController, NewsController, header navigation, and templates.
 * Designed to run across mixed/legacy schemas (shared hosting).
 */
final class CategoryService
{
    public function __construct(private PDO $pdo) {}

    /** @var array<string,bool> */
    private static array $colCache = [];
    /** @var array<string,bool> */
    private static array $tableCache = [];

    private function hasColumn(string $table, string $column): bool
    {
        $key = $table . ':' . $column;
        if (array_key_exists($key, self::$colCache)) {
            return (bool) self::$colCache[$key];
        }

        $exists = false;
        try {
            $schemaExpr = function_exists('gdy_db_schema_expr') ? gdy_db_schema_expr($this->pdo) : 'DATABASE()';
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
            $schemaExpr = function_exists('gdy_db_schema_expr') ? gdy_db_schema_expr($this->pdo) : 'DATABASE()';
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
        if ($this->hasColumn('categories', 'slug')) return 'slug';
        foreach (['category_slug', 'slug_name', 'permalink', 'url_slug'] as $alt) {
            if ($this->hasColumn('categories', $alt)) return $alt;
        }
        return null;
    }

    private function nameColumn(): string
    {
        if ($this->hasColumn('categories', 'name')) return 'name';
        foreach (['category_name', 'cat_name', 'title'] as $alt) {
            if ($this->hasColumn('categories', $alt)) return $alt;
        }
        return 'name';
    }

    private function publishedWhere(string $alias = 'n'): string
    {
        $clauses = [];
        $prefix = $alias !== '' ? (rtrim($alias, '.') . '.') : '';

        if ($this->hasColumn('news', 'status')) {
            $col = "{$prefix}status";
            $clauses[] = "({$col} = 'published' OR {$col} = 'publish' OR {$col} = 'active' OR {$col} = 'approved' OR {$col} = 1 OR {$col} = '1')";
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
        $slugCol = $this->slugColumn();
        if ($slugCol === null || $id <= 0) return null;

        try {
            $st = $this->pdo->prepare('SELECT `' . $slugCol . '` FROM categories WHERE id = ? LIMIT 1');
            $st->execute([$id]);
            $slug = trim((string)($st->fetchColumn() ?: ''));
            return $slug !== '' ? $slug : null;
        } catch (Throwable $e) {
            error_log('[CategoryService] slugById error: ' . $e->getMessage());
            return null;
        }
    }

    public function idBySlug(string $slug): ?int
    {
        $slug = trim($slug);
        if ($slug === '') return null;
        if (ctype_digit($slug)) return (int) $slug;

        if ($this->hasTable('category_slug_map')) {
            try {
                $st = $this->pdo->prepare('SELECT category_id FROM category_slug_map WHERE slug = :s LIMIT 1');
                $st->execute([':s' => $slug]);
                $id = (int)($st->fetchColumn() ?: 0);
                if ($id > 0) return $id;
            } catch (Throwable) {
                // ignore
            }
        }

        $slugCol = $this->slugColumn();
        if ($slugCol === null) return null;

        try {
            $st = $this->pdo->prepare('SELECT id FROM categories WHERE `' . $slugCol . '` = :s LIMIT 1');
            $st->execute([':s' => $slug]);
            $id = (int)($st->fetchColumn() ?: 0);
            return $id > 0 ? $id : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** @return array<string,mixed>|null */
    public function findBySlugOrId(string $param): ?array
    {
        $param = trim($param);
        if ($param === '') return null;

        $isNumeric = ctype_digit($param);
        $id = $isNumeric ? (int)$param : 0;

        $nameCol = $this->nameColumn();
        $slugCol = $this->slugColumn();

        $cols = ["id", "`{$nameCol}` AS name"];
        $cols[] = $slugCol ? "`{$slugCol}` AS slug" : "'' AS slug";
        if ($this->hasColumn('categories', 'is_members_only')) {
            $cols[] = 'is_members_only';
        } else {
            $cols[] = '0 AS is_members_only';
        }

        $where = $isNumeric ? 'id = :id' : ($slugCol ? ('`' . $slugCol . '` = :s') : '1=0');
        $sql = 'SELECT ' . implode(', ', $cols) . ' FROM categories WHERE ' . $where . ' LIMIT 1';

        try {
            $st = $this->pdo->prepare($sql);
            $st->execute($isNumeric ? [':id' => $id] : [':s' => $param]);
            $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;
            return $row;
        } catch (Throwable $e) {
            error_log('[CategoryService] findBySlugOrId error: ' . $e->getMessage());
            return null;
        }
    }

    /** @return array<string,mixed>|null */
    public function findById(int $id): ?array
    {
        if ($id <= 0) return null;
        return $this->findBySlugOrId((string)$id);
    }

    /** @return array<string,mixed>|null */
    public function findBySlug(string $slug): ?array
    {
        $slug = trim($slug);
        if ($slug === '') return null;
        return $this->findBySlugOrId($slug);
    }

    /** @return array<int,array<string,mixed>> */
    public function all(int $limit = 500): array
    {
        $limit = max(1, min(2000, $limit));
        $nameCol = $this->nameColumn();
        $slugCol = $this->slugColumn();

        $cols = ["id", "`{$nameCol}` AS name"];
        $cols[] = $slugCol ? "`{$slugCol}` AS slug" : "'' AS slug";

        $sql = 'SELECT ' . implode(', ', $cols) . ' FROM categories ORDER BY `' . $nameCol . '` ASC LIMIT :lim';

        try {
            $st = $this->pdo->prepare($sql);
            $st->bindValue(':lim', $limit, PDO::PARAM_INT);
            $st->execute();
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('[CategoryService] all error: ' . $e->getMessage());
            return [];
        }
    }

    /** @return array<int,array<string,mixed>> */
    public function headerCategories(int $limit = 8): array
    {
        $limit = max(1, min(50, $limit));

        $nameCol = $this->nameColumn();
        $slugCol = $this->slugColumn();

        $cols = ["id", "`{$nameCol}` AS name"];
        $cols[] = $slugCol ? "`{$slugCol}` AS slug" : "'' AS slug";

        if ($this->hasColumn('categories', 'parent_id')) $cols[] = 'parent_id';
        if ($this->hasColumn('categories', 'sort_order')) $cols[] = 'sort_order';

        $where = [];
        if ($this->hasColumn('categories', 'is_active')) $where[] = 'is_active = 1';

        $order = [];
        if ($this->hasColumn('categories', 'sort_order')) $order[] = 'sort_order ASC';
        $order[] = "`{$nameCol}` ASC";
        $order[] = 'id ASC';

        $sql = 'SELECT ' . implode(', ', $cols) . ' FROM categories'
            . ($where ? (' WHERE ' . implode(' AND ', $where)) : '')
            . ' ORDER BY ' . implode(', ', $order)
            . ' LIMIT :lim';

        try {
            $st = $this->pdo->prepare($sql);
            $st->bindValue(':lim', $limit, PDO::PARAM_INT);
            $st->execute();
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('[CategoryService] headerCategories error: ' . $e->getMessage());
            return [];
        }
    }

    /** @return array<int,array<string,mixed>> */
    public function subcategories(int $parentId, int $limit = 200): array
    {
        $limit = max(1, min(500, $limit));
        if ($parentId <= 0) return [];
        if (!$this->hasColumn('categories', 'parent_id')) return [];

        $nameCol = $this->nameColumn();
        $slugCol = $this->slugColumn();

        $cols = ["id", "`{$nameCol}` AS name", 'parent_id'];
        $cols[] = $slugCol ? "`{$slugCol}` AS slug" : "'' AS slug";

        $sql = 'SELECT ' . implode(', ', $cols) . ' FROM categories WHERE parent_id = :pid ORDER BY `' . $nameCol . '` ASC LIMIT :lim';

        try {
            $st = $this->pdo->prepare($sql);
            $st->bindValue(':pid', $parentId, PDO::PARAM_INT);
            $st->bindValue(':lim', $limit, PDO::PARAM_INT);
            $st->execute();
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Return sibling categories for a given category.
     *
     * CategoryController calls this method as:
     *   siblingCategories($parentIdOrNull, $currentCategoryId, $limit)
     *
     * - If $parentIdOrNull is null, we treat the category as a top-level category and return
     *   other top-level categories (parent_id IS NULL/0) excluding the current category.
     * - If $parentIdOrNull is an int > 0, we return subcategories under that parent excluding
     *   the current category.
     *
     * @return array<int,array<string,mixed>>
     */
    public function siblingCategories(?int $parentIdOrNull, int $excludeCategoryId, int $limit = 20): array
    {
        $limit = max(1, min(200, (int)$limit));
        $excludeCategoryId = (int)$excludeCategoryId;
        if (!$this->hasTable('categories')) return [];
        if (!$this->hasColumn('categories', 'parent_id')) return [];

        $nameCol = $this->nameColumn();
        $slugCol = $this->slugColumn();
        $cols = ["id", "`{$nameCol}` AS name", "parent_id"];
        $cols[] = $slugCol ? "`{$slugCol}` AS slug" : "'' AS slug";

        $where = '';
        $params = [':exclude' => $excludeCategoryId, ':lim' => $limit];

        if ($parentIdOrNull === null) {
            // Top-level siblings
            $where = '(parent_id IS NULL OR parent_id = 0)';
        } else {
            $pid = (int)$parentIdOrNull;
            if ($pid <= 0) {
                $where = '(parent_id IS NULL OR parent_id = 0)';
            } else {
                $where = 'parent_id = :pid';
                $params[':pid'] = $pid;
            }
        }

        // Exclude the current category if provided
        if ($excludeCategoryId > 0) {
            $where = '(' . $where . ') AND id <> :exclude';
        }

        $sql = 'SELECT ' . implode(', ', $cols)
            . ' FROM categories'
            . ' WHERE ' . $where
            . ' ORDER BY `' . $nameCol . '` ASC'
            . ' LIMIT :lim';

        try {
            $st = $this->pdo->prepare($sql);
            foreach ($params as $k => $v) {
                if ($k === ':lim' || $k === ':pid' || $k === ':exclude') {
                    $st->bindValue($k, (int)$v, PDO::PARAM_INT);
                } else {
                    $st->bindValue($k, $v);
                }
            }
            $st->execute();
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('[CategoryService] siblingCategories error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * List published news under a category.
     *
     * @return array{items: array<int,array<string,mixed>>, total: int, total_pages: int, page: int, per_page: int}
     */
    public function listPublishedNews(int $categoryId, int $page = 1, int $perPage = 12, string $sort = 'latest', string $period = 'all'): array
    {
        $categoryId = (int)$categoryId;
        $page = max(1, (int)$page);
        $perPage = max(1, min(60, (int)$perPage));

        if ($categoryId <= 0 || !$this->hasTable('news')) {
            return ['items' => [], 'total' => 0, 'total_pages' => 1, 'page' => $page, 'per_page' => $perPage];
        }

        $offset = ($page - 1) * $perPage;

        $dateCol = $this->hasColumn('news', 'publish_at') ? 'publish_at'
            : ($this->hasColumn('news', 'published_at') ? 'published_at'
                : ($this->hasColumn('news', 'created_at') ? 'created_at' : 'id'));

        $imgCol = $this->hasColumn('news', 'featured_image') ? 'featured_image'
            : ($this->hasColumn('news', 'image_path') ? 'image_path'
                : ($this->hasColumn('news', 'image') ? 'image' : null));

        $excerptCol = $this->hasColumn('news', 'excerpt') ? 'excerpt'
            : ($this->hasColumn('news', 'summary') ? 'summary' : null);

        $where = 'n.category_id = :cid AND ' . $this->publishedWhere('n');

        // Optional period filtering
        if ($period !== 'all' && $dateCol !== 'id') {
            $days = match ($period) {
                'today' => 1,
                'week' => 7,
                'month' => 30,
                'year' => 365,
                default => 0,
            };
            if ($days > 0) {
                $where .= " AND n.`{$dateCol}` >= (NOW() - INTERVAL {$days} DAY)";
            }
        }

        // Sort
        $order = "n.`{$dateCol}` DESC, n.id DESC";
        if ($sort === 'most_read') {
            $viewsCol = $this->hasColumn('news', 'views') ? 'views' : ($this->hasColumn('news', 'view_count') ? 'view_count' : null);
            if ($viewsCol) {
                $order = "n.`{$viewsCol}` DESC, n.`{$dateCol}` DESC, n.id DESC";
            }
        }

        // Count
        $total = 0;
        try {
            $st = $this->pdo->prepare("SELECT COUNT(*) FROM news n WHERE {$where}");
            $st->execute([':cid' => $categoryId]);
            $total = (int)($st->fetchColumn() ?: 0);
        } catch (Throwable $e) {
            error_log('[CategoryService] listPublishedNews count error: ' . $e->getMessage());
        }

        $totalPages = max(1, (int)ceil($total / $perPage));

        // Select
        $select = [
            'n.id',
            'n.title',
            'n.slug',
            "n.`{$dateCol}` AS publish_at",
        ];
        if ($imgCol) $select[] = "n.`{$imgCol}` AS featured_image";
        if ($excerptCol) $select[] = "n.`{$excerptCol}` AS excerpt";

        $items = [];
        try {
            $sql = 'SELECT ' . implode(', ', $select) . " FROM news n WHERE {$where} ORDER BY {$order} LIMIT :lim OFFSET :off";
            $st = $this->pdo->prepare($sql);
            $st->bindValue(':cid', $categoryId, PDO::PARAM_INT);
            $st->bindValue(':lim', $perPage, PDO::PARAM_INT);
            $st->bindValue(':off', $offset, PDO::PARAM_INT);
            $st->execute();
            $items = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('[CategoryService] listPublishedNews list error: ' . $e->getMessage());
        }

        return [
            'items' => $items,
            'total' => $total,
            'total_pages' => $totalPages,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    public function __version(): string
    {
        return 'CategoryService v3 2026-01-17';
    }
}
