<?php
declare(strict_types=1);
/** Logic tests of Storage Rent (WRMS + Offers) on an in-memory store: units, tenants, contracts, invoices, payments, penalties, write-offs, notifications, reports, exports, listings.
 *  Also covers the acceptance examples of the specification. No database, no network. Run: php tests/storagerent.php */
$dir = dirname(__DIR__) . '/core/plugins/storagerent';
foreach (['Err', 'Store', 'Billing', 'Ctx', 'Wrms', 'Ledger', 'Notify', 'Reports', 'Xlsx', 'Offers', 'Files', 'Contact'] as $c) require "$dir/lib/$c.php";
require __DIR__ . '/StoragerentMemStore.php';
use SysferaStoragerent\{Billing as B, Ctx, Wrms, Ledger, Notify, Reports, Xlsx, Offers, Err};
$fail = 0; function ok(bool $c, string $n): void { global $fail; echo ($c ? '  ok   ' : '  FAIL ') . $n . "\n"; if (!$c) $fail++; }
function refused(callable $f, string $code): bool { try { $f(); } catch (Err $e) { return $e->errCode === $code; } return false; }

$now = 1_780_000_000; $today = '2026-03-01'; $st = new StoragerentMemStore();
$clock = fn() => $now; $date = function () use (&$today) { return $today; };
$wr = new Wrms($st, $clock, $date); $lg = new Ledger($st, $clock, $date); $rp = new Reports($st, $lg, $wr, $date);
$admin = new Ctx(1, 'admin'); $mgr = new Ctx(2, 'manager'); $acc = new Ctx(3, 'accountant'); $guest = Ctx::guest();

/* ---------- roles ---------- */
ok(Ctx::for(5, true, [], null)->role === 'admin' && Ctx::for(5, false, ['storagerent.admin'], null)->role === 'admin' && Ctx::for(5, false, ['storagerent.manager'], null)->role === 'manager' && Ctx::for(5, false, ['storagerent.accountant'], 9)->role === 'accountant' && Ctx::for(5, false, [], 9)->role === 'tenant' && Ctx::for(5, false, [], null)->role === 'none' && Ctx::for(0, true, [], null)->role === 'none', 'roles: admin / manager / accountant / tenant / none from platform user, caps and tenant link');
ok($admin->can('tariff') && !$mgr->can('tariff') && $mgr->can('write') && !$acc->can('write') && $acc->can('export') && $acc->can('finance') && !$guest->can('view'), 'permissions: manager has no tariffs, accountant is read-only with exports');

/* ---------- warehouse, units ---------- */
$w = $wr->saveWarehouse($admin, 0, ['name' => 'Main', 'city' => 'Kazan', 'address' => 'Lenina 1', 'lat' => '55.79', 'lon' => '49,12']);
ok($w['id'] > 0 && $w['lon'] === '49.120000', 'warehouse saved, comma decimal accepted');
ok(refused(fn() => $wr->saveWarehouse($acc, 0, ['name' => 'x']), 'forbidden') && refused(fn() => $wr->saveWarehouse($guest, 0, ['name' => 'x']), 'forbidden') && refused(fn() => $wr->saveWarehouse($admin, 0, ['name' => 'x', 'lat' => '95', 'lon' => '1']), 'coords_invalid'), 'warehouse: accountant and guest refused, bad coordinates refused');
$u1 = $wr->saveUnit($admin, 0, ['warehouse_id' => $w['id'], 'name' => 'A-1', 'type' => 'dry', 'area' => '45', 'volume' => '180', 'pallets' => 30, 'load_t' => '3', 'height' => '4', 'dock' => 1, 'features' => ['racking', 'power', 'bogus']]);
$u2 = $wr->saveUnit($mgr, 0, ['warehouse_id' => $w['id'], 'name' => 'B-1', 'type' => 'cold', 'area' => '100', 'pallets' => 80]);
ok($u1['features'] === 'racking,power' && (int)$u1['dock'] === 1, 'unit saved; unknown features dropped');
ok(refused(fn() => $wr->saveUnit($admin, 0, ['warehouse_id' => $w['id'], 'name' => 'Z', 'type' => 'castle', 'area' => 1]), 'type_invalid') && refused(fn() => $wr->saveUnit($admin, 0, ['warehouse_id' => $w['id'], 'name' => 'Z']), 'size_required') && refused(fn() => $wr->saveUnit($admin, 0, ['warehouse_id' => 99, 'name' => 'Z', 'area' => 1]), 'warehouse_required') && refused(fn() => $wr->saveUnit($acc, 0, ['warehouse_id' => $w['id'], 'name' => 'Z', 'area' => 1]), 'forbidden'), 'unit: validation and permissions');
ok(array_column($wr->units(), 'status', 'name') === ['A-1' => 'free', 'B-1' => 'free'], 'units start free');

/* ---------- tenants ---------- */
$t = $wr->saveTenant($admin, 0, ['kind' => 'legal', 'name' => 'Acme LLC', 'tin' => '7707083893', 'contact' => 'Ivan', 'phone' => '+7 900', 'email' => 'acme@example.com', 'restrictions' => 'No flammable goods', 'vat_mode' => 'none']);
ok($t['id'] > 0 && $t['restrictions'] === 'No flammable goods' && $t['status'] === 'active', 'tenant saved with its storage restriction');
ok(refused(fn() => $wr->saveTenant($admin, 0, ['name' => 'X', 'kind' => 'legal', 'tin' => '123']), 'tin_invalid') && refused(fn() => $wr->saveTenant($admin, 0, ['name' => 'X', 'kind' => 'sole', 'tin' => '7707083893']), 'tin_invalid') && refused(fn() => $wr->saveTenant($admin, 0, ['name' => ' ']), 'name_required') && refused(fn() => $wr->saveTenant($admin, 0, ['name' => 'X', 'email' => 'nope']), 'email_invalid') && refused(fn() => $wr->saveTenant($admin, 0, ['name' => 'X', 'status' => 'gone']), 'status_invalid'), 'tenant: TIN length by kind, name, e-mail, status checked');
$wr->saveTenant($admin, $t['id'], ['kind' => 'legal', 'name' => 'Acme LLC', 'tin' => '7707083893', 'email' => 'acme@example.com', 'restrictions' => 'No flammable goods, no food', 'vat_mode' => 'none', 'phone' => '+7 901']);
$log = $wr->auditLog('tenant', $t['id']); $upd = array_values(array_filter($log, fn($l) => $l['action'] === 'update'))[0] ?? null;
ok($upd && isset(json_decode($upd['diff'], true)['restrictions'], json_decode($upd['diff'], true)['phone']) && !isset(json_decode($upd['diff'], true)['name']), 'audit log keeps who changed which fields (and only those)');
$tU = $wr->saveTenant($admin, 0, ['kind' => 'sole', 'name' => 'IP Petrov', 'tin' => '770708389312', 'user_id' => 44]);
ok(refused(fn() => $wr->saveTenant($admin, 0, ['name' => 'Other', 'user_id' => 44]), 'user_taken') && $wr->tenantOfUser(44)['id'] === $tU['id'] && $wr->tenantOfUser(45) === null, 'a Sysfera user is linked to at most one tenant');
ok(count($wr->tenants('acme')) === 1 && count($wr->tenants('770708389312')) === 1 && count($wr->tenants('', 'archived')) === 0, 'tenant search and status filter');

