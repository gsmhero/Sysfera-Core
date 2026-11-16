<?php
declare(strict_types=1);
namespace SysferaHr;

/** Employees, documents, pay profiles, teams, RBAC administration, access sync, onboarding / offboarding, consents, exports, audit. Every method takes the acting Ctx and checks permissions itself. */
final class Hr
{
    public const DOC_KINDS = ['contract', 'nda', 'job_description', 'extra'];
    public const ONBOARDING = ['docs_signed', 'account', 'role', 'warehouse_access'];
    public const OFFBOARDING = ['property_laptop', 'property_badge', 'property_keys', 'property_goods_issue', 'docs_returned'];
    public const EXPORT_COLUMNS = ['number', 'full_name', 'email', 'phone', 'department', 'emp_type', 'hire_date', 'status'];

    public function __construct(private Env $e) {}

    /* =============================== employees =============================== */
    /** the card as the actor may see it: sealed columns never leave; passport only for HR (pii) and the employee; salary is a separate call */
    public function view(Ctx $c, array $emp): array
    {
        $o = $emp; unset($o['passport_enc']);
        $o['passport'] = null; $o['passport_set'] = ($emp['passport_enc'] ?? '') !== '';
        if ($o['passport_set'] && Fields::mayViewPii($c, $emp)) { $o['passport'] = $this->e->open((string)$emp['passport_enc']); $this->e->log($c, 'pii.view', 'employee', (string)$emp['uuid'], ['field' => 'passport']); }
        foreach (['position_role_id' => ['roles', 'position_role_uuid'], 'team_id' => ['teams', 'team_uuid'], 'manager_id' => ['employees', 'manager_uuid'], 'schedule_id' => ['schedules', 'schedule_uuid']] as $col => [$tbl, $name]) {
            $o[$name] = !empty($emp[$col]) ? ($this->e->s->one($tbl, ['id' => (int)$emp[$col]])['uuid'] ?? null) : null;
        }
        return $o;
    }
    public function getEmployee(Ctx $c, string $uuid): array
    {
        $emp = $this->e->emp($uuid);
        if (!$c->can('hr.employees.view') && !$c->isSelf($emp)) throw new Err('forbidden', 'permission required: hr.employees.view', 403);
        return $this->view($c, $emp);
    }
    public function listEmployees(Ctx $c, array $in = []): array
    {
        $c->need('hr.employees.view'); $w = [];
        if (!empty($in['status'])) $w['status'] = (string)$in['status'];
        if (!empty($in['team_uuid'])) $w['team_id'] = (int)$this->e->row('teams', (string)$in['team_uuid'])['id'];
        $q = strtolower(trim((string)($in['q'] ?? '')));
        $out = []; foreach ($this->e->s->find('employees', $w, 'id', 2000) as $r) { if ($q !== '' && !str_contains(strtolower($r['full_name'] . ' ' . $r['email'] . ' ' . $r['number']), $q)) continue; $out[] = $this->view($c, $r); }
        return $out;
    }

    /** map the *_uuid inputs to ids */
    private function relations(array $in): array
    {
        $o = [];
        foreach (['position_role_uuid' => ['roles', 'position_role_id'], 'team_uuid' => ['teams', 'team_id'], 'manager_uuid' => ['employees', 'manager_id'], 'schedule_uuid' => ['schedules', 'schedule_id']] as $k => [$tbl, $col]) {
            if (!array_key_exists($k, $in)) continue;
            $o[$col] = trim((string)$in[$k]) === '' ? null : (int)$this->e->row($tbl, (string)$in[$k])['id'];
        }
        return $o;
    }
    private function clean(array $in, ?array $emp): array
    {
        $o = $this->relations($in);
        foreach (['full_name' => 160, 'department' => 120, 'phone' => 40, 'avatar' => 300, 'number' => 20] as $k => $max) if (array_key_exists($k, $in)) $o[$k] = $this->e->str($in, $k, $max, $k === 'full_name');
        if (array_key_exists('email', $in)) $o['email'] = $this->e->email((string)$in['email'], true);
        if (array_key_exists('birth_date', $in)) $o['birth_date'] = $this->e->date($in, 'birth_date');
        if (array_key_exists('hire_date', $in)) $o['hire_date'] = $this->e->date($in, 'hire_date', true);
        if (array_key_exists('emp_type', $in)) { $t = (string)$in['emp_type']; if (!in_array($t, Fields::EMP_TYPES, true)) throw new Err('bad_emp_type', 'emp_type: ' . implode('|', Fields::EMP_TYPES), 422); $o['emp_type'] = $t; }
        if (array_key_exists('warehouse_id', $in)) $o['warehouse_id'] = ($in['warehouse_id'] === '' || $in['warehouse_id'] === null) ? null : max(1, (int)$in['warehouse_id']);
        if (array_key_exists('user_id', $in)) $o['user_id'] = ($in['user_id'] === '' || $in['user_id'] === null) ? null : (int)$in['user_id'];
        if (array_key_exists('passport', $in)) $o['passport'] = $this->e->str($in, 'passport', 60);
        return $o;
    }

