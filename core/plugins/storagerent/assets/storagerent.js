/* Storage Rent: the Yandex map (public map of offers and the point picker of the listing form), plus two small form helpers.
 * The map key arrives from the server in data-key (set in Settings); nothing is stored here. Without a key the server-rendered list stays visible. */
(function () {
  'use strict';
  function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function json(el, name, dflt) { try { return JSON.parse(el.getAttribute(name) || ''); } catch (e) { return dflt; } }
  function safeUrl(u) { return /^(\/|https?:\/\/)/i.test(u || '') ? u : '#'; }

  var loading = null;
  function loadApi(key, lang) {   // the Yandex JS API v2.1, loaded once
    if (window.ymaps) return Promise.resolve(window.ymaps);
    if (loading) return loading;
    loading = new Promise(function (resolve, reject) {
      var s = document.createElement('script');
      s.src = 'https://api-maps.yandex.ru/2.1/?apikey=' + encodeURIComponent(key) + '&lang=' + encodeURIComponent(lang || 'ru_RU');
      s.onload = function () { window.ymaps.ready(function () { resolve(window.ymaps); }); };
      s.onerror = function () { loading = null; reject(new Error('ymaps')); };
      document.head.appendChild(s);
    });
    return loading;
  }

  function balloon(p, labels, cta) {
    var h = '<div class="sr-pop"><b>' + esc(p.name) + '</b><div>' + esc(p.addr) + '</div><div>' + esc(labels.area || '') + ': ' + esc(p.area) + ' m² · ' + esc(p.price) + '</div>' +
      '<div><a href="' + esc(safeUrl(p.url)) + '"' + (p.ext ? ' target="_blank" rel="noopener"' : '') + '>' + esc(labels.open || '') + '</a></div>';
    if (cta) h += '<div class="sr-pop-cta">' + esc(labels.cta || '') + ' <a class="btn primary sm" href="' + esc(safeUrl(cta)) + '">' + esc(labels.register || '') + '</a></div>';
    return h + '</div>';
  }

  function initMap(box) {
    var key = box.getAttribute('data-key') || ''; if (!key) return;
    var canvas = box.querySelector('.sr-map-canvas'), fallback = box.querySelector('.sr-map-fallback'), picker = box.getAttribute('data-picker') === '1';
    var pts = json(box, 'data-points', []), labels = json(box, 'data-labels', {}), cta = box.getAttribute('data-cta') || '';
    if (!picker && !pts.length) return;
    loadApi(key, box.getAttribute('data-lang')).then(function (ymaps) {
      canvas.hidden = false; canvas.style.height = picker ? '280px' : '440px';
      var latI = document.getElementById('srLat'), lonI = document.getElementById('srLon');
      var c0 = pts.length ? [pts[0].lat, pts[0].lon] : [55.751, 37.618];
      if (picker && latI && lonI && parseFloat(latI.value) && parseFloat(lonI.value)) c0 = [parseFloat(latI.value), parseFloat(lonI.value)];
      var map = new ymaps.Map(canvas, { center: c0, zoom: pts.length > 1 ? 4 : (picker && c0[0] !== 55.751 ? 14 : 9), controls: ['zoomControl', 'fullscreenControl'] });
      if (fallback) fallback.hidden = true;
      if (picker) {
        var mark = null;
        function put(co) { if (!mark) { mark = new ymaps.Placemark(co, {}, { draggable: true }); map.geoObjects.add(mark); mark.events.add('dragend', function () { set(mark.geometry.getCoordinates()); }); } else mark.geometry.setCoordinates(co); }
        function set(co) { latI.value = co[0].toFixed(6); lonI.value = co[1].toFixed(6); }
        if (latI.value && lonI.value) put(c0);
        map.events.add('click', function (e) { var co = e.get('coords'); put(co); set(co); });
        return;
      }
      var coll = new ymaps.GeoObjectCollection();
      pts.forEach(function (p) { coll.add(new ymaps.Placemark([p.lat, p.lon], { balloonContent: balloon(p, labels, cta), hintContent: p.name }, { preset: 'islands#blueDotIcon' })); });
      map.geoObjects.add(coll);
      if (pts.length > 1) map.setBounds(coll.getBounds(), { checkZoomRange: true, zoomMargin: 40 });
      else map.setCenter([pts[0].lat, pts[0].lon], 14);
    }).catch(function () { if (canvas) canvas.hidden = true; if (fallback) fallback.hidden = false; });
  }

  function cloneLine(box, extra) {   // contract units / manual invoice lines: copy the first line with empty values
    var first = box.querySelector('.sr-line'); if (!first) return;
    var n = first.cloneNode(true);
    n.querySelectorAll('input[type=text],input:not([type]),input[type=number]').forEach(function (i) { i.value = i.name === 'unit_share[]' ? '100' : ''; });
    n.querySelectorAll('select').forEach(function (s) { s.selectedIndex = 0; });
    n.querySelectorAll('input[type=checkbox]').forEach(function (c) { c.checked = false; if (extra) c.name = 'vatable[' + box.querySelectorAll('.sr-line').length + ']'; });
    box.appendChild(n);
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.sr-map').forEach(initMap);
    var add = document.getElementById('srAddLine'), lines = document.getElementById('srLines');
    if (add && lines) add.addEventListener('click', function () { cloneLine(lines, false); });
    var inv = document.getElementById('srInvLines');
    if (inv) { var b = document.createElement('button'); b.type = 'button'; b.className = 'btn sm'; b.textContent = '+'; b.addEventListener('click', function () { cloneLine(inv, true); }); inv.parentNode.insertBefore(b, inv.nextSibling); }
    initLightbox();
    initContact();
  });

  /* "Contact with Lessor": the message dialog (a native <dialog>; without script support the Contacts block still shows the details). */
  function initContact() {
    document.querySelectorAll('[data-sr-contact]').forEach(function (b) {
      var d = document.getElementById(b.getAttribute('data-sr-contact')); if (!d) return;
      b.addEventListener('click', function () { if (typeof d.showModal === 'function') d.showModal(); else d.setAttribute('open', ''); var t = d.querySelector('textarea'); if (t) { t.focus(); t.setSelectionRange(t.value.length, t.value.length); } });
      d.querySelectorAll('[data-sr-close]').forEach(function (x) { x.addEventListener('click', function () { if (typeof d.close === 'function') d.close(); else d.removeAttribute('open'); }); });
      d.addEventListener('click', function (e) { if (e.target === d && typeof d.close === 'function') d.close(); });
    });
  }

  /* Lightbox for the photos of a listing: click a photo, arrows / keys / swipe to slide, Esc or a click outside to close. */
  function initLightbox() {
    var gal = document.querySelector('.sr-photos'); if (!gal) return;
    var imgs = Array.prototype.slice.call(gal.querySelectorAll('img')); if (!imgs.length) return;
    var box = null, pic, cnt, cur = 0, tx = null, last = null;
    function lab(k, d) { return gal.getAttribute('data-' + k) || d; }
    function el(tag, cls, txt) { var e = document.createElement(tag); if (cls) e.className = cls; if (txt) e.textContent = txt; return e; }
    function show(i) { cur = (i + imgs.length) % imgs.length; pic.src = imgs[cur].currentSrc || imgs[cur].src; cnt.textContent = (cur + 1) + ' / ' + imgs.length; }
    function close() { if (!box) return; document.removeEventListener('keydown', key); document.body.style.overflow = ''; box.parentNode.removeChild(box); box = null; if (last && last.focus) last.focus(); }
    function key(e) { if (e.key === 'Escape') close(); else if (e.key === 'ArrowLeft') show(cur - 1); else if (e.key === 'ArrowRight') show(cur + 1); }
    function open(i) {
      last = document.activeElement; box = el('div', 'sr-lb'); box.setAttribute('role', 'dialog'); box.setAttribute('aria-modal', 'true');
      pic = el('img', 'sr-lb-img'); pic.alt = ''; cnt = el('div', 'sr-lb-cnt');
      var x = el('button', 'sr-lb-x', '×'); x.type = 'button'; x.setAttribute('aria-label', lab('close', 'Close'));
      var p = el('button', 'sr-lb-a sr-lb-p', '‹'); p.type = 'button'; p.setAttribute('aria-label', lab('prev', 'Previous'));
      var n = el('button', 'sr-lb-a sr-lb-n', '›'); n.type = 'button'; n.setAttribute('aria-label', lab('next', 'Next'));
      x.addEventListener('click', close);
      p.addEventListener('click', function (e) { e.stopPropagation(); show(cur - 1); });
      n.addEventListener('click', function (e) { e.stopPropagation(); show(cur + 1); });
      box.addEventListener('click', function (e) { if (e.target === box) close(); });
      box.addEventListener('touchstart', function (e) { tx = e.touches[0].clientX; }, { passive: true });
      box.addEventListener('touchend', function (e) { if (tx === null) return; var d = e.changedTouches[0].clientX - tx; tx = null; if (Math.abs(d) > 40) show(cur + (d < 0 ? 1 : -1)); }, { passive: true });
      box.appendChild(pic); box.appendChild(cnt); box.appendChild(x);
      if (imgs.length > 1) { box.appendChild(p); box.appendChild(n); } else cnt.style.display = 'none';
      document.body.appendChild(box); document.body.style.overflow = 'hidden'; document.addEventListener('keydown', key); show(i); x.focus();
    }
    imgs.forEach(function (im, i) {
      im.tabIndex = 0; im.setAttribute('role', 'button');
      im.addEventListener('click', function () { open(i); });
      im.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(i); } });
    });
  }
})();