/* ---------- contract: 500 RUB/m2 x 45 m2, due day 5 ---------- */
$in = ['tenant_id' => $t['id'], 'start_date' => '01.03.2026', 'end_date' => '2026-12-31', 'rate_type' => 'm2', 'rate' => '500', 'billing_months' => 1, 'due_day' => 5, 'grace_days' => 3, 'penalty_type' => 'none', 'penalty_value' => '0',
    'util_method' => 'included', 'access_24h' => 1, 'units' => [['unit_id' => $u1['id'], 'share' => 100]]];
$k = $wr->saveContract($admin, 0, $in);
ok($k['status'] === 'draft' && $k['state'] === 'draft' && preg_match('/^SR-2026-0001$/', $k['number']) === 1 && $k['monthly'] === 2250000 && $k['tenant']['restrictions'] === 'No flammable goods, no food', 'ACCEPTANCE: contract 500 RUB/m2 x 45 m2 = 22 500/month; restriction visible in the contract');
ok(array_column($wr->units(), 'status', 'name')['A-1'] === 'reserved', 'a draft contract reserves the unit');
foreach ([['end_date' => '2026-01-01', 'dates_order'], ['start_date' => 'x', 'date_invalid'], ['rate_type' => 'kg', 'rate_type_invalid'], ['rate' => '-5', 'amount_invalid'], ['billing_months' => 5, 'billing_invalid'], ['due_day' => 40, 'due_day_invalid'], ['penalty_type' => 'x', 'penalty_invalid'], ['util_method' => 'x', 'util_invalid'], ['units' => [], 'unit_required'], ['tenant_id' => 99, 'tenant_required'], ['access_24h' => 0, 'access_invalid']] as $bad) {
    $code = array_pop($bad); ok(refused(fn() => $wr->saveContract($admin, 0, $bad + $in), $code), "contract: refuses $code");
}
ok(refused(fn() => $wr->saveContract($acc, 0, $in), 'forbidden'), 'contract: accountant cannot write');
ok(refused(fn() => $wr->saveContract($admin, 0, ['units' => [['unit_id' => $u1['id'], 'share' => 40]]] + $in), 'unit_overbooked'), 'contract: a unit cannot be promised twice');
$k = $wr->activate($admin, $k['id']); ok($k['status'] === 'active' && count($wr->versions($k['id'])) === 1 && array_column($wr->units(), 'status', 'name')['A-1'] === 'occupied', 'activate: version 1 stored, unit occupied');
ok(refused(fn() => $wr->activate($admin, $k['id']), 'state_invalid'), 'activate twice refused');
/* partial-unit rental: B-1 (100 m2) in shares */
$kA = $wr->saveContract($admin, 0, ['units' => [['unit_id' => $u2['id'], 'share' => 60]], 'rate' => '300'] + $in); $wr->activate($admin, $kA['id']);
ok($kA['monthly'] === 1800000 && refused(fn() => $wr->saveContract($admin, 0, ['units' => [['unit_id' => $u2['id'], 'share' => 50]]] + $in), 'unit_overbooked'), 'partial rental: 60 m2 share bills 18 000, a 50% second share would overbook');
$kB = $wr->saveContract($admin, 0, ['tenant_id' => $tU['id'], 'units' => [['unit_id' => $u2['id'], 'share' => 40]], 'rate' => '300'] + $in); $wr->activate($admin, $kB['id']);
ok(array_column($wr->units(), 'rented', 'name')['B-1'] === 100.0, 'partial rental: two tenants fill one unit');
foreach ([$kA['id'], $kB['id']] as $x) $wr->terminate($admin, $x, '2026-03-15', 'test');   // keep the main scenario to one contract

/* ---------- invoices: 22 500 on the 1st of each month ---------- */
$made = $lg->generate('2026-03-01'); $inv = $lg->invoice($made[0]);
ok(count($made) === 1 && $inv['total'] === 2250000 && $inv['due_date'] === '2026-03-05' && $inv['status'] === 'issued' && $inv['issue_date'] === '2026-03-01' && $inv['period_start'] === '2026-03-01', 'ACCEPTANCE: invoice of 22 500 RUB generated on the 1st, due on the 5th');
ok($lg->generate('2026-03-01') === [] && $lg->generate('2026-03-31') === [] && count($st->t['invoices']) === 1, 'generating again the same month creates nothing');
$today = '2026-04-01'; ok(count($lg->tick()) === 1 && count($st->t['invoices']) === 2, 'next month: a second invoice');

/* ---------- payments: partial, FIFO, statuses ---------- */
$today = '2026-03-04'; $p1 = $lg->registerPayment($admin, $t['id'], null, '2026-03-04', '10 000', 'bank', 'PP-1');
$i1 = $lg->invoice(1); ok($i1['status'] === 'partially_paid' && $i1['outstanding'] === 1250000 && $p1['unallocated'] === 0, 'ACCEPTANCE: 10 000 of 22 500 -> partially_paid, 12 500 in debt');
ok($lg->balance($t['id'])['outstanding'] === 1250000 + 2250000 && $lg->invoice(2)['status'] === 'issued', 'balance counts both invoices');
$p2 = $lg->registerPayment($admin, $t['id'], null, '2026-03-04', '20000', 'cash', 'PP-2');
ok($lg->invoice(1)['status'] === 'paid' && $lg->invoice(2)['outstanding'] === 2250000 - (2000000 - 1250000) && $p2['unallocated'] === 0 && count($p2['allocations']) === 2, 'FIFO: the oldest invoice is settled first, the rest goes to the next');
$p3 = $lg->registerPayment($admin, $t['id'], null, '2026-03-05', '25000', 'bank', 'PP-3'); ok($lg->invoice(2)['status'] === 'paid' && $p3['unallocated'] === 1000000 && $lg->credit($t['id']) === 1000000, 'overpayment: the second invoice is paid and 10 000 stay as credit');
ok(refused(fn() => $lg->registerPayment($admin, $t['id'], null, '2026-03-05', '0', 'bank', ''), 'amount_invalid') && refused(fn() => $lg->registerPayment($acc, $t['id'], null, '2026-03-05', '5', 'bank', ''), 'forbidden') && refused(fn() => $lg->registerPayment($admin, 99, null, '2026-03-05', '5', 'bank', ''), 'tenant_required'), 'payment: zero amount, accountant, unknown tenant refused');
/* credit is used by the next invoice automatically */
$today = '2026-05-01'; $lg->tick(); $inv3 = $lg->invoice(3); ok($inv3['total'] === 2250000 && $inv3['status'] === 'partially_paid' && $inv3['outstanding'] === 1250000 && $lg->credit($t['id']) === 0, 'credit is applied to the next invoice when it is issued');

