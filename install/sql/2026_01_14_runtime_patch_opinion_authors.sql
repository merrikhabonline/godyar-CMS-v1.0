/* Godyar CMS - runtime patch for opinion_authors compatibility (MariaDB/MySQL)
   Adds missing columns expected by frontend queries.
*/

SET @tbl_exists := (
  SELECT COUNT(*) FROM information_schema.tables
  WHERE table_schema = DATABASE() AND table_name='opinion_authors'
);

-- If table doesn't exist, create it (minimal compatible schema)
SET @create_sql := IF(@tbl_exists=0,
  'CREATE TABLE opinion_authors (id INT UNSIGNED NOT NULL AUTO_INCREMENT, name VARCHAR(190) NOT NULL, slug VARCHAR(190) NOT NULL, bio TEXT NULL, photo VARCHAR(255) NULL, specialization VARCHAR(190) NULL, is_active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY(id), UNIQUE KEY uniq_slug(slug), KEY idx_active(is_active)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
  'DO 0;'
);

PREPARE stmt1 FROM @create_sql;
EXECUTE stmt1;
DEALLOCATE PREPARE stmt1;

-- Add specialization column if missing
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name='opinion_authors' AND column_name='specialization'
);

SET @alter_sql := IF(@tbl_exists=1 AND @col_exists=0,
  'ALTER TABLE opinion_authors ADD COLUMN specialization VARCHAR(190) NULL AFTER photo;',
  'DO 0;'
);

PREPARE stmt2 FROM @alter_sql;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;
