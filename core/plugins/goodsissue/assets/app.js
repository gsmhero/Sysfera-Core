/* Goods issue — pickup warehouse app (PWA). Limited rights: it only CONFIRMS documents of the head office.
   Works offline: receipts, returns and counts are queued with their original time and sent when the connection is back.
   Issuing goods needs the server (the one-time code is checked live). Requests and answers are signed with the device key (HMAC-SHA256). */
(() => {
'use strict';
const G = window.GI, $app = document.getElementById('app');
const T = (k, v) => { let s = (G.t && G.t[k]) || k; if (v) for (const n in v) s = s.split('{' + n + '}').join(v[n]); return s; };
const h = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const LS = { get(k, d) { try { const v = localStorage.getItem('gi.' + k); return v === null ? d : JSON.parse(v); } catch (e) { return d; } }, set(k, v) { try { localStorage.setItem('gi.' + k, JSON.stringify(v)); } catch (e) {} }, del(k) { try { localStorage.removeItem('gi.' + k); } catch (e) {} } };
const enc = new TextEncoder();
const hex = b => [...new Uint8Array(b)].map(x => x.toString(16).padStart(2, '0')).join('');
const sha = async s => hex(await crypto.subtle.digest('SHA-256', enc.encode(s)));
const hmac = async (key, msg) => { const k = await crypto.subtle.importKey('raw', enc.encode(key), { name: 'HMAC', hash: 'SHA-256' }, false, ['sign']); return hex(await crypto.subtle.sign('HMAC', k, enc.encode(msg))); };
const uuid = () => (crypto.randomUUID ? crypto.randomUUID() : '10000000-1000-4000-8000-100000000000'.replace(/[018]/g, c => (c ^ crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> c / 4).toString(16)));

let S = { auth: LS.get('auth', null), dev: LS.get('dev', null), skew: 0, online: navigator.onLine, queue: LS.get('queue', []), failed: LS.get('failed', []), notes: [], busy: false };
if (!S.dev) { S.dev = (crypto.randomUUID ? crypto.randomUUID().replace(/-/g, '') : uuid().replace(/-/g, '')); LS.set('dev', S.dev); }
const now = () => Math.floor((Date.now() + S.skew) / 1000);
const save = () => { LS.set('queue', S.queue); LS.set('failed', S.failed); };

/* ---------------- signed API ---------------- */
class ApiErr extends Error { constructor(code, msg, status, detail) { super(msg || code); this.code = code; this.status = status; this.detail = detail; } }
async function call(method, path, body, opt = {}) {
  const url = new URL(G.api + path, location.href), raw = body === undefined ? '' : JSON.stringify(body), headers = { 'X-GI-Device': S.dev, 'Accept': 'application/json' };
  const ts = String(now());
  if (raw) headers['Content-Type'] = 'application/json';
  if (!opt.anon) {
    if (!S.auth) throw new ApiErr('unauthorized', 'sign in', 401);
    headers['X-GI-Token'] = S.auth.token; headers['X-GI-Timestamp'] = ts;
    headers['X-GI-Signature'] = await hmac(S.auth.device_key, ts + '\n' + method + '\n' + url.pathname + url.search + '\n' + await sha(raw));
  }
  let r, text;
  try { r = await fetch(url, { method, headers, body: raw || undefined, cache: 'no-store' }); text = await r.text(); }
  catch (e) { S.online = false; throw new ApiErr('network', 'no connection', 0); }
  S.online = true;
  let data = {}; try { data = text ? JSON.parse(text) : {}; } catch (e) { throw new ApiErr('bad_response', 'unreadable answer', r.status); }
  const sig = r.headers.get('X-GI-Signature'), sts = r.headers.get('X-GI-Timestamp');
  if (!opt.anon && (sig || r.ok)) {   // everything the server sends back to a signed-in app is signed too
    const want = sig && sts ? await hmac(S.auth.device_key, sts + '\n' + r.status + '\n' + await sha(text)) : '';
    if (!sig || want !== sig) throw new ApiErr('bad_server_signature', 'the answer is not signed by the server', r.status);
  }
  if (!r.ok) { const er = data.error || {}; if (r.status === 401 && !opt.anon && er.code !== 'stale_request') { S.auth = null; LS.del('auth'); } throw new ApiErr(er.code || 'error', er.message, r.status, er.detail); }
  return data;
}
async function syncClock() { try { const t0 = Date.now(), r = await fetch(new URL(G.api + 'time', location.href), { cache: 'no-store' }), d = await r.json(); S.skew = d.time * 1000 - (t0 + Date.now()) / 2; S.online = true; } catch (e) { S.online = false; } }
const errText = e => { const k = 'a_err_' + (e.code || ''); const t = T(k); return t !== k ? t + (e.detail && e.detail.attempts_left !== undefined ? ' (' + e.detail.attempts_left + ')' : '') : (e.message || e.code || 'error'); };

/* ---------------- offline queue ---------------- */
function enqueue(type, ref, payload) { const a = { type, ref, doc_uuid: uuid(), client_ts: now(), payload }; S.queue.push(a); save(); return a; }
async function flush() {
  if (S.busy || !S.queue.length || !S.auth) return;
  S.busy = true; render();
  try {
    const r = await call('POST', 'sync', { actions: S.queue });
    const done = new Set();
    for (const x of r.results) {
      const a = S.queue.find(q => q.doc_uuid === x.doc_uuid); if (!a) continue;
      if (x.ok) { done.add(a.doc_uuid); S.notes.unshift({ a, res: x.result, conflict: x.conflict }); }
      else if (['busy', 'server_error'].includes(x.error)) continue;   // try again later
      else { done.add(a.doc_uuid); S.failed.unshift({ a, error: x.error, message: x.message }); }
    }
    S.queue = S.queue.filter(q => !done.has(q.doc_uuid)); S.notes = S.notes.slice(0, 20); S.failed = S.failed.slice(0, 30); save();
    await refresh();
  } catch (e) { if (e.code !== 'network') S.err = errText(e); }
  S.busy = false; render();
}
async function refresh() { try { const m = await call('GET', 'manifests'); LS.set('manifests', m.manifests); const o = await call('GET', 'orders?status=ready_for_issue,blocked,return_requested,issued'); LS.set('orders', o.orders); } catch (e) {} }

/* ---------------- scanning ---------------- */
let cam = null;
async function startCam(onCode) {
  if (!('BarcodeDetector' in window) || !navigator.mediaDevices) return false;
  stopCam();
  const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } }), v = document.getElementById('cam');
  if (!v) { stream.getTracks().forEach(t => t.stop()); return false; }
  v.srcObject = stream; await v.play();
  const det = new BarcodeDetector(), seen = { c: '', t: 0 };
  const tick = async () => { if (!cam) return; try { const f = await det.detect(v); if (f[0]) { const c = f[0].rawValue, t = Date.now(); if (c !== seen.c || t - seen.t > 1500) { seen.c = c; seen.t = t; onCode(c); } } } catch (e) {} cam.timer = setTimeout(tick, 250); };
  cam = { stream, timer: 0 }; tick(); return true;
}
function stopCam() { if (cam) { clearTimeout(cam.timer); cam.stream.getTracks().forEach(t => t.stop()); cam = null; } }

