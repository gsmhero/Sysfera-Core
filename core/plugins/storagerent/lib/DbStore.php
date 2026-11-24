<?php
declare(strict_types=1);
namespace SysferaStoragerent;

/** Store on the platform database. Table and column names are checked against a whitelist / pattern; values are always bound. */
final class DbStore implements Store
{
    public const TABLES = ['warehouses', 'units', 'tenants', 'contracts', 'contract_units', 'contract_versions', 'invoices', 'invoice_items', 'payments', 'allocations', 'adjustments',
        'meters', 'readings', 'documents', 'templates', 'notify_log', 'access_log', 'requests', 'audit', 'listings', 'listing_photos', 'remote_cache', 'favorites'];
    private int $depth = 0;

    public function __construct(private \Sysfera\Db $db) {}

    private function tbl(string $t): string
    {
        if (!in_array($t, self::TABLES, true)) throw new \InvalidArgumentException("unknown table $t");
        return '{p}sr_' . $t;
    }
    private function col(string $c): string
    {
        if (!preg_match('/^[a-z_][a-z0-9_]*$/', $c)) throw new \InvalidArgumentException("bad column $c");
        return '`' . $c . '`';
    }
    private function where(array $w, array &$params): string
    {
        $parts = [];
        foreach ($w as $k => $v) {
            if ($v === null) { $parts[] = $this->col($k) . ' IS NULL'; continue; }
            if (is_array($v)) {
                if (!$v) { $parts[] = '0'; continue; }
                $parts[] = $this->col($k) . ' IN (' . implode(',', array_fill(0, count($v), '?')) . ')'; foreach ($v as $x) $params[] = $x; continue;
            }
            $parts[] = $this->col($k) . ' = ?'; $params[] = $v;
        }
        return $parts ? ' WHERE ' . implode(' AND ', $parts) : '';
    }

    public function insert(string $table, array $row): int
    {
        unset($row['id']); $cols = array_map(fn($c) => $this->col($c), array_keys($row));
        $this->db->q('INSERT INTO ' . $this->tbl($table) . ' (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($row), '?')) . ')', array_values($row));
        return $this->db->insertId();
    }
    public function update(string $table, int $id, array $row): void
    {
        unset($row['id']); if (!$row) return;
        $set = implode(',', array_map(fn($c) => $this->col($c) . ' = ?', array_keys($row)));
        $this->db->q('UPDATE ' . $this->tbl($table) . " SET $set WHERE id = ?", [...array_values($row), $id]);
    }
    public function delete(string $table, int $id): void { $this->db->q('DELETE FROM ' . $this->tbl($table) . ' WHERE id = ?', [$id]); }
    public function find(string $table, array $where = [], string $order = 'id', int $limit = 100000): array
    {
        $p = []; $w = $this->where($where, $p);
        [$c, $d] = array_pad(explode(' ', trim($order)), 2, 'ASC'); $d = strtoupper($d) === 'DESC' ? 'DESC' : 'ASC';
        return $this->db->rows('SELECT * FROM ' . $this->tbl($table) . $w . ' ORDER BY ' . $this->col($c) . " $d LIMIT " . max(1, $limit), $p);
    }
    public function one(string $table, array $where): ?array { return $this->find($table, $where, 'id', 1)[0] ?? null; }
    public function atomic(callable $fn): mixed
    {
        $pdo = $this->db->pdo(); $outer = $this->depth === 0; $this->depth++;
        if ($outer) $pdo->beginTransaction();
        try { $r = $fn(); if ($outer) $pdo->commit(); return $r; }
        catch (\Throwable $e) { if ($outer && $pdo->inTransaction()) $pdo->rollBack(); throw $e; }
        finally { $this->depth--; }
    }
}
