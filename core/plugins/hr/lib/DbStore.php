<?php
declare(strict_types=1);
namespace SysferaHr;

use Sysfera\Db;

/** MySQL storage of the HR engine. Table and column names are checked against a pattern (they come from code, never from requests). The audit table has an INSERT-only path. */
final class DbStore implements Store
{
    public const TABLES = ['roles', 'user_roles', 'teams', 'team_members', 'schedules', 'vacancies', 'vacancy_versions', 'employees', 'candidates', 'stage_log', 'interviews', 'feedback', 'status_history', 'docs', 'pay', 'leave', 'tasks', 'consents', 'payroll_runs', 'audit', 'hub'];
    /** tables that are never deleted from */
    private const KEEP = ['audit', 'status_history', 'consents', 'stage_log', 'vacancy_versions', 'docs', 'payroll_runs', 'employees'];
    private int $depth = 0;

    public function __construct(private Db $db) {}

    private function t(string $table): string { if (!in_array($table, self::TABLES, true)) throw new \InvalidArgumentException("unknown table $table"); return '{p}hr_' . $table; }
    private function c(string $col): string { if (!preg_match('/^[a-z_][a-z0-9_]*$/', $col)) throw new \InvalidArgumentException("bad column $col"); return '`' . $col . '`'; }

    public function insert(string $table, array $row): int
    {
        $cols = array_keys($row);
        $this->db->q('INSERT INTO ' . $this->t($table) . ' (' . implode(',', array_map([$this, 'c'], $cols)) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')', array_values($row));
        return $this->db->insertId();
    }
    public function update(string $table, int $id, array $row): void
    {
        if ($table === 'audit') throw new \LogicException('the audit log is append-only');
        if (!$row) return;
        $this->db->q('UPDATE ' . $this->t($table) . ' SET ' . implode(',', array_map(fn($c) => $this->c($c) . ' = ?', array_keys($row))) . ' WHERE id = ?', [...array_values($row), $id]);
    }
    public function delete(string $table, int $id): void
    {
        if (in_array($table, self::KEEP, true)) throw new \LogicException("$table is never deleted from");
        $this->db->q('DELETE FROM ' . $this->t($table) . ' WHERE id = ?', [$id]);
    }
    private function where(array $where, array &$args): string
    {
        $w = [];
        foreach ($where as $k => $v) {
            if ($v === null) $w[] = $this->c($k) . ' IS NULL';
            elseif (is_array($v)) { if (!$v) { $w[] = '1 = 0'; continue; } $w[] = $this->c($k) . ' IN (' . implode(',', array_fill(0, count($v), '?')) . ')'; array_push($args, ...array_values($v)); }
            else { $w[] = $this->c($k) . ' = ?'; $args[] = $v; }
        }
        return $w ? ' WHERE ' . implode(' AND ', $w) : '';
    }
    public function find(string $table, array $where = [], string $order = 'id', int $limit = 1000): array
    {
        if (!preg_match('/^([a-z_]+)( (ASC|DESC))?$/', $order, $m)) throw new \InvalidArgumentException('bad order'); $args = [];
        return $this->db->rows('SELECT * FROM ' . $this->t($table) . $this->where($where, $args) . ' ORDER BY ' . $this->c($m[1]) . ($m[3] ?? '') . ' LIMIT ' . max(1, min(1000000, $limit)), $args);
    }
    public function one(string $table, array $where): ?array { return $this->find($table, $where, 'id', 1)[0] ?? null; }

    /** append to the hash chain; the previous hash is read under a lock so two requests cannot fork the chain */
    public function audit(array $e): array
    {
        $lock = 'hr_audit_' . substr(sha1($this->db->prefix), 0, 20);
        $this->db->q('SELECT GET_LOCK(?, 10)', [$lock]);
        try {
            $prev = (string)($this->db->val('SELECT hash FROM {p}hr_audit ORDER BY id DESC LIMIT 1') ?? Audit::GENESIS);
            $e = Audit::seal($e, $prev);
            $e['id'] = $this->insert('audit', ['ts' => $e['ts'], 'actor' => $e['actor'], 'action' => $e['action'], 'entity' => $e['entity'], 'ref' => $e['ref'], 'data' => $e['data'], 'prev_hash' => $e['prev_hash'], 'hash' => $e['hash']]);
            return $e;
        } finally { $this->db->q('SELECT RELEASE_LOCK(?)', [$lock]); }
    }
    public function atomic(callable $fn): mixed
    {
        $pdo = $this->db->pdo();
        if ($this->depth++ > 0 || $pdo->inTransaction()) { try { return $fn(); } finally { $this->depth--; } }   // nested: the outer transaction decides
        $pdo->beginTransaction();
        try { $r = $fn(); $pdo->commit(); return $r; }
        catch (\Throwable $x) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $x; }
        finally { $this->depth = 0; }
    }
}