/* ---------------- views ---------------- */
let V = { name: 'home' }, flash = null;
const go = (name, p = {}) => { stopCam(); V = { name, ...p }; flash = null; S.err = ''; render(); window.scrollTo(0, 0); };
const header = (title, back) => `<header class="top">${back ? `<button class="back" data-go="${back}">‹</button>` : ''}<h1>${h(title)}</h1><span class="pill ${S.online ? '' : 'off'}">${S.online ? T('a_online') : T('a_offline')}</span>${S.queue.length ? `<button class="pill q" data-go="queue">${S.queue.length}</button>` : ''}</header>`;
const msg = () => (S.err ? `<div class="err">${h(S.err)}</div>` : '') + (flash ? `<div class="${flash.t}">${h(flash.m)}</div>` : '');
const stLabel = s => `<span class="st ${h(s)}">${h(T('a_st_' + s))}</span>`;

function render() {
  if (!S.auth) return $app.innerHTML = viewLogin();
  const f = { home: viewHome, receive: viewReceiveList, rcv: viewReceive, issue: viewIssueList, iss: viewIssue, returns: viewReturns, count: viewCount, queue: viewQueue }[V.name] || viewHome;
  $app.innerHTML = f(); after();
}
function viewLogin() {
  return `<header class="top"><h1>${h(T('a_title'))}</h1><span class="pill ${S.online ? '' : 'off'}">${S.online ? T('a_online') : T('a_offline')}</span></header>${msg()}
  <form class="card" id="login"><label>${h(T('a_email'))}<input name="email" type="email" autocomplete="username" required></label><label>${h(T('a_password'))}<input name="password" type="password" autocomplete="current-password" required></label>
  <button class="btn primary block">${h(T('a_signin'))}</button><p class="muted">${h(T('a_device_hint'))}</p></form>`;
}
function viewHome() {
  const m = LS.get('manifests', []).filter(x => ['created', 'transferred'].includes(x.status)).length, o = LS.get('orders', []).filter(x => x.status === 'ready_for_issue').length;
  return `${header(S.auth.warehouse.name)}${msg()}<div class="tiles">
  <button class="card tile" data-go="receive"><b>${h(T('a_receive'))}</b><span>${m} ${h(T('a_manifests'))}</span></button>
  <button class="card tile" data-go="issue"><b>${h(T('a_issue'))}</b><span>${o} ${h(T('a_ready'))}</span></button>
  <button class="card tile" data-go="returns"><b>${h(T('a_returns'))}</b><span>${h(T('a_returns_h'))}</span></button>
  <button class="card tile" data-go="count"><b>${h(T('a_count'))}</b><span>${h(T('a_count_h'))}</span></button></div>
  <div class="card"><div class="row"><span class="grow muted">${h(S.auth.user.name)}</span><button class="btn sm" data-act="sync">${h(T('a_sync_now'))}</button><button class="btn sm" data-act="logout">${h(T('a_signout'))}</button></div>
  ${S.notes.length ? '<hr>' + S.notes.slice(0, 5).map(noteHtml).join('') : ''}</div>`;
}
function noteHtml(n) {
  if (n.conflict) return `<p class="warn">${h(T('a_conflict_note', { t: n.a.ref ? n.a.ref.slice(0, 8) : '' }))}</p>`;
  if (n.a.type === 'receipt') { const bl = (n.res.orders || []).filter(o => o.status === 'blocked').length; return `<p class="${bl ? 'warn' : 'okmsg'}">${h(T(bl ? 'a_receipt_blocked' : 'a_receipt_ok', { n: bl }))}</p>`; }
  return `<p class="okmsg">${h(T('a_sent'))}: ${h(n.a.type)}</p>`;
}
function viewQueue() {
  return `${header(T('a_queue'), 'home')}${msg()}<div class="card"><button class="btn primary block" data-act="sync" ${S.queue.length ? '' : 'disabled'}>${h(T('a_sync_now'))}</button></div>
  ${S.queue.map(a => `<div class="card"><b>${h(a.type)}</b> <span class="muted">${h(new Date(a.client_ts * 1000).toLocaleString())}</span><br><small class="muted">${h(a.doc_uuid)}</small></div>`).join('') || `<p class="muted">${h(T('a_queue_empty'))}</p>`}
  ${S.failed.length ? `<h3>${h(T('a_failed'))}</h3>` + S.failed.map(f => `<div class="card err"><b>${h(f.a.type)}</b> ${h(errText({ code: f.error, message: f.message }))}</div>`).join('') + `<button class="btn sm" data-act="clearfailed">${h(T('a_clear'))}</button>` : ''}`;
}

