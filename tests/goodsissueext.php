<?php
declare(strict_types=1);
/**
 * Goods issue 0.2 extensions: returns with an office-approved refund and weekly registry, storage rules + auto-refund, blind stock counts,
 * KPI, photo proof, labels, courier handover, offline / stuck alerts, operator permissions and per-warehouse settings.
 * Pure PHP, in-memory store. Run: php tests/goodsissueext.php
 */
if (!function_exists('mb_substr')) { function mb_substr(string $s, int $a = 0, ?int $l = null): string { return $l === null ? substr($s, $a) : substr($s, $a, $l); } }
if (!function_exists('mb_strlen')) { function mb_strlen(string $s): int { return strlen($s); } }
foreach (['Err', 'Units', 'Machine', 'Sig', 'Code', 'Audit', 'Manifest', 'Store', 'Seed', 'Extras', 'Kpi', 'Label', 'Exchange'] as $c) require dirname(__DIR__) . "/core/plugins/goodsissue/lib/$c.php";
require __DIR__ . '/GiMemStore.php';
use SysferaGi\{Err, Machine, Manifest, Exchange, Seed, Label, Kpi, Audit};

$fail = 0; function ok(bool $c, string $n): void { global $fail; echo ($c ? '  ok   ' : '  FAIL ') . $n . "\n"; if (!$c) $fail++; }
function err(callable $f): ?string { try { $f(); return null; } catch (Err $e) { return $e->errCode; } }
function uuid(): string { return Manifest::newUuid(); }
$jpg = 'data:image/jpeg;base64,' . base64_encode(random_bytes(400));
$ev = str_repeat('x', 40);

/* a fresh world: warehouse 1, both seed manifests received and clean, all codes known */
function world(array $cfg = [], ?array $scansT1 = null): array
{
    global $now, $codes;
    $store = new GiMemStore(); $codes = [];
    $ex = new Exchange($store, $cfg + ['secret' => 's', 'code_ttl' => 86400 * 30, 'id_threshold_kop' => 3000000, 'overdue_days' => 3, 'stuck_hours' => 48, 'offline_hours' => 24], function ($o, $c) use (&$codes) { $codes[$o['uuid']] = $c; }, function () use (&$now) { return $now; });
    $off = ['kind' => 'office', 'user' => 1, 'name' => 'head', 'warehouse' => null, 'device' => null]; $op = ['kind' => 'operator', 'user' => 5, 'name' => 'opA', 'warehouse' => 1, 'device' => 'dev-A'];
    [$t1, $t2] = Seed::transfers(1); $ex->createTransfer($t1, $off); $ex->createTransfer($t2, $off);
    foreach ([[Seed::T1, $scansT1 ?? [['GI4600000001', 1], ['GI4600000002', 3], ['GI4600000003', 1], ['GI4600000004', 2]]], [Seed::T2, [['GI4600000005', 1]]]] as [$tu, $sc]) {
        $v = $ex->deliver($tu, $op);
        $ex->receipt($tu, ['doc_uuid' => uuid(), 'checksum' => $v['checksum'], 'decision' => 'confirm', 'scans' => array_map(fn($b) => ['barcode' => $b[0], 'qty' => $b[1]], $sc)], $op);
    }
    return [$store, $ex, $off, $op];
}
$now = strtotime('2026-11-17 10:00:00');
$issue = fn(Exchange $ex, string $o, string $code, array $op, array $more = []) => $ex->issue($o, $more + ['doc_uuid' => uuid(), 'code' => $code, 'signature' => 'x' . str_repeat('y', 40), 'id_checked' => true], $op);

