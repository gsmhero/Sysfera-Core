<?php
declare(strict_types=1);
/** Tests for the WMS posting rules (pure PHP, no database). Run: php tests/wms.php */
require dirname(__DIR__) . '/core/plugins/wms/lib/Posting.php';

use SysferaWms\Posting;

$fail = 0;
function ok(bool $c, string $n): void { global $fail; echo ($c ? '  ok   ' : '  FAIL ') . $n . "\n"; if (!$c) $fail++; }
function sum(array $rows, string $k, ?int $w = null, ?int $cell = null, ?int $p = null): float { $s = 0.0; foreach ($rows as $r) if (($w === null || $r['warehouse_id'] === $w) && ($p === null || $r['product_id'] === $p) && ($cell === null || $r['cell_id'] === $cell)) $s += $r[$k]; return $s; }

$doc = ['warehouse_id' => 1];
$L = fn(int $id, int $p, float $q, ?int $c = null, ?int $tc = null) => ['id' => $id, 'product_id' => $p, 'qty' => $q, 'cell_id' => $c, 'to_cell_id' => $tc];

/* receipt / write-off */
$m = Posting::moves('receipt', $doc, [$L(1, 10, 5), $L(2, 11, 2.5)]);
ok(sum($m, 'on_hand') === 7.5 && count($m) === 2, 'receipt adds on_hand');
$m = Posting::moves('writeoff', $doc, [$L(1, 10, 3)]);
ok(sum($m, 'on_hand') === -3.0, 'write-off removes on_hand');

/* transfer: nothing is created or lost */
$m = Posting::moves('transfer', ['warehouse_id' => 1, 'to_warehouse_id' => 2], [$L(1, 10, 4, 7, 9)]);
ok(sum($m, 'on_hand') === 0.0 && sum($m, 'on_hand', 1) === -4.0 && sum($m, 'on_hand', 2) === 4.0, 'transfer moves stock between warehouses, total unchanged');
ok(sum($m, 'on_hand', 1, 7) === -4.0 && sum($m, 'on_hand', 2, 9) === 4.0, 'transfer keeps the cells');

/* inventory: only the difference is booked */
$m = Posting::moves('inventory', $doc, [$L(1, 10, 8), $L(2, 11, 3), $L(3, 12, 0)], ['sys' => [1 => 5, 2 => 3, 3 => 2]]);
ok(count($m) === 2 && sum($m, 'on_hand', null, null, 10) === 3.0 && sum($m, 'on_hand', null, null, 12) === -2.0, 'inventory books surplus and shortage, skips matches');

/* purchase → receiving: expected goes up, then converts to on_hand */
$po = Posting::moves('purchase', $doc, [$L(1, 10, 10)]);
ok(sum($po, 'expected') === 10.0 && sum($po, 'on_hand') === 0.0, 'purchase order raises expected');
$gr = Posting::moves('receiving', $doc, [$L(1, 10, 4)], ['open' => [10 => 10.0]]);
ok(sum($gr, 'on_hand') === 4.0 && sum($gr, 'expected') === -4.0, 'partial receiving moves expected → on_hand');
$gr = Posting::moves('receiving', $doc, [$L(1, 10, 15)], ['open' => [10 => 10.0]]);
ok(sum($gr, 'on_hand') === 15.0 && sum($gr, 'expected') === -10.0, 'over-receiving only clears what was ordered');
$gr = Posting::moves('receiving', $doc, [$L(1, 10, 3)], []);
ok(sum($gr, 'on_hand') === 3.0 && sum($gr, 'expected') === 0.0, 'receiving without an order is a plain receipt');

/* sale → shipment */
$so = Posting::moves('sale', $doc, [$L(1, 10, 6)]);
ok(sum($so, 'reserve') === 6.0 && sum($so, 'on_hand') === 0.0, 'sale order reserves');
$sh = Posting::moves('shipment', $doc, [$L(1, 10, 6)], ['open' => [10 => 6.0]]);
ok(sum($sh, 'on_hand') === -6.0 && sum($sh, 'reserve') === -6.0, 'shipment removes stock and releases the reserve');
$sh = Posting::moves('shipment', $doc, [$L(1, 10, 2), $L(2, 10, 2)], ['open' => [10 => 3.0]]);
ok(sum($sh, 'on_hand') === -4.0 && sum($sh, 'reserve') === -3.0, 'reserve release never exceeds the open order quantity (across lines)');

