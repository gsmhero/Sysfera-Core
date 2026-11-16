<?php
declare(strict_types=1);
namespace SysferaHr;

use Sysfera\Db;

/**
 * The real bridges: they read / write the tables of the other plugins only when those plugins are installed (every method degrades to a no-op),
 * and never edit those plugins' code. Goods issue and Accounting are "coming": Accounting reads the payroll export (see Leave::payroll and the hook hr.payroll.exported).
 */
final class DbBridges
{
    public static function make(Db $db, string $storageDir, callable $client): Bridges
    {
        return new Bridges(new DbIdentity($db), new DbWarehouse($db), new MsgNotifier($db, $client), new DbMissions($db), new ActivityPoster($client), new DiskFiles($storageDir));
    }
    public static function has(Db $db, string $table): bool
    {
        static $tables = null; $tables ??= array_map('strtolower', $db->tables());
        return in_array(strtolower($db->prefix . $table), $tables, true);
    }
}

final class DbIdentity implements Identity
{
    public function __construct(private Db $db) {}
    public function exists(int $uid): bool { return (bool)$this->db->val('SELECT 1 FROM {p}users WHERE id = ?', [$uid]); }
    public function findByEmail(string $email): ?int { $v = $this->db->val('SELECT id FROM {p}users WHERE email = ?', [strtolower($email)]); return $v === null ? null : (int)$v; }
    public function name(int $uid): string { return (string)($this->db->val('SELECT name FROM {p}users WHERE id = ?', [$uid]) ?? ''); }
    /** a blocked account has no session any more: Auth::user() only accepts status = active on every request. The last active administrator is never blocked. */
    public function block(int $uid): bool
    {
        $u = $this->db->row('SELECT role, status FROM {p}users WHERE id = ?', [$uid]); if (!$u) return true;
        if ($u['role'] === 'admin' && (int)$this->db->val("SELECT COUNT(*) FROM {p}users WHERE role = 'admin' AND status = 'active' AND id <> ?", [$uid]) === 0) return false;
        $this->db->q("UPDATE {p}users SET status = 'blocked' WHERE id = ?", [$uid]);
        return true;
    }
}

/** Goods issue (gi_staff = who may use the warehouse app; gi_sessions / gi_devices = its bound devices). */
final class DbWarehouse implements WarehouseAccess
{
    public function __construct(private Db $db) {}
    private function on(): bool { return DbBridges::has($this->db, 'gi_staff'); }