/* ================= 1. returns ================= */
[$store, $ex, $off, $op] = world();
$issue($ex, Seed::O1, $codes[Seed::O1], $op);
$rq = ['doc_uuid' => $rdoc = uuid(), 'kind' => 'request', 'reason' => 'too small', 'condition' => 'opened', 'items' => [['barcode' => 'GI4600000002', 'qty' => 2]], 'photo' => $jpg];
$r = $ex->returnAction(Seed::O1, $rq, $op);
ok($r['order']['status'] === 'return_requested' && $r['return']['status'] === 'pending' && $r['return']['refund_rub'] === '800.00' && strlen($r['return']['photo_sha256']) === 64, 'return: reason, condition, photo and lines are captured; the refund is computed from the lines (2 x 400.00)');
$rec = $store->record('return', $rdoc);
ok($rec['status'] === 'pending' && $rec['data']['condition'] === 'opened' && $rec['data']['items'][0]['qty'] === 2 && !str_contains(json_encode($rec), 'base64'), 'return: the document keeps the lines and the photo hash, not the photo');
ok(!empty($ex->returnAction(Seed::O1, $rq, $op)['replayed']) && count($store->records('return')) === 1, 'return: a replay creates no second document');
ok($ex->returnAction(Seed::O1, ['doc_uuid' => uuid(), 'kind' => 'request', 'reason' => 'twice'], $op)['conflict'] === true, 'return: a second request for an order that is already in return is a conflict, not a duplicate');
[$s2, $e2, , $o2] = world(); $c2 = $codes; $issue($e2, Seed::O1, $c2[Seed::O1], $o2);
ok(err(fn() => $e2->returnAction(Seed::O1, ['doc_uuid' => uuid(), 'kind' => 'request', 'reason' => 'x'], $o2)) === 'reason_required', 'return: a reason is required');
ok(err(fn() => $e2->returnAction(Seed::O1, ['doc_uuid' => uuid(), 'kind' => 'request', 'reason' => 'broken', 'condition' => 'damaged'], $o2)) === 'photo_required', 'return: damaged goods need a photo');
ok(err(fn() => $e2->returnAction(Seed::O1, ['doc_uuid' => uuid(), 'kind' => 'request', 'reason' => 'broken', 'condition' => 'damaged', 'photo' => 'data:text/html;base64,AAAA'], $o2)) === 'bad_photo', 'return: only images are accepted as photos');
ok(err(fn() => $e2->returnAction(Seed::O1, ['doc_uuid' => uuid(), 'kind' => 'request', 'reason' => 'broken', 'condition' => 'weird'], $o2)) === 'bad_condition', 'return: the condition is ok / opened / damaged');
ok(err(fn() => $e2->returnAction(Seed::O1, ['doc_uuid' => uuid(), 'kind' => 'request', 'reason' => 'broken', 'items' => [['barcode' => 'NOPE0000', 'qty' => 1]]], $o2)) === 'bad_return_item' && err(fn() => $e2->returnAction(Seed::O1, ['doc_uuid' => uuid(), 'kind' => 'request', 'reason' => 'broken', 'items' => [['barcode' => 'GI4600000002', 'qty' => 9]]], $o2)) === 'bad_return_item', 'return: only the order\'s own items, never more pieces than ordered');
ok($store->openIncident('return_request', Seed::O1) !== null, 'return: the office is told (incident)');
ok(err(fn() => $ex->decideReturn($rdoc, 'approve', '9999.00', '', $off)) === 'refund_too_high', 'refund: more than the order total is refused');
$d = $ex->decideReturn($rdoc, 'approve', '700.00', 'ok, minus the wrapping', $off);
ok($d['status'] === 'approved' && $d['refund_rub'] === '700.00' && $store->openIncident('return_request', Seed::O1) === null && err(fn() => $ex->decideReturn($rdoc, 'reject', '', '', $off)) === 'already_decided', 'refund: approved with a smaller amount, incident closed, decided only once');
ok(count($store->actions('return.approved')) === 1, 'refund: the decision is in the audit log');
$rc = $ex->returnAction(Seed::O1, ['doc_uuid' => uuid(), 'kind' => 'receive', 'condition' => 'ok'], $op);
ok($rc['order']['status'] === 'returned' && !empty($store->record('return', $rdoc)['data']['received_at']), 'return: the goods taken back are linked to the return document');
ok(err(fn() => $ex->markRefunded(uuid(), '', $off)) === 'not_found' && $ex->markRefunded($rdoc, 'paid by transfer 12', $off)['status'] === 'refunded' && err(fn() => $ex->markRefunded($rdoc, '', $off)) === 'not_approved', 'refund: marked as paid once');
$exp = $ex->expectedStock(1);
ok($exp['GI4600000002']['qty'] === 2, 'stock: only the returned pieces are back on the shelf (2 of 3)');
/* reject restores */
[$s3, $e3, $of3, $o3] = world(); $c3 = $codes; $issue($e3, Seed::O1, $c3[Seed::O1], $o3);
$e3->returnAction(Seed::O1, ['doc_uuid' => $d3 = uuid(), 'kind' => 'request', 'reason' => 'changed my mind'], $o3);
$e3->decideReturn($d3, 'reject', '', 'not within 14 days', $of3);
ok($s3->order(Seed::O1)['status'] === 'issued' && $s3->record('return', $d3)['status'] === 'rejected', 'refund: a rejected return puts the order back to issued');
/* registry */
$reg = $ex->returnRegistry('2026-11-16', '2026-11-22', 1);
ok($reg['count'] === 1 && $reg['refund_rub'] === '700.00' && $reg['rows'][0]['status'] === 'refunded' && $reg['rows'][0]['received'] === true && $reg['by_status'] === ['refunded' => 1], 'registry: the week lists the return with status and refund');
ok($ex->returnRegistry('2026-12-01', '2026-12-07')['count'] === 0 && err(fn() => $ex->returnRegistry('x', 'y')) === 'bad_day', 'registry: other periods are empty, dates are validated');
$csv = Exchange::registryCsv(['rows' => [['day' => '2026-11-17', 'warehouse_id' => 1, 'number' => '=cmd()', 'customer' => 'A "B"', 'source' => 'customer', 'reason' => '+1', 'condition' => 'ok', 'status' => 'pending', 'pieces' => 1, 'refund_rub' => '1.00', 'received' => false, 'decided_by' => '']]]);
ok(str_starts_with($csv, "\xEF\xBB\xBF") && str_contains($csv, '"\'=cmd()"') && str_contains($csv, '"A ""B"""') && str_contains($csv, '"\'+1"'), 'registry: CSV has a BOM, quotes are doubled, formula cells are neutralised');
ok(Exchange::weekStart('2026-11-17') === '2026-11-16' && Exchange::weekStart('2026-11-22') === '2026-11-16' && Exchange::weekStart('2026-11-23') === '2026-11-23', 'registry: weeks start on Monday');
$now = strtotime('2026-11-30 09:00:00'); $w1 = $ex->weeklyRegistry(1, '2026-11-17'); $w2 = $ex->weeklyRegistry(1, '2026-11-20');
ok($w1['count'] === 1 && $store->record('regweek', '1:2026-11-16') !== null && count($store->actions('registry.weekly')) === 1 && $w2 === $w1, 'registry: a finished week is generated and stored once');
ok($ex->weeklyRegistry(1, '2026-11-30')['count'] === 0 && $store->record('regweek', '1:2026-11-30') === null, 'registry: the running week is computed but not stored yet');
$now = strtotime('2026-11-17 10:00:00');

