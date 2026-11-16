<?php
declare(strict_types=1);
namespace SysferaHr;

/** Field-level permissions of the employee profile: one table, one place. */
final class Fields
{
    /** fields the employee may change in their own card */
    public const SELF = ['phone', 'avatar'];
    /** only HR (hr.employees.edit) */
    public const CRITICAL = ['full_name', 'email', 'birth_date', 'department', 'position_role_id', 'team_id', 'emp_type', 'hire_date', 'manager_id', 'warehouse_id', 'schedule_id', 'user_id', 'number'];
    /** need hr.employees.pii; shown to the employee themself */
    public const PII = ['passport'];
    public const EMP_TYPES = ['full_time', 'part_time', 'contract'];

    /** the fields among $changes that the actor may NOT change (empty = allowed) */
    public static function denied(Ctx $c, ?array $emp, array $changes): array
    {
        $bad = [];
        foreach (array_keys($changes) as $f) {
            if (in_array($f, self::PII, true)) { if (!$c->can('hr.employees.pii')) $bad[] = $f; }
            elseif (in_array($f, self::CRITICAL, true)) { if (!$c->can('hr.employees.edit')) $bad[] = $f; }
            elseif (in_array($f, self::SELF, true)) { if (!$c->can('hr.employees.edit') && !$c->isSelf($emp)) $bad[] = $f; }
            else $bad[] = $f;   // an unknown field is never silently accepted
        }
        return $bad;
    }
    public static function mayViewPii(Ctx $c, ?array $emp): bool { return $c->can('hr.employees.pii') || $c->isSelf($emp); }
    public static function mayViewPay(Ctx $c, ?array $emp): bool { return $c->can('hr.pay.view') || $c->isSelf($emp); }
}
