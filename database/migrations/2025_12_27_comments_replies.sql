ALTER TABLE comments ADD COLUMN parent_id INT NOT NULL DEFAULT 0, ADD INDEX idx_parent_id (parent_id);
