/*
  Godyar CMS - CSP-friendly media fallbacks
  ---------------------------------------
  This file replaces inline HTML event handlers (onerror/onload) with
  delegated JS listeners using data attributes.

  Supported attributes:
  - data-gdy-fallback-src="/path/to/fallback.jpg"
      When the image fails, swap to fallback (once).

  - data-gdy-hide-onerror="1"
      Hide the element if it fails to load.

  - data-gdy-hide-parent-class="className"
      If hiding on error, also add className to parent element.

  - data-gdy-show-onload="1"
      When the image loads, set opacity to 1 (for fade-in patterns).
*/

(function(){
  'use strict';

  function applyToImg(img){
    if(!img || img.__gdyFallbackBound) return;
    img.__gdyFallbackBound = true;

    var showOnLoad = img.getAttribute('data-gdy-show-onload');
    if(showOnLoad){
      img.addEventListener('load', function(){
        try { img.style.opacity = '1'; } catch(e) {}
      }, { once: true });
    }

    img.addEventListener('error', function(){
      try {
        var fallback = img.getAttribute('data-gdy-fallback-src');
        if(fallback && !img.__gdyFallbackTried){
          img.__gdyFallbackTried = true;
          img.src = fallback;
          return;
        }

        var hide = img.getAttribute('data-gdy-hide-onerror');
        if(hide && hide !== '0'){
          img.style.display = 'none';
          var parentClass = img.getAttribute('data-gdy-hide-parent-class');
          // Security hardening: only allow safe CSS class tokens.
          if(parentClass && img.parentElement){
            parentClass = String(parentClass).trim();
            if(/^[A-Za-z0-9_-]{1,64}$/.test(parentClass)){
              img.parentElement.classList.add(parentClass);
            }
          }
        }
      } catch(e) {}
    });
  }

  function scan(){
    document.querySelectorAll('img[data-gdy-fallback-src], img[data-gdy-hide-onerror], img[data-gdy-show-onload]').forEach(applyToImg);
  }

  if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', scan);
  } else {
    scan();
  }

  // In case content is injected dynamically (AJAX loadmore), observe body mutations.
  try {
    var obs = new MutationObserver(function(muts){
      for(var i=0;i<muts.length;i++){
        var m = muts[i];
        if(m.addedNodes?.length){
          for(var j=0;j<m.addedNodes.length;j++){
            var n = m.addedNodes[j];
            if(!n || n.nodeType !== 1) continue;
            if(n.tagName === 'IMG') applyToImg(n);
            n.querySelectorAll?.('img[data-gdy-fallback-src], img[data-gdy-hide-onerror], img[data-gdy-show-onload]')?.forEach(applyToImg);
          }
        }
      }
    });
    obs.observe(document.body, { childList: true, subtree: true });
  } catch(e) {}
})();
