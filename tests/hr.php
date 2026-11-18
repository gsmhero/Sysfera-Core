<?php
declare(strict_types=1);
/** HR engine tests: RBAC checker, field permissions, recruiting, documents, pay, payroll export, leave, onboarding / offboarding, audit chain. No database. Run: php tests/hr.php */
$dir = dirname(__DIR__) . '/core/plugins/hr';
foreach (['Err', 'Sig', 'Audit', 'Crypt', 'Store', 'Ctx', 'Rbac', 'Fields', 'Payroll', 'TimeOff', 'Pipeline', 'Bridges', 'Env', 'Hr', 'Recruit', 'Leave'] as $c) require "$dir/lib/$c.php";
require __DIR__ . '/HrMemStore.php';
use SysferaHr\{Err, Audit, Crypt, Ctx, Rbac, Fields, Payroll, TimeOff, Pipeline, Env, Hr, Recruit, Leave, Sig};

$fail = 0; function ok(bool $c, string $n): void { global $fail; echo ($c ? '  ok   ' : '  FAIL ') . $n . "\n"; if (!$c) $fail++; }
function refused(callable $f, string $code): bool { try { $f(); return false; } catch (Err $e) { return $e->errCode === $code; } }
set_error_handler(function (int $no, string $msg, string $file, int $line): bool { throw new ErrorException("$msg in " . basename($file) . ":$line"); });

/* ---------- crypto, signatures ---------- */
$k = Crypt::newKey(); $sealed = Crypt::seal('salary 150000', $k);
ok(Crypt::open($sealed, $k) === 'salary 150000' && !str_contains($sealed, '150000'), 'crypt: round trip, plaintext not visible');
ok(Crypt::open($sealed, Crypt::newKey()) === null, 'crypt: wrong key refused');
$raw = base64_decode(substr($sealed, 3)); $raw[50] = chr(ord($raw[50]) ^ 1); $bad = 'h1:' . base64_encode($raw);
ok(Crypt::open($bad, $k) === null, 'crypt: tampered value refused');
ok(Crypt::seal('x', $k) !== Crypt::seal('x', $k), 'crypt: fresh nonce each time');
ok(Sig::ok(Sig::request('k', '100', 'post', '/a', '{}'), Sig::request('k', '100', 'POST', '/a', '{}')) && !Sig::ok(Sig::request('k', '100', 'POST', '/a', '{}'), Sig::request('k', '100', 'POST', '/a', '{"x":1}')), 'sig: body is covered');

/* ---------- the permission checker (pure) ---------- */
ok(Rbac::matches(['hr.*'], 'hr.pay.view') && !Rbac::matches(['hr.*'], 'warehouse.issue') && Rbac::matches(['*'], 'x.y') && !Rbac::matches(['hr.pay.view'], 'hr.pay.manage') && !Rbac::matches([], 'hr.pay.view'), 'rbac: exact, wildcard, root, none');
ok(!Rbac::matches(['hr.pay'], 'hr.pay.view') && !Rbac::matches(['hr.*'], 'hrx.y'), 'rbac: no accidental prefix match');
ok(Rbac::valid('hr.*') && Rbac::valid('warehouse.issue') && !Rbac::valid('hr.fly') && !Rbac::valid('nope.*'), 'rbac: permission names are validated');
ok(Rbac::warehouse(['warehouse.receive', 'warehouse.issue', 'warehouse.return', 'warehouse.count', 'warehouse.shelf']) === ['role' => 'operator', 'perms' => ''], 'warehouse: all five = operator with all rights');
ok(Rbac::warehouse(['warehouse.receive', 'warehouse.count']) === ['role' => 'operator', 'perms' => 'receive,count'], 'warehouse: partial rights');
ok(Rbac::warehouse(['warehouse.office', 'warehouse.issue']) === ['role' => 'office', 'perms' => ''] && Rbac::warehouse(['warehouse.view']) === ['role' => 'viewer', 'perms' => ''] && Rbac::warehouse(['hr.*']) === null && Rbac::warehouse(['*']) === ['role' => 'office', 'perms' => ''], 'warehouse: office / viewer / none');

/* ---------- fixture ---------- */
$now = strtotime('2026-11-17 10:00:00'); $st = new HrMemStore(); $spy = new HrSpy(); $rbac = new Rbac($st); $rbac->ensureBuiltin();
$e = new Env($st, $rbac, $spy->bridges(), Crypt::newKey(), fn() => $now, ['collect_passport' => true]);
$hr = new Hr($e); $rec = new Recruit($e, $hr); $lv = new Leave($e, $hr);
$role = fn(string $code) => $st->one('roles', ['code' => $code]);
$root = new Ctx(1, 'admin', [], true, null);   // Sysfera administrator
$as = fn(int $uid, string $name) => $rbac->ctx($uid, $name, false, ($x = $e->empOfUser($uid)) ? (int)$x['id'] : null);
ok(count($st->t['roles']) === count(Rbac::BUILTIN), 'roles: built-in catalog seeded');
$rbac->ensureBuiltin(); ok(count($st->t['roles']) === count(Rbac::BUILTIN), 'roles: seeding is idempotent');

/* ---------- role catalog ---------- */
$hrAdmin = $hr->saveRole($root, ['code' => 'hr_boss', 'name' => 'HR boss', 'perms' => ['hr.*']]);
$st->insert('user_roles', ['user_id' => 2, 'role_id' => (int)$role('hr_admin')['id'], 'granted_by' => 't', 'granted_at' => 'x']);   // user 2 = HR administrator
$oleg = $as(2, 'oleg');
$rolePm = $hr->saveRole($oleg, ['code' => 'pm', 'name' => 'Project manager', 'descr' => 'runs projects', 'perms' => ['hr.employees.view']]);
ok($rolePm['code'] === 'pm' && refused(fn() => $hr->saveRole($oleg, ['code' => 'pm', 'name' => 'dup']), 'duplicate'), 'roles: create, unique code');
ok(refused(fn() => $hr->saveRole($oleg, ['code' => 'bad', 'name' => 'x', 'perms' => ['hr.fly']]), 'bad_permission'), 'roles: unknown permission refused');
$hr->saveRole($oleg, ['uuid' => $rolePm['uuid'], 'name' => 'Project lead']);
ok($st->one('roles', ['code' => 'pm'])['name'] === 'Project lead', 'roles: edit');
$hr->setRoleActive($oleg, $rolePm['uuid'], false); ok((int)$st->one('roles', ['code' => 'pm'])['active'] === 0, 'roles: deactivate');
ok(refused(fn() => $hr->saveRole($as(9, 'nobody'), ['code' => 'zz', 'name' => 'x']), 'forbidden'), 'roles: needs hr.roles.manage');

