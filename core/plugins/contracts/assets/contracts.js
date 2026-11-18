/* Contracts: add a step / an own step to a counteroffer, move a step up or down, confirm dangerous buttons. No dependencies. */
(function () {
  function renumber(box) {
    var rows = box.querySelectorAll('[data-row]');
    rows.forEach(function (r, i) {
      r.querySelectorAll('[name]').forEach(function (el) { el.name = el.name.replace(/\[(?:\d+|__)\]/, '[' + i + ']'); });
    });
  }
  document.addEventListener('click', function (ev) {
    var t = ev.target.closest('[data-add], [data-up], [data-down], [data-remove]');
    if (!t) return;
    var box = t.closest('[data-rows]') || document.querySelector(t.getAttribute('data-add'));
    if (t.hasAttribute('data-add')) {
      ev.preventDefault();
      var tpl = document.querySelector(t.getAttribute('data-add') + ' template');
      if (!tpl) return;
      var holder = document.querySelector(t.getAttribute('data-add'));
      var n = holder.querySelectorAll('[data-row]').length;
      var html = tpl.innerHTML.replace(/\[__\]/g, '[' + n + ']');
      holder.querySelector('[data-list]').insertAdjacentHTML('beforeend', html);
      return;
    }
    var row = t.closest('[data-row]'); if (!row || !box) return; ev.preventDefault();
    if (t.hasAttribute('data-remove')) row.remove();
    else if (t.hasAttribute('data-up') && row.previousElementSibling) row.parentNode.insertBefore(row, row.previousElementSibling);
    else if (t.hasAttribute('data-down') && row.nextElementSibling) row.parentNode.insertBefore(row.nextElementSibling, row);
    renumber(box);
  });
  document.addEventListener('submit', function (ev) {
    var f = ev.target, m = f.getAttribute && f.getAttribute('data-confirm');
    if (m && !window.confirm(m)) ev.preventDefault();
  });
})();