/* ================= 2. rules, overdue, auto-refund ================= */
[$store, $ex, $off, $op] = world(['refund_days' => 0]);
$ex->setRule('SKU-TV', ['max_days' => 1, 'refund_days' => 5], $off);
ok($ex->rules()['SKU-TV'] === ['max_days' => 1, 'refund_days' => 5] && err(fn() => $ex->setRule('SKU-TV', ['max_days' => 9, 'refund_days' => 3], $off)) === 'bad_setting' && err(fn() => $ex->setRule('', [], $off)) === 'bad_sku', 'rules: stored per SKU, a refund before the overdue date is refused');
$o = $store->order(Seed::O2); ok($ex->overdueAt($o, $ex->rules()) === strtotime($o['ready_at']) + 86400 && $ex->overdueAt($store->order(Seed::O1), $ex->rules()) === strtotime($store->order(Seed::O1)['ready_at']) + 3 * 86400, 'rules: the item rule (1 day) beats the warehouse default (3 days)');
$pres = [1 => ['name' => 'Pickup 1', 'active' => true, 'operators' => 1, 'last_seen' => $now]];
$now += 2 * 86400; $sw = $ex->sweep($pres);
ok($sw['overdue'] === 1 && $store->openIncident('overdue', Seed::O2) !== null && $store->openIncident('overdue', Seed::O1) === null, 'overdue: only the order past ITS limit raises an alert');
$sw2 = $ex->sweep($pres); ok($sw2['overdue'] === 0 && count(array_filter($store->incidents, fn($i) => $i['kind'] === 'overdue')) === 1, 'overdue: a sweep again does not repeat the alert');
$now += 4 * 86400; $pres[1]['last_seen'] = $now; $sw3 = $ex->sweep($pres);   // 6 days: O2 over its refund limit (5), O1/O3/O4 over the 3-day limit
ok($sw3['refunds'] === 1 && $store->order(Seed::O2)['status'] === 'return_requested' && $store->openIncident('overdue', Seed::O2) === null && $store->openIncident('overdue', Seed::O1) !== null, 'auto-refund: an order past the refund limit is returned, its overdue alert closes');
$rr = array_values(array_filter($store->records('return'), fn($r) => ($r['data']['order_uuid'] ?? '') === Seed::O2))[0];
ok($rr['status'] === 'pending' && $rr['data']['source'] === 'unclaimed' && $rr['data']['refund_rub'] === '45990.50' && $store->openIncident('refund_due', Seed::O2) !== null && count($store->actions('refund.auto')) === 1, 'auto-refund: the office gets a pending refund of the full total');
ok($ex->sweep($pres)['refunds'] === 0 && count($store->actions('refund.auto')) === 1, 'auto-refund: once');
[$st4, $e4, $of4, $o4] = world(['refund_days' => 2, 'refund_auto' => true]); $now += 3 * 86400; $e4->sweep([1 => ['name' => 'P', 'active' => true, 'operators' => 1, 'last_seen' => $now]]);
$ar = array_values(array_filter($st4->records('return'), fn($r) => ($r['data']['order_uuid'] ?? '') === Seed::O4))[0] ?? null;
ok($ar && $ar['status'] === 'approved' && $ar['data']['decided_by'] === 'system' && $st4->order(Seed::O4)['status'] === 'return_requested', 'auto-refund: with "approve automatically" the refund is approved by the system and audited');
$now = strtotime('2026-11-17 10:00:00');

