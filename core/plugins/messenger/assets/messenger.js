/* Keeps the open chat and the list of chats up to date without reloading the page. */
(function () {
  /* repeats fn (which returns a promise) while the page is visible; asks at once when the person comes back to it */
  function live(fn, delay) {
    var timer = 0, busy = false;
    function plan() { clearTimeout(timer); timer = setTimeout(run, delay()); }
    function run() {
      if (busy) return; if (document.hidden) { plan(); return; } busy = true;
      fn().catch(function () {}).then(function () { busy = false; plan(); });
    }
    document.addEventListener('visibilitychange', function () { if (!document.hidden) run(); });
    window.addEventListener('focus', run);
    plan();
  }

  var list = document.getElementById('msg-chats');
  if (list) live(function () {
    return fetch(list.dataset.live, { credentials: 'same-origin' }).then(function (r) { return r.ok ? r.text() : null; })
      .then(function (h) { if (h !== null) list.innerHTML = h; });
  }, function () { return 6000; });

  var box = document.getElementById('msgs'); if (!box) return;
  var last = parseInt(box.dataset.last || '0', 10), fresh = Date.now(), fails = 0, off = document.getElementById('msg-offline');
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML.replace(/"/g, '&quot;'); }
  function add(m) {
    if (box.querySelector('[data-id="' + parseInt(m.id, 10) + '"]')) return;
    var e = document.getElementById('msg-empty'); if (e) e.remove();
    var d = document.createElement('div'); d.className = 'msg ' + (m.mine ? 'mine' : 'theirs'); d.dataset.id = m.id;
    var h = m.text ? '<p>' + esc(m.text).replace(/\n/g, '<br>') + '</p>' : '';
    if (m.file) {
      var u = box.dataset.file + encodeURIComponent(m.file.id);
      h += m.file.image ? '<a href="' + u + '"><img class="msg-img" src="' + u + '&inline=1" alt="' + esc(m.file.name) + '"></a>' : '<a class="msg-file" href="' + u + '">📎 ' + esc(m.file.name) + '</a>';
    }
    d.innerHTML = h + '<small class="muted">' + esc(m.at) + '</small>'; box.appendChild(d); last = Math.max(last, m.id);
    var img = d.querySelector('img'); if (img) img.addEventListener('load', function () { if (box.scrollHeight - box.scrollTop - box.clientHeight < img.height + 60) box.scrollTop = box.scrollHeight; });
  }
  box.scrollTop = box.scrollHeight;
  // every 3 seconds while the conversation is going, every 10 after two quiet minutes
  live(function () {
    return fetch(box.dataset.poll + '&after=' + last, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
      if (!j.messages) throw new Error('no answer');
      fails = 0; if (off) off.hidden = true;
      if (j.messages.length) { var down = box.scrollTop + box.clientHeight >= box.scrollHeight - 40; j.messages.forEach(add); fresh = Date.now(); if (down) box.scrollTop = box.scrollHeight; }
    }).catch(function () { if (++fails >= 3 && off) off.hidden = false; });
  }, function () { return Date.now() - fresh < 120000 ? 3000 : 10000; });
})();
