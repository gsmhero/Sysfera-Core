<?php
declare(strict_types=1);
/**
 * Goods issue: units, state machine, signatures, manifest, receipt, issue with one-time code, returns, offline sync conflicts,
 * duplicate submissions, reconciliation, audit chain. Pure PHP, in-memory store. Run: php tests/goodsissue.php
 */
if (!function_exists('mb_substr')) { function mb_substr(string $s, int $a = 0, ?int $l = null): string { return $l === null ? substr($s, $a) : substr($s, $a, $l); } }
if (!function_exists('mb_strlen')) { function mb_strlen(string $s): int { return strlen($s); } }
foreach (['Err', 'Units', 'Machine', 'Sig', 'Code', 'Audit', 'Manifest', 'Store', 'Seed', 'Extras', 'Kpi', 'Label', 'Exchange'] as $c) require dirname(__DIR__) . "/core/plugins/goodsissue/lib/$c.php";
require __DIR__ . '/GiMemStore.php';
use SysferaGi\{Err, Units, Machine, Sig, Code, Audit, Manifest, Exchange, Seed};

$fail = 0; function ok(bool $c, string $n): void { global $fail; echo ($c ? '  ok   ' : '  FAIL ') . $n . "\n"; if (!$c) $fail++; }
function err(callable $f): ?string { try { $f(); return null; } catch (Err $e) { return $e->errCode; } }
function uuid(): string { return Manifest::newUuid(); }

/* ---------- units ---------- */
ok(Units::grams(1200) === 1200 && Units::grams('350') === 350, 'weight: whole grams');
ok(err(fn() => Units::grams(1.2)) === 'unit_weight' && err(fn() => Units::grams('1.2kg')) === 'unit_weight' && err(fn() => Units::grams(-5)) === 'unit_weight', 'weight: floats, "1.2kg", negatives refused');
ok(err(fn() => Units::grams(9_999_999)) === 'unit_weight', 'weight: absurd value refused (kg sent as g)');
ok(Units::rub('1250.5') === '1250.50' && Units::rub(100) === '100.00' && Units::kop('45990.50') === 4599050, 'money: decimal rubles, normalised');
ok(err(fn() => Units::rub(12.5)) === 'unit_money' && err(fn() => Units::rub('12,5')) === 'unit_money' && err(fn() => Units::rub('1.999')) === 'unit_money' && err(fn() => Units::rub('₽100')) === 'unit_money', 'money: floats, comma, 3 digits, symbols refused');
ok(err(fn() => Units::declared(['weight' => 'kg', 'money' => 'RUB'])) === 'unit_mismatch' && err(fn() => Units::declared(null)) === 'unit_mismatch', 'declared units must be g + RUB');

/* ---------- state machine ---------- */
ok(Machine::go('created', 'transferred', 'system') === 'transferred', 'machine: created -> transferred');
ok(err(fn() => Machine::go('created', 'issued', 'operator')) === 'bad_transition', 'machine: cannot skip steps');
ok(err(fn() => Machine::go('blocked', 'ready_for_issue', 'operator')) === 'forbidden_transition', 'machine: only the office releases a blocked order');
ok(err(fn() => Machine::go('ready_for_issue', 'issued', 'office')) === 'forbidden_transition', 'machine: the office does not issue goods');
ok(Machine::next('issued') === ['return_requested'] && Machine::next('returned') === [], 'machine: issued -> return_requested, returned is final');

/* ---------- signatures ---------- */
$k = 'device-key'; $ts = (string)time(); $body = '{"a":1}';
$sg = Sig::request($k, $ts, 'post', '/x', $body);
ok(Sig::ok($sg, Sig::request($k, $ts, 'POST', '/x', $body)), 'sig: valid request signature');
ok(!Sig::ok($sg, Sig::request($k, $ts, 'POST', '/x', '{"a":2}')) && !Sig::ok($sg, Sig::request('other', $ts, 'POST', '/x', $body)) && !Sig::ok($sg, ''), 'sig: changed body / wrong key / empty refused');
ok(Sig::fresh($ts, time()) && !Sig::fresh((string)(time() - 3600), time()) && !Sig::fresh('abc', time()), 'sig: replay window');
ok(Sig::response($k, $ts, 200, $body) !== Sig::response($k, $ts, 500, $body), 'sig: the status is signed in server messages');

