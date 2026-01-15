Godyar runtime fix (R8)

Files:
- public_html/includes/site_settings.php
- public_html/frontend/controllers/TrendingController.php (from your last pack; safe settings usage)
- public_html/includes/classes/Services/NewsService.php (includes archive() method)

What this fixes:
1) Fatal: gdy_load_settings(): Argument #1 must be bool, PDO given
   - site_settings_load() and gdy_load_settings() now accept PDO OR bool for force reload.

2) Warnings about settings key/value column name differences across DB forks
   - settings loader normalizes key/value from: key/setting_key/name and value/setting_value/val.

Deploy:
- Upload and overwrite the included files on your hosting (same paths).
- Clear OPcache (if enabled) or restart PHP from your hosting panel.
