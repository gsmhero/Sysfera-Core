<?php
declare(strict_types=1);
namespace SysferaHr;

/**
 * Integration ports. The HR engine talks to the rest of the platform only through these interfaces, so Goods issue, Accounting, Messenger, Missions and the
 * Activity feed plug in (or are absent) without touching the engine. DbBridges.php holds the MySQL/hook implementations; tests use Null/recording ones.
 */
interface Identity
{
    public function exists(int $uid): bool;
    public function findByEmail(string $email): ?int;
    public function name(int $uid): string;
    /** block the account (no new sessions; running ones die on the next request). false = refused (the last administrator) */
    public function block(int $uid): bool;
}
interface WarehouseAccess
{
    /** make the Goods issue staff row match the projection ($proj null = no access). Returns 'synced' | 'removed' | 'skipped_no_warehouse' | 'absent' */
    public function sync(int $uid, ?array $proj, ?int $warehouseId): string;
    /** remove every Goods issue access of the user (staff row, sessions, devices). Returns the number of things removed */
    public function revoke(int $uid): int;
    /** user ids that still hold Goods issue access */
    public function staffUserIds(): array;
    /** KPI feed for payroll: {issues, receipts, returns, counts} of the user in the period */
    public function kpi(int $uid, string $from, string $to): array;
}
interface Notifier { public function notify(int $uid, string $text): void; }
interface MissionsFeed { public function completed(int $uid, string $from, string $to): int; }
interface ActivityFeed { public function publish(string $title, string $body, string $audience): ?string; }
interface Files { public function put(string $key, string $bytes): void; public function get(string $key): ?string; }

final class Bridges
{
    public function __construct(public Identity $id, public WarehouseAccess $wh, public Notifier $msg, public MissionsFeed $mis, public ActivityFeed $feed, public Files $files) {}

    /** do-nothing implementations (Goods issue / Messenger / Missions not installed; also the base of the test doubles) */
    public static function null(): self
    {
        return new self(
            new class implements Identity { public function exists(int $u): bool { return true; } public function findByEmail(string $e): ?int { return null; } public function name(int $u): string { return "user$u"; } public function block(int $u): bool { return true; } },
            new class implements WarehouseAccess { public function sync(int $u, ?array $p, ?int $w): string { return 'absent'; } public function revoke(int $u): int { return 0; } public function staffUserIds(): array { return []; } public function kpi(int $u, string $f, string $t): array { return ['issues' => 0, 'receipts' => 0, 'returns' => 0, 'counts' => 0]; } },
            new class implements Notifier { public function notify(int $u, string $t): void {} },
            new class implements MissionsFeed { public function completed(int $u, string $f, string $t): int { return 0; } },
            new class implements ActivityFeed { public function publish(string $t, string $b, string $a): ?string { return null; } },
            new class implements Files { private array $d = []; public function put(string $k, string $b): void { $this->d[$k] = $b; } public function get(string $k): ?string { return $this->d[$k] ?? null; } },
        );
    }
}