/* ---------- manifest ---------- */
$T = Seed::transfers(1)[0];
$m = Manifest::parse($T);
ok(count($m['orders']) === 3 && count($m['items']) === 4 && strlen($m['checksum']) === 64, 'manifest: parsed, checksum computed');
$T2 = $T; $T2['orders'][0]['items'] = array_reverse($T2['orders'][0]['items']);
ok(Manifest::parse($T2)['checksum'] === $m['checksum'], 'manifest: checksum does not depend on the order of lines');
$bad = $T; $bad['orders'][0]['total_rub'] = '2701.00';
ok(err(fn() => Manifest::parse($bad)) === 'total_mismatch', 'manifest: total must equal the sum of items');
$bad = $T; $bad['units'] = ['weight' => 'kg', 'money' => 'RUB'];
ok(err(fn() => Manifest::parse($bad)) === 'unit_mismatch', 'manifest: unit mismatch refused');
$bad = $T; $bad['orders'][0]['items'][0]['weight_g'] = 1.2;
ok(err(fn() => Manifest::parse($bad)) === 'unit_weight', 'manifest: kilograms as float refused');
$bad = $T; $bad['orders'][1]['items'][0]['barcode'] = 'GI4600000001';
ok(err(fn() => Manifest::parse($bad)) === 'duplicate_barcode', 'manifest: a barcode is unique');
$bad = $T; $bad['checksum'] = str_repeat('0', 64);
ok(err(fn() => Manifest::parse($bad)) === 'checksum_mismatch', 'manifest: a wrong checksum refused');

/* ---------- engine ---------- */
$now = strtotime('2026-11-17 10:00:00');
$store = new GiMemStore(); $codes = [];
$ex = new Exchange($store, ['secret' => 's3cret', 'code_ttl' => 3600, 'id_threshold_kop' => 3000000, 'overdue_days' => 3], function (array $o, string $c) use (&$codes) { $codes[$o['uuid']] = $c; }, function () use (&$now) { return $now; });
$office = ['kind' => 'office', 'user' => 1, 'name' => 'head', 'warehouse' => null, 'device' => null];
$opA = ['kind' => 'operator', 'user' => 5, 'name' => 'opA', 'warehouse' => 1, 'device' => 'dev-A'];
$opB = ['kind' => 'operator', 'user' => 6, 'name' => 'opB', 'warehouse' => 1, 'device' => 'dev-B'];
$other = ['kind' => 'operator', 'user' => 7, 'name' => 'far', 'warehouse' => 2, 'device' => 'dev-X'];
[$t1, $t2] = Seed::transfers(1);

/* duplicate manifest submission */
$r1 = $ex->createTransfer($t1, $office); $r2 = $ex->createTransfer($t1, $office);
ok(empty($r1['replayed']) && !empty($r2['replayed']) && $r2['checksum'] === $r1['checksum'] && count($store->transfers) === 1 && count($store->orders) === 3, 'duplicate: a repeated manifest returns the original result, nothing is created twice');
$chg = $t1; $chg['orders'][2]['items'][0]['qty'] = 3; $chg['orders'][2]['total_rub'] = '1485.00';
ok(err(fn() => $ex->createTransfer($chg, $office)) === 'idempotency_conflict', 'duplicate: same uuid, different contents is refused');
$ex->createTransfer($t2, $office);
ok(array_unique(array_column($store->orders, 'status')) === ['created'], 'orders start as created');

/* a far warehouse cannot see it */
ok(err(fn() => $ex->deliver(Seed::T1, $other)) === 'not_found' && err(fn() => $ex->transferView(Seed::T1, $other)) === 'not_found', 'isolation: another warehouse sees nothing');

/* delivery */
$v = $ex->deliver(Seed::T1, $opA); $ex->deliver(Seed::T1, $opA);
ok($v['status'] === 'transferred' && count($store->actions('transfer.delivered')) === 1 && $store->order(Seed::O1)['status'] === 'transferred', 'delivery: created -> transferred, once');

/* scans */
$cs = $v['checksum'];
$scan = fn(array $bcs) => array_map(fn($b) => ['barcode' => $b[0], 'qty' => $b[1]], $bcs);
$ctsEarly = $now - 600;
$rcpA = ['doc_uuid' => uuid(), 'checksum' => $cs, 'decision' => 'confirm', 'client_ts' => $ctsEarly,
    'scans' => $scan([['GI4600000001', 1], ['GI4600000002', 3], ['GI4600000003', 1], ['GI4600000004', 1]]), 'discrepancies' => []];   // lamp: 1 of 2 -> short
