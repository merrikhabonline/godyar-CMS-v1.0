# Changelog

## v1.0.x-security-pg (RC)

### Security
- Added CSRF token to the mobile plugin toggle form in admin plugins page.

### PostgreSQL compatibility
- Improved table/column existence checks to support PostgreSQL via `information_schema`.
- Updated admin/news helpers to fetch columns via `information_schema` when using PostgreSQL.
- Removed MySQL-only `LIMIT` from UPDATE in admin/news/edit.php.

### CI
- Fixed `.github/workflows/php.yml` (was incomplete) and pinned actions by commit SHA.


## 2026-01-14 — Release Candidate (Clean + Hardened)
- Security hardening: deny sensitive files, disable display_errors via .user.ini, session hardening.
- Removed unsafe repair script and runtime logs from release package.
- Added BOM/whitespace scan tool and deployment/security checklist.
