<?php
declare(strict_types=1);
/** Renders every HR page with real sample data (engine + in-memory store) for an administrator and for a plain employee; a warning or a missing translation fails.
 *  Also: en/ru have the same keys, every key used exists, the REST routes agree with openapi.yaml, the migration has every table, the payroll contract files are valid. Run: php tests/hrviews.php */
foreach (['mb_substr', 'mb_strlen'] as $f) if (!function_exists($f)) eval("function $f(string \$s, int \$a = 0, ?int \$l = null) { return " . ($f === 'mb_strlen' ? 'strlen($s)' : '$l === null ? substr($s, $a) : substr($s, $a, $l)') . '; }');
if (!function_exists('e')) { function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); } }
if (!function_exists('csrf_field')) { function csrf_field(): string { return '<input type="hidden" name="_t" value="x">'; } }
if (!function_exists('csrf_token')) { function csrf_token(): string { return 'x'; } }
if (!function_exists('url')) { function url(string $p = '/'): string { return '/' . ltrim($p, '/'); } }
$dir = dirname(__DIR__) . '/core/plugins/hr';
foreach (['Err', 'Sig', 'Audit', 'Crypt', 'Store', 'Ctx', 'Rbac', 'Fields', 'Payroll', 'TimeOff', 'Pipeline', 'Bridges', 'Env', 'Hr', 'Recruit', 'Leave', 'Api'] as $c) require "$dir/lib/$c.php";
require __DIR__ . '/HrMemStore.php';
use SysferaHr\{Env, Rbac, Hr, Recruit, Leave, Api, Crypt, Payroll, Pipeline};
$fail = 0; function ok(bool $c, string $n): void { global $fail; echo ($c ? '  ok   ' : '  FAIL ') . $n . "\n"; if (!$c) $fail++; }
set_error_handler(function (int $no, string $msg, string $file, int $line): bool { throw new ErrorException("$msg in " . basename($file) . ":$line"); });
$en = require "$dir/lang/en.php"; $ru = require "$dir/lang/ru.php";

final class FakeSf {
    public array $manifest = ['version' => '0.1.0']; public array $missing = [];
    public function t(string $k, array $v = []): string { global $en; if (!isset($en[$k])) { $this->missing[] = $k; return 'hr.' . $k; } $s = $en[$k]; foreach ($v as $a => $b) $s = str_replace('{' . $a . '}', (string)$b, $s); return $s; }
    public function url(string $p = ''): string { return '/p/hr/' . ltrim($p, '/'); }
    public function asset(string $f): string { return '/assets/p/hr/' . $f; }
}
$sf = new FakeSf();