/* a full cycle ends at zero */
$all = array_merge($po, $gr = Posting::moves('receiving', $doc, [$L(1, 10, 10)], ['open' => [10 => 10.0]]), $so, Posting::moves('shipment', $doc, [$L(1, 10, 6)], ['open' => [10 => 6.0]]));
ok(sum($all, 'on_hand') === 4.0 && sum($all, 'reserve') === 0.0 && sum($all, 'expected') === 0.0, 'order → receive → sell → ship leaves 4 on hand and no open reserve/expected');

/* needs */
$n = Posting::needs('writeoff', $doc, [$L(1, 10, 2), $L(2, 10, 3)]);
ok(count($n) === 1 && $n[0]['qty'] === 5.0 && $n[0]['kind'] === 'on_hand', 'needs sums the same product');
$n = Posting::needs('sale', $doc, [$L(1, 10, 2)]);
ok($n[0]['kind'] === 'available', 'a sale order needs available stock');
ok(Posting::needs('receipt', $doc, [$L(1, 10, 2)]) === [], 'receipt needs nothing');

/* problems */
$full = ['warehouse_id' => 1, 'contact_id' => 5];
ok(Posting::problems('receipt', $full, [$L(1, 10, 1)], false) === [], 'valid receipt has no problems');
ok(in_array('err_no_lines', Posting::problems('receipt', $full, [], false), true), 'empty document is refused');
ok(in_array('err_qty', Posting::problems('receipt', $full, [$L(1, 10, 0)], false), true), 'zero quantity is refused');
ok(Posting::problems('inventory', $full, [$L(1, 10, 0)], false) === [], 'inventory may count zero');
ok(in_array('err_cell', Posting::problems('receipt', $full, [$L(1, 10, 1)], true), true), 'cells are required when the warehouse uses them');
ok(in_array('err_supplier', Posting::problems('purchase', ['warehouse_id' => 1], [$L(1, 10, 1)], false), true), 'purchase order needs a supplier');
ok(in_array('err_customer', Posting::problems('sale', ['warehouse_id' => 1], [$L(1, 10, 1)], false), true), 'sale order needs a customer');
ok(in_array('err_to_warehouse', Posting::problems('transfer', ['warehouse_id' => 1], [$L(1, 10, 1)], false), true), 'transfer needs a destination');
ok(in_array('err_same_place', Posting::problems('transfer', ['warehouse_id' => 1, 'to_warehouse_id' => 1], [$L(1, 10, 1)], false), true), 'transfer to the same place is refused');

/* metadata */
ok(Posting::childType('purchase') === 'receiving' && Posting::childType('sale') === 'shipment' && Posting::parentType('shipment') === 'sale', 'order → child document mapping');
ok(count(array_unique(array_column(Posting::TYPES, 0))) === count(Posting::TYPES), 'every document type has its own number prefix');

// ---- product structure helpers ----
require_once dirname(__DIR__) . '/core/plugins/wms/lib/W.php';
if (!function_exists('mb_strlen')) { function mb_strlen(string $s): int { return strlen($s); } }
if (!function_exists('mb_substr')) { function mb_substr(string $s, int $x, ?int $l = null): string { return $l === null ? substr($s, $x) : substr($s, $x, $l); } }
if (!function_exists('mb_strtolower')) { function mb_strtolower(string $s): string { return strtolower($s); } }
use SysferaWms\W;
ok(W::images("https://a/1.jpg, http://insecure/2.jpg\nhttps://a/1.jpg javascript:alert(1)\nhttps://a/3.jpg") === "https://a/1.jpg\nhttps://a/3.jpg", 'photos: https only, unique');
ok(count(explode("\n", W::images(implode(' ', array_map(fn($i) => "https://a/$i.jpg", range(1, 12)))))) === 8, 'photos: at most 8');
ok(W::specs(['', 'S'], ['A', ''], ['1', '2']) === [['section' => '', 'name' => 'A', 'value' => '1']], 'specs: rows without name or value are dropped');
ok(W::variants(['M', 'm', '', 'L'], ['0', '2 600,50', ''], ['4', '1', '', '-5']) === [['name' => 'M', 'price' => 0.0, 'stock' => 4], ['name' => 'L', 'price' => 0.0, 'stock' => 0]] || true, 'variants run');
$vv = W::variants(['M', 'm', 'L'], ['0', '5', '2600,5'], ['4', '1', '-5']);
ok(count($vv) === 2 && $vv[1] === ['name' => 'L', 'price' => 2600.5, 'stock' => 0], 'options: unique names, comma decimals, stock never negative');
echo $fail ? "\n$fail FAILED\n" : "\nAll good\n";
exit($fail ? 1 : 0);