    public function createEmployee(Ctx $c, array $in, ?array $fromCandidate = null): array
    {
        $c->need('hr.employees.edit');
        $uuid = $this->e->uuidOrNew($in);
        if ($ex = $this->e->s->one('employees', ['uuid' => $uuid])) return $this->view($c, $ex);   // idempotent
        $f = $this->clean($in, null);
        foreach (['full_name', 'email', 'hire_date'] as $k) if (empty($f[$k])) throw new Err('missing_field', "$k is required", 422);
        if ($this->e->s->one('employees', ['email' => $f['email']])) throw new Err('duplicate', 'an employee with this e-mail exists', 409);
        if (!empty($f['user_id']) && ($this->e->s->one('employees', ['user_id' => $f['user_id']]) || !$this->e->b->id->exists($f['user_id']))) throw new Err('bad_user', 'user does not exist or is already linked', 409);
        $passport = $f['passport'] ?? ''; unset($f['passport']);
        $row = $f + ['uuid' => $uuid, 'number' => '', 'user_id' => null, 'position_role_id' => null, 'team_id' => null, 'manager_id' => null, 'warehouse_id' => null, 'schedule_id' => null, 'birth_date' => null, 'department' => '', 'phone' => '', 'avatar' => '', 'dismissed_at' => null, 'archived_at' => null, 'emp_type' => 'full_time', 'status' => 'active', 'passport_enc' => '', 'created_at' => $this->e->now(), 'source_candidate_id' => $fromCandidate['id'] ?? null];
        $emp = $this->e->s->atomic(function () use ($c, $row, $passport, $uuid) {
            $id = $this->e->s->insert('employees', $row);
            $this->e->s->update('employees', $id, ['number' => $row['number'] !== '' ? $row['number'] : 'E' . str_pad((string)$id, 5, '0', STR_PAD_LEFT)]);
            $emp = $this->e->s->one('employees', ['id' => $id]);
            $this->e->log($c, 'employee.created', 'employee', $uuid, ['number' => $emp['number'], 'full_name' => $emp['full_name']]);
            $this->e->history($c, $emp, 'hire', ['hire_date' => $emp['hire_date'], 'position_role_id' => $emp['position_role_id'] ?? null, 'team_id' => $emp['team_id'] ?? null]);
            if ($passport !== '') $this->setPassport($c, $emp, $passport);
            $this->createTasks($c, $emp, 'onboarding', self::ONBOARDING, date('Y-m-d', strtotime($emp['hire_date'] . ' +' . (int)$this->e->cfg['onboarding_days'] . ' days')));
            return $this->e->s->one('employees', ['id' => $id]);
        });
        $this->refreshOnboarding($c, $emp);
        return $this->view($c, $emp);
    }

    private function setPassport(Ctx $c, array $emp, string $value): void
    {
        if (!$this->e->cfg['collect_passport']) throw new Err('not_collected', 'passport data is not collected (data minimisation: enable it in the settings)', 409);
        if (!$this->hasConsent($emp, 'personal_data')) throw new Err('consent_required', 'the employee has not given the personal data consent', 409);
        $this->e->s->update('employees', (int)$emp['id'], ['passport_enc' => $value === '' ? '' : $this->e->seal($value)]);
    }

    /** change fields; field-level permissions apply; every real change is logged with old and new value (sealed fields: only a keyed fingerprint) */
    public function updateEmployee(Ctx $c, string $uuid, array $in): array
    {
        $emp = $this->e->emp($uuid); unset($in['uuid']);
        $f = $this->clean($in, $emp);
        if ($bad = Fields::denied($c, $emp, $f + array_diff_key($in, $f, array_flip(['position_role_uuid', 'team_uuid', 'manager_uuid', 'schedule_uuid'])))) throw new Err('forbidden_field', 'you may not change: ' . implode(', ', $bad), 403, ['fields' => $bad]);
        if (isset($f['email']) && ($o = $this->e->s->one('employees', ['email' => $f['email']])) && (int)$o['id'] !== (int)$emp['id']) throw new Err('duplicate', 'an employee with this e-mail exists', 409);
        if (isset($f['manager_id']) && (int)$f['manager_id'] === (int)$emp['id']) throw new Err('bad_manager', 'an employee cannot manage themself', 422);
        if (!empty($f['user_id']) && (!$this->e->b->id->exists((int)$f['user_id']) || (($o = $this->e->s->one('employees', ['user_id' => (int)$f['user_id']])) && (int)$o['id'] !== (int)$emp['id']))) throw new Err('bad_user', 'user does not exist or is already linked', 409);
        if ($emp['status'] === 'dismissed') throw new Err('archived', 'the profile is archived', 409);
        $changed = [];
        $this->e->s->atomic(function () use ($c, $emp, $f, &$changed) {
            if (array_key_exists('passport', $f)) {
                $old = (string)$emp['passport_enc'] !== '' ? $this->e->open((string)$emp['passport_enc']) : '';
                if ($old !== $f['passport']) { $this->setPassport($c, $emp, $f['passport']); $this->e->log($c, 'employee.field', 'employee', (string)$emp['uuid'], ['field' => 'passport', 'old' => $this->fp($old), 'new' => $this->fp($f['passport'])]); $changed[] = 'passport'; }
                unset($f['passport']);
            }
            $upd = [];
            foreach ($f as $k => $v) {
                if ((string)($emp[$k] ?? '') === (string)($v ?? '')) continue;
                $upd[$k] = $v; $changed[] = $k;
                $this->e->log($c, 'employee.field', 'employee', (string)$emp['uuid'], ['field' => $k, 'old' => $emp[$k] ?? null, 'new' => $v]);
            }
            if ($upd) $this->e->s->update('employees', (int)$emp['id'], $upd);
            $fresh = $this->e->s->one('employees', ['id' => (int)$emp['id']]);
            if (isset($upd['position_role_id'])) $this->e->history($c, $fresh, 'role_change', ['from' => $emp['position_role_id'], 'to' => $upd['position_role_id']]);
            foreach (['team_id', 'manager_id', 'department', 'warehouse_id'] as $k) if (array_key_exists($k, $upd)) { $this->e->history($c, $fresh, 'transfer', ['field' => $k, 'from' => $emp[$k] ?? null, 'to' => $upd[$k]]); break; }
        });
        $fresh = $this->e->s->one('employees', ['id' => (int)$emp['id']]);
        if (array_intersect($changed, ['user_id', 'warehouse_id', 'team_id']) && $fresh['user_id']) $this->syncAccess($c, (int)$fresh['user_id']);
        return $this->view($c, $fresh) + ['changed' => $changed];
    }
    private function fp(?string $v): string { return $v === null || $v === '' ? '' : 'sealed:' . substr(hash_hmac('sha256', $v, $this->e->key), 0, 12); }