/* ---------- a populated company ---------- */
$now = strtotime('2026-11-17 10:00:00'); $st = new HrMemStore(); $spy = new HrSpy(); $rbac = new Rbac($st); $rbac->ensureBuiltin();
$env = new Env($st, $rbac, $spy->bridges(), Crypt::newKey(), fn() => $now, ['collect_passport' => true]);
$hr = new Hr($env); $rec = new Recruit($env, $hr); $lv = new Leave($env, $hr); $ops = Api::ops($env, $hr, $rec, $lv);
$root = new SysferaHr\Ctx(1, 'admin', [], true, null); $call = fn(string $op, array $in = [], ?SysferaHr\Ctx $c = null) => $ops[$op]($c ?? $root, $in);
$role = fn(string $code) => $st->one('roles', ['code' => $code]);
$rOp = $role('warehouse_operator'); $team = $call('teams.save', ['name' => 'Pickup Moscow', 'warehouse_id' => 1, 'mission_ref' => 3]);
$sch = $call('schedules.save', ['name' => 'Office 5/2', 'week' => [1 => 8, 2 => 8, 3 => 8, 4 => 8, 5 => 8, 6 => 0, 7 => 0]]);
$boss = $call('employees.create', ['full_name' => 'Ivan Manager', 'email' => 'ivan@x.ru', 'hire_date' => '2025-01-10', 'user_id' => 4]);
$vac = $call('vacancies.save', ['role_uuid' => $rOp['uuid'], 'title' => 'Pickup operator', 'description' => 'text', 'team_uuid' => $team['uuid']]); $call('vacancies.approve', ['uuid' => $vac['uuid']]); $call('vacancies.publish', ['uuid' => $vac['uuid']]);
$c1 = $call('candidates.add', ['uuid' => $vac['uuid'], 'full_name' => 'Maria Petrova', 'email' => 'maria@x.ru', 'phone' => '+7 900']); $c2 = $call('candidates.add', ['uuid' => $vac['uuid'], 'full_name' => 'Pavel', 'email' => 'p@x.ru']);
$call('candidates.move', ['uuid' => $c1['uuid'], 'stage' => 'screening']); $iv = $call('interviews.schedule', ['uuid' => $c1['uuid'], 'interviewer_user_id' => 4, 'starts_at' => '2026-11-20 11:00', 'ends_at' => '2026-11-20 12:00']);
$call('interviews.feedback', ['uuid' => $iv['uuid'], 'scores' => ['skills' => 4, 'communication' => 4, 'culture' => 5, 'motivation' => 4], 'recommend' => 'yes', 'comment' => 'ok'], $root);
$call('candidates.move', ['uuid' => $c1['uuid'], 'stage' => 'offer']); $hired = $call('candidates.hire', ['uuid' => $c1['uuid'], 'hire_date' => '2026-06-01']); $uuid = $hired['employee']['uuid']; $emp = $env->emp($uuid);
$st->update('employees', (int)$emp['id'], ['user_id' => 3, 'manager_id' => (int)$env->emp($boss['uuid'])['id'], 'team_id' => (int)$team['id'], 'warehouse_id' => 1]);
$call('consent.set', ['uuid' => $uuid, 'kind' => 'personal_data', 'action' => 'grant']); $call('employees.update', ['uuid' => $uuid, 'passport' => '4500 123456', 'department' => 'Logistics']);
$d = $call('docs.add', ['uuid' => $uuid, 'kind' => 'contract', 'title' => 'Contract', 'body' => 'text']); $call('docs.add', ['uuid' => $uuid, 'kind' => 'nda', 'title' => 'NDA', 'body' => 'nda']);
$call('pay.set', ['uuid' => $uuid, 'profile' => ['type' => 'fixed', 'base_kop' => 9000000, 'mission_bonus_kop' => 50000, 'gi' => ['per_issue_kop' => 1000], 'deductions' => [['code' => 'ndfl', 'label' => 'Income tax', 'kind' => 'percent', 'value' => 1300]]]]);
$call('rbac.grant', ['user_id' => 3, 'role_uuid' => $rOp['uuid']]); $call('rbac.grant', ['user_id' => 4, 'role_uuid' => $role('manager')['uuid']]);
$lr = $call('leave.request', ['employee_uuid' => $uuid, 'kind' => 'vacation', 'starts_on' => '2026-12-07', 'ends_on' => '2026-12-11']);
$call('leave.request', ['employee_uuid' => $uuid, 'kind' => 'sick', 'starts_on' => '2026-11-18', 'ends_on' => '2026-11-18']);
$call('payroll.export', ['from' => '2026-11-01', 'to' => '2026-11-30']);
$spy->gi[9] = ['role' => 'operator', 'perms' => '', 'warehouse' => 1];   // stale access: a user without a profile
$late = $call('employees.create', ['full_name' => 'Late Larry', 'email' => 'l@x.ru', 'hire_date' => '2026-10-01']);
$dis = $call('employees.create', ['full_name' => 'Gone Gina', 'email' => 'g@x.ru', 'hire_date' => '2024-01-01', 'user_id' => 5]); $call('offboard', ['uuid' => $dis['uuid'], 'reason' => 'resigned']);
$call('tasks.add', ['uuid' => $uuid, 'phase' => 'onboarding', 'code' => 'custom_badge']);