/* ---------- vacancies ---------- */
$roleOp = $role('warehouse_operator');
$v = $rec->saveVacancy($oleg, ['role_uuid' => $roleOp['uuid'], 'title' => 'Pickup operator', 'description' => 'v1 text']);
ok($v['wf'] === 'draft' && $v['status'] === 'open' && $v['version'] === 1 && $v['description'] === 'v1 text', 'vacancy: created as draft, version 1');
ok(refused(fn() => $rec->publish($oleg, $v['uuid']), 'bad_state'), 'vacancy: cannot publish a draft');
$rec->approve($oleg, $v['uuid']); $spy->posts = [];
$boards = []; $rec->addBoard('hh', function ($vac, $ver) use (&$boards) { $boards[] = $ver['version']; return 'ext-77'; });
$p = $rec->publish($oleg, $v['uuid']);
ok($p['wf'] === 'published' && $p['post_ref'] === 'post-1' && count($spy->posts) === 1 && $boards === [1] && str_contains((string)$p['external'], 'ext-77'), 'vacancy: published to the Activity feed + job-board hook');
$rec->publish($oleg, $v['uuid']); ok(count($spy->posts) === 1, 'vacancy: publish is idempotent');
$v2 = $rec->saveVacancy($oleg, ['uuid' => $v['uuid'], 'title' => 'Pickup operator', 'description' => 'v2 text']);
ok($v2['version'] === 2 && $v2['wf'] === 'draft' && count($rec->versions($oleg, $v['uuid'])) === 2, 'vacancy: new description = new version, back to draft for re-approval');
$rec->saveVacancy($oleg, ['uuid' => $v['uuid'], 'title' => 'Pickup operator (edited)', 'description' => 'v2 text']);
ok($st->one('vacancies', ['uuid' => $v['uuid']])['version'] === 2, 'vacancy: same text does not make a version');
ok(refused(fn() => $rec->approve($as(9, 'x'), $v['uuid']), 'forbidden') && refused(fn() => $rec->archive($oleg, $v['uuid']), 'bad_state'), 'vacancy: approval needs its permission; archive needs closed');
$rec->approve($oleg, $v['uuid']); $rec->publish($oleg, $v['uuid']);

/* ---------- candidates, interviews, feedback, hire ---------- */
$c1 = $rec->addCandidate($oleg, $v['uuid'], ['full_name' => 'Maria Petrova', 'email' => 'Maria@Example.com', 'phone' => '+7 900 111-22-33']);
ok($c1['stage'] === 'new' && !isset($c1['phone_enc']) && str_starts_with($st->one('candidates', ['uuid' => $c1['uuid']])['phone_enc'], 'h1:'), 'candidate: added, phone sealed at rest');
ok(refused(fn() => $rec->addCandidate($oleg, $v['uuid'], ['full_name' => 'Dup', 'email' => 'maria@example.com']), 'duplicate'), 'candidate: no duplicate application');
ok(refused(fn() => $rec->move($oleg, $c1['uuid'], 'offer'), 'bad_move'), 'pipeline: cannot skip stages');
$rec->move($oleg, $c1['uuid'], 'screening');
$i1 = $rec->schedule($oleg, $c1['uuid'], ['interviewer_user_id' => 4, 'starts_at' => '2026-11-20 11:00', 'ends_at' => '2026-11-20 12:00']);
ok($st->one('candidates', ['uuid' => $c1['uuid']])['stage'] === 'interview' && $i1['status'] === 'scheduled', 'interview: scheduling moves the candidate to interview');
$c2 = $rec->addCandidate($oleg, $v['uuid'], ['full_name' => 'Pavel', 'email' => 'p@example.com']); $rec->move($oleg, $c2['uuid'], 'screening');
ok(refused(fn() => $rec->schedule($oleg, $c2['uuid'], ['interviewer_user_id' => 4, 'starts_at' => '2026-11-20 11:30', 'ends_at' => '2026-11-20 12:30']), 'slot_busy'), 'interview: interviewer double-booking refused');
$free = $rec->slots($oleg, 4, '2026-11-20'); ok(count($free) === 8 && !in_array('2026-11-20 11:00:00', array_column($free, 'starts_at'), true), 'interview: calendar slots exclude the busy hour');
ok(refused(fn() => $rec->move($oleg, $c1['uuid'], 'offer'), 'feedback_required'), 'pipeline: an offer needs feedback');
$ivan = $rbac->ctx(4, 'ivan', false); $st->insert('user_roles', ['user_id' => 4, 'role_id' => (int)$role('hr_interviewer')['id'], 'granted_by' => 't', 'granted_at' => 'x']); $ivan = $rbac->ctx(4, 'ivan', false);
ok(refused(fn() => $rec->feedback($rbac->ctx(5, 'anna', false), $i1['uuid'], []), 'forbidden') && refused(fn() => $rec->feedback($ivan, $i1['uuid'], ['scores' => ['skills' => 9]]), 'bad_feedback'), 'feedback: only the interviewer with permission; scores validated');
$fb = ['scores' => ['skills' => 4, 'communication' => 5, 'culture' => 4, 'motivation' => 5], 'recommend' => 'yes', 'comment' => 'good'];
$f1 = $rec->feedback($ivan, $i1['uuid'], $fb); $rec->feedback($ivan, $i1['uuid'], $fb);
ok((float)$f1['avg'] === 4.5 && count($st->t['feedback']) === 1 && $st->one('interviews', ['uuid' => $i1['uuid']])['status'] === 'done', 'feedback: structured, average, repeat is idempotent');
$rec->move($oleg, $c1['uuid'], 'offer');
ok(refused(fn() => $rec->move($oleg, $c1['uuid'], 'hired'), 'use_hire') && refused(fn() => $rec->move($oleg, $c2['uuid'], 'rejected'), 'note_required'), 'pipeline: hire has its own action; rejection needs a reason');
$rec->move($oleg, $c2['uuid'], 'rejected', 'not a fit');
$h = $rec->hire($oleg, $c1['uuid'], ['hire_date' => '2026-12-01', 'note' => 'start of month']);
$emp = $h['employee']; $eRow = $e->emp($emp['uuid']);
ok($h['candidate']['stage'] === 'hired' && $emp['full_name'] === 'Maria Petrova' && $emp['number'] === 'E00001' && $emp['phone'] === '+7 900 111-22-33' && $emp['position_role_uuid'] === $roleOp['uuid'], 'hire: the candidate became an employee with the role of the vacancy');
ok(count($st->find('status_history', ['employee_id' => (int)$eRow['id'], 'kind' => 'recruiting'])) >= 3 && $st->find('status_history', ['employee_id' => (int)$eRow['id'], 'kind' => 'hire']) !== [], 'hire: recruiting history kept in the employee timeline');
$h2 = $rec->hire($oleg, $c1['uuid'], ['hire_date' => '2026-12-01']); ok(count($st->t['employees']) === 1 && $h2['employee']['uuid'] === $emp['uuid'], 'hire: idempotent');
ok(count($st->find('tasks', ['employee_id' => (int)$eRow['id'], 'phase' => 'onboarding'])) === 4, 'onboarding: checklist generated on hire');
ok($st->audits('candidate.decision') !== [] && count($st->audits('interview.feedback')) === 1, 'audit: hiring decision and feedback logged');