$res = $ex->receipt(Seed::T1, $rcpA, $opA);
$st = array_column($res['orders'], 'status', 'uuid');
ok($st[Seed::O1] === 'ready_for_issue' && $st[Seed::O2] === 'ready_for_issue' && $st[Seed::O3] === 'blocked', 'receipt: a short item blocks ONLY its order, the others are ready_for_issue');
ok($res['transfer']['status'] === 'disputed' && $res['incidents'] === 1 && $store->openIncident('receipt_discrepancy', Seed::O3) !== null, 'receipt: a discrepancy opens an incident for the office');
ok(isset($codes[Seed::O1]) && isset($codes[Seed::O2]) && !isset($codes[Seed::O3]) && strlen($codes[Seed::O1]) === 6, 'receipt: a one-time code per ready order, none for the blocked one');
ok(!str_contains(json_encode($res), $codes[Seed::O1]), 'receipt: the code never appears in the answer to the app');
ok($store->actions('receipt.discrepancy')[0]['client_ts'] === date('Y-m-d H:i:s', $ctsEarly), 'audit keeps the ORIGINAL (client) timestamp');

/* duplicate receipt */
$n0 = count($store->audit); $d0 = count($store->docs);
$again = $ex->receipt(Seed::T1, $rcpA, $opA);
ok(!empty($again['replayed']) && $again['orders'] === $res['orders'] && count($store->audit) === $n0 && count($store->docs) === $d0, 'duplicate: replaying the receipt returns the original result, no new audit rows, no new document');
$mut = $rcpA; $mut['scans'][0]['qty'] = 2;
ok(err(fn() => $ex->receipt(Seed::T1, $mut, $opA)) === 'idempotency_conflict', 'duplicate: same document uuid with other content is refused');

/* conflict: a second device confirms the same manifest later -> the server copy wins */
$rcpB = ['doc_uuid' => uuid(), 'checksum' => $cs, 'decision' => 'confirm', 'scans' => $scan([['GI4600000001', 1], ['GI4600000002', 3], ['GI4600000003', 1], ['GI4600000004', 2]])];
$c = $ex->receipt(Seed::T1, $rcpB, $opB);
ok($c['conflict'] === true && $c['server']['status'] === 'disputed' && $store->order(Seed::O3)['status'] === 'blocked', 'conflict: a later confirmation changes nothing, the server copy is returned');
ok(!empty($ex->receipt(Seed::T1, $rcpB, $opB)['replayed']), 'conflict: and the conflict answer is replayable too');
ok(err(fn() => $ex->receipt(Seed::T1, ['checksum' => str_repeat('a', 64)] + $rcpB + [], $opB)) !== null, 'receipt: a wrong checksum is refused');

/* issue: guards */
$ev = str_repeat('x', 40);
$iss = fn(string $o, array $in, array $a = null) => $ex->issue($o, $in + ['doc_uuid' => uuid(), 'signature' => $ev], $a ?? $opA);
ok(err(fn() => $iss(Seed::O3, ['code' => '123456'])) === 'order_blocked', 'issue: a blocked order cannot be issued (even with a code)');
ok(err(fn() => $iss(Seed::O1, ['code' => ''])) === 'code_wrong', 'issue: no code -> no issue');
ok(err(fn() => $iss(Seed::O1, ['code' => '000000' === $codes[Seed::O1] ? '111111' : '000000'])) === 'code_wrong', 'issue: a wrong code is refused');
$fl = $store->actions('issue.code_failed'); ok(count($fl) === 2 && strlen($fl[0]['code_hash']) === 64 && $fl[0]['device_id'] === 'dev-A', 'audit: failed attempts keep the code hash and the device id');
ok(err(fn() => $iss(Seed::O1, ['code' => $codes[Seed::O1]], $other)) === 'not_found', 'issue: another warehouse cannot issue it');
ok(err(fn() => $iss(Seed::O2, ['code' => $codes[Seed::O2]])) === 'id_check_required', 'issue: above the threshold the ID check is required');
ok($store->code(Seed::O2)['used_at'] === null, 'issue: a refused ID check does not burn the code');
ok(err(fn() => $ex->issue(Seed::O1, ['doc_uuid' => uuid(), 'code' => $codes[Seed::O1]], $opA)) === 'evidence_required', 'issue: signature / photo is required');
$bad = $codes[Seed::O1] === '999999' ? '888888' : '999999';
for ($i = 0; $i < 3; $i++) err(fn() => $iss(Seed::O1, ['code' => $bad]));
ok(err(fn() => $iss(Seed::O1, ['code' => $codes[Seed::O1]])) === 'code_locked' && $store->openIncident('code_locked', Seed::O1) !== null, 'issue: 5 wrong attempts lock the code and open an incident');
$new = $ex->newCode(Seed::O1, $office); $codes[Seed::O1] = $new;
$issueDoc = uuid();
$ok1 = $ex->issue(Seed::O1, ['doc_uuid' => $issueDoc, 'code' => $new, 'signature' => $ev], $opA);
ok($ok1['order']['status'] === 'issued' && $ok1['act']['evidence_sha256'] === hash('sha256', $ev), 'issue: new code from the office -> issued, act with the evidence hash');
$rp = $ex->issue(Seed::O1, ['doc_uuid' => $issueDoc, 'code' => $new, 'signature' => $ev], $opA); $acts = count($store->actions('issue.act'));
ok(!empty($rp['replayed']) && $acts === 1, 'duplicate: replaying the issue (e.g. after a timeout) does not issue twice');
ok(err(fn() => $iss(Seed::O1, ['code' => $new])) === 'already_issued', 'issue: a second document for the same order is refused');
ok(err(fn() => $iss(Seed::O2, ['code' => $codes[Seed::O2], 'id_checked' => true], $opA) ?: null) === null && $store->order(Seed::O2)['status'] === 'issued', 'issue: with the ID check the expensive order is issued');
/* expiry */
$now += 7200;
ok(err(fn() => $ex->newCode(Seed::O1, $office)) === 'not_ready', 'code: a new code only for ready orders');

