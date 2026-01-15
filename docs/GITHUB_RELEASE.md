# GitHub Release Checklist

## Before pushing
- Ensure `.env` is NOT in the repo
- Ensure `includes/env_path.php` is NOT in the repo (use `.example`)
- Remove logs, backups, archives from web root
- Confirm `.gitignore` is present and correct

## Suggested repository layout
This repo is intended to be the web root. If you prefer a `public/` layout:
- Move web-entry files (app.php, index.php, .htaccess) into `public/`
- Update RewriteBase and paths accordingly

## Tagging
- Use semantic tags: `v1.09-rc5`, `v1.09`, etc.
