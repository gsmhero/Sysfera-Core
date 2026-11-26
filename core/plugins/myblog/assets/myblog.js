/* My Blog: copy a product embed code */
document.addEventListener('click', function (e) {
  var b = e.target.closest ? e.target.closest('[data-mb-copy]') : null;
  if (!b) return;
  var text = b.getAttribute('data-mb-copy');
  var done = function () { var old = b.textContent; b.textContent = b.getAttribute('data-mb-done'); setTimeout(function () { b.textContent = old; }, 1600); };
  var fallback = function () { window.prompt('', text); };
  if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(text).then(done, fallback); else fallback();
});
