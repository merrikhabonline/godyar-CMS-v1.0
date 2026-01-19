// Public interactions without inline event handlers
(function () {
  'use strict';

  // Auto-submit selects
  document.addEventListener('change', function (e) {
    var el = e.target;
    if (el?.matches?.('select.js-auto-submit')) {
      var form = el.form;
      form?.submit();
    }
  });

  // Copy buttons (generic)
  document.addEventListener('click', function (e) {
    var btn = e.target?.closest?.('[data-copy-url]') || null;
    if (!btn) return;

    var url = btn.getAttribute('data-copy-url') || '';
    if (!url) return;

    copyToClipboard(url, function () {
      var okMsg = btn.getAttribute('data-copy-success') || 'تم نسخ الرابط';
      alert(okMsg);
    });
  });

  // Password toggle buttons
  document.addEventListener('click', function (e) {
    var btn = e.target?.closest('.password-toggle-btn');
    if (!btn) return;

    var inputId = btn.getAttribute('data-target') || 'password';
    var input = document.getElementById(inputId);
    if (!input) return;

    var icon = document.getElementById(btn.getAttribute('data-icon') || 'passwordToggleIcon');

    if (input.type === 'password') {
      input.type = 'text';
      if (icon) icon.className = 'fa-regular fa-eye-slash';
    } else {
      input.type = 'password';
      if (icon) icon.className = 'fa-regular fa-eye';
    }
  });

  function copyToClipboard(text, onSuccess) {
    if (navigator.clipboard?.writeText) {
      navigator.clipboard.writeText(text).then(function () {
        onSuccess?.();
      }).catch(function () {
        fallbackCopy(text, onSuccess);
      });
    } else {
      fallbackCopy(text, onSuccess);
    }
  }

  function fallbackCopy(text, onSuccess) {
    try {
      var ta = document.createElement('textarea');
      ta.value = text;
      ta.setAttribute('readonly', '');
      ta.style.position = 'fixed';
      ta.style.left = '-9999px';
      document.body.appendChild(ta);
      ta.select();
      document.execCommand('copy');
      document.body.removeChild(ta);
      onSuccess?.();
    } catch (err) {
      // ignore
    }
  }
})();
