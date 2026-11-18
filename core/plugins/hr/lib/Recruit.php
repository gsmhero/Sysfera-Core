<?php
declare(strict_types=1);
namespace SysferaHr;

/** Vacancies (versioned descriptions, approval workflow, Activity feed publication, job-board hook) and the candidate pipeline (interviews, feedback, hire -> employee). */
final class Recruit
{
    /** job boards plug in here: each provider is callable(array $vacancy, array $version): ?string  (returns an external id). No providers ship with the module. */
    private array $boards = [];

    public function __construct(private Env $e, private Hr $hr) {}
    public function addBoard(string $name, callable $fn): void { $this->boards[$name] = $fn; }

    /* =============================== vacancies =============================== */
    public function listVacancies(Ctx $c, array $in = []): array
    {
        $c->need('hr.vacancies.manage'); $w = []; foreach (['status', 'wf'] as $k) if (!empty($in[$k])) $w[$k] = (string)$in[$k];
        return array_map(fn($v) => $this->shape($v), $this->e->s->find('vacancies', $w, 'id DESC'));
    }
    public const CURRENCIES = ['RUB' => '₽', 'USD' => '$', 'EUR' => '€'];
    /** the salary range from the input: whole numbers, both ends optional, from <= to. Only the keys that were sent change an existing vacancy. */
    private function salary(array $in, ?array $ex): array
    {
        if ($ex && !array_key_exists('salary_from', $in) && !array_key_exists('salary_to', $in) && !array_key_exists('salary_cur', $in)) return [];
        $n = function ($v): ?int { $v = preg_replace('/[\s\x{00A0}]/u', '', (string)$v); if ($v === '') return null; if (!preg_match('/^\d{1,9}$/', $v)) throw new Err('bad_salary', 'the salary must be a whole number', 422); return (int)$v; };
        $from = $n($in['salary_from'] ?? ($ex['salary_from'] ?? '')); $to = $n($in['salary_to'] ?? ($ex['salary_to'] ?? ''));
        if ($from !== null && $to !== null && $from > $to) throw new Err('bad_salary', 'the salary "from" is above "to"', 422);
        $cur = strtoupper((string)($in['salary_cur'] ?? ($ex['salary_cur'] ?? 'RUB'))); if (!isset(self::CURRENCIES[$cur])) $cur = 'RUB';
        return ['salary_from' => $from, 'salary_to' => $to, 'salary_cur' => $cur];
    }
    public const MAX_SKILLS = 30, SKILL_LEN = 40;
    /** The skills of a vacancy: a list (or a comma / line separated text) of short names, trimmed, without repeats (case does not matter), at most 30. Sent only when the key is present. */
    private function skillsIn(array $in, ?array $ex): array
    {
        if (!array_key_exists('skills', $in)) return $ex ? [] : ['skills' => null];
        $raw = $in['skills']; if (!is_array($raw)) $raw = preg_split('/[,\n\r;]+/u', (string)$raw); $out = []; $seen = [];
        foreach ($raw as $x) {
            $x = trim(preg_replace('/\s+/u', ' ', (string)$x)); if ($x === '') continue;
            $x = function_exists('mb_substr') ? mb_substr($x, 0, self::SKILL_LEN) : (string)preg_replace('/^(.{' . self::SKILL_LEN . '}).*$/us', '$1', $x);
            $k = function_exists('mb_strtolower') ? mb_strtolower($x) : strtolower($x); if (isset($seen[$k])) continue; $seen[$k] = true; $out[] = $x;
            if (count($out) >= self::MAX_SKILLS) break;
        }
        return ['skills' => $out ? json_encode($out, JSON_UNESCAPED_UNICODE) : null];
    }
    public static function skillList(array $v): array { $j = $v['skills'] ?? null; if (is_array($j)) return array_values($j); $a = is_string($j) && $j !== '' ? json_decode($j, true) : []; return is_array($a) ? array_values(array_map('strval', $a)) : []; }
    /** "100 000 – 150 000 ₽", "≥ 100 000 ₽", "≤ 150 000 ₽" or '' */
    public static function salaryText(array $v): string
    {
        $f = $v['salary_from'] ?? null; $t = $v['salary_to'] ?? null; $f = $f === null || $f === '' ? null : (int)$f; $t = $t === null || $t === '' ? null : (int)$t; if ($f === null && $t === null) return '';
        $fmt = fn(int $n) => number_format($n, 0, '.', ' '); $cur = self::CURRENCIES[$v['salary_cur'] ?? 'RUB'] ?? '₽';
        return ($f !== null && $t !== null ? $fmt($f) . ' – ' . $fmt($t) : ($f !== null ? '≥ ' . $fmt($f) : '≤ ' . $fmt($t))) . ' ' . $cur;
    }
    /** What a visitor may read about a published, open vacancy (HR managers may also see the others): no permission needed, nothing internal. */
    public function preview(Ctx $c, string $uuid, string $email = ''): array
    {
        $v = $this->e->row('vacancies', $uuid);
        if (!($v['status'] === 'open' && $v['wf'] === 'published') && !$c->can('hr.vacancies.manage')) throw new Err('not_found', 'no such vacancy', 404);
        $s = $this->shape($v); $role = $this->e->s->one('roles', ['id' => (int)$v['role_id']]); $team = !empty($v['team_id']) ? $this->e->s->one('teams', ['id' => (int)$v['team_id']]) : null; $email = strtolower(trim($email));
        return ['uuid' => (string)$v['uuid'], 'title' => (string)$v['title'], 'description' => (string)$s['description'], 'role' => (string)($role['name'] ?? ''), 'role_ru' => (string)(($role['name_ru'] ?? '') ?: ($role['name'] ?? '')), 'team' => (string)($team['name'] ?? ''),
            'salary' => self::salaryText($v), 'skills' => $s['skills'], 'published_at' => (string)($v['published_at'] ?? ''), 'open' => $v['status'] === 'open' && $v['wf'] === 'published', 'applications' => $s['candidates'],
            'applied' => $email !== '' && (bool)$this->e->s->one('candidates', ['vacancy_id' => (int)$v['id'], 'email' => $email])];
    }
    private function shape(array $v): array
    {
        $v['skills'] = self::skillList($v);
        $v['team_uuid'] = !empty($v['team_id']) ? ($this->e->s->one('teams', ['id' => (int)$v['team_id']])['uuid'] ?? null) : null;
        $v['role_uuid'] = $this->e->s->one('roles', ['id' => (int)$v['role_id']])['uuid'] ?? null;
        $cur = $this->e->s->find('vacancy_versions', ['vacancy_id' => (int)$v['id']], 'version DESC', 1); $v['description'] = $cur[0]['description'] ?? '';
        $v['candidates'] = count($this->e->s->find('candidates', ['vacancy_id' => (int)$v['id']])); return $v;
    }
    public function getVacancy(Ctx $c, string $uuid): array { $c->need('hr.vacancies.manage'); return $this->shape($this->e->row('vacancies', $uuid)); }
    public function versions(Ctx $c, string $uuid): array { $c->need('hr.vacancies.manage'); return $this->e->s->find('vacancy_versions', ['vacancy_id' => (int)$this->e->row('vacancies', $uuid)['id']], 'version DESC'); }