/* ---------- employee card: field-level permissions ---------- */
$maria = $e->emp($emp['uuid']); $st->update('employees', (int)$maria['id'], ['user_id' => 3]); $mC = $as(3, 'maria');
ok($mC->employeeId === (int)$maria['id'] && !$mC->can('hr.employees.edit'), 'ctx: employee resolved from the account');
$u = $hr->updateEmployee($mC, $emp['uuid'], ['phone' => '+7 999 000-00-00']);
ok($u['phone'] === '+7 999 000-00-00' && $u['changed'] === ['phone'], 'fields: the employee edits their own phone');
ok(refused(fn() => $hr->updateEmployee($mC, $emp['uuid'], ['full_name' => 'Hacker']), 'forbidden_field') && refused(fn() => $hr->updateEmployee($mC, $emp['uuid'], ['hire_date' => '2020-01-01', 'phone' => '1']), 'forbidden_field') && refused(fn() => $hr->updateEmployee($mC, $emp['uuid'], ['status' => 'x']), 'forbidden_field') && refused(fn() => $hr->updateEmployee($mC, $emp['uuid'], ['passport' => '1234']), 'forbidden_field'), 'fields: critical, unknown and passport fields refused (nothing is applied)');
ok($e->emp($emp['uuid'])['phone'] === '+7 999 000-00-00', 'fields: a refused request changes nothing');
$other = $as(4, 'ivan'); ok(refused(fn() => $hr->updateEmployee($other, $emp['uuid'], ['phone' => '5']), 'forbidden_field'), 'fields: nobody else edits the phone without HR rights');
$hr->updateEmployee($oleg, $emp['uuid'], ['department' => 'Logistics', 'emp_type' => 'part_time']);
ok(refused(fn() => $hr->updateEmployee($oleg, $emp['uuid'], ['emp_type' => 'slave']), 'bad_emp_type'), 'fields: employment type validated');
ok(refused(fn() => $hr->updateEmployee($oleg, $emp['uuid'], ['passport' => '4500 123456']), 'consent_required'), 'privacy: passport needs the personal-data consent');
$hr->consent($mC, $emp['uuid'], 'personal_data', 'grant'); $hr->consent($mC, $emp['uuid'], 'personal_data', 'grant');
ok(count($st->t['consents']) === 1 && $hr->hasConsent($e->emp($emp['uuid']), 'personal_data'), 'consent: timestamped, versioned, a repeat adds nothing');
$hr->updateEmployee($oleg, $emp['uuid'], ['passport' => '4500 123456']);
ok(str_starts_with($e->emp($emp['uuid'])['passport_enc'], 'h1:') && !str_contains(json_encode($st->t['audit']), '123456'), 'privacy: passport sealed at rest and never in the audit log');
ok($hr->getEmployee($oleg, $emp['uuid'])['passport'] === '4500 123456' && $hr->getEmployee($mC, $emp['uuid'])['passport'] === '4500 123456', 'view: passport visible to HR and the employee');
$mgrC = $rbac->ctx(4, 'ivan', false); $st->insert('user_roles', ['user_id' => 4, 'role_id' => (int)$role('manager')['id'], 'granted_by' => 't', 'granted_at' => 'x']); $mgrC = $rbac->ctx(4, 'ivan', false);
$seen = $hr->getEmployee($mgrC, $emp['uuid']); ok($seen['passport'] === null && $seen['passport_set'] === true && !isset($seen['passport_enc']), 'view: others see no passport (only that one is stored)');
$log = array_filter($st->audits('employee.field'), fn($r) => str_contains($r['data'], '"phone"')); ok(count($log) === 1 && str_contains(end($log)['data'], '+7 999 000-00-00') && str_contains(end($log)['data'], '"old"'), 'audit: every change keeps who, field, when, old and new');
$before = count($st->audits('employee.field')); $hr->updateEmployee($oleg, $emp['uuid'], ['department' => 'Logistics']); ok(count($st->audits('employee.field')) === $before, 'audit: an unchanged value writes nothing');

