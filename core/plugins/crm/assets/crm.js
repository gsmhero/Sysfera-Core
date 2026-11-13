(function () {
  /* show/hide blocks: <button data-toggle="#id"> */
  document.querySelectorAll('[data-toggle]').forEach(function (b) {
    b.addEventListener('click', function () { var t = document.querySelector(b.dataset.toggle); if (t) t.hidden = !t.hidden; });
  });
  /* kanban drag & drop */
  var board = document.querySelector('.crm-board');
  if (board && board.dataset.can === '1') {
    var dragged = null;
    board.addEventListener('dragstart', function (e) {
      var c = e.target.closest('.crm-card'); if (!c) return;
      dragged = c; c.classList.add('drag'); e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', c.dataset.id); } catch (x) {}
    });
    board.addEventListener('dragend', function () { if (dragged) dragged.classList.remove('drag'); board.querySelectorAll('.crm-col.over').forEach(function (c) { c.classList.remove('over'); }); dragged = null; });
    board.querySelectorAll('.crm-col').forEach(function (col) {
      col.addEventListener('dragover', function (e) { if (dragged) { e.preventDefault(); col.classList.add('over'); } });
      col.addEventListener('dragleave', function () { col.classList.remove('over'); });
      col.addEventListener('drop', function (e) {
        e.preventDefault(); col.classList.remove('over'); if (!dragged) return;
        var card = dragged, from = card.parentNode, reason = '';
        if (col.dataset.kind === 'lost') { reason = prompt(board.dataset.lostPrompt || 'Reason?', '') || ''; }
        col.querySelector('.crm-cards').prepend(card);
        var body = new URLSearchParams({ _t: board.dataset.token, id: card.dataset.id, stage: col.dataset.stage, reason: reason });
        fetch(board.dataset.move, { method: 'POST', body: body, credentials: 'same-origin' }).then(function (r) { return r.json(); })
          .then(function (j) { if (!j.ok) from.prepend(card); else location.reload(); }).catch(function () { from.prepend(card); });
      });
    });
  }

  /* deal page: stage buttons ask for a reason when the stage is "lost" */
  document.querySelectorAll('.crm-stagebar form[data-kind="lost"]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      var bar = f.closest('.crm-stagebar'), r = prompt(bar.dataset.lostPrompt || 'Reason?', '');
      if (r === null) { e.preventDefault(); return; }
      f.querySelector('input[name=reason]').value = r;
    });
  });

  /* contact picker on the deal form */
  document.querySelectorAll('.crm-pick').forEach(function (box) {
    var id = box.querySelector('input[name=contact_id]'), inp = box.querySelector('input[name=new_contact]'), sug = box.querySelector('.crm-sug'), t;
    inp.addEventListener('input', function () {
      id.value = ''; clearTimeout(t); var q = inp.value.trim(); if (q.length < 2) { sug.innerHTML = ''; return; }
      t = setTimeout(function () {
        fetch(inp.dataset.find + '?q=' + encodeURIComponent(q), { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (rows) {
          sug.innerHTML = '';
          rows.forEach(function (c) {
            var a = document.createElement('a'); a.textContent = c.name + (c.company ? ' · ' + c.company : '') + (c.email ? ' · ' + c.email : '');
            a.addEventListener('mousedown', function (e) { e.preventDefault(); id.value = c.id; inp.value = c.name; sug.innerHTML = ''; });
            sug.appendChild(a);
          });
        }).catch(function () {});
      }, 220);
    });
    inp.addEventListener('blur', function () { setTimeout(function () { sug.innerHTML = ''; }, 150); });
  });
})();
