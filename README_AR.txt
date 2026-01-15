Godyar R4 — Fix ERR_TOO_MANY_REDIRECTS (/ar /en /fr) + Fix "headers already sent"

Symptoms:
- ERR_TOO_MANY_REDIRECTS on /ar /en /fr
- PHP Warning: Cannot modify header information - headers already sent by language_prefix_router.php:1

Root causes:
1) language_prefix_router.php had output at line 1 (usually BOM/whitespace), causing headers_sent().
2) includes/lang_prefix.php likely performed redirects for canonical language routing, causing loops.

Fix:
A) Replace public_html/language_prefix_router.php with the provided BOM-free version (R4).
   - This version does NOT send headers and DOES NOT output anything.
   - It strips /{lang} prefix internally by rewriting REQUEST_URI only.

B) Replace includes/lang_prefix.php with the provided R4 version (NO REDIRECTS).
   - It only determines the language and sets cookie best-effort (no redirect).

C) Ensure app.php includes language_prefix_router.php BEFORE bootstrap:
   - In public_html/app.php, at the very top (first lines after <?php):
     require_once __DIR__ . '/language_prefix_router.php';

D) After upload, CLEAR site cookies for godyar.org (or test Incognito).
