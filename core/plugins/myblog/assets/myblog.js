/* My Blog: copy a product embed code */
document.addEventListener('click', function (e) {
  var b = e.target.closest ? e.target.closest('[data-mb-copy]') : null;
  if (!b) return;
  var text = b.getAttribute('data-mb-copy');
  var done = function () { var old = b.textContent; b.textContent = b.getAttribute('data-mb-done'); setTimeout(function () { b.textContent = old; }, 1600); };
  var fallback = function () { window.prompt('', text); };
  if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(text).then(done, fallback); else fallback();
});

/* gallery: slide with the arrows or a swipe; a click opens the picture full screen */
(function () {
  function el(tag, cls, label) { var x = document.createElement(tag); x.className = cls; if (tag === 'button') x.type = 'button'; if (label) x.setAttribute('aria-label', label); return x; }
  document.querySelectorAll('[data-mb-gal]').forEach(function (gal) {
    var track = gal.querySelector('.mb-track'), links = [].slice.call(gal.querySelectorAll('[data-mb-zoom]')), count = gal.querySelector('[data-mb-count]'), n = links.length;
    function at() { return Math.round(track.scrollLeft / (track.clientWidth || 1)); }
    function show(i, smooth) { i = (i + n) % n; track.scrollTo({ left: i * track.clientWidth, behavior: smooth ? 'smooth' : 'auto' }); if (count) count.textContent = (i + 1) + ' / ' + n; }
    track.addEventListener('scroll', function () { if (count) count.textContent = (at() + 1) + ' / ' + n; }, { passive: true });
    var prev = gal.querySelector('[data-mb-prev]'), next = gal.querySelector('[data-mb-next]');
    if (prev) { prev.addEventListener('click', function () { show(at() - 1, true); }); next.addEventListener('click', function () { show(at() + 1, true); }); }
    links.forEach(function (a, i) { a.addEventListener('click', function (e) { if (e.ctrlKey || e.metaKey || e.shiftKey) return; e.preventDefault(); open(i); }); });
    function open(i) {
      var box = el('div', 'mb-light'), img = el('img', 'mb-light-img'), num = el('span', 'mb-count'), close = el('button', 'mb-x', gal.dataset.close), back = el('button', 'mb-arrow prev', gal.dataset.prev), fwd = el('button', 'mb-arrow next', gal.dataset.next), x0 = null;
      box.setAttribute('role', 'dialog'); box.setAttribute('aria-modal', 'true'); close.textContent = '×'; back.textContent = '‹'; fwd.textContent = '›';
      function put(k) { i = (k + n) % n; img.src = links[i].href; img.alt = links[i].querySelector('img').alt; num.textContent = (i + 1) + ' / ' + n; }
      function shut() { document.removeEventListener('keydown', key); box.remove(); document.documentElement.style.overflow = ''; show(i, false); links[i].focus({ preventScroll: true }); }
      function key(e) {
        if (e.key === 'Escape') shut(); else if (e.key === 'ArrowLeft') put(i - 1); else if (e.key === 'ArrowRight') put(i + 1);
        else if (e.key === 'Tab') { var b = n > 1 ? [close, back, fwd] : [close]; var k = b.indexOf(document.activeElement); e.preventDefault(); b[(k + (e.shiftKey ? b.length - 1 : 1)) % b.length].focus(); }
      }
      close.addEventListener('click', shut); back.addEventListener('click', function () { put(i - 1); }); fwd.addEventListener('click', function () { put(i + 1); });
      box.addEventListener('click', function (e) { if (e.target === box) shut(); });
      box.addEventListener('touchstart', function (e) { x0 = e.touches.length === 1 ? e.touches[0].clientX : null; }, { passive: true });
      box.addEventListener('touchend', function (e) { if (x0 === null || n < 2) return; var d = e.changedTouches[0].clientX - x0; x0 = null; if (Math.abs(d) > 50) put(i + (d < 0 ? 1 : -1)); }, { passive: true });
      box.appendChild(img); box.appendChild(close); if (n > 1) { box.appendChild(back); box.appendChild(fwd); box.appendChild(num); }
      document.body.appendChild(box); document.documentElement.style.overflow = 'hidden'; document.addEventListener('keydown', key); put(i); close.focus();
    }
  });
})();

/* stop an upload the server would refuse, before the typed text is lost */
document.addEventListener('submit', function (e) {
  var f = e.target; if (!f.hasAttribute || !f.hasAttribute('data-mb-upload')) return;
  var total = 0, count = 0, big = false;
  f.querySelectorAll('input[type=file]').forEach(function (i) { [].forEach.call(i.files || [], function (x) { count++; total += x.size; if (x.size > +f.dataset.file) big = true; }); });
  if (big || (+f.dataset.total && total > +f.dataset.total - 262144) || (+f.dataset.count && count > +f.dataset.count)) { e.preventDefault(); window.alert(f.dataset.big); }
});