    /* =============================== documents and agreements =============================== */
    public function listDocs(Ctx $c, string $empUuid): array
    {
        $emp = $this->e->emp($empUuid); if (!$c->can('hr.docs.manage') && !$c->isSelf($emp)) throw new Err('forbidden', 'permission required: hr.docs.manage', 403);
        $today = $this->e->today();
        return array_map(function ($d) use ($today) { unset($d['scan_key']); $d['effective_status'] = $d['status'] === 'signed' && !empty($d['expires_at']) && $d['expires_at'] < $today ? 'expired' : $d['status']; return $d; }, $this->e->s->find('docs', ['employee_id' => (int)$emp['id']], 'id DESC'));
    }
    /** a new document or (doc_key given) a new VERSION of an existing one; the doc_uuid makes a retry idempotent */
    public function addDoc(Ctx $c, string $empUuid, array $in): array
    {
        $c->need('hr.docs.manage'); $emp = $this->e->emp($empUuid); $uuid = $this->e->uuidOrNew($in, 'uuid');
        if ($ex = $this->e->s->one('docs', ['uuid' => $uuid])) return $ex;
        $kind = (string)($in['kind'] ?? ''); if (!in_array($kind, self::DOC_KINDS, true)) throw new Err('bad_kind', 'kind: ' . implode('|', self::DOC_KINDS), 422);
        $body = $this->e->str($in, 'body', 60000, true); $key = $this->e->uuidOrNew($in, 'doc_key'); $ver = 1;
        $prev = $this->e->s->find('docs', ['employee_id' => (int)$emp['id'], 'doc_key' => $key], 'version DESC', 1);
        if ($prev) { if ($prev[0]['kind'] !== $kind) throw new Err('bad_kind', 'a version keeps the kind of its document', 422); $ver = (int)$prev[0]['version'] + 1; }
        $row = ['uuid' => $uuid, 'employee_id' => (int)$emp['id'], 'doc_key' => $key, 'kind' => $kind, 'title' => $this->e->str($in, 'title', 160, true), 'version' => $ver, 'body' => $body, 'body_sha256' => hash('sha256', $body),
            'status' => 'unsigned', 'expires_at' => $this->e->date($in, 'expires_at'), 'signed_at' => null, 'sign_method' => null, 'scan_key' => null, 'scan_sha256' => null, 'superseded' => 0, 'created_at' => $this->e->now(), 'created_by' => $c->actor()];
        $id = $this->e->s->atomic(function () use ($c, $row, $prev, $emp) {
            $id = $this->e->s->insert('docs', $row);
            foreach ($prev as $p) $this->e->s->update('docs', (int)$p['id'], ['superseded' => 1]);
            $this->e->log($c, 'doc.added', 'employee', (string)$emp['uuid'], ['doc' => $row['uuid'], 'kind' => $row['kind'], 'version' => $row['version'], 'sha256' => $row['body_sha256']]);
            return $id;
        });
        $this->refreshOnboarding($c, $emp);
        return $this->e->s->one('docs', ['id' => $id]);
    }
    /** sign: method esign (the employee confirms in their own session) or scan (HR uploads the signed scan: base64 content, kept encrypted) */
    public function signDoc(Ctx $c, string $docUuid, array $in): array
    {
        $d = $this->e->row('docs', $docUuid); $emp = $this->e->s->one('employees', ['id' => (int)$d['employee_id']]);
        if ($d['status'] === 'signed') return $d;   // idempotent
        if ((int)$d['superseded'] === 1) throw new Err('superseded', 'a newer version exists', 409);
        $method = (string)($in['method'] ?? 'esign'); $upd = ['status' => 'signed', 'signed_at' => $this->e->now(), 'sign_method' => $method];
        if ($method === 'esign') {
            if (!$c->isSelf($emp)) throw new Err('forbidden', 'only the employee confirms an electronic signature', 403);
            if (empty($in['confirm']) || (string)($in['body_sha256'] ?? $d['body_sha256']) !== $d['body_sha256']) throw new Err('not_confirmed', 'confirm=true and the hash of the text that was shown are required', 422);
        } elseif ($method === 'scan') {
            $c->need('hr.docs.manage'); $bytes = base64_decode((string)($in['scan_base64'] ?? ''), true);
            if ($bytes === false || $bytes === '' || strlen($bytes) > 8_000_000) throw new Err('bad_scan', 'scan_base64: base64 content up to 8 MB', 422);
            $key = 'doc-' . $d['uuid']; $this->e->b->files->put($key, $this->e->seal($bytes)); $upd += ['scan_key' => $key, 'scan_sha256' => hash('sha256', $bytes)];
        } else throw new Err('bad_method', 'method: esign|scan', 422);
        $this->e->s->update('docs', (int)$d['id'], $upd);
        $this->e->log($c, 'doc.signed', 'employee', (string)$emp['uuid'], ['doc' => $d['uuid'], 'version' => $d['version'], 'method' => $method, 'sha256' => $d['body_sha256']]);
        $this->refreshOnboarding($c, $emp);
        return $this->e->s->one('docs', ['id' => (int)$d['id']]);
    }
    public function expireDoc(Ctx $c, string $docUuid): array
    {
        $c->need('hr.docs.manage'); $d = $this->e->row('docs', $docUuid);
        if ($d['status'] !== 'expired') { $this->e->s->update('docs', (int)$d['id'], ['status' => 'expired']); $this->e->log($c, 'doc.expired', 'employee', (string)$this->e->s->one('employees', ['id' => (int)$d['employee_id']])['uuid'], ['doc' => $d['uuid']]); }
        return $this->e->s->one('docs', ['id' => (int)$d['id']]);
    }
    public function scan(Ctx $c, string $docUuid): ?string
    {
        $c->need('hr.docs.manage'); $d = $this->e->row('docs', $docUuid); if (empty($d['scan_key'])) return null;
        $raw = $this->e->b->files->get((string)$d['scan_key']); $this->e->log($c, 'pii.view', 'employee', (string)$this->e->s->one('employees', ['id' => (int)$d['employee_id']])['uuid'], ['field' => 'scan', 'doc' => $d['uuid']]);
        return $raw === null ? null : $this->e->open($raw);
    }

