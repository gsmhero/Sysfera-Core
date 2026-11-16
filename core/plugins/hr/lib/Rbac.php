<?php
declare(strict_types=1);
namespace SysferaHr;

/**
 * The central permission checker. Permissions are dotted strings; a role holds a list of them (wildcards "hr.*" and "*" allowed); a user holds many roles;
 * the effective permissions are the union. Everything else in the plugin asks Ctx::can() - there are no scattered role comparisons.
 * The Goods issue (warehouse app) rights are a projection of the warehouse.* permissions: see warehouse().
 */
final class Rbac
{
    public const CATALOG = [
        'hr.roles.manage', 'hr.vacancies.manage', 'hr.vacancies.approve', 'hr.candidates.manage', 'hr.interviews.feedback',
        'hr.employees.view', 'hr.employees.edit', 'hr.employees.pii', 'hr.docs.manage', 'hr.pay.view', 'hr.pay.manage', 'hr.payroll.run',
        'hr.teams.manage', 'hr.rbac.manage', 'hr.audit.view', 'hr.leave.approve', 'hr.leave.view', 'hr.onboarding.manage', 'hr.offboard', 'hr.export', 'hr.consent.manage',
        'warehouse.receive', 'warehouse.issue', 'warehouse.return', 'warehouse.count', 'warehouse.shelf', 'warehouse.office', 'warehouse.view',
    ];
    /** warehouse.<x> permission => Goods issue operator permission (gi_staff.perms) */
    public const GI_PERMS = ['warehouse.receive' => 'receive', 'warehouse.issue' => 'issue', 'warehouse.return' => 'return', 'warehouse.count' => 'count', 'warehouse.shelf' => 'shelf'];

    /** roles created on first use; code => [en, ru, permissions] */
    public const BUILTIN = [
        'hr_admin' => ['HR administrator', 'Администратор кадров', ['hr.*']],
        'hr_recruiter' => ['Recruiter', 'Рекрутер', ['hr.vacancies.manage', 'hr.candidates.manage', 'hr.interviews.feedback', 'hr.employees.view']],
        'hr_interviewer' => ['Interviewer', 'Интервьюер', ['hr.interviews.feedback']],
        'manager' => ['Manager', 'Руководитель', ['hr.employees.view', 'hr.leave.view', 'hr.interviews.feedback']],
        'accountant' => ['Accountant', 'Бухгалтер', ['hr.employees.view', 'hr.pay.view', 'hr.payroll.run']],
        'warehouse_operator' => ['Warehouse operator', 'Оператор склада', ['warehouse.receive', 'warehouse.issue', 'warehouse.return', 'warehouse.count', 'warehouse.shelf']],
        'warehouse_office' => ['Warehouse office', 'Офис склада', ['warehouse.office']],
        'warehouse_viewer' => ['Warehouse viewer', 'Наблюдатель склада', ['warehouse.view']],
    ];

    public function __construct(private Store $s) {}

    /* ------------------------------ the pure checker ------------------------------ */
    /** @param list<string> $granted */
    public static function matches(array $granted, string $perm): bool
    {
        foreach ($granted as $g) {
            if ($g === '*' || $g === $perm) return true;
            if (str_ends_with($g, '.*') && str_starts_with($perm, substr($g, 0, -1))) return true;
        }
        return false;
    }
    public static function valid(string $p): bool { return in_array($p, self::CATALOG, true) || $p === '*' || (str_ends_with($p, '.*') && count(array_filter(self::CATALOG, fn($c) => str_starts_with($c, substr($p, 0, -1)))) > 0); }

    /** Goods issue projection: office > operator > viewer; null = no warehouse app access. perms: '' = all five, as in gi_staff.perms
     * @return array{role:string, perms:string}|null */
    public static function warehouse(array $perms): ?array
    {
        $all = array_keys(self::GI_PERMS);
        $mine = array_values(array_filter($all, fn($p) => self::matches($perms, $p)));
        if (self::matches($perms, 'warehouse.office')) return ['role' => 'office', 'perms' => ''];
        if ($mine) return ['role' => 'operator', 'perms' => count($mine) === count($all) ? '' : implode(',', array_map(fn($p) => self::GI_PERMS[$p], $mine))];
        if (self::matches($perms, 'warehouse.view')) return ['role' => 'viewer', 'perms' => ''];
        return null;
    }

    /* ------------------------------ roles and grants (state in the store, history in the audit) ------------------------------ */
    public function ensureBuiltin(): void
    {
        foreach (self::BUILTIN as $code => [$en, $ru, $perms]) {
            if ($this->s->one('roles', ['code' => $code])) continue;
            $this->s->insert('roles', ['uuid' => self::uuid(), 'code' => $code, 'name' => $en, 'name_ru' => $ru, 'descr' => '', 'perms' => json_encode($perms), 'builtin' => 1, 'active' => 1, 'created_at' => date('Y-m-d H:i:s')]);
        }
    }
    public function rolePerms(array $role): array { return array_values(array_filter((array)json_decode((string)($role['perms'] ?? '[]'), true), 'is_string')); }

    /** @return list<string> */
    public function permsOf(int $uid): array
    {
        $out = [];
        foreach ($this->s->find('user_roles', ['user_id' => $uid]) as $ur) {
            $r = $this->s->one('roles', ['id' => (int)$ur['role_id'], 'active' => 1]);
            if ($r) foreach ($this->rolePerms($r) as $p) $out[$p] = true;
        }
        return array_keys($out);
    }
    public function rolesOf(int $uid): array
    {
        $out = []; foreach ($this->s->find('user_roles', ['user_id' => $uid]) as $ur) { $r = $this->s->one('roles', ['id' => (int)$ur['role_id']]); if ($r) $out[] = $r; } return $out;
    }
    public function ctx(?int $uid, string $name, bool $root, ?int $employeeId = null, string $kind = 'user'): Ctx
    {
        return new Ctx($uid, $name, $uid ? $this->permsOf($uid) : [], $root, $employeeId, $kind);
    }
    public function can(int $uid, string $perm, bool $root = false): bool { return $root || self::matches($this->permsOf($uid), $perm); }

    public static function uuid(): string
    {
        $b = random_bytes(16); $b[6] = chr((ord($b[6]) & 0x0f) | 0x40); $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }
}
