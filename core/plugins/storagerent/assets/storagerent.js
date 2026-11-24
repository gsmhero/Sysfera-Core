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
  });
})();
