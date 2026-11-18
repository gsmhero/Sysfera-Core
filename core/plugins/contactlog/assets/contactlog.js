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
  function find(id) { for (var i = 0; i < cards.length; i++) if (cards[i].id === id) return cards[i]; return null; }
  function fmt(ts) { return new Date(ts * 1000).toLocaleString(); }

  /* ---------- theme, columns ---------- */
  function applyTheme(dark) { root.classList.toggle('cl-dark', dark); $('clThemeIcon').textContent = dark ? '☀️' : '🌙'; $('clThemeText').textContent = t(dark ? 'light_mode' : 'dark_mode'); }
  applyTheme(pref('theme') === 'dark');
  $('clTheme').addEventListener('click', function () { var d = !root.classList.contains('cl-dark'); pref('theme', d ? 'dark' : 'light'); applyTheme(d); });
  var cols = pref('cols'); if (cols === '2' || cols === '3' || cols === '4') $('clCols').value = cols;
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
  function btn(cls, label, fn, title) { var b = h('button', { type: 'button', 'class': cls, text: label, onclick: fn }); if (title) { b.title = title; b.setAttribute('aria-label', title); } return b; }
  function cardEl(c) {
    var color = boot.colors[c.status] || '#3b82f6';
    var logs = h('div', { 'class': 'cl-log' }, c.logs.length ? c.logs.map(function (l) { return h('div', { text: '📋 [' + fmt(l.at) + '] ' + t('log_' + l.action) }); }) : [h('i', { text: t('no_logs') })]);
    var badge = h('span', { 'class': 'cl-badge', text: t('st_' + c.status) }); badge.style.cssText = 'background:' + color + '20;color:' + color + ';border:1px solid ' + color + '40';
    function act(action, icon, key) { return btn('', icon + ' ' + t(key), function () { run(this, function () { return call('log', { id: c.id, action: action }).then(function (r) { c.logs = r.logs; render(); }); }); }); }
    return h('div', { 'class': 'cl-card' }, [
      btn('cl-del', '✕', function () { if (confirm(t('confirm_delete'))) run(this, function () { return call('delete', { id: c.id }).then(function () { cards = cards.filter(function (x) { return x.id !== c.id; }); render(); }); }); }, t('confirm_delete')),
      h('div', { 'class': 'cl-head' }, [h('span', { 'class': 'cl-name', text: fullName(c) }), badge]),
      h('div', { 'class': 'cl-info' }, [h('span', { text: '📞 ' + (c.mobile || '—') }), h('span', { text: '✈️ ' + (c.messenger || '—') }), h('span', { text: '✉️ ' + (c.email || '—') })]),
      h('div', { 'class': 'cl-note' }, [h('strong', { text: t('lbl_theme') }), ' ' + (c.theme || '—'), h('br'), h('strong', { text: t('lbl_notes') }), ' ' + (c.notes || '—')]),
      h('div', { 'class': 'cl-act' }, [act('call', '📞', 'btn_call'), act('email', '✉️', 'btn_email'), act('messenger', '💬', 'btn_msg'), act('success', '✅', 'btn_success')]),
      h('div', { 'class': 'cl-next', text: '📅 ' + t('lbl_next') + ' ' + (c.next_call || t('not_set')) }),
      h('div', { 'class': 'cl-created', text: '🕐 ' + t('lbl_created') + ' ' + new Date(c.created_at * 1000).toLocaleDateString() }),
      logs,
      h('div', { 'class': 'cl-foot' }, [
        btn('cl-outline', '🗑 ' + t('btn_clear'), function () { if (confirm(t('confirm_clear'))) run(this, function () { return call('clearlog', { id: c.id }).then(function () { c.logs = []; render(); }); }); }),
        btn('cl-success', '✈ ' + t('btn_report'), function () { sendReport(this, c); }),
        btn('', '✎ ' + t('btn_edit'), function () { openCard(c); })
      ])
    ]);
  }
  /** runs an API action; a failure becomes a toast; the button is locked meanwhile (no double clicks) */
  function run(button, fn) {
    if (button) button.disabled = true;
    return fn().catch(function (e) { toast(e.message, 'bad', 8000); }).then(function () { if (button && button.isConnected) button.disabled = false; });
  }
  function sendReport(button, c) {
    var old = button.textContent; button.textContent = '… ' + t('sending'); button.disabled = true;
    call('report', { id: c.id }).then(function () { toast(t('report_sent'), 'ok'); }, function (e) { toast(t('report_error') + ': ' + e.message, 'bad', 9000); })
      .then(function () { if (button.isConnected) { button.textContent = old; button.disabled = false; } });
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