/* office resolves the dispute */
$rel = $ex->resolve(Seed::O3, 'release', 'office accepts 1 of 2', $office);
ok($store->order(Seed::O3)['status'] === 'ready_for_issue' && strlen((string)$rel['code']) === 6 && $store->openIncident('receipt_discrepancy', Seed::O3) === null && $store->transfer(Seed::T1)['status'] === 'confirmed', 'resolve: release -> ready_for_issue, new code, incident closed, transfer confirmed');
$exp = $store->code(Seed::O3)['expires_at']; ok($exp === $now + 3600, 'code: expiry window is configurable (1h here)');
$now += 4000;
ok(err(fn() => $ex->issue(Seed::O3, ['doc_uuid' => uuid(), 'code' => $rel['code'], 'signature' => $ev], $opA)) === 'code_expired', 'issue: an expired code is refused');
$c3 = $ex->newCode(Seed::O3, $office);
ok($ex->issue(Seed::O3, ['doc_uuid' => uuid(), 'code' => $c3, 'signature' => $ev], $opA)['order']['status'] === 'issued', 'issue: a fresh code works');

/* offline queue */
$ex->deliver(Seed::T2, $opA);
$cs2 = $store->transfer(Seed::T2)['checksum'];
$tQ = $now - 3000; $tR = $now - 1000;
$actions = [
    ['type' => 'count', 'doc_uuid' => uuid(), 'client_ts' => $tR, 'payload' => ['day' => date('Y-m-d', $now), 'lines' => [['barcode' => 'GI4600000005', 'qty' => 1]]]],
    ['type' => 'receipt', 'doc_uuid' => $dq = uuid(), 'ref' => Seed::T2, 'client_ts' => $tQ, 'payload' => ['checksum' => $cs2, 'decision' => 'confirm', 'scans' => $scan([['GI4600000005', 1]])]],
    ['type' => 'issue', 'doc_uuid' => uuid(), 'ref' => Seed::O4, 'client_ts' => $tQ + 1, 'payload' => ['code' => '123456']],
    ['type' => 'receipt', 'doc_uuid' => uuid(), 'ref' => Seed::T1, 'client_ts' => $tQ + 2, 'payload' => ['checksum' => $cs, 'decision' => 'confirm', 'scans' => []]],
];
$out = $ex->sync($actions, $opA);
ok($out[0]['type'] === 'receipt' && $out[0]['ok'] && !$out[0]['conflict'], 'offline: actions run in the order they happened (receipt before the later count)');
ok($out[1]['type'] === 'issue' && !$out[1]['ok'] && $out[1]['error'] === 'online_only', 'offline: issuing is never accepted from the queue');
ok($out[2]['type'] === 'receipt' && $out[2]['ok'] && $out[2]['conflict'] === true && $out[2]['result']['server']['status'] === 'confirmed', 'offline: a stale receipt of a manifest the server already closed is a conflict -> server copy');
ok($out[3]['type'] === 'count' && $out[3]['ok'], 'offline: the count is accepted');
ok($store->actions('receipt.confirmed')[count($store->actions('receipt.confirmed')) - 1]['client_ts'] === date('Y-m-d H:i:s', $tQ), 'offline: the queued action keeps its original time');
$out2 = $ex->sync($actions, $opA);
ok(!array_filter($out2, fn($x) => $x['ok'] && empty($x['replayed'])), 'offline: re-sending the same queue is idempotent (everything replayed)');
ok(count($store->actions('receipt.confirmed')) === 1 && $store->order(Seed::O4)['status'] === 'ready_for_issue', 'offline: still one confirmation per manifest');
/* a clock far in the future */
$rr = $ex->count(['doc_uuid' => uuid(), 'day' => date('Y-m-d', $now - 86400), 'client_ts' => $now + 86400 * 3, 'lines' => []], $opA);
ok(str_contains(json_encode($store->actions('count.submitted')), '"client_ts"') && strtotime(end($store->audit)['client_ts']) <= $now, 'offline: a device clock in the future is clamped to the server time');

