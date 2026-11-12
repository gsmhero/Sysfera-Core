(function () {
  var box = document.getElementById('msgs'); if (!box) return;
  var last = parseInt(box.dataset.last || '0', 10), busy = false;
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
  function add(m) {
    var e = document.getElementById('msg-empty'); if (e) e.remove();
    var d = document.createElement('div'); d.className = 'msg ' + (m.mine ? 'mine' : 'theirs'); d.dataset.id = m.id;
    var h = m.text ? '<p>' + esc(m.text).replace(/\n/g, '<br>') + '</p>' : '';
    if (m.file) {
      var u = box.dataset.file + encodeURIComponent(m.file.id);
      h += m.file.image ? '<a href="' + u + '"><img class="msg-img" src="' + u + '&inline=1" alt=""></a>' : '<a class="msg-file" href="' + u + '">📎 ' + esc(m.file.name) + '</a>';
    }
    d.innerHTML = h + '<small class="muted">' + esc(m.at) + '</small>'; box.appendChild(d); last = Math.max(last, m.id);
  }
  function tick() {
    if (busy || document.hidden) return; busy = true;
    fetch(box.dataset.poll + '&after=' + last, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
      if (j.messages && j.messages.length) { var down = box.scrollTop + box.clientHeight >= box.scrollHeight - 40; j.messages.forEach(add); if (down) box.scrollTop = box.scrollHeight; }
    }).catch(function () {}).then(function () { busy = false; });
  }
  box.scrollTop = box.scrollHeight; setInterval(tick, 8000);
})();