/* ---------- documents ---------- */
$d1 = $hr->addDoc($oleg, $emp['uuid'], ['kind' => 'contract', 'title' => 'Employment contract', 'body' => 'text v1']);
$d1b = $hr->addDoc($oleg, $emp['uuid'], ['uuid' => $d1['uuid'], 'kind' => 'contract', 'title' => 'x', 'body' => 'other']); ok($d1b['uuid'] === $d1['uuid'] && count($st->t['docs']) === 1, 'docs: idempotent by uuid');
$d2 = $hr->addDoc($oleg, $emp['uuid'], ['kind' => 'contract', 'title' => 'Employment contract', 'body' => 'text v2', 'doc_key' => $d1['doc_key']]);
ok($d2['version'] === 2 && $d2['doc_key'] === $d1['doc_key'] && (int)$st->one('docs', ['uuid' => $d1['uuid']])['superseded'] === 1, 'docs: a new version supersedes the old one');
ok(refused(fn() => $hr->signDoc($mC, $d1['uuid'], ['method' => 'esign', 'confirm' => true]), 'superseded') && refused(fn() => $hr->signDoc($oleg, $d2['uuid'], ['method' => 'esign', 'confirm' => true]), 'forbidden') && refused(fn() => $hr->signDoc($mC, $d2['uuid'], ['method' => 'esign']), 'not_confirmed'), 'docs: e-sign by the employee only, with confirmation; old versions cannot be signed');
$s = $hr->signDoc($mC, $d2['uuid'], ['method' => 'esign', 'confirm' => true, 'body_sha256' => $d2['body_sha256']]);
ok($s['status'] === 'signed' && $s['sign_method'] === 'esign' && count($st->audits('doc.signed')) === 1, 'docs: signed, event logged');
$d3 = $hr->addDoc($oleg, $emp['uuid'], ['kind' => 'nda', 'title' => 'NDA', 'body' => 'nda text']);
$s3 = $hr->signDoc($oleg, $d3['uuid'], ['method' => 'scan', 'scan_base64' => base64_encode('%PDF-scan')]);
ok($s3['sign_method'] === 'scan' && $s3['scan_sha256'] === hash('sha256', '%PDF-scan') && $hr->scan($oleg, $d3['uuid']) === '%PDF-scan', 'docs: manual signed scan stored sealed and readable by HR');
ok(refused(fn() => $hr->scan($mC, $d3['uuid']), 'forbidden') && refused(fn() => $hr->listDocs($mgrC, $emp['uuid']), 'forbidden') && count($hr->listDocs($mC, $emp['uuid'])) === 3, 'docs: own documents visible, others need hr.docs.manage');
$byKind = array_column($hr->listDocs($oleg, $emp['uuid']), 'effective_status', 'uuid'); ok($byKind[$d1['uuid']] === 'unsigned', 'docs: status unsigned for the superseded version');
$hr->expireDoc($oleg, $d3['uuid']); ok($st->one('docs', ['uuid' => $d3['uuid']])['status'] === 'expired', 'docs: expired');
$ob = array_column($st->find('tasks', ['employee_id' => (int)$maria['id'], 'phase' => 'onboarding']), 'done', 'code');
ok((int)$ob['account'] === 1 && (int)$ob['role'] === 0, 'onboarding: automatic items follow reality (account linked, no role yet)');

/* ---------- pay profile ---------- */
$profile = ['type' => 'fixed', 'base_kop' => 9000000, 'mission_bonus_kop' => 50000, 'gi' => ['per_issue_kop' => 1500, 'per_receipt_kop' => 1000], 'deductions' => [['code' => 'ndfl', 'label' => 'Income tax', 'kind' => 'percent', 'value' => 1300]]];
$accC = new Ctx(5, 'anna', ['hr.pay.view', 'hr.payroll.run', 'hr.employees.view'], false, null);
ok(refused(fn() => $hr->setPay($mgrC, $emp['uuid'], ['profile' => $profile]), 'forbidden') && refused(fn() => $hr->setPay($accC, $emp['uuid'], ['profile' => $profile]), 'forbidden'), 'pay: only hr.pay.manage sets it');
ok(refused(fn() => $hr->setPay($oleg, $emp['uuid'], ['profile' => ['type' => 'barter']]), 'bad_pay_type') && refused(fn() => $hr->setPay($oleg, $emp['uuid'], ['profile' => ['type' => 'fixed', 'base_kop' => -5]]), 'bad_amount'), 'pay: validated');
$hr->setPay($oleg, $emp['uuid'], ['profile' => $profile]); $raw = $st->one('pay', ['employee_id' => (int)$maria['id']])['profile_enc'];
ok(str_starts_with($raw, 'h1:') && !str_contains($raw, '9000000') && !str_contains(json_encode($st->t['audit']), '9000000'), 'pay: salary sealed at rest and not in the audit log');
ok($hr->getPay($oleg, $emp['uuid'])['profile']['base_kop'] === 9000000 && $hr->getPay($accC, $emp['uuid'])['profile']['type'] === 'fixed' && $hr->getPay($mC, $emp['uuid'])['profile']['type'] === 'fixed', 'pay: visible to HR, Accounting and the employee');
ok(refused(fn() => $hr->getPay($mgrC, $emp['uuid']), 'forbidden') && refused(fn() => $hr->getPay($as(9, 'zz'), $emp['uuid']), 'forbidden'), 'pay: hidden from managers and everybody else');
ok($hr->setPay($oleg, $emp['uuid'], ['profile' => $profile])['changed'] === false, 'pay: same profile writes nothing');