/* ---- receipt ---- */
function viewReceiveList() {
  const ms = LS.get('manifests', []);
  return `${header(T('a_receive'), 'home')}${msg()}<button class="btn block" data-act="reload">${h(T('a_reload'))}</button><br>${ms.map(m => `<button class="card tile block" data-go="rcv" data-uuid="${h(m.uuid)}" style="width:100%"><b>${h(m.uuid.slice(0, 8))} ${stLabel(m.status)}</b><span>${m.orders} ${h(T('a_orders'))} · ${h(m.created_at)}</span></button>`).join('') || `<p class="muted">${h(T('a_no_data'))}</p>`}`;
}
function viewReceive() {
  const m = V.m; if (!m) return header(T('a_receive'), 'receive') + '<p class="muted">…</p>';
  const items = m.orders.flatMap(o => o.items.map(i => ({ ...i, order: o.number }))), sc = V.scans, miss = items.filter(i => (sc[i.barcode] || 0) !== i.qty);
  return `${header(m.uuid.slice(0, 8), 'receive')}${msg()}
  <div class="card"><input id="scan" placeholder="${h(T('a_scan_ph'))}" autocomplete="off" inputmode="none" autofocus>
  <div class="row"><button class="btn sm" data-act="cam">${h(T('a_camera'))}</button><span class="muted grow">${items.reduce((s, i) => s + Math.min(sc[i.barcode] || 0, i.qty), 0)} / ${items.reduce((s, i) => s + i.qty, 0)}</span></div><video id="cam" class="cam" playsinline muted hidden></video></div>
  <div class="card">${items.map(i => { const q = sc[i.barcode] || 0, c = q === i.qty ? 'good' : q > i.qty ? 'mid' : q ? 'bad' : ''; return `<div class="item"><div class="nm"><b>${h(i.name)}</b><small>#${h(i.order)} · ${h(i.barcode)} · ${i.weight_g} g</small>
    ${V.flag[i.barcode] ? `<small class="bad">${h(T('a_' + V.flag[i.barcode]))}</small>` : ''}</div><div class="qty"><button data-dec="${h(i.barcode)}">−</button><b class="${c}">${q}/${i.qty}</b><button data-inc="${h(i.barcode)}">+</button></div>
    <button class="btn sm" data-flag="${h(i.barcode)}">⚠</button></div>`; }).join('')}
  ${Object.keys(V.unknown).map(b => `<div class="item"><div class="nm"><b class="mid">${h(T('a_unknown'))}</b><small>${h(b)}</small></div><b>${V.unknown[b]}</b></div>`).join('')}</div>
  ${m.status !== 'transferred' && m.status !== 'created' ? `<div class="warn">${h(T('a_already_done'))}</div>` : `
  ${miss.length ? `<div class="warn">${h(T('a_diff_warn', { n: miss.length }))}</div>` : ''}
  <button class="btn primary block" data-act="rcv-ok">${h(T('a_confirm_receipt'))}</button><br><button class="btn danger block" data-act="rcv-reject">${h(T('a_reject'))}</button>`}`;
}
async function openManifest(uuid) {
  let m = null;
  try { m = await call('GET', 'manifests/' + uuid); const c = LS.get('mf_' + uuid, null); LS.set('mf_' + uuid, m); } catch (e) { m = LS.get('mf_' + uuid, null); if (!m) { S.err = errText(e); return go('receive'); } }
  V = { name: 'rcv', uuid, m, scans: {}, unknown: {}, flag: {}, note: {} }; render();
}
function scanCode(code) {
  code = String(code).trim(); if (!code || V.name !== 'rcv' && V.name !== 'count') return;
  if (V.name === 'count') { V.lines[code] = (V.lines[code] || 0) + 1; return render(); }
  const known = V.m.orders.some(o => o.items.some(i => i.barcode === code));
  if (known) V.scans[code] = (V.scans[code] || 0) + 1; else V.unknown[code] = (V.unknown[code] || 0) + 1;
  if (navigator.vibrate) navigator.vibrate(known ? 30 : [60, 40, 60]); render();
}
async function submitReceipt(decision) {
  const m = V.m, disc = [];
  for (const b in V.flag) { const f = V.flag[b]; disc.push({ barcode: b, kind: f === 'damaged' ? 'damaged' : 'wrong_weight', qty: f === 'damaged' ? Math.max(1, V.scans[b] || 1) : undefined, note: V.note[b] || '' }); }
  const scans = Object.keys(V.scans).map(b => ({ barcode: b, qty: V.scans[b] })).concat(Object.keys(V.unknown).map(b => ({ barcode: b, qty: V.unknown[b] })));
  enqueue('receipt', m.uuid, { checksum: m.checksum, decision, scans, discrepancies: disc });
  await flush(); const m = { t: 'okmsg', m: S.queue.length ? T('a_queued') : T('a_sent') }; go('home'); flash = m; render();
}