/* ---------- pages ---------- */
$users = [['id' => 3, 'name' => 'Maria', 'email' => 'm@x.ru'], ['id' => 4, 'name' => 'Ivan', 'email' => 'i@x.ru']]; $whs = [['id' => 1, 'code' => 'PVZ', 'name' => 'Pickup 1']];
$active = fn() => array_values(array_filter($call('roles.list'), fn($r) => (int)$r['active']));
$access = array_map(fn($u) => $u + array_intersect_key($call('rbac.user', ['user_id' => $u['id']]), ['roles' => 1, 'permissions' => 1, 'warehouse' => 1]), $users);
$vars = function (SysferaHr\Ctx $c, string $who) use ($call, $uuid, $vac, $env, $hr, $st, $users, $whs, $active, $access, $emp, $lr, $team, $boss, $sch) {
    $eView = $call('employees.get', ['uuid' => $uuid], $c); $try = function (callable $f, $d = null) { try { return $f(); } catch (SysferaHr\Err $e) { return $d; } };
    $board = $try(fn() => $call('candidates.board', ['uuid' => $vac['uuid']], $c), ['vacancy' => [], 'stages' => Pipeline::STAGES, 'columns' => array_fill_keys(Pipeline::STAGES, [])]);
    $hist = $try(fn() => array_map(fn($cd) => $call('candidates.history', ['uuid' => $cd['uuid']], $c), array_merge(...array_values($board['columns']))), []);
    $doc = $call('payroll.calc', ['from' => '2026-11-01', 'to' => '2026-11-30'], new SysferaHr\Ctx(5, 'anna', ['hr.payroll.run', 'hr.pay.view'], false, null));
    $p = [
        'dashboard' => ['tab' => 'dashboard', 'vac' => $try(fn() => $call('vacancies.list', [], $c), []), 'alerts' => $try(fn() => $call('onboarding.alerts', ['notify' => '0'], $c), []), 'pending' => $try(fn() => $call('leave.list', ['status' => 'pending_manager'], $c), []), 'orphans' => $try(fn() => $call('rbac.orphans', [], $c), []), 'missing' => $try(fn() => $call('consent.missing', [], $c), []), 'emps' => $try(fn() => $call('employees.list', [], $c), []), 'isHr' => $c->can('hr.employees.view'), 'myLeave' => $try(fn() => $call('leave.list', [], $c), [])],
        'roles' => ['tab' => 'roles', 'roles' => $try(fn() => $call('roles.list', [], $c), []), 'catalog' => Rbac::CATALOG],
        'vacancies' => ['tab' => 'vacancies', 'vac' => $try(fn() => $call('vacancies.list', [], $c), []), 'roles' => $try($active, []), 'teams' => $try(fn() => $call('teams.list', [], $c), []), 'st' => ''],
        'vacancy' => ['tab' => 'vacancies', 'v' => $try(fn() => $call('vacancies.get', ['uuid' => $vac['uuid']], $c), []), 'board' => $board, 'versions' => $try(fn() => $call('vacancies.versions', ['uuid' => $vac['uuid']], $c), []), 'roles' => $try($active, []), 'teams' => $try(fn() => $call('teams.list', [], $c), []), 'users' => $users, 'interviews' => array_values(array_filter($hist, fn($h) => !empty($h['interviews'])))],
        'employees' => ['tab' => 'employees', 'emps' => $try(fn() => $call('employees.list', [], $c), []), 'st' => 'active', 'q' => '', 'roles' => $try($active, []), 'teams' => $try(fn() => $call('teams.list', [], $c), []), 'users' => $users],
        'employee' => ['tab' => 'employees', 'e' => $eView, 'docs' => $try(fn() => $call('docs.list', ['uuid' => $uuid], $c), []), 'pay' => $try(fn() => $call('pay.get', ['uuid' => $uuid], $c)['profile'], null), 'payAllowed' => $c->can('hr.pay.view') || (int)$eView['id'] === $c->employeeId,
            'timeline' => $try(fn() => $call('employees.timeline', ['uuid' => $uuid], $c), []), 'tasks' => ['onboarding' => $try(fn() => $call('tasks.list', ['uuid' => $uuid, 'phase' => 'onboarding'], $c), []), 'offboarding' => []], 'balance' => $try(fn() => $call('leave.balance', ['uuid' => $uuid], $c)), 'consents' => $st->find('consents', ['employee_id' => (int)$emp['id']]), 'consentOn' => true,
            'roles' => $try($active, []), 'teams' => $try(fn() => $call('teams.list', [], $c), []), 'schedules' => $try(fn() => $call('schedules.list', [], $c), []), 'managers' => $try(fn() => $call('employees.list', [], $c), []), 'audit' => $try(fn() => $call('audit.list', ['entity' => 'employee', 'ref' => $uuid], $c), []),
            'access' => $try(fn() => $call('rbac.user', ['user_id' => 3], $c)), 'users' => $users, 'self' => (int)$eView['id'] === $c->employeeId, 'editAll' => $c->can('hr.employees.edit'), 'pii' => $c->can('hr.employees.pii'), 'docsAdmin' => $c->can('hr.docs.manage'), 'payAdmin' => $c->can('hr.pay.manage'), 'offboardOk' => $c->can('hr.offboard'), 'taskAdmin' => $c->can('hr.onboarding.manage'), 'collectPassport' => true, 'whs' => $whs],
        'teams' => ['tab' => 'teams', 'teams' => $try(fn() => $call('teams.list', [], $c), []), 'emps' => $try(fn() => $call('employees.list', [], $c), []), 'whs' => $whs],
        'leave' => ['tab' => 'leave', 'mine' => $try(fn() => $call('leave.list', ['employee_uuid' => $uuid], $c), []), 'queue' => $try(fn() => $call('leave.list', ['status' => 'pending_manager'], $c), []), 'cal' => $try(fn() => $call('leave.calendar', ['month' => '2026-12'], $c), ['month' => '2026-12', 'entries' => []]), 'month' => '2026-12', 'team' => '', 'teams' => $try(fn() => $call('teams.list', [], $c), []), 'schedules' => $try(fn() => $call('schedules.list', [], $c), []), 'balance' => $try(fn() => $call('leave.balance', ['uuid' => $uuid], $c)), 'meUuid' => $uuid, 'canHr' => $c->can('hr.leave.approve'), 'canEdit' => $c->can('hr.employees.edit'), 'myId' => $c->employeeId],
        'access' => ['tab' => 'access', 'users' => $access, 'roles' => $try(fn() => $call('roles.list', [], $c), []), 'catalog' => Rbac::CATALOG, 'orphans' => $try(fn() => $call('rbac.orphans', [], $c), [])],
        'payroll' => ['tab' => 'payroll', 'from' => '2026-11-01', 'to' => '2026-11-30', 'doc' => $doc, 'err' => 'x', 'runs' => $st->find('payroll_runs'), 'emps' => $try(fn() => $call('employees.list', [], $c), [])],
        'audit' => ['tab' => 'audit', 'rows' => $try(fn() => $call('audit.list', [], $c), []), 'verify' => ['ok' => false, 'rows' => 9, 'broken_at' => 4], 'entity' => 'employee', 'action' => ''],
        'privacy' => ['tab' => 'privacy', 'missing' => $try(fn() => $call('consent.missing', [], $c), []), 'exports' => $st->audits('export.employees'), 'version' => '1', 'collect' => true, 'cols' => Hr::EXPORT_COLUMNS],
        'settings' => ['tab' => 'settings', 'key' => str_repeat('k', 64), 'base' => '/api/hr/v1/', 'v' => ['annual_days' => '28', 'onboarding_days' => '3', 'consent_version' => '1', 'collect_passport' => '1', 'holidays' => '', 'api_perms' => 'hr.pay.view'], 'keyFile' => true],
    ];
    return $p;
};
$render = function (string $view, array $vars, SysferaHr\Ctx $c) use ($sf, $dir, $emp): string { extract($vars + ['sf' => $sf, 'c' => $c, 'me' => $emp]); ob_start(); try { include "$dir/views/$view.php"; } finally { $out = (string)ob_get_clean(); } return $out; };
$ctxs = ['administrator' => $root, 'employee' => new SysferaHr\Ctx(3, 'maria', $rbac->permsOf(3), false, (int)$emp['id']), 'manager' => $rbac->ctx(4, 'ivan', false, (int)$env->emp($boss['uuid'])['id'])];
$pages = 0;
foreach ($ctxs as $who => $c) {
    $gate = ['roles' => 'hr.roles.manage', 'vacancies' => 'hr.vacancies.manage', 'vacancy' => 'hr.vacancies.manage', 'employees' => 'hr.employees.view', 'teams' => 'hr.employees.view', 'access' => 'hr.rbac.manage', 'payroll' => 'hr.payroll.run', 'audit' => 'hr.audit.view', 'privacy' => 'hr.consent.manage'];
    foreach ($vars($c, $who) as $view => $v) {
        if ((isset($gate[$view]) && !$c->can($gate[$view])) || ($view === 'settings' && !$c->root)) continue;   // the router answers 403 there
        try { $h = $render($view, $v, $c); $pages++; ok(strlen($h) > 80, "renders: $view as $who"); } catch (Throwable $x) { ok(false, "renders: $view as $who — " . $x->getMessage()); }
    }
}
$miss = array_values(array_diff(array_unique($sf->missing), ['task_custom_badge']));   // a custom checklist item falls back to its code
ok($miss === [], 'every translation key the pages use exists in en' . ($miss ? ' — missing: ' . implode(', ', $miss) : ''));
$adm = $render('employee', $vars($root, 'a')['employee'], $root); $emv = $render('employee', $vars($ctxs['employee'], 'e')['employee'], $ctxs['employee']);
ok(str_contains($adm, 'name="passport"') && str_contains($adm, '4500 123456') && str_contains($adm, 'name="pay[base]"'), 'employee page (HR): passport and pay forms are there');
ok(str_contains($emv, 'name="phone" value="+7 900"') && preg_match('/name="full_name"[^>]*disabled/', $emv) === 1 && !preg_match('/name="phone"[^>]*disabled/', $emv) && !str_contains($emv, 'name="pay[base]"') && str_contains($emv, 'sign_esign') === false && str_contains($emv, 'Sign electronically'), 'employee page (self): own phone editable, critical fields locked, no pay editor, e-sign offered');
$mgr = $render('employee', $vars($ctxs['manager'], 'm')['employee'], $ctxs['manager']);
ok(!str_contains($mgr, '4500 123456') && !str_contains($mgr, 'Employment contract') && !str_contains($mgr, '90000.00'), 'employee page (manager): no passport, no salary');
ok(str_contains($render('vacancy', $vars($root, 'a')['vacancy'], $root), 'draggable="true"'), 'vacancy page: kanban cards are draggable');