/* ---------- reassign, refund, write-off, credit note, cancel, manual invoice ---------- */
$p4 = $lg->registerPayment($admin, $t['id'], null, '2026-05-02', '100000', 'bank', 'BIG'); $open = array_values(array_filter($lg->invoices(['tenant_id' => $t['id']]), fn($i) => $i['outstanding'] > 0));
ok($p4['unallocated'] > 0 || !$open, 'a big payment covers all open invoices and leaves credit');
$credit = $lg->credit($t['id']); $r = $lg->refund($admin, $t['id'], '1000', '2026-05-03', 'overpaid'); ok($lg->credit($t['id']) === $credit - 100000 && $st->t['payments'][$r]['amount'] === -100000 && $st->t['payments'][$r]['kind'] === 'refund', 'refund reduces the credit and is stored as a negative payment');
ok(refused(fn() => $lg->refund($admin, $t['id'], '99999999', '2026-05-03', 'x'), 'refund_too_big') && refused(fn() => $lg->refund($admin, $t['id'], '10', '2026-05-03', ' '), 'reason_required'), 'refund: not more than the credit, a reason is required');
$mi = $lg->createManual($admin, $k['id'], [['description' => 'Key replacement', 'amount' => '1 500', 'vatable' => 1]], '2026-06-10', 'lost key', true);
ok($lg->invoice($mi)['total'] === 150000 && $lg->invoice($mi)['reason'] === 'lost key' && $lg->invoice($mi)['kind'] === 'manual' && refused(fn() => $lg->createManual($admin, $k['id'], [['description' => 'x', 'amount' => '1']], '2026-06-10', '', true), 'reason_required') && refused(fn() => $lg->createManual($mgr, 99, [['description' => 'x', 'amount' => '1']], '2026-06-10', 'r', true), 'not_found'), 'manual invoice: needs a reason, which is stored and audited');
$mi2 = $lg->createManual($admin, $k['id'], [['description' => 'Cleaning', 'amount' => '500']], '2026-07-10', 'cleaning', false); ok($lg->invoice($mi2)['status'] === 'draft' && $lg->invoice($mi2)['outstanding'] === 0, 'manual draft is not billed'); $lg->issue($admin, $mi2); ok($lg->invoice($mi2)['status'] !== 'draft', 'draft can be issued');
$cr = $lg->credit($t['id']); $lg->cancel($admin, $mi2, 'mistake'); ok($lg->invoice($mi2)['status'] === 'cancelled' && refused(fn() => $lg->cancel($admin, $mi2, 'again'), 'state_invalid') && refused(fn() => $lg->cancel($admin, $mi, ''), 'reason_required'), 'cancel: reason required, once');

/* ---------- penalties: 0.5 %/day from day 1 after the grace period ---------- */
$st2 = new StoragerentMemStore(); $today2 = '2026-03-01'; $d2 = function () use (&$today2) { return $today2; }; $wr2 = new Wrms($st2, $clock, $d2); $lg2 = new Ledger($st2, $clock, $d2);
$wr2->saveWarehouse($admin, 0, ['name' => 'W']); $un = $wr2->saveUnit($admin, 0, ['warehouse_id' => 1, 'name' => 'U', 'area' => 45]); $tt = $wr2->saveTenant($admin, 0, ['name' => 'T', 'vat_mode' => 'none']);
$kk = $wr2->saveContract($admin, 0, ['tenant_id' => $tt['id'], 'start_date' => '2026-03-01', 'end_date' => '2026-12-31', 'rate' => '500', 'due_day' => 5, 'grace_days' => 3, 'penalty_type' => 'percent', 'penalty_value' => '0.5', 'units' => [['unit_id' => $un['id'], 'share' => 100]]] + $in); $wr2->activate($admin, $kk['id']);
$lg2->generate('2026-03-01'); $today2 = '2026-03-08'; ok($lg2->invoice(1)['penalty_accrued'] === 0 && $lg2->invoice(1)['status'] === 'overdue', 'grace period: overdue status, but no penalty yet (due 05.03 + 3 days)');
$today2 = '2026-03-09'; ok($lg2->invoice(1)['penalty_accrued'] === 11250, 'ACCEPTANCE: penalty accrues from day 1 after the grace period (0.5% of 22 500 = 112,50)');
$today2 = '2026-03-19'; ok($lg2->invoice(1)['penalty_accrued'] === 123750, 'penalty after 11 late days = 1 237,50');
$lg2->registerPayment($admin, $tt['id'], null, '2026-03-19', '10000', 'bank', ''); ok($lg2->invoice(1)['penalty_accrued'] === 118750 && $lg2->invoice(1)['status'] === 'partially_paid', 'a part payment on the day does not undo earlier penalty');
$today2 = '2026-03-20'; ok($lg2->invoice(1)['penalty_accrued'] === 125000, 'then it accrues on the unpaid 12 500 only');
$ar = (new Reports($st2, $lg2, $wr2, $d2))->arrears(); ok(count($ar['rows']) === 1 && $ar['rows'][0]['bucket'] === '1-30' && $ar['rows'][0]['outstanding'] === 1250000 && $ar['rows'][0]['penalty'] > 0 && $ar['rows'][0]['days_late'] === 15, 'ACCEPTANCE: arrears report lists the invoice with debt, penalty and aging bucket');
$a0 = $lg2->invoice(1)['penalty_accrued']; $lg2->adjust($admin, 1, 'penalty_waive', '100', 'waive'); ok($lg2->invoice(1)['penalty_accrued'] === $a0 - 10000 && refused(fn() => $lg2->adjust($admin, 1, 'penalty_waive', '99999', 'x'), 'amount_too_big'), 'penalty can be waived with a reason, never above what accrued');
/* penalty is billed with the next invoice, once */
$today2 = '2026-04-01'; $lg2->generate('2026-04-01'); $items = $lg2->items(2); $pen = array_values(array_filter($items, fn($i) => $i['kind'] === 'penalty'));
ok(count($pen) === 1 && $pen[0]['vatable'] === 0 && $lg2->invoice(2)['total'] === 2250000 + $pen[0]['amount'] && $lg2->invoice(2)['principal'] === 2250000, 'the next invoice carries the accrued penalty as its own non-VAT line');
$before = $lg2->invoice(1)['penalty_billed']; $lg2->tick(); $lg2->generate('2026-04-01'); ok($before === $lg2->invoice(1)['penalty_billed'] && $before > 0, 'a billed penalty is not billed again');
$pid = $lg2->billPenalties($admin, $kk['id']); ok($pid === null, 'bill penalties: only what accrued since');
/* write-off, credit note, waiver: a reason is required, nothing above the debt, only staff with write rights */
$o1 = $lg2->invoice(1); ok($o1['late'] && $o1['outstanding'] > 0, 'invoice 1 is still late');
ok(refused(fn() => $lg2->adjust($admin, 1, 'writeoff', '1', ''), 'reason_required') && refused(fn() => $lg2->adjust($admin, 1, 'writeoff', B::dec($o1['outstanding'] + 100), 'x'), 'amount_too_big') && refused(fn() => $lg2->adjust($acc, 1, 'writeoff', '1', 'x'), 'forbidden') && refused(fn() => $lg2->adjust($admin, 1, 'bogus', '1', 'x'), 'bad_request'), 'write-off: reason, limit and permission are checked');
$lg2->adjust($admin, 1, 'writeoff', '1000', 'goodwill'); $o2 = $lg2->invoice(1); ok($o2['outstanding'] === $o1['outstanding'] - 100000 && $o2['settled'] === $o1['settled'] + 100000, 'write-off lowers the debt');
$lg2->adjust($admin, 1, 'credit_note', B::dec($o2['outstanding']), 'price correction'); ok($lg2->invoice(1)['status'] === 'paid' && $lg2->invoice(1)['outstanding'] === 0, 'a credit note for the whole debt closes the invoice');
/* fixed penalty */
$wr2->saveContract($admin, $kk['id'], ['penalty_type' => 'fixed', 'penalty_value' => '100', 'tenant_id' => $tt['id'], 'start_date' => '2026-03-01', 'end_date' => '2026-12-31', 'rate' => '500', 'units' => [['unit_id' => $un['id'], 'share' => 100]]] + $in);
ok($wr2->contract($kk['id'])['penalty_value'] == 10000 && count($wr2->versions($kk['id'])) === 2, 'editing an active contract stores a new version; fixed penalty is kept in kopecks');

