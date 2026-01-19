# Changelog / سجل التغييرات

All notable changes to this GitHub release are documented here.

> Date: 2026-01-17 (Asia/Riyadh)

## 2026-01-17 Maintenance Patch (v1.11-git-clean-r7)

### Admin / لوحة التحكم
- Sidebar: merged “إدارة الأخبار” under a single **الأخبار** group with a nested submenu (no duplicate cards).  
  **Files:** `admin/layout/sidebar.php`
- Responsive layout: ensured admin pages render within the viewport (no overflow under the sidebar).  
  **Files:** `admin/news/polls.php`, `admin/news/questions.php`, CSS adjustments where applicable.
- News create/edit: fixed saving of `content`, `category_id`, and image columns (`featured_image`, `image_path`, `image`) using schema detection.  
  **Files:** `admin/news/create.php`, `admin/news/edit.php`, `admin/news/_news_helpers.php`

### Frontend / الواجهة
- Article page: fixed featured image rendering; added safe fallback to render first image found in article content when a featured image is missing.
  **Files:** `frontend/views/news_report.php`
- Author avatar: hidden from all news pages and sidebars; kept only in **كتّاب الرأي** section.
  **Files:** `frontend/views/partials/sidebar.php` (and any news templates that previously printed the avatar).

### Services / الخدمات
- Category service: added missing methods used by controllers and fixed compatibility of `siblingCategories()` to accept root categories (NULL parent) safely.
  **Files:** `includes/classes/Services/CategoryService.php`, `src/Services/CategoryService.php`
- Tag service: fixed `findBySlug()` to avoid selecting non-existent `description` column (schema compatibility).
  **Files:** `includes/classes/Services/TagService.php` (and/or `src/Services/TagService.php` if present).

### Controllers / الكنترولرز
- NewsController: fixed related-news query invocation (removed invalid argument passing).
  **Files:** `src/Http/Controllers/NewsController.php`

### Repository hygiene / نظافة الريبو
- Removed runtime artifacts from `storage/` (logs and ratelimit JSON) and improved `.gitignore` so placeholders remain tracked.

## 2026-01-17 Compatibility Patch (v1.11-git-clean-r8)

### Frontend compatibility / توافق المتصفحات
- Safari/WebKit: ensured `-webkit-backdrop-filter` is present where `backdrop-filter` is used, and enforced correct declaration order (WebKit first).
  **Files:** `frontend/views/home_modern.php`, `frontend/views/partials/header.php`, `header.php`
- Cats nav scrollbar: removed `scrollbar-width` declarations to avoid Safari/Webhint compatibility warnings; WebKit scrollbar is hidden with `::-webkit-scrollbar`.
  **Files:** `frontend/views/partials/header.php`, `header.php`
- Added `assets/css/compat.css` to the loaded stylesheet stack (so compatibility rules apply site-wide).
  **Files:** `frontend/views/partials/header.php`, `header.php`

## 2026-01-17 Schema Compatibility Patch (v1.11-git-clean-r9)

### Users schema / توافق جدول المستخدمين
- Fixed: `Unknown column 'display_name'` during user creation (registration/OAuth) on databases where `users.display_name` does not exist.
  - Insert logic now falls back to `users.name` / `users.full_name` (if present) when `display_name` is missing.
  - Read logic now selects the best available column as `display_name` (alias), avoiding SQL errors.
  **Files:** `register.php`, `oauth/facebook_callback.php`, `oauth/github_callback.php`, `oauth/facebook/callback/facebook_callback.php`, `includes/bootstrap.php`

### Comments / التعليقات
- Fixed: comments endpoints no longer SELECT `u.display_name` when the column is absent (schema-safe COALESCE expression built dynamically).
  **Files:** `frontend/ajax/comments.php`, `frontend/api/comments.php`, `ajax/comments.php`

## 2026-01-17 Hotfix Patch (v1.11-git-clean-r10)

### Users schema / توافق جدول المستخدمين
- Hardened user creation against inconsistent schema detection in some hosting environments (caching/multiple connections):
  - If an `INSERT INTO users` fails with SQLSTATE `42S22` referencing `display_name`, the code automatically retries the insert **without** `display_name`.
  **Files:** `register.php`, `oauth/facebook_callback.php`, `oauth/github_callback.php`, `oauth/facebook/callback/facebook_callback.php`

## 2026-01-17 Frontend Lint & Headers Patch (v1.11-git-clean-r11)

### Frontend compatibility / توافق المتصفحات
- Safari/WebKit: added `-webkit-backdrop-filter` in remaining locations where `backdrop-filter` appeared without the WebKit prefix.
  **Files:** `frontend/views/home/content.php`, `frontend/views/news_report.php`, `assets/css/style.css`