/* ---- issue ---- */
function viewIssueList() {
  const os = (V.found || LS.get('orders', []).filter(o => o.status === 'ready_for_issue' || o.status === 'blocked'));
  return `${header(T('a_issue'), 'home')}${msg()}<form class="card" id="find"><input name="q" value="${h(V.q || '')}" placeholder="${h(T('a_search'))}" autocomplete="off"><button class="btn block">${h(T('a_find'))}</button></form>
  ${os.map(o => `<button class="card tile" data-go="iss" data-uuid="${h(o.uuid)}" style="width:100%"><b>#${h(o.number)} ${stLabel(o.status)}</b><span>${h(o.customer_name)} · ${h(o.total_rub)} ₽</span></button>`).join('') || `<p class="muted">${h(T('a_no_data'))}</p>`}`;
}
function viewIssue() {
  const o = V.o; if (!o) return header(T('a_issue'), 'issue') + '<p class="muted">…</p>';
  const ready = o.status === 'ready_for_issue';
  return `${header('#' + o.number, 'issue')}${msg()}<div class="card"><b>${h(o.customer_name)}</b> ${stLabel(o.status)}<br><span class="muted">${h(o.customer_phone)} · ${h(o.total_rub)} ₽</span>
  ${o.items.map(i => `<div class="item"><div class="nm"><b>${h(i.name)}</b><small>${h(i.barcode)}</small></div><b>× ${i.qty}</b></div>`).join('')}</div>
  ${!ready ? `<div class="warn">${h(T('a_not_ready', { s: T('a_st_' + o.status) }))}</div>` : !S.online ? `<div class="warn">${h(T('a_issue_online'))}</div>` : `
  <form class="card" id="issue"><label>${h(T('a_code'))}<input class="code-in" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required autofocus></label>
  ${o.id_check ? `<div class="warn">${h(T('a_id_required'))}</div><label class="row"><input type="checkbox" name="idc" style="width:auto"> ${h(T('a_id_checked'))}</label>` : ''}
  <label>${h(T('a_signature'))}</label><canvas class="sig" id="sig" width="640" height="320"></canvas><div class="row"><button type="button" class="btn sm" data-act="sigclear">${h(T('a_clear'))}</button></div><br>
  <button class="btn primary block" ${V.sending ? 'disabled' : ''}>${h(V.retry ? T('a_retry') : T('a_do_issue'))}</button></form>`}`;
}
async function openOrder(uuid) {
  let o = null; try { o = await call('GET', 'orders/' + uuid); } catch (e) { o = LS.get('orders', []).find(x => x.uuid === uuid); if (!o) { S.err = errText(e); return go('issue'); } }
  V = { name: 'iss', uuid, o, doc: uuid_(), sig: false }; render();
}
const uuid_ = uuid;
function sigPad() {
  const c = document.getElementById('sig'); if (!c) return; const x = c.getContext('2d'); x.lineWidth = 4; x.lineCap = 'round'; x.strokeStyle = '#111'; let d = false;
  const p = e => { const r = c.getBoundingClientRect(); return [(e.clientX - r.left) * c.width / r.width, (e.clientY - r.top) * c.height / r.height]; };
  c.onpointerdown = e => { d = true; c.setPointerCapture(e.pointerId); const [a, b] = p(e); x.beginPath(); x.moveTo(a, b); V.sig = true; };
  c.onpointermove = e => { if (!d) return; const [a, b] = p(e); x.lineTo(a, b); x.stroke(); }; c.onpointerup = () => { d = false; };
}
async function doIssue(f) {
  const code = f.code.value.trim(); if (!/^\d{6}$/.test(code)) { S.err = T('a_err_code_wrong'); return render(); }
  if (!V.sig) { S.err = T('a_err_evidence_required'); return render(); }
  const body = { doc_uuid: V.doc, code, id_checked: !!(f.idc && f.idc.checked), signature: document.getElementById('sig').toDataURL('image/png'), client_ts: now() };
  V.sending = true; S.err = ''; render();
  try { const r = await call('POST', 'orders/' + V.uuid + '/issue', body); flash = { t: 'okmsg', m: T('a_issued_ok', { n: r.order.number }) }; await refresh(); const m = flash; go('home'); flash = m; render(); }
  catch (e) {
    V.sending = false; if (e.code === 'network') { V.retry = true; S.err = T('a_not_sent'); }   // the SAME document uuid is sent again: no duplicate act
    else { V.retry = false; V.doc = uuid(); S.err = errText(e); if (['already_issued', 'order_blocked', 'not_ready'].includes(e.code)) { await refresh(); } }
    render();
  }
}