/* ---------- VAT per tenant ---------- */
$st3 = new StoragerentMemStore(); $today3 = '2026-03-01'; $d3 = function () use (&$today3) { return $today3; }; $wr3 = new Wrms($st3, $clock, $d3); $lg3 = new Ledger($st3, $clock, $d3);
$wr3->saveWarehouse($admin, 0, ['name' => 'W']); $un3 = $wr3->saveUnit($admin, 0, ['warehouse_id' => 1, 'name' => 'U', 'area' => 45]);
foreach (['added' => [2250000, 450000, 2700000], 'included' => [1875000, 375000, 2250000], 'none' => [2250000, 0, 2250000]] as $mode => [$net, $vat, $tot]) {
    $tn = $wr3->saveTenant($admin, 0, ['name' => "T $mode", 'vat_mode' => $mode, 'vat_rate' => '20']); $kv = $wr3->saveContract($admin, 0, ['tenant_id' => $tn['id'], 'units' => [['unit_id' => $un3['id'], 'share' => 100]]] + $in); $wr3->activate($admin, $kv['id']);
    $id = $lg3->generate('2026-03-01')[0]; $iv = $lg3->invoice($id); ok($iv['net'] === $net && $iv['vat'] === $vat && $iv['total'] === $tot, "VAT $mode: net $net, vat $vat, total $tot");
    $wr3->terminate($admin, $kv['id'], '2026-03-01', 'next');
}

/* ---------- contract lifecycle ---------- */
$today = '2026-12-05'; $kc = $wr->contract($k['id']); ok($kc['state'] === 'expiring' && count($wr->expiring()) === 1 && $wr->expiring()[0]['days_left'] === 26, 'contract is flagged "expiring" N days before the end (auto)');
$today = '2026-06-01'; ok($wr->contract($k['id'])['state'] === 'active' && $wr->expiring() === [], 'not expiring yet in June');
$r = $wr->renew($admin, $k['id'], '31.12.2027', '550'); ok($r['end_date'] === '2027-12-31' && $r['rate'] === 55000 && $r['renewed_count'] === 1 && count($wr->versions($k['id'])) === 2, 'one-click renewal: new end date and rate, a version is kept');
$r = $wr->renew($admin, $k['id']); ok($r['end_date'] > '2027-12-31', 'renewal without input extends by the same length');
ok(refused(fn() => $wr->renew($admin, $k['id'], '2020-01-01'), 'dates_order') && refused(fn() => $wr->renew($acc, $k['id']), 'forbidden'), 'renewal: date must move forward; permission');
ok(refused(fn() => $wr->terminate($admin, $k['id'], '2026-06-30', ''), 'reason_required'), 'termination needs a reason'); $x = $wr->terminate($admin, $k['id'], '2026-06-30', 'tenant left');
ok($x['state'] === 'terminated' && $x['end_date'] === '2026-06-30' && refused(fn() => $wr->saveContract($admin, $k['id'], $in), 'contract_closed') && array_column($wr->units(), 'status', 'name')['A-1'] === 'free', 'early termination: contract closed, unit free again');
$today = '2026-12-01'; $lg->tick(); ok(!array_filter($st->t['invoices'], fn($i) => (int)$i['contract_id'] === $k['id'] && (string)$i['period_start'] >= '2026-07-01'), 'no invoices for the months after the termination');

