<?php
declare(strict_types=1);
namespace SysferaWms;

/**
 * The accounting rules of the warehouse, as pure functions (no database) so they are easy to test.
 * A posted document turns into rows of the stock ledger: on_hand (physically here), reserve (promised to customers),
 * expected (ordered from suppliers, not yet received). Available = on_hand − reserve.
 */
final class Posting
{
    /** type => [number prefix, group, parent type, needs contact] */
    public const TYPES = [
        'receipt'   => ['RC', 'stock', null,       false],
        'writeoff'  => ['WO', 'stock', null,       false],
        'transfer'  => ['TR', 'stock', null,       false],
        'inventory' => ['IV', 'stock', null,       false],
        'purchase'  => ['PO', 'buy',   null,       true],
        'receiving' => ['GR', 'buy',   'purchase', false],
        'sale'      => ['SO', 'sell',  null,       true],
        'shipment'  => ['SH', 'sell',  'sale',     false],
    ];

    public static function known(string $type): bool { return isset(self::TYPES[$type]); }
    public static function prefix(string $type): string { return self::TYPES[$type][0] ?? 'DOC'; }
    public static function group(string $type): string { return self::TYPES[$type][1] ?? 'stock'; }
    public static function parentType(string $type): ?string { return self::TYPES[$type][2] ?? null; }
    public static function needsContact(string $type): bool { return (bool)(self::TYPES[$type][3] ?? false); }
    /** Does posting this type change physical stock (so cells matter)? */
    public static function physical(string $type): bool { return in_array($type, ['receipt', 'writeoff', 'transfer', 'inventory', 'receiving', 'shipment'], true); }
    public static function childType(string $type): ?string { foreach (self::TYPES as $t => $m) if ($m[2] === $type) return $t; return null; }

    /**
     * Ledger rows for a document.
     * @param array $doc   warehouse_id, to_warehouse_id
     * @param array $lines each: id, product_id, qty, cell_id, to_cell_id
     * @param array $ctx   'open' => [product_id => qty still open on the parent order], 'sys' => [line_id => counted system qty] (inventory)
     * @return array<int,array{line_id:?int,product_id:int,warehouse_id:int,cell_id:?int,on_hand:float,reserve:float,expected:float}>
     */
    public static function moves(string $type, array $doc, array $lines, array $ctx = []): array
    {
        $wh = (int)$doc['warehouse_id']; $to = (int)($doc['to_warehouse_id'] ?? 0);
        $open = $ctx['open'] ?? [];
        $out = [];
        $row = fn(array $l, int $w, ?int $cell, float $hand = 0.0, float $res = 0.0, float $exp = 0.0): array => [
            'line_id' => isset($l['id']) ? (int)$l['id'] : null, 'product_id' => (int)$l['product_id'], 'warehouse_id' => $w,
            'cell_id' => $cell ?: null, 'on_hand' => $hand, 'reserve' => $res, 'expected' => $exp];
        foreach ($lines as $l) {
            $q = (float)$l['qty']; $p = (int)$l['product_id']; $cell = isset($l['cell_id']) ? (int)$l['cell_id'] : null;
            switch ($type) {
                case 'receipt':   $out[] = $row($l, $wh, $cell, $q); break;
                case 'writeoff':  $out[] = $row($l, $wh, $cell, -$q); break;
                case 'transfer':
                    $out[] = $row($l, $wh, $cell, -$q);
                    $out[] = $row($l, $to ?: $wh, isset($l['to_cell_id']) ? (int)$l['to_cell_id'] : null, $q);
                    break;
                case 'inventory':
                    $delta = $q - (float)($ctx['sys'][$l['id'] ?? 0] ?? 0);
                    if (abs($delta) > 0.0000001) $out[] = $row($l, $wh, $cell, $delta);
                    break;
                case 'purchase':  $out[] = $row($l, $wh, null, 0, 0, $q); break;
                case 'receiving':
                    $take = isset($open[$p]) ? min($q, max(0.0, (float)$open[$p])) : 0.0;
                    if (isset($open[$p])) $open[$p] -= $take;
                    $out[] = $row($l, $wh, $cell, $q, 0, -$take);
                    break;
                case 'sale':      $out[] = $row($l, $wh, null, 0, $q); break;
                case 'shipment':
                    $take = isset($open[$p]) ? min($q, max(0.0, (float)$open[$p])) : 0.0;
                    if (isset($open[$p])) $open[$p] -= $take;
                    $out[] = $row($l, $wh, $cell, -$q, -$take);
                    break;
            }
        }
        return $out;
    }

    /**
     * What must be in stock for the document to be postable (summed per product / warehouse / cell).
     * kind 'on_hand' = physically present, 'available' = on_hand − reserve (a new customer order must not oversell).
     * @return array<int,array{kind:string,product_id:int,warehouse_id:int,cell_id:?int,qty:float}>
     */
    public static function needs(string $type, array $doc, array $lines, array $ctx = []): array
    {
        $acc = [];
        $add = function (string $kind, int $p, int $w, ?int $c, float $q) use (&$acc) {
            $k = "$kind|$p|$w|" . ($c ?: 0);
            $acc[$k] = ($acc[$k] ?? ['kind' => $kind, 'product_id' => $p, 'warehouse_id' => $w, 'cell_id' => $c ?: null, 'qty' => 0.0]);
            $acc[$k]['qty'] += $q;
        };
        $wh = (int)$doc['warehouse_id'];
        foreach ($lines as $l) {
            $q = (float)$l['qty']; $p = (int)$l['product_id']; $c = isset($l['cell_id']) ? (int)$l['cell_id'] : null;
            if ($type === 'writeoff' || $type === 'transfer') $add('on_hand', $p, $wh, $c, $q);
            elseif ($type === 'shipment') $add('on_hand', $p, $wh, $c, $q);
            elseif ($type === 'sale') $add('available', $p, $wh, null, $q);
        }
        return array_values($acc);
    }

    /** Problems that make a document unpostable regardless of stock. Returns translation keys (wms.*). */
    public static function problems(string $type, array $doc, array $lines, bool $warehouseUsesCells, bool $toWarehouseUsesCells = false): array
    {
        $e = [];
        if (!self::known($type)) return ['err_type'];
        if (empty($doc['warehouse_id'])) $e[] = 'err_warehouse';
        if ($type === 'transfer' && empty($doc['to_warehouse_id'])) $e[] = 'err_to_warehouse';
        if (self::needsContact($type) && empty($doc['contact_id'])) $e[] = $type === 'purchase' ? 'err_supplier' : 'err_customer';
        if (!$lines) $e[] = 'err_no_lines';
        foreach ($lines as $l) {
            $q = (float)$l['qty'];
            if (($type === 'inventory' ? $q < 0 : $q <= 0)) { $e[] = 'err_qty'; break; }
        }
        if (self::physical($type)) {
            foreach ($lines as $l) { if ($warehouseUsesCells && empty($l['cell_id'])) { $e[] = 'err_cell'; break; } }
            if ($type === 'transfer') foreach ($lines as $l) { if ($toWarehouseUsesCells && empty($l['to_cell_id'])) { $e[] = 'err_to_cell'; break; } }
        }
        if ($type === 'transfer' && !empty($doc['warehouse_id']) && (int)$doc['warehouse_id'] === (int)($doc['to_warehouse_id'] ?? 0)) {
            foreach ($lines as $l) if ((int)($l['cell_id'] ?? 0) === (int)($l['to_cell_id'] ?? 0)) { $e[] = 'err_same_place'; break; }
        }
        return array_values(array_unique($e));
    }
}