/* returns */
$rq = $ex->returnAction(Seed::O1, ['doc_uuid' => uuid(), 'kind' => 'request', 'reason' => 'customer changed mind'], $opA);
ok($rq['order']['status'] === 'return_requested' && $store->openIncident('return_request', Seed::O1) !== null, 'return: issued -> return_requested with a reason, the office is told');
ok(err(fn() => $ex->returnAction(Seed::O4, ['doc_uuid' => uuid(), 'kind' => 'request', 'reason' => 'x y z'], $opA)) === null && ($store->order(Seed::O4)['status'] === 'ready_for_issue'), 'return: an order that was never issued is a conflict, not a change');
$rc = $ex->returnAction(Seed::O1, ['doc_uuid' => $rd = uuid(), 'kind' => 'receive', 'condition' => 'ok'], $opA);
ok($rc['order']['status'] === 'returned' && !empty($ex->returnAction(Seed::O1, ['doc_uuid' => $rd, 'kind' => 'receive', 'condition' => 'ok'], $opA)['replayed']), 'return: return_requested -> returned, replay safe');

/* reconciliation */
$day = date('Y-m-d', $now);
$rep = $ex->reconcile(1, $day);
ok($rep['counted'] === true && $rep['actual_pieces'] === 1, 'reconcile: the day count is used');
$byBc = array_column($rep['lines'], null, 'barcode');
ok($byBc['GI4600000005']['expected'] === 1 && $byBc['GI4600000005']['diff'] === 0, 'reconcile: expected = received and not yet issued; the matching line has no difference');
ok(isset($byBc['GI4600000002']) && $byBc['GI4600000002']['expected'] === 3 && $byBc['GI4600000002']['diff'] === -3 && $store->openIncident('stock_diff', "1:$day:GI4600000002") !== null, 'reconcile: returned goods are expected on the shelf; the difference becomes an incident');
ok(!empty($ex->reconcile(1, $day)['replayed']), 'reconcile: one report per warehouse and day');
$before = count($store->incidents); $ex->reconcile(1, $day, true);
ok(count($store->incidents) === $before, 'reconcile: re-running does not duplicate incidents');
$now += 5 * 86400; $d2 = date('Y-m-d', $now);
$rep2 = $ex->reconcile(1, $d2);
ok($rep2['counted'] === false && count($rep2['overdue']) === 1 && $store->openIncident('overdue', Seed::O4) !== null && $store->openIncident('no_count', "1:$d2") !== null, 'reconcile: no count and an order waiting too long -> incidents');
ok(count($rep['issued']) >= 0 && count($rep2['not_issued']) === 1, 'reconcile: not issued list');

/* audit chain */
ok(Audit::verify($store->audit) === null && count($store->audit) > 30, 'audit: the chain verifies');
$t = $store->audit; $t[5]['actor'] = 'operator:evil';
ok(Audit::verify($t) === $t[5]['id'], 'audit: an edited row is detected');
$t = $store->audit; unset($t[7]); $t = array_values($t);
ok(Audit::verify($t) !== null, 'audit: a deleted row is detected');
ok(!array_filter($store->audit, fn($e) => preg_match('/\b\d{6}\b/', (string)$e['data']) && in_array(preg_replace('/\D/', '', (string)json_decode($e['data'], true)['code'] ?? ''), $codes, true)), 'audit: plain codes are never written');

echo $fail ? "\n$fail FAILED\n" : "\nAll good\n"; exit($fail ? 1 : 0);