/* ---------- utilities ---------- */
$st4 = new StoragerentMemStore(); $today4 = '2026-03-01'; $d4 = function () use (&$today4) { return $today4; }; $wr4 = new Wrms($st4, $clock, $d4); $lg4 = new Ledger($st4, $clock, $d4);
$wr4->saveWarehouse($admin, 0, ['name' => 'W']); $un4 = $wr4->saveUnit($admin, 0, ['warehouse_id' => 1, 'name' => 'U', 'area' => 10]); $tn4 = $wr4->saveTenant($admin, 0, ['name' => 'T']);
$k4 = $wr4->saveContract($admin, 0, ['tenant_id' => $tn4['id'], 'util_method' => 'metered', 'units' => [['unit_id' => $un4['id'], 'share' => 100]], 'rate' => '100'] + $in); $wr4->activate($admin, $k4['id']);
ok(refused(fn() => $lg4->saveMeter($mgr, 0, $un4['id'], 'electricity', 'E1', '6.50'), 'forbidden'), 'utility rates are tariffs: a manager cannot set them');
$mid = $lg4->saveMeter($admin, 0, $un4['id'], 'electricity', 'E1', '6.50'); $r1 = $lg4->addReading($mgr, $mid, '2026-02-28', '1000'); $r2 = $lg4->addReading($mgr, $mid, '2026-03-28', '1100.5');
ok($r1['consumption'] == 0 && $r2['consumption'] == 100.5 && $r2['charge'] === 65325, 'utilities: consumption 100.5 kWh x 6.50 = 653.25');
ok(refused(fn() => $lg4->addReading($mgr, $mid, '2026-04-02', '900'), 'reading_lower') && refused(fn() => $lg4->addReading($mgr, $mid, '2026-01-01', '2000'), 'reading_order'), 'utilities: a lower value and an out-of-order date are refused');
$id4 = $lg4->generate('2026-03-01')[0]; $it4 = array_values(array_filter($lg4->items($id4), fn($i) => $i['kind'] === 'utility')); ok(count($it4) === 1 && $it4[0]['amount'] === 65325 && $lg4->invoice($id4)['total'] === 100000 + 65325, 'metered utilities are added to the invoice once');
$today4 = '2026-04-01'; $id5 = $lg4->generate('2026-04-01')[0]; ok(!array_filter($lg4->items($id5), fn($i) => $i['kind'] === 'utility'), 'and not billed twice');
$k5 = $wr4->saveContract($admin, $k4['id'], ['util_method' => 'fixed', 'util_fixed' => '300', 'tenant_id' => $tn4['id'], 'units' => [['unit_id' => $un4['id'], 'share' => 100]], 'rate' => '100'] + $in); $today4 = '2026-05-01'; $id6 = $lg4->generate('2026-05-01')[0];
ok($lg4->invoice($id6)['total'] === 100000 + 30000, 'fixed utilities fee is added every period');

/* ---------- documents, access, requests ---------- */
ok(Wrms::checkUpload('scan.pdf', 100) === 'pdf' && refused(fn() => Wrms::checkUpload('x.php', 100), 'file_type') && refused(fn() => Wrms::checkUpload('x.pdf', 0), 'file_empty') && refused(fn() => Wrms::checkUpload('x.pdf', 9_000_000), 'file_big'), 'upload check: extension whitelist, empty, too big');
$doc = $wr->addDocument($admin, 'tenant', $t['id'], $t['id'], 'Certificate', 'cert.pdf', str_repeat('a', 32) . '.pdf', 'application/pdf', 100); ok($wr->document($doc)['title'] === 'Certificate' && count($wr->searchDocuments('cert')) === 1 && count($wr->searchDocuments('zzz')) === 0 && count($wr->searchDocuments('', 999)) === 0, 'documents: stored, searched by title and file name, limited by tenant');
$today = '2026-06-01'; $now = 1_780_000_000; $a1 = $wr->logAccess($mgr, $t['id'], null, 'Ivan', 'in', ''); ok($a1['direction'] === 'in', 'access log entry saved');
$nonstop = $wr->logAccess($mgr, $tU['id'], null, 'x', 'in', ''); ok($nonstop['outside_schedule'] === 1, 'an entry by a tenant without an active contract is flagged');
$kS = $wr->saveContract($admin, 0, ['tenant_id' => $tU['id'], 'start_date' => '2026-05-01', 'end_date' => '2026-12-31', 'access_24h' => 0, 'access_from' => '08:00', 'access_to' => '20:00', 'units' => [['unit_id' => $u1['id'], 'share' => 100]]] + $in); $wr->activate($admin, $kS['id']);
$night = $wr->logAccess($mgr, $tU['id'], null, 'x', 'in', '', gmmktime(23, 0, 0, 6, 1, 2026)); $day = $wr->logAccess($mgr, $tU['id'], null, 'x', 'out', '', gmmktime(12, 0, 0, 6, 1, 2026)); ok($night['outside_schedule'] === 1 && $day['outside_schedule'] === 0, 'access outside the contract schedule is flagged, inside is not');
$asTenant = new Ctx(44, 'tenant', $tU['id']); $rq = $wr->createRequest($asTenant, $tU['id'], 'Leaking roof', 'photo attached', $u1['id'], null);
ok($rq > 0 && refused(fn() => $wr->createRequest($asTenant, $t['id'], 'x', ''), 'forbidden') && refused(fn() => $wr->createRequest($asTenant, $tU['id'], ' ', ''), 'title_required') && count($wr->requests($tU['id'])) === 1 && refused(fn() => $wr->setRequestStatus($asTenant, $rq, 'done'), 'forbidden'), 'requests: a tenant files only for themselves; only staff change the status');
$wr->setRequestStatus($mgr, $rq, 'in_progress'); ok($wr->requests()[0]['status'] === 'in_progress', 'staff update the request status');
ok($asTenant->seesTenant($tU['id']) && !$asTenant->seesTenant($t['id']) && $acc->seesTenant($t['id']) && $asTenant->onlyTenant() === $tU['id'] && $admin->onlyTenant() === 0, 'a tenant sees only their own records');

