<?php
declare(strict_types=1);
namespace SysferaWms;

use Sysfera\Db;

/** Documents: create/edit drafts, post (writes the stock ledger), unpost, cancel, create follow-up documents. */
final class Docs
{
    public function __construct(private Db $db, private Stock $stock, private \Closure $setting) {}

    /* ---------- reading ---------- */

    public function find(int $id): ?array
    {
        return $this->db->row('SELECT d.*, w.name AS warehouse, w2.name AS to_warehouse, c.name AS contact FROM {p}wms_docs d
            LEFT JOIN {p}wms_warehouses w ON w.id = d.warehouse_id LEFT JOIN {p}wms_warehouses w2 ON w2.id = d.to_warehouse_id
            LEFT JOIN {p}contacts c ON c.id = d.contact_id WHERE d.id = ?', [$id]);
    }

    public function lines(int $id): array
    {
        return $this->db->rows('SELECT l.*, p.name, p.sku, p.unit, p.barcode, c.name AS cell, c2.name AS to_cell FROM {p}wms_doc_lines l JOIN {p}wms_products p ON p.id = l.product_id
            LEFT JOIN {p}wms_cells c ON c.id = l.cell_id LEFT JOIN {p}wms_cells c2 ON c2.id = l.to_cell_id WHERE l.doc_id = ? ORDER BY l.id', [$id]);
    }

    /** List with filters: type, status, q (number / comment / contact), contact_id. */
    public function list(array $f = [], int $limit = 50, int $offset = 0): array
    {
        [$w, $a] = $this->filter($f);
        $limit = max(1, min(200, $limit)); $offset = max(0, $offset);
        return $this->db->rows("SELECT d.*, w.name AS warehouse, c.name AS contact FROM {p}wms_docs d LEFT JOIN {p}wms_warehouses w ON w.id = d.warehouse_id
            LEFT JOIN {p}contacts c ON c.id = d.contact_id$w ORDER BY d.moment DESC, d.id DESC LIMIT $limit OFFSET $offset", $a);
    }

    public function count(array $f = []): int
    {
        [$w, $a] = $this->filter($f);
        return (int)$this->db->val("SELECT COUNT(*) FROM {p}wms_docs d LEFT JOIN {p}contacts c ON c.id = d.contact_id$w", $a);
    }

    private function filter(array $f): array
    {
        $w = ' WHERE 1=1'; $a = [];
        if (($o = Scope::id()) !== null) { $w .= ' AND d.owner_id = ?'; $a[] = $o; }
        if (!empty($f['type']) && Posting::known((string)$f['type'])) { $w .= ' AND d.type = ?'; $a[] = $f['type']; }
        if (!empty($f['group'])) { $types = array_keys(array_filter(Posting::TYPES, fn($m) => $m[1] === $f['group'])); if ($types) { $w .= ' AND d.type IN (' . implode(',', array_fill(0, count($types), '?')) . ')'; array_push($a, ...$types); } }
        if (!empty($f['status']) && in_array($f['status'], ['draft', 'posted', 'cancelled'], true)) { $w .= ' AND d.status = ?'; $a[] = $f['status']; }
        if (!empty($f['contact_id'])) { $w .= ' AND d.contact_id = ?'; $a[] = (int)$f['contact_id']; }
        if (!empty($f['q'])) { $w .= ' AND (d.number LIKE ? OR d.comment LIKE ? OR c.name LIKE ?)'; $l = '%' . $f['q'] . '%'; array_push($a, $l, $l, $l); }
        return [$w, $a];
    }

    /** Ordered vs. already processed quantity of a purchase / sale order. */
    public function progress(int $orderId): array
    {
        $ordered = (float)$this->db->val('SELECT COALESCE(SUM(qty),0) FROM {p}wms_doc_lines WHERE doc_id = ?', [$orderId]);
        $done = (float)$this->db->val("SELECT COALESCE(SUM(l.qty),0) FROM {p}wms_doc_lines l JOIN {p}wms_docs d ON d.id = l.doc_id WHERE d.parent_id = ? AND d.status = 'posted'", [$orderId]);
        return ['ordered' => $ordered, 'done' => $done];
    }

    /** product_id => quantity still open on an order (ordered minus received/shipped by posted children). */
    public function open(int $orderId, ?int $exceptDoc = null): array
    {
        $open = [];
        foreach ($this->db->rows('SELECT product_id, SUM(qty) q FROM {p}wms_doc_lines WHERE doc_id = ? GROUP BY product_id', [$orderId]) as $r) $open[(int)$r['product_id']] = (float)$r['q'];
        $sql = "SELECT l.product_id, SUM(l.qty) q FROM {p}wms_doc_lines l JOIN {p}wms_docs d ON d.id = l.doc_id WHERE d.parent_id = ? AND d.status = 'posted'";
        $a = [$orderId];
        if ($exceptDoc) { $sql .= ' AND d.id <> ?'; $a[] = $exceptDoc; }
        foreach ($this->db->rows($sql . ' GROUP BY l.product_id', $a) as $r) $open[(int)$r['product_id']] = ($open[(int)$r['product_id']] ?? 0) - (float)$r['q'];
        return array_map(fn($q) => max(0.0, $q), $open);
    }

    public function nextNumber(string $type): string
    {
        $k = 'wms.counter.' . $type;
        $this->db->q('INSERT IGNORE INTO {p}settings (k, v) VALUES (?, ?)', [$k, '0']);
        $this->db->q('UPDATE {p}settings SET v = LAST_INSERT_ID(CAST(v AS UNSIGNED) + 1) WHERE k = ?', [$k]);
        return Posting::prefix($type) . '-' . str_pad((string)(int)$this->db->val('SELECT LAST_INSERT_ID()'), 6, '0', STR_PAD_LEFT);
    }

    /* ---------- writing ---------- */

    /**
     * Create or update a DRAFT. $d: type (new only), moment, warehouse_id, to_warehouse_id, contact_id, parent_id, comment, source, ref.
     * $lines: product_id, qty, price, cell_id, to_cell_id, note. Returns the document id.
     */
    public function save(array $d, array $lines, ?int $id = null, ?int $userId = null): int
    {
        $pdo = $this->db->pdo(); $pdo->beginTransaction();
        try {
            $clean = [];
            foreach ($lines as $l) {
                $p = (int)($l['product_id'] ?? 0); $q = round((float)str_replace(',', '.', (string)($l['qty'] ?? 0)), 3);
                if ($p <= 0 || !$this->db->val('SELECT 1 FROM {p}wms_products WHERE id = ?' . (($o = ((int)($d['owner_id'] ?? 0)) ?: Scope::id()) ? ' AND owner_id = ' . $o : ''), [$p])) continue;
                $clean[] = ['product_id' => $p, 'qty' => $q, 'price' => round((float)str_replace(',', '.', (string)($l['price'] ?? 0)), 2),
                    'cell_id' => ((int)($l['cell_id'] ?? 0)) ?: null, 'to_cell_id' => ((int)($l['to_cell_id'] ?? 0)) ?: null, 'note' => mb_substr(trim((string)($l['note'] ?? '')), 0, 255) ?: null];
            }
            $moment = date('Y-m-d H:i:s', strtotime((string)($d['moment'] ?? '')) ?: time());
            $total = 0.0; foreach ($clean as $l) $total += $l['qty'] * $l['price'];
            $head = [$moment, ((int)($d['warehouse_id'] ?? 0)) ?: null, ((int)($d['to_warehouse_id'] ?? 0)) ?: null, ((int)($d['contact_id'] ?? 0)) ?: null,
                ((int)($d['parent_id'] ?? 0)) ?: null, round($total, 2), mb_substr(trim((string)($d['comment'] ?? '')), 0, 2000) ?: null];
            if ($id === null) {
                $type = (string)($d['type'] ?? '');
                if (!Posting::known($type)) throw new \RuntimeException('type');
                $this->db->q('INSERT INTO {p}wms_docs (type, number, status, moment, warehouse_id, to_warehouse_id, contact_id, parent_id, total, comment, source, ref, created_by, owner_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                    [$type, $this->nextNumber($type), 'draft', ...$head, $d['source'] ?? null, $d['ref'] ?? null, $userId, ((int)($d['owner_id'] ?? 0)) ?: (Scope::id() ?? $userId)]);
                $id = $this->db->insertId();
            } else {
                $cur = $this->find($id);
                if (!$cur || $cur['status'] !== 'draft') throw new \RuntimeException('not_draft');
                $this->db->q('UPDATE {p}wms_docs SET moment=?, warehouse_id=?, to_warehouse_id=?, contact_id=?, parent_id=?, total=?, comment=? WHERE id = ?', [...$head, $id]);
                $this->db->q('DELETE FROM {p}wms_doc_lines WHERE doc_id = ?', [$id]);
            }
            foreach ($clean as $l) $this->db->q('INSERT INTO {p}wms_doc_lines (doc_id, product_id, qty, price, cell_id, to_cell_id, note) VALUES (?,?,?,?,?,?,?)', [$id, $l['product_id'], $l['qty'], $l['price'], $l['cell_id'], $l['to_cell_id'], $l['note']]);
            $pdo->commit();
        } catch (\Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
        return $id;
    }

    /** A follow-up draft for an order: receiving for a purchase order, shipment for a sale order, prefilled with what is still open. */
    public function child(int $parentId, ?int $userId = null): int
    {
        $p = $this->find($parentId) ?? throw new \RuntimeException('missing');
        $type = Posting::childType($p['type']) ?? throw new \RuntimeException('type');
        if ($p['status'] !== 'posted') throw new \RuntimeException('parent_not_posted');
        $open = $this->open($parentId); $lines = [];
        foreach ($this->lines($parentId) as $l) {
            $q = $open[(int)$l['product_id']] ?? 0; if ($q <= 0) continue;
            $take = min($q, (float)$l['qty']); $open[(int)$l['product_id']] = $q - $take;
            $lines[] = ['product_id' => $l['product_id'], 'qty' => $take, 'price' => $l['price']];
        }
        return $this->save(['type' => $type, 'warehouse_id' => $p['warehouse_id'], 'contact_id' => $p['contact_id'], 'parent_id' => $parentId, 'owner_id' => $p['owner_id'] ?? null, 'moment' => date('Y-m-d H:i:s'), 'comment' => null], $lines, null, $userId);
    }

    /** Post: validate, check stock, write ledger rows. @return string[] problems (translation keys, or ['stock:<name>'...]); empty = posted */
    public function post(int $id): array
    {
        $doc = $this->find($id); if (!$doc || $doc['status'] !== 'draft') return ['err_state'];
        $lines = $this->lines($id); $type = $doc['type'];
        $wh = $this->warehouse((int)$doc['warehouse_id']); $to = $this->warehouse((int)$doc['to_warehouse_id']);
        $orig = $lines;
        $open = $doc['parent_id'] ? $this->open((int)$doc['parent_id'], $id) : [];
        [$lines, $open, $bad] = $this->physical($type, $lines, $open);   // sets are taken apart into components, services and downloads never touch stock
        if ($bad) return $bad;
        $problems = Posting::problems($type, $doc, $lines ?: array_map(fn($l) => $l + ['cell_id' => 1, 'to_cell_id' => 1], $orig), (bool)($wh['use_cells'] ?? false), (bool)($to['use_cells'] ?? false));
        if ($problems) return $problems;
        if ($parent = $doc['parent_id'] ? $this->find((int)$doc['parent_id']) : null) { if ($parent['status'] !== 'posted' || $parent['type'] !== Posting::parentType($type)) return ['err_parent']; }

        $ctx = ['open' => $open, 'sys' => []];
        if ($type === 'inventory') foreach ($lines as $l) $ctx['sys'][(int)$l['id']] = $this->stock->onHand((int)$l['product_id'], (int)$doc['warehouse_id'], $l['cell_id'] ? (int)$l['cell_id'] : null, (bool)$l['cell_id']);

        if (($this->setting)('forbid_negative', '1') === '1') {
            foreach (Posting::needs($type, $doc, $lines, $ctx) as $n) {
                $have = $n['kind'] === 'available' ? $this->stock->available($n['product_id'], $n['warehouse_id']) : $this->stock->onHand($n['product_id'], $n['warehouse_id'], $n['cell_id'], (bool)$n['cell_id']);
                if ($type === 'shipment' && $n['kind'] === 'on_hand' && $doc['parent_id']) { /* shipping against a reserve is fine as long as the goods are physically there */ }
                if ($have + 0.0000001 < $n['qty']) { $name = (string)$this->db->val('SELECT name FROM {p}wms_products WHERE id = ?', [$n['product_id']]); return ['stock:' . $name . '|' . $this->fmt($have) . '|' . $this->fmt($n['qty'])]; }
            }
        }

        $pdo = $this->db->pdo(); $pdo->beginTransaction();
        try {
            if ($type === 'inventory') foreach ($ctx['sys'] as $lineId => $sys) $this->db->q('UPDATE {p}wms_doc_lines SET sys_qty = ? WHERE id = ?', [$sys, $lineId]);
            foreach (Posting::moves($type, $doc, $lines, $ctx) as $m)
                $this->db->q('INSERT INTO {p}wms_moves (doc_id, line_id, product_id, warehouse_id, cell_id, on_hand, reserve, expected, moment) VALUES (?,?,?,?,?,?,?,?,?)',
                    [$id, $m['line_id'], $m['product_id'], $m['warehouse_id'], $m['cell_id'], $m['on_hand'], $m['reserve'], $m['expected'], $doc['moment']]);
            $this->db->q("UPDATE {p}wms_docs SET status = 'posted', posted_at = NOW() WHERE id = ?", [$id]);
            $pdo->commit();
        } catch (\Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
        try { \Sysfera\Hooks::do('wms.doc_changed', $id, 'post'); } catch (\Throwable $e) { /* integrations must never break posting */ }
        return [];
    }

    /** Take a posted document back to a draft (its ledger rows are removed). Not possible while follow-up documents are posted. */
    public function unpost(int $id, bool $cancel = false): array
    {
        $doc = $this->find($id); if (!$doc || $doc['status'] === 'draft') return ['err_state'];
        if ($doc['status'] === 'posted' && $this->db->val("SELECT 1 FROM {p}wms_docs WHERE parent_id = ? AND status = 'posted' LIMIT 1", [$id])) return ['err_children'];
        // taking goods back out of stock must not push another document's stock below zero
        if ($doc['status'] === 'posted' && ($this->setting)('forbid_negative', '1') === '1' && in_array($doc['type'], ['receipt', 'receiving', 'transfer'], true)) {
            foreach ($this->db->rows('SELECT m.product_id, m.warehouse_id, SUM(m.on_hand) q FROM {p}wms_moves m WHERE m.doc_id = ? GROUP BY m.product_id, m.warehouse_id HAVING q > 0', [$id]) as $r) {
                if ($this->stock->onHand((int)$r['product_id'], (int)$r['warehouse_id']) - (float)$r['q'] < -0.0000001)
                    return ['stock:' . $this->db->val('SELECT name FROM {p}wms_products WHERE id = ?', [$r['product_id']]) . '|' . $this->fmt($this->stock->onHand((int)$r['product_id'], (int)$r['warehouse_id'])) . '|' . $this->fmt((float)$r['q'])];
            }
        }
        $this->db->q('DELETE FROM {p}wms_moves WHERE doc_id = ?', [$id]);
        $this->db->q('UPDATE {p}wms_docs SET status = ?, posted_at = NULL WHERE id = ?', [$cancel ? 'cancelled' : 'draft', $id]);
        try { \Sysfera\Hooks::do('wms.doc_changed', $id, $cancel ? 'cancel' : 'unpost'); } catch (\Throwable $e) { /* integrations must never break posting */ }
        return [];
    }

    public function delete(int $id): bool
    {
        $doc = $this->find($id); if (!$doc || $doc['status'] === 'posted') return false;
        $this->db->q('DELETE FROM {p}wms_doc_lines WHERE doc_id = ?', [$id]);
        $this->db->q('DELETE FROM {p}wms_docs WHERE id = ?', [$id]);
        return true;
    }

    /** Lines that really move stock (see Kinds::expand). @return array{0:list<array>,1:array<int,float>,2:list<string>} */
    private function physical(string $type, array $lines, array $open): array
    {
        $ids = array_values(array_unique(array_merge(array_map(fn($l) => (int)$l['product_id'], $lines), array_keys($open))));
        if (!$ids) return [$lines, $open, []];
        $in = implode(',', array_map('intval', $ids)); $kind = []; $comps = [];
        foreach ($this->db->rows("SELECT id, ptype FROM {p}wms_products WHERE id IN ($in)") as $r) $kind[(int)$r['id']] = (string)$r['ptype'];
        if (!array_filter($kind, fn($k) => $k !== 'simple')) return [$lines, $open, []];   // the usual case: nothing to expand
        foreach ($this->db->rows("SELECT parent_id, child_id, qty FROM {p}wms_product_links WHERE parent_id IN ($in)") as $r) $comps[(int)$r['parent_id']][] = ['child' => (int)$r['child_id'], 'qty' => (float)$r['qty']];
        return Kinds::expand($type, $lines, $kind, $comps, $open);
    }

    private function warehouse(int $id): ?array { return $id ? $this->db->row('SELECT * FROM {p}wms_warehouses WHERE id = ?', [$id]) : null; }
    private function fmt(float $n): string { return rtrim(rtrim(number_format($n, 3, '.', ''), '0'), '.') ?: '0'; }
}
