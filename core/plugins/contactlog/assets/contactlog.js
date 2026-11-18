/* Sysfera Contact Log: the page script. Data comes from the session JSON API (see openapi.yaml); all text is inserted with textContent, never as HTML. */
(function () {
  'use strict';
  var boot; try { boot = JSON.parse(document.getElementById('clBoot').textContent); } catch (e) { return; }
  var L = boot.L, cards = boot.cards, copyEmail = boot.copyEmail || '';
  var root = document.getElementById('clApp');
  function t(k, v) { var s = L[k] !== undefined ? L[k] : k; if (v) for (var a in v) s = s.split('{' + a + '}').join(v[a]); return s; }
  function $(id) { return document.getElementById(id); }
  function pref(k, v) { try { if (v === undefined) return localStorage.getItem('cl_' + k); localStorage.setItem('cl_' + k, v); } catch (e) { return null; } return null; }
  function h(tag, attrs, kids) {
    var el = document.createElement(tag);
    for (var k in attrs || {}) { if (k === 'text') el.textContent = attrs[k]; else if (k === 'class') el.className = attrs[k]; else if (k.slice(0, 2) === 'on') el.addEventListener(k.slice(2), attrs[k]); else el.setAttribute(k, attrs[k]); }
    (kids || []).forEach(function (c) { if (c !== null && c !== undefined) el.appendChild(typeof c === 'string' ? document.createTextNode(c) : c); });
    return el;
  }
  function toast(msg, kind, ms) {
    var el = h('div', { 'class': 'cl-toast ' + (kind || 'ok'), text: msg, role: 'status' }); $('clToasts').appendChild(el);
    requestAnimationFrame(function () { el.classList.add('show'); });
    setTimeout(function () { el.classList.remove('show'); setTimeout(function () { el.remove(); }, 300); }, ms || 4000);
  }
  /** POST to the API; resolves to the parsed answer, or throws an Error with a message the user can read */
  function call(op, data) {
    var body = new URLSearchParams(); body.set('_t', boot.token); for (var k in data || {}) body.set(k, data[k]);
    return fetch(boot.api + op, { method: 'POST', body: body, credentials: 'same-origin', headers: { 'Accept': 'application/json' } }).then(function (r) {
      return r.text().then(function (txt) {
        var j; try { j = JSON.parse(txt); } catch (e) { throw new Error(t(r.status === 400 ? 'err_bad_request' : 'err_server')); }
        if (!j.ok) throw new Error(j.message || t('err_server'));
        return j;
      });
    }, function () { throw new Error(t('err_network')); });
  }
  /* monochrome line icons (static markup, currentColor): they follow the text colour of the platform theme */
  var ICO = {
    plus: '<path d="M12 5v14M5 12h14"/>', x: '<path d="M18 6 6 18M6 6l12 12"/>', search: '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
    user: '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>', sliders: '<path d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6"/>',
    phone: '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/>',
    send: '<path d="m22 2-11 11M22 2l-7 20-4-9-9-4z"/>', mail: '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/>', msg: '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
    check: '<circle cx="12" cy="12" r="10"/><path d="m8 12 3 3 5-6"/>', cal: '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>', clock: '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
    trash: '<path d="M3 6h18M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6M10 11v6M14 11v6M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>', edit: '<path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>'
  };
  function svg(n) { var d = new DOMParser().parseFromString('<svg xmlns="' + SVGNS + '" ' + SVGA + '>' + (ICO[n] || '') + '</svg>', 'image/svg+xml').documentElement; return document.importNode(d, true); }
  var SVGNS = 'http:' + '//www.w3.org/2000/svg'; /* the XML namespace name of SVG, not an address that is ever fetched */
  var SVGA = 'viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"';
  function ico(n) { return h('i', { 'class': 'cl-i' }, [svg(n)]); }
  Array.prototype.forEach.call(document.querySelectorAll('[data-ico]'), function (el) { el.className = 'cl-i'; el.appendChild(svg(el.getAttribute('data-ico'))); });
  var TONE = { busy: 'bad', no_answer: 'warn', sent_email: 'info', dropped: 'mute', ignored: 'mute', call_later: 'info', wrong_number: 'bad', application: 'ok', actual: 'ok', not_relevant: 'mute' };
  function find(id) { for (var i = 0; i < cards.length; i++) if (cards[i].id === id) return cards[i]; return null; }
  function fmt(ts) { return new Date(ts * 1000).toLocaleString(); }

  /* ---------- columns ---------- */
  var cols = pref('cols'); $('clCols').value = (cols === '2' || cols === '3' || cols === '4') ? cols : '3'; /* three cards per row unless the user chose otherwise */
  $('clCols').addEventListener('change', function () { pref('cols', $('clCols').value); render(); });
  $('clSearch').addEventListener('input', render); $('clOrder').addEventListener('change', render);

  /* ---------- cards ---------- */
  function fullName(c) { return [c.name, c.second_name, c.surname].filter(Boolean).join(' '); }
  function render() {
    var q = $('clSearch').value.toLowerCase(), box = $('clCards'); box.setAttribute('data-cols', $('clCols').value); box.textContent = '';
    var list = cards.filter(function (c) { return fullName(c).toLowerCase().indexOf(q) !== -1; });
    if ($('clOrder').value === 'status') list.sort(function (a, b) { return t('st_' + a.status).localeCompare(t('st_' + b.status)); }); else list.sort(function (a, b) { return b.created_at - a.created_at || b.id - a.id; });
    if (!list.length) { box.appendChild(h('div', { 'class': 'cl-empty', text: t('no_cards') })); return; }
    list.forEach(function (c) { box.appendChild(cardEl(c)); });
  }
  function btn(cls, label, fn, title, icon) { var b = h('button', { type: 'button', 'class': 'btn ' + cls, onclick: fn }); if (icon) b.appendChild(ico(icon)); if (label) b.appendChild(h('span', { text: label })); if (title) { b.title = title; b.setAttribute('aria-label', title); } return b; }
  function line(icon, text) { return h('div', { 'class': 'cl-line' }, [ico(icon), h('span', { text: text || '—' })]); }
  function cardEl(c) {
    var logs = h('div', { 'class': 'cl-log' }, c.logs.length ? c.logs.map(function (l) { return h('div', { 'class': 'cl-logrow' }, [h('time', { text: fmt(l.at) }), h('span', { text: t('log_' + l.action) })]); }) : [h('i', { text: t('no_logs') })]);
    var badge = h('span', { 'class': 'badge ' + (TONE[c.status] || 'mute'), text: t('st_' + c.status) });
    function act(action, icon, key) { return btn('cl-actbtn', t(key), function () { run(this, function () { return call('log', { id: c.id, action: action }).then(function (r) { c.logs = r.logs; render(); }); }); }, t(key), icon); }
    return h('div', { 'class': 'card cl-card' }, [
      btn('cl-del', '', function () { if (confirm(t('confirm_delete'))) run(this, function () { return call('delete', { id: c.id }).then(function () { cards = cards.filter(function (x) { return x.id !== c.id; }); render(); }); }); }, t('confirm_delete'), 'x'),
      h('div', { 'class': 'cl-head' }, [h('h3', { 'class': 'cl-name', text: fullName(c) }), badge]),
      h('div', { 'class': 'cl-info' }, [line('phone', c.mobile), line('send', c.messenger), line('mail', c.email)]),
      h('div', { 'class': 'cl-note' }, [h('div', {}, [h('b', { text: t('lbl_theme') }), ' ' + (c.theme || '—')]), h('div', {}, [h('b', { text: t('lbl_notes') }), ' ' + (c.notes || '—')])]),
      h('div', { 'class': 'cl-act' }, [act('call', 'phone', 'btn_call'), act('email', 'mail', 'btn_email'), act('messenger', 'msg', 'btn_msg'), act('success', 'check', 'btn_success')]),
      h('div', { 'class': 'cl-meta' }, [h('span', {}, [ico('cal'), ' ' + t('lbl_next') + ' ' + (c.next_call || t('not_set'))]), h('span', {}, [ico('clock'), ' ' + t('lbl_created') + ' ' + new Date(c.created_at * 1000).toLocaleDateString()])]),
      logs,
      h('div', { 'class': 'cl-foot' }, [
        btn('', t('btn_clear'), function () { if (confirm(t('confirm_clear'))) run(this, function () { return call('clearlog', { id: c.id }).then(function () { c.logs = []; render(); }); }); }, '', 'trash'),
        btn('', t('btn_report'), function () { sendReport(this, c); }, '', 'send'),
        btn('primary', t('btn_edit'), function () { openCard(c); }, '', 'edit')
      ])
    ]);
  }
  /** runs an API action; a failure becomes a toast; the button is locked meanwhile (no double clicks) */
  function run(button, fn) {
    if (button) button.disabled = true;
    return fn().catch(function (e) { toast(e.message, 'bad', 8000); }).then(function () { if (button && button.isConnected) button.disabled = false; });
  }
  function sendReport(button, c) {
    var old = button.lastChild.textContent; button.lastChild.textContent = t('sending') + '…'; button.disabled = true;
    call('report', { id: c.id }).then(function () { toast(t('report_sent'), 'ok'); }, function (e) { toast(t('report_error') + ': ' + e.message, 'bad', 9000); })
      .then(function () { if (button.isConnected) { button.lastChild.textContent = old; button.disabled = false; } });
  }

  /* ---------- card form ---------- */
  var F = { name: 'clName', second_name: 'clSecond', surname: 'clSurname', mobile: 'clMobile', messenger: 'clMessenger', email: 'clEmail', status: 'clStatus', theme: 'clTheme2', notes: 'clNotes', next_call: 'clNext' };
  function showErr(id, msg) { var el = $(id); el.textContent = msg || ''; el.style.display = msg ? 'block' : 'none'; }
  function openCard(c) {
    if ($('clNext').type !== 'date') $('clNext').placeholder = 'YYYY-MM-DD / DD.MM.YYYY';   // a browser without a date picker: the server accepts both
    showErr('clCardErr', ''); $('clId').value = c ? c.id : ''; $('clModalTitle').textContent = t(c ? 'modal_edit' : 'modal_add');
    for (var k in F) $(F[k]).value = c ? c[k] : (k === 'status' ? boot.statuses[0] : '');
    $('clCardModal').classList.remove('cl-hide'); $('clName').focus();
  }
  $('clAdd').addEventListener('click', function () { openCard(null); });
  $('clCardCancel').addEventListener('click', function () { $('clCardModal').classList.add('cl-hide'); });
  $('clSave').addEventListener('click', function () {
    var data = { id: $('clId').value }; for (var k in F) data[k] = $(F[k]).value;
    if (!data.name.trim()) { showErr('clCardErr', t('err_name_required')); return; }
    var b = this; b.disabled = true;
    call('save', data).then(function (r) {
      var i = cards.findIndex(function (x) { return x.id === r.card.id; }); if (i === -1) cards.push(r.card); else cards[i] = r.card;
      $('clCardModal').classList.add('cl-hide'); render();
    }).catch(function (e) { showErr('clCardErr', e.message); }).then(function () { b.disabled = false; });
  });

  /* ---------- profile (copy address) ---------- */
  $('clProfile').addEventListener('click', function () { showErr('clProfileErr', ''); $('clCopy').value = copyEmail; $('clProfileModal').classList.remove('cl-hide'); });
  $('clProfileClose').addEventListener('click', function () { $('clProfileModal').classList.add('cl-hide'); });
  $('clProfileSave').addEventListener('click', function () {
    var b = this; b.disabled = true;
    call('profile', { copy_email: $('clCopy').value }).then(function (r) { copyEmail = r.copy_email; toast(t('profile_saved'), 'ok'); $('clProfileModal').classList.add('cl-hide'); })
      .catch(function (e) { showErr('clProfileErr', e.message); }).then(function () { b.disabled = false; });
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') ['clCardModal', 'clProfileModal'].forEach(function (id) { $(id).classList.add('cl-hide'); }); });

  render();
})();
