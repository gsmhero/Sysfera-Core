<?php
declare(strict_types=1);

use SysferaHr\{Err, Api, Env, Hr, Recruit, Leave, Rbac, Sig, Crypt, DbStore, DbBridges, Payroll, Ctx};

/**
 * Sysfera HR (Отдел кадров). Rules live in lib/ (tested without a database); this file only wires them to the platform:
 *  - ONE registry of operations (lib/Api.php) is served three ways: the signed machine API /api/hr/v1/..., the session API /p/hr/api/<op> used by the pages' scripts, and the HTML forms /p/hr/do/<op>.
 *  - Access is by RBAC (hr_roles -> hr_user_roles); a Sysfera administrator is the root. The employee always reaches their own card, leave and documents.
 *  - Encryption key: env SYSFERA_HR_KEY or core/storage/hr.key (never in the database, never in git).
 */
return function (\Sysfera\Plugin $sf): void {
    foreach (['Err', 'Sig', 'Audit', 'Crypt', 'Store', 'Ctx', 'Rbac', 'Fields', 'Payroll', 'TimeOff', 'Pipeline', 'Bridges', 'Env', 'Hr', 'Recruit', 'Leave', 'Api', 'DbStore', 'DbBridges'] as $c) require_once __DIR__ . "/lib/$c.php";

    $db = $sf->db(); $store = new DbStore($db);
    $secret = function (string $k) use ($sf): string { $v = $sf->setting($k); if ($v === '') { $v = bin2hex(random_bytes(32)); $sf->setSetting($k, $v); } return $v; };
    $dataKey = function (): string {
        $e = getenv('SYSFERA_HR_KEY'); if (is_string($e) && strlen($e) >= 32) return $e;
        $f = SF_ROOT . '/storage/hr.key'; if (is_file($f)) return trim((string)file_get_contents($f));
        $k = Crypt::newKey(); @file_put_contents($f, $k, LOCK_EX); @chmod($f, 0600); return $k;
    };
    $client = fn() => \Sysfera\Hooks::filter('messenger.client', null);
    $cfg = function () use ($sf): array {
        return ['annual_days' => max(0, min(60, (int)$sf->setting('annual_days', '28'))), 'onboarding_days' => max(0, min(60, (int)$sf->setting('onboarding_days', '3'))), 'consent_version' => preg_replace('/[^A-Za-z0-9._-]/', '', $sf->setting('consent_version', '1')) ?: '1',
            'collect_passport' => $sf->setting('collect_passport', '0') === '1', 'holidays' => array_values(array_filter(array_map('trim', explode(',', $sf->setting('holidays', ''))), fn($d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)))];
    };
    $svc = function () use ($db, $store, $dataKey, $cfg, $client, $sf): array {
        static $o = null; if ($o) return $o;
        $rbac = new Rbac($store); $env = new Env($store, $rbac, DbBridges::make($db, SF_ROOT . '/storage/hr', $client), $dataKey(), fn() => time(), $cfg());
        $hr = new Hr($env); $rec = new Recruit($env, $hr); $lv = new Leave($env, $hr);
        $rec->label = fn(string $k): string => $sf->t($k);
        return $o = ['rbac' => $rbac, 'env' => $env, 'hr' => $hr, 'rec' => $rec, 'lv' => $lv, 'ops' => Api::ops($env, $hr, $rec, $lv)];
    };
    $seed = function () use ($sf, $svc, $db) {
        // the site deploy copies bundled components without running their migrations: apply pending ones once per version
        if ($sf->setting('schema') !== (string)$sf->manifest['version']) { (new \Sysfera\Migrator($db))->run($sf->dir . '/migrations', 'plugin:hr:'); $sf->setSetting('schema', (string)$sf->manifest['version']); }
        if ($sf->setting('seed_v1') !== '1') { $svc()['rbac']->ensureBuiltin(); $sf->setSetting('seed_v1', '1'); }
        if ($sf->setting('teams_sync_v1') !== '1' && DbBridges::has($db, 'teams_teams')) {   // once: show the teams that existed before the Teams app was connected
            foreach ($svc()['env']->s->find('teams') as $t) $svc()['hr']->syncTeam((string)$t['uuid']);
            $sf->setSetting('teams_sync_v1', '1');
        }
    };

    /** who is asking, resolved by the session */
    $ctx = function () use ($sf, $svc, $seed): Ctx {
        static $c = null; if ($c) return $c; $seed(); $s = $svc(); $u = $sf->auth()->user(); $uid = (int)($u['id'] ?? 0);
        $emp = $uid ? $s['env']->empOfUser($uid) : null;
        return $c = $s['rbac']->ctx($uid ?: null, (string)($u['name'] ?? 'guest'), $sf->auth()->isAdmin(), $emp ? (int)$emp['id'] : null);
    };
    $apiCtx = function () use ($sf, $svc, $seed): Ctx {   // the machine key is least privilege: only what the administrator lists in api_perms
        $seed(); $perms = array_values(array_filter(array_map('trim', explode(',', $sf->setting('api_perms', 'hr.employees.view,hr.pay.view,hr.payroll.run,hr.audit.view'))), fn($p) => Rbac::valid($p)));
        return new Ctx(null, 'hr-api', $perms, false, null, 'api');
    };

    $errText = fn(Err $e): string => ($t = $sf->t('err_' . $e->errCode)) !== 'hr.err_' . $e->errCode ? $t : $e->getMessage();
    /* new applications waiting for HR: a badge on the menu item (only for those who handle candidates) */
    $sf->menu('menu', '/', ['order' => 22, 'icon' => 'users', 'badge' => function () use ($sf, $svc, $ctx) {
        if ($sf->auth()->id() <= 0) return 0;
        try { if (!$ctx()->can('hr.candidates.manage')) return 0; return count(array_filter($svc()['env']->s->find('candidates', ['stage' => 'new']), fn($x) => $x['source'] === 'apply')); } catch (\Throwable) { return 0; }
    }]);

    /* =============================== machine API (signed) =============================== */
    $out = function (int $status, array $data, ?string $key = null): void {
        http_response_code($status); header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
        $body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION) ?: '{}';
        if ($key) { $ts = (string)time(); header('X-HR-Timestamp: ' . $ts); header('X-HR-Signature: ' . Sig::response($key, $ts, $status, $body)); }
        echo $body;
    };
    $hdr = fn(string $n): string => (string)($_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $n))] ?? '');
    $failBody = fn(Err $e): array => ['error' => ['code' => $e->errCode, 'message' => $e->getMessage()] + ($e->extra ? ['detail' => $e->extra] : [])];
    $inputOf = function (string $raw, array $params): array {
        $in = $raw !== '' ? json_decode($raw, true) : []; if (!is_array($in)) throw new Err('bad_json', 'the body must be a JSON object', 400);
        return $params + $in + $_GET;   // the path wins over the body, the body over the query
    };

    foreach (Api::ROUTES as [$method, $path, $op]) {
        $sf->api($method, 'hr/v1/' . $path, function (array $p = []) use ($op, $svc, $apiCtx, $out, $hdr, $secret, $failBody, $inputOf) {
            $key = null;
            try {
                if ($op !== 'time') {
                    $raw0 = (string)file_get_contents('php://input', false, null, 0, 3_000_000); $key = $secret('api_key');
                    $ts = $hdr('X-HR-Timestamp'); if (!Sig::fresh($ts, time())) throw new Err('stale_request', 'X-HR-Timestamp is missing or outside the 5 minute window', 401);
                    if (!Sig::ok(Sig::request($key, $ts, (string)($_SERVER['REQUEST_METHOD'] ?? 'GET'), (string)($_SERVER['REQUEST_URI'] ?? ''), $raw0), $hdr('X-HR-Signature'))) throw new Err('bad_signature', 'signature does not match', 401);
                    $in = $inputOf($raw0, $p); $c = $apiCtx();
                } else { $in = []; $c = new Ctx(null, 'public', []); }
                $out(200, ['data' => ($svc()['ops'][$op])($c, $in)], $key);
            } catch (Err $e) { $out($e->http, $failBody($e), $key); }
            catch (\Throwable $e) { \Sysfera\App::log('hr ' . $e); $out(500, ['error' => ['code' => 'server_error', 'message' => 'internal error']], $key); }
        });
    }
    $sf->api('GET', 'hr/v1/openapi.yaml', function () { header('Content-Type: application/yaml; charset=utf-8'); readfile(__DIR__ . '/openapi.yaml'); });
    $sf->api('GET', 'hr/v1/schemas/payroll-export.schema.json', function () { header('Content-Type: application/schema+json; charset=utf-8'); readfile(__DIR__ . '/schemas/payroll-export.schema.json'); });

    /* =============================== session API: the pages' scripts =============================== */
    $sessionApi = function (array $p) use ($svc, $ctx, $out, $failBody) {
        $op = (string)($p['op'] ?? ''); $ops = $svc()['ops'];
        try {
            if (!isset($ops[$op]) || $op === 'time') throw new Err('not_found', 'unknown operation', 404);
            $in = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET; unset($in['_t']);
            if (!$in && $_SERVER['REQUEST_METHOD'] === 'POST') { $j = json_decode((string)file_get_contents('php://input', false, null, 0, 3_000_000), true); if (is_array($j)) $in = $j; }
            $out(200, ['data' => $ops[$op]($ctx(), $in)]);
        } catch (Err $e) { $out($e->http, $failBody($e)); }
        catch (\Throwable $e) { \Sysfera\App::log('hr ' . $e); $out(500, ['error' => ['code' => 'server_error', 'message' => 'internal error']]); }
    };
    $sf->route('GET', '/api/{op}', $sessionApi);
    $sf->route('POST', '/api/{op}', $sessionApi);

    /* =============================== HTML forms: the same operations =============================== */
    $money = fn($v): int => (int)round((float)str_replace([' ', ','], ['', '.'], (string)$v) * 100);
    $payForm = function (array $f) use ($money): array {   // the payroll config form (rubles, percents) -> the profile of the engine (kopecks, basis points)
        $ded = []; foreach ((array)($f['ded'] ?? []) as $d) { if (trim((string)($d['value'] ?? '')) === '') continue; $pc = ($d['kind'] ?? 'fixed') === 'percent'; $ded[] = ['code' => (string)($d['code'] ?? 'deduction'), 'label' => (string)($d['label'] ?? ''), 'kind' => $pc ? 'percent' : 'fixed', 'value' => $pc ? (int)round((float)str_replace(',', '.', (string)$d['value']) * 100) : $money($d['value'])]; }
        return ['type' => (string)($f['type'] ?? 'fixed'), 'base_kop' => $money($f['base'] ?? 0), 'mission_bonus_kop' => $money($f['mission_bonus'] ?? 0), 'commission_bp' => (int)round((float)str_replace(',', '.', (string)($f['commission_pct'] ?? 0)) * 100),
            'gi' => ['per_issue_kop' => $money($f['gi_issue'] ?? 0), 'per_receipt_kop' => $money($f['gi_receipt'] ?? 0), 'per_return_kop' => $money($f['gi_return'] ?? 0), 'per_count_kop' => $money($f['gi_count'] ?? 0)], 'deductions' => $ded];
    };
    $sf->route('POST', '/do/{op}', function (array $p) use ($svc, $ctx, $sf, $errText, $payForm) {
        $op = (string)$p['op']; $ops = $svc()['ops']; $in = $_POST; unset($in['_t']);
        $back = (string)($in['return'] ?? ''); unset($in['return']); if ($back === '' || $back[0] !== '/' || str_starts_with($back, '//') || str_contains($back, '\\')) $back = '/';
        try {
            if (!isset($ops[$op]) || in_array($op, ['time'], true)) throw new Err('not_found', 'unknown operation', 404);
            if ($op === 'pay.set' && isset($in['pay'])) $in = ['uuid' => $in['uuid'] ?? '', 'profile' => $payForm((array)$in['pay'])];
            if (isset($_FILES['scan']) && is_uploaded_file((string)($_FILES['scan']['tmp_name'] ?? '')) && (int)$_FILES['scan']['size'] <= 8_000_000) $in['scan_base64'] = base64_encode((string)file_get_contents($_FILES['scan']['tmp_name']));
            $r = $ops[$op]($ctx(), $in);
            $msg = $op === 'candidates.hire' ? $sf->t('flash_hired') : ($op === 'offboard' ? $sf->t('flash_offboarded') : $sf->t('saved'));
            flash($msg);
            if ($op === 'candidates.hire' && !empty($r['employee']['uuid'])) $back = '/employees/' . $r['employee']['uuid'];
            elseif ($op === 'employees.create' && !empty($r['uuid'])) $back = '/employees/' . $r['uuid'];
            elseif ($op === 'vacancies.save' && !empty($r['uuid']) && str_starts_with($back, '/vacancies')) $back = '/vacancies/' . $r['uuid'];
        } catch (Err $e) { flash($errText($e), 'bad'); }
        catch (\Throwable $e) { \Sysfera\App::log('hr ' . $e); flash($sf->t('err_server'), 'bad'); }
        redirect('/p/hr' . ($back === '/' ? '/' : $back));
    });

    /* =============================== Apply: from the Activity post and from the dashboard =============================== */
    $user = fn(): array => (array)($sf->auth()->user() ?? []);
    $open = function () use ($svc, $user): array { static $o = null; return $o ??= $svc()['rec']->openPublished((string)($user()['email'] ?? '')); };
    $sf->route('POST', '/apply/{uuid}', function (array $p) use ($sf, $svc, $ctx, $seed, $user, $errText) {
        $u = $user(); $ret = (string)($_POST['return'] ?? ''); $back = $ret === 'dashboard' ? '/' : ($ret === 'preview' ? '/p/hr/v/' . preg_replace('/[^0-9a-f-]/i', '', (string)$p['uuid']) : '/p/activity/');
        if (empty($u['id']) || \Sysfera\Demo::on()) { flash($sf->t('forbidden'), 'bad'); redirect($back); }
        try { $seed(); $svc()['rec']->apply($ctx(), (string)$p['uuid'], ['full_name' => (string)($u['name'] ?? ''), 'email' => (string)($u['email'] ?? ''), 'phone' => (string)($u['phone'] ?? '')]); flash($sf->t('applied')); }
        catch (Err $e) { flash($e->errCode === 'duplicate' ? $sf->t('applied_already') : $errText($e), 'bad'); }
        catch (\Throwable $e) { \Sysfera\App::log('hr ' . $e); flash($sf->t('err_server'), 'bad'); }
        redirect($back);
    });
    $sf->route('GET', '/v/{uuid}', function (array $p) use ($sf, $svc, $ctx, $seed, $user, $errText) {
        if (empty($user()['id'])) { http_response_code(403); echo '<div class="card empty"><h3>' . e($sf->t('forbidden')) . '</h3></div>'; return; }
        try { $seed(); $v = $svc()['rec']->preview($ctx(), (string)$p['uuid'], (string)($user()['email'] ?? '')); }
        catch (Err $e) { http_response_code($e->http === 404 ? 404 : 403); echo '<div class="card empty"><h3>' . e($sf->t($e->http === 404 ? 'not_found' : 'forbidden')) . '</h3></div>'; return; }
        $sf->render('preview', ['v' => $v, 'lang' => ($user()['lang'] ?? '') === 'ru' ? 'ru' : 'en', 'manage' => $ctx()->can('hr.vacancies.manage'), 'back' => (string)($_GET['from'] ?? '')], $v['title']);
    });
    /* the button under the vacancy's post in the Activity feed (the post is found by the reference saved when the vacancy was published) */
    $sf->on('activity.post.actions', function (string $html, array $post) use ($sf, $open, $user) {
        if (empty($user()['id']) || \Sysfera\Demo::on() || empty($post['id'])) return $html;
        foreach ($open() as $v) if ($v['post_ref'] === 'activity:' . (int)$post['id'])
            return $html . '<a class="btn sm" href="' . e($sf->url('/v/' . $v['uuid'] . '?from=activity')) . '">' . e($sf->t('act_preview')) . '</a> ' . ($v['applied'] ? '<span class="hr-st ok">' . e($sf->t('applied_badge')) . '</span> '
                : '<form method="post" action="' . e($sf->url('/apply/' . $v['uuid'])) . '" class="inline">' . csrf_field() . '<button class="btn sm primary">' . e($sf->t('act_apply')) . '</button></form> ');
        return $html;
    });
    /* dashboard widget: published vacancies with the number of applications */
    $sf->on('dashboard.widgets', function (array $w) use ($sf, $open, $user, $ctx, $seed) {
        if (\Sysfera\Demo::on() || empty($user()['id'])) return $w;
        try { $seed(); $list = $open(); $manage = $ctx()->can('hr.candidates.manage'); } catch (\Throwable) { return $w; }
        if (!$list && !$manage) return $w;
        $w['hr'] = \Sysfera\View::file($sf->dir . '/views/widget.php', ['sf' => $sf, 'list' => array_slice($list, 0, 5), 'total' => count($list), 'manage' => $manage]);
        return $w;
    });

    /* =============================== pages =============================== */
    $try = function (callable $f, mixed $default = null) { try { return $f(); } catch (Err $e) { if ($e->http === 403 || $e->http === 404) return $default; throw $e; } };
    $op = fn(string $name, array $in = []) => ($svc()['ops'][$name])($ctx(), $in);
    $page = function (string $view, array $vars, string $title, string $tab) use ($sf, $ctx, $seed, $svc) {
        $seed(); $c = $ctx();
        $sf->render($view, $vars + ['tab' => $tab, 'c' => $c, 'me' => $c->employeeId ? $svc()['env']->s->one('employees', ['id' => $c->employeeId]) : null], $title);
    };
    $denied = function () use ($sf) { http_response_code(403); echo '<div class="card empty"><h3>' . e($sf->t('forbidden')) . '</h3></div>'; };
    $guard = function (callable $fn) use ($denied, $sf) { return function (array $p = []) use ($fn, $denied, $sf) { try { $fn($p); } catch (Err $e) { if ($e->http === 403) { $denied(); return; } if ($e->http === 404) { http_response_code(404); echo '<div class="card empty"><h3>' . e($sf->t('not_found')) . '</h3></div>'; return; } throw $e; } }; };
    $route = fn(string $path, callable $fn) => $sf->route('GET', $path, $guard($fn));
    $env = fn() => $svc()['env'];

    $route('/', function () use ($page, $op, $try, $ctx, $sf, $svc) {
        $c = $ctx(); $isHr = $c->can('hr.employees.view');
        $page('dashboard', ['vac' => $try(fn() => $op('vacancies.list', ['status' => 'open']), []), 'alerts' => $try(fn() => $op('onboarding.alerts', ['notify' => '0']), []), 'pending' => $try(fn() => array_merge($op('leave.list', ['status' => 'pending_manager']), $op('leave.list', ['status' => 'pending_hr'])), []),
            'orphans' => $try(fn() => $op('rbac.orphans'), []), 'missing' => $try(fn() => $op('consent.missing'), []), 'emps' => $isHr ? $try(fn() => $op('employees.list', ['status' => 'active']), []) : [], 'isHr' => $isHr, 'myLeave' => $try(fn() => $op('leave.list'), [])], $sf->t('menu'), 'dashboard');
    });

    $users = fn(): array => $db->rows("SELECT id, name, email FROM {p}users WHERE status = 'active' ORDER BY name LIMIT 1000");
    $whs = fn(): array => DbBridges::has($db, 'gi_warehouses') ? $db->rows('SELECT id, code, name FROM {p}gi_warehouses WHERE active = 1 ORDER BY name') : [];

    $route('/roles', fn() => $page('roles', ['roles' => $op('roles.list'), 'catalog' => Rbac::CATALOG], $sf->t('nav_roles'), 'roles'));

    $route('/vacancies', fn() => $page('vacancies', ['vac' => $op('vacancies.list', array_filter(['status' => (string)($_GET['status'] ?? '')])), 'roles' => array_values(array_filter($op('roles.list'), fn($r) => (int)$r['active'])), 'teams' => $try(fn() => $op('teams.list'), []), 'st' => (string)($_GET['status'] ?? '')], $sf->t('nav_vacancies'), 'vacancies'));
    $route('/vacancies/{uuid}', function (array $p) use ($page, $op, $try, $sf, $users) {
        $v = $op('vacancies.get', ['uuid' => $p['uuid']]);
        $page('vacancy', ['v' => $v, 'board' => $op('candidates.board', ['uuid' => $p['uuid']]), 'versions' => $op('vacancies.versions', ['uuid' => $p['uuid']]), 'roles' => array_values(array_filter($op('roles.list'), fn($r) => (int)$r['active'])), 'teams' => $try(fn() => $op('teams.list'), []), 'users' => $users(),
            'interviews' => array_values(array_filter($try(fn() => array_merge(...array_map(fn($col) => array_map(fn($cd) => $op('candidates.history', ['uuid' => $cd['uuid']]), $col), array_values($op('candidates.board', ['uuid' => $p['uuid']])['columns']))), []), fn($h) => !empty($h['interviews'])))], $v['title'], 'vacancies');
    });

    $route('/me', function () use ($ctx, $env) { $c = $ctx(); $emp = $c->employeeId ? $env()->s->one('employees', ['id' => $c->employeeId]) : null; redirect($emp ? '/p/hr/employees/' . $emp['uuid'] : '/p/hr/'); });
    $route('/employees', fn() => $page('employees', ['emps' => $op('employees.list', array_filter(['status' => (string)($_GET['status'] ?? 'active'), 'q' => (string)($_GET['q'] ?? '')])), 'st' => (string)($_GET['status'] ?? 'active'), 'q' => (string)($_GET['q'] ?? ''), 'roles' => array_values(array_filter($op('roles.list'), fn($r) => (int)$r['active'])), 'teams' => $try(fn() => $op('teams.list'), []), 'users' => $users()], $sf->t('nav_employees'), 'employees'));
    $route('/employees/{uuid}', function (array $p) use ($page, $op, $try, $ctx, $env, $users, $svc, $sf) {
        $c = $ctx(); $e = $op('employees.get', ['uuid' => $p['uuid']]); $u = $p['uuid'];
        $page('employee', ['e' => $e, 'docs' => $try(fn() => $op('docs.list', ['uuid' => $u]), []), 'pay' => $try(fn() => $op('pay.get', ['uuid' => $u])['profile'], null), 'payAllowed' => $c->can('hr.pay.view') || (int)$e['id'] === $c->employeeId,
            'timeline' => $try(fn() => $op('employees.timeline', ['uuid' => $u]), []), 'tasks' => ['onboarding' => $try(fn() => $op('tasks.list', ['uuid' => $u, 'phase' => 'onboarding']), []), 'offboarding' => $try(fn() => $op('tasks.list', ['uuid' => $u, 'phase' => 'offboarding']), [])],
            'balance' => $try(fn() => $op('leave.balance', ['uuid' => $u]), null), 'consents' => $try(fn() => $svc()['env']->s->find('consents', ['employee_id' => (int)$e['id']], 'id'), []), 'consentOn' => $svc()['hr']->hasConsent($e, 'personal_data'),
            'roles' => $try(fn() => array_values(array_filter($op('roles.list'), fn($r) => (int)$r['active'])), []), 'teams' => $try(fn() => $op('teams.list'), []), 'schedules' => $try(fn() => $op('schedules.list'), []), 'managers' => $try(fn() => $op('employees.list', ['status' => 'active']), []),
            'audit' => $try(fn() => $op('audit.list', ['entity' => 'employee', 'ref' => $u, 'limit' => 40]), []), 'access' => !empty($e['user_id']) ? $try(fn() => $op('rbac.user', ['user_id' => (int)$e['user_id']]), null) : null, 'users' => $users(),
            'self' => (int)$e['id'] === $c->employeeId, 'editAll' => $c->can('hr.employees.edit'), 'pii' => $c->can('hr.employees.pii'), 'docsAdmin' => $c->can('hr.docs.manage'), 'payAdmin' => $c->can('hr.pay.manage'), 'offboardOk' => $c->can('hr.offboard'), 'taskAdmin' => $c->can('hr.onboarding.manage'), 'collectPassport' => $svc()['env']->cfg['collect_passport'], 'whs' => $whs()], $e['full_name'], 'employees');
    });

    $route('/teams', fn() => $page('teams', ['teams' => $op('teams.list'), 'emps' => $op('employees.list', ['status' => 'active']), 'whs' => $whs()], $sf->t('nav_teams'), 'teams'));
    $route('/leave', function () use ($page, $op, $try, $ctx, $sf, $env) {
        $c = $ctx(); $month = preg_match('/^\d{4}-\d{2}$/', (string)($_GET['month'] ?? '')) ? (string)$_GET['month'] : date('Y-m'); $team = (string)($_GET['team'] ?? '');
        $me = $c->employeeId ? $env()->s->one('employees', ['id' => $c->employeeId]) : null;
        $page('leave', ['mine' => $c->employeeId ? $try(fn() => $op('leave.list', ['employee_uuid' => $me['uuid']]), []) : [], 'queue' => $try(fn() => array_merge($op('leave.list', ['status' => 'pending_manager']), $op('leave.list', ['status' => 'pending_hr'])), []), 'cal' => $try(fn() => $op('leave.calendar', ['month' => $month, 'team_uuid' => $team]), ['month' => $month, 'entries' => []]),
            'month' => $month, 'team' => $team, 'teams' => $try(fn() => $op('teams.list'), []), 'schedules' => $try(fn() => $op('schedules.list'), []), 'balance' => $me ? $try(fn() => $op('leave.balance', ['uuid' => $me['uuid']]), null) : null, 'meUuid' => $me['uuid'] ?? '', 'canHr' => $c->can('hr.leave.approve'), 'canEdit' => $c->can('hr.employees.edit'), 'myId' => $c->employeeId], $sf->t('nav_leave'), 'leave');
    });
    $route('/access', function () use ($page, $op, $try, $svc, $sf, $db) {
        $s = $svc(); $rows = [];
        foreach ($db->rows("SELECT id, name, email FROM {p}users WHERE status = 'active' ORDER BY name LIMIT 500") as $u) { $a = $try(fn() => $op('rbac.user', ['user_id' => (int)$u['id']]), ['roles' => [], 'permissions' => [], 'warehouse' => null]); $rows[] = $u + ['roles' => $a['roles'], 'permissions' => $a['permissions'], 'warehouse' => $a['warehouse']]; }
        $page('access', ['users' => $rows, 'roles' => $op('roles.list'), 'catalog' => Rbac::CATALOG, 'orphans' => $try(fn() => $op('rbac.orphans'), [])], $sf->t('nav_access'), 'access');
    });
    $route('/payroll', function () use ($page, $op, $try, $sf, $svc, $db) {
        $from = (string)($_GET['from'] ?? date('Y-m-01')); $to = (string)($_GET['to'] ?? date('Y-m-t')); $doc = null; $err = '';
        try { $doc = $op('payroll.calc', ['from' => $from, 'to' => $to]); } catch (Err $e) { if ($e->http === 403) throw $e; $err = $e->getMessage(); }
        $page('payroll', ['from' => $from, 'to' => $to, 'doc' => $doc, 'err' => $err, 'runs' => $try(fn() => $svc()['env']->s->find('payroll_runs', [], 'id DESC', 30), []), 'emps' => $try(fn() => $op('employees.list', ['status' => 'active']), [])], $sf->t('nav_payroll'), 'payroll');
    });
    $route('/payroll/download', function () use ($op, $sf) {
        $d = $op('payroll.export', ['from' => (string)($_GET['from'] ?? ''), 'to' => (string)($_GET['to'] ?? '')]);
        header('Content-Type: application/json; charset=utf-8'); header('Content-Disposition: attachment; filename="payroll-' . preg_replace('/[^0-9-]/', '', $d['period']['from'] . '_' . $d['period']['to']) . '.json"');
        echo json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    });
    $route('/audit', function () use ($page, $op, $sf) {
        $q = array_filter(['entity' => (string)($_GET['entity'] ?? ''), 'action' => (string)($_GET['action'] ?? ''), 'limit' => 300]);
        $page('audit', ['rows' => $op('audit.list', $q), 'verify' => isset($_GET['verify']) ? $op('audit.verify') : null, 'entity' => (string)($_GET['entity'] ?? ''), 'action' => (string)($_GET['action'] ?? '')], $sf->t('nav_audit'), 'audit');
    });
    $route('/privacy', fn() => $page('privacy', ['missing' => $op('consent.missing'), 'exports' => $svc()['env']->s->find('audit', ['action' => 'export.employees'], 'id DESC', 30), 'version' => $svc()['env']->cfg['consent_version'], 'collect' => $svc()['env']->cfg['collect_passport'], 'cols' => \SysferaHr\Hr::EXPORT_COLUMNS], $sf->t('nav_privacy'), 'privacy'));
    $route('/export.csv', function () use ($op) {
        $r = $op('export.employees', ['purpose' => 'ui', 'status' => (string)($_GET['status'] ?? '')]);
        header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="employees.csv"'); echo "\xEF\xBB\xBF" . $r['content'];
    });
    $route('/doc/{uuid}/print', function (array $p) use ($svc, $ctx, $sf) {   // print is only a projection of the electronic document
        $s = $svc(); $d = $s['env']->row('docs', $p['uuid']); $emp = $s['env']->s->one('employees', ['id' => (int)$d['employee_id']]); $c = $ctx();
        if (!$c->can('hr.docs.manage') && !$c->isSelf($emp)) throw new Err('forbidden', '', 403);
        echo '<div class="card"><h3>' . e($d['title']) . ' <small>v' . e($d['version']) . '</small></h3><pre style="white-space:pre-wrap">' . e($d['body']) . '</pre><p class="muted">' . e($sf->t('doc_hash')) . ': ' . e($d['body_sha256']) . ' · ' . e($sf->t('st_' . $d['status'])) . ($d['signed_at'] ? ' · ' . e($d['signed_at']) : '') . '</p></div><script>window.print()</script>';
    });
    $route('/settings', function () use ($page, $sf, $ctx, $secret, $svc) {
        if (!$ctx()->root) throw new Err('forbidden', '', 403);
        $page('settings', ['key' => $secret('api_key'), 'base' => url('/api/hr/v1/'), 'v' => ['annual_days' => $sf->setting('annual_days', '28'), 'onboarding_days' => $sf->setting('onboarding_days', '3'), 'consent_version' => $sf->setting('consent_version', '1'), 'collect_passport' => $sf->setting('collect_passport', '0'), 'holidays' => $sf->setting('holidays', ''),
            'api_perms' => $sf->setting('api_perms', 'hr.employees.view,hr.pay.view,hr.payroll.run,hr.audit.view')], 'keyFile' => is_file(SF_ROOT . '/storage/hr.key') || getenv('SYSFERA_HR_KEY') !== false], $sf->t('nav_settings'), 'settings');
    });
    $sf->route('POST', '/settings', function () use ($sf, $ctx, $secret) {
        if (!$ctx()->root) { http_response_code(403); echo e($sf->t('forbidden')); return; }
        if (($_POST['do'] ?? '') === 'newkey') { $sf->setSetting('api_key', bin2hex(random_bytes(32))); flash($sf->t('key_new')); redirect('/p/hr/settings'); }
        $perms = array_values(array_filter(array_map('trim', explode(',', (string)($_POST['api_perms'] ?? ''))), fn($p) => Rbac::valid($p) && $p !== '*'));
        foreach (['annual_days' => [0, 60], 'onboarding_days' => [0, 60]] as $k => [$lo, $hi]) $sf->setSetting($k, (string)max($lo, min($hi, (int)($_POST[$k] ?? 0))));
        $sf->setSetting('consent_version', preg_replace('/[^A-Za-z0-9._-]/', '', (string)($_POST['consent_version'] ?? '1')) ?: '1');
        $sf->setSetting('collect_passport', !empty($_POST['collect_passport']) ? '1' : '0');
        $sf->setSetting('holidays', implode(',', array_filter(array_map('trim', explode(',', (string)($_POST['holidays'] ?? ''))), fn($d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d))));
        $sf->setSetting('api_perms', implode(',', $perms)); flash($sf->t('saved')); redirect('/p/hr/settings');
    });
};
