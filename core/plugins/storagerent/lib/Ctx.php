<?php
declare(strict_types=1);
namespace SysferaStoragerent;

/**
 * Who is acting and what they may do. Built once per request from the platform user:
 *  - admin      : a Sysfera administrator or a user with the capability storagerent.admin. Everything, including tariffs (utility rates, notification templates, settings);
 *  - manager    : capability storagerent.manager. Everything operational (units, tenants, contracts, invoices, payments) but not tariffs and settings;
 *  - accountant : capability storagerent.accountant. Read access to units, tenants, contracts and all financial data, and exports; changes nothing;
 *  - tenant     : a Sysfera user linked to a tenant record. Sees only their own contracts, invoices, payments; can upload a payment confirmation and file requests;
 *  - none       : any other user; only the Offers tab.
 * Ctx::can() is the single check the service and the pages use.
 */
final class Ctx
{
    public const PERMS = [
        'admin'      => ['view', 'write', 'finance', 'export', 'tariff', 'settings', 'audit'],
        'manager'    => ['view', 'write', 'finance', 'export', 'audit'],
        'accountant' => ['view', 'finance', 'export'],
        'tenant'     => ['portal'],
        'none'       => [],
    ];

    public function __construct(public readonly int $uid, public readonly string $role = 'none', public readonly int $tenantId = 0) {}

    public static function guest(): self { return new self(0, 'none', 0); }
    public static function for(int $uid, bool $platformAdmin, array $caps, ?int $tenantId): self
    {
        if ($uid <= 0) return self::guest();
        $role = $platformAdmin || in_array('storagerent.admin', $caps, true) ? 'admin'
            : (in_array('storagerent.manager', $caps, true) ? 'manager' : (in_array('storagerent.accountant', $caps, true) ? 'accountant' : ($tenantId ? 'tenant' : 'none')));
        return new self($uid, $role, (int)$tenantId);
    }
    public function can(string $perm): bool { return in_array($perm, self::PERMS[$this->role] ?? [], true); }
    public function staff(): bool { return $this->can('view'); }
    /** The tenant record a tenant user may see (0 for staff: no restriction by tenant). */
    public function onlyTenant(): int { return $this->role === 'tenant' ? $this->tenantId : 0; }
    public function require(string $perm): void { if (!$this->can($perm)) throw new Err('forbidden', '', 403); }
    /** May this user see data of the tenant? Staff yes; a tenant only their own. */
    public function seesTenant(int $tenantId): bool { return $this->staff() || ($this->role === 'tenant' && $this->tenantId === $tenantId); }
}