    /** create or edit. A changed description makes a NEW VERSION (the old text stays) and sends an approved / published vacancy back to draft. */
    public function saveVacancy(Ctx $c, array $in): array
    {
        $c->need('hr.vacancies.manage'); $uuid = $this->e->uuidOrNew($in); $ex = $this->e->s->one('vacancies', ['uuid' => $uuid]);
        $role = $this->e->row('roles', (string)($in['role_uuid'] ?? $this->e->s->one('roles', ['id' => (int)($ex['role_id'] ?? 0)])['uuid'] ?? ''));
        if (!(int)$role['active']) throw new Err('inactive', 'the role is deactivated', 409);
        $sal = $this->salary($in, $ex) + $this->skillsIn($in, $ex);
        $title = $this->e->str($in, 'title', 160, true); $desc = $this->e->str($in, 'description', 20000, !$ex);
        $team = !empty($in['team_uuid']) ? (int)$this->e->row('teams', (string)$in['team_uuid'])['id'] : ($ex['team_id'] ?? null);
        if ($ex) {
            if ($ex['status'] !== 'open') throw new Err('closed', 'a closed vacancy cannot be edited', 409);
            $this->e->s->update('vacancies', (int)$ex['id'], ['title' => $title, 'role_id' => (int)$role['id'], 'team_id' => $team] + $sal);
        } else {
            $this->e->s->insert('vacancies', ['uuid' => $uuid, 'role_id' => (int)$role['id'], 'team_id' => $team, 'title' => $title, 'status' => 'open', 'wf' => 'draft', 'version' => 0, 'post_ref' => null, 'external' => null, 'created_by' => $c->actor(), 'created_at' => $this->e->now(), 'published_at' => null] + $sal);
            $this->e->log($c, 'vacancy.created', 'vacancy', $uuid, ['title' => $title]);
        }
        $v = $this->e->row('vacancies', $uuid); $last = $this->e->s->find('vacancy_versions', ['vacancy_id' => (int)$v['id']], 'version DESC', 1);
        if ($desc !== '' && (!$last || $last[0]['description'] !== $desc)) {
            $n = (int)$v['version'] + 1;
            $this->e->s->insert('vacancy_versions', ['vacancy_id' => (int)$v['id'], 'version' => $n, 'description' => $desc, 'sha256' => hash('sha256', $desc), 'created_by' => $c->actor(), 'created_at' => $this->e->now()]);
            $upd = ['version' => $n]; if (in_array($v['wf'], ['approved', 'published'], true)) $upd['wf'] = 'draft';
            $this->e->s->update('vacancies', (int)$v['id'], $upd); $this->e->log($c, 'vacancy.version', 'vacancy', $uuid, ['version' => $n, 'sha256' => hash('sha256', $desc), 'wf' => $upd['wf'] ?? $v['wf']]);
        }
        return $this->shape($this->e->row('vacancies', $uuid));
    }
    private function wf(Ctx $c, array $v, string $to, array $extra = []): array
    {
        if (!Pipeline::canWf((string)$v['wf'], $to)) throw new Err('bad_state', "workflow {$v['wf']} -> $to is not allowed", 409);
        $this->e->s->update('vacancies', (int)$v['id'], ['wf' => $to] + $extra); $this->e->log($c, 'vacancy.' . $to, 'vacancy', (string)$v['uuid'], ['from' => $v['wf']] + $extra);
        return $this->shape($this->e->row('vacancies', (string)$v['uuid']));
    }
    public function approve(Ctx $c, string $uuid): array
    {
        $c->need('hr.vacancies.approve'); $v = $this->e->row('vacancies', $uuid);
        if ($v['status'] !== 'open') throw new Err('closed', 'the vacancy is closed', 409);
        return $this->wf($c, $v, 'approved', ['approved_by' => $c->actor()]);
    }
    /** publish to the Activity feed (draft -> approved -> PUBLISHED) and offer the posting to registered job boards */
    public function publish(Ctx $c, string $uuid, string $audience = 'global'): array
    {
        $c->need('hr.vacancies.approve'); $v = $this->e->row('vacancies', $uuid);
        if ($v['wf'] === 'published') return $this->shape($v);   // idempotent
        if (!Pipeline::canWf((string)$v['wf'], 'published')) throw new Err('bad_state', 'approve the vacancy first', 409);
        $ver = $this->e->s->find('vacancy_versions', ['vacancy_id' => (int)$v['id']], 'version DESC', 1)[0]; $ext = [];
        $sal = self::salaryText($v); $ref = $this->e->b->feed->publish((string)$v['title'], ($sal !== '' ? $sal . '. ' : '') . (string)$ver['description'], in_array($audience, ['global', 'users', 'admins'], true) ? $audience : 'global');
        foreach ($this->boards as $name => $fn) { try { $ext[$name] = $fn($v, $ver); } catch (\Throwable $x) { $ext[$name] = 'error: ' . substr($x->getMessage(), 0, 80); } }
        try { \Sysfera\Hooks::do('hr.vacancy.published', $v, $ver); } catch (\Throwable) {}
        return $this->wf($c, $v, 'published', ['post_ref' => $ref, 'external' => $ext ? json_encode($ext) : null, 'published_at' => $this->e->now()]);
    }
    /** Published, open vacancies with the number of applications. Nothing personal and no permission: it feeds the dashboard and the Activity button. $email marks the ones that person already applied to. */
    public function openPublished(string $email = ''): array
    {
        $email = strtolower(trim($email)); $out = [];
        foreach ($this->e->s->find('vacancies', ['status' => 'open', 'wf' => 'published'], 'id DESC') as $v) {
            $cands = $this->e->s->find('candidates', ['vacancy_id' => (int)$v['id']]);
            $out[] = ['uuid' => (string)$v['uuid'], 'title' => (string)$v['title'], 'salary' => self::salaryText($v), 'post_ref' => (string)($v['post_ref'] ?? ''), 'published_at' => (string)($v['published_at'] ?? ''), 'applications' => count($cands),
                'new' => count(array_filter($cands, fn($x) => $x['stage'] === 'new')), 'applied' => $email !== '' && (bool)array_filter($cands, fn($x) => strtolower((string)$x['email']) === $email)];
        }
        return $out;
    }
    /** "Apply": a signed-in user answers a published vacancy; they become a candidate in the stage NEW. HR managers are told. */
    public function apply(Ctx $c, string $vacUuid, array $in): array
    {
        if (!$c->uid) throw new Err('forbidden', 'sign in to apply', 403);
        $v = $this->e->row('vacancies', $vacUuid);
        if ($v['status'] !== 'open' || $v['wf'] !== 'published') throw new Err('closed', 'the vacancy is not open for applications', 409);
        $email = $this->e->email((string)($in['email'] ?? ''), true);
        if ($this->e->s->one('candidates', ['vacancy_id' => (int)$v['id'], 'email' => $email])) throw new Err('duplicate', 'this candidate already applied', 409);
        $phone = $this->e->str($in, 'phone', 40); $uuid = $this->e->uuidOrNew([]);
        $id = $this->e->s->insert('candidates', ['uuid' => $uuid, 'vacancy_id' => (int)$v['id'], 'full_name' => $this->e->str($in, 'full_name', 160, true), 'email' => $email, 'phone_enc' => $phone === '' ? '' : $this->e->seal($phone), 'source' => 'apply', 'stage' => 'new', 'employee_id' => null, 'created_at' => $this->e->now(), 'updated_at' => $this->e->now()]);
        $this->e->s->insert('stage_log', ['candidate_id' => $id, 'from_stage' => '', 'to_stage' => 'new', 'note' => '', 'at' => $this->e->now(), 'actor' => $c->actor()]);
        $this->e->log($c, 'candidate.applied', 'candidate', $uuid, ['vacancy' => $v['uuid']]);
        $cd = $this->cand($this->e->s->one('candidates', ['id' => $id]));
        $this->e->notifyPerm('hr.candidates.manage', 'New application: ' . $cd['full_name'] . ' - ' . $v['title']);
        try { \Sysfera\Hooks::do('hr.candidate.applied', $v, $cd); } catch (\Throwable) {}
        return $cd;
    }
    public function closeVacancy(Ctx $c, string $uuid): array
    {
        $c->need('hr.vacancies.manage'); $v = $this->e->row('vacancies', $uuid); if ($v['status'] === 'closed' || $v['status'] === 'archived') return $this->shape($v);
        $this->e->s->update('vacancies', (int)$v['id'], ['status' => 'closed']); $v = $this->e->row('vacancies', $uuid);
        return $v['wf'] === 'closed' ? $this->shape($v) : $this->wf($c, $v, 'closed');
    }
    public function archive(Ctx $c, string $uuid): array
    {
        $c->need('hr.vacancies.manage'); $v = $this->e->row('vacancies', $uuid);
        if ($v['status'] === 'open') throw new Err('bad_state', 'close the vacancy before archiving', 409);
        if ($v['status'] !== 'archived') { $this->e->s->update('vacancies', (int)$v['id'], ['status' => 'archived']); $this->e->log($c, 'vacancy.archived', 'vacancy', $uuid, []); }
        return $this->shape($this->e->row('vacancies', $uuid));
    }

