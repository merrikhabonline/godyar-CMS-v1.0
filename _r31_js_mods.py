import re
from pathlib import Path

ROOT = Path(__file__).resolve().parent

def apply(rel, subs):
    p = ROOT / rel
    if not p.exists():
        print(f"SKIP {rel}")
        return
    s = p.read_text(encoding='utf-8')
    orig = s
    for pat, repl, flags in subs:
        s = re.sub(pat, repl, s, flags=flags)
    if s != orig:
        p.write_text(s, encoding='utf-8')
        print(f"UPDATED {rel}")

# Generic utility substitutions
CATCH_EMPTY = [
    (r"\.catch\(\(\)\s*=>\s*\{\s*\}\)", ".catch(() => { /* intentionally ignore errors */ })", 0),
    (r"\.catch\(\(\)\s*=>\s*\{\s*\/\*\s*no-op.*?\*\/\s*\}\)", ".catch(() => { /* intentionally ignore errors */ })", re.DOTALL),
]

# sw.js
apply("sw.js", [
    (r"if \(resp\s*&&\s*resp\.ok\)", "if (resp?.ok)", 0),
    (r"if \(fresh\s*&&\s*fresh\.ok\)", "if (fresh?.ok)", 0),
    (r"const urlToOpen = \(event\.notification && event\.notification\.data && event\.notification\.data\.url\) \|\| '/'\;",
     "const urlToOpen = event.notification?.data?.url || '/';", 0),
    (r"cache\.put\(req,\s*resp\.clone\(\)\)\.catch\(\(\)\s*=>\s*\{\s*\}\);",
     "cache.put(req, resp.clone()).catch(() => { /* intentionally ignore errors */ });", 0),
    (r"cache\.put\(req,\s*fresh\.clone\(\)\)\.catch\(\(\)\s*=>\s*\{\s*\}\);",
     "cache.put(req, fresh.clone()).catch(() => { /* intentionally ignore errors */ });", 0),
    (r"cache\.put\(req,\s*fresh\.clone\(\)\)\.catch\(\(\)\s*=>\s*\{\s*\/\/.*?\}\);",
     "cache.put(req, fresh.clone()).catch(() => { /* intentionally ignore errors */ });", re.DOTALL),
    (r"cache\.put\(req,\s*resp\.clone\(\)\)\.catch\(\(\)\s*=>\s*\{\s*\/\/.*?\}\);",
     "cache.put(req, resp.clone()).catch(() => { /* intentionally ignore errors */ });", re.DOTALL),
])

# saved-filters.js
apply("admin/assets/js/saved-filters.js", [
    (r"if \(json\s*&&\s*json\.ok\)", "if (json?.ok)", 0),
])

# js/sidebar.js
apply("js/sidebar.js", [
    (r"while\(el\s*&&\s*el\.firstChild\)", "while(el?.firstChild)", 0),
])

# assets/js/gdy-embeds.js
apply("assets/js/gdy-embeds.js", [
    (r"if \(body && body\.dataset && body\.dataset\.baseUrl\)", "if (body?.dataset?.baseUrl)", 0),
    (r"b = \(body\.dataset\.baseUrl \|\| \"\"\)\.trim\(\);", "b = (body?.dataset?.baseUrl || \"\").trim();", 0),
    (r"if \(children && children\.length\) children\.forEach\(", "children?.forEach(", 0),
])

# assets/js/pwa.js
apply("assets/js/pwa.js", [
    (r"if \(reg && reg\.waiting\)", "if (reg?.waiting)", 0),
    (r"reg\.waiting\.postMessage\(", "reg.waiting?.postMessage(", 0),
])

