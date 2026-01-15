ALTER TABLE comments ADD COLUMN parent_id INTEGER NOT NULL DEFAULT 0, ADD INDEX idx_parent_id (parent_id);