/* ================= 3. blind stock count ================= */
[$store, $ex, $off, $op] = world();
$st = $ex->stocktakeStart(1, $off); $again = $ex->stocktakeStart(1, $off);
ok(!empty($again['replayed']) && $again['uuid'] === $st['uuid'] && $st['items'] === 5, 'stocktake: one open count per warehouse');
$view = $ex->stocktakeView($op);
ok($view['uuid'] === $st['uuid'] && count($view['items']) === 5 && !preg_match('/qty|expected|count/i', json_encode($view)), 'stocktake: the app sees names and barcodes only, never an expected quantity');
ok($ex->stocktakeView(['warehouse' => 2] + $op) === null, 'stocktake: another warehouse sees nothing');
$line = fn(string $b, int $q) => ['barcode' => $b, 'qty' => $q];
$sub = ['doc_uuid' => $sd = uuid(), 'lines' => [$line('GI4600000001', 1), $line('GI4600000002', 2), $line('GI4600000003', 1), $line('GI4600000004', 2), $line('GI4600000099', 1)]];   // mugs 2 of 3, a stranger
$res = $ex->stocktakeSubmit($st['uuid'], $sub, $op);
ok($res['conflict'] === false && $res['differences'] === 3 && !str_contains(json_encode($res), 'expected') && $res['stocktake']['status'] === 'submitted', 'stocktake: the answer to the app only says HOW MANY differences, not which');
$adj = $store->records('adjust', 1, 'proposed'); $by = array_column(array_column($adj, 'data'), null, 'barcode');
ok(count($adj) === 3 && $by['GI4600000002']['diff'] === -1 && $by['GI4600000099']['diff'] === 1 && $by['GI4600000005']['diff'] === -1 && $by['GI4600000005']['uncounted'] === true && str_starts_with($by['GI4600000002']['act'], 'ADJ-'), 'stocktake: differences become proposed adjustment acts (short, surplus, not counted)');
ok(!empty($ex->stocktakeSubmit($st['uuid'], $sub, $op)['replayed']) && $ex->stocktakeSubmit($st['uuid'], ['doc_uuid' => uuid(), 'lines' => []] + [], $op)['conflict'] === true, 'stocktake: a replay is safe; a second count of the same stocktake is a conflict (server copy wins)');
ok($store->openIncident('stocktake_diff', $st['uuid']) !== null && err(fn() => $ex->stocktakeSubmit($st['uuid'], ['doc_uuid' => uuid(), 'lines' => []], ['warehouse' => 2] + $op)) === 'not_found', 'stocktake: the office is told; another warehouse cannot submit it');
$act = $ex->adjustmentAct($st['uuid']);
ok(count($act['lines']) === 3 && $act['status'] === 'submitted', 'stocktake: the adjustment act lists every line');
$ex->decideAdjustment("{$st['uuid']}:GI4600000002", 'approve', 'one mug broken', $off);
ok($ex->expectedStock(1)['GI4600000002']['qty'] === 2 && err(fn() => $ex->decideAdjustment("{$st['uuid']}:GI4600000002", 'approve', '', $off)) === 'already_decided', 'adjustment: an approved act corrects the expected stock from now on, once');
$ex->decideAdjustment("{$st['uuid']}:GI4600000099", 'reject', 'not ours', $off); $last = $ex->decideAdjustment("{$st['uuid']}:GI4600000005", 'approve', '', $off);
ok($last['stocktake_closed'] === true && $store->record('stocktake', $st['uuid'])['status'] === 'closed' && $store->openIncident('stocktake_diff', $st['uuid']) === null && $ex->expectedStock(1)['GI4600000005']['qty'] === 0, 'adjustment: when every line is decided the count closes and its incident too');
ok(!isset($ex->expectedStock(1)['GI4600000099']), 'adjustment: a rejected surplus does not enter the stock');
$st2 = $ex->stocktakeStart(1, $off); $clean = [];
foreach ($ex->expectedStock(1) as $bc => $x) $clean[] = ['barcode' => $bc, 'qty' => $x['qty']];
$r2 = $ex->stocktakeSubmit($st2['uuid'], ['doc_uuid' => uuid(), 'lines' => $clean], $op);
ok($r2['differences'] === 0 && $store->record('stocktake', $st2['uuid'])['status'] === 'closed', 'stocktake: a count that matches closes at once');
ok(array_filter(array_map(fn($e) => $e['action'], $store->audit), fn($a) => $a === 'adjust.approved') !== [], 'stocktake: decisions are audited');
/* offline: a count through the queue */
$st3 = $ex->stocktakeStart(1, $off); $q = $ex->sync([['type' => 'stocktake', 'doc_uuid' => uuid(), 'ref' => $st3['uuid'], 'client_ts' => $now - 100, 'payload' => ['lines' => []]]], $op);
ok($q[0]['ok'] && $q[0]['result']['differences'] > 0, 'stocktake: also works from the offline queue');