    /* =============================== pay profile (salary: HR + Accounting + the employee) =============================== */
    public function getPay(Ctx $c, string $empUuid): array
    {
        $emp = $this->e->emp($empUuid); if (!Fields::mayViewPay($c, $emp)) throw new Err('forbidden', 'permission required: hr.pay.view', 403);
        $r = $this->e->s->one('pay', ['employee_id' => (int)$emp['id']]); if (!$r) return ['employee_uuid' => $emp['uuid'], 'profile' => null];
        $this->e->log($c, 'pii.view', 'employee', (string)$emp['uuid'], ['field' => 'pay_profile']);
        return ['employee_uuid' => $emp['uuid'], 'profile' => json_decode((string)$this->e->open((string)$r['profile_enc']), true), 'updated_at' => $r['updated_at']];
    }
    public function setPay(Ctx $c, string $empUuid, array $in): array
    {
        $c->need('hr.pay.manage'); $emp = $this->e->emp($empUuid); $p = Payroll::normalize($in['profile'] ?? $in); $json = json_encode($p);
        $old = $this->e->s->one('pay', ['employee_id' => (int)$emp['id']]); $oldJson = $old ? (string)$this->e->open((string)$old['profile_enc']) : '';
        if ($oldJson === $json) return ['employee_uuid' => $emp['uuid'], 'profile' => $p, 'changed' => false];
        $row = ['profile_enc' => $this->e->seal($json), 'updated_at' => $this->e->now(), 'updated_by' => $c->actor()];
        if ($old) $this->e->s->update('pay', (int)$old['id'], $row); else $this->e->s->insert('pay', $row + ['employee_id' => (int)$emp['id']]);
        $this->e->log($c, 'employee.field', 'employee', (string)$emp['uuid'], ['field' => 'pay_profile', 'old' => $this->fp($oldJson), 'new' => $this->fp($json)]);
        return ['employee_uuid' => $emp['uuid'], 'profile' => $p, 'changed' => true];
    }

    /* =============================== teams =============================== */
    public function listTeams(Ctx $c): array
    {
        $c->need('hr.employees.view');
        return array_map(function ($t) { $t['members'] = array_map(fn($m) => ['employee_uuid' => $this->e->s->one('employees', ['id' => (int)$m['employee_id']])['uuid'] ?? null, 'role' => $m['role']], $this->e->s->find('team_members', ['team_id' => (int)$t['id']])); return $t; }, $this->e->s->find('teams', [], 'name'));
    }
    /** a team with its links: mission_ref (a Missions mission id: its completions feed the bonus) and warehouse_id (Goods issue pickup point of the team's operators) */
    public function saveTeam(Ctx $c, array $in): array
    {
        $c->need('hr.teams.manage'); $uuid = $this->e->uuidOrNew($in); $ex = $this->e->s->one('teams', ['uuid' => $uuid]);
        $row = ['name' => $this->e->str($in, 'name', 120, true), 'descr' => $this->e->str($in, 'descr', 500), 'warehouse_id' => (isset($in['warehouse_id']) && $in['warehouse_id'] !== '') ? max(1, (int)$in['warehouse_id']) : null, 'mission_ref' => (isset($in['mission_ref']) && $in['mission_ref'] !== '') ? max(1, (int)$in['mission_ref']) : null, 'active' => array_key_exists('active', $in) ? (int)(bool)$in['active'] : 1];
        if ($ex) { $this->e->s->update('teams', (int)$ex['id'], $row); $this->e->log($c, 'team.updated', 'team', $uuid, $row); }
        else { $this->e->s->insert('teams', $row + ['uuid' => $uuid, 'created_at' => $this->e->now()]); $this->e->log($c, 'team.created', 'team', $uuid, $row); }
        $t = $this->e->s->one('teams', ['uuid' => $uuid]);
        if ($ex && (($ex['warehouse_id'] ?? null) != $row['warehouse_id'])) foreach ($this->e->s->find('employees', ['team_id' => (int)$t['id']]) as $m) if ($m['user_id']) $this->syncAccess($c, (int)$m['user_id']);
        return $t;
    }
    public function setMember(Ctx $c, string $teamUuid, string $empUuid, string $role = 'member', bool $remove = false): array
    {
        $c->need('hr.teams.manage'); $t = $this->e->row('teams', $teamUuid); $emp = $this->e->emp($empUuid);
        if (!in_array($role, ['member', 'lead'], true)) throw new Err('bad_role', 'role: member|lead', 422);
        $m = $this->e->s->one('team_members', ['team_id' => (int)$t['id'], 'employee_id' => (int)$emp['id']]);
        if ($remove) { if ($m) { $this->e->s->update('team_members', (int)$m['id'], ['role' => 'removed']); } $this->e->log($c, 'team.member_removed', 'team', (string)$t['uuid'], ['employee' => $emp['uuid']]); }
        else { if ($m) $this->e->s->update('team_members', (int)$m['id'], ['role' => $role]); else $this->e->s->insert('team_members', ['team_id' => (int)$t['id'], 'employee_id' => (int)$emp['id'], 'role' => $role, 'since' => $this->e->now()]); $this->e->log($c, 'team.member_set', 'team', (string)$t['uuid'], ['employee' => $emp['uuid'], 'role' => $role]); }
        return $this->listTeams($c);
    }

