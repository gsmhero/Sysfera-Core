<?php
declare(strict_types=1);
namespace SysferaWms;

use Sysfera\Db;

/** Read side of the stock ledger. */
final class Stock
{
    public function __construct(private Db $db) {}

    /** Physical quantity. With $byCell the exact cell (null = "no cell"); otherwise the whole warehouse. */
    public function onHand(int $product, int $warehouse, ?int $cell = null, bool $byCell = false): float
    {
        $sql = 'SELECT COALESCE(SUM(on_hand),0) FROM {p}wms_moves WHERE product_id = ? AND warehouse_id = ?'; $a = [$product, $warehouse];
        if ($byCell) { if ($cell) { $sql .= ' AND cell_id = ?'; $a[] = $cell; } else $sql .= ' AND cell_id IS NULL'; }
        return (float)$this->db->val($sql, $a);
    }

    /** on_hand − reserve in a warehouse. */
    public function available(int $product, int $warehouse): float
    {
        $r = $this->db->row('SELECT COALESCE(SUM(on_hand),0) h, COALESCE(SUM(reserve),0) r FROM {p}wms_moves WHERE product_id = ? AND warehouse_id = ?', [$product, $warehouse]);
        return (float)$r['h'] - (float)$r['r'];
    }

    private function where(array $f, array &$a): string
    {
        $w = ' WHERE p.archived = ' . (($f['only'] ?? '') === 'archived' ? '1' : '0');
        if (($o = Scope::id()) !== null) { $w .= ' AND p.owner_id = ?'; $a[] = $o; }
        if (!empty($f['q'])) { $w .= ' AND (p.name LIKE ? OR p.sku LIKE ? OR p.code LIKE ? OR p.barcode = ?)'; $like = '%' . $f['q'] . '%'; array_push($a, $like, $like, $like, (string)$f['q']); }
        if (!empty($f['group_id'])) { $w .= ' AND p.group_id = ?'; $a[] = (int)$f['group_id']; }
        return $w;
    }