/* ---------- RBAC administration + the warehouse app ---------- */
$st->update('employees', (int)$maria['id'], ['warehouse_id' => 7]); $spy->synced = [];
$acc = $hr->grant($oleg, 3, $roleOp['uuid']);
ok($acc['roles'] === ['warehouse_operator'] && $acc['warehouse'] === ['role' => 'operator', 'perms' => ''] && ($spy->gi[3]['role'] ?? '') === 'operator' && $spy->gi[3]['warehouse'] === 7, 'warehouse: the warehouse_operator role gives the Goods issue operator row with the employee warehouse');
$custom = $hr->saveRole($oleg, ['code' => 'wh_receiver', 'name' => 'Receiver', 'perms' => ['warehouse.receive', 'warehouse.count']]);
$hr->revoke($oleg, 3, $roleOp['uuid']); ok(!isset($spy->gi[3]), 'warehouse: revoking the role removes the Goods issue access');
$hr->grant($oleg, 3, $custom['uuid']); ok($spy->gi[3]['perms'] === 'receive,count', 'warehouse: partial rights are projected to gi_staff.perms');
$hr->saveRole($oleg, ['uuid' => $custom['uuid'], 'perms' => ['warehouse.receive', 'warehouse.count', 'warehouse.issue']]); ok($spy->gi[3]['perms'] === 'receive,issue,count', 'warehouse: changing the role permissions re-syncs its users');
$hr->grant($oleg, 3, $roleOp['uuid']); ok($spy->gi[3]['perms'] === '' && count($rbac->rolesOf(3)) === 2 && $mC->can('hr.pay.view') === false && $as(3, 'maria')->can('warehouse.shelf'), 'rbac: several roles per user, permissions are the union');
$hr->setRoleActive($oleg, $roleOp['uuid'], false); $hr->setRoleActive($oleg, $custom['uuid'], false); ok(!isset($spy->gi[3]) && $as(3, 'maria')->perms === [], 'rbac: deactivating the roles takes the rights away');
$hr->setRoleActive($oleg, $roleOp['uuid'], true);
$lim = new Ctx(6, 'lim', ['hr.rbac.manage', 'hr.roles.manage', 'warehouse.issue'], false, null); $spy->users[6] = 'lim';
ok(refused(fn() => $hr->grant($lim, 3, $role('hr_admin')['uuid']), 'escalation') && refused(fn() => $hr->saveRole($lim, ['uuid' => $custom['uuid'], 'perms' => ['hr.pay.view']]), 'escalation'), 'rbac: nobody grants what they do not hold');
ok(refused(fn() => $hr->grant($oleg, 99, $roleOp['uuid']), 'bad_user'), 'rbac: unknown user refused');
ok(count($st->audits('rbac.grant')) >= 3 && count($st->audits('rbac.revoke')) >= 1 && $st->audits('rbac.role_perms') !== [], 'audit: grants, revocations and role permission changes logged');
$chk = $hr->check($oleg, 3, 'warehouse.issue'); ok($chk['allowed'] === false || $chk['allowed'] === true, 'rbac: check endpoint answers');

/* ---------- teams ---------- */
$tm = $hr->saveTeam($oleg, ['name' => 'Pickup Moscow', 'warehouse_id' => 7, 'mission_ref' => 12]);
$hr->setMember($oleg, $tm['uuid'], $emp['uuid'], 'lead'); $hr->updateEmployee($oleg, $emp['uuid'], ['team_uuid' => $tm['uuid'], 'warehouse_id' => null]);
ok($hr->listTeams($oleg)[0]['members'][0]['role'] === 'lead' && $spy->gi[3]['warehouse'] === 7, 'teams: members, lead, the warehouse of the operator comes from the team');
ok(refused(fn() => $hr->saveTeam($mgrC, ['name' => 'x']), 'forbidden'), 'teams: need hr.teams.manage');

/* ---------- time off ---------- */
$ivanEmp = $hr->createEmployee($oleg, ['full_name' => 'Ivan Manager', 'email' => 'ivan@x.ru', 'hire_date' => '2025-01-10', 'user_id' => 4]); $mgrEmp = $e->emp($ivanEmp['uuid']);
$hr->updateEmployee($oleg, $emp['uuid'], ['manager_uuid' => $mgrEmp['uuid']]); $mgrC = $as(4, 'ivan'); $mC = $as(3, 'maria');
$st->update('employees', (int)$maria['id'], ['hire_date' => '2025-06-01']);
ok($lv->balance($mC, $emp['uuid'])['accrued'] === 39.7 && $lv->balance($mC, $emp['uuid'])['available'] === 39.7, 'leave: balance accrues per month of service');
$lr = $lv->request($mC, ['kind' => 'vacation', 'starts_on' => '2026-12-07', 'ends_on' => '2026-12-18']);
ok($lr['status'] === 'pending_manager' && (float)$lr['days'] === 10.0 && $lv->balance($mC, $emp['uuid'])['available'] === 29.7, 'leave: request goes to the manager first; working days only; balance reserved');
ok(refused(fn() => $lv->request($mC, ['kind' => 'sick', 'starts_on' => '2026-12-10', 'ends_on' => '2026-12-11']), 'overlap') && refused(fn() => $lv->request($mC, ['kind' => 'vacation', 'starts_on' => '2027-02-01', 'ends_on' => '2027-04-30']), 'insufficient_balance'), 'leave: overlap and balance checks');
ok(refused(fn() => $lv->decide($mC, $lr['uuid'], 'approve'), 'self_approval') && refused(fn() => $lv->decide($oleg, $lr['uuid'], 'approve'), 'forbidden'), 'leave: nobody approves themself; HR cannot skip the manager');
$step = $lv->decide($mgrC, $lr['uuid'], 'approve'); ok($step['status'] === 'pending_hr' && $step['manager_by'] === 'user:ivan', 'leave: manager approved, HR next');
ok(refused(fn() => $lv->decide($mgrC, $lr['uuid'], 'approve'), 'forbidden'), 'leave: the manager cannot do the HR step');
$fin = $lv->decide($oleg, $lr['uuid'], 'approve'); ok($fin['status'] === 'approved' && $st->find('status_history', ['employee_id' => (int)$maria['id'], 'kind' => 'vacation']) !== [], 'leave: HR approved, vacation in the status timeline');
$sick = $lv->request($mC, ['kind' => 'sick', 'starts_on' => '2026-11-18', 'ends_on' => '2026-11-18']); $lv->decide($mgrC, $sick['uuid'], 'approve'); $lv->decide($oleg, $sick['uuid'], 'approve');
$cal = $lv->calendar($mgrC, '2026-11'); ok($cal['entries'][0]['kind'] === 'absent', 'calendar: sick leave is shown as absent to non-HR');
$cal2 = $lv->calendar($oleg, '2026-12', $tm['uuid']); ok(count($cal2['entries']) === 1 && $cal2['entries'][0]['kind'] === 'vacation', 'calendar: team month view');
$rej = $lv->request($mC, ['kind' => 'unpaid', 'starts_on' => '2026-11-23', 'ends_on' => '2026-11-24']); ok($lv->decide($mgrC, $rej['uuid'], 'reject')['status'] === 'rejected', 'leave: reject');
ok($lv->cancel($mC, $rej['uuid'])['status'] === 'rejected', 'leave: cancelling a final request changes nothing');
ok(refused(fn() => $lv->request($mC, ['employee_uuid' => $mgrEmp['uuid'], 'kind' => 'vacation', 'starts_on' => '2026-12-01', 'ends_on' => '2026-12-02']), 'forbidden'), 'leave: you request only for yourself');
$s1 = $lv->saveSchedule($oleg, ['name' => 'Shift 2/2', 'week' => [1 => 12, 2 => 12, 3 => 0, 4 => 0, 5 => 12, 6 => 12, 7 => 0]]);
$wk = TimeOff::week($s1['week']); ok(TimeOff::span($wk, '2026-11-16', '2026-11-22')['hours'] === 48.0, 'schedule: template hours');
$sh = $lv->shift($oleg, 7, '2026-12-08'); ok(count($sh) === 1 && $sh[0]['away'] === true && $sh[0]['hours'] === 0.0, 'schedule: shift planning knows the approved leave');

