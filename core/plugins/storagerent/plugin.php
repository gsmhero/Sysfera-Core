<?php
declare(strict_types=1);

use SysferaStoragerent\{Err, Ctx, Billing, Wrms, Ledger, Notify, Reports, Offers, Pages, Files, Xlsx, Text, DbStore, Contact, OwnerStore};

/**
 * Sysfera Storage Rent: two tabs under one menu entry.
 *  1) WRMS - a Warehouse Rental Management System for the owner of a warehouse: units, tenants, contracts, invoices, payments, penalties, utilities, notifications,
 *     reports and exports, a tenant portal and a manual on-site access log;
 *  2) Offers - public listings of storage for rent with a Yandex map, for every Sysfera user and, on the landing page, for guests.
 * The rules live in lib/ (tested without a database); this file wires them to the platform: users and roles (capabilities storagerent.admin / .manager / .accountant, and a
 * tenant = a Sysfera user linked to a tenant record), settings, the mailer, uploads and the pages. Money is kopecks, dates are DD.MM.YYYY on screen.
 */
return function (\Sysfera\Plugin $sf): void {
    foreach (['Err', 'Store', 'Billing', 'Ctx', 'Wrms', 'Ledger', 'Notify', 'Reports', 'Xlsx', 'Offers', 'Files', 'Text', 'Pages', 'DbStore', 'Contact', 'OwnerStore'] as $c) require_once __DIR__ . "/lib/$c.php";

    $L = ['en' => require __DIR__ . '/lang/en.php', 'ru' => require __DIR__ . '/lang/ru.php'];
    $lang = function () use ($sf): string { $l = (string)($sf->auth()->user()['lang'] ?? ''); if ($l === '') $l = \Sysfera\I18n::lang(); return $l === 'en' ? 'en' : 'ru'; };
    $tr = function (string $k, array $v = []) use ($L, $lang): string { $s = $L[$lang()][$k] ?? $L['en'][$k] ?? $k; foreach ($v as $a => $b) $s = str_replace('{' . $a . '}', (string)$b, $s); return $s; };
    $today = fn(): string => date('Y-m-d');
    /* ---------- values kept once on the hub (core.sysfera.ru): the map key and the feeds of all connected sites. What an administrator types on a site wins over the hub's value ---------- */
    $mdir = dirname($sf->dir) . '/messenger/lib/';
    foreach (['Util', 'Hub', 'Client'] as $lf) if (is_file($mdir . "$lf.php")) require_once $mdir . "$lf.php";
    $hostNow = fn(): string => (string)preg_replace('/:\d+$/', '', strtolower((string)($_SERVER['HTTP_HOST'] ?? '')));
    /** the public feeds of all sites connected to the hub (and the hub itself), without $exclude (the asking site) */
    $feedsOf = function (\Sysfera\Db $hdb, string $exclude) use ($hostNow): array {
        $out = []; $add = function (string $base) use (&$out, $exclude) {
            $p = parse_url(trim($base)); if (!$p || strtolower((string)($p['scheme'] ?? '')) !== 'https' || empty($p['host'])) return;
            $host = strtolower($p['host']); if ($host === $exclude) return;
            $out[$host] = 'https://' . $host . (isset($p['port']) ? ':' . (int)$p['port'] : '') . '/api/storagerent/v1/listings';
        };
        if ($hostNow() !== '') $add('https://' . ($_SERVER['HTTP_HOST'] ?? ''));
        foreach ($hdb->rows('SELECT domain, base FROM {p}msg_installs ORDER BY enrolled_at DESC LIMIT 60') as $r) $add((string)$r['base'] !== '' ? (string)$r['base'] : 'https://' . $r['domain']);
        return array_slice(array_values($out), 0, 60);
    };
    if (class_exists(\SysferaMsg\HubServer::class)) {
        // the hub answers the installations (user 0) with its map key and the feeds of everybody
        \SysferaMsg\HubServer::register('rent.setup', function (\SysferaMsg\Ctx $c, \Sysfera\Db $hdb) use ($sf, $feedsOf) {
            return ['yandex_key' => $sf->setting('yandex_key', ''), 'feeds' => $feedsOf($hdb, strtolower($c->domain))];
        }, true);
    }
    /** a site: what the hub sent, kept for an hour in a file; on the hub itself the feeds come from its own table */
    $hubSetup = function () use ($feedsOf): array {
        static $mem = null; if ($mem !== null) return $mem;
        if ($hub = \Sysfera\Hooks::filter('messenger.hub', null)) { try { return $mem = ['yandex_key' => '', 'feeds' => $feedsOf($hub->db(), '')]; } catch (\Throwable) { return $mem = []; } }
        $file = SF_ROOT . '/storage/storagerent-setup.json';
        $cached = is_file($file) ? (json_decode((string)@file_get_contents($file), true) ?: []) : [];
        $age = is_file($file) ? time() - (int)filemtime($file) : PHP_INT_MAX;
        if ($age >= 3600 && !(is_file($file . '.try') && time() - (int)filemtime($file . '.try') < 300)) {
            @file_put_contents($file . '.try', (string)time());
            $c = \Sysfera\Hooks::filter('messenger.client', null);
            if ($c && method_exists($c, 'ready') && $c->ready()) {
                try { $r = $c->call('GET', 'rent.setup', [], null, null, 4, '0'); if ($r['ok']) { $cached = ['yandex_key' => (string)($r['data']['yandex_key'] ?? ''), 'feeds' => array_values(array_filter((array)($r['data']['feeds'] ?? []), 'is_string'))]; @file_put_contents($file, json_encode($cached)); } } catch (\Throwable) {}
            }
        }
        return $mem = $cached;
    };
    /** the settings the module reads: the map key is the site's own, else the hub's ("yandex_key_local" / "yandex_key_hub" give the two parts for the settings page) */
    $eff = function (string $k, string $d = '') use ($sf, $hubSetup): string {
        if ($k === 'yandex_key_local') return (string)$sf->setting('yandex_key', $d);
        if ($k === 'yandex_key_hub') return (string)($hubSetup()['yandex_key'] ?? '');
        if ($k === 'yandex_key') { $v = (string)$sf->setting('yandex_key', ''); return $v !== '' ? $v : ((string)($hubSetup()['yandex_key'] ?? '') ?: $d); }
        return (string)$sf->setting($k, $d);
    };
    /** The services on a store limited to one owner ($scope = user id; $legacy: plus the rows of the site itself) or, with scope 0, on the whole site (staff, the daily run). */
    $svcFor = function (int $scope, bool $legacy = false) use ($sf, $today, $eff): array {
        static $cache = []; $key = $scope . ($legacy ? 'L' : ''); if (isset($cache[$key])) return $cache[$key];
        $st = new OwnerStore(new DbStore($sf->db()), $scope, $legacy); $now = fn() => time();
        $wr = new Wrms($st, $now, $today); $lg = new Ledger($st, $now, $today); $rp = new Reports($st, $lg, $wr, $today);
        $site = \Sysfera\App::get()->setting('site_name', 'Sysfera');
        $nf = new Notify($st, $lg, $now, $today, fn($to, $s, $b) => $sf->mail($to, $s, $b, null), $site);
        $of = new Offers($st, $now);
        $photo = fn(array $p, int $id) => url('/p/storagerent/offers/photo/' . (int)$p['id']);
        $pg = new Pages($st, $wr, $lg, $rp, $nf, $of, fn($k, $d = '') => $eff($k, $d), $photo, $today);
        return $cache[$key] = compact('st', 'wr', 'lg', 'rp', 'nf', 'of', 'pg');
    };
    /** The staff list kept in the setting "staff", one "user id:role" per line (roles: admin, manager, accountant) => [user id => role]. */
    $staffMap = function () use ($sf): array {
        $m = []; foreach (preg_split('/\R/', (string)$sf->setting('staff', '')) ?: [] as $line) if (preg_match('/^\s*(\d{1,9})\s*[:=]\s*(admin|manager|accountant)\s*$/', $line, $x)) $m[(int)$x[1]] = $x[2];
        return $m;
    };
    $ctx = function () use ($sf, $svcFor, $staffMap): Ctx {
        static $c = null; if ($c) return $c; $u = $sf->auth()->user(); if (!$u) return $c = Ctx::guest();
        $all = $svcFor(0); $t = $all['wr']->tenantOfUser((int)$u['id']);
        // Staff roles come from the module's own list (Settings > Staff), NOT from platform capabilities: the platform may grant every capability to every new user.
        $role = $staffMap()[(int)$u['id']] ?? null;
        $owners = $sf->setting('owners_open', '1') !== '0';   // every signed-in user manages their own storages (the administrator may switch it off in the settings)
        return $c = Ctx::for((int)$u['id'], $sf->auth()->isAdmin(), $role ? ['storagerent.' . $role] : [], $t ? (int)$t['id'] : null, $owners, $t && $all['st']->one('warehouses', ['owner_id' => (int)$u['id']]) !== null);
    };
    /** Whose data the current user works with: an owner and a platform administrator without a staff role see only their own storages (the administrator also the site's older rows); staff and tenants are limited by their role. */
    $svc = function () use ($svcFor, $ctx, $staffMap, $sf): array {
        $c = $ctx();
        if ($c->owner()) return $svcFor($c->uid);
        if ($c->role === 'admin' && $sf->auth()->isAdmin() && !isset($staffMap()[$c->uid])) return $svcFor($c->uid, true);
        return $svcFor(0);
    };
    $abs = function (string $path): string { $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'; return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $path; };

    /** Common variables of every view. */
    $common = fn(): array => ['sf' => $sf, 'mapKey' => $eff('yandex_key'), 'ctx' => $ctx(), 'lang' => $lang(), 'L' => $L[$lang()], 'regOpen' => \Sysfera\App::get()->setting('registration', '0') === '1'];
    $render = function (array $page) use ($sf, $common, $tr, $ctx): void {
        if ($ctx()->uid <= 0 && empty($page['bare'])) {   // a guest sees the public pages (offers) in a plain shell: the platform layout needs a signed-in user
            $inner = \Sysfera\View::file($sf->dir . '/views/' . $page['view'] . '.php', $page + $common());
            header('Content-Type: text/html; charset=utf-8');
            echo \Sysfera\View::file($sf->dir . '/views/_guest.php', ['sf' => $sf, 'content' => $inner, 'title' => $tr($page['title'] ?? 'title'), 'open' => \Sysfera\App::get()->setting('registration', '0') === '1']);
            return;
        }
        if (!empty($page['bare'])) { header('Content-Type: text/html; charset=utf-8'); echo \Sysfera\View::file($sf->dir . '/views/' . $page['view'] . '.php', $page + $common()); return; }
        $sf->render($page['view'], $page + $common(), $tr($page['title'] ?? 'title'));
    };
    $fail = function (Err $e) use ($tr): string {
        $m = $tr('err_' . $e->errCode); if ($m === 'err_' . $e->errCode) $m = $tr('err_server'); return $m . ($e->errCode === 'unit_overbooked' && $e->getMessage() !== 'unit_overbooked' ? ' (' . $e->getMessage() . ')' : '');
    };
    /** Runs a page builder; refusals become a message and a redirect to the right place. */
    $page = function (callable $build, string $backOnFail = '/') use ($sf, $render, $fail, $ctx): void {
        try { $render($build()); }
        catch (Err $e) {
            if ($e->errCode === 'forbidden' && $ctx()->uid <= 0) redirect('/login');
            if ($e->http === 404) { http_response_code(404); }
            flash($fail($e), 'bad'); redirect($e->errCode === 'forbidden' || $e->http === 404 ? '/p/storagerent/' : $backOnFail);
        }
    };
    /** Runs an action; flash the result and redirect (to $to, or $to($result)). */
    $act = function (callable $do, string|callable $to, string $okKey = 'saved', string $failTo = '') use ($sf, $fail, $tr): void {
        try {
            $r = $do(); if ($okKey !== '') flash($tr($okKey));
            redirect(is_callable($to) ? $to($r) : $to);
        } catch (Err $e) {
            flash($fail($e), 'bad'); redirect($failTo !== '' ? $failTo : (is_string($to) ? $to : '/p/storagerent/'));
        } catch (\Throwable $e) { \Sysfera\App::log('storagerent ' . $e); flash($tr('err_server'), 'bad'); redirect($failTo !== '' ? $failTo : (is_string($to) ? $to : '/p/storagerent/')); }
    };
    $back = fn(string $fallback): string => (isset($_POST['back']) && is_string($_POST['back']) && str_starts_with($_POST['back'], '/p/storagerent/') && !str_contains($_POST['back'], '//') ? $_POST['back'] : $fallback);
    /** Moves an uploaded file from $_FILES[$field] into storage: [stored, name, mime, size] or null when nothing was sent. */
    $upload = function (string $field, bool $image) {
        $f = $_FILES[$field] ?? null; if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
        if ($f['error'] !== UPLOAD_ERR_OK) throw new Err($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE ? 'file_big' : 'file_failed');
        $size = (int)$f['size']; $ext = $image ? Offers::checkPhoto((string)$f['name'], $size) : Wrms::checkUpload((string)$f['name'], $size);
        return [Files::store((string)$f['tmp_name'], $ext, $image || in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)), (string)$f['name'], Files::mime($ext), $size];
    };
    $uploads = function (string $field, bool $image): array {   // several files in one input (name="photos[]")
        $f = $_FILES[$field] ?? null; if (!$f || !is_array($f['name'] ?? null)) return []; $out = [];
        foreach ($f['name'] as $i => $n) { if (($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue; $out[] = ['name' => $n, 'tmp_name' => $f['tmp_name'][$i], 'size' => $f['size'][$i], 'error' => $f['error'][$i]]; }
        return $out;
    };

    /* ---------------------------------------------------------------- the daily run (invoices, statuses, notifications): lazily on staff page views, and by cron */
    $tick = function (bool $force = false) use ($sf, $svcFor, $today): void {
        $day = $today(); if (!$force && $sf->setting('last_tick') === $day) return;
        $sf->setSetting('last_tick', $day);
        try { $s = $svcFor(0); $s['lg']->tick($day); $set = $s['pg']->notifySettings(); $s['nf']->run($set); }
        catch (\Throwable $e) { \Sysfera\App::log('storagerent tick ' . $e); }
    };

    $sf->menu('menu', '/', ['order' => 28, 'icon' => 'box']);
    \Sysfera\Hooks::on('user.deleted', function ($uid) use ($sf) {   // a deleted Sysfera user keeps the tenant record but loses the link; their listings are removed
        $sf->db()->q('UPDATE {p}sr_tenants SET user_id = NULL WHERE user_id = ?', [(int)$uid]);
        $sf->db()->q('UPDATE {p}sr_listings SET status = ? WHERE user_id = ?', ['archived', (int)$uid]);
    });

    /* ================================================================== entry */
    $sf->route('GET', '/', function () use ($ctx, $svc, $tick, $page) {
        $c = $ctx(); if ($c->role === 'tenant') redirect('/p/storagerent/portal');
        if ($c->staff()) { $tick(); redirect('/p/storagerent/wrms'); }
        redirect('/p/storagerent/offers');
    });

    /* ================================================================== WRMS pages (staff) */
    $get = fn(string $path, callable $fn) => $sf->route('GET', $path, $fn);
    $get('/wrms', fn() => $page(fn() => $svc()['pg']->dashboard($ctx())));
    $get('/units', fn() => $page(fn() => $svc()['pg']->units($ctx())));
    $get('/tenants', fn() => $page(fn() => $svc()['pg']->tenants($ctx(), trim((string)($_GET['q'] ?? '')), (string)($_GET['status'] ?? ''))));
    $get('/tenant/{id}', fn(array $p) => $page(fn() => $svc()['pg']->tenant($ctx(), (int)$p['id']), '/p/storagerent/tenants'));
    $get('/contracts', fn() => $page(fn() => $svc()['pg']->contracts($ctx(), (string)($_GET['state'] ?? ''))));
    $get('/contract/{id}', fn(array $p) => $page(fn() => $svc()['pg']->contract($ctx(), (int)$p['id']), '/p/storagerent/contracts'));
    $get('/contract/{id}/edit', fn(array $p) => $page(fn() => $svc()['pg']->contractForm($ctx(), (int)$p['id'], (int)($_GET['tenant'] ?? 0)), '/p/storagerent/contracts'));
    $get('/contract/{id}/print', fn(array $p) => $page(fn() => $svc()['pg']->contractPrint($ctx(), (int)$p['id']), '/p/storagerent/contracts'));
    $get('/invoices', fn() => $page(fn() => $svc()['pg']->invoices($ctx(), (string)($_GET['status'] ?? ''), (int)($_GET['tenant'] ?? 0), trim((string)($_GET['q'] ?? '')))));
    $get('/invoice/{id}', fn(array $p) => $page(fn() => $svc()['pg']->invoice($ctx(), (int)$p['id']), '/p/storagerent/invoices'));
    $get('/invoice/{id}/print', fn(array $p) => $page(fn() => $svc()['pg']->invoicePrint($ctx(), (int)$p['id']), '/p/storagerent/invoices'));
    $get('/payments', fn() => $page(fn() => $svc()['pg']->payments($ctx(), (int)($_GET['tenant'] ?? 0))));
    $get('/utilities', fn() => $page(fn() => $svc()['pg']->utilities($ctx(), (int)($_GET['meter'] ?? 0))));
    $get('/reports', fn() => $page(fn() => $svc()['pg']->reports($ctx(), (string)($_GET['r'] ?? ''), (string)($_GET['from'] ?? ''), (string)($_GET['to'] ?? ''))));
    $get('/notifications', fn() => $page(fn() => $svc()['pg']->notifications($ctx())));
    $get('/access', fn() => $page(fn() => $svc()['pg']->access($ctx())));
    $get('/documents', fn() => $page(fn() => $svc()['pg']->documents($ctx(), trim((string)($_GET['q'] ?? '')))));
    $get('/requests', fn() => $page(fn() => $svc()['pg']->requests($ctx())));
    $get('/portal', fn() => $page(fn() => $svc()['pg']->portal($ctx())));
    $get('/settings', function () use ($page, $svc, $ctx, $sf) {
        if ($ctx()->can('settings') && $sf->setting('cron_token') === '') $sf->setSetting('cron_token', bin2hex(random_bytes(16)));
        $page(fn() => $svc()['pg']->settings($ctx(), $sf->auth()->isAdmin()));
    });

    /* ---------------------------------------------------------------- documents: download and upload */
    $get('/doc/{id}', function (array $p) use ($svc, $ctx) {
        $d = $svc()['wr']->document((int)$p['id']); $c = $ctx();
        if (!$d || !($c->staff() || ($c->role === 'tenant' && (int)($d['tenant_id'] ?? 0) === $c->tenantId))) { http_response_code(404); echo 'Not found'; return; }
        $f = Files::path((string)$d['file_key']); if (!$f) { http_response_code(404); echo 'Not found'; return; }
        $ext = strtolower(pathinfo((string)$d['file_key'], PATHINFO_EXTENSION)); $name = preg_replace('/[^\w.\- ]+/u', '_', (string)$d['file_name']);
        header('Content-Type: ' . Files::mime($ext)); header('X-Content-Type-Options: nosniff'); header('Content-Disposition: ' . (Files::inline($ext) ? 'inline' : 'attachment') . '; filename="' . $name . '"'); header('Content-Length: ' . filesize($f)); header('Cache-Control: private, no-store'); readfile($f);
    });
    $sf->route('POST', '/doc/upload', function () use ($act, $svc, $ctx, $upload, $back) {
        $act(function () use ($svc, $ctx, $upload) {
            $c = $ctx(); $type = (string)($_POST['owner_type'] ?? ''); $oid = (int)($_POST['owner_id'] ?? 0); $wr = $svc()['wr'];
            $tenantId = match ($type) { 'tenant' => $oid, 'contract' => (int)($svc()['st']->one('contracts', ['id' => $oid])['tenant_id'] ?? 0), 'invoice' => (int)($svc()['st']->one('invoices', ['id' => $oid])['tenant_id'] ?? 0), default => 0 };
            if ($tenantId <= 0) throw new Err('not_found', '', 404);
            $isConfirmation = $c->role === 'tenant';   // a tenant may only attach a payment confirmation to their own invoice
            if ($isConfirmation ? !($type === 'invoice' && $c->tenantId === $tenantId) : !$c->can('write')) throw new Err('forbidden', '', 403);
            $u = $upload('file', false) ?? throw new Err('file_empty');
            $wr->addDocument($c, $type, $oid, $tenantId, (string)($_POST['title'] ?? ''), $u[1], $u[0], $u[2], $u[3], $isConfirmation ? 'confirmation' : (string)($_POST['kind'] ?? 'file'));
            return null;
        }, $back('/p/storagerent/documents'), 'doc_saved');
    });
    $sf->route('POST', '/doc/{id}/delete', function (array $p) use ($act, $svc, $ctx, $back) {
        $act(function () use ($p, $svc, $ctx) { $d = $svc()['wr']->deleteDocument($ctx(), (int)$p['id']); if ($d) Files::remove((string)$d['file_key']); }, $back('/p/storagerent/documents'), 'deleted');
    });

    /* ================================================================== WRMS actions */
    $post = fn(string $path, callable $fn) => $sf->route('POST', $path, $fn);
    $post('/warehouse/save', fn() => $act(fn() => $svc()['wr']->saveWarehouse($ctx(), (int)($_POST['id'] ?? 0), $_POST), '/p/storagerent/units'));
    $post('/unit/save', fn() => $act(fn() => $svc()['wr']->saveUnit($ctx(), (int)($_POST['id'] ?? 0), $_POST), '/p/storagerent/units'));
    $post('/unit/{id}/delete', fn(array $p) => $act(fn() => $svc()['wr']->deleteUnit($ctx(), (int)$p['id']), '/p/storagerent/units', 'deleted'));
    $post('/tenant/save', fn() => $act(fn() => $svc()['wr']->saveTenant($ctx(), (int)($_POST['id'] ?? 0), $_POST), fn($t) => '/p/storagerent/tenant/' . $t['id'], 'saved', '/p/storagerent/tenant/' . (int)($_POST['id'] ?? 0)));
    $post('/contract/save', function () use ($act, $svc, $ctx) {
        $in = $_POST; $in['units'] = []; foreach ((array)($_POST['unit_id'] ?? []) as $i => $uid) if ((int)$uid > 0) $in['units'][] = ['unit_id' => (int)$uid, 'share' => $_POST['unit_share'][$i] ?? 100];
        $id = (int)($_POST['id'] ?? 0);
        $act(fn() => $svc()['wr']->saveContract($ctx(), $id, $in), fn($k) => '/p/storagerent/contract/' . $k['id'], 'saved', '/p/storagerent/contract/' . $id . '/edit');
    });
    $post('/contract/{id}/do', function (array $p) use ($act, $svc, $ctx) {
        $id = (int)$p['id']; $do = (string)($_POST['do'] ?? ''); $wr = $svc()['wr']; $lg = $svc()['lg']; $c = $ctx();
        $act(function () use ($do, $id, $wr, $lg, $c) {
            return match ($do) {
                'activate' => $wr->activate($c, $id), 'terminate' => $wr->terminate($c, $id, (string)($_POST['date'] ?? ''), (string)($_POST['reason'] ?? '')),
                'renew' => $wr->renew($c, $id, (string)($_POST['end_date'] ?? ''), (string)($_POST['rate'] ?? '')), 'penalties' => $lg->billPenalties($c, $id) ?? throw new Err('nothing_to_bill'),
                default => throw new Err('bad_request'),
            };
        }, '/p/storagerent/contract/' . $id, 'done');
    });
    $post('/invoice/new', function () use ($act, $svc, $ctx) {
        $items = []; foreach ((array)($_POST['desc'] ?? []) as $i => $d) $items[] = ['description' => $d, 'amount' => $_POST['amount'][$i] ?? '', 'vatable' => !empty($_POST['vatable'][$i])];
        $act(fn() => $svc()['lg']->createManual($ctx(), (int)($_POST['contract_id'] ?? 0), $items, (string)($_POST['due'] ?? ''), (string)($_POST['reason'] ?? ''), empty($_POST['draft'])), fn($id) => '/p/storagerent/invoice/' . $id, 'saved', '/p/storagerent/invoices');
    });
    $post('/invoice/{id}/do', function (array $p) use ($act, $svc, $ctx) {
        $id = (int)$p['id']; $do = (string)($_POST['do'] ?? ''); $lg = $svc()['lg']; $c = $ctx();
        $act(fn() => match ($do) {
            'issue' => $lg->issue($c, $id), 'cancel' => $lg->cancel($c, $id, (string)($_POST['reason'] ?? '')),
            'adjust' => $lg->adjust($c, $id, (string)($_POST['kind'] ?? ''), (string)($_POST['amount'] ?? ''), (string)($_POST['reason'] ?? '')), default => throw new Err('bad_request'),
        }, '/p/storagerent/invoice/' . $id, 'done');
    });
    $post('/payments/add', fn() => $act(fn() => $svc()['lg']->registerPayment($ctx(), (int)($_POST['tenant_id'] ?? 0), ((int)($_POST['contract_id'] ?? 0)) ?: null, (string)($_POST['date'] ?? ''), (string)($_POST['amount'] ?? ''), (string)($_POST['method'] ?? 'bank'), (string)($_POST['reference'] ?? '')), '/p/storagerent/payments', 'payment_saved'));
    $post('/payments/{id}/reassign', function (array $p) use ($act, $svc, $ctx) {
        $act(fn() => $svc()['lg']->reassign($ctx(), (int)$p['id'], (array)($_POST['alloc'] ?? [])), '/p/storagerent/payments', 'done');
    });
    $post('/payments/refund', fn() => $act(fn() => $svc()['lg']->refund($ctx(), (int)($_POST['tenant_id'] ?? 0), (string)($_POST['amount'] ?? ''), (string)($_POST['date'] ?? ''), (string)($_POST['reason'] ?? '')), '/p/storagerent/payments', 'done'));
    $post('/utilities/meter', fn() => $act(fn() => $svc()['lg']->saveMeter($ctx(), (int)($_POST['id'] ?? 0), (int)($_POST['unit_id'] ?? 0), (string)($_POST['type'] ?? ''), (string)($_POST['serial'] ?? ''), (string)($_POST['rate'] ?? ''), (string)($_POST['rollover'] ?? '')), '/p/storagerent/utilities'));
    $post('/utilities/reading', function () use ($act, $svc, $ctx, $upload) {
        $mid = (int)($_POST['meter_id'] ?? 0);
        $act(function () use ($svc, $ctx, $upload, $mid) {
            $c = $ctx(); $c->require('write'); $u = $upload('photo', true); $doc = null;
            $r = null;
            $svc()['st']->atomic(function () use (&$r, $svc, $c, $u, $mid, &$doc) {
                if ($u) $doc = $svc()['wr']->addDocument($c, 'meter', $mid, null, 'meter reading', $u[1], $u[0], $u[2], $u[3], 'meter');
                $r = $svc()['lg']->addReading($c, $mid, (string)($_POST['date'] ?? ''), (string)($_POST['value'] ?? ''), $doc);
            });
            return $r;
        }, '/p/storagerent/utilities?meter=' . $mid);
    });
    $post('/notifications/template', function () use ($act, $svc, $ctx) {
        $act(function () use ($svc, $ctx) { $n = $svc()['nf']; if (!empty($_POST['reset'])) $n->resetTemplate($ctx(), (string)($_POST['event'] ?? ''), (string)($_POST['lang'] ?? '')); else $n->saveTemplate($ctx(), (string)($_POST['event'] ?? ''), (string)($_POST['lang'] ?? ''), (string)($_POST['subject'] ?? ''), (string)($_POST['body'] ?? '')); }, '/p/storagerent/notifications');
    });
    $post('/notifications/run', fn() => $act(function () use ($svc, $ctx, $tr) { $ctx()->require('write'); if ($ctx()->owner()) throw new Err('forbidden', '', 403); [$s, $f, $k] = $svc()['nf']->run($svc()['pg']->notifySettings()); flash($tr('notify_ran', ['sent' => $s, 'failed' => $f, 'skipped' => $k])); }, '/p/storagerent/notifications', ''));
    $post('/access/log', fn() => $act(fn() => $svc()['wr']->logAccess($ctx(), (int)($_POST['tenant_id'] ?? 0), ((int)($_POST['unit_id'] ?? 0)) ?: null, (string)($_POST['person'] ?? ''), (string)($_POST['dir'] ?? 'in'), (string)($_POST['note'] ?? '')), '/p/storagerent/access'));
    $post('/requests/{id}/status', fn(array $p) => $act(fn() => $svc()['wr']->setRequestStatus($ctx(), (int)$p['id'], (string)($_POST['status'] ?? '')), '/p/storagerent/requests'));
    $post('/portal/request', function () use ($act, $svc, $ctx, $upload) {
        $act(function () use ($svc, $ctx, $upload) {
            $c = $ctx(); if ($c->role !== 'tenant') throw new Err('forbidden', '', 403); $u = $upload('photo', true); $doc = null;
            if ($u) $doc = $svc()['wr']->addDocument($c, 'request', 0, $c->tenantId, 'request photo', $u[1], $u[0], $u[2], $u[3], 'request');
            return $svc()['wr']->createRequest($c, $c->tenantId, (string)($_POST['title'] ?? ''), (string)($_POST['body'] ?? ''), ((int)($_POST['unit_id'] ?? 0)) ?: null, $doc);
        }, '/p/storagerent/portal', 'request_sent');
    });
    $post('/settings/save', function () use ($act, $sf, $ctx, $svc) {
        $act(function () use ($sf, $ctx) {
            $ctx()->require('settings');
            $key = trim((string)($_POST['yandex_key'] ?? '')); if ($key !== '' && !preg_match('/^[A-Za-z0-9\-_]{8,80}$/', $key)) throw new Err('key_invalid'); $sf->setSetting('yandex_key', $key); @unlink(SF_ROOT . '/storage/storagerent-setup.json.try');
            $peers = []; foreach (preg_split('/\R/', (string)($_POST['peers'] ?? '')) ?: [] as $u) { $u = trim($u); if ($u === '') continue; if (!preg_match('~^https://[^\s<>"\']{4,250}$~i', $u)) throw new Err('peer_invalid'); $peers[] = $u; }
            $sf->setSetting('peers', implode("\n", array_slice($peers, 0, 10)));
            $sf->setSetting('owners_open', !empty($_POST['owners_open']) ? '1' : '0');
            if ($sf->auth()->isAdmin()) {   // only a platform administrator hands out staff roles
                $staff = []; foreach (preg_split('/\R/', (string)($_POST['staff'] ?? '')) ?: [] as $line) { if (trim($line) === '') continue; if (!preg_match('/^\s*(\d{1,9})\s*[:=]\s*(admin|manager|accountant)\s*$/', $line, $x)) throw new Err('staff_invalid'); $staff[(int)$x[1]] = $x[2]; }
                foreach (array_keys($staff) as $uid) if (!$sf->db()->rows('SELECT id FROM {p}users WHERE id = ?', [$uid])) throw new Err('staff_invalid');
                $sf->setSetting('staff', implode("\n", array_map(fn($id, $r) => $id . ':' . $r, array_keys($staff), $staff)));
            }
            foreach (['before', 'after', 'expiring'] as $k) $sf->setSetting('notify_' . $k, implode(',', Notify::parseList((string)($_POST['notify_' . $k] ?? ''))));
            $sf->setSetting('notify_lang', ($_POST['notify_lang'] ?? 'ru') === 'en' ? 'en' : 'ru');
            $sf->setSetting('company_name', Wrms::clean($_POST['company_name'] ?? '', 190)); $sf->setSetting('company_details', Wrms::clean($_POST['company_details'] ?? '', 1500));
            if (!empty($_POST['new_token'])) $sf->setSetting('cron_token', bin2hex(random_bytes(16)));
        }, '/p/storagerent/settings');
    });

    /* ================================================================== exports: CSV, XLSX, printable */
    $get('/reports/export', function () use ($svc, $ctx, $sf, $lang, $tr, $render, $fail) {
        try {
            $c = $ctx(); $c->require('export'); $name = (string)($_GET['r'] ?? ''); $fmt = (string)($_GET['fmt'] ?? 'csv');
            $today = date('Y-m-d'); $from = Billing::parseDate($_GET['from'] ?? '') ?? Billing::addMonths(Billing::monthStart($today), -5); $to = Billing::parseDate($_GET['to'] ?? '') ?? $today;
            $t = $svc()['rp']->run($name, $from, $to); $label = fn(string $k) => $tr('col_' . $k); $file = 'storagerent-' . $name . '-' . date('Ymd');
            if ($fmt === 'print') { $render(['view' => 'report_print', 'bare' => true, 'title' => 'rep_' . $name, 'name' => $name, 't' => $t, 'from' => $from, 'to' => $to]); return; }
            $m = Reports::matrix($t, $label); $svc()['wr']->audit($c->uid, 'report', 0, 'export', ['report' => $name, 'fmt' => $fmt]);
            header('X-Content-Type-Options: nosniff');
            if ($fmt === 'xlsx') { header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'); header('Content-Disposition: attachment; filename="' . $file . '.xlsx"'); echo Xlsx::build($m, $tr('rep_' . $name)); return; }
            header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="' . $file . '.csv"'); echo Reports::csv($m);
        } catch (Err $e) { flash($fail($e), 'bad'); redirect('/p/storagerent/reports'); }
    });

    /* ================================================================== Offers */
    $remote = function () use ($sf, $svc, $hubSetup, $hostNow): array {
        // the feeds typed on this site, plus the ones of every site connected to the hub (the site's own address left out)
        $peers = array_values(array_filter(array_map('trim', preg_split('/\R/', $sf->setting('peers')) ?: [])));
        foreach ((array)($hubSetup()['feeds'] ?? []) as $f) if (is_string($f) && !in_array($f, $peers, true) && strtolower((string)parse_url($f, PHP_URL_HOST)) !== $hostNow()) $peers[] = $f;
        if (!$peers) return [];
        $fetch = function (string $url): ?string {
            $ctx = stream_context_create(['http' => ['timeout' => 3, 'follow_location' => 0, 'ignore_errors' => false, 'header' => "Accept: application/json\r\nUser-Agent: Sysfera-StorageRent\r\n"], 'ssl' => ['verify_peer' => true]]);
            $b = @file_get_contents($url, false, $ctx, 0, 2_000_000); return $b === false ? null : $b;
        };
        try { return $svc()['of']->remote($peers, $fetch); } catch (\Throwable $e) { \Sysfera\App::log('storagerent peers ' . $e); return []; }
    };
    $sf->route('GET', '/offers', fn() => $page(fn() => $svc()['pg']->offers($ctx(), trim((string)($_GET['q'] ?? '')), $remote()), '/p/storagerent/'), ['auth' => false]);
    $sf->route('GET', '/offers/map', fn() => $page(fn() => $svc()['pg']->map($ctx(), $remote())), ['auth' => false]);
    /** Messenger (another component) lends its connection through a hook; null when it is not installed. */
    $messengerClient = fn() => \Sysfera\Hooks::filter('messenger.client', null);
    $messengerReady = function () use ($messengerClient): bool { $c = $messengerClient(); return $c !== null && $c->ready(); };

    $sf->route('GET', '/offers/mine', fn() => $page(fn() => $svc()['pg']->myOffers($ctx()), '/p/storagerent/offers'));
    $sf->route('GET', '/offers/favorites', fn() => $page(fn() => $svc()['pg']->favorites($ctx()), '/p/storagerent/offers'));
    $sf->route('GET', '/offers/new', fn() => $page(fn() => $svc()['pg']->offerForm($ctx(), 0, (int)($_GET['wh'] ?? 0)), '/p/storagerent/offers'));
    $sf->route('GET', '/offers/{id}/edit', fn(array $p) => $page(fn() => $svc()['pg']->offerForm($ctx(), (int)$p['id']), '/p/storagerent/offers'));
    $sf->route('GET', '/offers/photo/{id}', function (array $p) use ($svc, $ctx) {
        $ph = $svc()['st']->one('listing_photos', ['id' => (int)$p['id']]); $l = $ph ? $svc()['st']->one('listings', ['id' => (int)$ph['listing_id']]) : null; $c = $ctx();
        if (!$ph || !$l || ($l['status'] !== 'published' && (int)$l['user_id'] !== $c->uid && $c->role !== 'admin') || !($f = Files::path((string)$ph['file_key']))) { http_response_code(404); return; }
        $ext = strtolower(pathinfo((string)$ph['file_key'], PATHINFO_EXTENSION)); header('Content-Type: ' . Files::mime($ext)); header('X-Content-Type-Options: nosniff'); header('Cache-Control: public, max-age=86400'); header('Content-Length: ' . filesize($f)); readfile($f);
    }, ['auth' => false]);
    $sf->route('GET', '/offers/{id}', fn(array $p) => $page(fn() => $svc()['pg']->offer($ctx(), (int)$p['id']) + ['messenger' => $messengerReady()], '/p/storagerent/offers'), ['auth' => false]);
    $sf->route('POST', '/offers/favorite', function () use ($act, $svc, $ctx, $tr, $back, $remote) {
        $ref = (string)($_POST['ref'] ?? '');
        $act(function () use ($svc, $ctx, $tr, $ref, $remote) {
            $c = $ctx(); $remoteCards = str_starts_with($ref, 'R') ? $remote() : [];
            $on = $svc()['of']->toggleFavorite($c->uid, $ref, fn(array $p, int $id) => url('/p/storagerent/offers/photo/' . (int)$p['id']), $remoteCards);
            flash($tr($on ? 'fav_added' : 'fav_removed')); return null;
        }, $back('/p/storagerent/offers'), '');
    });

    /** "Contact with Lessor": the message goes into the Messenger chat with the author of the listing (his Sysfera ID is <user id>@<this domain>). */
    $sf->route('POST', '/offers/{id}/contact', function (array $p) use ($svc, $ctx, $sf, $tr, $fail, $abs, $messengerClient) {
        $id = (int)$p['id']; $to = '/p/storagerent/offers/' . $id;
        try {
            $c = $ctx(); if ($c->uid <= 0) throw new Err('forbidden', '', 403);
            $l = $svc()['of']->get($id); if (!$l || $l['status'] !== 'published') throw new Err('not_found', '', 404);
            if ((int)$l['user_id'] === $c->uid) throw new Err('contact_own');
            $sid = Contact::send($messengerClient(), $sf->auth()->user() ?? [], (int)$l['user_id'], (string)$l['contact_name'], (string)($_POST['text'] ?? ''), $abs(url($to)));
            flash($tr('contact_sent')); redirect('/p/messenger/chat?with=' . rawurlencode($sid));
        } catch (Err $e) { flash($fail($e), 'bad'); redirect($e->errCode === 'not_found' || $e->errCode === 'forbidden' ? '/p/storagerent/offers' : $to);
        } catch (\Throwable $e) { \Sysfera\App::log('storagerent contact ' . $e); flash($tr('err_server'), 'bad'); redirect($to); }
    });
    $sf->route('POST', '/offers/save',function () use ($act, $svc, $ctx, $sf, $uploads, $tr) {
        $id = (int)($_POST['id'] ?? 0);
        $act(function () use ($svc, $ctx, $sf, $uploads, $id, $tr) {
            $c = $ctx(); $of = $svc()['of']; $u = $sf->auth()->user() ?? [];
            $l = $of->save($c->uid, $sf->auth()->isAdmin(), $id, $_POST, (string)($u['name'] ?? ''));
            foreach ($uploads('photos', true) as $f) {
                try { $ext = Offers::checkPhoto((string)$f['name'], (int)$f['size']); if ($f['error'] !== UPLOAD_ERR_OK) throw new Err('file_failed'); $of->addPhoto($c->uid, $sf->auth()->isAdmin(), (int)$l['id'], Files::store((string)$f['tmp_name'], $ext, true), Files::mime($ext), (int)$f['size']); }
                catch (Err $e) { flash($e->errCode === 'photos_max' ? $tr('err_photos_max') : $tr('err_' . $e->errCode) . ': ' . $f['name'], 'warn'); }
            }
            return $l;
        }, fn($l) => '/p/storagerent/offers/' . $l['id'], 'saved', $id > 0 ? '/p/storagerent/offers/' . $id . '/edit' : '/p/storagerent/offers/new');
    });
    $sf->route('POST', '/offers/{id}/do', function (array $p) use ($act, $svc, $ctx, $sf) {
        $id = (int)$p['id']; $do = (string)($_POST['do'] ?? ''); $c = $ctx(); $adm = $sf->auth()->isAdmin(); $of = $svc()['of'];
        $act(function () use ($do, $id, $c, $adm, $of) {
            if ($do === 'delete') { foreach ($of->delete($c->uid, $adm, $id) as $ph) Files::remove((string)$ph['file_key']); return null; }
            if ($do === 'photo_delete') { $ph = $of->removePhoto($c->uid, $adm, (int)($_POST['photo_id'] ?? 0)); if ($ph) Files::remove((string)$ph['file_key']); return null; }
            $of->setStatus($c->uid, $adm, $id, $do); return null;
        }, $do === 'delete' ? '/p/storagerent/offers' : ($do === 'photo_delete' ? '/p/storagerent/offers/' . $id . '/edit' : '/p/storagerent/offers/' . $id), 'done');
    });

    /* ================================================================== machine endpoints (no login): the public feed and the cron */
    $sf->api('GET', 'storagerent/v1/listings', function () use ($svc, $abs) {
        header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: public, max-age=300'); header('Access-Control-Allow-Origin: *');
        $photo = fn(array $p, int $id) => $abs(url('/p/storagerent/offers/photo/' . (int)$p['id']));
        $feed = $svc()['of']->feed($photo, $abs(url('/')));
        foreach ($feed['listings'] as &$c) $c['url'] = $abs(url('/p/storagerent/offers/' . (int)$c['id']));
        echo json_encode($feed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    });
    $sf->api('POST', 'storagerent/v1/cron', function () use ($sf, $tick) {
        header('Content-Type: application/json; charset=utf-8'); $t = $sf->setting('cron_token'); $h = (string)($_SERVER['HTTP_X_CRON_TOKEN'] ?? '');
        if ($t === '' || !hash_equals($t, $h)) { http_response_code(403); echo json_encode(['ok' => false, 'error' => 'forbidden']); return; }
        $tick(true); echo json_encode(['ok' => true]);
    });

    /* ================================================================== the guest landing block (public map of offers) */
    $sf->on('landing.blocks', function (string $html) use ($sf, $svc, $L, $remote) {
        $lg = \Sysfera\I18n::lang() === 'en' ? 'en' : 'ru'; $l = $L[$lg];
        try { $cards = $svc()['pg']->cards('', $remote()); } catch (\Throwable $e) { \Sysfera\App::log('storagerent landing ' . $e); return $html; }
        $open = \Sysfera\App::get()->setting('registration', '0') === '1' || \Sysfera\Demo::on();
        return $html . \Sysfera\View::file($sf->dir . '/views/landing_block.php', ['sf' => $sf, 'L' => $l, 'lang' => $lg, 'cards' => array_slice($cards, 0, 300), 'key' => $eff('yandex_key'), 'open' => $open]);
    });
};