/* ---- returns ---- */
async function loadReturns() {
  let os = []; try { os = (await call('GET', 'orders?status=issued,return_requested' + (V.q ? '&q=' + encodeURIComponent(V.q) : ''))).orders; } catch (e) { os = LS.get('orders', []).filter(o => ['issued', 'return_requested'].includes(o.status)); }
  V.list = os; render();
}
function viewReturns() {
  return `${header(T('a_returns'), 'home')}${msg()}<form class="card" id="rfind"><input name="q" value="${h(V.q || '')}" placeholder="${h(T('a_search'))}"><button class="btn block">${h(T('a_find'))}</button></form>
  ${(V.list || []).map(o => `<div class="card"><b>#${h(o.number)}</b> ${stLabel(o.status)}<br><span class="muted">${h(o.customer_name)} · ${h(o.total_rub)} ₽</span>
  <div class="row" style="margin-top:8px">${o.status === 'issued' ? `<button class="btn sm" data-ret="request" data-uuid="${h(o.uuid)}">${h(T('a_request_return'))}</button>` : `<button class="btn sm primary" data-ret="receive" data-uuid="${h(o.uuid)}">${h(T('a_received_back'))}</button><button class="btn sm danger" data-ret="receive-bad" data-uuid="${h(o.uuid)}">${h(T('a_received_damaged'))}</button>`}</div></div>`).join('') || `<p class="muted">${h(T('a_no_data'))}</p>`}`;
}
async function doReturn(kind, uuid) {
  const p = { kind: kind === 'request' ? 'request' : 'receive' };
  if (kind === 'request') { const r = prompt(T('a_reason')); if (!r || r.trim().length < 3) return; p.reason = r.trim(); }
  else p.condition = kind === 'receive-bad' ? 'damaged' : 'ok';
  enqueue('return', uuid, p); await flush(); flash = { t: 'okmsg', m: S.queue.length ? T('a_queued') : T('a_sent') }; await loadReturns();
}