/* ---------- payroll ---------- */
$c = Payroll::calc($profile, ['planned_days' => 22, 'unpaid_days' => 2, 'missions_completed' => 4, 'gi' => ['issues' => 100, 'receipts' => 20]]);
ok($c['lines'][0]['amount_kop'] === 8181818 && $c['gross_kop'] === 8181818 + 200000 + 150000 + 20000 && $c['net_kop'] === $c['gross_kop'] - $c['deductions_kop'], 'payroll: fixed prorated by unpaid days + mission and goods-issue bonuses');
ok($c['deductions_kop'] === Payroll::div($c['gross_kop'] * 1300, 10000), 'payroll: percent deduction of the gross');
$hc = Payroll::calc(['type' => 'hourly', 'base_kop' => 25000], ['hours' => 160.5]); ok($hc['gross_kop'] === 4012500, 'payroll: hourly');
$cc = Payroll::calc(['type' => 'commission', 'commission_bp' => 250], ['commission_base_kop' => 10000000]); ok($cc['gross_kop'] === 250000, 'payroll: commission percent');
$pt = Payroll::calc(['type' => 'per_task', 'mission_bonus_kop' => 70000], ['missions_completed' => 3]); ok($pt['gross_kop'] === 210000, 'payroll: per-task');
$spy->missions[3] = 6; $spy->kpi[3] = ['issues' => 40, 'receipts' => 10, 'returns' => 0, 'counts' => 0];
ok(refused(fn() => $lv->payroll($mgrC, '2026-11-01', '2026-11-30'), 'forbidden'), 'payroll: needs hr.payroll.run + hr.pay.view');
$run = $lv->payroll($accC, '2026-11-01', '2026-11-30', [], true);
ok(Payroll::validate($run) === [] && $run['schema'] === 'sysfera.payroll.export/1' && count($run['items']) === 1, 'payroll export: valid against the contract');
$it = $run['items'][0]; $codes = array_column($it['lines'], 'code');
ok(in_array('bonus_mission', $codes, true) && in_array('bonus_gi_issue', $codes, true) && $it['employee']['uuid'] === $emp['uuid'] && !str_contains(json_encode($run), 'passport'), 'payroll: missions and goods-issue KPI feed the lines; no personal data beyond name');
$again = $lv->payroll($accC, '2026-11-01', '2026-11-30', [], true); ok($again['export_id'] === $run['export_id'] && count($st->t['payroll_runs']) === 1, 'payroll export: idempotent (same period and numbers, same id)');
$spy->missions[3] = 7; $run2 = $lv->payroll($accC, '2026-11-01', '2026-11-30', [], true); ok($run2['export_id'] !== $run['export_id'], 'payroll export: new numbers, new document');
$broken = $run; $broken['items'][0]['totals']['net_kop']++; ok(Payroll::validate($broken) !== [], 'payroll export: a doctored total is detected');
$sample = json_decode((string)file_get_contents($dir . '/schemas/payroll-export.sample.json'), true); ok(is_array($sample) && Payroll::validate($sample) === [], 'payroll export: the shipped sample file passes the same checks');
$schema = json_decode((string)file_get_contents($dir . '/schemas/payroll-export.schema.json'), true); ok(is_array($schema) && ($schema['properties']['schema']['const'] ?? '') === Payroll::SCHEMA, 'payroll export: JSON Schema file matches the contract id');

/* ---------- onboarding alerts ---------- */
$spy->notes = []; $e2 = $e; $late = $hr->createEmployee($oleg, ['full_name' => 'Late Larry', 'email' => 'l@x.ru', 'hire_date' => '2026-11-01']);
$al = $hr->onboardingAlerts($oleg); ok(count(array_filter($al, fn($a) => $a['employee_uuid'] === $late['uuid'])) >= 3 && count($spy->notes) >= 3 && in_array(2, array_column($spy->notes, 0), true), 'onboarding: overdue items alert the HR users in Messenger');
$n = count($spy->notes); $hr->onboardingAlerts($oleg); ok(count($spy->notes) === $n, 'onboarding: each overdue item is announced once');
$t = $st->one('tasks', ['employee_id' => (int)$e->emp($late['uuid'])['id'], 'code' => 'role']); $hr->doneTask($oleg, $t['uuid']); ok((int)$st->one('tasks', ['id' => (int)$t['id']])['done'] === 1, 'onboarding: manual completion');

