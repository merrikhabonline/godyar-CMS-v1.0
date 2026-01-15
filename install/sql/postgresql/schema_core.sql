DROP TABLE IF EXISTS news_tags CASCADE;
DROP TABLE IF EXISTS role_permissions CASCADE;
DROP TABLE IF EXISTS user_roles CASCADE;
DROP TABLE IF EXISTS news CASCADE;
DROP TABLE IF EXISTS tags CASCADE;
DROP TABLE IF EXISTS pages CASCADE;
DROP TABLE IF EXISTS settings CASCADE;
DROP TABLE IF EXISTS categories CASCADE;
DROP TABLE IF EXISTS permissions CASCADE;
DROP TABLE IF EXISTS roles CASCADE;
DROP TABLE IF EXISTS users CASCADE;

CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    name VARCHAR(190) NULL,
    username VARCHAR(60) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,

    password VARCHAR(255) NULL,
    role VARCHAR(100) NOT NULL DEFAULT 'user',
    is_admin BOOLEAN NOT NULL DEFAULT FALSE,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    avatar VARCHAR(255) NULL,
    last_login_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL
);
CREATE UNIQUE INDEX IF NOT EXISTS uniq_users_email ON users(email);
CREATE UNIQUE INDEX IF NOT EXISTS uniq_users_username ON users(username);
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);
CREATE INDEX IF NOT EXISTS idx_users_status ON users(status);
CREATE INDEX IF NOT EXISTS idx_users_is_admin ON users(is_admin);

CREATE TABLE roles (
    id SERIAL PRIMARY KEY,
    name VARCHAR(60) NOT NULL,
    label VARCHAR(120) NOT NULL,
    description TEXT NULL,
    is_system BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL
);
CREATE UNIQUE INDEX IF NOT EXISTS uniq_roles_name ON roles(name);

CREATE TABLE permissions (
    id SERIAL PRIMARY KEY,
    code VARCHAR(120) NOT NULL,
    label VARCHAR(190) NOT NULL,
    description TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE UNIQUE INDEX IF NOT EXISTS uniq_permissions_code ON permissions(code);

CREATE TABLE role_permissions (
    role_id INTEGER NOT NULL,
    permission_id INTEGER NOT NULL,
    PRIMARY KEY (role_id, permission_id)
);

CREATE TABLE user_roles (
    user_id INTEGER NOT NULL,
    role_id INTEGER NOT NULL,
    PRIMARY KEY (user_id, role_id)
);

INSERT INTO roles (name, label, description, is_system)
SELECT 'admin','مدير النظام','صلاحيات كاملة',TRUE
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE name='admin');

INSERT INTO roles (name, label, description, is_system)
SELECT 'writer','كاتب','كتابة وتعديل أخبار',TRUE
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE name='writer');

INSERT INTO roles (name, label, description, is_system)
SELECT 'user','مستخدم','حساب مستخدم عادي',TRUE
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE name='user');

INSERT INTO permissions (code, label, description)
SELECT '*','صلاحيات كاملة','جميع الصلاحيات'
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code='*');

INSERT INTO permissions (code, label, description)
SELECT 'manage_users','إدارة المستخدمين',NULL
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code='manage_users');

INSERT INTO permissions (code, label, description)
SELECT 'manage_roles','إدارة الأدوار والصلاحيات',NULL
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code='manage_roles');

INSERT INTO permissions (code, label, description)
SELECT 'manage_security','إعدادات الأمان',NULL
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code='manage_security');

INSERT INTO permissions (code, label, description)
SELECT 'manage_plugins','إدارة الإضافات',NULL
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code='manage_plugins');

INSERT INTO permissions (code, label, description)
SELECT 'posts.*','إدارة الأخبار',NULL
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code='posts.*');

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r, permissions p
WHERE r.name='admin' AND p.code='*'
  AND NOT EXISTS (
    SELECT 1 FROM role_permissions rp
    WHERE rp.role_id=r.id AND rp.permission_id=p.id
  );

CREATE TABLE categories (
    id SERIAL PRIMARY KEY,
    name VARCHAR(190) NOT NULL,
    slug VARCHAR(190) NOT NULL,
    parent_id INTEGER NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL
);
CREATE UNIQUE INDEX IF NOT EXISTS uniq_categories_slug ON categories(slug);
CREATE INDEX IF NOT EXISTS idx_categories_parent ON categories(parent_id);

CREATE TABLE news (
    id SERIAL PRIMARY KEY,
    category_id INTEGER NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    excerpt TEXT NULL,
    content TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'published',
    featured_image VARCHAR(255) NULL,
    image_path VARCHAR(255) NULL,
    image VARCHAR(255) NULL,
    is_breaking BOOLEAN NOT NULL DEFAULT FALSE,
    view_count INTEGER NOT NULL DEFAULT 0,
    published_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL
);
CREATE UNIQUE INDEX IF NOT EXISTS uniq_news_slug ON news(slug);
CREATE INDEX IF NOT EXISTS idx_news_category ON news(category_id);
CREATE INDEX IF NOT EXISTS idx_news_status ON news(status);
CREATE INDEX IF NOT EXISTS idx_news_published ON news(published_at);

CREATE TABLE tags (
    id SERIAL PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(190) NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE UNIQUE INDEX IF NOT EXISTS uniq_tags_slug ON tags(slug);

CREATE TABLE news_tags (
    news_id INTEGER NOT NULL,
    tag_id INTEGER NOT NULL,
    PRIMARY KEY (news_id, tag_id)
);

CREATE TABLE pages (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(190) NOT NULL,
    content TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'published',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL
);
CREATE UNIQUE INDEX IF NOT EXISTS uniq_pages_slug ON pages(slug);

CREATE TABLE settings (
    setting_key VARCHAR(120) NOT NULL PRIMARY KEY,
    value TEXT NULL,
    updated_at TIMESTAMP NULL
);

UPDATE settings SET value='Godyar', updated_at=CURRENT_TIMESTAMP WHERE setting_key='site_name';
INSERT INTO settings(setting_key, value, updated_at)
SELECT 'site_name','Godyar',CURRENT_TIMESTAMP
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE setting_key='site_name');

UPDATE settings SET value='ar', updated_at=CURRENT_TIMESTAMP WHERE setting_key='site_lang';
INSERT INTO settings(setting_key, value, updated_at)
SELECT 'site_lang','ar',CURRENT_TIMESTAMP
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE setting_key='site_lang');

UPDATE settings SET value='rtl', updated_at=CURRENT_TIMESTAMP WHERE setting_key='site_dir';
INSERT INTO settings(setting_key, value, updated_at)
SELECT 'site_dir','rtl',CURRENT_TIMESTAMP
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE setting_key='site_dir');