# assets/js/image_fallback.js
apply("assets/js/image_fallback.js", [
    (r"if\(m\.addedNodes && m\.addedNodes\.length\)", "if(m.addedNodes?.length)", 0),
    (r"if\(n\.querySelectorAll\)\{\s*n\.querySelectorAll\('\s*img\[data-gdy-fallback-src\], img\[data-gdy-hide-onerror\], img\[data-gdy-show-onload\]\s*'\)\.forEach\(applyToImg\);\s*\}",
     "n.querySelectorAll?.('img[data-gdy-fallback-src], img[data-gdy-hide-onerror], img[data-gdy-show-onload]')?.forEach(applyToImg);", re.DOTALL),
])

# assets/js/public-interactions.js
apply("assets/js/public-interactions.js", [
    (r"if \(el && el\.matches && el\.matches\('select\.js-auto-submit'\)\)", "if (el?.matches?.('select.js-auto-submit'))", 0),
    (r"if \(form\) form\.submit\(\);", "form?.submit();", 0),
    (r"var btn = e\.target && e\.target\.closest \? e\.target\.closest\('\[data-copy-url\]'\) : null;",
     "var btn = e.target?.closest?.('[data-copy-url]') || null;", 0),
    (r"var btn = e\.target && e\.target\.closest \? e\.target\.closest\('\.password-toggle-btn'\) : null;",
     "var btn = e.target?.closest('.password-toggle-btn');", 0),
    (r"if \(navigator\.clipboard && navigator\.clipboard\.writeText\)", "if (navigator.clipboard?.writeText)", 0),
    (r"if \(typeof onSuccess === 'function'\) onSuccess\(\);", "onSuccess?.();", 0),
])

# assets/js/modules/push_prompt.js
apply("assets/js/modules/push_prompt.js", [
    (r"if\(Notification && Notification\.permission === 'denied'\)", "if(Notification?.permission === 'denied')", 0),
    (r"btnLater && btnLater\.addEventListener", "btnLater?.addEventListener", 0),
    (r"btnEnable && btnEnable\.addEventListener", "btnEnable?.addEventListener", 0),
])

# assets/js/modules/home_loadmore.js
apply("assets/js/modules/home_loadmore.js", [
    (r"const id = item && item\.id \? item\.id : 0;", "const id = item?.id ? item.id : 0;", 0),
    (r"const title = \(item && item\.title\) \? String\(item\.title\) : ''\;", "const title = item?.title ? String(item.title) : '';", 0),
    (r"const excerpt = \(item && item\.excerpt\) \? String\(item\.excerpt\)\.trim\(\) : ''\;", "const excerpt = item?.excerpt ? String(item.excerpt).trim() : '';", 0),
    (r"const img = \(item && item\.image\) \? resolveUrl\(item\.image\) : defaultThumb\(\);", "const img = item?.image ? resolveUrl(item.image) : defaultThumb();", 0),
    (r"const date = \(item && item\.date\) \? String\(item\.date\) : ''\;", "const date = item?.date ? String(item.date) : '';", 0),
])

# assets/js/modules/mobile_app.js
apply("assets/js/modules/mobile_app.js", [
    (r"while\(el && el\.firstChild\)", "while(el?.firstChild)", 0),
    (r"const auth = b && b\.dataset \? \(b\.dataset\.auth === '1'\) : false;", "const auth = b.dataset?.auth === '1';", 0),
    (r"const uid = b && b\.dataset \? parseInt\(b\.dataset\.userId \|\| '0', 10\) : 0;", "const uid = parseInt(b.dataset?.userId || '0', 10);", 0),
    (r"if\(j && j\.ok\)", "if(j?.ok)", 0),
    (r"const url = \(location && location\.href\) \? location\.href : ''\;", "const url = location?.href ? location.href : '';", 0),
    (r"\.then\(j => \{ if\(j && j\.ok\) setBookmarkBtnState\(btn, !!j\.saved\); \}\)",
     ".then(j => { if(j?.ok) setBookmarkBtnState(btn, !!j?.saved); })", 0),
    (r"if\(res && res\.ok\)", "if(res?.ok)", 0),
    (r"if\(btn && btn\.dataset\.newsId\)", "if(btn?.dataset.newsId)", 0),
    (r"if\(any && any\.getAttribute\('data-news-id'\)\)", "if(any?.getAttribute('data-news-id'))", 0),
])

