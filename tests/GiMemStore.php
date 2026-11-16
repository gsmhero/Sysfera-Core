<?php
declare(strict_types=1);
/** In-memory Store for the Goods issue tests (the real one is DbStore). */
final class GiMemStore implements SysferaGi\Store
{
    public array $transfers = [], $items = [], $orders = [], $docs = [], $codes = [], $audit = [], $incidents = [], $reports = [];
    private int $iid = 0, $inc = 0;

    public function transfer(string $u): ?array { return $this->transfers[$u] ?? null; }
    public function saveTransfer(array $t): void { $this->transfers[$t['uuid']] = $t; }
    public function items(string $tu): array { return array_values(array_filter($this->items, fn($i) => $i['transfer_uuid'] === $tu)); }
    public function saveItems(string $tu, array $items): void { foreach ($items as $i) { if (empty($i['id'])) $i['id'] = ++$this->iid; $i['transfer_uuid'] = $tu; $this->items[$i['id']] = $i; } }
    public function order(string $u): ?array { return $this->orders[$u] ?? null; }
    public function saveOrder(array $o): void { $this->orders[$o['uuid']] = $o; }
    public function ordersOfTransfer(string $tu): array { return array_values(array_filter($this->orders, fn($o) => $o['transfer_uuid'] === $tu)); }
    public function ordersOfWarehouse(int $w): array { return array_values(array_filter($this->orders, fn($o) => (int)$o['warehouse_id'] === $w)); }
    public function orderItems(string $ou): array { return array_values(array_filter($this->items, fn($i) => $i['order_uuid'] === $ou)); }
    public function doc(string $u): ?array { return $this->docs[$u] ?? null; }
    public function saveDoc(array $d): bool { if (isset($this->docs[$d['uuid']])) return false; $this->docs[$d['uuid']] = $d; return true; }
    public function docs(int $w, string $type, string $day = ''): array { return array_values(array_filter($this->docs, fn($d) => (int)$d['warehouse_id'] === $w && $d['type'] === $type && ($day === '' || $d['day'] === $day))); }
    public function code(string $ou): ?array { return $this->codes[$ou] ?? null; }
    public function saveCode(array $c): void { $this->codes[$c['order_uuid']] = $c; }
    public function audit(array $e): array { $prev = $this->audit ? end($this->audit)['hash'] : SysferaGi\Audit::GENESIS; $e = SysferaGi\Audit::seal($e, $prev); $e['id'] = count($this->audit) + 1; $this->audit[] = $e; return $e; }
    public function addIncident(array $i): int { $i['id'] = ++$this->inc; $this->incidents[$i['id']] = $i; return $i['id']; }
    public function openIncident(string $kind, string $ref): ?array { foreach ($this->incidents as $i) if ($i['kind'] === $kind && $i['ref'] === $ref && $i['status'] === 'open') return $i; return null; }
    public function closeIncident(int $id, string $by, string $note): void { $this->incidents[$id]['status'] = 'resolved'; $this->incidents[$id]['note'] = $note; }
    public function report(int $w, string $day): ?array { return $this->reports["$w|$day"] ?? null; }
    public function saveReport(array $r): void { $this->reports[$r['warehouse_id'] . '|' . $r['day']] = $r; }
    public array $records = [];
    public function transfersByStatus(array $st): array { return array_values(array_filter($this->transfers, fn($t) => in_array($t['status'], $st, true))); }
    public function openIncidents(string $kind): array { return array_values(array_filter($this->incidents, fn($i) => $i['kind'] === $kind && $i['status'] === 'open')); }
    public function record(string $k, string $r): ?array { return $this->records["$k|$r"] ?? null; }
    public function saveRecord(array $r): void { $this->records[$r['kind'] . '|' . $r['ref']] = $r; }
    public function records(string $k, int $w = 0, string $s = ''): array { return array_reverse(array_values(array_filter($this->records, fn($r) => $r['kind'] === $k && ($w === 0 || (int)$r['warehouse_id'] === $w) && ($s === '' || $r['status'] === $s)))); }
    public function actions(string $a): array { return array_values(array_filter($this->audit, fn($e) => $e['action'] === $a)); }
}