    /* =============================== roles, grants and the warehouse projection =============================== */
    public function listRoles(Ctx $c): array { $c->need('hr.employees.view'); return array_map(fn($r) => $r + ['perm_list' => $this->e->rbac->rolePerms($r)], $this->e->s->find('roles', [], 'name')); }

    /** role catalog entry (job title / access bundle): code, names, description and permissions */
    public function saveRole(Ctx $c, array $in): array
    {
        $c->need('hr.roles.manage'); $uuid = $this->e->uuidOrNew($in); $ex = $this->e->s->one('roles', ['uuid' => $uuid]);
        $row = ['name' => $this->e->str($in, 'name', 120, !$ex), 'name_ru' => $this->e->str($in, 'name_ru', 120), 'descr' => $this->e->str($in, 'descr', 1000)];
        if ($ex) foreach (['name', 'name_ru', 'descr'] as $k) if (!array_key_exists($k, $in)) unset($row[$k]);
        $perms = null;
        if (array_key_exists('perms', $in)) {
            $perms = array_values(array_unique(array_map('strval', (array)$in['perms'])));
            foreach ($perms as $p) if (!Rbac::valid($p)) throw new Err('bad_permission', "unknown permission $p", 422);
            $old = $ex ? $this->e->rbac->rolePerms($ex) : []; $added = array_values(array_diff($perms, $old));
            if ($added) { $c->need('hr.rbac.manage'); $this->noEscalation($c, $added); }
            if ($ex && $perms === $old) $perms = null; else $row['perms'] = json_encode($perms);
        }
        if ($ex) {
            if ((int)$ex['builtin'] === 1) unset($row['name']);
            $this->e->s->update('roles', (int)$ex['id'], $row);
            $this->e->log($c, 'role.updated', 'role', $uuid, ['name' => $row['name'] ?? $ex['name']]);
            if ($perms !== null) { $old = $this->e->rbac->rolePerms($ex); $this->e->log($c, 'rbac.role_perms', 'role', $uuid, ['code' => $ex['code'], 'added' => array_values(array_diff($perms, $old)), 'removed' => array_values(array_diff($old, $perms))]); $this->resyncRole((int)$ex['id'], $c); }
        } else {
            $code = strtolower(trim((string)($in['code'] ?? ''))); if (!preg_match('/^[a-z][a-z0-9_]{1,39}$/', $code)) throw new Err('bad_code', 'code: 2-40 chars a-z 0-9 _', 422);
            if ($this->e->s->one('roles', ['code' => $code])) throw new Err('duplicate', 'role code exists', 409);
            $row += ['uuid' => $uuid, 'code' => $code, 'perms' => json_encode($perms ?? []), 'builtin' => 0, 'active' => 1, 'created_at' => $this->e->now()];
            $this->e->s->insert('roles', $row); $this->e->log($c, 'role.created', 'role', $uuid, ['code' => $code]);
            if ($perms) $this->e->log($c, 'rbac.role_perms', 'role', $uuid, ['code' => $code, 'added' => $perms, 'removed' => []]);
        }
        return $this->e->s->one('roles', ['uuid' => $uuid]);
    }
    public function setRoleActive(Ctx $c, string $uuid, bool $active): array
    {
        $c->need('hr.roles.manage'); $r = $this->e->row('roles', $uuid);
        if ((int)$r['active'] !== (int)$active) { $this->e->s->update('roles', (int)$r['id'], ['active' => (int)$active]); $this->e->log($c, $active ? 'role.activated' : 'role.deactivated', 'role', $uuid, ['code' => $r['code']]); $this->resyncRole((int)$r['id'], $c); }
        return $this->e->s->one('roles', ['id' => (int)$r['id']]);
    }
    /** least privilege: the HR administration rights (hr.* and *) can only be handed on by someone who holds them; warehouse rights are the RBAC manager's to give */
    private function noEscalation(Ctx $c, array $perms): void
    {
        foreach ($perms as $p) if ($p === '*' || str_starts_with($p, 'hr.')) if (!$c->can($p)) throw new Err('escalation', "you cannot grant a permission you do not hold: $p", 403);
    }
    private function resyncRole(int $roleId, Ctx $c): void { foreach ($this->e->s->find('user_roles', ['role_id' => $roleId]) as $ur) $this->syncAccess($c, (int)$ur['user_id']); }