# assets/js/modules/category_page.js
apply("assets/js/modules/category_page.js", [
    (r"const wrap = doc\.body && doc\.body\.firstElementChild \? doc\.body\.firstElementChild : null;", "const wrap = doc.body?.firstElementChild;", 0),
    (r"const html = \(data && data\.html\) \? String\(data\.html\) : ''\;", "const html = data?.html ? String(data.html) : '';", 0),
])

# assets/js/modules/mobile_search_overlay.js
apply("assets/js/modules/mobile_search_overlay.js", [
    (r"setTimeout\(function\(\)\{ try\{ input && input\.focus\(\); \}catch\(e\)\{\}\ },", "setTimeout(function(){ try{ input?.focus(); }catch(e){} },", 0),
    (r"const title = \(it && it\.title\) \? String\(it\.title\) : ''\;", "const title = (it?.title) ? String(it.title) : '';", 0),
    (r"const url = \(it && it\.url\) \? String\(it\.url\) : '#';", "const url = (it?.url) ? String(it.url) : '#';", 0),
    (r"var q = \(input && input\.value \|\| ''\)\.trim\(\);", "var q = (input?.value || '').trim();", 0),
    (r"\.then\(function\(j\)\{ if\(j && j\.ok\) renderItems\(j\.items \|\| \[\]\); \}\)",
     ".then(function(j){ if(j?.ok) renderItems(j?.items || []); })", 0),
    (r"\.then\(function\(j\)\{\s*if\(j && j\.ok\)\{\s*renderItems\(j\.suggestions \|\| \[\]\);\s*\}\s*\}\)",
     ".then(function(j){\n          if(j?.ok){\n            renderItems(j.suggestions || []);\n          }\n        })", re.DOTALL),
    (r"\.catch\(function\(\)\{\}\);", ".catch(function() {\n        // Intentionally ignoring errors\n      });", 0),
])

# assets/js/modules/newsletter_subscribe.js
apply("assets/js/modules/newsletter_subscribe.js", [
    (r"var email = \(input && input\.value\) \? input\.value\.trim\(\) : ''\;", "var email = input?.value ? input.value.trim() : '';", 0),
    (r"setMsg\(\(data && data\.message\) \? data\.message : 'تعذر الاشتراك الآن، حاول لاحقًا\.', false\);",
     "setMsg(data?.message ? data.message : 'تعذر الاشتراك الآن، حاول لاحقًا.', false);", 0),
])

# assets/js/modules/search.js
apply("assets/js/modules/search.js", [
    (r"const title = it && it\.title \? String\(it\.title\) : ''\;", "const title = it?.title ? String(it.title) : '';", 0),
    (r"const type = it && it\.type \? String\(it\.type\) : ''\;", "const type = it?.type ? String(it.type) : '';", 0),
    (r"const url = it && it\.url \? String\(it\.url\) : '#';", "const url = it?.url ? String(it.url) : '#';", 0),
])

# admin/assets/editor/gdy-editor.js
apply("admin/assets/editor/gdy-editor.js", [
    (r"if \(t && t\.tagName === 'IMG'\)", "if (t?.tagName === 'IMG')", 0),
    (r"else if \(t && t\.closest\) img = t\.closest\('img'\);", "else img = t.closest?.('img');", 0),
    (r"else if \(t && t\.closest\) img = t\.closest\('img'\);", "else img = t.closest?.('img');", 0),
    (r"var el = \(node && node\.nodeType === 1\) \? node : \(node \? node\.parentElement : null\);",
     "var el = (node?.nodeType === 1) ? node : node?.parentElement;", 0),
    (r"if \(r && r\.startContainer && r\.startContainer\.nodeType === 1\)", "if (r?.startContainer?.nodeType === 1)", 0),
    (r"var curW = \(img\.style && img\.style\.width\) \? img\.style\.width : ''\;", "var curW = img.style?.width ? img.style.width : '';", 0),
    (r"var curH = \(img\.style && img\.style\.height\) \? img\.style\.height : ''\;", "var curH = img.style?.height ? img.style.height : '';", 0),
])