/* ---- end-of-day count ---- */
function viewCount() {
  const bs = Object.keys(V.lines);
  return `${header(T('a_count'), 'home')}${msg()}<div class="card"><input id="scan" placeholder="${h(T('a_scan_ph'))}" autocomplete="off" inputmode="none" autofocus><div class="row"><button class="btn sm" data-act="cam">${h(T('a_camera'))}</button></div><video id="cam" class="cam" playsinline muted hidden></video></div>
  <div class="card">${bs.map(b => `<div class="item"><div class="nm"><b>${h(b)}</b></div><div class="qty"><button data-cdec="${h(b)}">−</button><b>${V.lines[b]}</b><button data-cinc="${h(b)}">+</button></div></div>`).join('') || `<p class="muted">${h(T('a_count_empty'))}</p>`}</div>
  <button class="btn primary block" data-act="count-send" ${bs.length ? '' : 'disabled'}>${h(T('a_submit_count'))}</button>`;
}

/* ---------------- events ---------------- */
function after() { if (V.name === 'iss') sigPad(); const s = document.getElementById('scan'); if (s && !S.err) s.focus({ preventScroll: true }); }
document.addEventListener('submit', async e => {
  const f = e.target; e.preventDefault();
  if (f.id === 'login') {
    S.err = ''; const btn = f.querySelector('button'); btn.disabled = true;
    try { await syncClock(); const r = await call('POST', 'auth/login', { email: f.email.value, password: f.password.value, device_id: S.dev, device_label: (navigator.userAgent || '').slice(0, 100) }, { anon: true }); S.auth = r; LS.set('auth', r); await refresh(); V = { name: 'home' }; flush(); }
    catch (er) { S.err = er.code === 'network' ? T('a_err_network') : errText(er); }
    render();
  } else if (f.id === 'issue') doIssue(f);
  else if (f.id === 'find') { V.q = f.q.value.trim(); try { V.found = (await call('GET', 'orders?status=ready_for_issue,blocked,issued,return_requested&q=' + encodeURIComponent(V.q))).orders; } catch (er) { const q = V.q.toLowerCase(); V.found = LS.get('orders', []).filter(o => (o.number + o.customer_name + o.customer_phone).toLowerCase().includes(q)); } render(); }
  else if (f.id === 'rfind') { V.q = f.q.value.trim(); loadReturns(); }
});
document.addEventListener('keydown', e => { if (e.key === 'Enter' && e.target.id === 'scan') { e.preventDefault(); scanCode(e.target.value); e.target.value = ''; } });
document.addEventListener('click', async e => {
  const t = e.target.closest('[data-go],[data-act],[data-inc],[data-dec],[data-flag],[data-ret],[data-cinc],[data-cdec]'); if (!t) return;
  const d = t.dataset;
  if (d.go) { if (d.go === 'rcv') return openManifest(d.uuid); if (d.go === 'iss') return openOrder(d.uuid); go(d.go, d.go === 'count' ? { lines: {} } : {}); if (d.go === 'returns') loadReturns(); if (d.go === 'receive' || d.go === 'issue') refresh().then(render); return; }
  if (d.inc) { V.scans[d.inc] = (V.scans[d.inc] || 0) + 1; return render(); }
  if (d.dec) { V.scans[d.dec] = Math.max(0, (V.scans[d.dec] || 0) - 1); return render(); }
  if (d.cinc) { V.lines[d.cinc]++; return render(); }
  if (d.cdec) { V.lines[d.cdec] = Math.max(0, V.lines[d.cdec] - 1); if (!V.lines[d.cdec]) delete V.lines[d.cdec]; return render(); }
  if (d.flag) { const cur = V.flag[d.flag]; if (!cur) V.flag[d.flag] = 'damaged'; else if (cur === 'damaged') V.flag[d.flag] = 'wrong_weight'; else delete V.flag[d.flag]; return render(); }
  if (d.ret) return doReturn(d.ret, d.uuid);
  switch (d.act) {
    case 'sync': S.err = ''; await syncClock(); await refresh(); await flush(); render(); break;
    case 'reload': await refresh(); render(); break;
    case 'logout': try { await call('POST', 'auth/logout', {}); } catch (er) {} if (!S.queue.length) { S.auth = null; LS.del('auth'); } else { S.err = T('a_queue_first'); } render(); break;
    case 'clearfailed': S.failed = []; save(); render(); break;
    case 'sigclear': { const c = document.getElementById('sig'); c.getContext('2d').clearRect(0, 0, c.width, c.height); V.sig = false; break; }
    case 'cam': { const v = document.getElementById('cam'); try { v.hidden = false; if (!await startCam(scanCode)) { v.hidden = true; S.err = T('a_no_camera'); render(); } } catch (er) { v.hidden = true; S.err = T('a_no_camera'); render(); } break; }
    case 'rcv-ok': if (confirm(T('a_confirm_q'))) submitReceipt('confirm'); break;
    case 'rcv-reject': if (confirm(T('a_reject_q'))) submitReceipt('reject'); break;
    case 'count-send': { enqueue('count', '', { lines: Object.keys(V.lines).map(b => ({ barcode: b, qty: V.lines[b] })), day: new Date(Date.now() + S.skew).toISOString().slice(0, 10) }); await flush(); flash = { t: 'okmsg', m: S.queue.length ? T('a_queued') : T('a_sent') }; const m = flash; go('home'); flash = m; render(); break; }
  }
});
window.addEventListener('online', () => { S.online = true; syncClock().then(flush); render(); });
window.addEventListener('offline', () => { S.online = false; render(); });
setInterval(() => { if (navigator.onLine && S.queue.length) flush(); }, 20000);

if ('serviceWorker' in navigator) navigator.serviceWorker.register(G.sw, { scope: G.scope }).catch(() => {});
if (!window.crypto || !crypto.subtle) { $app.innerHTML = '<div class="err" style="margin-top:20px">' + h(T('a_need_https')) + '</div>'; return; }
syncClock().then(() => { render(); if (S.auth) { refresh().then(render); flush(); } });
render();
})();