    public function sync(int $uid, ?array $proj, ?int $warehouseId): string
    {
        if (!$this->on()) return 'absent';
        if ($proj === null) { $n = $this->revoke($uid); return $n ? 'removed' : 'absent'; }
        if ($proj['role'] === 'operator') {
            if (!$warehouseId || !$this->db->val('SELECT 1 FROM {p}gi_warehouses WHERE id = ? AND active = 1', [$warehouseId])) { $this->revoke($uid); return 'skipped_no_warehouse'; }
            $this->db->q('INSERT INTO {p}gi_staff (user_id, role, warehouse_id, perms) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE role = VALUES(role), warehouse_id = VALUES(warehouse_id), perms = VALUES(perms)', [$uid, 'operator', $warehouseId, $proj['perms']]);
        } else {
            $this->db->q('INSERT INTO {p}gi_staff (user_id, role, warehouse_id, perms) VALUES (?,?,NULL,?) ON DUPLICATE KEY UPDATE role = VALUES(role), warehouse_id = NULL, perms = VALUES(perms)', [$uid, $proj['role'], $proj['perms']]);
        }
        return 'synced';
    }
    public function revoke(int $uid): int
    {
        if (!$this->on()) return 0; $n = 0;
        $n += $this->db->q('DELETE FROM {p}gi_staff WHERE user_id = ?', [$uid])->rowCount();
        if (DbBridges::has($this->db, 'gi_sessions')) $n += $this->db->q('DELETE FROM {p}gi_sessions WHERE user_id = ?', [$uid])->rowCount();
        if (DbBridges::has($this->db, 'gi_devices')) $n += $this->db->q("UPDATE {p}gi_devices SET status = 'revoked' WHERE user_id = ? AND status <> 'revoked'", [$uid])->rowCount();
        return $n;
    }
    public function staffUserIds(): array { return $this->on() ? array_map('intval', array_column($this->db->rows('SELECT user_id FROM {p}gi_staff'), 'user_id')) : []; }
    /** counted from the Goods issue audit log with the same rules as its KPI page */
    public function kpi(int $uid, string $from, string $to): array
    {
        $z = ['issues' => 0, 'receipts' => 0, 'returns' => 0, 'counts' => 0];
        if (!$this->on() || !DbBridges::has($this->db, 'gi_audit')) return $z;
        $name = (string)$this->db->val('SELECT name FROM {p}users WHERE id = ?', [$uid]); if ($name === '') return $z;
        foreach ($this->db->rows("SELECT action, COUNT(*) n FROM {p}gi_audit WHERE actor = ? AND ts >= ? AND ts < ? + INTERVAL 1 DAY GROUP BY action", ['operator:' . $name, $from . ' 00:00:00', $to]) as $r) {
            $a = (string)$r['action']; $n = (int)$r['n'];
            if ($a === 'issue.act') $z['issues'] += $n; elseif (str_starts_with($a, 'receipt.') && $a !== 'receipt.conflict') $z['receipts'] += $n;
            elseif ($a === 'order.return_requested' || $a === 'order.returned') $z['returns'] += $n; elseif ($a === 'count.submitted' || $a === 'stocktake.submitted') $z['counts'] += $n;
        }
        return $z;
    }
}

final class MsgNotifier implements Notifier
{
    /** @param callable(): mixed $client the Messenger client (hook messenger.client) or null */
    public function __construct(private Db $db, private $client) {}
    public function notify(int $uid, string $text): void
    {
        $c = ($this->client)(); if (!$c || !method_exists($c, 'call') || !$c->ready()) return;
        $c->call('POST', 'send', [], ['to' => $uid . '@' . $c->domain(), 'text' => '[HR] ' . substr($text, 0, 500)], null, 4);
    }
}

final class DbMissions implements MissionsFeed
{
    public function __construct(private Db $db) {}
    public function completed(int $uid, string $from, string $to): int
    {
        if (!DbBridges::has($this->db, 'mis_missions')) return 0;
        return (int)$this->db->val("SELECT COUNT(*) FROM {p}mis_missions WHERE user_id = ? AND status = 'completed' AND updated_at >= ? AND updated_at < ? + INTERVAL 1 DAY", [$uid, $from . ' 00:00:00', $to]);
    }
}

final class ActivityPoster implements ActivityFeed
{
    public function __construct(private $client) {}
    public function publish(string $title, string $body, string $audience): ?string
    {
        $c = ($this->client)(); if (!$c || !method_exists($c, 'call') || !$c->ready()) return null;
        $r = $c->call('POST', 'act.post', [], ['title' => substr($title, 0, 200), 'body' => substr($body, 0, 3000), 'aud' => $audience]);
        return !empty($r['ok']) && isset($r['data']['id']) ? 'activity:' . (int)$r['data']['id'] : null;
    }
}

/** sealed files (signed scans) in core/storage/hr, outside the web root; the name is a hash, the content is already encrypted by the caller */
final class DiskFiles implements Files
{
    public function __construct(private string $dir) {}
    private function path(string $key): string { return rtrim($this->dir, '/\\') . '/' . hash('sha256', $key) . '.bin'; }
    public function put(string $key, string $bytes): void { if (!is_dir($this->dir)) @mkdir($this->dir, 0750, true); file_put_contents($this->path($key), $bytes, LOCK_EX); }
    public function get(string $key): ?string { $p = $this->path($key); return is_file($p) ? (string)file_get_contents($p) : null; }
}
