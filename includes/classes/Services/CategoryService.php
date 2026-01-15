<?php
declare(strict_types=1);

namespace Godyar\Services;

use PDO;
use Throwable;

/**
 * CategoryService (schema-tolerant)
 *
 * This service is expected by CategoryController and other parts of the app.
 * It focuses on safe lookup by slug/id and listing categories.
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

    public function slugById(int $id): ?string
    {
        $slugCol = $this->slugColumn();
        if ($slugCol === null) return null;

        try {
            $st = $this->pdo->prepare('SELECT `' . $slugCol . '` FROM categories WHERE id = ? LIMIT 1');
            $st->execute([$id]);
            $slug = trim((string) ($st->fetchColumn() ?: ''));
            return $slug !== '' ? $slug : null;
        } catch (Throwable $e) {
            @error_log('[CategoryService] slugById error: ' . $e->getMessage());
            return null;
        }
    }

    public function idBySlug(string $slug): ?int
    {
        $slug = trim($slug);
        if ($slug === '') return null;

        if (ctype_digit($slug)) return (int) $slug;

        // optional mapping table to keep old slugs working
        if ($this->hasTable('category_slug_map')) {
            try {
                $st = $this->pdo->prepare("SELECT category_id FROM category_slug_map WHERE slug = :s LIMIT 1");
                $st->execute([':s' => $slug]);
                $id = (int) ($st->fetchColumn() ?: 0);
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
            $id = (int) ($st->fetchColumn() ?: 0);
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
        $id = $isNumeric ? (int) $param : 0;

        $nameCol = $this->nameColumn();
        $slugCol = $this->slugColumn();

        $where = $isNumeric ? 'id = :id' : ($slugCol ? ('`' . $slugCol . '` = :s') : '1=0');

        $sql = "SELECT id, `{$nameCol}` AS name" . ($slugCol ? (", `{$slugCol}` AS slug") : ", '' AS slug") . "
                FROM categories
                WHERE {$where}
                LIMIT 1";

        try {
            $st = $this->pdo->prepare($sql);
            if ($isNumeric) {
                $st->execute([':id' => $id]);
            } else {
                $st->execute([':s' => $param]);
            }
            $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;
            return $row;
        } catch (Throwable $e) {
            @error_log('[CategoryService] findBySlugOrId error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * List categories for /categories and nav.
     *
     * @return array<int,array<string,mixed>>
     */
    public function all(int $limit = 500): array
    {
        $limit = max(1, min(2000, $limit));
        $nameCol = $this->nameColumn();
        $slugCol = $this->slugColumn();

        $sql = "SELECT id, `{$nameCol}` AS name" . ($slugCol ? (", `{$slugCol}` AS slug") : ", '' AS slug") . "
                FROM categories
                ORDER BY `{$nameCol}` ASC
                LIMIT :lim";

        try {
            $st = $this->pdo->prepare($sql);
            $st->bindValue(':lim', $limit, PDO::PARAM_INT);
            $st->execute();
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            @error_log('[CategoryService] all error: ' . $e->getMessage());
            return [];
        }
    }

    
    /**
     * Return categories for header/navigation.
     * Schema-tolerant: prefers is_active + sort_order if available.
     *
     * @return array<int, array<string,mixed>>
     */
    public function headerCategories(int $limit = 8): array
    {
        $limit = max(1, min(50, $limit));

        $nameCol = $this->nameColumn();
        $slugCol = $this->slugColumn();

        $cols = ["id", "`{$nameCol}` AS name"];
        if ($slugCol) {
            $cols[] = "`{$slugCol}` AS slug";
        } else {
            $cols[] = "'' AS slug";
        }

        // Optional columns
        if ($this->hasColumn('categories', 'parent_id')) {
            $cols[] = "parent_id";
        }
        if ($this->hasColumn('categories', 'sort_order')) {
            $cols[] = "sort_order";
        }

        $where = [];
        if ($this->hasColumn('categories', 'is_active')) {
            $where[] = "is_active = 1";
        }

        $order = [];
        if ($this->hasColumn('categories', 'sort_order')) {
            $order[] = "sort_order ASC";
        }
        $order[] = "`{$nameCol}` ASC";
        $order[] = "id ASC";

        $sql = "SELECT " . implode(', ', $cols) . "
                FROM categories" .
                ($where ? (" WHERE " . implode(' AND ', $where)) : "") . "
                ORDER BY " . implode(', ', $order) . "
                LIMIT :lim";

        try {
            $st = $this->pdo->prepare($sql);
            $st->bindValue(':lim', $limit, PDO::PARAM_INT);
            $st->execute();
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            @error_log('[CategoryService] headerCategories error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Find a single category by slug.
     * Backward-compatible helper expected by CategoryController.
     *
     * @return array<string,mixed>|null
     */
    public function findBySlug(string $slug): ?array
    {
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }

        $nameCol = $this->nameColumn();
        $slugCol = $this->slugColumn();
        if (!$slugCol) {
            // If schema doesn't have a slug column, there is no reliable slug lookup.
            return null;
        }

        $cols = ["id", "`{$nameCol}` AS name", "`{$slugCol}` AS slug"];
        foreach (['description', 'parent_id', 'sort_order', 'is_active', 'is_members_only'] as $c) {
            if ($this->hasColumn('categories', $c)) {
                $cols[] = $c;
            }
        }

        $sql = "SELECT " . implode(', ', $cols) . " FROM categories WHERE `{$slugCol}` = :slug LIMIT 1";

        try {
            $st = $this->pdo->prepare($sql);
            $st->execute([':slug' => $slug]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable $e) {
            @error_log('[CategoryService] findBySlug error: ' . $e->getMessage());
            return null;
        }
    }

    public function __version(): string
    {
        return 'CategoryService v2 (parse-fix) 2026-01-14';
    }
}
