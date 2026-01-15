Godyar CMS v1.09 — Security Hardening Notes (Jan 14, 2026)

1) Move .env OUTSIDE webroot (recommended)
   - This release removes /.env from the package.
   - Place your .env at:
       /home/USER/godyar/.env
     OR (supported by includes/env.php):
       one level above ABSPATH (public_html)
   - includes/env.php searches:
       ABSPATH/.env
       dirname(ABSPATH)/.env

2) Production PHP settings
   - display_errors=0 (already forced in includes/bootstrap.php)
   - log_errors=1 (recommended)

3) Isolate sessions from /tmp (shared hosting)
   - Create a private folder:
       /home/USER/godyar/storage/sessions
   - Set in .env:
       SESSION_SAVE_PATH=/home/USER/godyar/storage/sessions
       SESSION_SAMESITE=Lax

4) Cleanup (server-side)
   - Remove duplicate docroots (www vs public_html)
   - Remove old/ and any *.bak_* files from the live webroot
   - Ensure /uploads forbids PHP execution (uploads/.htaccess already included)
