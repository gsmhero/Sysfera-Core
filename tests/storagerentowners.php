<?php
declare(strict_types=1);
/** Storage Rent: every user manages their own storages and never sees or touches the data of another owner (OwnerStore). No database. Run: php tests/storagerentowners.php */
$dir = dirname(__DIR__) . '/core/plugins/storagerent';
foreach (['Err', 'Store', 'Billing', 'Ctx', 'Wrms', 'Ledger', 'Notify', 'Reports', 'Offers', 'OwnerStore'] as $c) require "$dir/lib/$c.php";
require __DIR__ . '/StoragerentMemStore.php';
use SysferaStoragerent\{Ctx, Wrms, Ledger, Notify, Reports, OwnerStore, Err};
$fail = 0; function ok(bool $c, string $n): void { global $fail; echo ($c ? '  ok   ' : '  FAIL ') . $n . "\n"; if (!$c) $fail++; }
function refused(callable $f, string $code): bool { try { $f(); } catch (Err $e) { return $e->errCode === $code; } return false; }

$raw = new StoragerentMemStore(); $now = 1_780_000_000; $today = '2026-03-01'; $clock = fn() => $now; $date = function () use (&$today) { return $today; };
$svc = function (int $scope, bool $legacy = false) use ($raw, $clock, $date): array { $st = new OwnerStore($raw, $scope, $legacy); $wr = new Wrms($st, $clock, $date); $lg = new Ledger($st, $clock, $date); return ['st' => $st, 'wr' => $wr, 'lg' => $lg, 'rp' => new Reports($st, $lg, $wr, $date)]; };
$A = new Ctx(10, 'owner'); $B = new Ctx(20, 'owner'); $staff = new Ctx(1, 'admin');
$a = $svc(10); $b = $svc(20); $all = $svc(0);

/* roles */
ok(Ctx::for(5, false, [], null, true)->role === 'owner' && Ctx::for(5, false, [], null, false)->role === 'none' && Ctx::for(5, false, [], 3, true)->role === 'tenant' && Ctx::for(5, false, [], 3, true, true)->role === 'owner'
    && Ctx::for(5, true, [], null, true)->role === 'admin' && Ctx::for(5, false, ['storagerent.manager'], null, true)->role === 'manager', 'roles: a signed-in user is an owner, a tenant stays a tenant unless they own storage, staff roles win');
ok($A->can('write') && $A->can('finance') && $A->can('tariff') && !$A->can('settings') && $A->owner() && !$staff->owner(), 'an owner works with everything of their own but not with the site settings');

/* owner A builds a rental business */
$wa = $a['wr']->saveWarehouse($A, 0, ['name' => 'A-store', 'city' => 'Kazan']); $ua = $a['wr']->saveUnit($A, 0, ['warehouse_id' => $wa['id'], 'name' => 'A-1', 'type' => 'dry', 'area' => '50']);
$ta = $a['wr']->saveTenant($A, 0, ['kind' => 'legal', 'name' => 'Acme', 'tin' => '7707083893', 'user_id' => 44]);
$in = fn($t, $u) => ['tenant_id' => $t, 'start_date' => '01.03.2026', 'end_date' => '2026-12-31', 'rate_type' => 'm2', 'rate' => '500', 'billing_months' => 1, 'due_day' => 5, 'grace_days' => 3, 'penalty_type' => 'none', 'penalty_value' => '0', 'util_method' => 'included', 'access_24h' => 1, 'units' => [['unit_id' => $u, 'share' => 100]]];
$ka = $a['wr']->saveContract($A, 0, $in($ta['id'], $ua['id'])); $a['wr']->activate($A, $ka['id']);
ok((int)$raw->t['warehouses'][$wa['id']]['owner_id'] === 10 && (int)$raw->t['units'][$ua['id']]['own_uid'] === 10 && (int)$raw->t['tenants'][$ta['id']]['own_uid'] === 10 && (int)$raw->t['contracts'][$ka['id']]['own_uid'] === 10, 'everything A creates is stamped with A');
$today = '2026-03-05'; $all['lg']->tick($today);   // the daily run works on the whole site
$inv = $all['lg']->invoices(); ok(count($inv) >= 1 && (int)$inv[0]['own_uid'] === 10, 'the daily run stamps an invoice with the owner of its contract (' . count($inv) . ' invoice)');
$pay = $a['lg']->registerPayment($A, $ta['id'], null, '2026-03-05', '1000', 'bank', 'P-1');
ok((int)$raw->t['payments'][$pay['id'] ?? array_key_last($raw->t['payments'])]['own_uid'] === 10, 'a payment is stamped with the owner of its tenant');
ok(count($a['lg']->invoices()) === count($inv) && count($a['wr']->units()) === 1 && count($a['wr']->tenants()) === 1 && count($a['wr']->contracts()) === 1 && count($a['wr']->warehouses()) === 1, 'A sees their own data');

