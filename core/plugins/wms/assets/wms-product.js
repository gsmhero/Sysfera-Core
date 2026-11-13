(function () {
  document.querySelectorAll('[data-rep]').forEach(function (box) {
    var rows = box.querySelector('.rep-rows'), tpl = box.querySelector('template');
    box.addEventListener('click', function (e) {
      if (e.target.closest('[data-add]')) { rows.appendChild(tpl.content.cloneNode(true)); var i = rows.querySelectorAll('input'); if (i.length) i[i.length - tpl.content.querySelectorAll('input').length].focus(); }
      var d = e.target.closest('[data-del]'); if (d) d.closest('.rep-row').remove();
    });
  });
})();
