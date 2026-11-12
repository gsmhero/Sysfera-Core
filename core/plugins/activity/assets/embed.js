/* Sysfera Activity: scrolling ticker for any web page.  <div data-sysfera-activity data-count="5" data-height="160" data-theme="light"></div> + this script. */
(function () {
  var me = document.currentScript, src = me && me.src ? me.src : '';
  if (!src) { var all = document.getElementsByTagName('script'); for (var i = all.length - 1; i >= 0; i--) if (all[i].src.indexOf('/assets/p/activity/embed.js') > -1) { src = all[i].src; break; } }
  var root = src.split('/assets/p/activity/embed.js')[0];
  if (!root) return;
  if (!document.getElementById('sf-act-css')) {
    var st = document.createElement('style'); st.id = 'sf-act-css';
    st.textContent = '.sfa{box-sizing:border-box;border:1px solid #d9dde3;border-radius:10px;font:14px/1.45 system-ui,sans-serif;background:#fff;color:#1c2330;overflow:hidden}' +
      '.sfa.dark{background:#1c2330;color:#e8ecf2;border-color:#374151}.sfa-h{display:flex;justify-content:space-between;padding:8px 12px;border-bottom:1px solid rgba(128,128,128,.25);font-size:12px}' +
      '.sfa-h a{color:inherit;opacity:.75}.sfa-v{position:relative;overflow:hidden}.sfa-in{animation:sfa-up var(--d,30s) linear infinite}.sfa-v:hover .sfa-in{animation-play-state:paused}' +
      '.sfa-i{padding:8px 12px;border-bottom:1px solid rgba(128,128,128,.18)}.sfa-i b{display:block}.sfa-i small{opacity:.65}' +
      '@keyframes sfa-up{from{transform:translateY(0)}to{transform:translateY(-50%)}}@media (prefers-reduced-motion:reduce){.sfa-in{animation:none}.sfa-v{overflow:auto}}';
    document.head.appendChild(st);
  }
  function mk(tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text != null) e.textContent = text; return e; }
  function render(box) {
    if (box.getAttribute('data-sfa-done')) return; box.setAttribute('data-sfa-done', '1');
    var n = parseInt(box.getAttribute('data-count') || '5', 10) || 5, h = parseInt(box.getAttribute('data-height') || '160', 10) || 160;
    var wrap = mk('div', 'sfa' + (box.getAttribute('data-theme') === 'dark' ? ' dark' : ''));
    var head = mk('div', 'sfa-h'); head.appendChild(mk('span', '', 'Sysfera Activity'));
    var a = mk('a', '', '→'); a.href = root + '/p/activity/'; a.target = '_blank'; a.rel = 'noopener'; head.appendChild(a);
    var v = mk('div', 'sfa-v'); v.style.height = h + 'px'; wrap.appendChild(head); wrap.appendChild(v); box.appendChild(wrap);
    fetch(root + '/api/activity/feed?n=' + n).then(function (r) { return r.ok ? r.json() : []; }).then(function (items) {
      if (!items.length) { v.appendChild(mk('div', 'sfa-i', '—')); return; }
      var inn = mk('div', 'sfa-in'); inn.style.setProperty('--d', Math.max(12, items.length * 7) + 's');
      for (var k = 0; k < (items.length > 1 ? 2 : 1); k++) items.forEach(function (p) {
        var it = mk('div', 'sfa-i'); it.appendChild(mk('b', '', p.title)); it.appendChild(mk('span', '', p.body)); it.appendChild(mk('small', '', ' — ' + p.name + ', ' + p.at)); inn.appendChild(it);
      });
      v.appendChild(inn);
    }).catch(function () {});
  }
  function go() { var els = document.querySelectorAll('[data-sysfera-activity]'); for (var i = 0; i < els.length; i++) render(els[i]); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', go); else go();
})();