/* ================= 4. photo proof ================= */
[$store, $ex, $off, $op] = world(['photo_threshold_kop' => 1000000]);
ok($ex->orderView($store->order(Seed::O1))['photo_required'] === false && $ex->orderView($store->order(Seed::O2))['photo_required'] === true, 'photo: required above the threshold only');
ok(err(fn() => $issue($ex, Seed::O2, $codes[Seed::O2], $op)) === 'photo_required' && $store->code(Seed::O2)['used_at'] === null, 'photo: an expensive order cannot be issued without it, and the code is not burnt');
ok(err(fn() => $issue($ex, Seed::O2, $codes[Seed::O2], $op, ['photo' => 'not an image'])) === 'bad_photo', 'photo: garbage is refused');
$idoc = uuid(); $ia = $issue($ex, Seed::O2, $codes[Seed::O2], $op, ['doc_uuid' => $idoc, 'photo' => $jpg]);
ok($ia['act']['photo_sha256'] === hash('sha256', $jpg) && json_decode($store->doc($idoc)['payload'], true)['photo_sha256'] === hash('sha256', $jpg), 'photo: its hash is part of the issue act');
ok(!empty($issue($ex, Seed::O2, $codes[Seed::O2], $op, ['doc_uuid' => $idoc, 'photo' => $jpg])['replayed']) && err(fn() => $issue($ex, Seed::O2, $codes[Seed::O2], $op, ['doc_uuid' => $idoc, 'photo' => 'data:image/jpeg;base64,' . base64_encode(random_bytes(300))])) === 'idempotency_conflict', 'photo: replay is safe; the same document with another photo is a conflict');
$p1 = $issue($ex, Seed::O1, $codes[Seed::O1], $op, ['photo' => $jpg]);
ok(!empty($p1['act']['photo_sha256']), 'photo: optional below the threshold, kept when given');

/* ================= 5. courier handover ================= */
[$store, $ex, $off, $op] = world();
$cr = ['service' => 'boxberry', 'name' => 'Petr Courier', 'waybill' => 'BB-123456'];
ok(err(fn() => $issue($ex, Seed::O1, $codes[Seed::O1], $op, ['handover' => 'courier'])) === 'bad_courier' && err(fn() => $issue($ex, Seed::O1, $codes[Seed::O1], $op, ['handover' => 'courier', 'courier' => ['service' => 'pigeon'] + $cr])) === 'bad_courier'
    && err(fn() => $issue($ex, Seed::O1, $codes[Seed::O1], $op, ['handover' => 'courier', 'courier' => ['waybill' => '1'] + $cr])) === 'bad_courier' && err(fn() => $issue($ex, Seed::O1, $codes[Seed::O1], $op, ['handover' => 'taxi'])) === 'bad_handover', 'courier: service, name and waybill are required and checked');
ok(err(fn() => $ex->issue(Seed::O2, ['doc_uuid' => uuid(), 'code' => $codes[Seed::O2], 'signature' => $ev, 'handover' => 'courier', 'courier' => $cr], $op)) === 'id_check_required', 'courier: the same ID check above the threshold applies to a courier');
ok(err(fn() => $ex->issue(Seed::O1, ['doc_uuid' => uuid(), 'code' => '000000' === $codes[Seed::O1] ? '111111' : '000000', 'signature' => $ev, 'handover' => 'courier', 'courier' => $cr], $op)) === 'code_wrong', 'courier: the same one-time code is required');
$ca = $issue($ex, Seed::O1, $codes[Seed::O1], $op, ['handover' => 'courier', 'courier' => $cr]);
$o = $store->order(Seed::O1);
ok($ca['act']['handover'] === 'courier' && $ca['act']['courier']['service'] === 'boxberry' && $o['handover'] === 'courier' && json_decode($o['courier'], true)['waybill'] === 'BB-123456' && str_contains(json_encode($store->actions('issue.act')), 'BB-123456'), 'courier: the courier is stored on the order and in the audit log');
$cu = $issue($ex, Seed::O3, $codes[Seed::O3], $op);
ok($cu['act']['handover'] === 'customer' && $store->order(Seed::O3)['courier'] === null, 'courier: a handover to the customer stays as before');

