<?php
declare(strict_types=1);
/** In-memory Store for the HR tests (the real one is SysferaHr\DbStore). Also: a recording set of bridges. */
final class HrMemStore implements SysferaHr\Store
{
    public array $t = []; private array $seq = [];
    public const NO_DELETE = ['audit', 'status_history', 'consents', 'stage_log', 'vacancy_versions'];

    public function insert(string $table, array $row): int
    {
        $id = $this->seq[$table] = ($this->seq[$table] ?? 0) + 1; $row['id'] = $id;
        $uniq = ['employees' => [['email'], ['user_id']], 'roles' => [['code']], 'user_roles' => [['user_id', 'role_id']], 'team_members' => [['team_id', 'employee_id']], 'tasks' => [['employee_id', 'phase', 'code']], 'payroll_runs' => [['export_id']], 'feedback' => [['interview_id']], 'candidates' => [['vacancy_id', 'email']]][$table] ?? [];
        if (isset($row['uuid'])) $uniq[] = ['uuid'];
        foreach ($this->t[$table] ?? [] as $o) foreach ($uniq as $k) {
            $mine = array_map(fn($c) => $row[$c] ?? null, $k); if (in_array(null, $mine, true) || in_array('', $mine, true)) continue;
            if ($mine === array_map(fn($c) => $o[$c] ?? null, $k)) throw new RuntimeException("duplicate key $table " . implode(',', $k));
        }
        $this->t[$table][$id] = $row; return $id;
    }
    public function update(string $table, int $id, array $row): void { if (!isset($this->t[$table][$id])) throw new RuntimeException("no row $table#$id"); if ($table === 'audit') throw new RuntimeException('audit is append-only'); $this->t[$table][$id] = $row + $this->t[$table][$id]; }
    public function delete(string $table, int $id): void { if (in_array($table, self::NO_DELETE, true)) throw new RuntimeException("$table is append-only"); unset($this->t[$table][$id]); }
    private function match(array $r, array $w): bool
    {
        foreach ($w as $k => $v) { $x = $r[$k] ?? null; if (is_array($v)) { if (!in_array($x, $v, false)) return false; } elseif ($v === null) { if ($x !== null) return false; } elseif ((string)$x !== (string)$v) return false; }
        return true;
    }
    public function find(string $table, array $where = [], string $order = 'id', int $limit = 1000): array
    {
        $rows = array_values(array_filter($this->t[$table] ?? [], fn($r) => $this->match($r, $where)));
        [$col, $dir] = array_pad(explode(' ', $order), 2, 'ASC'); usort($rows, fn($a, $b) => ($dir === 'DESC' ? -1 : 1) * (($a[$col] ?? 0) <=> ($b[$col] ?? 0)));
        return array_slice($rows, 0, $limit);
    }
    public function one(string $table, array $where): ?array { return $this->find($table, $where, 'id', 1)[0] ?? null; }
    public function audit(array $e): array
    {
        $rows = $this->t['audit'] ?? []; $prev = $rows ? end($rows)['hash'] : SysferaHr\Audit::GENESIS; $e = SysferaHr\Audit::seal($e, $prev);
        $e['id'] = count($rows) + 1; $this->t['audit'][$e['id']] = $e; return $e;
    }
    public function atomic(callable $fn): mixed { $snap = $this->t; $seq = $this->seq; try { return $fn(); } catch (Throwable $x) { $this->t = $snap; $this->seq = $seq; throw $x; } }
    public function audits(string $action): array { return array_values(array_filter($this->t['audit'] ?? [], fn($r) => $r['action'] === $action)); }
}

/** bridges that remember what the engine asked of the rest of the platform */
final class HrSpy
{
    public array $notes = [], $posts = [], $synced = [], $revoked = [], $blocked = [], $users = [1 => 'admin', 2 => 'oleg', 3 => 'maria', 4 => 'ivan', 5 => 'anna'];
    public array $gi = [];   // uid => proj currently in gi_staff
    public array $kpi = [], $missions = [];
    public bool $lastAdmin = false;

    public function bridges(): SysferaHr\Bridges
    {
        $s = $this; $nul = SysferaHr\Bridges::null();
        return new SysferaHr\Bridges(
            new class($s) implements SysferaHr\Identity { public function __construct(private HrSpy $s) {} public function exists(int $u): bool { return isset($this->s->users[$u]); } public function findByEmail(string $e): ?int { return null; } public function name(int $u): string { return $this->s->users[$u] ?? ''; }
                public function block(int $u): bool { if ($this->s->lastAdmin && $u === 1) return false; $this->s->blocked[] = $u; return true; } },
            new class($s) implements SysferaHr\WarehouseAccess { public function __construct(private HrSpy $s) {}
                public function sync(int $u, ?array $p, ?int $w): string { $this->s->synced[] = [$u, $p, $w]; if ($p === null) { unset($this->s->gi[$u]); return 'removed'; } if ($p['role'] === 'operator' && !$w) return 'skipped_no_warehouse'; $this->s->gi[$u] = $p + ['warehouse' => $w]; return 'synced'; }
                public function revoke(int $u): int { $n = isset($this->s->gi[$u]) ? 1 : 0; unset($this->s->gi[$u]); $this->s->revoked[] = $u; return $n; }
                public function staffUserIds(): array { return array_keys($this->s->gi); }
                public function kpi(int $u, string $f, string $t): array { return $this->s->kpi[$u] ?? ['issues' => 0, 'receipts' => 0, 'returns' => 0, 'counts' => 0]; } },
            new class($s) implements SysferaHr\Notifier { public function __construct(private HrSpy $s) {} public function notify(int $u, string $t): void { $this->s->notes[] = [$u, $t]; } },
            new class($s) implements SysferaHr\MissionsFeed { public function __construct(private HrSpy $s) {} public function completed(int $u, string $f, string $t): int { return $this->s->missions[$u] ?? 0; } },
            new class($s) implements SysferaHr\ActivityFeed { public function __construct(private HrSpy $s) {} public function publish(string $t, string $b, string $a): ?string { $this->s->posts[] = [$t, $a, $b]; return 'post-' . count($this->s->posts); } },
            $nul->files,
        );
    }
}