/* ---------- notifications ---------- */
$st5 = new StoragerentMemStore(); $today5 = '2026-03-01'; $d5 = function () use (&$today5) { return $today5; }; $wr5 = new Wrms($st5, $clock, $d5); $lg5 = new Ledger($st5, $clock, $d5); $sent = []; $mailErr = null;
$nf = new Notify($st5, $lg5, $clock, $d5, function ($to, $s, $b) use (&$sent, &$mailErr) { if ($mailErr) return $mailErr; $sent[] = [$to, $s, $b]; return null; }, 'https://x.test');
$wr5->saveWarehouse($admin, 0, ['name' => 'W']); $un5 = $wr5->saveUnit($admin, 0, ['warehouse_id' => 1, 'name' => 'U', 'area' => 45]); $tn5 = $wr5->saveTenant($admin, 0, ['name' => 'Acme', 'email' => 'acme@example.com']); $tn6 = $wr5->saveTenant($admin, 0, ['name' => 'NoMail']);
$k5 = $wr5->saveContract($admin, 0, ['tenant_id' => $tn5['id'], 'end_date' => '2026-04-20', 'due_day' => 15, 'units' => [['unit_id' => $un5['id'], 'share' => 100]]] + $in); $wr5->activate($admin, $k5['id']);
$set = ['before' => '5', 'after' => '1,3,7', 'expiring' => '7', 'lang' => 'en']; $lg5->generate('2026-03-01');
[$s, $f, $sk] = $nf->run($set); ok($s === 1 && $sent[0][0] === 'acme@example.com' && str_contains($sent[0][1], 'INV-000001') && str_contains($sent[0][2], '22,500.00') && str_contains($sent[0][2], '15.03.2026') && !str_contains($sent[0][2], '{'), 'invoice issued: one e-mail with the placeholders filled');
[$s] = $nf->run($set); ok($s === 0 && count($sent) === 1, 'running again sends nothing twice');
$today5 = '2026-03-10'; $nf->run($set); ok(count($sent) === 2 && str_contains($sent[1][1], 'due in 5'), 'due soon: reminder inside the 5-day window (5 days left)');
$today5 = '2026-03-16'; $nf->run($set); ok(count($sent) === 3 && str_contains($sent[2][1], 'Overdue'), 'overdue day 1');
$today5 = '2026-03-18'; $nf->run($set); $today5 = '2026-03-22'; $nf->run($set); ok(count($sent) === 5, 'overdue day 3 and day 7 each once');
$today5 = '2026-03-30'; $nf->run($set); ok(count($sent) === 5, 'after a gap only the highest threshold is sent, not all');
$today5 = '2026-04-15'; $before = count($sent); $nf->run($set); ok(count($sent) > $before && str_contains(end($sent)[1], 'SR-2026-0001'), 'contract expiring: e-mail 30 / 7 days before the end');
foreach ($st5->t['notify_log'] as $lid => $lr) if (str_starts_with($lr['dedupe'], 'contract_expiring')) unset($st5->t['notify_log'][$lid]);
$mailErr = 'smtp down'; $today5 = '2026-04-16'; [$s, $f] = $nf->run($set); ok($s === 0 && $f === 1, 'a failed delivery is counted, not logged'); $mailErr = null;
[$s2] = $nf->run($set); [$s3] = $nf->run($set); ok($s2 === 1 && $s3 === 0, 'and goes out once when the mailer works again');
ok(Notify::parseList('5, 3,x,5;0') === [3, 5] && Notify::parseList('') === [], 'schedule list parsing');
$nf->saveTemplate($admin, 'overdue', 'en', 'LATE {invoice}', 'Dear {tenant}, {amount} is late'); ok($nf->template('overdue', 'en')[0] === 'LATE {invoice}' && str_contains($nf->template('overdue', 'ru')[0], 'Просрочен') && refused(fn() => $nf->saveTemplate($mgr, 'overdue', 'en', 'a', 'b'), 'forbidden') && refused(fn() => $nf->saveTemplate($admin, 'nope', 'en', 'a', 'b'), 'bad_request'), 'templates: editable by admin only, per language, built-in fallback');
$nf->resetTemplate($admin, 'overdue', 'en'); ok(str_contains($nf->template('overdue', 'en')[0], 'Overdue'), 'template reset');

/* ---------- reports and exports ---------- */
$d = $rp->dashboard(); ok(isset($d['occupancy'], $d['arrears'], $d['expiring'], $d['invoiced'], $d['received']) && $d['occupancy'] > 0, 'dashboard: occupancy, revenue, arrears, expiring');
$rv = $rp->run('revenue_month', '2026-03-01', '2026-06-30'); ok(count($rv['rows']) === 4 && $rv['sum']['invoiced'] > 0 && $rv['sum']['received'] > 0, 'report: revenue by month');
ok(count($rp->run('revenue_tenant', '2026-01-01', '2026-12-31')['rows']) >= 1 && count($rp->run('revenue_unit', '2026-01-01', '2026-12-31')['rows']) >= 1 && $rp->run('occupancy', '2026-03-01', '2026-06-30')['rows'][0]['percent'] > 0 && $rp->run('utilities', '2026-01-01', '2026-12-31') !== [], 'reports: tenant, unit, occupancy history, utilities');
ok(refused(fn() => $rp->run('nope', '2026-01-01', '2026-12-31'), 'bad_request'), 'unknown report refused');
$ar = $rp->run('arrears', '', ''); $lab = fn($k) => strtoupper($k); $mx = Reports::matrix($ar, $lab);
ok($mx[0][0] === 'TENANT' && count($mx) === count($ar['rows']) + 2 && end($mx)[0] === 'TOTAL', 'export matrix: header, rows, total row');
$csv = Reports::csv([['a;b', '=SUM(A1)', 12.5, "x\"y"], ['ok', '-5', 'Привет']]); ok(str_starts_with($csv, "\xEF\xBB\xBF") && str_contains($csv, '"a;b";\'=SUM(A1);12.50;"x""y"') && str_contains($csv, "ok;-5;Привет"), 'CSV: BOM, semicolons, quoting, formula injection neutralised');
$x = Xlsx::build($mx, 'Arrears'); ok(str_starts_with($x, "PK\x03\x04") && str_contains($x, 'PK\x05\x06' === '' ? '' : "PK\x05\x06"), 'XLSX: a zip');
function unzipAll(string $z): array {   // reads our own zip back: central directory, inflate, crc check
    $eocd = strrpos($z, "PK\x05\x06"); $h = unpack('vdisk/vcd/ventries/vtotal/Vsize/Voffset', substr($z, $eocd + 4, 18)); $files = []; $p = $h['offset'];
    for ($i = 0; $i < $h['total']; $i++) {
        $c = unpack('Vsig/vvm/vvn/vflags/vmethod/vtime/vdate/Vcrc/Vcsize/Vsize/vnlen/velen/vclen/vdisk/vint/Vext/Voff', substr($z, $p, 46)); $name = substr($z, $p + 46, $c['nlen']); $p += 46 + $c['nlen'] + $c['elen'] + $c['clen'];
        $l = unpack('vnlen/velen', substr($z, $c['off'] + 26, 4)); $raw = substr($z, $c['off'] + 30 + $l['nlen'] + $l['elen'], $c['csize']); $data = $c['method'] === 8 ? gzinflate($raw) : $raw;
        if (crc32($data) !== $c['crc'] || strlen($data) !== $c['size']) return []; $files[$name] = $data;
    }
    return $files;
}
$files = unzipAll($x); ok(isset($files['xl/workbook.xml'], $files['xl/worksheets/sheet1.xml'], $files['[Content_Types].xml']) && str_contains($files['xl/worksheets/sheet1.xml'], 'TENANT') && str_contains($files['xl/worksheets/sheet1.xml'], 'TOTAL') && @simplexml_load_string($files['xl/worksheets/sheet1.xml']) !== false, 'XLSX: entries readable with valid CRC, the arrears sheet is well-formed XML with the data');
ok(@simplexml_load_string(unzipAll(Xlsx::build([['H'], ["bad \x01 <&> \"q\" Привет", 5, 1.5]]))['xl/worksheets/sheet1.xml']) !== false, 'XLSX: control characters and markup in cells are escaped');