/* ================= 6. labels ================= */
$bad = [];
foreach (Label::PATTERNS as $i => $w) { $bars = 0; foreach (str_split($w) as $k => $d) if ($k % 2 === 0) $bars += (int)$d; if (($i < 106 && (array_sum(str_split($w)) !== 11 || $bars % 2 !== 0)) || ($i === 106 && array_sum(str_split($w)) !== 13)) $bad[] = $i; }
ok(count(Label::PATTERNS) === 107 && count(array_unique(Label::PATTERNS)) === 107 && !$bad, 'label: the Code 128 table has 107 distinct symbols of width 11 with the bar-parity property');
$sym = Label::symbols('A'); ok($sym === [104, 33, (104 + 33) % 103, 106], 'label: start B + data + modulo-103 checksum + stop');
ok(Label::widths('A') === '211214' . '111323' . '131123' . '2331112', 'label: "A" has the known bar pattern');
ok(err(fn() => Label::symbols('')) === 'bad_label' && err(fn() => Label::symbols("tab\t")) === 'bad_label' && err(fn() => Label::symbols(str_repeat('a', 41))) === 'bad_label', 'label: only printable ASCII, up to 40 characters');
$svg = Label::svg('SH:A12', 50, 2); ok(str_starts_with($svg, '<svg') && substr_count($svg, '<rect') === 1 + (int)(strlen(Label::widths('SH:A12')) / 2 + 0.5), 'label: SVG with one rect per bar');
ok(Label::range('A', 1, 3) === ['A1', 'A2', 'A3'] && err(fn() => Label::range('A', 1, 500)) === 'bad_range' && err(fn() => Label::range('a b', 1, 2)) === 'bad_range', 'label: shelf ranges are limited');
ok(Exchange::shelfCode(' sh:a-12 ') === 'A-12' && Exchange::shelfCode('') === '' && err(fn() => Exchange::shelfCode('A 1')) === 'bad_shelf' && err(fn() => Exchange::shelfCode('<b>')) === 'bad_shelf', 'shelf: the "SH:" label prefix is understood, bad characters refused');

/* ================= 7. shelf cells ================= */
[$store, $ex, $off, $op] = world();
$sd = uuid(); $sr = $ex->setShelf(Seed::O1, ['doc_uuid' => $sd, 'shelf' => 'sh:b7'], $op);
ok($sr['order']['shelf'] === 'B7' && $store->order(Seed::O1)['shelf'] === 'B7' && $ex->orderView($store->order(Seed::O1))['shelf'] === 'B7', 'shelf: set from a scanned label, shown in the order view');
ok(!empty($ex->setShelf(Seed::O1, ['doc_uuid' => $sd, 'shelf' => 'sh:b7'], $op)['replayed']) && count($store->actions('shelf.set')) === 1, 'shelf: idempotent');
ok($ex->setShelf(Seed::O1, ['doc_uuid' => uuid(), 'shelf' => ''], $op)['order']['shelf'] === null, 'shelf: can be emptied');
$issue($ex, Seed::O1, $codes[Seed::O1], $op);
ok($ex->setShelf(Seed::O1, ['doc_uuid' => uuid(), 'shelf' => 'C1'], $op)['conflict'] === true && err(fn() => $ex->setShelf(Seed::O1, ['doc_uuid' => uuid(), 'shelf' => 'C1'], ['warehouse' => 2] + $op)) === 'not_found', 'shelf: an issued order is no longer on a shelf (conflict); other warehouses cannot touch it');
$qs = $ex->sync([['type' => 'shelf', 'doc_uuid' => uuid(), 'ref' => Seed::O3, 'client_ts' => $now - 50, 'payload' => ['shelf' => 'D4']]], $op);
ok($qs[0]['ok'] && $store->order(Seed::O3)['shelf'] === 'D4', 'shelf: also from the offline queue');