/* owner B sees nothing of A and cannot touch it */
ok(!$b['wr']->units() && !$b['wr']->tenants() && !$b['wr']->contracts() && !$b['wr']->warehouses() && !$b['lg']->invoices() && !$b['lg']->payments() && !$b['wr']->requests() && !$b['lg']->meters(), 'B sees none of the data of A');
ok($b['wr']->tenant($ta['id']) === null && $b['wr']->contract($ka['id']) === null && $b['lg']->invoice((int)$inv[0]['id']) === null, 'B cannot open a tenant, a contract or an invoice of A by its number');
ok(refused(fn() => $b['wr']->saveUnit($B, $ua['id'], ['warehouse_id' => $wa['id'], 'name' => 'x', 'area' => 1]), 'warehouse_required') && refused(fn() => $b['wr']->saveWarehouse($B, $wa['id'], ['name' => 'hijack']), 'not_found')
    && refused(fn() => $b['wr']->saveTenant($B, $ta['id'], ['kind' => 'legal', 'name' => 'hijack']), 'not_found'), 'B cannot change the warehouse, a unit or a tenant of A');
ok($raw->t['warehouses'][$wa['id']]['name'] === 'A-store' && $raw->t['tenants'][$ta['id']]['name'] === 'Acme' && isset($raw->t['units'][$ua['id']]), 'nothing of A changed');
ok(refused(fn() => $b['st']->delete('units', $ua['id']), 'not_found') && refused(fn() => $b['st']->update('tenants', $ta['id'], ['name' => 'x']), 'not_found') && isset($raw->t['units'][$ua['id']]), 'the store refuses a write on a row of somebody else');
$wb = $b['wr']->saveWarehouse($B, 0, ['name' => 'B-store']); $ub = $b['wr']->saveUnit($B, 0, ['warehouse_id' => $wb['id'], 'name' => 'B-1', 'type' => 'dry', 'area' => '20']); $tb = $b['wr']->saveTenant($B, 0, ['kind' => 'legal', 'name' => 'Bcorp']);
ok(refused(fn() => $b['wr']->saveContract($B, 0, $in($ta['id'], $ub['id'])), 'tenant_required') || refused(fn() => $b['wr']->saveContract($B, 0, $in($ta['id'], $ub['id'])), 'not_found'), "B cannot make a contract with A's tenant");
$threw = false; try { $b['wr']->saveContract($B, 0, $in($tb['id'], $ua['id'])); } catch (Err $e) { $threw = true; } ok($threw, "B cannot rent out A's unit");
$kb = $b['wr']->saveContract($B, 0, $in($tb['id'], $ub['id']));
ok($kb['number'] !== $ka['number'], 'contract numbers stay unique over the whole site (' . $ka['number'] . ' / ' . $kb['number'] . ')');
$a['wr']->saveTenant($A, $ta['id'], ['kind' => 'legal', 'name' => 'Acme', 'tin' => '7707083893', 'user_id' => 44]);
ok(refused(fn() => $b['wr']->saveTenant($B, 0, ['kind' => 'legal', 'name' => 'Other', 'user_id' => 44]), 'user_taken'), 'a Sysfera user is linked to one tenant on the whole site, whoever the owner is');
ok(count($a['wr']->contracts()) === 1 && count($b['wr']->contracts()) === 1 && count($all['wr']->contracts()) === 2, 'the staff see both; each owner only their own');
$rb = $b['rp']->run('revenue_tenant', '2026-01-01', '2026-12-31'); ok(!str_contains(json_encode($rb), 'Acme'), "B's reports do not contain A's tenants");

/* what an owner may not do */
$n = new Notify($b['st'], $b['lg'], $clock, $date, fn() => true, 'site');
ok(refused(fn() => $n->saveTemplate($B, 'reminder', 'en', 's', 'b'), 'forbidden') && refused(fn() => $n->resetTemplate($B, 'reminder', 'en'), 'forbidden'), 'an owner cannot change the notification templates of the site');

/* the older rows of the site (owner 0): the platform administrator manages them, an ordinary owner never sees them */
$raw->insert('units', ['warehouse_id' => 0, 'name' => 'legacy', 'type' => 'dry', 'area' => 1, 'status_manual' => 'auto', 'own_uid' => 0, 'features' => '', 'created_at' => $now]);
$adm = $svc(1, true);
ok(count($adm['wr']->units()) === 1 && $adm['wr']->units()[0]['name'] === 'legacy' && count($a['wr']->units()) === 1 && $a['wr']->units()[0]['name'] === 'A-1', 'the administrator sees the old rows of the site, an owner does not');
echo $fail ? "\n$fail FAILED\n" : "\nAll good\n"; exit($fail ? 1 : 0);