    /* =============================== candidates: kanban =============================== */
    public function board(Ctx $c, string $vacUuid): array
    {
        $c->need('hr.candidates.manage'); $v = $this->e->row('vacancies', $vacUuid); $cols = array_fill_keys(Pipeline::STAGES, []);
        foreach ($this->e->s->find('candidates', ['vacancy_id' => (int)$v['id']], 'id') as $cd) $cols[$cd['stage']][] = $this->cand($cd);
        return ['vacancy' => $this->shape($v), 'stages' => Pipeline::STAGES, 'columns' => $cols];
    }
    private function cand(array $cd): array { unset($cd['phone_enc']); $cd['employee_uuid'] = !empty($cd['employee_id']) ? ($this->e->s->one('employees', ['id' => (int)$cd['employee_id']])['uuid'] ?? null) : null; return $cd; }
    public function addCandidate(Ctx $c, string $vacUuid, array $in): array
    {
        $c->need('hr.candidates.manage'); $v = $this->e->row('vacancies', $vacUuid); $uuid = $this->e->uuidOrNew($in);
        if ($ex = $this->e->s->one('candidates', ['uuid' => $uuid])) return $this->cand($ex);
        if ($v['status'] !== 'open') throw new Err('closed', 'the vacancy is closed', 409);
        $email = $this->e->email((string)($in['email'] ?? ''), true);
        if ($this->e->s->one('candidates', ['vacancy_id' => (int)$v['id'], 'email' => $email])) throw new Err('duplicate', 'this candidate already applied', 409);
        $phone = $this->e->str($in, 'phone', 40);
        $id = $this->e->s->insert('candidates', ['uuid' => $uuid, 'vacancy_id' => (int)$v['id'], 'full_name' => $this->e->str($in, 'full_name', 160, true), 'email' => $email, 'phone_enc' => $phone === '' ? '' : $this->e->seal($phone), 'source' => $this->e->str($in, 'source', 60), 'stage' => 'new', 'employee_id' => null, 'created_at' => $this->e->now(), 'updated_at' => $this->e->now()]);
        $this->e->s->insert('stage_log', ['candidate_id' => $id, 'from_stage' => '', 'to_stage' => 'new', 'note' => '', 'at' => $this->e->now(), 'actor' => $c->actor()]);
        $this->e->log($c, 'candidate.added', 'candidate', $uuid, ['vacancy' => $v['uuid']]);
        return $this->cand($this->e->s->one('candidates', ['id' => $id]));
    }
    public function move(Ctx $c, string $uuid, string $to, string $note = ''): array
    {
        $c->need('hr.candidates.manage'); $cd = $this->e->row('candidates', $uuid);
        if ($cd['stage'] === $to) return $this->cand($cd);   // idempotent
        if ($to === 'hired') throw new Err('use_hire', 'use the hire action: it creates the employee', 409);
        if (!Pipeline::canMove((string)$cd['stage'], $to)) throw new Err('bad_move', "{$cd['stage']} -> $to is not allowed", 409);
        if ($to === 'offer') {
            $fb = $this->feedbackOf((int)$cd['id']);
            if (!$fb) throw new Err('feedback_required', 'an offer needs interviewer feedback', 409);
            if (count(array_filter($fb, fn($f) => $f['recommend'] === 'strong_no'))) throw new Err('strong_no', 'an interviewer strongly advises against', 409);
        }
        if ($to === 'rejected' && trim($note) === '') throw new Err('note_required', 'give a reason for the rejection', 422);
        $this->e->s->update('candidates', (int)$cd['id'], ['stage' => $to, 'updated_at' => $this->e->now()]);
        $this->e->s->insert('stage_log', ['candidate_id' => (int)$cd['id'], 'from_stage' => $cd['stage'], 'to_stage' => $to, 'note' => substr($note, 0, 500), 'at' => $this->e->now(), 'actor' => $c->actor()]);
        $this->e->log($c, 'candidate.decision', 'candidate', (string)$cd['uuid'], ['from' => $cd['stage'], 'to' => $to, 'note' => substr($note, 0, 500)]);
        return $this->cand($this->e->row('candidates', $uuid));
    }
    /** the hiring decision: the candidate in OFFER becomes an Employee (idempotent); the candidate card and its stage history stay and are copied into the employee timeline */
    public function hire(Ctx $c, string $uuid, array $in): array
    {
        $c->need('hr.candidates.manage'); $c->need('hr.employees.edit'); $cd = $this->e->row('candidates', $uuid);
        if ($cd['stage'] === 'hired') return ['candidate' => $this->cand($cd), 'employee' => $this->hr->getEmployee($c, (string)$this->e->s->one('employees', ['id' => (int)$cd['employee_id']])['uuid'])];
        if ($cd['stage'] !== 'offer') throw new Err('bad_move', 'only a candidate in the offer stage can be hired', 409);
        $v = $this->e->s->one('vacancies', ['id' => (int)$cd['vacancy_id']]); $role = $this->e->s->one('roles', ['id' => (int)$v['role_id']]); $team = $v['team_id'] ? $this->e->s->one('teams', ['id' => (int)$v['team_id']]) : null;
        $phone = (string)$cd['phone_enc'] !== '' ? (string)$this->e->open((string)$cd['phone_enc']) : '';
        $emp = null;
        $this->e->s->atomic(function () use ($c, $cd, $in, $v, $role, $team, $phone, &$emp) {
            $emp = $this->hr->createEmployee($c, ['full_name' => $cd['full_name'], 'email' => $cd['email'], 'phone' => $phone, 'hire_date' => $this->e->date($in, 'hire_date', true), 'position_role_uuid' => $role['uuid'], 'team_uuid' => $team['uuid'] ?? '', 'emp_type' => $in['emp_type'] ?? 'full_time', 'manager_uuid' => $in['manager_uuid'] ?? ''], $cd);
            $row = $this->e->emp((string)$emp['uuid']);
            $this->e->s->update('candidates', (int)$cd['id'], ['stage' => 'hired', 'employee_id' => (int)$row['id'], 'updated_at' => $this->e->now()]);
            $this->e->s->insert('stage_log', ['candidate_id' => (int)$cd['id'], 'from_stage' => 'offer', 'to_stage' => 'hired', 'note' => substr((string)($in['note'] ?? ''), 0, 500), 'at' => $this->e->now(), 'actor' => $c->actor()]);
            $this->e->log($c, 'candidate.decision', 'candidate', (string)$cd['uuid'], ['from' => 'offer', 'to' => 'hired', 'employee' => $emp['uuid'], 'note' => substr((string)($in['note'] ?? ''), 0, 500)]);
            foreach ($this->e->s->find('stage_log', ['candidate_id' => (int)$cd['id']], 'id') as $s) $this->e->s->insert('status_history', ['employee_id' => (int)$row['id'], 'kind' => 'recruiting', 'data' => json_encode(['from' => $s['from_stage'], 'to' => $s['to_stage'], 'note' => $s['note']], JSON_UNESCAPED_UNICODE), 'at' => $s['at'], 'actor' => $s['actor']]);
        });
        return ['candidate' => $this->cand($this->e->row('candidates', $uuid)), 'employee' => $emp];
    }
    public function candidateHistory(Ctx $c, string $uuid): array { $c->need('hr.candidates.manage'); $cd = $this->e->row('candidates', $uuid); return ['candidate' => $this->cand($cd), 'stages' => $this->e->s->find('stage_log', ['candidate_id' => (int)$cd['id']], 'id'), 'interviews' => $this->e->s->find('interviews', ['candidate_id' => (int)$cd['id']], 'starts_at'), 'feedback' => $this->feedbackOf((int)$cd['id'])]; }

