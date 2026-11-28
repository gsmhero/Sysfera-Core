/* Sysfera: tiny helpers. Forms/buttons with data-confirm ask before submitting. */
document.addEventListener('submit', function (e) {
  var f = e.target, sub = e.submitter, msg = (sub && sub.dataset.confirm) || f.dataset.confirm;
  if (msg && !window.confirm(msg)) e.preventDefault();
}, true);

/* Dates as DD/MM/YYYY and times as 24 h in every component, whatever the browser's locale: date / time / datetime-local fields get a text field with a mask
   (the original field stays in the form as a hidden input with the ISO value, so servers still receive YYYY-MM-DD / HH:MM). */
(function () {
  var D = 'dd/mm/yyyy', T = 'hh:mm', bad = { date: 'Use DD/MM/YYYY', time: 'Use 24-hour HH:MM', datetime: 'Use DD/MM/YYYY HH:MM' };
  function p2(n) { return (n < 10 ? '0' : '') + n; }
  function validDate(d, m, y) { var x = new Date(y, m - 1, d); return y >= 1 && x.getFullYear() === y && x.getMonth() === m - 1 && x.getDate() === d; }
  function maskDate(s) { s = s.replace(/\D/g, '').slice(0, 8); var o = s.slice(0, 2); if (s.length > 2) o += '/' + s.slice(2, 4); if (s.length > 4) o += '/' + s.slice(4); return o; }
  function maskTime(s) { s = s.replace(/\D/g, '').slice(0, 4); return s.length > 2 ? s.slice(0, 2) + ':' + s.slice(2) : s; }
  function parseDate(t) { var m = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(t); return m && validDate(+m[1], +m[2], +m[3]) ? m[3] + '-' + m[2] + '-' + m[1] : null; }
  function parseTime(t) { var m = /^(\d{2}):(\d{2})$/.exec(t); return m && +m[1] < 24 && +m[2] < 60 ? t : null; }
  function isoToDate(v) { var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(v || ''); return m ? m[3] + '/' + m[2] + '/' + m[1] : ''; }
  function listFor() {
    var l = document.getElementById('sf-times'); if (l) return l;
    l = document.createElement('datalist'); l.id = 'sf-times'; for (var h = 0; h < 24; h++) for (var m = 0; m < 60; m += 30) { var o = document.createElement('option'); o.value = p2(h) + ':' + p2(m); l.appendChild(o); }
    document.body.appendChild(l); return l;
  }
  function upgrade(inp) {
    var kind = inp.type === 'datetime-local' ? 'datetime' : inp.type; if (!/^(date|time|datetime)$/.test(kind) || inp.dataset.sfDone || inp.classList.contains('dtp-n')) return;
    inp.dataset.sfDone = '1';
    var wrap = document.createElement('span'); wrap.className = 'dtp dtp-' + kind;
    var t = document.createElement('input'); t.type = 'text'; t.className = 'dtp-t'; t.autocomplete = 'off'; t.inputMode = 'numeric';
    t.placeholder = kind === 'date' ? D : kind === 'time' ? T : D + ' ' + T; t.maxLength = kind === 'date' ? 10 : kind === 'time' ? 5 : 16;
    if (inp.required) t.required = true; if (inp.disabled) t.disabled = true; if (inp.id) { t.id = inp.id; inp.removeAttribute('id'); }
    if (inp.getAttribute('aria-label')) t.setAttribute('aria-label', inp.getAttribute('aria-label'));
    if (kind !== 'date') t.setAttribute('list', listFor().id);
    var v = inp.value || '';
    t.value = kind === 'date' ? isoToDate(v) : kind === 'time' ? v.slice(0, 5) : (isoToDate(v) + (v.length > 10 ? ' ' + v.slice(11, 16) : '')).trim();
    inp.type = 'hidden'; inp.parentNode.insertBefore(wrap, inp); wrap.appendChild(t);
    var pick = null;
    if (kind !== 'time') {
      var b = document.createElement('button'); b.type = 'button'; b.className = 'dtp-b'; b.tabIndex = -1; b.setAttribute('aria-label', 'Calendar'); b.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>';
      pick = document.createElement('input'); pick.type = 'date'; pick.className = 'dtp-n'; pick.dataset.sfDone = '1'; pick.tabIndex = -1; pick.setAttribute('aria-hidden', 'true');
      wrap.appendChild(b); wrap.appendChild(pick);
      b.addEventListener('click', function () { var d = parseDate(t.value.slice(0, 10)); pick.value = d || ''; try { pick.showPicker(); } catch (e) { pick.focus(); pick.click(); } });
      pick.addEventListener('change', function () { if (!pick.value) return; var tm = kind === 'datetime' ? t.value.slice(10).trim() : ''; t.value = isoToDate(pick.value) + (kind === 'datetime' ? ' ' + (tm || '09:00') : ''); sync(); });
    }
    function sync() {
      var s = t.value.trim(), iso = '', ok = true;
      if (s === '') { iso = ''; ok = !t.required; }
      else if (kind === 'date') { var d = parseDate(s); ok = !!d; iso = d || ''; }
      else if (kind === 'time') { var tm = parseTime(s); ok = !!tm; iso = tm || ''; }
      else { var m = /^(\S+)(?:\s+(\S+))?$/.exec(s), d2 = m && parseDate(m[1]), t2 = m && m[2] ? parseTime(m[2]) : (m ? '00:00' : null); ok = !!(d2 && t2); iso = ok ? d2 + 'T' + t2 : ''; }
      var changed = inp.value !== iso; inp.value = iso; t.setCustomValidity(ok ? '' : (s === '' ? 'Required' : bad[kind]));
      if (changed && ready) inp.dispatchEvent(new Event('change', { bubbles: true }));
    }
    t.addEventListener('input', function () {
      var s = t.value;
      if (kind === 'date') t.value = maskDate(s);
      else if (kind === 'time') t.value = maskTime(s);
      else { var dg = s.replace(/\D/g, '').slice(0, 12), tp = dg.length > 8 ? maskTime(dg.slice(8)) : ''; t.value = maskDate(dg.slice(0, 8)) + (tp ? ' ' + tp : (/\s$/.test(s) && dg.length === 8 ? ' ' : '')); }
      sync();
    });
    var ready = false; t.addEventListener('blur', sync); sync(); ready = true;
  }
  function scan(root) { var l = (root || document).querySelectorAll ? (root || document).querySelectorAll('input[type=date],input[type=time],input[type=datetime-local]') : []; for (var i = 0; i < l.length; i++) upgrade(l[i]); }
  function start() {
    scan(document);
    new MutationObserver(function (ms) { ms.forEach(function (m) { m.addedNodes.forEach(function (n) { if (n.nodeType === 1) { if (n.matches && n.matches('input[type=date],input[type=time],input[type=datetime-local]')) upgrade(n); else scan(n); } }); }); }).observe(document.body, { childList: true, subtree: true });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();

/* Live menu counters: a sidebar item with data-live is asked for {"unread": n}; its badge and the tab title follow. */
(function () {
  var title = document.title, counts = [];
  Array.prototype.forEach.call(document.querySelectorAll('a[data-live]'), function (a, i) {
    var timer = 0, busy = false;
    function show(n) {
      var b = a.querySelector('.nbadge');
      if (n > 0) { if (!b) { b = document.createElement('b'); b.className = 'nbadge'; a.appendChild(b); } b.textContent = n > 99 ? '99+' : n; }
      else if (b) b.remove();
      counts[i] = n; var all = counts.reduce(function (s, x) { return s + (x || 0); }, 0);
      document.title = (all > 0 ? '(' + all + ') ' : '') + title;
    }
    function plan() { clearTimeout(timer); timer = setTimeout(ask, document.hidden ? 60000 : 15000); }
    function ask() {
      if (busy) return; busy = true;
      fetch(a.dataset.live, { credentials: 'same-origin' }).then(function (r) { return r.ok ? r.json() : null; })
        .then(function (j) { if (j && typeof j.unread === 'number') show(j.unread); }).catch(function () {}).then(function () { busy = false; plan(); });
    }
    document.addEventListener('visibilitychange', function () { if (!document.hidden) ask(); else plan(); });
    ask();
  });
})();
