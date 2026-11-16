<?php
declare(strict_types=1);
namespace SysferaHr;

/** Who is acting: resolved once by Rbac::ctx(). All permission questions go through can() / need() and nowhere else. */
final class Ctx
{
    /** @param list<string> $perms effective permissions (union of the roles) */
    public function __construct(public readonly ?int $uid, public readonly string $name, public readonly array $perms, public readonly bool $root = false, public readonly ?int $employeeId = null, public readonly string $kind = 'user') {}

    public function can(string $perm): bool { return $this->root || Rbac::matches($this->perms, $perm); }
    public function need(string $perm): void { if (!$this->can($perm)) throw new Err('forbidden', 'permission required: ' . $perm, 403); }
    public function actor(): string { return $this->kind . ':' . $this->name; }
    public function isSelf(?array $employee): bool { return $employee !== null && $this->employeeId !== null && (int)$employee['id'] === $this->employeeId; }
}
