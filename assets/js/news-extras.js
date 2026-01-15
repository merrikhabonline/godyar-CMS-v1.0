/* Godyar News Extras: reactions, polls, ask author, TTS (client), download via API */
(function () {
  function qs(sel, root){ return (root||document).querySelector(sel); }
  function qsa(sel, root){ return Array.from((root||document).querySelectorAll(sel)); }

  const BASE = (window.GDY_BASE || '');
  function api(path){
    if(!BASE) return path;
    return BASE.replace(/\/$/,'') + path;
  }

  function safeJson(res){
    return res.text().then(txt => {
      const t = (txt || '').trim();
      if(!t) return {};
      try{ return JSON.parse(t); }
      catch(e){
        const err = new Error('Non-JSON response');
        err.status = res.status;
        err.responseText = txt;
        throw err;
      }
    });
  }

  function postForm(url, data){
    const params = new URLSearchParams();
    Object.keys(data||{}).forEach(k => params.append(k, (data[k] ?? '')));
    return fetch(url, {
      method:'POST',
      body: params.toString(),
      credentials:'same-origin',
      headers: {
        'Accept':'application/json',
        'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'
      }
    }).then(safeJson);
  }
  function postJson(url, data){
    return fetch(url, { method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json'}, body: JSON.stringify(data||{}), credentials:'same-origin' }).then(safeJson);
  }
  function getJson(url){
    return fetch(url, { credentials:'same-origin', headers:{'Accept':'application/json'} }).then(safeJson);
  }

  async function initReactions(){
    const wrap = qs('#gdy-reactions');
    if(!wrap) return;
    const newsId = wrap.getAttribute('data-news-id');
    if(!newsId) return;

    // Emoji-only reactions (no text labels inside the buttons).
    // Keep aria-label/title for accessibility and hover tooltips.
    const reactions = {
      like:     { label: 'إعجاب',  emoji: '👍' },
      useful:   { label: 'مفيد',   emoji: '✅' },
      disagree: { label: 'مختلف',  emoji: '🤔' },
      angry:    { label: 'غاضب',   emoji: '😡' },
      funny:    { label: 'مضحك',   emoji: '😂' }
    };

    function render(state){
      const counts = (state && state.counts) || {};
      const mine = new Set((state && state.mine) || []);
      clearChildren(wrap);
      const row = document.createElement('div');
      row.className = 'gdy-react-row';
      Object.keys(reactions).forEach(key => {
        const btn = document.createElement('button');
        btn.type='button';
        btn.className = 'gdy-react-btn' + (mine.has(key) ? ' active' : '');
        btn.setAttribute('data-reaction', key);
        btn.title = reactions[key].label;
        btn.setAttribute('aria-label', reactions[key].label);
        clearChildren(btn);
        const emo = document.createElement('span');
        emo.className = 'emo';
        emo.setAttribute('aria-hidden','true');
        emo.textContent = String(reactions[key].emoji || '');
        btn.appendChild(emo);
        const cnt = document.createElement('span');
        cnt.className = 'cnt';
        cnt.textContent = String(counts[key] || 0);
        btn.appendChild(cnt);
        btn.addEventListener('click', async () => {
          btn.disabled = true;
          try{
            const res = await postForm(api('/api/news/react'), {news_id: newsId, reaction: key});
            if(res && res.ok){
              render({counts: res.counts, mine: res.mine});
            }
          }catch(e){}
          btn.disabled = false;
        });
        row.appendChild(btn);
      });
      wrap.appendChild(row);
    }

    try{
      const res = await getJson(api(`/api/news/reactions?news_id=${encodeURIComponent(newsId)}`));
      if(res && res.ok) render(res);
    }catch(e){}
  }

  async function initPoll(){
    const el = qs('#gdy-poll');
    if(!el) return;
    const newsId = el.getAttribute('data-news-id');
    if(!newsId) return;
    function renderPoll(payload){
      clearChildren(el);

      const poll = payload && payload.poll ? payload.poll : null;
      const counts = payload && payload.counts ? payload.counts : {};
      const votedFor = payload ? payload.votedFor : null;

      if(!poll){
        el.style.display='none';
        return;
      }
      el.style.display='block';

      const wrap = document.createElement('div');
      wrap.className = 'gdy-poll';

      const q = document.createElement('div');
      q.className = 'gdy-poll-q';
      q.textContent = String(poll.question || '');
      wrap.appendChild(q);

      const optsWrap = document.createElement('div');
      optsWrap.className = 'gdy-poll-opts';

      const options = Array.isArray(poll.options) ? poll.options : [];
      options.forEach((opt) => {
        const oid = opt && (opt.id ?? opt.value ?? opt.option_id);
        const label = opt && (opt.label ?? opt.text ?? opt.title ?? '');
        const pct = opt && (opt.pct ?? opt.percent ?? 0);
        const votes = opt && (opt.votes ?? counts[oid] ?? 0);

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'gdy-poll-opt';
        btn.dataset.option = String(oid ?? '');
        if(votedFor && String(votedFor) === String(oid)) btn.classList.add('is-voted');
        if(votedFor) btn.disabled = true;

        const lbl = document.createElement('span');
        lbl.className = 'lbl';
        lbl.textContent = String(label);
        btn.appendChild(lbl);

        const bar = document.createElement('span');
        bar.className = 'bar';
        btn.appendChild(bar);

        const fill = document.createElement('span');
        fill.className = 'fill';
        fill.style.width = Math.max(0, Math.min(100, Number(pct)||0)) + '%';
        bar.appendChild(fill);

        const meta = document.createElement('span');
        meta.className = 'meta';
        meta.textContent = (votedFor ? (String((Number(pct)||0).toFixed(0)) + '% · ') : '') + String(votes);
        btn.appendChild(meta);

        optsWrap.appendChild(btn);
      });

      wrap.appendChild(optsWrap);

      if(!votedFor){
        const hint = document.createElement('div');
        hint.className = 'gdy-poll-hint';
        hint.textContent = 'اختر خيارًا للتصويت';
        wrap.appendChild(hint);
      }

      el.appendChild(wrap);

      // bind vote handlers
      qsa('.gdy-poll-opt', el).forEach(function(btn){
        btn.addEventListener('click', function(){
          const opt = btn.dataset.option || '';
          // Use the shared API helper and ensure the correct news id is sent.
          postJson(api('/api/news-poll/vote'), { news_id: newsId, option: opt })
            .then(renderPoll)
            .catch(()=>{});
        });
      });
    }

    try{

      const res = await getJson(api(`/api/news/poll?news_id=${encodeURIComponent(newsId)}`));
      if(res && res.ok) renderPoll(res);
    }catch(e){}
  }

  async function initQuestions(){
    const box = qs('#gdy-qa');
    if(!box) return;
    const newsId = box.getAttribute('data-news-id');
    if(!newsId) return;

    const listEl = qs('#gdy-qa-list');
    const form = qs('#gdy-ask-form');
    const msg = qs('#gdy-ask-msg');

    async function load(){
      if(!listEl) return;
      clearChildren(listEl);
      const m = document.createElement('div');
      m.className = 'text-muted small';
      m.textContent = 'جارٍ التحميل…';
      listEl.appendChild(m);
      try{
        const res = await getJson(api(`/api/news/questions?news_id=${encodeURIComponent(newsId)}`));
        if(!res || res.ok === false){
          clearChildren(listEl);
          const m = document.createElement('div');
          m.className = 'text-muted small';
          m.textContent = 'تعذر تحميل الأسئلة.';
          listEl.appendChild(m);
          return;
        }
        const items = (res.items) || [];
        if(!items.length){
          clearChildren(listEl);
          const m = document.createElement('div');
          m.className = 'text-muted small';
          m.textContent = 'لا توجد أسئلة منشورة بعد.';
          listEl.appendChild(m);
          return;
        }
        clearChildren(listEl);
        const frag = document.createDocumentFragment();
        items.forEach((it) => {
          const card = document.createElement('div');
          card.className = 'gdy-qa-item';
          const q = document.createElement('div');
          q.className = 'gdy-qa-q';
          q.textContent = String(it.question || '');
          card.appendChild(q);
          if (it.answer) {
            const a = document.createElement('div');
            a.className = 'gdy-qa-a';
            a.textContent = String(it.answer);
            card.appendChild(a);
          }
          if (it.created_at) {
            const d = document.createElement('div');
            d.className = 'gdy-qa-date small text-muted';
            d.textContent = String(it.created_at);
            card.appendChild(d);
          }
          frag.appendChild(card);
        });
        listEl.appendChild(frag);
      }catch(e){
        clearChildren(listEl);
          const m = document.createElement('div');
          m.className = 'text-muted small';
          m.textContent = 'تعذر تحميل الأسئلة.';
          listEl.appendChild(m);
      }
    }

    if(form){
      form.addEventListener('submit', async (ev) => {
        ev.preventDefault();
        if(msg) msg.textContent='';
        const name = (qs('[name="name"]', form)?.value||'').trim();
        const email = (qs('[name="email"]', form)?.value||'').trim();
        const question = (qs('[name="question"]', form)?.value||'').trim();
        if(!question){
          if(msg) msg.textContent='اكتب سؤالك أولاً.';
          return;
        }
        try{
          const res = await postForm(api('/api/news/ask'), {news_id: newsId, name, email, question});
          if(res && res.ok){
            form.reset();
            if(msg) msg.textContent = res.message || 'تم الإرسال.';
            await load();
          }else{
            if(msg) msg.textContent = (res && res.error) ? res.error : 'تعذر الإرسال.';
          }
        }catch(e){
          if(msg) {
            const status = (e && e.status) ? (' (HTTP ' + e.status + ')') : '';
            msg.textContent = 'تعذر الإرسال.' + status;
          }
        }
      });
    }

    await load();
  }


  function initTts(){
  const playBtn = document.getElementById('gdy-tts-play');
  const stopBtn = document.getElementById('gdy-tts-stop');
  const rateEl  = document.getElementById('gdy-tts-rate');
  const langEl  = document.getElementById('gdy-tts-lang');
  const statusEl= document.getElementById('gdy-tts-status');

  if(!playBtn || !stopBtn) return;
  if(!('speechSynthesis' in window) || !('SpeechSynthesisUtterance' in window)){
    playBtn.disabled = true;
    stopBtn.disabled = true;
    return;
  }

  let queue = [];
  let idx = 0;
  let isPaused = false;
  let isSpeaking = false;

  const mergeSpelledArabic = (t) => {
    // يحوّل: "ا ل س و د ا ن" => "السودان"
    // نكرر عدة مرات لتجميع الكلمات الطويلة
    for(let k=0;k<6;k++){
      t = t.replace(/([ء-ي])\s+(?=[ء-ي])/g, '$1');
    }
    return t;
  };

  const normalizeText = (t) => {
    t = (t||'').replace(/\u00A0/g,' ').replace(/\s+/g,' ').trim();
    t = mergeSpelledArabic(t);
    // إزالة الرموز المتكررة التي تربك TTS
    t = t.replace(/[•·•]+/g,' ').replace(/\s+/g,' ').trim();
    return t;
  };

  const chunkText = (t) => {
    // تقسيم مريح: فقرات ثم جمل ثم مقاطع (حد أقصى ~180 حرف)
    const out = [];
    const paras = t.split(/\n+/).map(x=>x.trim()).filter(Boolean);

    const pushChunk = (s) => {
      s = s.trim();
      if(!s) return;
      const maxLen = 180;
      if(s.length <= maxLen){ out.push(s); return; }
      // قص ذكي على المسافات
      let cur = '';
      s.split(' ').forEach(w=>{
        if((cur + ' ' + w).trim().length > maxLen){
          if(cur.trim()) out.push(cur.trim());
          cur = w;
        }else{
          cur = (cur + ' ' + w).trim();
        }
      });
      if(cur.trim()) out.push(cur.trim());
    };

    paras.forEach(p=>{
      const parts = p.split(/(?<=[\.\!\؟\?])\s+/);
      parts.forEach(pushChunk);
    });

    return out;
  };

  const getLang = () => {
    const v = (langEl && langEl.value) ? langEl.value : (document.documentElement.lang || 'ar');
    // تحويل قيم بسيطة إلى قيم مناسبة للـ Speech API
    if(v === 'ar') return 'ar-SA';
    if(v === 'en') return 'en-US';
    return v;
  };

  const updateStatus = () => {
    if(!statusEl) return;
    if(!queue.length) { statusEl.textContent = ''; return; }
    statusEl.textContent = `${idx+1}/${queue.length}`;
  };

  const speakNext = () => {
    if(idx >= queue.length){
      isSpeaking = false;
      isPaused = false;
      playBtn.classList.remove('is-playing');
      setPlayButton(playBtn, 'play');
      updateStatus();
      return;
    }

    const u = new SpeechSynthesisUtterance(queue[idx]);
    u.lang = getLang();
    u.rate = Math.max(0.6, Math.min(1.4, parseFloat(rateEl?.value || '1') || 1));

    u.onend = () => {
      if(isPaused) return;
      idx += 1;
      updateStatus();
      speakNext();
    };
    u.onerror = () => {
      idx += 1;
      updateStatus();
      speakNext();
    };

    isSpeaking = true;
    window.speechSynthesis.speak(u);
  };

  const stopAll = () => {
    window.speechSynthesis.cancel();
    queue = [];
    idx = 0;
    isPaused = false;
    isSpeaking = false;
    playBtn.classList.remove('is-playing');
    setPlayButton(playBtn, 'play');
    updateStatus();
  };

  playBtn.addEventListener('click', function(){
    // Toggle: Play / Pause / Resume
    if(isSpeaking && !isPaused){
      isPaused = true;
      window.speechSynthesis.pause();
      setPlayButton(playBtn, 'resume');
      return;
    }
    if(isSpeaking && isPaused){
      isPaused = false;
      window.speechSynthesis.resume();
      setPlayButton(playBtn, 'pause');
      return;
    }

    const raw = normalizeText(extractReadableText());
    if(!raw){
      alert('لا يوجد نص صالح للقراءة.');
      return;
    }

    queue = chunkText(raw);
    idx = 0;
    isPaused = false;

    playBtn.classList.add('is-playing');
    setPlayButton(playBtn, 'pause');
    updateStatus();

    window.speechSynthesis.cancel();
    speakNext();
  });

  stopBtn.addEventListener('click', stopAll);

  if(rateEl){
    rateEl.addEventListener('change', function(){
      // لا نغير أثناء التشغيل حتى لا تتقطع القراءة بشكل مزعج
    });
  }

  // إيقاف TTS عند تغيير الصفحة/المسار
  window.addEventListener('beforeunload', stopAll);
}



  function escapeHtml(s){
    return String(s||'').replace(/[&<>"']/g, function(c){
      return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]);
    });
  }

  document.addEventListener('DOMContentLoaded', function(){
    initReactions();
    initPoll();
    initQuestions();
    initTts();
  });
})();