/* ---------- translations ---------- */
ok(array_keys($en) === array_keys($ru), 'lang: en and ru have the same keys in the same order');
ok(count(array_filter($ru, fn($v) => trim((string)$v) === '')) === 0 && count(array_filter($en, fn($v) => trim((string)$v) === '')) === 0, 'lang: no empty translation');
ok(count(array_filter(array_keys($en), fn($k) => $en[$k] === $ru[$k] && !preg_match('/^(wd_|f_code|api_key|f_email)/', $k) && strlen($en[$k]) > 12)) === 0, 'lang: long texts are really translated');
$src = ''; foreach (glob("$dir/views/*.php") as $f) $src .= file_get_contents($f); $src .= file_get_contents("$dir/plugin.php");
preg_match_all("/t\('([a-z_0-9]+)'[,)]/", $src, $m); $missing = array_values(array_diff(array_unique($m[1]), array_keys($en)));
ok($missing === [], 'lang: every static key used in views and plugin.php exists' . ($missing ? ' — missing: ' . implode(', ', $missing) : ''));
$fam = ['wf_' => ['draft', 'approved', 'published', 'closed'], 'vs_' => ['open', 'closed', 'archived'], 'stage_' => Pipeline::STAGES, 'is_' => ['scheduled', 'done', 'cancelled'], 'rec_' => Pipeline::RECOMMEND, 'crit_' => Pipeline::CRITERIA, 'es_' => ['active', 'dismissed'], 'et_' => SysferaHr\Fields::EMP_TYPES,
    'dk_' => Hr::DOC_KINDS, 'st_' => ['unsigned', 'signed', 'expired'], 'sm_' => ['esign', 'scan'], 'pt_' => Payroll::TYPES, 'task_' => array_merge(Hr::ONBOARDING, Hr::OFFBOARDING), 'lk_' => array_merge(SysferaHr\TimeOff::KINDS, ['absent']), 'ls_' => SysferaHr\TimeOff::STATUSES, 'wd_' => range(1, 7),
    'tl_' => ['hire', 'transfer', 'role_change', 'vacation', 'sick', 'dismissal', 'recruiting'], 'tr_' => ['member', 'lead'], 'role_' => ['operator', 'office', 'viewer'], 'orphan_' => ['no_employee', 'dismissed', 'no_permission'],
    'pl_' => ['base_fixed', 'base_guaranteed', 'base_hourly', 'bonus_mission', 'commission', 'bonus_gi_issue', 'bonus_gi_receipt', 'bonus_gi_return', 'bonus_gi_count'], 'perm_' => array_map(fn($p) => str_replace('.', '_', $p), Rbac::CATALOG), 'act_' => ['approve', 'reject', 'publish', 'close', 'archive', 'hire', 'expire', 'offboard']];
