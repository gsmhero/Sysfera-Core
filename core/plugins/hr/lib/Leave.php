<?php
declare(strict_types=1);
namespace SysferaHr;

/** Time off (vacation / sick / unpaid) with the approval chain manager -> HR, balances, the team calendar, work-schedule templates and the payroll activity of a period. */
final class Leave
{
    public function __construct(private Env $e, private Hr $hr) {}

    /* =============================== schedules =============================== */
    public function listSchedules(Ctx $c): array { $c->need('hr.employees.view'); return array_map(fn($s) => $s + ['week_hours' => TimeOff::week($s['week'])], $this->e->s->find('schedules', [], 'name')); }
    public function saveSchedule(Ctx $c, array $in): array
    {
        $c->need('hr.employees.edit'); $uuid = $this->e->uuidOrNew($in); $ex = $this->e->s->one('schedules', ['uuid' => $uuid]);
        $row = ['name' => $this->e->str($in, 'name', 100, true), 'week' => json_encode(TimeOff::week($in['week'] ?? TimeOff::DEFAULT_WEEK))];
        if ($ex) { $this->e->s->update('schedules', (int)$ex['id'], $row); $this->e->log($c, 'schedule.updated', 'schedule', $uuid, $row); } else { $this->e->s->insert('schedules', $row + ['uuid' => $uuid]); $this->e->log($c, 'schedule.created', 'schedule', $uuid, $row); }
        return $this->e->row('schedules', $uuid);
    }
    public function weekOf(array $emp): array
    {
        $s = !empty($emp['schedule_id']) ? $this->e->s->one('schedules', ['id' => (int)$emp['schedule_id']]) : null; return TimeOff::week($s['week'] ?? TimeOff::DEFAULT_WEEK);
    }
    /** shift planning input for the warehouse: working hours of the employees of a warehouse on a day */
    public function shift(Ctx $c, int $warehouseId, string $day): array
    {
        $c->need('hr.leave.view'); $out = []; $dow = (int)date('N', strtotime($day . ' 12:00:00'));
        foreach ($this->e->s->find('employees', ['status' => 'active']) as $emp) {
            $team = $emp['team_id'] ? $this->e->s->one('teams', ['id' => (int)$emp['team_id']]) : null; $wh = $emp['warehouse_id'] ?: ($team['warehouse_id'] ?? null);
            if ((int)$wh !== $warehouseId) continue;
            $away = false; foreach ($this->e->s->find('leave', ['employee_id' => (int)$emp['id'], 'status' => 'approved']) as $l) if (TimeOff::overlap($l['starts_on'], $l['ends_on'], $day, $day)) $away = true;
            $out[] = ['employee_uuid' => $emp['uuid'], 'full_name' => $emp['full_name'], 'hours' => $away ? 0.0 : $this->weekOf($emp)[$dow], 'away' => $away];
        }
        return $out;
    }

