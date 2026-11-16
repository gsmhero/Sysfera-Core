<?php
declare(strict_types=1);
namespace SysferaHr;

/** Shared services of the engine: store, permission checker, bridges, clock, audit helper and input validation. */
final class Env
{
    public const DEFAULTS = ['annual_days' => 28, 'onboarding_days' => 3, 'consent_version' => '1', 'collect_passport' => false, 'holidays' => []];
    public array $cfg;

    public function __construct(public Store $s, public Rbac $rbac, public Bridges $b, public string $key, private \Closure $clock, array $cfg = []) { $this->cfg = $cfg + self::DEFAULTS; }

    public function ts(): int { return ($this->clock)(); }
    public function now(): string { return date('Y-m-d H:i:s', $this->ts()); }
    public function today(): string { return date('Y-m-d', $this->ts()); }

    /** one append-only audit row: who, what, when, details */
    public function log(Ctx $c, string $action, string $entity, string $ref, array $data = []): array
    {
        return $this->s->audit(['ts' => $this->now(), 'actor' => $c->actor(), 'action' => $action, 'entity' => $entity, 'ref' => substr($ref, 0, 64), 'data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    }
    /** an employee timeline entry (hire, transfer, role change, vacation, dismissal...) that is also in the audit chain */
    public function history(Ctx $c, array $emp, string $kind, array $data = [], ?string $at = null): void
    {
        $this->s->insert('status_history', ['employee_id' => (int)$emp['id'], 'kind' => $kind, 'data' => json_encode($data, JSON_UNESCAPED_UNICODE), 'at' => $at ?? $this->now(), 'actor' => $c->actor()]);
        $this->log($c, 'status.' . $kind, 'employee', (string)$emp['uuid'], $data);
    }
    public function notifyPerm(string $perm, string $text): void
    {
        $seen = [];
        foreach ($this->s->find('user_roles') as $ur) { $u = (int)$ur['user_id']; if (isset($seen[$u])) continue; $seen[$u] = true; if (Rbac::matches($this->rbac->permsOf($u), $perm)) { try { $this->b->msg->notify($u, $text); } catch (\Throwable) {} } }
    }

    /* ------------------------------ input helpers ------------------------------ */
    public function str(array $in, string $k, int $max = 200, bool $req = false): string
    {
        $v = trim((string)($in[$k] ?? '')); if ($req && $v === '') throw new Err('missing_field', "$k is required", 422);
        return strlen($v) > $max ? (function_exists('mb_substr') ? mb_substr($v, 0, $max) : substr($v, 0, $max)) : $v;
    }
    public function date(array $in, string $k, bool $req = false): ?string
    {
        $v = trim((string)($in[$k] ?? '')); if ($v === '') { if ($req) throw new Err('missing_field', "$k is required", 422); return null; }
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m) || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) throw new Err('bad_date', "$k must be YYYY-MM-DD", 422);
        return $v;
    }
    public function dt(array $in, string $k): string
    {
        $v = str_replace('T', ' ', trim((string)($in[$k] ?? '')));
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2}) ([01]\d|2[0-3]):([0-5]\d)(:\d{2})?$/', $v, $m) || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) throw new Err('bad_date', "$k must be YYYY-MM-DD HH:MM", 422);
        return substr($v, 0, 16) . ':00';
    }
    public function email(string $v, bool $req = false): string
    {
        $v = strtolower(trim($v)); if ($v === '') { if ($req) throw new Err('missing_field', 'email is required', 422); return ''; }
        if (!filter_var($v, FILTER_VALIDATE_EMAIL)) throw new Err('bad_email', 'invalid e-mail', 422); return $v;
    }
    public function row(string $table, string $uuid): array
    {
        $r = $this->s->one($table, ['uuid' => strtolower($uuid)]); if (!$r) throw new Err('not_found', "$table not found", 404); return $r;
    }
    public function emp(string $uuid): array { return $this->row('employees', $uuid); }
    public function empOfUser(int $uid): ?array { return $this->s->one('employees', ['user_id' => $uid]); }
    public function seal(string $plain): string { return Crypt::seal($plain, $this->key); }
    public function open(string $sealed): ?string { return Crypt::open($sealed, $this->key); }
    public function uuid(): string { return Rbac::uuid(); }
    public function uuidOrNew(array $in, string $k = 'uuid'): string
    {
        $u = strtolower(trim((string)($in[$k] ?? ''))); if ($u === '') return $this->uuid();
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $u)) throw new Err('bad_uuid', "$k must be a UUID", 422); return $u;
    }
}
