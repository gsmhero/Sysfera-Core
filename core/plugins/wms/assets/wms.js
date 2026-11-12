/* Sysfera WMS — document editor (product search, barcode scan, line table) and small UI helpers. */
(function () {
  'use strict';

  /* dropdown menu "New document" */
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-menu]');
    document.querySelectorAll('.menu.open').forEach(function (m) { if (!b || m !== b.parentNode) m.classList.remove('open'); });
    if (b) b.parentNode.classList.toggle('open');
  });

  var C = window.WMS;
  if (!C || !C.draft) return;
  var rows = document.getElementById('rows'), whSel = document.getElementById('wh'), wh2Sel = document.getElementById('wh2');
  var search = document.getElementById('psearch'), results = document.getElementById('presults'), table = rows.closest('table');
  var lines = (C.lines || []).map(function (l) { return Object.assign({}, l); });
  var timer = null;

  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function num(v) { var n = parseFloat(String(v).replace(/\s/g, '').replace(',', '.')); return isNaN(n) ? 0 : n; }
  function fmt(n) { return (Math.round(n * 100) / 100).toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ₽'; }
  function usesCells(sel) { return sel && C.whs[sel.value] == 1; }

  function cellOptions(whId, selected) {
    var o = '<option value="">' + esc(C.t.noCell) + '</option>';
    C.cells.filter(function (c) { return String(c.warehouse_id) === String(whId); }).forEach(function (c) {
      o += '<option value="' + c.id + '"' + (String(c.id) === String(selected) ? ' selected' : '') + '>' + esc((c.zone ? c.zone + ' · ' : '') + c.name) + '</option>';
    });
    return o;
  }

  function render() {
    var cells = usesCells(whSel) || (C.transfer && usesCells(wh2Sel));
    table.classList.toggle('nocells', !cells);
    var h = '';
    lines.forEach(function (l, i) {
      var n = 'lines[' + i + ']';
      h += '<tr data-i="' + i + '"><td class="idx">' + (i + 1) + '</td>' +
        '<td><b>' + esc(l.name) + '</b><br><small class="muted">' + esc(l.sku || '') + '</small><input type="hidden" name="' + n + '[product_id]" value="' + l.product_id + '"></td>';
      if (C.physical) h += '<td class="cellcol"><select name="' + n + '[cell_id]" data-f="cell_id">' + cellOptions(whSel.value, l.cell_id) + '</select></td>';
      if (C.transfer) h += '<td class="cellcol"><select name="' + n + '[to_cell_id]" data-f="to_cell_id">' + cellOptions(wh2Sel.value, l.to_cell_id) + '</select></td>';
      h += '<td class="num"><input class="q" name="' + n + '[qty]" data-f="qty" value="' + esc(l.qty) + '" inputmode="decimal"> <small class="muted">' + esc(l.unit || '') + '</small></td>';
      if (C.withPrice) h += '<td class="num"><input class="q" name="' + n + '[price]" data-f="price" value="' + esc(l.price) + '" inputmode="decimal"></td><td class="num sum">' + fmt(num(l.qty) * num(l.price)) + '</td>';
      h += '<td><button type="button" class="x" data-del="' + i + '" title="' + esc(C.t.del) + '">×</button></td></tr>';
    });
    rows.innerHTML = h;
    total();
  }

  function total() {
    var t = document.getElementById('total'); if (!t) return;
    var s = 0; lines.forEach(function (l) { s += num(l.qty) * num(l.price); });
    t.textContent = fmt(s);
  }

  rows.addEventListener('input', function (e) {
    var tr = e.target.closest('tr'); if (!tr) return;
    var l = lines[+tr.dataset.i], f = e.target.dataset.f; if (!f) return;
    l[f] = (f === 'cell_id' || f === 'to_cell_id') ? e.target.value : e.target.value;
    var sum = tr.querySelector('.sum'); if (sum) sum.textContent = fmt(num(l.qty) * num(l.price));
    total();
  });
  rows.addEventListener('click', function (e) {
    var d = e.target.closest('[data-del]'); if (!d) return;
    lines.splice(+d.dataset.del, 1); render();
  });
  [whSel, wh2Sel].forEach(function (s) {
    if (s) s.addEventListener('change', function () { lines.forEach(function (l) { l.cell_id = ''; l.to_cell_id = ''; }); render(); });
  });

  function addProduct(p) {
    var price = (C.type === 'sale' || C.type === 'shipment') ? p.sell_price : p.buy_price;
    var found = lines.filter(function (l) { return l.product_id === +p.id && !l.cell_id; })[0];
    if (found && !C.inventory) found.qty = num(found.qty) + 1;
    else lines.push({ product_id: +p.id, name: p.name, sku: p.sku || p.code, unit: p.unit, qty: C.inventory ? 0 : 1, price: num(price), cell_id: '', to_cell_id: '' });
    render();
    results.innerHTML = ''; results.classList.remove('open'); search.value = ''; search.focus();
    var last = rows.querySelector('tr:last-child [data-f=qty]'); if (last && C.inventory) { last.focus(); last.select(); }
  }

  function find() {
    var q = search.value.trim();
    if (q === '') { results.classList.remove('open'); results.innerHTML = ''; return Promise.resolve([]); }
    return fetch(C.api + '?q=' + encodeURIComponent(q) + '&wh=' + encodeURIComponent(whSel.value), { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (list) {
      results.innerHTML = list.length ? list.map(function (p, i) {
        return '<button type="button" data-p="' + i + '"><b>' + esc(p.name) + '</b><small>' + esc([p.sku, p.barcode].filter(Boolean).join(' · ')) + '</small>' +
          (p.available !== null ? '<em class="' + (p.available > 0 ? 'ok-t' : 'bad-t') + '">' + esc(C.t.available) + ': ' + p.available + ' ' + esc(p.unit) + '</em>' : '') + '</button>';
      }).join('') : '<div class="none">' + esc(C.t.nothing) + '</div>';
      results.classList.add('open'); results._list = list; return list;
    });
  }
  search.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(find, 160); });
  search.addEventListener('keydown', function (e) {
    if (e.key !== 'Enter') return;
    e.preventDefault(); clearTimeout(timer);
    find().then(function (list) {
      var exact = list.filter(function (p) { return p.barcode === search.value.trim() || p.sku === search.value.trim() || p.code === search.value.trim(); });
      if (exact.length === 1 || list.length === 1) addProduct(exact[0] || list[0]);
    });
  });
  results.addEventListener('click', function (e) {
    var b = e.target.closest('[data-p]'); if (b && results._list) addProduct(results._list[+b.dataset.p]);
  });
  document.addEventListener('click', function (e) { if (!e.target.closest('.search')) results.classList.remove('open'); });

  var load = document.getElementById('loadstock');
  if (load) load.addEventListener('click', function () {
    fetch(C.apiStock + '?wh=' + encodeURIComponent(whSel.value), { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (list) {
      if (lines.length && !window.confirm(C.t.loaded)) return;
      lines = list.map(function (p) { return { product_id: p.id, name: p.name, sku: p.sku, unit: p.unit, qty: p.on_hand, price: p.price, cell_id: '', to_cell_id: '' }; });
      render();
    });
  });

  document.getElementById('docform').addEventListener('submit', function (e) {
    if (e.submitter && e.submitter.value === 'post' && !whSel.value) { e.preventDefault(); window.alert(C.t.need_wh); }
  });

  render();
})();
