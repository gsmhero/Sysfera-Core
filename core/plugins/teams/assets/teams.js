(function () {
  document.querySelectorAll('[data-toggle]').forEach(function (b) { b.addEventListener('click', function () { var t = document.querySelector(b.dataset.toggle); if (t) t.hidden = !t.hidden; }); });
  var priv = document.querySelector('[data-priv]');
  if (priv) priv.addEventListener('change', function () { var b = document.querySelector('.tm-priv'); if (b) b.hidden = !priv.checked; });

  /* ---------- task board drag & drop ---------- */
  var board = document.querySelector('.tm-board');
  if (board && board.dataset.can === '1') {
    var drag = null;
    board.addEventListener('dragstart', function (e) { var c = e.target.closest('.tm-task'); if (!c) return; drag = c; c.classList.add('drag'); e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', c.dataset.id); } catch (x) {} });
    board.addEventListener('dragend', function () { if (drag) drag.classList.remove('drag'); board.querySelectorAll('.over').forEach(function (c) { c.classList.remove('over'); }); drag = null; });
    board.querySelectorAll('.tm-col').forEach(function (col) {
      col.addEventListener('dragover', function (e) { if (drag) { e.preventDefault(); col.classList.add('over'); } });
      col.addEventListener('dragleave', function () { col.classList.remove('over'); });
      col.addEventListener('drop', function (e) {
        e.preventDefault(); col.classList.remove('over'); if (!drag) return;
        var card = drag, from = card.parentNode; col.querySelector('.tm-cards').prepend(card);
        fetch(board.dataset.move, { method: 'POST', credentials: 'same-origin', body: new URLSearchParams({ _t: board.dataset.token, id: card.dataset.id, col: col.dataset.col }) })
          .then(function (r) { return r.json(); }).then(function (j) { if (!j.ok) from.prepend(card); else location.reload(); }).catch(function () { from.prepend(card); });
      });
    });
  }

  /* ---------- chat ---------- */
  var cfgEl = document.getElementById('tmcfg'); if (!cfgEl) return;
  var cfg = JSON.parse(cfgEl.textContent), T = cfg.i18n, box = document.getElementById('tmmsgs'), pinsBox = document.getElementById('tmpins');
  var form = document.getElementById('tmform'), body = document.getElementById('tmbody'), fileIn = document.getElementById('tmfile'), errEl = document.getElementById('tmerr'), replyBar = document.getElementById('tmreply'), sug = document.getElementById('tmsug');
  var byId = {}, lastId = 0, replyTo = null, polling = false;

  function el(tag, cls, text) { var n = document.createElement(tag); if (cls) n.className = cls; if (text != null) n.textContent = text; return n; }
  function post(url, data) { var b = data instanceof FormData ? data : new URLSearchParams(data); if (b instanceof FormData) b.append('_t', cfg.token); else b.append('_t', cfg.token); return fetch(url, { method: 'POST', credentials: 'same-origin', body: b }).then(function (r) { return r.json(); }); }
  function size(n) { return n > 1048576 ? (n / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(n / 1024)) + ' KB'; }

  /* text with links and @mentions as DOM nodes (never innerHTML) */
  function richText(parent, text) {
    var names = cfg.people.map(function (p) { return p.name; }).sort(function (a, b) { return b.length - a.length; });
    text.split('\n').forEach(function (line, li) {
      if (li) parent.appendChild(document.createElement('br'));
      var re = /(https?:\/\/[^\s<>"]+)|@([^\s@]+(?: [^\s@]+)*)/g, last = 0, m;
      while ((m = re.exec(line))) {
        if (m.index > last) parent.appendChild(document.createTextNode(line.slice(last, m.index)));
        if (m[1]) { var a = el('a', null, m[1]); a.href = m[1]; a.target = '_blank'; a.rel = 'noopener noreferrer'; parent.appendChild(a); last = re.lastIndex; }
        else {
          var rest = m[0].slice(1), hit = names.find(function (n) { return rest.toLowerCase().indexOf(n.toLowerCase()) === 0; });
          if (hit) { var s = el('span', 'tm-men' + (cfg.people.some(function (p) { return p.id === cfg.me && p.name === hit; }) ? ' me' : ''), '@' + rest.slice(0, hit.length)); parent.appendChild(s); last = m.index + 1 + hit.length; re.lastIndex = last; }
          else { parent.appendChild(document.createTextNode(m[0].charAt(0))); last = m.index + 1; re.lastIndex = last; }
        }
      }
      if (last < line.length) parent.appendChild(document.createTextNode(line.slice(last)));
    });
  }

  function render(m) {
    var row = el('div', 'tm-m' + (m.mine ? ' mine' : '') + (m.id > cfg.lastRead && !m.mine ? ' fresh' : '') + (m.pinned ? ' pinned' : '')); row.dataset.id = m.id;
    row.appendChild(el('i', 'tm-av', (m.name || '?').charAt(0).toUpperCase()));
    var c = el('div', 'tm-mc'), h = el('div', 'tm-mh'); h.appendChild(el('b', null, m.name)); h.appendChild(el('small', 'muted', ' ' + m.at + (m.edited ? ' · ' + T.edited : '') + (m.pinned ? ' · ' + T.pinned : ''))); c.appendChild(h);
    if (m.reply) { var q = el('div', 'tm-quote'); q.appendChild(el('b', null, m.reply.name)); q.appendChild(document.createTextNode(' ' + m.reply.text)); c.appendChild(q); }
    if (m.deleted) c.appendChild(el('div', 'tm-del muted', T.deleted));
    else {
      if (m.body) { var b = el('div', 'tm-body'); richText(b, m.body); c.appendChild(b); }
      if (m.file) {
        var fw = el('div', 'tm-file');
        if (m.file.img) { var a = el('a', 'tm-imga'); a.href = cfg.file + m.file.id + '?inline=1'; a.setAttribute('data-lb', '1'); var im = document.createElement('img'); im.src = cfg.file + m.file.id + '?inline=1'; im.alt = m.file.name; im.loading = 'lazy'; a.appendChild(im); fw.appendChild(a); }
        var l = el('a', 'tm-dl', '⬇ ' + T.download + ' ' + m.file.name + ' (' + size(m.file.size) + ')'); l.href = cfg.file + m.file.id; l.setAttribute('download', m.file.name); fw.appendChild(l); c.appendChild(fw);
      }
      var rx = el('div', 'tm-rx');
      m.react.forEach(function (r) { var bt = el('button', 'tm-r' + (r.me ? ' me' : '')); bt.type = 'button'; if (EMO[r.e]) { bt.innerHTML = svg(EMO[r.e]); bt.appendChild(document.createTextNode(' ' + r.n)); } else bt.textContent = r.e + ' ' + r.n; bt._fn = function () { act(m.id, 'react', { emoji: r.e }); }; rx.appendChild(bt); });
      var tools = el('span', 'tm-tools');
      if (cfg.canPost) tools.appendChild(tool('reply', T.reply, function () { setReply(m); }));
      if (cfg.canReact) cfg.emoji.slice(0, 3).forEach(function (e) { tools.appendChild(tool(EMO[e] || 'smile', e, function () { act(m.id, 'react', { emoji: e }); })); });
      if (m.mine) tools.appendChild(tool('edit', T.edit, function () { editMsg(m, row); }));
      if (cfg.canPin) tools.appendChild(tool('pin', m.pinned ? T.unpin : T.pin, function () { act(m.id, 'pin', {}); }));
      if (m.can_del) tools.appendChild(tool('trash', T.delete, function () { if (confirm(T.confirm_delete)) act(m.id, 'delete', {}); }));
      rx.appendChild(tools); c.appendChild(rx);
    }
    row.appendChild(c); return row;
  }
  /* lightbox for the images of the channel: arrows / swipe / Esc */
  (function () {
    var lb, lim, lcnt, list = [], at = 0;
    function build() {
      lb = el('div', 'lbx'); lb.hidden = true;
      lb.innerHTML = '<button type="button" class="lb-x" aria-label="Close">×</button><button type="button" class="lb-p" aria-label="Previous">‹</button><img alt=""><button type="button" class="lb-n" aria-label="Next">›</button><div class="lb-c"></div><a class="lb-d" download></a>';
      document.body.appendChild(lb); lim = lb.querySelector('img'); lcnt = lb.querySelector('.lb-c');
      lb.querySelector('.lb-x').onclick = close; lb.querySelector('.lb-p').onclick = function () { go(-1); }; lb.querySelector('.lb-n').onclick = function () { go(1); };
      lb.addEventListener('click', function (e) { if (e.target === lb) close(); });
      var x0 = null; lb.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; }, { passive: true });
      lb.addEventListener('touchend', function (e) { if (x0 === null) return; var d = e.changedTouches[0].clientX - x0; x0 = null; if (Math.abs(d) > 40) go(d < 0 ? 1 : -1); });
    }
    function showImg() {
      var a = list[at], im = a.querySelector('img'), d = lb.querySelector('.lb-d'), dl = a.parentNode.querySelector('.tm-dl');
      lim.src = a.href; lim.alt = im ? im.alt : ''; lcnt.textContent = (at + 1) + ' / ' + list.length; lb.classList.toggle('one', list.length < 2);
      if (dl) { d.href = dl.href; d.textContent = '⬇ ' + T.download + ' ' + (im ? im.alt : ''); d.hidden = false; } else d.hidden = true;
    }
    function go(d) { if (list.length < 2) return; at = (at + d + list.length) % list.length; showImg(); }
    function close() { lb.hidden = true; lim.removeAttribute('src'); document.documentElement.classList.remove('lb-open'); }
    document.addEventListener('click', function (e) {
      var a = e.target.closest ? e.target.closest('a[data-lb]') : null; if (!a || e.ctrlKey || e.metaKey || e.shiftKey) return; e.preventDefault();
      if (!lb) build(); list = [].slice.call(box.querySelectorAll('a[data-lb]')); at = list.indexOf(a); if (at < 0) return;
      lb.hidden = false; document.documentElement.classList.add('lb-open'); showImg();
    });
    document.addEventListener('keydown', function (e) { if (!lb || lb.hidden) return; if (e.key === 'Escape') close(); else if (e.key === 'ArrowLeft') go(-1); else if (e.key === 'ArrowRight') go(1); });
  })();
  /* one-colour line icons (follow the text colour) */
  var ICO = { reply: 'M9 14 4 9l5-5M4 9h10a6 6 0 0 1 6 6v3', thumb: 'M7 10v11M7 10l4-7a2 2 0 0 1 2 2v4h6a2 2 0 0 1 2 2l-1.5 7A2 2 0 0 1 17.5 21H7', heart: 'M12 21s-7-4.5-9.5-9A5.5 5.5 0 0 1 12 6a5.5 5.5 0 0 1 9.5 6C19 16.5 12 21 12 21z', smile: 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zM8 14s1.5 2 4 2 4-2 4-2M9 9h.01M15 9h.01', edit: 'M4 20h4L19 9l-4-4L4 16v4z', pin: 'M12 17v5M8 3h8l-1 7 3 3v2H6v-2l3-3-1-7z', trash: 'M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3' };
  var EMO = { '\uD83D\uDC4D': 'thumb', '\u2764\uFE0F': 'heart', '\u2764': 'heart', '\uD83D\uDE04': 'smile' };
  function svg(n) { return '<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="' + ICO[n] + '"/></svg>'; }
  function tool(name, title, fn) { var b = el('button', 'tm-tool'); b.type = 'button'; b.title = title; b.setAttribute('aria-label', title); b.innerHTML = svg(name); b._fn = fn; return b; }
  box.addEventListener('click', function (e) { var b = e.target.closest ? e.target.closest('.tm-tool, .tm-r') : null; if (b && b._fn) { e.preventDefault(); b._fn(); } });
  function editMsg(m, row) {
    var bd = row.querySelector('.tm-body') || row.querySelector('.tm-mc'), ta = el('textarea'); ta.value = m.body || ''; ta.rows = 3;
    var ok = el('button', 'btn sm primary', T.save), no = el('button', 'btn sm', T.cancel), w = el('div', 'tm-edit'); ok.type = no.type = 'button';
    w.appendChild(ta); w.appendChild(ok); w.appendChild(no); bd.replaceWith(w); ta.focus();
    no.onclick = function () { show(m); }; ok.onclick = function () { act(m.id, 'edit', { body: ta.value }); };
  }
  function show(m) {
    byId[m.id] = m; var old = box.querySelector('[data-id="' + m.id + '"]'), n = render(m);
    if (old) old.replaceWith(n); else box.appendChild(n);
    if (m.id > lastId) lastId = m.id; pins();
  }
  function pins() {
    var list = Object.keys(byId).map(function (k) { return byId[k]; }).filter(function (m) { return m.pinned && !m.deleted; });
    pinsBox.hidden = !list.length; pinsBox.textContent = '';
    list.slice(-3).forEach(function (m) { var d = el('div', null, '📌 ' + m.name + ': ' + (m.body || '').slice(0, 120)); pinsBox.appendChild(d); });
  }
  function act(id, what, data) { post(cfg.msg + id + '/' + what, data).then(function (j) { if (j.ok && j.message) show(j.message); else alert(j.error || T.sending_failed); }).catch(function () { alert(T.sending_failed); }); }
  function atBottom() { return box.scrollHeight - box.scrollTop - box.clientHeight < 80; }

  cfg.msgs.forEach(show); box.scrollTop = box.scrollHeight;
  var firstFresh = box.querySelector('.fresh'); if (firstFresh) firstFresh.scrollIntoView({ block: 'center' });

  function poll() {
    if (polling || document.hidden) return; polling = true;
    fetch(cfg.poll + '?after=' + lastId, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
      if (!j.ok) return; var down = atBottom();
      (j.updates || []).forEach(function (m) { var o = byId[m.id]; if (o && JSON.stringify(o) !== JSON.stringify(m)) show(m); });
      (j.new || []).forEach(show); if ((j.new || []).length && down) box.scrollTop = box.scrollHeight;
    }).catch(function () {}).then(function () { polling = false; });
  }
  setInterval(poll, 6000); document.addEventListener('visibilitychange', poll);

  function setReply(m) {
    replyTo = m.id; replyBar.hidden = false; replyBar.textContent = '';
    replyBar.appendChild(el('span', null, T.replying + ' ' + m.name + ': ' + (m.body || '').slice(0, 80)));
    var x = el('button', 'tm-x', '×'); x.type = 'button'; x.onclick = clearReply; replyBar.appendChild(x); body.focus();
  }
  function clearReply() { replyTo = null; replyBar.hidden = true; replyBar.textContent = ''; }

  if (form) {
    var fname = document.getElementById('tmfname');
    if (fileIn) fileIn.onchange = function () { var f = fileIn.files[0]; if (f && f.size > cfg.maxFile) { errEl.textContent = T.too_big; fileIn.value = ''; fname.textContent = ''; } else { errEl.textContent = ''; fname.textContent = f ? f.name : ''; } };
    function send() {
      var txt = body.value.trim(), f = fileIn && fileIn.files[0]; if (!txt && !f) return;
      var fd = new FormData(); fd.append('body', txt); if (replyTo) fd.append('reply_to', replyTo); if (f) fd.append('file', f);
      var btn = document.getElementById('tmsend'); btn.disabled = true; errEl.textContent = '';
      post(cfg.send, fd).then(function (j) {
        if (j.ok && j.message) { body.value = ''; if (fileIn) { fileIn.value = ''; fname.textContent = ''; } clearReply(); show(j.message); box.scrollTop = box.scrollHeight; } else errEl.textContent = j.error || T.sending_failed;
      }).catch(function () { errEl.textContent = T.sending_failed; }).then(function () { btn.disabled = false; });
    }
    form.addEventListener('submit', function (e) { e.preventDefault(); send(); });
    body.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey && !e.isComposing && sug.hidden) { e.preventDefault(); send(); } });

    /* @mention suggestions */
    body.addEventListener('input', function () {
      var pos = body.selectionStart, m = /(?:^|\s)@([^\s@]*)$/.exec(body.value.slice(0, pos)); sug.textContent = '';
      if (!m) { sug.hidden = true; return; }
      var q = m[1].toLowerCase(), list = cfg.people.filter(function (p) { return p.name.toLowerCase().indexOf(q) !== -1; }).slice(0, 6);
      if (!list.length) { sug.hidden = true; return; }
      list.forEach(function (p) { var a = el('a', null, p.name); a.onmousedown = function (e) { e.preventDefault(); var before = body.value.slice(0, pos).replace(/@[^\s@]*$/, '@' + p.name + ' '); body.value = before + body.value.slice(pos); body.focus(); body.selectionStart = body.selectionEnd = before.length; sug.hidden = true; }; sug.appendChild(a); });
      sug.hidden = false;
    });
    body.addEventListener('blur', function () { setTimeout(function () { sug.hidden = true; }, 150); });
  }
})();
