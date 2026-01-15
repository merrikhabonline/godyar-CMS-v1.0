ALTER TABLE admin_saved_filters ADD COLUMN is_default BOOLEAN NOT NULL DEFAULT false; CREATE INDEX idx_admin_saved_filters_default ON admin_saved_filters(user_id, page_key, is_default);