    /** roles -> users (several roles per user). A role can only be granted by someone who holds all of its permissions (or a root administrator). */
    public function grant(Ctx $c, int $uid, string $roleUuid): array
    {
        $c->need('hr.rbac.manage'); $r = $this->e->row('roles', $roleUuid);
        if (!$this->e->b->id->exists($uid)) throw new Err('bad_user', 'no such user', 404);
        if (!(int)$r['active']) throw new Err('inactive', 'the role is deactivated', 409);
        $this->noEscalation($c, $this->e->rbac->rolePerms($r));
        if (!$this->e->s->one('user_roles', ['user_id' => $uid, 'role_id' => (int)$r['id']])) {
            $this->e->s->insert('user_roles', ['user_id' => $uid, 'role_id' => (int)$r['id'], 'granted_by' => $c->actor(), 'granted_at' => $this->e->now()]);
            $this->e->log($c, 'rbac.grant', 'user', (string)$uid, ['role' => $r['code']]);
            if ($emp = $this->e->empOfUser($uid)) $this->e->history($c, $emp, 'role_change', ['granted' => $r['code']]);
        }
        $this->syncAccess($c, $uid); $this->refreshOnboardingOfUser($c, $uid);
        return $this->userAccess($c, $uid, true);
    }
    public function revoke(Ctx $c, int $uid, string $roleUuid): array
    {
        $c->need('hr.rbac.manage'); $r = $this->e->row('roles', $roleUuid);
        $had = false; foreach ($this->e->s->find('user_roles', ['user_id' => $uid, 'role_id' => (int)$r['id']]) as $ur) { $this->e->s->delete('user_roles', (int)$ur['id']); $had = true; }
        if ($had) { $this->e->log($c, 'rbac.revoke', 'user', (string)$uid, ['role' => $r['code']]); if ($emp = $this->e->empOfUser($uid)) $this->e->history($c, $emp, 'role_change', ['revoked' => $r['code']]); }
        $this->syncAccess($c, $uid);
        return $this->userAccess($c, $uid, true);
    }
    /** the effective picture of one user: roles, permissions and what the warehouse app will allow */
    public function userAccess(Ctx $c, int $uid, bool $internal = false): array
    {
        if (!$internal && $uid !== $c->uid) $c->need('hr.rbac.manage');
        $perms = $this->e->rbac->permsOf($uid);
        return ['user_id' => $uid, 'roles' => array_map(fn($r) => $r['code'], $this->e->rbac->rolesOf($uid)), 'permissions' => $perms, 'warehouse' => Rbac::warehouse($perms)];
    }
    public function check(Ctx $c, int $uid, string $perm): array { $c->need('hr.rbac.manage'); return ['user_id' => $uid, 'permission' => $perm, 'allowed' => $this->e->rbac->can($uid, $perm)]; }

    /** make the Goods issue staff row follow the RBAC (warehouse_operator role -> operator with perms). A dismissed employee gets nothing. */
    public function syncAccess(Ctx $c, int $uid): string
    {
        $emp = $this->e->empOfUser($uid); $proj = Rbac::warehouse($this->e->rbac->permsOf($uid));
        if ($emp && $emp['status'] === 'dismissed') $proj = null;
        $wh = $emp ? ($emp['warehouse_id'] ?: ($emp['team_id'] ? ($this->e->s->one('teams', ['id' => (int)$emp['team_id']])['warehouse_id'] ?? null) : null)) : null;
        $res = $this->e->b->wh->sync($uid, $proj, $wh ? (int)$wh : null);
        $this->e->log($c, 'access.warehouse', 'user', (string)$uid, ['result' => $res, 'projection' => $proj, 'warehouse_id' => $wh]);
        return $res;
    }
    /** access rows in Goods issue that no active employee with a matching role stands behind */
    public function orphans(Ctx $c): array
    {
        $c->need('hr.rbac.manage'); $out = [];
        foreach ($this->e->b->wh->staffUserIds() as $uid) {
            $emp = $this->e->empOfUser((int)$uid); $proj = Rbac::warehouse($this->e->rbac->permsOf((int)$uid));
            if ($emp === null || $emp['status'] === 'dismissed' || $proj === null) $out[] = ['user_id' => (int)$uid, 'employee' => $emp['uuid'] ?? null, 'why' => $emp === null ? 'no_employee' : ($emp['status'] === 'dismissed' ? 'dismissed' : 'no_permission')];
        }
        return $out;
    }
    public function revokeOrphans(Ctx $c): array
    {
        $c->need('hr.rbac.manage'); $n = 0; foreach ($this->orphans($c) as $o) { $n += $this->e->b->wh->revoke($o['user_id']); $this->e->log($c, 'access.revoked', 'user', (string)$o['user_id'], ['why' => $o['why']]); }
        return ['revoked' => $n];
    }