/* ---------- Offers ---------- */
$of = new Offers($st, $clock); $photo = fn($p, $id) => "/photo/{$p['id']}";
$form = ['name' => 'Dry warehouse', 'city' => 'Kazan', 'street' => 'Lenina 1', 'lat' => '55.79', 'lon' => '49.12', 'area' => '1 200', 'price_m2' => '450', 'ceiling_height' => '9', 'floor_load' => '5', 'power' => 1, 'power_kw' => '200', 'video' => 1, 'parking' => 'Trucks x5', 'access_24h' => 1, 'security_company' => 1, 'prohibitions' => 'No explosives', 'junk' => 'x'];
$l = $of->save(7, false, 0, $form, 'Olga'); ok($l['id'] > 0 && $l['area'] == 1200 && $l['price_m2'] === 45000 && $l['d']['power_kw'] == 200.0 && !isset($l['d']['junk']) && $l['user_id'] === 7 && $l['contact_name'] === 'Olga', 'listing saved: spec fields, price in kopecks, unknown input dropped');
ok($l['svc_video'] === 1 && $l['svc_parking'] === 1 && $l['svc_24h'] === 1 && $l['svc_security'] === 1, 'service icons come from the form: video, parking, 24/7, security');
$l2 = $of->save(8, false, 0, ['name' => 'Cold', 'city' => 'Moscow', 'area' => '50', 'price_m2' => '900', 'access_24h' => 0, 'access_from' => '08:00', 'access_to' => '18:00']); ok($l2['svc_24h'] === 0 && $l2['svc_security'] === 0 && $l2['d']['access_from'] === '08:00', 'second listing: no services, working hours');
foreach ([[['name' => ''], 'name_required'], [['city' => ''], 'city_required'], [['area' => '0'], 'area_required'], [['area' => 'abc'], 'number_invalid'], [['price_m2' => 'x'], 'amount_invalid'], [['lat' => '99', 'lon' => '1'], 'coords_invalid'], [['status' => 'x'], 'status_invalid'], [['access_24h' => 0, 'access_from' => '8am', 'access_to' => '18:00'], 'time_invalid'], [['access_24h' => 0, 'access_from' => '08:00', 'access_to' => ''], 'time_invalid'], [['floor_load' => '99999'], 'number_invalid']] as [$bad, $code]) ok(refused(fn() => $of->save(7, false, 0, $bad + $form), $code), "listing: refuses $code");
ok(refused(fn() => $of->save(0, false, 0, $form), 'forbidden'), 'a guest cannot post');
ok(refused(fn() => $of->save(9, false, $l['id'], $form), 'forbidden') && $of->save(9, true, $l['id'], ['name' => 'Renamed'] + $form)['name'] === 'Renamed' && $of->save(7, false, $l['id'], $form)['name'] === 'Dry warehouse', 'only the owner (or a platform admin) edits a listing');
$cards = $of->cards($of->listings(), $photo); ok(count($cards) === 2 && !isset($cards[0]['user_id']) && !array_key_exists('contact_name', $cards[0]) && !array_key_exists('data', $cards[0]) && $cards[1]['services']['video'] === true, 'public card: map point, address, name, area, price, service icons; no owner data');

ok(refused(fn() => $of->setStatus(7, false, $l2['id'], 'draft'), 'forbidden'), 'status of someone else\'s listing is protected'); $of->setStatus(8, false, $l2['id'], 'draft');
ok(count($of->listings()) === 1 && count($of->listings('', 8)) === 1 && count($of->listings('kazan')) === 1 && count($of->listings('moscow')) === 0, 'drafts are not public; my list shows mine; search by city');
$pid = $of->addPhoto(7, false, $l['id'], str_repeat('b', 32) . '.jpg', 'image/jpeg', 1000); $c1 = $of->card($of->get($l['id']), $photo); ok($c1['photo'] === "/photo/$pid", 'photo becomes the card image');
ok(refused(fn() => $of->addPhoto(9, false, $l['id'], str_repeat('c', 32) . '.jpg', 'image/jpeg', 1), 'forbidden') && Offers::checkPhoto('a.JPG', 10) === 'jpg' && refused(fn() => Offers::checkPhoto('a.svg', 10), 'file_type') && refused(fn() => Offers::checkPhoto('a.png', 99_000_000), 'file_big'), 'photo: owner only, images only (no SVG), size limit');
for ($i = 1; $i < Offers::MAX_PHOTOS; $i++) $of->addPhoto(7, false, $l['id'], sprintf('%032d', $i) . '.jpg', 'image/jpeg', 1); ok(refused(fn() => $of->addPhoto(7, false, $l['id'], str_repeat('d', 32) . '.jpg', 'image/jpeg', 1), 'photos_max'), 'at most ' . Offers::MAX_PHOTOS . ' photos');
/* prefill from WRMS */
$pf = $of->prefill($wr->warehouses()[0], [$u1, $u2]); ok($pf['name'] === 'Main' && $pf['city'] === 'Kazan' && $pf['street'] === 'Lenina 1' && $pf['area'] === '145' && $pf['ceiling_height'] === '4' && ($pf['power'] ?? 0) === 1 && $pf['warehouse_id'] === $w['id'], 'prefill: the listing form takes address, area, height, features from the WRMS warehouse and units');
/* feeds between domains */
$feed = $of->feed($photo, 'https://a.test'); $json = json_encode($feed); ok($feed['v'] === 1 && count($feed['listings']) === 1 && !str_contains($json, 'contact') && !str_contains($json, 'user_id'), 'feed: public cards only');
$rem = Offers::parseFeed(json_encode(['v' => 1, 'listings' => [['id' => 5, 'name' => '<b>Far</b>', 'city' => 'Sochi', 'lat' => 43.6, 'lon' => 39.7, 'area' => 80, 'price_m2' => 70000, 'services' => ['video' => 1], 'photo' => 'javascript:alert(1)', 'url' => 'https://b.test/p/5'], ['id' => 'x'], ['id' => 6, 'name' => '', 'city' => 'x'], ['id' => 7, 'name' => 'BadGeo', 'lat' => 500, 'lon' => 1]]]), 'b.test');
ok(count($rem) === 2 && $rem[0]['photo'] === '' && $rem[0]['url'] === 'https://b.test/p/5' && $rem[0]['origin'] === 'b.test' && $rem[1]['lat'] === null && $rem[0]['name'] === '<b>Far</b>', 'remote feed is untrusted: bad rows dropped, javascript: link dropped, bad coordinates dropped (escaping happens on output)');
ok(Offers::parseFeed('not json', 'x') === [] && Offers::parseFeed('{"v":2,"listings":[]}', 'x') === [], 'remote feed: wrong version / garbage gives nothing');
$calls = 0; $fetch = function ($u) use (&$calls, $json) { $calls++; return $json; }; $ofr = new Offers($st, $clock);
$r1 = $ofr->remote(['https://peer.test/api/storagerent/v1/listings', 'http://insecure.test/x', 'ftp://x'], $fetch); $r2 = $ofr->remote(['https://peer.test/api/storagerent/v1/listings'], $fetch); ok(count($r1) === 1 && count($r2) === 1 && $calls === 1, 'peers: https only, fetched once and cached');
$now += 1000; $ofr->remote(['https://peer.test/api/storagerent/v1/listings'], fn($u) => null); ok(count($ofr->remote(['https://peer.test/api/storagerent/v1/listings'], fn($u) => null)) === 1, 'a failing peer keeps its old copy');