- Scrollbars: removed Firefox-only `scrollbar-width` from templates to eliminate Safari/Webhint warnings; WebKit scrollbar hiding remains via `::-webkit-scrollbar`.
  **Files:** `header.php`, `frontend/views/partials/header.php`, `frontend/views/home_modern.php`, `assets/css/compat.css`

### Security/Headers / الأمان والترويسات
- Sessions: disabled PHP's legacy session cache limiter so it no longer injects `Expires` and `Pragma` headers; added an explicit `Cache-Control` header for HTML responses.
  **Files:** `includes/bootstrap.php`
- Clickjacking: removed `X-Frame-Options` header in favor of CSP `frame-ancestors` (already present).
  **Files:** `includes/bootstrap.php`

### Cookies / الكوكيز
- Language cookies: emitted RFC-compliant `Expires` attribute (space-separated) to satisfy strict linters, and added `HttpOnly`.
  **Files:** `includes/lang_prefix.php`, `includes/lang.php`, `includes/i18n.php`, `admin/i18n.php`, `admin/includes/lang.php`

## 2026-01-17 Deployment Compatibility Hotfix (v1.11-git-clean-r12)

### Users schema / توافق جدول المستخدمين
- Fixed: some hosts still triggered `Unknown column 'display_name' in 'INSERT INTO'` due to inconsistent cached column lists.
  - Registration and OAuth callbacks now validate column existence using `information_schema` at insert time (`db_column_exists`) rather than relying on precomputed arrays.
  **Files:** `register.php`, `oauth/github_callback.php`, `oauth/facebook_callback.php`, `oauth/facebook/callback/facebook_callback.php`

### Assets / الملفات الثابتة
- Fixed: `assets/css/compat.css` was shipped with restrictive permissions, causing servers to return an HTML error page (wrong MIME type) and strict browsers to refuse the stylesheet.
  **Files:** `assets/css/compat.css`

## 2026-01-17 Users Schema Hotfix (v1.11-git-clean-r13)

### Users schema / توافق جدول المستخدمين
- Fixed: some PDO drivers return a generic error code (e.g., `HY000`) while the message still contains `SQLSTATE[42S22]`, which prevented the "retry without `display_name`" logic from triggering.
  - The retry condition now keys off the exception message (presence of `display_name` + `Unknown column` or `42S22`) instead of relying on `getCode()`.
  **Files:** `register.php`, `oauth/github_callback.php`, `oauth/facebook_callback.php`, `oauth/facebook/callback/facebook_callback.php`

## 2026-01-17 Display Name Validation Patch (v1.11-git-clean-r14)

### Display name / اسم الظهور
- Fixed: display name validation could reject valid Arabic names due to invisible characters, smart apostrophes, or diacritics.
  - Added Unicode-safe normalization (`normalize_display_name`) that removes zero-width characters, normalizes apostrophes, strips combining marks, and collapses whitespace.
  - Enforced the exact allowed character set: letters/numbers/spaces and `. _ - '`.
  - OAuth: sanitize provider display names to the allowed character set to avoid validation failures.
  **Files:** `includes/bootstrap.php`, `profile.php`, `register.php`, `oauth/github_callback.php`, `oauth/facebook_callback.php`, `oauth/facebook/callback/facebook_callback.php`

## 2026-01-18 Admin UI Icons Patch (v1.11-git-clean-r15)

### Admin news list / أيقونات لوحة التحكم
- Fixed: the action buttons in `admin/news/index.php` were rendering as dots because they referenced the generic `#dot` icon.
  - Added dedicated SVG symbols (`external-link`, `edit`, `duplicate`, `toggle`, `trash`, `copy`) to `assets/icons/gdy-icons.svg`.
  - Wired those icons into the action buttons so each action has a meaningful icon.
  **Files:** `assets/icons/gdy-icons.svg`, `admin/news/index.php`

## 2026-01-18 Icon Sprite Expansion (v1.11-git-clean-r16)

### Icons / ملف الأيقونات
- Added: expanded the SVG sprite to include a broader set of UI/action icons used across the admin and frontend.
- Fixed: `#dot` symbol was empty; it now renders a proper glyph to avoid "blank" icons where `#dot` is still referenced.
  **Files:** `assets/icons/gdy-icons.svg`

## 2026-01-18 Full Admin + Frontend Icon Wiring (v1.11-git-clean-r17)

### Icons / أيقونات الموقع ولوحة التحكم
- Changed: replaced all remaining `#dot` icon usages across the admin and frontend with meaningful icons (save/edit/trash/copy/search/menu/etc.) to prevent "dot-only" UI controls.
- Fixed: admin sidebar menu items that carry an `icon` field now render that icon correctly (previously hardcoded and/or malformed), both in `admin/layout/sidebar.php` and legacy admin layouts.
- Improved: footer/mobile bar icons (saved/bookmark, apps, team, address) now use correct symbols.
- Improved: category load-more endpoint now uses semantically correct meta icons (calendar, eye, spark).

### Added
- Added: `bookmark` and `spark` SVG symbols.

