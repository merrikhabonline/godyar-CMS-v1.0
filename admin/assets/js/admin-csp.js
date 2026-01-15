/* admin-csp.js
 * هدف الملف:
 * - إزالة الاعتماد على inline event handlers (onclick/onsubmit/onerror)
 * - دعم CSP صارمة (script-src بدون 'unsafe-inline')
 * ملاحظة: يعتمد على وجود عناصر data-* داخل قوالب الإدارة.
 */
(function () {
  'use strict';

  function safeSameOriginUrl(raw) {
    try {
      if (!raw) return null;
      const u = new URL(String(raw), window.location.origin);
      if (u.origin !== window.location.origin) return null;
      if (u.protocol !== 'http:' && u.protocol !== 'https:') return null;
      // Block javascript: and other weird schemes (URL() already normalizes, but be explicit)
      return u.toString();
    } catch (e) {
      return null;
    }
  }

  function clearEl(el) {
    while (el.firstChild) el.removeChild(el.firstChild);
  }

  function closest(el, selector) {
    while (el && el.nodeType === 1) {
      if (el.matches(selector)) return el;
      el = el.parentElement;
    }
    return null;
  }

  function getConfirmMessage(el) {
    // data-confirm قد يكون نصًا أو قد يكون null
    const msg = el.getAttribute('data-confirm');
    return (msg && String(msg).trim()) ? msg : null;
  }

  function askConfirm(el) {
    const msg = getConfirmMessage(el);
    if (!msg) return true;
    return window.confirm(msg);
  }

  // ---------- Click handlers ----------
  document.addEventListener('click', function (e) {
    const target = e.target;

    // Stop propagation helper
    const stopEl = closest(target, '[data-stop-prop="1"]');
    if (stopEl) {
      e.stopPropagation();
      return;
    }

    // Confirm for clickable elements
    const confirmEl = closest(target, '[data-confirm]');
    if (confirmEl) {
      if (!askConfirm(confirmEl)) {
        e.preventDefault();
        e.stopPropagation();
        return;
      }
    }

    // Check a target checkbox (e.g., test-only push)
    const checkEl = closest(target, '[data-check-target]');
    if (checkEl) {
      const sel = checkEl.getAttribute('data-check-target');
      if (sel) {
        const cb = document.querySelector(sel);
        if (cb) cb.checked = true;
      }
    }

    // Generic actions
    const actionEl = closest(target, '[data-action]');
    if (!actionEl) return;

    const action = actionEl.getAttribute('data-action');

    if (action === 'toggle-password') {
      e.preventDefault();
      const input = document.querySelector('input[type="password"], input[data-role="password"]');
      if (input) input.type = (input.type === 'password') ? 'text' : 'password';
      return;
    }

    if (action === 'open-media-modal') {
      e.preventDefault();
      const field = actionEl.getAttribute('data-target') || '';
      if (typeof window.godyarOpenMediaModal === 'function') {
        window.godyarOpenMediaModal(field);
      }
      return;
    }

    if (action === 'ai-suggest-title') {
      e.preventDefault();
      if (typeof window.gdyPageAiSuggestTitle === 'function') {
        window.gdyPageAiSuggestTitle(actionEl);
      }
      return;
    }

    if (action === 'ai-improve-content') {
      e.preventDefault();
      if (typeof window.gdyPageAiImproveContent === 'function') {
        window.gdyPageAiImproveContent(actionEl);
      }
      return;
    }

    if (action === 'row-select') {
      // Used in review/bulk tables: selects current row checkbox before submit
      // Do NOT prevent default submission.
      const tr = closest(actionEl, 'tr');
      const form = closest(actionEl, 'form') || document.getElementById('bulkForm');
      if (form) {
        // Uncheck all
        form.querySelectorAll('input[type="checkbox"][name="ids[]"]').forEach(function (cb) {
          cb.checked = false;
        });
      }
      if (tr) {
        const cb = tr.querySelector('input[type="checkbox"][name="ids[]"]');
        if (cb) cb.checked = true;
      }
      // Update selected count if present
      const countEl = document.getElementById('selectedCount');
      if (countEl) {
        const selected = (form || document).querySelectorAll('input[type="checkbox"][name="ids[]"]:checked').length;
        countEl.textContent = String(selected);
      }
      return;
    }

    if (action === 'open-attachment-modal') {
      e.preventDefault();
      const uid = actionEl.getAttribute('data-uid');
      if (!uid) return;

      const modal = document.getElementById(uid + '_modal');
      const mName = document.getElementById(uid + '_m_name');
      const mBody = document.getElementById(uid + '_m_body');
      const mDl = document.getElementById(uid + '_m_download');

      if (!modal || !mBody) return;

      const rawUrl = actionEl.getAttribute('data-url') || '';
      const url = safeSameOriginUrl(rawUrl) || '';
      const name = actionEl.getAttribute('data-name') || 'المرفق';
      const isPdf = actionEl.getAttribute('data-pdf') === '1';
      const isImg = actionEl.getAttribute('data-img') === '1';
      const isTxt = actionEl.getAttribute('data-txt') === '1';

      if (mName) mName.textContent = name;
      if (mDl && url) mDl.setAttribute('href', url);

      // Build preview (DOM-only to avoid XSS)
      clearEl(mBody);

      if (isImg && url) {
        const img = document.createElement('img');
        img.src = url;
        img.alt = '';
        img.style.maxWidth = '100%';
        img.style.height = 'auto';
        img.style.borderRadius = '10px';
        mBody.appendChild(img);

      } else if (isPdf && url) {
        // iframe only for PDF (same-origin) per security policy
        const lower = url.toLowerCase();
        if (!lower.endsWith('.pdf') && lower.indexOf('.pdf?') === -1 && lower.indexOf('.pdf#') === -1) {
          const msg = document.createElement('div');
          msg.className = 'text-muted small';
          msg.textContent = 'تُسمح المعاينة داخل إطار للملفات PDF فقط.';
          mBody.appendChild(msg);
        } else {
          const frame = document.createElement('iframe');
          frame.src = url;
          frame.style.width = '100%';
          frame.style.height = '70vh';
          frame.style.border = '0';
          frame.style.borderRadius = '10px';
          mBody.appendChild(frame);
        }

      } else if (isTxt && url) {
        // Try fetch (same-origin)
        fetch(url, { credentials: 'same-origin' })
          .then(function (r) { return r.ok ? r.text() : ''; })
          .then(function (t) {
            clearEl(mBody);
            const pre = document.createElement('pre');
            pre.style.whiteSpace = 'pre-wrap';
            pre.style.wordBreak = 'break-word';
            pre.textContent = (t || '—');
            mBody.appendChild(pre);
          })
          .catch(function () {
            clearEl(mBody);
            mBody.textContent = 'تعذر عرض المحتوى.';
          });
      } else {
        const msg = document.createElement('div');
        msg.className = 'text-muted small';
        msg.textContent = url ? 'لا توجد معاينة لهذا النوع.' : 'الرابط غير صالح أو غير مسموح (يجب أن يكون من نفس الموقع).';
        mBody.appendChild(msg);
      }

      modal.classList.add('is-open');
      modal.style.display = 'block';
      document.body.style.overflow = 'hidden';
      return;
    }

    if (action === 'close-attachment-modal') {
      e.preventDefault();
      const uid = actionEl.getAttribute('data-uid');
      if (!uid) return;
      const modal = document.getElementById(uid + '_modal');
      if (!modal) return;
      modal.classList.remove('is-open');
      modal.style.display = 'none';
      document.body.style.overflow = '';
      return;
    }
  }, true);

  // ---------- Submit handlers (CSRF confirm on forms) ----------
  document.addEventListener('submit', function (e) {
    const form = e.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (form.hasAttribute('data-confirm')) {
      if (!askConfirm(form)) {
        e.preventDefault();
        e.stopPropagation();
      }
    }
  }, true);

  // ---------- Image error handlers (replace inline onerror) ----------
  document.addEventListener('error', function (e) {
    const el = e.target;
    if (!(el instanceof HTMLImageElement)) return;

    const fallback = el.getAttribute('data-fallback-src');
    if (fallback && el.src !== fallback) {
      el.src = fallback;
      return;
    }

    const mode = el.getAttribute('data-img-error');
    if (!mode) return;

    if (mode === 'hide') {
      el.style.display = 'none';
      return;
    }

    if (mode === 'hide-show-next-flex') {
      el.style.display = 'none';
      const next = el.nextElementSibling;
      if (next && next instanceof HTMLElement) next.style.display = 'flex';
      return;
    }

    if (mode === 'hide-show-next-unhide') {
      el.style.display = 'none';
      const next = el.nextElementSibling;
      if (next && next instanceof HTMLElement) next.classList.remove('d-none');
      return;
    }

    if (mode === 'replace-parent-error') {
      const p = el.parentElement;
      if (p) {
        while(p.firstChild) p.removeChild(p.firstChild);
        const d = document.createElement('div');
        d.className = 'text-danger small';
        const i = document.createElement('i');
        i.className = 'fa-solid fa-triangle-exclamation';
        i.setAttribute('aria-hidden','true');
        d.appendChild(i);
        d.appendChild(document.createTextNode(' فشل تحميل الصورة'));
        p.appendChild(d);
      }
    }
  }, true);

})();
