# Security Policy

## Reporting vulnerabilities
Please report security issues privately to the maintainers.

## Hardening defaults (recommended)
- Keep `.env` outside web root
- Disable `display_errors` in production
- Store sessions outside `/tmp` on shared hosting
- Block access to sensitive files via `.htaccess`