    /* =============================== requests =============================== */
    private function usedDays(array $emp, ?string $exceptUuid = null): float
    {
        $d = 0.0; foreach ($this->e->s->find('leave', ['employee_id' => (int)$emp['id'], 'kind' => 'vacation']) as $l) if (in_array($l['status'], ['approved', 'pending_manager', 'pending_hr'], true) && $l['uuid'] !== $exceptUuid) $d += (float)$l['days']; return $d;
    }
    public function balance(Ctx $c, string $empUuid): array
    {
        $emp = $this->e->emp($empUuid); if (!$c->isSelf($emp) && !$c->can('hr.leave.view')) throw new Err('forbidden', 'permission required: hr.leave.view', 403);
        return ['employee_uuid' => $emp['uuid']] + TimeOff::balance((string)$emp['hire_date'], $this->e->today(), (float)$this->e->cfg['annual_days'], $this->usedDays($emp));
    }
    public function request(Ctx $c, array $in): array
    {
        $emp = !empty($in['employee_uuid']) ? $this->e->emp((string)$in['employee_uuid']) : ($c->employeeId ? $this->e->s->one('employees', ['id' => $c->employeeId]) : null);
        if (!$emp) throw new Err('missing_field', 'employee_uuid is required', 422);
        if (!$c->isSelf($emp) && !$c->can('hr.leave.approve')) throw new Err('forbidden', 'you can only request leave for yourself', 403);
        if ($emp['status'] !== 'active') throw new Err('archived', 'the profile is archived', 409);
        $uuid = $this->e->uuidOrNew($in); if ($ex = $this->e->s->one('leave', ['uuid' => $uuid])) return $ex;
        $kind = (string)($in['kind'] ?? ''); if (!in_array($kind, TimeOff::KINDS, true)) throw new Err('bad_kind', 'kind: ' . implode('|', TimeOff::KINDS), 422);
        $a = $this->e->date($in, 'starts_on', true); $b = $this->e->date($in, 'ends_on', true); if ($b < $a) throw new Err('bad_dates', 'ends_on is before starts_on', 422);
        foreach ($this->e->s->find('leave', ['employee_id' => (int)$emp['id']]) as $l) if (in_array($l['status'], ['pending_manager', 'pending_hr', 'approved'], true) && TimeOff::overlap($a, $b, $l['starts_on'], $l['ends_on'])) throw new Err('overlap', 'the dates overlap another request', 409);
        $span = TimeOff::span($this->weekOf($emp), $a, $b, (array)$this->e->cfg['holidays']);
        if ($span['days'] === 0) throw new Err('no_workdays', 'no working days in the period', 422);
        $bal = TimeOff::balance((string)$emp['hire_date'], $b, (float)$this->e->cfg['annual_days'], $this->usedDays($emp));
        if ($kind === 'vacation' && $span['days'] > $bal['available']) throw new Err('insufficient_balance', 'not enough vacation days: ' . $bal['available'] . ' available, ' . $span['days'] . ' requested', 409);
        $mgr = !empty($emp['manager_id']) ? $this->e->s->one('employees', ['id' => (int)$emp['manager_id'], 'status' => 'active']) : null;
        $id = $this->e->s->insert('leave', ['uuid' => $uuid, 'employee_id' => (int)$emp['id'], 'kind' => $kind, 'starts_on' => $a, 'ends_on' => $b, 'days' => $span['days'], 'status' => TimeOff::first($mgr !== null && !empty($mgr['user_id'])), 'note' => $this->e->str($in, 'note', 300), 'manager_by' => '', 'hr_by' => '', 'created_at' => $this->e->now()]);
        $this->e->log($c, 'leave.requested', 'employee', (string)$emp['uuid'], ['leave' => $uuid, 'kind' => $kind, 'from' => $a, 'to' => $b, 'days' => $span['days']]);
        $l = $this->e->s->one('leave', ['id' => $id]);
        if ($l['status'] === 'pending_manager' && $mgr && $mgr['user_id']) { try { $this->e->b->msg->notify((int)$mgr['user_id'], 'Leave request: ' . $emp['full_name'] . ' ' . $a . ' - ' . $b); } catch (\Throwable) {} }
        else $this->e->notifyPerm('hr.leave.approve', 'Leave request: ' . $emp['full_name'] . ' ' . $a . ' - ' . $b);
        return $l;
    }
    /** approve / reject the current step: the manager of the employee decides pending_manager, HR (hr.leave.approve) decides pending_hr. Nobody decides their own request. */
    public function decide(Ctx $c, string $uuid, string $decision, string $note = ''): array
    {
        $l = $this->e->row('leave', $uuid); $emp = $this->e->s->one('employees', ['id' => (int)$l['employee_id']]);
        if (!in_array($decision, ['approve', 'reject'], true)) throw new Err('bad_decision', 'decision: approve|reject', 422);
        if ($c->isSelf($emp)) throw new Err('self_approval', 'you cannot decide your own request', 403);
        if ($l['status'] === 'pending_manager') { if (!($c->employeeId !== null && (int)$emp['manager_id'] === $c->employeeId) && !$c->root) throw new Err('forbidden', 'only the manager of the employee decides this step', 403); }
        elseif ($l['status'] === 'pending_hr') $c->need('hr.leave.approve');
        else throw new Err('bad_state', "the request is {$l['status']}", 409);
        $step = $l['status'] === 'pending_manager' ? 'manager' : 'hr';
        $to = $decision === 'reject' ? 'rejected' : TimeOff::next((string)$l['status'], true);
        $this->e->s->update('leave', (int)$l['id'], ['status' => $to, $step . '_by' => $c->actor()]);
        $this->e->log($c, 'leave.' . $decision, 'employee', (string)$emp['uuid'], ['leave' => $l['uuid'], 'step' => $step, 'to' => $to, 'note' => substr($note, 0, 300)]);
        if ($to === 'approved') $this->e->history($c, $emp, $l['kind'] === 'sick' ? 'sick' : 'vacation', ['from' => $l['starts_on'], 'to' => $l['ends_on'], 'days' => $l['days'], 'kind' => $l['kind']], $l['starts_on'] . ' 00:00:00');
        elseif ($to === 'pending_hr') $this->e->notifyPerm('hr.leave.approve', 'Leave awaits HR: ' . $emp['full_name'] . ' ' . $l['starts_on'] . ' - ' . $l['ends_on']);
        if ($emp['user_id'] && in_array($to, ['approved', 'rejected'], true)) { try { $this->e->b->msg->notify((int)$emp['user_id'], 'Leave ' . $to . ': ' . $l['starts_on'] . ' - ' . $l['ends_on']); } catch (\Throwable) {} }
        return $this->e->row('leave', $uuid);
    }
    public function cancel(Ctx $c, string $uuid): array
    {
        $l = $this->e->row('leave', $uuid); $emp = $this->e->s->one('employees', ['id' => (int)$l['employee_id']]);
        if (!$c->isSelf($emp) && !$c->can('hr.leave.approve')) throw new Err('forbidden', 'only the employee or HR cancels', 403);
        if (in_array($l['status'], ['cancelled', 'rejected'], true)) return $l;
        if ($l['status'] === 'approved' && $l['starts_on'] <= $this->e->today() && !$c->can('hr.leave.approve')) throw new Err('started', 'a leave that has started can only be cancelled by HR', 409);
        $this->e->s->update('leave', (int)$l['id'], ['status' => 'cancelled']); $this->e->log($c, 'leave.cancelled', 'employee', (string)$emp['uuid'], ['leave' => $uuid, 'was' => $l['status']]);
        return $this->e->row('leave', $uuid);
    }
    public function listLeave(Ctx $c, array $in = []): array
    {
        $w = []; if (!empty($in['status'])) $w['status'] = (string)$in['status'];
        if (!empty($in['employee_uuid'])) { $emp = $this->e->emp((string)$in['employee_uuid']); if (!$c->isSelf($emp) && !$c->can('hr.leave.view')) throw new Err('forbidden', 'permission required: hr.leave.view', 403); $w['employee_id'] = (int)$emp['id']; }
        elseif (!$c->can('hr.leave.view')) { if ($c->employeeId === null) return []; $w['employee_id'] = $c->employeeId; }
        return array_map(fn($l) => $l + ['employee_uuid' => $this->e->s->one('employees', ['id' => (int)$l['employee_id']])['uuid'] ?? null], $this->e->s->find('leave', $w, 'id DESC', 500));
    }
    /** team calendar of a month: who is away when. Sick leave is shown as "absent" to everyone except HR (data minimisation). */
    public function calendar(Ctx $c, string $month, ?string $teamUuid = null): array
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) throw new Err('bad_date', 'month: YYYY-MM', 422);
        $c->need('hr.leave.view'); $from = $month . '-01'; $to = date('Y-m-t', strtotime($from . ' 12:00:00')); $team = $teamUuid ? (int)$this->e->row('teams', $teamUuid)['id'] : null; $out = [];
        foreach ($this->e->s->find('leave', [], 'starts_on', 5000) as $l) {
            if (!in_array($l['status'], ['approved', 'pending_manager', 'pending_hr'], true) || !TimeOff::overlap($l['starts_on'], $l['ends_on'], $from, $to)) continue;
            $emp = $this->e->s->one('employees', ['id' => (int)$l['employee_id']]); if ($team !== null && (int)$emp['team_id'] !== $team) continue;
            $out[] = ['employee_uuid' => $emp['uuid'], 'full_name' => $emp['full_name'], 'kind' => $l['kind'] === 'sick' && !$c->can('hr.leave.approve') ? 'absent' : $l['kind'], 'starts_on' => $l['starts_on'], 'ends_on' => $l['ends_on'], 'status' => $l['status']];
        }
        return ['month' => $month, 'entries' => $out];
    }

    /* =============================== payroll activity of a period =============================== */
    /** @return array{planned_days:int, unpaid_days:int, hours:float, missions_completed:int, gi:array} */
    public function activity(array $emp, string $from, string $to): array
    {
        $week = $this->weekOf($emp); $hol = (array)$this->e->cfg['holidays']; $planned = TimeOff::span($week, $from, $to, $hol); $off = []; $unpaid = [];
        foreach ($this->e->s->find('leave', ['employee_id' => (int)$emp['id'], 'status' => 'approved']) as $l) foreach (TimeOff::dates(max($l['starts_on'], $from), min($l['ends_on'], $to)) as $d) { $off[$d] = true; if ($l['kind'] === 'unpaid') $unpaid[$d] = true; }
        $worked = TimeOff::span($week, $from, $to, array_merge($hol, array_keys($off)));
        $u = TimeOff::span($week, $from, $to, array_merge($hol, array_diff(TimeOff::dates($from, $to), array_keys($unpaid))));
        $uid = (int)($emp['user_id'] ?? 0);
        return ['planned_days' => $planned['days'], 'unpaid_days' => $u['days'], 'hours' => $worked['hours'], 'missions_completed' => $uid ? $this->e->b->mis->completed($uid, $from, $to) : 0,
            'gi' => $uid ? $this->e->b->wh->kpi($uid, $from, $to) : ['issues' => 0, 'receipts' => 0, 'returns' => 0, 'counts' => 0]];
    }
    public function payroll(Ctx $c, string $from, string $to, array $commission = [], bool $export = false): array
    {
        $c->need('hr.payroll.run'); $c->need('hr.pay.view');
        $from = $this->e->date(['d' => $from], 'd', true); $to = $this->e->date(['d' => $to], 'd', true); if ($to < $from) throw new Err('bad_dates', 'to is before from', 422);
        $items = [];
        foreach ($this->e->s->find('employees', [], 'id', 5000) as $emp) {
            if ($emp['hire_date'] > $to || (!empty($emp['dismissed_at']) && $emp['dismissed_at'] < $from)) continue;
            $p = $this->e->s->one('pay', ['employee_id' => (int)$emp['id']]); if (!$p) continue;
            $profile = json_decode((string)$this->e->open((string)$p['profile_enc']), true); if (!is_array($profile)) continue;
            $act = $this->activity($emp, $from, $to) + ['commission_base_kop' => (int)($commission[$emp['uuid']] ?? 0)];
            $items[] = ['employee' => $emp, 'calc' => Payroll::calc($profile, $act), 'activity' => $act];
        }
        $doc = Payroll::export($from, $to, $items, $this->e->now());
        if ($export) {
            $ex = $this->e->s->one('payroll_runs', ['export_id' => $doc['export_id']]); if ($ex) return json_decode((string)$ex['document'], true);   // idempotent: same period + numbers = same document
            $this->e->s->insert('payroll_runs', ['export_id' => $doc['export_id'], 'period_from' => $from, 'period_to' => $to, 'document' => json_encode($doc, JSON_UNESCAPED_UNICODE), 'created_at' => $this->e->now(), 'created_by' => $c->actor()]);
            $this->e->log($c, 'payroll.export', 'payroll', $doc['export_id'], ['from' => $from, 'to' => $to, 'employees' => $doc['totals']['employees'], 'gross_kop' => $doc['totals']['gross_kop']]);
            try { \Sysfera\Hooks::do('hr.payroll.exported', $doc); } catch (\Throwable) {}
        }
        return $doc;
    }
}
