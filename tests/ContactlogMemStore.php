<?php
declare(strict_types=1);
/** In-memory Store for the Contact Log tests (the real one is SysferaContactlog\DbStore). Same unique keys as the migration. */
final class ContactlogMemStore implements SysferaContactlog\Store
{
    public array $t = []; private array $seq = [];
    private const UNIQ = ['profiles' => [['user_id']]];

    public function insert(string $table, array $row): int
    {
        $id = $this->seq[$table] = ($this->seq[$table] ?? 0) + 1; $row['id'] = $id;
        foreach (self::UNIQ[$table] ?? [] as $k) {
            $mine = array_map(fn($c) => $row[$c] ?? null, $k);
            foreach ($this->t[$table] ?? [] as $o) if ($mine === array_map(fn($c) => $o[$c] ?? null, $k)) throw new RuntimeException("duplicate key $table " . implode(',', $k));
        }
        $this->t[$table][$id] = $row; return $id;
    }
    public function update(string $table, int $id, array $row): void
    {
        if (!isset($this->t[$table][$id])) throw new RuntimeException("no row $table#$id");
        $this->t[$table][$id] = $row + $this->t[$table][$id];
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