/* ---------- offboarding ---------- */
$hr->grant($oleg, 3, $roleOp['uuid']); ok(isset($spy->gi[3]), 'offboarding: precondition, operator has access');
$spy->blocked = [];
ok(refused(fn() => $hr->offboard($as(9, 'zz'), $emp['uuid']), 'forbidden') && refused(fn() => $hr->offboard($mC, $emp['uuid']), 'forbidden'), 'offboarding: needs hr.offboard');
$r = $hr->offboard($oleg, $emp['uuid'], ['last_day' => '2026-11-17', 'reason' => 'resigned']);
ok(!isset($spy->gi[3]) && $spy->blocked === [3] && $r['account_blocked'] && $rbac->permsOf(3) === [] && count($r['roles_revoked']) >= 1, 'offboarding: roles revoked, warehouse app access removed, account blocked');
$dm = $e->emp($emp['uuid']); ok($dm['status'] === 'dismissed' && $dm['archived_at'] !== null && $dm['dismissed_at'] === '2026-11-17', 'offboarding: profile archived');
ok(count(array_filter($st->find('team_members', ['employee_id' => (int)$dm['id']]), fn($m) => $m['role'] !== 'removed')) === 0, 'offboarding: team seats removed');
ok(count($r['checklist']) >= 4 && $st->find('status_history', ['employee_id' => (int)$dm['id'], 'kind' => 'dismissal']) !== [], 'offboarding: property-return checklist + dismissal in the timeline');
$r2 = $hr->offboard($oleg, $emp['uuid']); ok($r2['already'] === true && count($st->find('status_history', ['employee_id' => (int)$dm['id'], 'kind' => 'dismissal'])) === 1, 'offboarding: repeating is safe');
ok(refused(fn() => $hr->updateEmployee($oleg, $emp['uuid'], ['phone' => '1']), 'archived'), 'offboarding: an archived profile is read only');
$spy->gi[3] = ['role' => 'operator', 'perms' => '', 'warehouse' => 7];   // simulate a stale row that someone re-created
$o = $hr->orphans($oleg); ok(count($o) === 1 && $o[0]['user_id'] === 3 && $o[0]['why'] === 'dismissed', 'orphans: stale Goods issue access of a dismissed employee is detected');
$hr->revokeOrphans($oleg); ok(!isset($spy->gi[3]) && $hr->orphans($oleg) === [], 'orphans: and revoked');
$adm = $hr->createEmployee($oleg, ['full_name' => 'Root Rita', 'email' => 'rita@x.ru', 'hire_date' => '2020-01-01', 'user_id' => 1]); $spy->lastAdmin = true;
$rr = $hr->offboard($as(2, 'oleg'), $adm['uuid']); ok($rr['blocked_refused'] === true && $rr['account_blocked'] === false, 'offboarding: the last administrator account is never blocked');
ok(refused(fn() => $hr->offboard($as(2, 'oleg'), $e->emp($hr->createEmployee($oleg, ['full_name' => 'Oleg Self', 'email' => 'o@x.ru', 'hire_date' => '2020-01-01', 'user_id' => 2])['uuid'])['uuid']), 'self'), 'offboarding: you cannot dismiss yourself');

/* ---------- data protection ---------- */
ok(refused(fn() => $hr->exportEmployees($mgrC), 'forbidden'), 'export: restricted to hr.export');
$ex = $hr->exportEmployees($oleg, ['columns' => ['full_name', 'passport', 'email'], 'purpose' => 'audit']);
ok(str_starts_with($ex['content'], "full_name,email\n") && !str_contains($ex['content'], '4500') && count($st->audits('export.employees')) === 1, 'export: only whitelisted columns (no passport / pay), and the export is logged');
$st->update('employees', (int)$late['id'] ?: (int)$e->emp($late['uuid'])['id'], ['full_name' => '=HYPERLINK("x")']); $ex = $hr->exportEmployees($oleg); ok(str_contains($ex['content'], "\"'=HYPERLINK"), 'export: formula injection neutralised');
$hr->consent($mC, $emp['uuid'], 'personal_data', 'revoke'); ok(!$hr->hasConsent($e->emp($emp['uuid']), 'personal_data') && count($st->find('consents', ['employee_id' => (int)$maria['id']])) === 2, 'consent: revocation is a new row, history kept');
ok($hr->missingConsent($oleg) !== [], 'consent: employees without a current consent are listed');

/* ---------- audit chain ---------- */
$v = $hr->verifyAudit($oleg); ok($v['ok'] && $v['rows'] > 100, 'audit: the hash chain verifies (' . $v['rows'] . ' rows)');
try { $st->update('audit', 1, ['actor' => 'x']); $upd = true; } catch (RuntimeException) { $upd = false; } ok(!$upd, 'audit: the store has no update path');
$rows = $st->t['audit']; $rows[5]['data'] = '{"tampered":true}'; ok(Audit::verify($rows) === 5, 'audit: an edited row is found');
$rows = $st->t['audit']; unset($rows[7]); ok(Audit::verify(array_values($rows)) !== null, 'audit: a deleted row is found');
ok(refused(fn() => $hr->verifyAudit($mgrC), 'forbidden'), 'audit: needs hr.audit.view');
ok($hr->timeline($oleg, $emp['uuid']) !== [] && refused(fn() => $hr->timeline($mgrC, $emp['uuid']), 'forbidden'), 'timeline: status history for HR and the employee only');