/* MySQL reserved words must never be bare column names in the migrations (STORED broke 002_extras.sql on a real server) */
$reserved = ['stored', 'virtual', 'generated', 'rank', 'groups', 'system', 'window', 'lateral', 'empty', 'function', 'range', 'rows', 'row', 'usage', 'read', 'write', 'key', 'keys', 'index', 'order', 'group', 'limit', 'check', 'condition', 'release', 'signal', 'schema', 'over', 'except', 'intersect', 'cube', 'persist', 'recursive', 'grouping', 'of', 'lead', 'lag', 'ntile', 'first_value', 'last_value', 'dense_rank', 'percent_rank', 'cume_dist', 'nth_value', 'row_number'];
$bad = [];
foreach (glob("$dir/migrations/*.sql") as $mf) foreach (preg_split('/\R/', (string)file_get_contents($mf)) as $ln) {
    if (preg_match('/^\s+([a-z_]+)\s+(?:VARCHAR|INT|BIGINT|TINYINT|SMALLINT|DECIMAL|DATE|DATETIME|TEXT|MEDIUMTEXT|LONGTEXT|CHAR|JSON|BLOB)\b/i', $ln, $m) && in_array(strtolower($m[1]), $reserved, true)) $bad[] = basename($mf) . ':' . $m[1];
}
ok(!$bad, 'no reserved word as a bare column name in migrations' . ($bad ? ' (' . implode(', ', $bad) . ')' : ''));

/* ---- Contact with Lessor: the message goes through the Messenger client (a fake one here) */
final class FakeMessenger
{
    public array $calls = []; public array $known = []; public bool $ready = true; public string $sendError = ''; public bool $stayUnknown = false;
    public function ready(): bool { return $this->ready; }
    public function domain(): string { return 'shop.test'; }
    public function call(string $m, string $op, array $q = [], ?array $body = null, ?string $raw = null, int $t = 8, ?string $asUid = null): array
    {
        $this->calls[] = [$op, $asUid, $body];
        if ($op === 'me') { if (!$this->stayUnknown) $this->known[$asUid ?? '8'] = true; return ['ok' => true, 'data' => [], 'error' => null]; }
        if ($this->sendError !== '') return ['ok' => false, 'data' => [], 'error' => $this->sendError];
        return isset($this->known[explode('@', (string)$body['to'])[0]]) ? ['ok' => true, 'data' => ['id' => 1], 'error' => null] : ['ok' => false, 'data' => [], 'error' => 'no_user'];
    }
}
use SysferaStoragerent\Contact;
$m = new FakeMessenger(); $sid = Contact::send($m, ['name' => 'Buyer', 'email' => 'b@example.com', 'role' => 'user'], 7, 'Olga', "  Hello, is it free?  ", 'https://shop.test/p/storagerent/offers/3');
$ops = array_map(fn($c) => $c[0] . ($c[1] !== null ? "@{$c[1]}" : ''), $m->calls);
ok($sid === '7@shop.test' && $ops === ['me', 'send', 'me@7', 'send'], 'contact: the lessor is not in the hub yet - he is introduced by name and the message is sent again (' . implode(',', $ops) . ')');
$sent = array_values(array_filter($m->calls, fn($c) => $c[0] === 'send'))[1][2]; ok($sent['to'] === '7@shop.test' && str_starts_with($sent['text'], 'Hello, is it free?') && str_ends_with($sent['text'], 'https://shop.test/p/storagerent/offers/3') && $sent['file'] === '', 'contact: the text is trimmed and carries the link of the listing');
$intro = array_values(array_filter($m->calls, fn($c) => $c[0] === 'me' && $c[1] === '7'))[0][2]; ok($intro === ['name' => 'Olga', 'email' => '', 'admin' => false], 'contact: the lessor is introduced by name only - no e-mail, not an administrator');
$m2 = new FakeMessenger(); $m2->known['7'] = true; Contact::send($m2, ['name' => 'Admin', 'email' => 'a@example.com', 'role' => 'admin'], 7, 'Olga', 'Hi there', 'https://x/y');
ok(array_column($m2->calls, 0) === ['me', 'send'] && $m2->calls[0][2]['admin'] === true, 'contact: a known lessor gets the message at once; the sender keeps his admin flag');
$m3 = new FakeMessenger(); $m3->stayUnknown = true; ok(refused(fn() => Contact::send($m3, [], 7, '', 'Hi there', 'u'), 'messenger_no_user') && count(array_filter($m3->calls, fn($c) => $c[0] === 'send')) === 2, 'contact: a lessor who cannot be introduced - a clear error after one retry only');
foreach (['blocked' => 'messenger_blocked', 'rate' => 'messenger_rate', 'network' => 'messenger_failed', 'weird' => 'messenger_failed'] as $hub => $mine) { $m4 = new FakeMessenger(); $m4->known['7'] = true; $m4->sendError = $hub; ok(refused(fn() => Contact::send($m4, [], 7, 'O', 'Hi there', 'u'), $mine), "contact: hub says $hub -> $mine"); }
$off = new FakeMessenger(); $off->ready = false;
ok(refused(fn() => Contact::send(null, [], 7, 'O', 'Hi there', 'u'), 'messenger_off') && refused(fn() => Contact::send($off, [], 7, 'O', 'Hi there', 'u'), 'messenger_off') && !$off->calls, 'contact: Messenger missing or not connected - refused before any call');
ok(refused(fn() => Contact::send(new FakeMessenger(), [], 7, 'O', ' x ', 'u'), 'message_empty') && refused(fn() => Contact::send(new FakeMessenger(), [], 7, 'O', str_repeat('a', Contact::MAX + 1), 'u'), 'message_long') && Contact::send($m2, [], 7, 'O', str_repeat('я', Contact::MAX), 'u') === '7@shop.test', 'contact: empty and over-long messages are refused, the maximum is accepted');

echo $fail ? "\n$fail FAILED\n" : "\nAll good\n"; exit($fail ? 1 : 0);
