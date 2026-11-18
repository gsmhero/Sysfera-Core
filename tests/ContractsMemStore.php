<?php
declare(strict_types=1);
/** In-memory Store for the Contracts tests (the real one is SysferaContracts\DbStore). Same unique keys as the migration. */
final class ContractsMemStore implements SysferaContracts\Store
{
    public array $t = []; private array $seq = [];
    private const UNIQ = ['remote' => [['remote_id']], 'links' => [['user_id'], ['remote_id']], 'profiles' => [['user_id']], 'categories' => [['name']], 'offers' => [['contract_id', 'user_id']], 'offer_todos' => [['offer_id', 'todo_id']], 'reviews' => [['contract_id', 'reviewer_id']], 'watch' => [['user_id', 'contract_id']], 'invoices' => [['number']], 'pvalues' => [['user_id', 'field_id']]];

    public function insert(string $table, array $row): int
    {
        $id = $this->seq[$table] = ($this->seq[$table] ?? 0) + 1; $row['id'] = $id;
        foreach (self::UNIQ[$table] ?? [] as $k) {
            $mine = array_map(fn($c) => $row[$c] ?? null, $k); if (in_array(null, $mine, true)) continue;
            foreach ($this->t[$table] ?? [] as $o) if ($mine === array_map(fn($c) => $o[$c] ?? null, $k)) throw new RuntimeException("duplicate key $table " . implode(',', $k));
        }
        $this->t[$table][$id] = $row; return $id;
    }
    public function update(string $table, int $id, array $row): void
    {
        if (!isset($this->t[$table][$id])) throw new RuntimeException("no row $table#$id");
        $new = $row + $this->t[$table][$id];
        foreach (self::UNIQ[$table] ?? [] as $k) { $mine = array_map(fn($c) => $new[$c] ?? null, $k); if (in_array(null, $mine, true)) continue; foreach ($this->t[$table] as $oid => $o) if ($oid !== $id && $mine === array_map(fn($c) => $o[$c] ?? null, $k)) throw new RuntimeException("duplicate key $table " . implode(',', $k)); }
        $this->t[$table][$id] = $new;
    }
    public function delete(string $table, int $id): void { unset($this->t[$table][$id]); }
    private function match(array $r, array $w): bool
    {
        foreach ($w as $k => $v) { $x = $r[$k] ?? null; if (is_array($v)) { if (!in_array($x, $v, false)) return false; } elseif ($v === null) { if ($x !== null) return false; } elseif ((string)$x !== (string)$v) return false; }
        return true;
    }
    public function find(string $table, array $where = [], string $order = 'id', int $limit = 5000): array
    {
        $rows = array_values(array_filter($this->t[$table] ?? [], fn($r) => $this->match($r, $where)));
        [$col, $dir] = array_pad(explode(' ', $order), 2, 'ASC'); usort($rows, fn($a, $b) => ($dir === 'DESC' ? -1 : 1) * (($a[$col] ?? 0) <=> ($b[$col] ?? 0)));
        return array_slice($rows, 0, $limit);
    }
    public function one(string $table, array $where): ?array { return $this->find($table, $where, 'id', 1)[0] ?? null; }
    public function atomic(callable $fn): mixed { $snap = $this->t; $seq = $this->seq; try { return $fn(); } catch (Throwable $x) { $this->t = $snap; $this->seq = $seq; throw $x; } }
}