# assets/js/news-extras.js (partial, rest handled below)
apply("assets/js/news-extras.js", [
    (r"return \(root && root\.textContent\) \? String\(root\.textContent\) : ''\;", "return root?.textContent ? String(root.textContent) : '';", 0),
    (r"const counts = \(state && state\.counts\) \|\| \{\};", "const counts = state?.counts || {};", 0),
    (r"const mine = new Set\(\(state && state\.mine\) \|\| \[\]\);", "const mine = new Set(state?.mine || []);", 0),
    (r"if\(res && res\.ok\)", "if(res?.ok)", 0),
    (r"if\(res && res\.ok\) render\(res\);", "if(res?.ok) render(res);", 0),
    (r"const poll = payload && payload\.poll \? payload\.poll : null;", "const poll = payload?.poll || null;", 0),
    (r"const counts = payload && payload\.counts \? payload\.counts : \{\};", "const counts = payload?.counts || {};", 0),
    (r"const votedFor = payload \? payload\.votedFor : null;", "const votedFor = payload?.votedFor || null;", 0),
    (r"const oid = opt && \(opt\.id \?\? opt\.value \?\? opt\.option_id\);", "const oid = opt?.id ?? opt?.value ?? opt?.option_id;", 0),
    (r"const label = opt && \(opt\.label \?\? opt\.text \?\? opt\.title \?\? ''\);", "const label = opt?.label ?? opt?.text ?? opt?.title ?? '';", 0),
    (r"const pct = opt && \(opt\.pct \?\? opt\.percent \?\? 0\);", "const pct = opt?.pct ?? opt?.percent ?? 0;", 0),
    (r"const votes = opt && \(opt\.votes \?\? counts\[oid\] \?\? 0\);", "const votes = opt?.votes ?? counts[oid] ?? 0;", 0),
    (r"const v = \(langEl && langEl\.value\) \? langEl\.value : \(document\.documentElement\.lang \|\| 'ar'\);",
     "const v = langEl?.value ? langEl.value : (document.documentElement.lang || 'ar');", 0),
    (r"const status = \(e && e\.status\) \? \('\s*\(HTTP '\s*\+\s*e\.status\s*\+\s*'\)\s*\)\s*:\s*''\;",
     "const status = e?.status ? (' (HTTP ' + e.status + ')') : '';", 0),
])

# Add comments to empty catch blocks in selected JS
for rel in [
    "sw.js",
    "assets/js/pwa.js",
    "assets/js/news-extras.js",
    "assets/js/modules/mobile_search_overlay.js",
]:
    apply(rel, CATCH_EMPTY)

# Post-process news-extras normalizeText and unicode regex flags using simple replacements
p = ROOT / "assets/js/news-extras.js"
if p.exists():
    s = p.read_text(encoding='utf-8')
    orig = s
    # normalizeText: add /u flags
    s = s.replace("replace(/\u00A0/g", "replace(/\\u00A0/gu")
    s = s.replace("replace(/\\s+/g", "replace(/\\s+/gu")
    s = s.replace("replace(/[•·•]+/g", "replace(/[•·•]+/gu")
    # sentence split: add unicode flag
    s = s.replace("p.split(/(?<=[\\.\\!\\؟\\?])\\s+/)", "p.split(/(?<=[\\.\\!\\؟\\?])\\s+/u)")
    if s != orig:
        p.write_text(s, encoding='utf-8')
        print("UPDATED assets/js/news-extras.js (post)")