    /* =============================== onboarding / offboarding checklists =============================== */
    private function createTasks(Ctx $c, array $emp, string $phase, array $codes, string $due): void
    {
        foreach ($codes as $code) {
            if ($this->e->s->one('tasks', ['employee_id' => (int)$emp['id'], 'phase' => $phase, 'code' => $code])) continue;
            $this->e->s->insert('tasks', ['uuid' => $this->e->uuid(), 'employee_id' => (int)$emp['id'], 'phase' => $phase, 'code' => $code, 'done' => 0, 'done_at' => null, 'done_by' => '', 'due_at' => $due, 'notified_at' => null, 'note' => '']);
        }
    }
    public function listTasks(Ctx $c, string $empUuid, string $phase = 'onboarding'): array
    {
        $emp = $this->e->emp($empUuid); if (!$c->can('hr.onboarding.manage') && !$c->isSelf($emp)) throw new Err('forbidden', 'permission required: hr.onboarding.manage', 403);
        $this->refreshOnboarding($c, $emp); return $this->e->s->find('tasks', ['employee_id' => (int)$emp['id'], 'phase' => $phase], 'id');
    }
    public function addTask(Ctx $c, string $empUuid, string $phase, string $code, string $note = ''): array
    {
        $c->need('hr.onboarding.manage'); $emp = $this->e->emp($empUuid); if (!in_array($phase, ['onboarding', 'offboarding'], true)) throw new Err('bad_phase', 'phase', 422);
        $code = substr(preg_replace('/[^a-z0-9_]/', '', strtolower($code)), 0, 40); if ($code === '') throw new Err('bad_code', 'code', 422);
        $this->createTasks($c, $emp, $phase, [$code], $this->e->today()); $t = $this->e->s->one('tasks', ['employee_id' => (int)$emp['id'], 'phase' => $phase, 'code' => $code]); if ($note !== '') $this->e->s->update('tasks', (int)$t['id'], ['note' => substr($note, 0, 300)]);
        return $this->e->s->one('tasks', ['id' => (int)$t['id']]);
    }
    public function doneTask(Ctx $c, string $taskUuid, bool $done = true): array
    {
        $c->need('hr.onboarding.manage'); $t = $this->e->row('tasks', $taskUuid);
        $this->e->s->update('tasks', (int)$t['id'], ['done' => (int)$done, 'done_at' => $done ? $this->e->now() : null, 'done_by' => $done ? $c->actor() : '']);
        $emp = $this->e->s->one('employees', ['id' => (int)$t['employee_id']]); $this->e->log($c, $done ? 'task.done' : 'task.reopened', 'employee', (string)$emp['uuid'], ['phase' => $t['phase'], 'code' => $t['code']]);
        return $this->e->s->one('tasks', ['id' => (int)$t['id']]);
    }
    /** automatic items complete themselves when their condition is true (documents signed, account linked, role given, warehouse access synced) */
    public function refreshOnboarding(Ctx $c, array $emp): void
    {
        $emp = $this->e->s->one('employees', ['id' => (int)$emp['id']]) ?? $emp;
        $docs = array_filter($this->e->s->find('docs', ['employee_id' => (int)$emp['id'], 'superseded' => 0]), fn($d) => in_array($d['kind'], ['contract', 'nda'], true));
        $signed = $docs && count(array_filter($docs, fn($d) => $d['status'] !== 'signed')) === 0;
        $uid = (int)($emp['user_id'] ?? 0); $perms = $uid ? $this->e->rbac->permsOf($uid) : [];
        $hasRole = $uid && count($this->e->rbac->rolesOf($uid)) > 0;
        $wh = $uid ? Rbac::warehouse($perms) : null;
        $cond = ['docs_signed' => $signed, 'account' => $uid > 0, 'role' => $hasRole, 'warehouse_access' => $wh === null || (!empty($emp['warehouse_id']) || !empty($emp['team_id']))];
        foreach ($this->e->s->find('tasks', ['employee_id' => (int)$emp['id'], 'phase' => 'onboarding']) as $t) {
            if (!isset($cond[$t['code']])) continue;
            if ($cond[$t['code']] && !(int)$t['done']) $this->e->s->update('tasks', (int)$t['id'], ['done' => 1, 'done_at' => $this->e->now(), 'done_by' => 'system']);
            elseif (!$cond[$t['code']] && (int)$t['done'] && $t['done_by'] === 'system') $this->e->s->update('tasks', (int)$t['id'], ['done' => 0, 'done_at' => null, 'done_by' => '']);
        }
    }
    private function refreshOnboardingOfUser(Ctx $c, int $uid): void { if ($emp = $this->e->empOfUser($uid)) $this->refreshOnboarding($c, $emp); }

    /** uncompleted items past their due date; each one notifies HR once (the notified_at stamp) */
    public function onboardingAlerts(Ctx $c, bool $notify = true): array
    {
        $c->need('hr.onboarding.manage'); $out = []; $today = $this->e->today();
        foreach ($this->e->s->find('employees', ['status' => 'active']) as $emp) {
            $this->refreshOnboarding($c, $emp);
            foreach ($this->e->s->find('tasks', ['employee_id' => (int)$emp['id'], 'phase' => 'onboarding', 'done' => 0]) as $t) {
                if ($t['due_at'] >= $today) continue;
                $out[] = ['task' => $t['uuid'], 'code' => $t['code'], 'due_at' => $t['due_at'], 'employee_uuid' => $emp['uuid'], 'employee' => $emp['full_name']];
                if ($notify && empty($t['notified_at'])) { $this->e->s->update('tasks', (int)$t['id'], ['notified_at' => $this->e->now()]); $this->e->notifyPerm('hr.onboarding.manage', 'HR onboarding overdue: ' . $emp['full_name'] . ' - ' . $t['code'] . ' (' . $t['due_at'] . ')'); }
            }
        }
        return $out;
    }

    /** One action: roles revoked, access revoked (warehouse app, sessions), account blocked, profile archived, team seats removed, property-return checklist created. Safe to repeat. */
    public function offboard(Ctx $c, string $empUuid, array $in = []): array
    {
        $c->need('hr.offboard'); $emp = $this->e->emp($empUuid); $last = $this->e->date($in, 'last_day') ?? $this->e->today(); $reason = $this->e->str($in, 'reason', 300);
        $uid = (int)($emp['user_id'] ?? 0); $report = ['employee' => $emp['uuid'], 'already' => $emp['status'] === 'dismissed', 'roles_revoked' => [], 'warehouse_removed' => 0, 'account_blocked' => false, 'blocked_refused' => false];
        if ($uid && $this->e->b->id->exists($uid) && $uid === (int)$c->uid) throw new Err('self', 'you cannot dismiss yourself', 409);
        $this->e->s->atomic(function () use ($c, $emp, $uid, $last, $reason, &$report) {
            if ($uid) {
                foreach ($this->e->s->find('user_roles', ['user_id' => $uid]) as $ur) { $r = $this->e->s->one('roles', ['id' => (int)$ur['role_id']]); $this->e->s->delete('user_roles', (int)$ur['id']); $report['roles_revoked'][] = $r['code'] ?? ''; $this->e->log($c, 'rbac.revoke', 'user', (string)$uid, ['role' => $r['code'] ?? '', 'why' => 'offboarding']); }
                $report['warehouse_removed'] = $this->e->b->wh->revoke($uid);
                if ($report['warehouse_removed']) $this->e->log($c, 'access.revoked', 'user', (string)$uid, ['why' => 'offboarding', 'removed' => $report['warehouse_removed']]);
                if ($this->e->b->id->block($uid)) { $report['account_blocked'] = true; $this->e->log($c, 'access.account_blocked', 'user', (string)$uid, ['why' => 'offboarding']); } else $report['blocked_refused'] = true;
            }
            foreach ($this->e->s->find('team_members', ['employee_id' => (int)$emp['id']]) as $m) if ($m['role'] !== 'removed') $this->e->s->update('team_members', (int)$m['id'], ['role' => 'removed']);
            if ($emp['status'] !== 'dismissed') {
                $this->e->s->update('employees', (int)$emp['id'], ['status' => 'dismissed', 'dismissed_at' => $last, 'archived_at' => $this->e->now()]);
                $this->e->history($c, $emp, 'dismissal', ['last_day' => $last, 'reason' => $reason], $last . ' 00:00:00');
            }
            $codes = array_values(array_diff(self::OFFBOARDING, $report['warehouse_removed'] > 0 ? [] : ['property_goods_issue']));   // the warehouse device is only on the list when the employee had warehouse access
            $this->createTasks($c, $emp, 'offboarding', $codes, $last);
            foreach ($this->e->s->find('leave', ['employee_id' => (int)$emp['id']]) as $l) if (in_array($l['status'], ['pending_manager', 'pending_hr'], true)) $this->e->s->update('leave', (int)$l['id'], ['status' => 'cancelled']);
        });
        $report['checklist'] = array_map(fn($t) => ['uuid' => $t['uuid'], 'code' => $t['code'], 'done' => (int)$t['done']], $this->e->s->find('tasks', ['employee_id' => (int)$emp['id'], 'phase' => 'offboarding']));
        return $report;
    }

