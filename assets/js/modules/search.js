/* Smart search suggestions for the header search box */
(function(){
  function qs(sel, root){ return (root||document).querySelector(sel); }
  const form = qs('.header-search');
  const input = form ? qs('input[name="q"]', form) : null;
  if(!input) return;

  const box = document.createElement('div');
  box.className = 'gdy-suggest-box';
  box.style.display = 'none';
  form.style.position = 'relative';
  form.appendChild(box);

  let timer = null;
  let lastQ = '';

  function clearBox(){ while (box.firstChild) box.removeChild(box.firstChild); }
  function hide(){ box.style.display='none'; clearBox(); }
  function show(){ box.style.display='block'; }

  
  function render(res){
    if(!res || !res.ok){ hide(); return; }
    const items = res.suggestions || [];
    const corrected = res.corrected;
    if(!items.length && !corrected){ hide(); return; }

    clearBox();

    if(corrected){
      const d = document.createElement('div');
      d.className = 'gdy-suggest-correct';
      d.appendChild(document.createTextNode('هل تقصد: '));
      const a = document.createElement('a');
      a.href = '/search?q=' + encodeURIComponent(corrected);
      a.textContent = String(corrected);
      d.appendChild(a);
      d.appendChild(document.createTextNode('؟'));
      box.appendChild(d);
    }

    const list = document.createElement('div');
    list.className = 'gdy-suggest-list';

    items.slice(0,10).forEach(it => {
      const title = it && it.title ? String(it.title) : '';
      const type = it && it.type ? String(it.type) : '';
      const url = it && it.url ? String(it.url) : '#';

      let href = '#';
      try{
        const u = new URL(url, window.location.origin);
        if(u.origin === window.location.origin) href = u.pathname + u.search + u.hash;
      }catch(e){}

      const a = document.createElement('a');
      a.className = 'gdy-suggest-item';
      a.href = href;

      const t = document.createElement('span');
      t.className = 't';
      t.textContent = title;
      a.appendChild(t);

      const k = document.createElement('span');
      k.className = 'k';
      k.textContent = type;
      a.appendChild(k);

      list.appendChild(a);
    });

    box.appendChild(list);
    show();
  }


  function escapeHtml(s){
    return String(s||'').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }

  async function fetchSuggest(q){
    try{
      const r = await fetch(`/api/search/suggest?q=${encodeURIComponent(q)}`, {credentials:'same-origin'});
      const j = await r.json();
      render(j);
    }catch(e){
      hide();
    }
  }

  input.addEventListener('input', () => {
    const q = (input.value||'').trim();
    lastQ = q;
    if(timer) clearTimeout(timer);
    if(q.length < 2){ hide(); return; }
    timer = setTimeout(() => {
      if(lastQ === q) fetchSuggest(q);
    }, 180);
  });

  document.addEventListener('click', (e) => {
    if(!form.contains(e.target)) hide();
  });
})();