**Files:**
- `assets/icons/gdy-icons.svg`
- `admin/layout/sidebar.php`
- `admin/includes/admin_layout.php`
- `includes/admin_layout.php`
- `views/partials/footer.php`, `footer.php`
- `api/v1/category_loadmore.php`

## 2026-01-18 Inline SVG Sprite + Local <use> References (v1.11-git-clean-r18)

### Icons / حل نهائي لاختفاء الأيقونات
- Fixed: action/control icons could appear as empty circles in some deployments due to external SVG sprite fetch/MIME/CSP constraints.
- Changed: all `<use>` references were switched from external `.../assets/icons/gdy-icons.svg#id` to local fragment `#id`.
- Added: inlined the SVG sprite into both admin and frontend headers so icons always render without relying on a separate HTTP fetch.

**Files:**
- `admin/layout/header.php`
- `header.php`
- `frontend/views/partials/header.php`
- Multiple templates/pages updated to use `href="#icon-id"`.

## 2026-01-18 Security/Lint Hardening (v1.11-git-clean-r20)

### JavaScript / Media fallback
- Hardened: validated `data-gdy-hide-parent-class` before applying it as a class token, preventing unsafe class injection and satisfying stricter security analyzers.
  **File:** `assets/js/image_fallback.js`

### PHP / Regex wrappers
- Changed: removed `call_user_func()` usage in regex wrapper helpers and switched to direct `preg_replace()` / `preg_replace_callback()` calls (no change in behavior).
  **Files:** `includes/hotfix_prepend.php`, `includes/lang.php`

### PHP / Tags schema compatibility
- Fixed: `TagService::findBySlug()` no longer selects the optional `tags.description` column (some installs do not have it). It now always fetches core fields and returns an empty description.
  **File:** `includes/classes/Services/TagService.php`

## 2026-01-18 Static Analysis Compatibility Pass (v1.11-git-clean-r21)

### PHP 7.4 compatibility
- Fixed: removed `mixed` typehints from `Cache::put()`/`Cache::get()` to support PHP 7.4+.

### Linter hardening (reduce false positives / improve portability)
- Changed: removed error-suppression operator `@` in session start/hardening flow and regex helper wrappers (kept behavior safe with guards).
- Changed: normalized regex wrapper signatures to tolerate 4/5-arg call patterns consistently.
- Fixed: JSON-LD partial now uses a single, de-duplicated `json_encode` flags mask.
- Improved: replaced a few template outputs that used `h()` with explicit `htmlspecialchars()` in high-visibility areas (footer + trending) to satisfy strict scanners.

**Files:**
- `includes/cache.php`
- `includes/lang.php`
- `includes/hotfix_prepend.php`
- `includes/bootstrap.php`
- `header.php`
- `frontend/views/partials/header.php`
- `frontend/views/partials/jsonld.php`
- `frontend/views/trending.php`
- `frontend/views/trending/content.php`
- `footer.php`

## 2026-01-18 Consistency + Autofix-Resilience (v1.11-git-clean-r22)

### Search filter consistency
- Fixed: aligned the global search date filter keys with the server-side search logic (use `24h/7d/30d/year`), to prevent filters from being ignored.
  **File:** `frontend/search.php`

### Queue scheduler reliability
- Fixed: removed stray backslashes in multi-line SQL strings (can break queries on some PHP builds / when auto-formatters touch the file).
  **File:** `admin/system/queue/index.php`

### Notes
- If you are using a static analyzer with “Autofix”, avoid bulk autofixes on PHP templates; prefer targeted fixes, then re-run the analyzer.

## 2026-01-18 View include hardening (v1.11-git-clean-r23)

### Safe view loading without breaking templates
- Fixed: replaced per-controller `view_include()` helpers with a single, centralized `gdy_require_view()` that:
  - only allows includes from `frontend/views/`
  - keeps controller scope intact (views still see controller variables)
  - provides a friendly “View not found” message instead of a fatal error
- Improved: removed error-suppression operator `@` from frontend controller session start.

**Files:**
- `includes/functions.php`
- `frontend/controllers/ArchiveController.php`
- `frontend/controllers/SearchController.php`
- `frontend/controllers/AuthorController.php`

## 2026-01-18 Security/Static-Analysis Patch (v1.11-git-clean-r24)

### Frontend controllers / كنترولرز الواجهة
- Replaced view loader helper calls with direct `require` of constant view paths to eliminate false-positive "include injection" warnings from static analyzers.
  **Files:** `frontend/controllers/ArchiveController.php`, `frontend/controllers/SearchController.php`, `frontend/controllers/AuthorController.php`

### Core helpers / الدوال المساعدة
- Removed unused generic include helpers (`gdy_require_view`, `safe_include`, `safe_include_return`, `load_view`) to prevent automated fixers from corrupting view/templates and to reduce "include injection" findings.
  **Files:** `includes/functions.php`