/* ================= 8. offline warehouses, stuck shipments ================= */
$now = strtotime('2026-11-17 10:00:00'); $store = new GiMemStore();
$ex = new Exchange($store, ['secret' => 's', 'code_ttl' => 3600, 'id_threshold_kop' => 0, 'overdue_days' => 30, 'stuck_hours' => 48, 'offline_hours' => 24], null, function () use (&$now) { return $now; });
$off = ['kind' => 'office', 'user' => 1, 'name' => 'head', 'warehouse' => null, 'device' => null]; [$t1] = Seed::transfers(1); $ex->createTransfer($t1, $off);
$pres = fn(?int $seen, int $ops = 1, bool $active = true) => [1 => ['name' => 'Pickup 1', 'active' => $active, 'operators' => $ops, 'last_seen' => $seen]];
ok($ex->sweep($pres($now - 3600))['offline'] === 0, 'offline: seen an hour ago, nothing');
$now += 30 * 3600; $s = $ex->sweep($pres($now - 30 * 3600));
ok($s['offline'] === 1 && $s['stuck'] === 0 && $store->openIncident('offline', '1') !== null, 'offline: not seen for 30 hours -> incident');
ok($ex->sweep($pres($now - 30 * 3600))['offline'] === 0, 'offline: the same alert is not repeated');
$now += 30 * 3600; $s = $ex->sweep($pres($now - 3600));
ok($store->openIncident('offline', '1') === null && $s['closed'] >= 1 && $s['stuck'] === 1 && $store->openIncident('stuck_shipment', Seed::T1) !== null, 'offline: closes by itself when the warehouse is back; the unconfirmed manifest (60 h) is a stuck shipment');
$op = ['kind' => 'operator', 'user' => 5, 'name' => 'opA', 'warehouse' => 1, 'device' => 'dev-A']; $v = $ex->deliver(Seed::T1, $op);
$ex->receipt(Seed::T1, ['doc_uuid' => uuid(), 'checksum' => $v['checksum'], 'decision' => 'confirm', 'scans' => [['barcode' => 'GI4600000001', 'qty' => 1], ['barcode' => 'GI4600000002', 'qty' => 3], ['barcode' => 'GI4600000003', 'qty' => 1], ['barcode' => 'GI4600000004', 'qty' => 2]]], $op);
$ex->sweep($pres($now));
ok($store->openIncident('stuck_shipment', Seed::T1) === null, 'stuck: the alert closes when the manifest is confirmed');
ok($ex->sweep($pres(null, 1))['offline'] === 0 && $ex->sweep($pres($now - 99 * 3600, 0))['offline'] === 0 && $ex->sweep($pres($now - 99 * 3600, 1, false))['offline'] === 0, 'offline: no alert for a warehouse that never started, has no operators, or is switched off');

/* ================= 9. permissions and per-warehouse settings ================= */
[$store, $ex, $off, $op] = world();
$limited = $op + ['perms' => ['receive', 'shelf']];
ok(err(fn() => $issue($ex, Seed::O1, $codes[Seed::O1], $limited)) === 'forbidden' && $store->order(Seed::O1)['status'] === 'ready_for_issue', 'rbac: an operator without the issue permission cannot issue');
ok(err(fn() => $ex->returnAction(Seed::O1, ['doc_uuid' => uuid(), 'kind' => 'request', 'reason' => 'abc'], $limited)) === 'forbidden' && err(fn() => $ex->count(['doc_uuid' => uuid(), 'lines' => []], $limited)) === 'forbidden'
    && err(fn() => $ex->stocktakeSubmit(uuid(), ['doc_uuid' => uuid()], $limited)) === 'forbidden' && err(fn() => $ex->receipt(Seed::T1, ['doc_uuid' => uuid()], ['perms' => []] + $op)) === 'forbidden', 'rbac: returns, counts, stock counts and receipts are separate permissions');
ok($ex->setShelf(Seed::O1, ['doc_uuid' => uuid(), 'shelf' => 'A1'], $limited)['conflict'] === false, 'rbac: what is permitted still works');
$wc = $ex->setWarehouseConfig(1, ['id_threshold' => '100.00', 'photo_threshold' => '500', 'code_ttl_h' => '2', 'overdue_days' => '9', 'refund_days' => '', 'junk' => 'x'], $off);
ok($wc === ['id_threshold_kop' => 10000, 'photo_threshold_kop' => 50000, 'code_ttl' => 7200, 'overdue_days' => 9] && $ex->wcfg(1)['overdue_days'] === 9 && $ex->wcfg(2)['overdue_days'] === 3, 'settings: a warehouse can override the thresholds, the code lifetime and the overdue days');
ok($ex->orderView($store->order(Seed::O1))['id_check'] === true && $ex->orderView($store->order(Seed::O1))['photo_required'] === true, 'settings: the new thresholds apply at once (ID check from 100 ₽)');
$nc = $ex->newCode(Seed::O1, $off); ok($store->code(Seed::O1)['expires_at'] === $now + 7200, 'settings: the code lifetime of the warehouse is used');
ok(err(fn() => $ex->setWarehouseConfig(1, ['code_ttl_h' => '0'], $off)) === 'bad_setting' && err(fn() => $ex->setWarehouseConfig(1, ['id_threshold' => '1,5x'], $off)) === 'unit_money' && err(fn() => $ex->setWarehouseConfig(1, ['overdue_days' => '-1'], $off)) === 'bad_setting', 'settings: values are validated');
ok(count($store->actions('wcfg.set')) === 1, 'settings: changes are audited');