    /** One row per product with on_hand / reserve / expected / available. $f: q, group_id, warehouse_id, only = all|in|low|zero */
    public function summary(array $f = [], int $limit = 50, int $offset = 0): array
    {
        $a = []; $join = '';
        if (!empty($f['warehouse_id'])) { $join = ' AND m.warehouse_id = ' . (int)$f['warehouse_id']; }
        $w = $this->where($f, $a);
        $having = match ($f['only'] ?? 'all') { 'in' => ' HAVING on_hand > 0', 'zero' => ' HAVING on_hand <= 0', 'low' => ' HAVING min_stock > 0 AND (on_hand - reserve) < min_stock', default => '' };
        $limit = max(1, min(500, $limit)); $offset = max(0, $offset);
        return $this->db->rows("SELECT p.*, (SELECT name FROM {p}wms_groups WHERE id = p.group_id) AS group_name, COALESCE(SUM(m.on_hand),0) AS on_hand, COALESCE(SUM(m.reserve),0) AS reserve, COALESCE(SUM(m.expected),0) AS expected,
            COALESCE(SUM(m.on_hand),0) - COALESCE(SUM(m.reserve),0) AS available
            FROM {p}wms_products p LEFT JOIN {p}wms_moves m ON m.product_id = p.id$join$w
            GROUP BY p.id$having ORDER BY p.name LIMIT $limit OFFSET $offset", $a);
    }

    public function summaryCount(array $f = []): int
    {
        $a = []; $join = !empty($f['warehouse_id']) ? ' AND m.warehouse_id = ' . (int)$f['warehouse_id'] : '';
        $w = $this->where($f, $a);
        $having = match ($f['only'] ?? 'all') { 'in' => ' HAVING on_hand > 0', 'zero' => ' HAVING on_hand <= 0', 'low' => ' HAVING min_stock > 0 AND (on_hand - reserve) < min_stock', default => '' };
        return count($this->db->rows("SELECT p.id, p.min_stock, COALESCE(SUM(m.on_hand),0) AS on_hand, COALESCE(SUM(m.reserve),0) AS reserve FROM {p}wms_products p LEFT JOIN {p}wms_moves m ON m.product_id = p.id$join$w GROUP BY p.id$having", $a));
    }

    /** Stock of one product by warehouse. */
    public function byWarehouse(int $product): array
    {
        return $this->db->rows('SELECT w.id, w.name, COALESCE(SUM(m.on_hand),0) on_hand, COALESCE(SUM(m.reserve),0) reserve, COALESCE(SUM(m.expected),0) expected
            FROM {p}wms_warehouses w LEFT JOIN {p}wms_moves m ON m.warehouse_id = w.id AND m.product_id = ? WHERE w.active = 1 GROUP BY w.id ORDER BY w.is_default DESC, w.name', [$product]);
    }

    /** Stock of one product by cell (only cells holding something). */
    public function byCell(int $product): array
    {
        return $this->db->rows('SELECT m.warehouse_id, w.name AS warehouse, m.cell_id, c.name AS cell, SUM(m.on_hand) on_hand
            FROM {p}wms_moves m JOIN {p}wms_warehouses w ON w.id = m.warehouse_id LEFT JOIN {p}wms_cells c ON c.id = m.cell_id
            WHERE m.product_id = ? GROUP BY m.warehouse_id, m.cell_id HAVING on_hand <> 0 ORDER BY w.name, c.name', [$product]);
    }

    /** Which products sit in a cell. */
    public function inCell(int $cell): array
    {
        return $this->db->rows('SELECT p.id, p.name, p.sku, p.unit, SUM(m.on_hand) on_hand FROM {p}wms_moves m JOIN {p}wms_products p ON p.id = m.product_id
            WHERE m.cell_id = ? GROUP BY p.id HAVING on_hand <> 0 ORDER BY p.name', [$cell]);
    }

    /** Movement history of a product (newest first), joined with the document. */
    public function history(int $product, int $limit = 50): array
    {
        $limit = max(1, min(500, $limit));
        return $this->db->rows("SELECT m.*, d.type, d.number, d.status, d.source, d.contact_id, d.owner_id, w.name AS warehouse, c.name AS cell FROM {p}wms_moves m JOIN {p}wms_docs d ON d.id = m.doc_id
            JOIN {p}wms_warehouses w ON w.id = m.warehouse_id LEFT JOIN {p}wms_cells c ON c.id = m.cell_id WHERE m.product_id = ? ORDER BY m.moment DESC, m.id DESC LIMIT $limit", [$product]);
    }

    /** Opening balance, income, outcome and closing balance per product for a period. */
    public function turnover(string $from, string $to, ?int $warehouse = null): array
    {
        $join = $warehouse ? ' AND m.warehouse_id = ' . (int)$warehouse : '';
        return $this->db->rows("SELECT p.id, p.name, p.sku, p.unit,
            COALESCE(SUM(CASE WHEN m.moment < ? THEN m.on_hand END),0) AS opening,
            COALESCE(SUM(CASE WHEN m.moment >= ? AND m.moment <= ? AND m.on_hand > 0 THEN m.on_hand END),0) AS income,
            COALESCE(-SUM(CASE WHEN m.moment >= ? AND m.moment <= ? AND m.on_hand < 0 THEN m.on_hand END),0) AS outcome
            FROM {p}wms_products p JOIN {p}wms_moves m ON m.product_id = p.id$join" . (($o = Scope::id()) !== null ? ' WHERE p.owner_id = ' . $o : '') . "
            GROUP BY p.id HAVING opening <> 0 OR income <> 0 OR outcome <> 0 ORDER BY p.name", [$from, $from, $to, $from, $to]);
    }

    /** Numbers for the dashboard. */
    public function totals(): array
    {
        $o = Scope::id();
        $r = $this->db->row('SELECT COALESCE(SUM(t.h * p.buy_price),0) AS value, COALESCE(SUM(t.h),0) AS qty, COALESCE(SUM(t.r),0) AS reserve, COALESCE(SUM(t.e),0) AS expected
            FROM (SELECT product_id, SUM(on_hand) h, SUM(reserve) r, SUM(expected) e FROM {p}wms_moves GROUP BY product_id) t JOIN {p}wms_products p ON p.id = t.product_id' . ($o ? ' WHERE p.owner_id = ' . $o : '')) ?: [];
        $low = (int)$this->db->val('SELECT COUNT(*) FROM (SELECT p.id, p.min_stock FROM {p}wms_products p LEFT JOIN {p}wms_moves m ON m.product_id = p.id WHERE p.archived = 0 AND p.min_stock > 0' . ($o ? ' AND p.owner_id = ' . $o : '') . '
            GROUP BY p.id HAVING COALESCE(SUM(m.on_hand),0) - COALESCE(SUM(m.reserve),0) < min_stock) x');
        return ['value' => (float)($r['value'] ?? 0), 'qty' => (float)($r['qty'] ?? 0), 'reserve' => (float)($r['reserve'] ?? 0), 'expected' => (float)($r['expected'] ?? 0), 'low' => $low,
            'products' => (int)$this->db->val('SELECT COUNT(*) FROM {p}wms_products WHERE archived = 0' . ($o ? ' AND owner_id = ' . $o : ''))];
    }
}
