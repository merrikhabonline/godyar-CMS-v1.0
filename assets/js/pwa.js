/**
 * Godyar PWA helper (no inline handlers)
 * - Service Worker registration + update UX
 * - Install prompt banner (Chrome/Edge/Android)
 * - iOS Add-to-Home-Screen tip (Safari)
 */
(function () {
  "use strict";

  const ls = {
    get(key) {
      try { return localStorage.getItem(key); } catch (e) { return null; }
    },
    set(key, val) {
      try { localStorage.setItem(key, val); } catch (e) {}
    }
  };

  const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
  const isStandalone = (function () {
    const iosStandalone = (navigator.standalone === true);
    const displayStandalone = window.matchMedia && window.matchMedia("(display-mode: standalone)").matches;
    return iosStandalone || displayStandalone;
  })();

  // ---------------------------
  // Install prompt (Chromium)
  // ---------------------------
  let deferredPrompt = null;

  window.addEventListener("beforeinstallprompt", function (e) {
    /*
      ملاحظة مهمة:
      بعض المتصفحات (Chromium) تعرض تحذير Console مثل:
      "Banner not shown: beforeinstallpromptevent.preventDefault() called..."
      إذا تم استدعاء preventDefault() ولم يتم استدعاء prompt() لاحقاً.

      لتجنب هذا التحذير نهائياً، لا نستدعي preventDefault هنا.
      نترك المتصفح يدير عرض التنبيه تلقائياً، ونستخدم البانر كإرشاد اختياري.
    */

    deferredPrompt = e;

    // Don't show if already dismissed recently
    if (ls.get("gdy_install_dismissed") === "1") return;

    showInstallBanner();
  });

  window.addEventListener("appinstalled", function () {
    deferredPrompt = null;
    ls.set("gdy_install_dismissed", "1");
    const b = document.getElementById("gdyInstallBanner");
    if (b) b.remove();
  });

  function showInstallBanner() {
    if (isStandalone) return;
    if (document.getElementById("gdyInstallBanner")) return;

    const banner = document.createElement("div");
    banner.id = "gdyInstallBanner";
    banner.className = "gdy-install-banner";
    banner.setAttribute("role", "dialog");
    banner.setAttribute("aria-label", "تثبيت التطبيق");

    const inner = document.createElement('div');
    inner.className = 'gdy-install-banner__inner';

    const text = document.createElement('div');
    text.className = 'gdy-install-banner__text';

    const title = document.createElement('div');
    title.className = 'gdy-install-banner__title';
    title.textContent = '📱 ثبّت غديار كتطبيق';

    const desc = document.createElement('div');
    desc.className = 'gdy-install-banner__desc';
    desc.textContent = 'تجربة أسرع + دعم عدم الاتصال';

    text.appendChild(title);
    text.appendChild(desc);

    const actions = document.createElement('div');
    actions.className = 'gdy-install-banner__actions';

    const installNow = document.createElement('button');
    installNow.type = 'button';
    installNow.id = 'gdyInstallNow';
    installNow.className = 'gdy-install-banner__btn';
    installNow.textContent = 'تثبيت';

    const dismiss = document.createElement('button');
    dismiss.type = 'button';
    dismiss.id = 'gdyInstallDismiss';
    dismiss.className = 'gdy-install-banner__close';
    dismiss.setAttribute('aria-label', 'إغلاق');
    dismiss.textContent = '×';

    actions.appendChild(installNow);
    actions.appendChild(dismiss);

    inner.appendChild(text);
    inner.appendChild(actions);
    banner.appendChild(inner);

    document.body.appendChild(banner);

    const installBtn = document.getElementById("gdyInstallNow");
    const closeBtn = document.getElementById("gdyInstallDismiss");

    if (installBtn) {
      installBtn.addEventListener("click", function () {
        // في بعض المتصفحات/السيناريوهات قد لا يكون prompt متاحاً.
        // عندها نعرض إرشاداً مختصراً للمستخدم.
        if (!deferredPrompt || typeof deferredPrompt.prompt !== 'function') {
          alert(isIOS
            ? 'على iPhone/iPad: من زر المشاركة اختر "Add to Home Screen".'
            : 'من قائمة المتصفح اختر "تثبيت التطبيق" أو "Add to Home screen".');
          return;
        }

        deferredPrompt.prompt();
        if (deferredPrompt.userChoice && typeof deferredPrompt.userChoice.then === 'function') {
          deferredPrompt.userChoice.then(function () {
            deferredPrompt = null;
          });
        } else {
          deferredPrompt = null;
        }
      });
    }

    if (closeBtn) {
      closeBtn.addEventListener("click", function () {
        ls.set("gdy_install_dismissed", "1");
        banner.remove();
      });
    }
  }

  // ---------------------------
  // iOS A2HS tip (Safari)
  // ---------------------------
  function showIosTip() {
    if (!isIOS || isStandalone) return;
    if (ls.get("gdy_ios_a2hs_dismissed") === "1") return;
    if (document.getElementById("gdyIosA2hsTip")) return;

    const tip = document.createElement("div");
    tip.id = "gdyIosA2hsTip";
    tip.className = "gdy-ios-a2hs";
    tip.setAttribute("role", "dialog");
    tip.setAttribute("aria-label", "إضافة إلى الشاشة الرئيسية");

    const inner = document.createElement('div');
    inner.className = 'gdy-ios-a2hs__inner';

    const txtWrap = document.createElement('div');
    txtWrap.className = 'gdy-ios-a2hs__txt';

    const strong = document.createElement('strong');
    strong.textContent = 'ثبّت الموقع كتطبيق';

    const line = document.createElement('div');
    line.appendChild(document.createTextNode('اضغط زر المشاركة '));

    // inline SVG icon
    const svgNS = 'http://www.w3.org/2000/svg';
    const svg = document.createElementNS(svgNS, 'svg');
    svg.setAttribute('width', '14');
    svg.setAttribute('height', '14');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('aria-hidden', 'true');
    const path = document.createElementNS(svgNS, 'path');
    path.setAttribute('fill', 'currentColor');
    path.setAttribute('d', 'M12 3l4 4h-3v7h-2V7H8l4-4zm-7 9h2v7h10v-7h2v9H5v-9z');
    svg.appendChild(path);
    line.appendChild(svg);

    line.appendChild(document.createTextNode(' ثم اختر '));
    const b = document.createElement('b');
    b.textContent = 'إضافة إلى الشاشة الرئيسية';
    line.appendChild(b);
    line.appendChild(document.createTextNode('.'));

    txtWrap.appendChild(strong);
    txtWrap.appendChild(line);

    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'gdy-ios-a2hs__close';
    closeBtn.setAttribute('aria-label', 'إغلاق');
    closeBtn.textContent = '×';

    inner.appendChild(txtWrap);
    inner.appendChild(closeBtn);
    tip.appendChild(inner);

    document.body.appendChild(tip);

    const close = tip.querySelector(".gdy-ios-a2hs__close");
    if (close) {
      close.addEventListener("click", function () {
        ls.set("gdy_ios_a2hs_dismissed", "1");
        tip.remove();
      });
    }
  }

  // ---------------------------
  // Service Worker + Updates
  // ---------------------------
  function registerSW() {
    if (!("serviceWorker" in navigator)) return;

    // Prefer root SW for full scope
    navigator.serviceWorker.register((window.GDY_SW_URL||"/sw.js"), {scope:"/"}).then(function (reg) {
      // Listen for updates
      reg.addEventListener("updatefound", function () {
        const newWorker = reg.installing;
        if (!newWorker) return;
        newWorker.addEventListener("statechange", function () {
          if (newWorker.state === "installed" && navigator.serviceWorker.controller) {
            // New version is ready
            showUpdateBanner(reg);
          }
        });
      });
    }).catch(function () {
      // ignore
    });

    // If controller changes, reload once (after user accepted)
    navigator.serviceWorker.addEventListener("controllerchange", function () {
      // We reload only if user already accepted update banner
      if (ls.get("gdy_sw_reload") === "1") {
        ls.set("gdy_sw_reload", "0");
        window.location.reload();
      }
    });
  }

  function showUpdateBanner(reg) {
    if (document.getElementById("gdyUpdateBanner")) return;

    const banner = document.createElement("div");
    banner.id = "gdyUpdateBanner";
    banner.className = "gdy-update-banner";
    banner.setAttribute("role", "dialog");
    banner.setAttribute("aria-label", "تحديث متوفر");

    const inner = document.createElement('div');
    inner.className = 'gdy-install-banner__inner';

    const text = document.createElement('div');
    text.className = 'gdy-install-banner__text';

    const title = document.createElement('div');
    title.className = 'gdy-install-banner__title';
    title.textContent = '📱 ثبّت غديار كتطبيق';

    const desc = document.createElement('div');
    desc.className = 'gdy-install-banner__desc';
    desc.textContent = 'تجربة أسرع + دعم عدم الاتصال';

    text.appendChild(title);
    text.appendChild(desc);

    const actions = document.createElement('div');
    actions.className = 'gdy-install-banner__actions';

    const installNow = document.createElement('button');
    installNow.type = 'button';
    installNow.id = 'gdyInstallNow';
    installNow.className = 'gdy-install-banner__btn';
    installNow.textContent = 'تثبيت';

    const dismiss = document.createElement('button');
    dismiss.type = 'button';
    dismiss.id = 'gdyInstallDismiss';
    dismiss.className = 'gdy-install-banner__close';
    dismiss.setAttribute('aria-label', 'إغلاق');
    dismiss.textContent = '×';

    actions.appendChild(installNow);
    actions.appendChild(dismiss);

    inner.appendChild(text);
    inner.appendChild(actions);
    banner.appendChild(inner);

    document.body.appendChild(banner);

    const btnNow = document.getElementById("gdyUpdateNow");
    const btnLater = document.getElementById("gdyUpdateLater");

    if (btnNow) {
      btnNow.addEventListener("click", function () {
        try {
          if (reg?.waiting) {
            ls.set("gdy_sw_reload", "1");
            reg.waiting?.postMessage({ action: "skipWaiting" });
          } else {
            // fallback
            window.location.reload();
          }
        } catch (e) {
          window.location.reload();
        }
      });
    }

    if (btnLater) {
      btnLater.addEventListener("click", function () {
        banner.remove();
      });
    }
  }

  // ---------------------------
  // Init
  // ---------------------------
  function init() {
    try { showIosTip(); } catch (e) {}
    try { registerSW(); } catch (e) {}
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
