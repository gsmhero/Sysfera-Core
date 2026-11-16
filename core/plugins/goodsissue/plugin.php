<?php
declare(strict_types=1);

use SysferaGi\{Err, Exchange, DbStore, Manifest, Sig, Seed, Units, Audit, Machine};

/**
 * Sysfera Goods issue: the head office creates transfer orders; the limited-rights pickup-warehouse app (PWA at /api/goodsissue/app)
 * confirms receipt, issues goods against a one-time code and registers returns. Rules live in lib/Exchange.php (tested without a database).
 * Access is NOT done with Sysfera capabilities (those are granted to every user by default): the head office = administrators + users listed
 * as "office" here; the warehouse operators are listed with their ONE warehouse on the Warehouses page.
 */
return function (\Sysfera\Plugin $sf): void {
    foreach (['Err', 'Units', 'Machine', 'Sig', 'Code', 'Audit', 'Manifest', 'Store', 'Seed', 'Exchange', 'DbStore'] as $c) require_once __DIR__ . "/lib/$c.php";

    $db = $sf->db(); $store = new DbStore($db);
    $secret = function (string $k) use ($sf): string { $v = $sf->setting($k); if ($v === '') { $v = bin2hex(random_bytes(32)); $sf->setSetting($k, $v); } return $v; };
    $cfg = function () use ($sf, $secret): array {
        try { $th = Units::kop(Units::rub($sf->setting('id_threshold', '30000'))); } catch (Err) { $th = 3000000; }
        return ['secret' => $secret('code_secret'), 'code_ttl' => max(1, (int)$sf->setting('code_ttl_h', '48')) * 3600, 'id_threshold_kop' => $th, 'overdue_days' => max(1, (int)$sf->setting('overdue_days', '7'))];
    };
    /** the code goes to the customer through a connector (hook "goodsissue.code": SMS, push) and/or by e-mail; the app never sees it */
    $onCode = function (array $o, string $code) use ($sf): void {
        try { \Sysfera\Hooks::do('goodsissue.code', $o, $code); } catch (\Throwable) {}
        $mail = (string)($o['customer_email'] ?? '');
        if ($mail !== '' && filter_var($mail, FILTER_VALIDATE_EMAIL)) { try { $sf->mail($mail, $sf->t('mail_subject', ['n' => $o['number']]), $sf->t('mail_body', ['n' => $o['number'], 'code' => $code])); } catch (\Throwable) {} }
    };
    $ex = fn(): Exchange => new Exchange($store, $cfg(), $onCode);
    $now = fn(): string => date('Y-m-d H:i:s');

    /** one request at a time per document / order / manifest, inside a transaction (a refused request is still committed: its audit row and failed-attempt counter must stay) */
    $run = function (string $lockKey, callable $fn) use ($db) {
        $pdo = $db->pdo(); $name = 'gi_' . substr(sha1($db->prefix . $lockKey), 0, 40);
        if (!(int)$db->val('SELECT GET_LOCK(?, 15)', [$name])) throw new Err('busy', 'try again in a moment', 503);
        try {
            $pdo->beginTransaction();
            try { $r = $fn(); $pdo->commit(); return $r; }
            catch (Err $e) { if ($pdo->inTransaction()) $pdo->commit(); throw $e; }
            catch (\Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
        } finally { $db->q('SELECT RELEASE_LOCK(?)', [$name]); }
    };

    /* =============================== staff, menu =============================== */
    $staff = function () use ($sf, $db): ?array {
        static $row = false; if ($row !== false) return $row;
        $uid = $sf->auth()->id(); if (!$uid) return $row = null;
        try { return $row = $db->row('SELECT * FROM {p}gi_staff WHERE user_id = ?', [$uid]); } catch (\Throwable) { return $row = null; }
    };
    $isOffice = fn(): bool => $sf->auth()->isAdmin() || (($r = $staff()) !== null && $r['role'] === 'office');
    $isOperator = fn(): bool => ($r = $staff()) !== null && $r['role'] === 'operator';
    $sf->on('menu.items', function ($items) use ($isOffice, $isOperator, $db) {
        try {
            if ($isOffice()) $items[] = ['label' => 'menu', 'slug' => 'goodsissue', 'path' => '/p/goodsissue/', 'cap' => null, 'order' => 21, 'admin' => false, 'icon' => 'box',
                'badge' => fn() => (int)$db->val("SELECT COUNT(*) FROM {p}gi_incidents WHERE status = 'open'")];
            elseif ($isOperator()) $items[] = ['label' => 'menu_desk', 'slug' => 'goodsissue', 'path' => '/p/goodsissue/desk', 'cap' => null, 'order' => 21, 'admin' => false, 'icon' => 'box', 'badge' => null];
        } catch (\Throwable) {}
        return $items;
    });

    /* =============================== machine API =============================== */
    $out = function (int $status, array $data, ?string $key = null): void {
        http_response_code($status); header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
        $body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION) ?: '{}';
        if ($key) { $ts = (string)time(); header('X-GI-Timestamp: ' . $ts); header('X-GI-Signature: ' . Sig::response($key, $ts, $status, $body)); }   // server -> app messages are signed
        echo $body;
    };
    $hdr = fn(string $n): string => (string)($_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $n))] ?? '');

    /** @return array{0: array, 1: string} actor and the key the answer is signed with */
    $authenticate = function (string $mode, string $raw) use ($db, $secret, $hdr, $sf): array {
        $ts = $hdr('X-GI-Timestamp'); $sig = $hdr('X-GI-Signature'); $method = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET'); $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
        if (!Sig::fresh($ts, time())) throw new Err('stale_request', 'X-GI-Timestamp is missing or outside the 5 minute window (check the clock; GET /v1/time)', 401);
        if ($mode === 'office') {
            $key = $secret('office_key');
            if (!Sig::ok(Sig::request($key, $ts, $method, $uri, $raw), $sig)) throw new Err('bad_signature', 'signature does not match', 401);
            return [['kind' => 'office', 'user' => null, 'name' => 'office-api', 'warehouse' => null, 'device' => 'office-api'], $key];
        }
        $token = $hdr('X-GI-Token'); if ($token === '' && preg_match('/^Bearer (\S+)$/', $hdr('Authorization'), $m)) $token = $m[1];
        $dev = $hdr('X-GI-Device');
        $s = $token !== '' ? $db->row('SELECT * FROM {p}gi_sessions WHERE token_hash = ? AND expires_at > NOW()', [hash('sha256', $token)]) : null;
        if (!$s || $dev === '' || !hash_equals((string)$s['device_id'], $dev)) throw new Err('unauthorized', 'sign in again', 401);
        $d = $db->row("SELECT * FROM {p}gi_devices WHERE user_id = ? AND device_id = ? AND status = 'approved'", [$s['user_id'], $dev]);
        $u = $db->row("SELECT u.name, st.warehouse_id FROM {p}users u JOIN {p}gi_staff st ON st.user_id = u.id AND st.role = 'operator' JOIN {p}gi_warehouses w ON w.id = st.warehouse_id AND w.active = 1 WHERE u.id = ? AND u.status = 'active'", [$s['user_id']]);
        if (!$d || !$u) throw new Err('unauthorized', 'this account or device is not allowed any more', 401);
        if (!Sig::ok(Sig::request($d['secret'], $ts, $method, $uri, $raw), $sig)) throw new Err('bad_signature', 'signature does not match', 401);
        if (strtotime((string)$d['last_seen']) < time() - 300) $db->q('UPDATE {p}gi_devices SET last_seen = NOW() WHERE id = ?', [$d['id']]);
        return [['kind' => 'operator', 'user' => (int)$s['user_id'], 'name' => (string)$u['name'], 'warehouse' => (int)$u['warehouse_id'], 'device' => $dev, 'dev_secret' => $d['secret']], $d['secret']];
    };

    /** $mode: public | app | office.  Answers are JSON {…} or {"error":{"code","message","detail"}}; app/office answers are signed. */
    $endpoint = function (string $method, string $path, string $mode, callable $fn) use ($sf, $out, $authenticate): void {
        $sf->api($method, 'goodsissue/v1/' . $path, function (array $p = []) use ($fn, $mode, $out, $authenticate) {
            $raw = (string)file_get_contents('php://input', false, null, 0, 3_000_000); $key = null;
            try {
                $actor = null; if ($mode !== 'public') [$actor, $key] = $authenticate($mode, $raw);
                $in = $raw !== '' ? json_decode($raw, true) : []; if (!is_array($in)) throw new Err('bad_json', 'the body must be a JSON object', 400);
                $out(200, $fn($p, $in, $actor), $key);
            } catch (Err $e) { $out($e->http, ['error' => ['code' => $e->errCode, 'message' => $e->getMessage()] + ($e->extra ? ['detail' => $e->extra] : [])], $key); }
            catch (\Throwable $e) { \Sysfera\App::log('goodsissue ' . $e); $out(500, ['error' => ['code' => 'server_error', 'message' => 'internal error']], $key); }
        });
    };

    $endpoint('GET', 'time', 'public', fn() => ['time' => time(), 'iso' => date('c')]);
    $sf->api('GET', 'goodsissue/v1/openapi.yaml', function () { header('Content-Type: application/yaml; charset=utf-8'); readfile(__DIR__ . '/openapi.yaml'); });

    /* ---- sign in: user account + bound device ---- */
    $endpoint('POST', 'auth/login', 'public', function ($p, $in) use ($db, $store, $now, $cfg, $sf) {
        $email = mb_strtolower(trim((string)($in['email'] ?? ''))); $pass = (string)($in['password'] ?? ''); $dev = trim((string)($in['device_id'] ?? ''));
        if (!preg_match('/^[A-Za-z0-9._\-]{16,64}$/', $dev)) throw new Err('bad_device', 'device_id: 16-64 characters, generated once by the app', 422);
        $fail = fn(string $why) => $store->audit(['ts' => $now(), 'client_ts' => null, 'actor' => 'app:' . mb_substr($email, 0, 120), 'action' => 'auth.failed', 'entity' => 'user', 'entity_uuid' => mb_substr($email, 0, 64), 'device_id' => $dev, 'code_hash' => null, 'data' => json_encode(['why' => $why])]);
        if ((int)$db->val("SELECT COUNT(*) FROM {p}gi_audit WHERE action = 'auth.failed' AND entity_uuid = ? AND ts > NOW() - INTERVAL 10 MINUTE", [mb_substr($email, 0, 64)]) >= 8) throw new Err('too_many_attempts', 'try again in 10 minutes', 429);
        $u = $db->row('SELECT * FROM {p}users WHERE email = ?', [$email]);
        if (!$u || !password_verify($pass, (string)$u['password_hash']) || $u['status'] !== 'active') { $fail('credentials'); throw new Err('invalid_credentials', 'wrong e-mail or password', 401); }
        $st = $db->row("SELECT st.warehouse_id, w.code, w.name FROM {p}gi_staff st JOIN {p}gi_warehouses w ON w.id = st.warehouse_id AND w.active = 1 WHERE st.user_id = ? AND st.role = 'operator'", [$u['id']]);
        if (!$st) { $fail('not_operator'); throw new Err('invalid_credentials', 'wrong e-mail or password', 401); }
        $d = $db->row('SELECT * FROM {p}gi_devices WHERE user_id = ? AND device_id = ?', [$u['id'], $dev]);
        if (!$d) {   // the first device of a user is trusted on first use; every other one waits for the office
            $first = !(int)$db->val('SELECT COUNT(*) FROM {p}gi_devices WHERE user_id = ?', [$u['id']]);
            $db->q('INSERT INTO {p}gi_devices (user_id, device_id, label, secret, status, created_at) VALUES (?,?,?,?,?,NOW())', [$u['id'], $dev, mb_substr(trim((string)($in['device_label'] ?? '')), 0, 120), bin2hex(random_bytes(32)), $first ? 'approved' : 'pending']);
            $d = $db->row('SELECT * FROM {p}gi_devices WHERE user_id = ? AND device_id = ?', [$u['id'], $dev]);
            $store->audit(['ts' => $now(), 'client_ts' => null, 'actor' => 'operator:' . $u['name'], 'action' => $first ? 'device.bound' : 'device.pending', 'entity' => 'device', 'entity_uuid' => $dev, 'device_id' => $dev, 'code_hash' => null, 'data' => json_encode(['user' => (int)$u['id']])]);
        }
        if ($d['status'] !== 'approved') throw new Err($d['status'] === 'pending' ? 'device_pending' : 'device_revoked', $d['status'] === 'pending' ? 'this device waits for approval by the head office' : 'this device was revoked', 403);
        $token = bin2hex(random_bytes(32));
        $db->q('DELETE FROM {p}gi_sessions WHERE expires_at < NOW()');
        $db->q('INSERT INTO {p}gi_sessions (token_hash, user_id, device_id, expires_at, created_at) VALUES (?,?,?,NOW() + INTERVAL 12 HOUR,NOW())', [hash('sha256', $token), $u['id'], $dev]);
        $store->audit(['ts' => $now(), 'client_ts' => null, 'actor' => 'operator:' . $u['name'], 'action' => 'auth.login', 'entity' => 'user', 'entity_uuid' => (string)$u['id'], 'device_id' => $dev, 'code_hash' => null, 'data' => '{}']);
        $c = $cfg();
        return ['token' => $token, 'expires_in' => 43200, 'device_key' => $d['secret'], 'user' => ['name' => $u['name']], 'warehouse' => ['id' => (int)$st['warehouse_id'], 'code' => $st['code'], 'name' => $st['name']],
            'config' => ['id_threshold_rub' => Units::fmt($c['id_threshold_kop']), 'code_ttl_hours' => intdiv($c['code_ttl'], 3600)], 'server_time' => time()];
    });
    $endpoint('POST', 'auth/logout', 'app', function ($p, $in, $a) use ($db, $hdr) {
        $t = $hdr('X-GI-Token'); if ($t !== '') $db->q('DELETE FROM {p}gi_sessions WHERE token_hash = ?', [hash('sha256', $t)]);
        return ['ok' => true];
    });
    $endpoint('GET', 'me', 'app', fn($p, $in, $a) => ['user' => $a['name'], 'warehouse_id' => $a['warehouse'], 'server_time' => time()]);

    /* ---- manifests ---- */
    $endpoint('GET', 'manifests', 'app', function ($p, $in, $a) use ($db) {
        $rows = $db->rows("SELECT uuid, status, checksum, created_at, (SELECT COUNT(*) FROM {p}gi_orders o WHERE o.transfer_uuid = t.uuid) AS orders FROM {p}gi_transfers t WHERE warehouse_id = ? AND (status IN ('created','transferred') OR created_at > NOW() - INTERVAL 30 DAY) ORDER BY created_at DESC LIMIT 100", [$a['warehouse']]);
        return ['manifests' => array_map(fn($r) => ['uuid' => $r['uuid'], 'status' => $r['status'], 'checksum' => $r['checksum'], 'created_at' => $r['created_at'], 'orders' => (int)$r['orders']], $rows)];
    });
    $endpoint('GET', 'manifests/{uuid}', 'app', fn($p, $in, $a) => $run('T' . $p['uuid'], fn() => $ex()->deliver(strtolower((string)$p['uuid']), $a)));
    $endpoint('POST', 'manifests/{uuid}/receipt', 'app', fn($p, $in, $a) => $run('T' . $p['uuid'], fn() => $ex()->receipt(strtolower((string)$p['uuid']), $in, $a)));

    /* ---- orders ---- */
    $endpoint('GET', 'orders', 'app', function ($p, $in, $a) use ($db, $ex) {
        $st = array_values(array_intersect(array_filter(explode(',', (string)($_GET['status'] ?? 'ready_for_issue,blocked,return_requested'))), Machine::STATUSES)) ?: ['ready_for_issue'];
        $q = trim((string)($_GET['q'] ?? '')); $w = ['warehouse_id = ?', 'status IN (' . implode(',', array_fill(0, count($st), '?')) . ')']; $args = array_merge([$a['warehouse']], $st);
        if ($q !== '') { $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%'; $w[] = '(number LIKE ? OR customer_name LIKE ? OR customer_phone LIKE ?)'; array_push($args, $like, $like, $like); }
        $rows = $db->rows('SELECT * FROM {p}gi_orders WHERE ' . implode(' AND ', $w) . ' ORDER BY updated_at DESC LIMIT 100', $args);
        $e = $ex(); return ['orders' => array_map(fn($o) => $e->orderView($o), $rows)];
    });
    $endpoint('GET', 'orders/{uuid}', 'app', function ($p, $in, $a) use ($store, $ex) {
        $o = $store->order(strtolower((string)$p['uuid'])); if (!$o || (int)$o['warehouse_id'] !== (int)$a['warehouse']) throw new Err('not_found', 'not found', 404);
        return $ex()->orderView($o);
    });
    $endpoint('POST', 'orders/{uuid}/issue', 'app', function ($p, $in, $a) use ($run, $ex) {
        $res = $run('O' . $p['uuid'], fn() => $ex()->issue(strtolower((string)$p['uuid']), $in, $a));
        if (empty($res['replayed']) && !empty($res['act']['evidence_sha256']) && is_string($in['signature'] ?? null) && strlen($in['signature']) < 900000) {   // the customer's signature / photo is kept next to the act, named by the document uuid
            $dir = SF_ROOT . '/storage/goodsissue'; if (!is_dir($dir)) @mkdir($dir, 0750, true);
            if (@file_put_contents($dir . '/' . $res['doc_uuid'] . '.txt', $in['signature']) === false) \Sysfera\App::log('goodsissue: cannot save the evidence of ' . $res['doc_uuid']);
        }
        return $res;
    });
    $endpoint('POST', 'orders/{uuid}/return', 'app', fn($p, $in, $a) => $run('O' . $p['uuid'], fn() => $ex()->returnAction(strtolower((string)$p['uuid']), $in, $a)));
    $endpoint('POST', 'counts', 'app', fn($p, $in, $a) => $run('W' . $a['warehouse'], fn() => $ex()->count($in, $a)));
    $endpoint('POST', 'sync', 'app', fn($p, $in, $a) => $run('W' . $a['warehouse'], fn() => ['results' => $ex()->sync((array)($in['actions'] ?? []), $a), 'server_time' => time()]));

    /* ---- head-office system (signed with the office key) ---- */
    $endpoint('POST', 'office/transfers', 'office', fn($p, $in, $a) => $run('T' . ($in['uuid'] ?? ''), fn() => $ex()->createTransfer($in, $a)));
    $endpoint('GET', 'office/transfers/{uuid}', 'office', fn($p, $in, $a) => $ex()->transferView(strtolower((string)$p['uuid']), $a));
    $endpoint('GET', 'office/orders/{uuid}', 'office', function ($p, $in, $a) use ($store, $ex) { $o = $store->order(strtolower((string)$p['uuid'])); if (!$o) throw new Err('not_found', 'not found', 404); return $ex()->orderView($o); });
    $endpoint('GET', 'office/incidents', 'office', fn() => ['incidents' => array_map(fn($r) => $r + ['details' => json_decode((string)$r['details'], true)], $db->rows('SELECT * FROM {p}gi_incidents WHERE status = ? ORDER BY id DESC LIMIT 200', [($_GET['status'] ?? 'open') === 'resolved' ? 'resolved' : 'open']))]);
    $endpoint('POST', 'office/orders/{uuid}/resolve', 'office', function ($p, $in, $a) use ($run, $ex) {
        $r = $run('O' . $p['uuid'], fn() => $ex()->resolve(strtolower((string)$p['uuid']), (string)($in['resolution'] ?? ''), mb_substr((string)($in['note'] ?? ''), 0, 300), $a)); unset($r['code']); return $r;   // the code goes to the customer, not to the caller
    });
    $endpoint('POST', 'office/orders/{uuid}/code', 'office', function ($p, $in, $a) use ($run, $ex) { $run('O' . $p['uuid'], fn() => $ex()->newCode(strtolower((string)$p['uuid']), $a)); return ['sent' => true]; });
    $endpoint('POST', 'office/reports', 'office', fn($p, $in, $a) => $ex()->reconcile((int)($in['warehouse_id'] ?? 0), (string)($in['day'] ?? date('Y-m-d')), !empty($in['force'])));
    $endpoint('GET', 'office/reports/{wid}/{day}', 'office', function ($p) use ($store) { $r = $store->report((int)$p['wid'], (string)$p['day']); if (!$r) throw new Err('not_found', 'no report for this day', 404); return json_decode((string)$r['body'], true); });

    /* =============================== the app (PWA) =============================== */
    $shell = function (string $file, string $type): void { header('Content-Type: ' . $type); header('Cache-Control: no-cache'); readfile(__DIR__ . '/assets/' . $file); };
    $appStrings = function (string $lang): array {   // the texts of the app (keys a_*): the chosen language over English
        $all = []; foreach (['en', $lang] as $l) { $f = __DIR__ . '/lang/' . preg_replace('/[^a-z]/', '', $l) . '.php'; if (is_file($f)) $all = array_merge($all, (array)require $f); }
        return array_filter($all, fn($k) => str_starts_with((string)$k, 'a_'), ARRAY_FILTER_USE_KEY);
    };
    $sf->api('GET', 'goodsissue/app', function () use ($sf, $appStrings) {
        header('Content-Type: text/html; charset=utf-8'); header('Cache-Control: no-cache'); header('Referrer-Policy: no-referrer');
        $v = e($sf->manifest['version']); $lang = \Sysfera\I18n::lang();
        echo '<!doctype html><html lang="' . e($lang) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#1f6feb"><title>' . e($sf->t('app_title')) . '</title>'
            . '<link rel="manifest" href="' . e(url('/api/goodsissue/app/manifest.webmanifest')) . '"><link rel="stylesheet" href="' . e($sf->asset('app.css')) . '?v=' . $v . '"></head><body><div id="app"></div>'
            . '<script>window.GI=' . json_encode(['api' => url('/api/goodsissue/v1/'), 'sw' => url('/api/goodsissue/app/sw.js'), 'scope' => url('/api/goodsissue/'), 'lang' => $lang, 'v' => $sf->manifest['version'], 't' => $appStrings($lang)], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . ';</script>'
            . '<script src="' . e($sf->asset('app.js')) . '?v=' . $v . '"></script></body></html>';
    });
    $sf->api('GET', 'goodsissue/app/manifest.webmanifest', function () use ($sf) {
        header('Content-Type: application/manifest+json'); echo json_encode(['name' => $sf->t('app_title'), 'short_name' => 'Goods issue', 'start_url' => url('/api/goodsissue/app'), 'scope' => url('/api/goodsissue/'), 'display' => 'standalone', 'background_color' => '#ffffff', 'theme_color' => '#1f6feb',
            'icons' => [['src' => $sf->asset('icon.svg'), 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any']]], JSON_UNESCAPED_SLASHES);
    });
    $sf->api('GET', 'goodsissue/app/sw.js', function () use ($shell) { header('Service-Worker-Allowed: ' . url('/api/goodsissue/')); $shell('sw.js', 'application/javascript; charset=utf-8'); });

    /* =============================== head office pages =============================== */
    $route = function (string $method, string $path, callable $fn) use ($sf, $isOffice): void {
        $sf->route($method, $path, function (array $p = []) use ($fn, $isOffice, $sf) {
            if (!$isOffice()) { http_response_code(403); echo '<div class="card empty"><h3>' . e($sf->t('forbidden')) . '</h3></div>'; return; }
            return $fn($p);
        });
    };
    $page = fn(string $view, array $vars, string $title, string $tab) => $sf->render($view, $vars + ['tab' => $tab, 'cfg' => $cfg()], $title);
    $whs = fn(): array => $db->rows('SELECT * FROM {p}gi_warehouses ORDER BY name');
    $actor = fn(): array => ['kind' => 'office', 'user' => $sf->auth()->id(), 'name' => (string)($sf->auth()->user()['name'] ?? 'office'), 'warehouse' => null, 'device' => null];
    $back = fn(string $path) => redirect('/p/goodsissue' . $path);
    $guard = function (callable $fn) use ($sf, $back) { try { return $fn(); } catch (Err $e) { flash($sf->t('err_' . $e->errCode) !== 'goodsissue.err_' . $e->errCode ? $sf->t('err_' . $e->errCode) : $e->getMessage(), 'bad'); return null; } };

    $route('GET', '/', function () use ($db, $page, $whs, $sf) {
        $counts = array_column($db->rows('SELECT status, COUNT(*) n FROM {p}gi_orders GROUP BY status'), 'n', 'status');
        $page('dashboard', ['counts' => $counts, 'incidents' => $db->rows("SELECT i.*, w.name wname FROM {p}gi_incidents i LEFT JOIN {p}gi_warehouses w ON w.id = i.warehouse_id WHERE i.status = 'open' ORDER BY i.id DESC LIMIT 8"),
            'transfers' => $db->rows('SELECT t.*, w.name wname, (SELECT COUNT(*) FROM {p}gi_orders o WHERE o.transfer_uuid = t.uuid) AS orders FROM {p}gi_transfers t LEFT JOIN {p}gi_warehouses w ON w.id = t.warehouse_id ORDER BY t.created_at DESC LIMIT 8'),
            'whs' => $whs(), 'appUrl' => url('/api/goodsissue/app'), 'ops' => (int)$db->val("SELECT COUNT(*) FROM {p}gi_staff WHERE role = 'operator'")], $sf->t('menu'), 'dashboard');
    });
    $sf->route('GET', '/desk', function () use ($isOffice, $isOperator) { redirect(($isOperator() || $isOffice()) ? '/api/goodsissue/app' : '/'); });

    /* ---- transfers ---- */
    $csv = function (int $wid, string $text): array {
        $orders = []; $n = 0;
        foreach (preg_split('/\R/u', trim($text)) ?: [] as $line) {
            if (trim($line) === '' || str_starts_with(trim($line), '#')) continue; $c = array_map('trim', str_getcsv($line, ';')); $n++;
            if (count($c) < 9) throw new Err('csv_columns', "line $n: 9 columns needed: order;customer;phone;email;sku;name;qty;weight_g;barcode;price_rub", 422);
            [$no, $cust, $phone, $mail, $sku, $name, $qty, $g, $bc, $price] = array_pad($c, 10, '');
            if ($price === '' && count($c) === 9) { [$no, $cust, $phone, $sku, $name, $qty, $g, $bc, $price] = $c; $mail = ''; }
            if ($bc === '') $bc = 'GI' . substr(str_replace('.', '', sprintf('%.6f', microtime(true))), -10) . random_int(10, 99);
            $orders[$no] ??= ['uuid' => Manifest::newUuid(), 'number' => $no, 'customer_name' => $cust, 'customer_phone' => $phone, 'customer_email' => $mail, 'items' => [], '_k' => 0];
            $q = Units::qty(ctype_digit($qty) ? (int)$qty : $qty, 'qty'); $orders[$no]['_k'] += Units::kop(Units::rub(str_replace(',', '.', $price))) * $q;
            $orders[$no]['items'][] = ['sku' => $sku, 'name' => $name, 'qty' => $q, 'weight_g' => ctype_digit($g) ? (int)$g : $g, 'barcode' => $bc, 'price_rub' => str_replace(',', '.', $price)];
        }
        foreach ($orders as &$o) { $o['total_rub'] = Units::fmt($o['_k']); unset($o['_k']); } unset($o);
        return ['uuid' => Manifest::newUuid(), 'warehouse_id' => $wid, 'units' => ['weight' => 'g', 'money' => 'RUB'], 'orders' => array_values($orders)];
    };
    $route('GET', '/transfers', function () use ($db, $page, $whs, $sf) {
        $page('transfers', ['rows' => $db->rows('SELECT t.*, w.name wname, (SELECT COUNT(*) FROM {p}gi_orders o WHERE o.transfer_uuid = t.uuid) AS orders FROM {p}gi_transfers t LEFT JOIN {p}gi_warehouses w ON w.id = t.warehouse_id ORDER BY t.created_at DESC LIMIT 100'), 'whs' => $whs()], $sf->t('nav_transfers'), 'transfers');
    });
    $route('POST', '/transfers', function () use ($sf, $ex, $run, $csv, $actor, $back, $guard) {
        $wid = (int)($_POST['warehouse_id'] ?? 0); $do = (string)($_POST['do'] ?? 'create');
        $r = $guard(function () use ($do, $wid, $csv, $run, $ex, $actor) {
            if ($do === 'demo') { $n = 0; foreach (Seed::transfers($wid) as $t) { $r = $run('T' . $t['uuid'], fn() => $ex()->createTransfer($t, $actor())); $n += empty($r['replayed']) ? 1 : 0; } return $n; }
            $m = $csv($wid, (string)($_POST['lines'] ?? '')); $run('T' . $m['uuid'], fn() => $ex()->createTransfer($m, $actor())); return $m['uuid'];
        });
        if ($r !== null) flash($sf->t($do === 'demo' ? 'demo_loaded' : 'transfer_created'));
        $back($do === 'create' && is_string($r) ? '/transfers/' . $r : '/transfers');
    });
    $route('GET', '/transfers/{uuid}', function (array $p) use ($db, $page, $store, $ex, $sf) {
        $t = $store->transfer(strtolower((string)$p['uuid'])); if (!$t) { http_response_code(404); echo e($sf->t('not_found')); return; }
        $page('transfer', ['t' => $t, 'wh' => $db->row('SELECT * FROM {p}gi_warehouses WHERE id = ?', [$t['warehouse_id']]), 'orders' => $store->ordersOfTransfer($t['uuid']), 'items' => $store->items($t['uuid']),
            'log' => $db->rows('SELECT * FROM {p}gi_audit WHERE entity_uuid = ? ORDER BY id DESC LIMIT 50', [$t['uuid']])], $sf->t('nav_transfers'), 'transfers');
    });

    /* ---- orders ---- */
    $route('GET', '/orders', function () use ($db, $page, $sf) {
        $st = (string)($_GET['status'] ?? ''); $q = trim((string)($_GET['q'] ?? '')); $w = ['1=1']; $a = [];
        if (in_array($st, Machine::STATUSES, true)) { $w[] = 'o.status = ?'; $a[] = $st; }
        if ($q !== '') { $l = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%'; $w[] = '(o.number LIKE ? OR o.customer_name LIKE ? OR o.customer_phone LIKE ?)'; array_push($a, $l, $l, $l); }
        $page('orders', ['rows' => $db->rows('SELECT o.*, w.name wname FROM {p}gi_orders o LEFT JOIN {p}gi_warehouses w ON w.id = o.warehouse_id WHERE ' . implode(' AND ', $w) . ' ORDER BY o.updated_at DESC LIMIT 200', $a), 'st' => $st, 'q' => $q], $sf->t('nav_orders'), 'orders');
    });
    $route('GET', '/orders/{uuid}', function (array $p) use ($db, $page, $store, $sf) {
        $o = $store->order(strtolower((string)$p['uuid'])); if (!$o) { http_response_code(404); echo e($sf->t('not_found')); return; }
        $page('order', ['o' => $o, 'items' => $store->orderItems($o['uuid']), 'code' => $store->code($o['uuid']), 'wh' => $db->row('SELECT * FROM {p}gi_warehouses WHERE id = ?', [$o['warehouse_id']]),
            'incidents' => $db->rows('SELECT * FROM {p}gi_incidents WHERE ref = ? ORDER BY id DESC', [$o['uuid']]), 'log' => $db->rows('SELECT * FROM {p}gi_audit WHERE entity_uuid = ? ORDER BY id DESC LIMIT 60', [$o['uuid']])], '#' . $o['number'], 'orders');
    });
    $route('POST', '/orders/{uuid}', function (array $p) use ($sf, $ex, $run, $actor, $back, $guard) {
        $u = strtolower((string)$p['uuid']); $do = (string)($_POST['do'] ?? ''); $note = mb_substr(trim((string)($_POST['note'] ?? '')), 0, 300);
        $r = $guard(fn() => $run('O' . $u, fn() => $do === 'newcode' ? ['code' => $ex()->newCode($u, $actor())] : $ex()->resolve($u, $do === 'return' ? 'return' : 'release', $note, $actor())));
        if ($r !== null && !empty($r['code'])) flash($sf->t('code_is', ['code' => $r['code']]) . ' ' . $sf->t('code_once'));
        elseif ($r !== null) flash($sf->t('saved'));
        $back('/orders/' . $u);
    });

    /* ---- incidents ---- */
    $route('GET', '/incidents', function () use ($db, $page, $sf) {
        $st = ($_GET['status'] ?? 'open') === 'resolved' ? 'resolved' : 'open';
        $page('incidents', ['rows' => $db->rows('SELECT i.*, w.name wname FROM {p}gi_incidents i LEFT JOIN {p}gi_warehouses w ON w.id = i.warehouse_id WHERE i.status = ? ORDER BY i.id DESC LIMIT 200', [$st]), 'st' => $st], $sf->t('nav_incidents'), 'incidents');
    });
    $route('POST', '/incidents', function () use ($store, $sf, $actor, $back) {
        $store->closeIncident((int)($_POST['id'] ?? 0), $actor()['name'], mb_substr(trim((string)($_POST['note'] ?? '')), 0, 300)); flash($sf->t('saved')); $back('/incidents');
    });

    /* ---- reconciliation ---- */
    $route('GET', '/reports', function () use ($db, $page, $whs, $sf) {
        $page('reports', ['rows' => $db->rows('SELECT r.warehouse_id, r.day, r.created_at, w.name wname FROM {p}gi_reports r LEFT JOIN {p}gi_warehouses w ON w.id = r.warehouse_id ORDER BY r.day DESC, r.warehouse_id LIMIT 90'), 'whs' => $whs()], $sf->t('nav_reports'), 'reports');
    });
    $route('POST', '/reports', function () use ($db, $ex, $sf, $back, $guard) {
        $day = (string)($_POST['day'] ?? date('Y-m-d')); $wid = (int)($_POST['warehouse_id'] ?? 0); $n = 0;
        $ids = $wid ? [$wid] : array_map('intval', array_column($db->rows('SELECT id FROM {p}gi_warehouses WHERE active = 1'), 'id'));
        foreach ($ids as $i) { $r = $guard(fn() => $ex()->reconcile($i, $day, !empty($_POST['force']))); if ($r !== null) $n++; }
        if ($n) flash($sf->t('report_done', ['n' => $n]));
        $back($wid ? "/reports/$wid/$day" : '/reports');
    });
    $route('GET', '/reports/{wid}/{day}', function (array $p) use ($store, $db, $page, $sf) {
        $r = $store->report((int)$p['wid'], (string)$p['day']); if (!$r) { http_response_code(404); echo e($sf->t('not_found')); return; }
        $page('report', ['r' => json_decode((string)$r['body'], true), 'wh' => $db->row('SELECT * FROM {p}gi_warehouses WHERE id = ?', [(int)$p['wid']])], $sf->t('nav_reports') . ' ' . $p['day'], 'reports');
    });

    /* ---- warehouses, staff, devices ---- */
    $route('GET', '/warehouses', function () use ($db, $page, $whs, $sf) {
        $page('warehouses', ['whs' => $whs(), 'staff' => $db->rows('SELECT st.*, u.name, u.email, w.name wname FROM {p}gi_staff st JOIN {p}users u ON u.id = st.user_id LEFT JOIN {p}gi_warehouses w ON w.id = st.warehouse_id ORDER BY st.role, u.name'),
            'users' => $db->rows("SELECT id, name, email FROM {p}users WHERE status = 'active' AND role <> 'admin' ORDER BY name LIMIT 500"),
            'devices' => $db->rows('SELECT d.*, u.name, u.email FROM {p}gi_devices d JOIN {p}users u ON u.id = d.user_id ORDER BY d.status = \'pending\' DESC, d.id DESC LIMIT 200')], $sf->t('nav_warehouses'), 'warehouses');
    });
    $route('POST', '/warehouses', function () use ($db, $sf, $back, $store, $actor, $now) {
        $do = (string)($_POST['do'] ?? '');
        if ($do === 'add_wh') {
            $code = strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', (string)($_POST['code'] ?? ''))); $name = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 160);
            if ($code === '' || $name === '') flash($sf->t('e_fill'), 'bad');
            elseif ($db->val('SELECT 1 FROM {p}gi_warehouses WHERE code = ?', [$code])) flash($sf->t('e_code_taken'), 'bad');
            else { $db->q('INSERT INTO {p}gi_warehouses (code, name) VALUES (?,?)', [$code, $name]); flash($sf->t('saved')); }
        } elseif ($do === 'toggle_wh') { $db->q('UPDATE {p}gi_warehouses SET active = 1 - active WHERE id = ?', [(int)($_POST['id'] ?? 0)]);
        } elseif ($do === 'add_staff') {
            $uid = (int)($_POST['user_id'] ?? 0); $role = ($_POST['role'] ?? '') === 'office' ? 'office' : 'operator'; $wid = (int)($_POST['warehouse_id'] ?? 0);
            if (!$db->val("SELECT 1 FROM {p}users WHERE id = ? AND role <> 'admin'", [$uid]) || ($role === 'operator' && !$db->val('SELECT 1 FROM {p}gi_warehouses WHERE id = ?', [$wid]))) flash($sf->t('e_fill'), 'bad');
            else { $db->q('INSERT INTO {p}gi_staff (user_id, role, warehouse_id) VALUES (?,?,?) ON DUPLICATE KEY UPDATE role = VALUES(role), warehouse_id = VALUES(warehouse_id)', [$uid, $role, $role === 'operator' ? $wid : null]); flash($sf->t('saved')); }
        } elseif ($do === 'del_staff') { $db->q('DELETE FROM {p}gi_staff WHERE user_id = ?', [(int)($_POST['user_id'] ?? 0)]); $db->q('DELETE FROM {p}gi_sessions WHERE user_id = ?', [(int)($_POST['user_id'] ?? 0)]);
        } elseif (in_array($do, ['dev_approve', 'dev_revoke'], true)) {
            $d = $db->row('SELECT * FROM {p}gi_devices WHERE id = ?', [(int)($_POST['id'] ?? 0)]);
            if ($d) {
                $db->q('UPDATE {p}gi_devices SET status = ? WHERE id = ?', [$do === 'dev_approve' ? 'approved' : 'revoked', $d['id']]);
                if ($do === 'dev_revoke') $db->q('DELETE FROM {p}gi_sessions WHERE user_id = ? AND device_id = ?', [$d['user_id'], $d['device_id']]);
                $store->audit(['ts' => $now(), 'client_ts' => null, 'actor' => 'office:' . $actor()['name'], 'action' => $do === 'dev_approve' ? 'device.approved' : 'device.revoked', 'entity' => 'device', 'entity_uuid' => $d['device_id'], 'device_id' => null, 'code_hash' => null, 'data' => json_encode(['user' => (int)$d['user_id']])]);
            }
        }
        $back('/warehouses');
    });

    /* ---- audit log (read only) ---- */
    $route('GET', '/audit', function () use ($db, $page, $sf) {
        $e = trim((string)($_GET['e'] ?? '')); $a = trim((string)($_GET['a'] ?? ''));
        $w = ['1=1']; $args = []; if ($e !== '') { $w[] = 'entity_uuid = ?'; $args[] = $e; } if ($a !== '') { $w[] = 'action LIKE ?'; $args[] = $a . '%'; }
        $verify = null;
        if (isset($_GET['verify'])) {   // walk the whole chain in slices
            $prev = Audit::GENESIS; $after = 0; $bad = null; $n = 0;
            while (true) { $rows = $db->rows('SELECT * FROM {p}gi_audit WHERE id > ? ORDER BY id LIMIT 5000', [$after]); if (!$rows) break; [$bad, $prev] = Audit::verifyFrom($rows, $prev); $n += count($rows); if ($bad !== null) break; $after = (int)end($rows)['id']; }
            $verify = ['bad' => $bad, 'n' => $n];
        }
        $page('audit', ['rows' => $db->rows('SELECT * FROM {p}gi_audit WHERE ' . implode(' AND ', $w) . ' ORDER BY id DESC LIMIT 200', $args), 'e' => $e, 'a' => $a, 'verify' => $verify], $sf->t('nav_audit'), 'audit');
    });

    /* ---- settings ---- */
    $route('GET', '/settings', function () use ($page, $sf, $secret) {
        $page('settings', ['officeKey' => $secret('office_key'), 'base' => url('/api/goodsissue/v1/'), 'appUrl' => url('/api/goodsissue/app'), 'https' => !empty($_SERVER['HTTPS']) || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https',
            'v' => ['id_threshold' => $sf->setting('id_threshold', '30000'), 'code_ttl_h' => $sf->setting('code_ttl_h', '48'), 'overdue_days' => $sf->setting('overdue_days', '7')]], $sf->t('nav_settings'), 'settings');
    });
    $route('POST', '/settings', function () use ($sf, $back) {
        if (($_POST['do'] ?? '') === 'newkey') { $sf->setSetting('office_key', bin2hex(random_bytes(32))); flash($sf->t('key_new')); $back('/settings'); }
        try { $sf->setSetting('id_threshold', Units::rub(str_replace(',', '.', trim((string)($_POST['id_threshold'] ?? '30000'))))); } catch (Err) { flash($sf->t('err_unit_money'), 'bad'); $back('/settings'); }
        $sf->setSetting('code_ttl_h', (string)max(1, min(720, (int)($_POST['code_ttl_h'] ?? 48)))); $sf->setSetting('overdue_days', (string)max(1, min(60, (int)($_POST['overdue_days'] ?? 7))));
        flash($sf->t('saved')); $back('/settings');
    });
};
