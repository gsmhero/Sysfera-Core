<?php
declare(strict_types=1);
namespace Sysfera;

/**
 * Generic access to one of the SHARED tables (contacts, orders, invoices). Every plugin uses the same records,
 * so a client created in CRM is the same client the Warehouse and Accounting see. Fires "<event>.created/updated/deleted".
 * `owner_id` separates data of different users of the same install (null = shared / created by the system).
 */
class Repo
{
    public function __construct(protected Db $db, protected string $table, protected array $fields, protected string $event, protected array $json = ['meta'])
    {
    }

    public function find(int $id): ?array { return $this->decode($this->db->row("SELECT * FROM {p}{$this->table} WHERE id = ?", [$id])); }

    /** @param array<string,mixed> $where column => value (exact match; null matches NULL) */
    public function list(array $where = [], int $limit = 100, int $offset = 0, string $order = 'id DESC'): array
    {
        [$w, $a] = $this->where($where);
        if (!preg_match('/^[a-z_]+( (ASC|DESC))?$/i', $order)) $order = 'id DESC';
        $limit = max(1, min(1000, $limit)); $offset = max(0, $offset);
        return array_map([$this, 'decode'], $this->db->rows("SELECT * FROM {p}{$this->table}$w ORDER BY $order LIMIT $limit OFFSET $offset", $a));
    }

    public function count(array $where = []): int
    {
        [$w, $a] = $this->where($where);
        return (int)$this->db->val("SELECT COUNT(*) FROM {p}{$this->table}$w", $a);
    }

    /** Free-text search over the given columns. */
    public function search(string $text, array $columns, array $where = [], int $limit = 50): array
    {
        [$w, $a] = $this->where($where);
        $like = []; foreach ($columns as $c) { if (in_array($c, $this->fields, true)) { $like[] = "$c LIKE ?"; $a[] = '%' . $text . '%'; } }
        if (!$like) return [];
        $w .= ($w ? ' AND ' : ' WHERE ') . '(' . implode(' OR ', $like) . ')';
        return array_map([$this, 'decode'], $this->db->rows("SELECT * FROM {p}{$this->table}$w ORDER BY id DESC LIMIT " . max(1, min(200, $limit)), $a));
    }

    public function create(array $data): int
    {
        $d = $this->clean($data);
        $cols = array_keys($d);
        $this->db->q("INSERT INTO {p}{$this->table} (" . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')', array_values($d));
        $id = $this->db->insertId();
        Hooks::do($this->event . '.created', $id, $this->find($id));
        return $id;
    }

    public function update(int $id, array $data): void
    {
        $d = $this->clean($data);
        if (!$d) return;
        $this->db->q("UPDATE {p}{$this->table} SET " . implode(', ', array_map(fn($c) => "$c = ?", array_keys($d))) . ' WHERE id = ?', [...array_values($d), $id]);
        Hooks::do($this->event . '.updated', $id, $this->find($id));
    }

    public function delete(int $id): void
    {
        $old = $this->find($id);
        $this->db->q("DELETE FROM {p}{$this->table} WHERE id = ?", [$id]);
        Hooks::do($this->event . '.deleted', $id, $old);
    }

    protected function clean(array $data): array
    {
        $out = [];
        foreach ($this->fields as $f) {
            if (!array_key_exists($f, $data)) continue;
            $v = $data[$f];
            $out[$f] = (in_array($f, $this->json, true) && is_array($v)) ? json_encode($v, JSON_UNESCAPED_UNICODE) : $v;
        }
        return $out;
    }

    private function where(array $where): array
    {
        $parts = []; $a = [];
        foreach ($where as $c => $v) {
            if (!in_array($c, $this->fields, true) && $c !== 'id') continue;
            if ($v === null) $parts[] = "$c IS NULL"; else { $parts[] = "$c = ?"; $a[] = $v; }
        }
        return [$parts ? ' WHERE ' . implode(' AND ', $parts) : '', $a];
    }

    protected function decode(?array $r): ?array
    {
        if (!$r) return null;
        foreach ($this->json as $j) if (isset($r[$j]) && is_string($r[$j])) $r[$j] = json_decode($r[$j], true) ?? [];
        return $r;
    }
}