$bad = []; foreach ($fam as $p => $vals) foreach ($vals as $v) if (!isset($en[$p . $v])) $bad[] = $p . $v;
ok($bad === [], 'lang: every dynamic key family is complete' . ($bad ? ' — missing: ' . implode(', ', $bad) : ''));
preg_match_all("/new Err\('([a-z_]+)'/", (string)implode('', array_map('file_get_contents', glob("$dir/lib/*.php"))), $em); $noerr = array_values(array_diff(array_unique($em[1]), ['bad_json', 'stale_request', 'bad_signature', 'bad_uuid', 'bad_code', 'bad_kind', 'bad_method', 'bad_scan', 'bad_role', 'bad_phase', 'bad_consent', 'bad_permission', 'bad_decision', 'bad_user', 'bad_manager', 'bad_dates', 'bad_emp_type', 'bad_deduction', 'bad_feedback', 'bad_slot', 'bad_stage', 'bad_pay_type', 'busy', 'inactive', 'no_workdays', 'use_hire', 'started', 'unknown']));
$noerr = array_values(array_filter($noerr, fn($c) => !isset($en['err_' . $c]))); ok($noerr === [], 'lang: user-facing error codes have a translation' . ($noerr ? ' — missing: ' . implode(', ', $noerr) : ''));