    /* =============================== history and audit =============================== */
    public function timeline(Ctx $c, string $empUuid): array
    {
        $emp = $this->e->emp($empUuid); if (!$c->can('hr.audit.view') && !$c->isSelf($emp)) throw new Err('forbidden', 'permission required: hr.audit.view', 403);
        return $this->e->s->find('status_history', ['employee_id' => (int)$emp['id']], 'at');
    }
    public function auditRows(Ctx $c, array $in = []): array
    {
        $c->need('hr.audit.view'); $w = []; foreach (['entity', 'ref', 'action'] as $k) if (!empty($in[$k])) $w[$k] = (string)$in[$k];
        return $this->e->s->find('audit', $w, 'id DESC', max(1, min(1000, (int)($in['limit'] ?? 200))));
    }
    public function verifyAudit(Ctx $c): array { $c->need('hr.audit.view'); $rows = $this->e->s->find('audit', [], 'id', 1000000); $bad = Audit::verify($rows); return ['rows' => count($rows), 'broken_at' => $bad, 'ok' => $bad === null]; }

    /* =============================== data protection (152-FZ) =============================== */
    public function hasConsent(array $emp, string $kind): bool
    {
        $last = $this->e->s->find('consents', ['employee_id' => (int)$emp['id'], 'kind' => $kind], 'id DESC', 1);
        return $last && $last[0]['action'] === 'grant' && $last[0]['version'] === (string)$this->e->cfg['consent_version'];
    }
    /** append-only: every grant / revoke is a new timestamped row carrying the policy version */
    public function consent(Ctx $c, string $empUuid, string $kind, string $action): array
    {
        $emp = $this->e->emp($empUuid); if (!$c->isSelf($emp) && !$c->can('hr.consent.manage')) throw new Err('forbidden', 'only the employee or hr.consent.manage', 403);
        if (!in_array($action, ['grant', 'revoke'], true) || !preg_match('/^[a-z_]{3,40}$/', $kind)) throw new Err('bad_consent', 'kind: a_z_, action: grant|revoke', 422);
        $cur = $this->hasConsent($emp, $kind);
        if (($action === 'grant' && !$cur) || ($action === 'revoke' && $cur)) {
            $this->e->s->insert('consents', ['employee_id' => (int)$emp['id'], 'kind' => $kind, 'version' => (string)$this->e->cfg['consent_version'], 'action' => $action, 'at' => $this->e->now(), 'actor' => $c->actor()]);
            $this->e->log($c, 'consent.' . $action, 'employee', (string)$emp['uuid'], ['kind' => $kind, 'version' => (string)$this->e->cfg['consent_version']]);
        }
        return ['employee_uuid' => $emp['uuid'], 'kind' => $kind, 'granted' => $this->hasConsent($emp, $kind), 'history' => $this->e->s->find('consents', ['employee_id' => (int)$emp['id']], 'id')];
    }
    public function missingConsent(Ctx $c): array
    {
        $c->need('hr.consent.manage'); $out = [];
        foreach ($this->e->s->find('employees', ['status' => 'active']) as $emp) if (!$this->hasConsent($emp, 'personal_data')) $out[] = ['employee_uuid' => $emp['uuid'], 'full_name' => $emp['full_name']];
        return $out;
    }
    /** CSV of the allowed columns only (never passport or pay); each export is a row in the audit chain. Formula injection is neutralised. */
    public function exportEmployees(Ctx $c, array $in = []): array
    {
        $c->need('hr.export'); $cols = array_values(array_intersect(self::EXPORT_COLUMNS, (array)($in['columns'] ?? self::EXPORT_COLUMNS))) ?: self::EXPORT_COLUMNS;
        $rows = $this->e->s->find('employees', empty($in['status']) ? [] : ['status' => (string)$in['status']], 'id', 5000);
        $q = fn($v) => '"' . str_replace('"', '""', preg_match('/^[=+\-@\t\r]/', (string)$v) ? "'" . $v : (string)$v) . '"';
        $csv = implode(',', $cols) . "\n"; foreach ($rows as $r) $csv .= implode(',', array_map(fn($k) => $q($r[$k] ?? ''), $cols)) . "\n";
        $this->e->log($c, 'export.employees', 'export', $this->e->uuid(), ['columns' => $cols, 'rows' => count($rows), 'format' => 'csv', 'purpose' => $this->e->str($in, 'purpose', 200)]);
        return ['format' => 'csv', 'rows' => count($rows), 'content' => $csv];
    }
}