/* ================= 10. KPI ================= */
$now = strtotime('2026-11-17 10:00:00'); [$store, $ex, $off, $op] = world(); $tw = [Seed::T1 => 1, Seed::T2 => 1];
$now += 3600; $issue($ex, Seed::O1, $codes[Seed::O1], $op);   // 1 hour after ready
$bad = $codes[Seed::O3] === '999999' ? '888888' : '999999';
for ($i = 0; $i < 3; $i++) err(fn() => $ex->issue(Seed::O3, ['doc_uuid' => uuid(), 'code' => $bad, 'signature' => $ev], $op));
$ex->newCode(Seed::O3, $off); $ex->newCode(Seed::O3, $off);
$ex->count(['doc_uuid' => uuid(), 'client_ts' => $now + 99999, 'lines' => []], $op);
$k = Kpi::compute([1 => 'Pickup 1', 2 => 'Empty'], array_values($store->orders), $store->audit, $tw, $now, 30);
$w = $k['warehouses'][0];
ok($w['issued'] === 1 && $w['issue_hours_avg'] === 1.0 && $w['storage_days_avg'] === round(1 / 24, 1) && $w['waiting'] === 3 && $w['received'] === 4 && $w['discrepancy_rate'] === 0.0, 'kpi: issue speed (1 h), storage days, waiting orders, discrepancy rate');
ok($k['warehouses'][1]['issued'] === 0 && $k['warehouses'][1]['issue_hours_avg'] === null && $k['warehouses'][1]['discrepancy_rate'] === null, 'kpi: a warehouse without data shows "no value", not 0');
$ops = array_column($k['operators'], null, 'actor');
ok($ops['opA']['issues'] === 1 && $ops['opA']['receipts'] === 2 && $ops['opA']['failed_codes'] === 3 && $ops['opA']['counts'] === 1 && $ops['opA']['warehouses'] === ['Pickup 1'], 'kpi: operator activity');
$types = array_column($k['suspicious'], null, 'type');
ok(isset($types['repeated_code']) && $types['repeated_code']['count'] === 3 && $types['repeated_code']['ref'] === 'A-1003' && $types['repeated_code']['device'] === 'dev-A', 'kpi: three wrong codes on one order are suspicious');
ok(isset($types['code_reissued']) && $types['code_reissued']['count'] === 2 && isset($types['clock_ahead']) && $types['clock_ahead']['ref'] === 'dev-A', 'kpi: codes re-issued by the office and a device clock in the future are suspicious');
/* a blocked order released by hand, and the discrepancy rate */
[$s6, $e6, $of6, $o6] = world(['id_threshold_kop' => 0], [['GI4600000001', 1], ['GI4600000002', 3], ['GI4600000003', 1], ['GI4600000004', 1]]);
$e6->resolve(Seed::O3, 'release', 'accepted 1 of 2', $of6);
$k2 = Kpi::compute([1 => 'Pickup 1'], array_values($s6->orders), $s6->audit, $tw, $now, 30); $t2 = array_column($k2['suspicious'], null, 'type');
ok($k2['warehouses'][0]['discrepancy_orders'] === 1 && $k2['warehouses'][0]['received'] === 4 && $k2['warehouses'][0]['discrepancy_rate'] === 25.0 && isset($t2['manual_override']) && $t2['manual_override']['ref'] === 'A-1003', 'kpi: 1 of 4 received orders had a discrepancy (25 %); releasing a blocked order by hand is flagged');
$kOld = Kpi::compute([1 => 'Pickup 1'], array_values($s6->orders), $s6->audit, $tw, $now + 90 * 86400, 7);
ok($kOld['warehouses'][0]['received'] === 0 && !$kOld['operators'] && !$kOld['suspicious'], 'kpi: events older than the period are left out');

/* ================= state machine of the extensions ================= */
ok(Machine::can('ready_for_issue', 'return_requested', 'system') && !Machine::can('ready_for_issue', 'return_requested', 'operator') && Machine::can('return_requested', 'issued', 'office') && Machine::can('return_requested', 'ready_for_issue', 'office') && !Machine::can('return_requested', 'issued', 'operator'), 'machine: the system sends unclaimed orders back; only the office can reject a return');
ok(Audit::verify($store->audit) === null, 'audit: the chain still verifies after all of this');

echo $fail ? "\n$fail FAILED\n" : "\nAll good\n"; exit($fail ? 1 : 0);