/* ---------- API contract ---------- */
$yaml = (string)file_get_contents("$dir/openapi.yaml"); $doc = []; $cur = null;
foreach (explode("\n", $yaml) as $line) { if (preg_match('#^  (/[^ ]*):$#', $line, $mm)) $cur = $mm[1]; elseif ($cur && preg_match('/^    (get|post|put|delete|patch):$/', $line, $mm)) $doc[$cur][] = $mm[1]; elseif (preg_match('/^\S/', $line)) $cur = $cur && !str_starts_with($line, ' ') && $line !== 'paths:' ? null : $cur; }
$routes = []; foreach (Api::ROUTES as [$meth, $path]) $routes['/' . $path][] = strtolower($meth);
$undoc = []; foreach ($routes as $p => $ms) foreach ($ms as $mth) if (!in_array($mth, $doc[$p] ?? [], true)) $undoc[] = strtoupper($mth) . " $p";
$extra = []; foreach ($doc as $p => $ms) foreach ($ms as $mth) if (!in_array($mth, $routes[$p] ?? [], true)) $extra[] = strtoupper($mth) . " $p";
ok($undoc === [] && $extra === [], 'openapi: every REST route is documented and nothing else' . ($undoc ? ' — undocumented: ' . implode(', ', $undoc) : '') . ($extra ? ' — unknown: ' . implode(', ', $extra) : ''));
$unimpl = array_values(array_filter(array_unique(array_column(Api::ROUTES, 2)), fn($o) => !isset($ops[$o]))); ok($unimpl === [], 'api: every route maps to an operation' . ($unimpl ? ' — ' . implode(', ', $unimpl) : ''));
ok(count(array_unique(array_map(fn($r) => $r[0] . ' ' . $r[1], Api::ROUTES))) === count(Api::ROUTES), 'api: no duplicate routes');
ok(Api::match('employees/{uuid}/pay', 'employees/abc/pay') === ['uuid' => 'abc'] && Api::match('roles', 'roles/x') === null, 'api: path matcher');
$mig = (string)file_get_contents("$dir/migrations/001_hr.sql"); require_once "$dir/lib/DbStore.php";
$missingT = array_values(array_filter(SysferaHr\DbStore::TABLES, fn($t) => !str_contains($mig, '{p}hr_' . $t . ' (')));
ok($missingT === [], 'migration: every table of the store exists' . ($missingT ? ' — ' . implode(', ', $missingT) : '')); ok(!preg_match('/\b(DROP|TRUNCATE)\b|DELETE FROM|ALTER TABLE .* DROP/i', $mig), 'migration: additive only');
ok(json_decode((string)file_get_contents("$dir/plugin.json"), true)['slug'] === 'hr', 'plugin.json: slug hr');
foreach (['audit', 'status_history', 'consents'] as $t) { try { (new HrMemStore())->delete($t, 1); $x = true; } catch (RuntimeException) { $x = false; } ok(!$x, "store: $t refuses deletes"); }
$pl = (string)file_get_contents("$dir/plugin.php"); ok(!preg_match('/hr_audit\s+(SET|WHERE)|UPDATE\s+\{p\}hr_audit|DELETE\s+FROM\s+\{p\}hr_audit/i', $pl . (string)file_get_contents("$dir/lib/DbStore.php")), 'audit table: no UPDATE / DELETE statement in the plugin');
ok(!preg_match('/(api_key|hr\.key)[\'"]?\s*=>\s*[\'"][0-9a-f]{16,}/', $pl), 'no secret in the code');

echo $fail ? "\n$fail FAILED\n" : "\nAll good ($pages pages rendered)\n"; exit($fail ? 1 : 0);
