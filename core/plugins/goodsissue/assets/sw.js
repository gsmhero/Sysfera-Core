/* Goods issue app shell: opens without a connection. The API (/v1/) is never cached here: the app keeps its own queue and copies. */
const CACHE = 'gi-shell-1';
self.addEventListener('install', e => { self.skipWaiting(); });
self.addEventListener('activate', e => { e.waitUntil(caches.keys().then(ks => Promise.all(ks.filter(k => k !== CACHE).map(k => caches.delete(k)))).then(() => self.clients.claim())); });
self.addEventListener('fetch', e => {
  const r = e.request, u = new URL(r.url);
  if (r.method !== 'GET' || u.origin !== location.origin || u.pathname.includes('/goodsissue/v1/')) return;
  if (!(u.pathname.endsWith('/goodsissue/app') || u.pathname.includes('/assets/p/goodsissue/') || u.pathname.endsWith('manifest.webmanifest'))) return;
  e.respondWith(caches.open(CACHE).then(c => c.match(r, { ignoreSearch: false }).then(hit => {
    const net = fetch(r).then(res => { if (res.ok) c.put(r, res.clone()); return res; }).catch(() => hit);
    return hit || net;
  })));
});
