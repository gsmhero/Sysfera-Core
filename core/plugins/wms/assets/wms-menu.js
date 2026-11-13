/* Sysfera WMS — dropdown menus ("New document"); loaded on every WMS page. */
(function () {
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-menu]');
    document.querySelectorAll('.menu.open').forEach(function (m) { if (!b || m !== b.parentNode) m.classList.remove('open'); });
    if (b) b.parentNode.classList.toggle('open');
  });
})();