    /* =============================== interviews: calendar slots and structured feedback =============================== */
    public function schedule(Ctx $c, string $candUuid, array $in): array
    {
        $c->need('hr.candidates.manage'); $cd = $this->e->row('candidates', $candUuid); $uuid = $this->e->uuidOrNew($in);
        if ($ex = $this->e->s->one('interviews', ['uuid' => $uuid])) return $ex;
        if (!in_array($cd['stage'], ['screening', 'interview'], true)) throw new Err('bad_stage', 'schedule interviews for candidates in screening or interview', 409);
        $uid = (int)($in['interviewer_user_id'] ?? 0); if (!$uid || !$this->e->b->id->exists($uid)) throw new Err('bad_user', 'interviewer_user_id: a user is required', 422);
        $a = $this->e->dt($in, 'starts_at'); $b = $this->e->dt($in, 'ends_at'); if ($b <= $a || substr($a, 0, 10) !== substr($b, 0, 10)) throw new Err('bad_slot', 'ends_at must be after starts_at on the same day', 422);
        foreach ($this->e->s->find('interviews', ['status' => 'scheduled']) as $o) {
            if (!($a < $o['ends_at'] && $o['starts_at'] < $b)) continue;
            if ((int)$o['interviewer_user_id'] === $uid) throw new Err('slot_busy', 'the interviewer is busy at this time', 409);
            if ((int)$o['candidate_id'] === (int)$cd['id']) throw new Err('slot_busy', 'the candidate has another interview at this time', 409);
        }
        if (($emp = $this->e->empOfUser($uid))) foreach ($this->e->s->find('leave', ['employee_id' => (int)$emp['id'], 'status' => 'approved']) as $l) if (TimeOff::overlap($l['starts_on'], $l['ends_on'], substr($a, 0, 10), substr($a, 0, 10))) throw new Err('interviewer_away', 'the interviewer is on leave that day', 409);
        $id = $this->e->s->insert('interviews', ['uuid' => $uuid, 'candidate_id' => (int)$cd['id'], 'interviewer_user_id' => $uid, 'starts_at' => $a, 'ends_at' => $b, 'place' => $this->e->str($in, 'place', 200), 'status' => 'scheduled', 'created_by' => $c->actor(), 'created_at' => $this->e->now()]);
        if ($cd['stage'] === 'screening') $this->move($c, $candUuid, 'interview', 'interview scheduled');
        $this->e->log($c, 'interview.scheduled', 'candidate', (string)$cd['uuid'], ['interview' => $uuid, 'interviewer' => $uid, 'starts_at' => $a]);
        try { $this->e->b->msg->notify($uid, 'Interview: ' . $cd['full_name'] . ', ' . $a); } catch (\Throwable) {}
        return $this->e->s->one('interviews', ['id' => $id]);
    }
    public function cancelInterview(Ctx $c, string $uuid): array
    {
        $c->need('hr.candidates.manage'); $i = $this->e->row('interviews', $uuid);
        if ($i['status'] === 'scheduled') { $this->e->s->update('interviews', (int)$i['id'], ['status' => 'cancelled']); $this->e->log($c, 'interview.cancelled', 'candidate', (string)$this->e->s->one('candidates', ['id' => (int)$i['candidate_id']])['uuid'], ['interview' => $uuid]); }
        return $this->e->row('interviews', $uuid);
    }
    /** free one-hour slots of an interviewer on a day (09:00-18:00) */
    public function slots(Ctx $c, int $uid, string $day): array
    {
        $c->need('hr.candidates.manage'); $day = $this->e->date(['d' => $day], 'd', true); $busy = [];
        foreach ($this->e->s->find('interviews', ['interviewer_user_id' => $uid, 'status' => 'scheduled']) as $o) if (substr($o['starts_at'], 0, 10) === $day) $busy[] = [$o['starts_at'], $o['ends_at']];
        if (($emp = $this->e->empOfUser($uid))) foreach ($this->e->s->find('leave', ['employee_id' => (int)$emp['id'], 'status' => 'approved']) as $l) if (TimeOff::overlap($l['starts_on'], $l['ends_on'], $day, $day)) return [];
        $out = []; for ($h = 9; $h < 18; $h++) { $a = sprintf('%s %02d:00:00', $day, $h); $b = sprintf('%s %02d:00:00', $day, $h + 1); $ok = true; foreach ($busy as [$s, $t]) if ($a < $t && $s < $b) $ok = false; if ($ok) $out[] = ['starts_at' => $a, 'ends_at' => $b]; }
        return $out;
    }
    /** only the assigned interviewer gives feedback, once per interview (a repeat of the same document is idempotent) */
    public function feedback(Ctx $c, string $interviewUuid, array $in): array
    {
        $c->need('hr.interviews.feedback'); $i = $this->e->row('interviews', $interviewUuid);
        if ((int)$i['interviewer_user_id'] !== (int)$c->uid && !$c->root) throw new Err('forbidden', 'only the assigned interviewer gives feedback', 403);
        if ($i['status'] === 'cancelled') throw new Err('bad_state', 'the interview was cancelled', 409);
        $f = Pipeline::feedback($in);
        if ($ex = $this->e->s->one('feedback', ['interview_id' => (int)$i['id']])) { if ($ex['recommend'] === $f['recommend'] && $ex['scores'] === json_encode($f['scores'])) return $ex; throw new Err('duplicate', 'feedback for this interview was already given', 409); }
        $id = $this->e->s->insert('feedback', ['interview_id' => (int)$i['id'], 'candidate_id' => (int)$i['candidate_id'], 'interviewer_user_id' => (int)$i['interviewer_user_id'], 'scores' => json_encode($f['scores']), 'avg' => $f['avg'], 'recommend' => $f['recommend'], 'comment' => $f['comment'], 'created_at' => $this->e->now()]);
        $this->e->s->update('interviews', (int)$i['id'], ['status' => 'done']);
        $this->e->log($c, 'interview.feedback', 'candidate', (string)$this->e->s->one('candidates', ['id' => (int)$i['candidate_id']])['uuid'], ['interview' => $interviewUuid, 'recommend' => $f['recommend'], 'avg' => $f['avg']]);
        return $this->e->s->one('feedback', ['id' => $id]);
    }
    private function feedbackOf(int $candId): array { return $this->e->s->find('feedback', ['candidate_id' => $candId], 'id'); }
}
