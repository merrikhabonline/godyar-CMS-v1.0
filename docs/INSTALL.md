# Godyar CMS v1.09 — Installation Guide

> This repository is a cleaned, GitHub-ready distribution intended for deployment on shared hosting (cPanel) or a VPS.

## Requirements
- PHP 8.1+ (tested: PHP 8.4.x)
- MariaDB 10.4+ / MySQL 5.7+ (tested: MariaDB 10.11.x)
- Apache or LiteSpeed with `mod_rewrite`
- PHP extensions: `mysqli`, `mbstring`, `curl`, `json`, `openssl`

## 1) Upload files
Upload the repository contents into your web root (typically `public_html/`).

## 2) Create database
From cPanel:
1. Create a database (e.g. `cpuser_myar`)
2. Create a DB user and assign it **ALL PRIVILEGES**
3. Note the DB host: usually `localhost`

## 3) Create `.env` outside web root
Create a private directory (outside `public_html`), e.g.:
- `/home/YOUR_USERNAME/godyar_private/`

Create the file:
- `/home/YOUR_USERNAME/godyar_private/.env`

Use this template (adjust values):
```env
APP_ENV=production
APP_URL=https://example.com

DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=cpuser_myar
DB_USERNAME=cpuser_dbuser
DB_PASSWORD=YOUR_PASSWORD
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci

SESSION_SAVE_PATH=/home/YOUR_USERNAME/godyar_private/sessions
```

File permissions:
- `.env` => `600`
- `godyar_private/` => `700`

## 4) Point the app to the `.env` file
### Option A (preferred if it works): `.htaccess`
Edit `public_html/.htaccess`:
- Set `ENV_FILE` to your `.env` location:
```apache
SetEnv ENV_FILE /home/YOUR_USERNAME/godyar_private/.env
```

### Option B (recommended for PHP-FPM): `includes/env_path.php`
If `SetEnv` does not reach PHP (common with PHP-FPM), do:
1. Copy `includes/env_path.php.example` to `includes/env_path.php`
2. Edit the path inside the file to your `.env` absolute path
3. **Do not commit** `includes/env_path.php` to Git.

## 5) Sessions path (shared hosting hardening)
Create:
- `/home/YOUR_USERNAME/godyar_private/sessions` with permissions `700`

If your host supports `.user.ini`, copy:
- `.user.ini.example` -> `.user.ini`
- `admin/.user.ini.example` -> `admin/.user.ini`

Then edit:
- `error_log`
- `session.save_path`
to match your server paths.

## 6) Install / migrate database
### If you use the installer
Open:
- `https://example.com/install/`
Complete the wizard and then **lock the installer**:
- Create `install/install.lock` OR remove the `install/` directory.

### If you install manually
Run the SQL in:
- `install/sql/` (core schema)
- then `admin/db/migrations/` (incremental migrations)

## 7) Post-install hardening
- Ensure `display_errors=0` in production
- Ensure `.env` is not inside web root
- Ensure `uploads/` does not execute PHP (already blocked by `.htaccess`)
- Confirm language routes `/ar /en /fr` work without redirect loops

## Smoke test (minimum)
- `/`, `/ar`, `/en`, `/fr`
- `/admin/login`
- `/news/id/1` (or any valid ID)
- `/category/<slug>`
- `/saved`, `/trending`, `/archive`
