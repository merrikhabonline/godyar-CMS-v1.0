# Shared Hosting Deployment Notes (cPanel)

## Verify security headers
Use a browser extension or an online header checker to confirm:
- X-Frame-Options: SAMEORIGIN
- X-Content-Type-Options: nosniff
- Referrer-Policy
- Permissions-Policy
- HSTS (only on HTTPS)

## Common issues & fixes
### 1) Database user becomes empty after moving `.env`
Cause: `ENV_FILE` not being passed to PHP via `.htaccess` (PHP-FPM).
Fix: Use `includes/env_path.php` (see INSTALL.md).

### 2) Redirect loops on /ar /en /fr
Use the provided `.htaccess` (NO-LOOP) and ensure `app.php` handles language prefixes internally (no redirect).

### 3) SVG icons blocked (nosniff)
If SVG files fail to load, ensure `.htaccess` has:
```apache
AddType image/svg+xml .svg .svgz
```