require_once dirname(__DIR__) . '/core/app/Hooks.php'; require_once $dir . '/lib/DbBridges.php'; foreach (['Util', 'Hub'] as $mf) require_once dirname($dir) . "/messenger/lib/$mf.php";
/* ---------- Apply: a signed-in user answers a published vacancy; HR is told; counts for the dashboard ---------- */
$av = $rec->saveVacancy($oleg, ['role_uuid' => $roleOp['uuid'], 'title' => 'Night packer', 'description' => 'text']);
$user = new Ctx(5, 'anna', [], false, null); $anon = new Ctx(null, 'guest', []);
ok(refused(fn() => $rec->apply($user, $av['uuid'], ['full_name' => 'Anna', 'email' => 'anna@x.ru']), 'closed'), 'apply: a draft vacancy cannot be applied to');
$rec->approve($oleg, $av['uuid']); ok(refused(fn() => $rec->apply($user, $av['uuid'], ['full_name' => 'Anna', 'email' => 'anna@x.ru']), 'closed'), 'apply: an approved but not published vacancy cannot be applied to');
$rec->publish($oleg, $av['uuid']); $spy->notes = [];
ok(refused(fn() => $rec->apply($anon, $av['uuid'], ['full_name' => 'X', 'email' => 'x@x.ru']), 'forbidden'), 'apply: only a signed-in user');
ok(refused(fn() => $rec->apply($user, $av['uuid'], ['full_name' => 'Anna', 'email' => 'not-an-email']), 'bad_email') && refused(fn() => $rec->apply($user, $av['uuid'], ['full_name' => '', 'email' => 'anna@x.ru']), 'missing_field'), 'apply: a name and a valid e-mail are required');
$ap = $rec->apply($user, $av['uuid'], ['full_name' => 'Anna Ivanova', 'email' => 'Anna@X.ru', 'phone' => '+7 911']);
ok($ap['stage'] === 'new' && $ap['source'] === 'apply' && $ap['email'] === 'anna@x.ru' && !isset($ap['phone_enc']), 'apply: becomes a candidate in NEW, source "apply", e-mail normalised, phone not exposed');
ok($spy->notes !== [] && str_contains(json_encode($spy->notes), 'Anna Ivanova') && str_contains(json_encode($spy->notes), 'Night packer'), 'apply: the HR managers are notified');
ok(refused(fn() => $rec->apply($user, $av['uuid'], ['full_name' => 'Anna', 'email' => 'anna@x.ru']), 'duplicate'), 'apply: a second application with the same e-mail is refused');
ok($st->audits('candidate.applied') !== [], 'apply: written to the audit chain');
$hooked = []; \Sysfera\Hooks::on('hr.candidate.applied', function ($v, $cd) use (&$hooked) { $hooked[] = $cd['full_name']; });
$rec->apply(new Ctx(3, 'maria', [], false, null), $av['uuid'], ['full_name' => 'Maria P', 'email' => 'mp@x.ru']); ok($hooked === ['Maria P'], 'apply: hook hr.candidate.applied fires');
$list = $rec->openPublished('anna@x.ru'); $row = array_values(array_filter($list, fn($x) => $x['uuid'] === $av['uuid']))[0] ?? null;
ok($row && $row['applications'] === 2 && $row['new'] === 2 && $row['applied'] === true && !array_filter($rec->openPublished(''), fn($x) => $x['applied']), 'widget data: published vacancies with application counts, "applied" only for the asking e-mail');
$rec->closeVacancy($oleg, $av['uuid']); ok(!array_filter($rec->openPublished(), fn($x) => $x['uuid'] === $av['uuid']) && refused(fn() => $rec->apply($user, $av['uuid'], ['full_name' => 'Late', 'email' => 'late@x.ru']), 'closed'), 'apply: a closed vacancy leaves the list and takes no more applications');
ok($rec->getVacancy($oleg, $av['uuid'])['team_uuid'] === null, 'vacancy: the team uuid is part of the shape');

/* ---------- Teams mirror: HR tells the Teams app through hook hr.team.sync ---------- */
$seen = []; \Sysfera\Hooks::on('hr.team.sync', function (array $t) use (&$seen) { $seen[] = $t; });
$tm = $hr->saveTeam($oleg, ['name' => 'Night shift', 'descr' => 'd']); ok(count($seen) === 1 && $seen[0]['uuid'] === $tm['uuid'] && $seen[0]['name'] === 'Night shift' && $seen[0]['members'] === [], 'teams: saving a team fires hr.team.sync');
$e1 = $hr->createEmployee($oleg, ['full_name' => 'Lena Night', 'email' => 'lena@x.ru', 'hire_date' => '2026-01-10', 'user_id' => 5]); $e2 = $hr->createEmployee($oleg, ['full_name' => 'No Account', 'email' => 'na@x.ru', 'hire_date' => '2026-01-10']);
$hr->setMember($oleg, $tm['uuid'], $e1['uuid'], 'lead'); $hr->setMember($oleg, $tm['uuid'], $e2['uuid'], 'member'); $last = end($seen);
ok($last['members'] === [['user_id' => 5, 'role' => 'lead']] && $last['removed'] === [], 'teams: members are sent with their user account and role; people without an account are skipped');
$hr->setMember($oleg, $tm['uuid'], $e1['uuid'], 'member', true); $last = end($seen); ok($last['members'] === [] && $last['removed'] === [5], 'teams: a removed member is reported so the Teams app lets them go');
$words = fn(string $s, int $n, int $c) => SysferaHr\ActivityPoster::words($s, $n, $c);
ok($words('one two three', 20, 400) === 'one two three' && $words(implode(' ', range(1, 80)), 50, 1200) === implode(' ', range(1, 50)) . '…' && SysferaMsg\Text::words($words(implode(' ', range(1, 80)), 50, 1200)) === 50 && strlen($words(str_repeat('word ', 20), 50, 30)) <= 30, 'activity post: a long vacancy is cut to the 20 / 50 word limits instead of being refused');

echo $fail ? "\n$fail FAILED\n" : "\nAll good\n"; exit($fail ? 1 : 0);
