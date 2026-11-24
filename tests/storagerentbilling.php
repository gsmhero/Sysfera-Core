<?php
declare(strict_types=1);
/** Billing rules of Storage Rent (pure functions): rent, VAT, penalties, partial payments, FIFO, statuses, periods. Run: php tests/storagerentbilling.php */
$dir = dirname(__DIR__) . '/core/plugins/storagerent';
require "$dir/lib/Billing.php";
use SysferaStoragerent\Billing as B;
$fail = 0; function ok(bool $c, string $n): void { global $fail; echo ($c ? '  ok   ' : '  FAIL ') . $n . "\n"; if (!$c) $fail++; }

/* ---- money and dates ---- */
ok(B::kop('22 500,50') === 2250050 && B::kop('22500.5') === 2250050 && B::kop('0') === 0 && B::kop('-10,5') === -1050 && B::kop('abc') === null && B::kop('') === null && B::kop('1.234') === null, 'kop: parses ru/en amounts, refuses junk');
ok(B::fmt(2250000) === "22\u{a0}500,00" && B::fmt(2250000, 'en') === '22,500.00' && B::fmt(-5, 'en') === '-0.05' && B::dec(2250050) === '22500.50', 'fmt: ru and en');
ok(B::rdiv(5, 2) === 3 && B::rdiv(-5, 2) === -3 && B::rdiv(4, 3) === 1, 'rdiv: half away from zero');
ok(B::parseDate('01.12.2026') === '2026-12-01' && B::parseDate('2026-2-3') === '2026-02-03' && B::parseDate('2026-02-30') === null && B::parseDate('x') === null && B::dmy('2026-12-01') === '01.12.2026', 'dates: DD.MM.YYYY in and out');
ok(B::addMonths('2026-01-31', 1) === '2026-02-28' && B::addMonths('2026-11-15', 3) === '2027-02-15' && B::addDays('2026-02-28', 1) === '2026-03-01' && B::days('2026-01-01', '2026-03-01') === 59, 'dates: month and day arithmetic');
ok(B::dueDate('2026-02-01', 31) === '2026-02-28' && B::dueDate('2026-03-01', 5) === '2026-03-05', 'due day is cut to the month length');

/* ---- acceptance: 500 RUB/m2 x 45 m2, due day 5 -> 22 500 RUB on the 1st of each month ---- */
$r = B::monthlyRent('m2', 50000, [['area' => 45, 'share' => 100]]);
ok($r['amount'] === 2250000 && $r['qty'] === 45.0, 'ACCEPTANCE: 500 RUB/m2 x 45 m2 = 22 500 RUB');
$per = B::periods('2026-03-17', '2026-12-31', 1, '2026-05-01'); ok(count($per) === 3 && $per[0] === ['2026-03-01', '2026-03-31'] && $per[2] === ['2026-05-01', '2026-05-31'], 'periods: invoices exist from the 1st of each month up to today');
ok(B::periods('2026-03-17', '2026-12-31', 1, '2026-03-31') === [['2026-03-01', '2026-03-31']], 'periods: the 1st of the next month has not come yet');
ok(count(B::periods('2026-01-01', '2026-12-31', 3, '2026-12-31')) === 4 && B::periods('2026-01-01', '2026-12-31', 3, '2026-12-31')[1] === ['2026-04-01', '2026-06-30'], 'periods: quarterly billing');
ok(B::periods('2026-01-01', '2026-02-10', 1, '2026-06-01') === [['2026-01-01', '2026-01-31'], ['2026-02-01', '2026-02-28']], 'periods: none after the contract ended');
ok(B::dueDate('2026-04-01', 5) === '2026-04-05', 'due date of the April invoice is 05.04');
ok(B::monthlyRent('m2', 50000, [['area' => 30, 'share' => 50], ['area' => 10, 'share' => 100]])['amount'] === 1250000, 'rent: share of a unit (50% of 30 m2 + 10 m2 = 25 m2)');
ok(B::monthlyRent('m3', 12000, [['volume' => 100, 'area' => 5, 'share' => 100]])['amount'] === 1200000, 'rent: per m3');
ok(B::monthlyRent('pallet', 80000, [['pallets' => 20, 'share' => 25]])['amount'] === 400000 && B::monthlyRent('pallet', 80000, [['pallets' => 3, 'share' => 10]])['amount'] === 80000, 'rent: per pallet spot (share of the places, at least one)');
ok(B::monthlyRent('fixed', 3500000, [['area' => 1, 'share' => 100], ['area' => 2, 'share' => 100]])['amount'] === 3500000, 'rent: fixed once per period');
ok(B::monthlyRent('m2', 33333, [['area' => 7.77, 'share' => 33.33]])['amount'] === B::rdiv(33333 * 777 * 3333, 1000000), 'rent: rounds to the kopeck');
ok(B::coverage('2026-04-01', '2026-04-30', '2026-04-11', '2026-12-31') === [20, 30] && B::coverage('2026-04-01', '2026-04-30', '2026-01-01', '2026-04-14') === [14, 30] && B::coverage('2026-04-01', '2026-04-30', '2026-01-01', '2026-12-31') === [30, 30], 'coverage: partial first / last month');

/* ---- VAT ---- */
ok(B::vat('none', 20, 2250000) === ['net' => 2250000, 'vat' => 0, 'total' => 2250000], 'VAT: none');
ok(B::vat('added', 20, 2250000) === ['net' => 2250000, 'vat' => 450000, 'total' => 2700000], 'VAT: 20% added on top (22 500 -> 27 000)');
ok(B::vat('included', 20, 2700000) === ['net' => 2250000, 'vat' => 450000, 'total' => 2700000], 'VAT: 20% included in the price');
ok(B::vat('added', 20, 2250000, 112500) === ['net' => 2362500, 'vat' => 450000, 'total' => 2812500], 'VAT: penalties carry no VAT');
ok(B::vat('added', 20, 1)['vat'] === 0 && B::vat('added', 20, 3)['vat'] === 1 && B::vat('included', 20, 100)['vat'] === 17, 'VAT: rounds to the kopeck');
ok(B::vat('added', 0, 1000)['vat'] === 0, 'VAT: rate 0 adds nothing');

/* ---- penalty: 0.5 %/day from day 1 after the grace period ---- */
$P = 2250000; $due = '2026-04-05';
ok(B::penalty($P, $due, 3, 'percent', 0.5, '2026-04-08') === 0, 'ACCEPTANCE: no penalty inside the grace period (due 05.04 + 3 days)');
ok(B::penalty($P, $due, 3, 'percent', 0.5, '2026-04-09') === 11250, 'ACCEPTANCE: penalty starts on day 1 after the grace period: 0.5% of 22 500 = 112,50');
ok(B::penalty($P, $due, 3, 'percent', 0.5, '2026-04-18') === 112500, 'penalty: 10 late days = 1 125,00');
ok(B::penalty($P, $due, 0, 'percent', 0.5, '2026-04-05') === 0 && B::penalty($P, $due, 0, 'percent', 0.5, '2026-04-06') === 11250, 'penalty: without grace day 1 is the day after the due date');
ok(B::penalty($P, $due, 0, 'fixed', 50000, '2026-04-09') === 200000, 'penalty: fixed 500 RUB per day x 4 days');
ok(B::penalty($P, $due, 0, 'none', 1, '2026-06-09') === 0 && B::penalty(0, $due, 0, 'percent', 0.5, '2026-06-09') === 0 && B::penalty($P, $due, 0, 'percent', 0, '2026-06-09') === 0, 'penalty: none / zero cases');
ok(B::penalty($P, $due, 0, 'percent', 0.5, '2026-07-01', [], 10) === 225000, 'penalty: capped at 10% of the invoice');
/* partial payment of 10 000 on day 4 of lateness: only the unpaid 12 500 earns the penalty afterwards */
$withPay = B::penalty($P, $due, 0, 'percent', 0.5, '2026-04-10', [['2026-04-09', 1000000]]);   // days 06,07,08 on 22 500 (3 x 11 250) + 09,10 on 12 500 (2 x 6 250)
ok($withPay === 3 * 11250 + 2 * 6250, 'penalty: a partial payment lowers the base from its day on');
ok(B::penalty($P, $due, 0, 'percent', 0.5, '2026-05-30', [['2026-04-07', $P]]) === 11250, 'penalty: paid in full on day 2 stops it');
ok(B::penalty($P, $due, 0, 'percent', 0.5, '2026-04-10', [['2026-04-08', $P]]) === 11250 * 2, 'penalty: reductions given in any order');

/* ---- partial payments and statuses ---- */
$st = fn(int $settled, string $today, string $stored = 'issued') => B::invoiceStatus($stored, $P, $settled, $due, $today);
ok($st(0, '2026-04-01') === 'issued' && $st(0, '2026-04-05') === 'issued' && $st(0, '2026-04-06') === 'overdue', 'status: issued, overdue the day after the due date');
ok($st(1000000, '2026-04-01') === 'partially_paid' && $P - 1000000 === 1250000, 'ACCEPTANCE: 10 000 of 22 500 paid -> partially_paid, 12 500 left');
ok($st($P, '2026-05-01') === 'paid' && $st(0, '2026-05-01', 'cancelled') === 'cancelled' && $st(0, '2026-05-01', 'draft') === 'draft', 'status: paid wins; draft and cancelled stay');
ok(B::isLate('issued', $P, 1000000, $due, '2026-04-10') && !B::isLate('issued', $P, $P, $due, '2026-04-10') && !B::isLate('cancelled', $P, 0, $due, '2026-04-10'), 'late flag also for a part-paid invoice');
[$al, $rest] = B::fifo(3000000, [['id' => 2, 'due' => '2026-05-05', 'out' => 2250000], ['id' => 1, 'due' => '2026-04-05', 'out' => 2250000]]);
ok($al === [1 => 2250000, 2 => 750000] && $rest === 0, 'FIFO: the oldest invoice is settled first');
[$al, $rest] = B::fifo(5000000, [['id' => 1, 'due' => '2026-04-05', 'out' => 2250000]]); ok($al === [1 => 2250000] && $rest === 2750000, 'FIFO: an overpayment is left as credit');
[$al, $rest] = B::fifo(1000000, [['id' => 1, 'due' => '2026-04-05', 'out' => 2250000]]); ok($al === [1 => 1000000] && $rest === 0, 'FIFO: a partial payment');
[$al] = B::fifo(100, [['id' => 5, 'due' => '2026-04-05', 'out' => 0], ['id' => 3, 'due' => '2026-04-05', 'out' => 50]]); ok($al === [3 => 50], 'FIFO: settled invoices are skipped; equal dates by id');

/* ---- aging, contract state, utilities, templates ---- */
ok(B::agingBucket(0) === 'current' && B::agingBucket(1) === '1-30' && B::agingBucket(30) === '1-30' && B::agingBucket(31) === '31-60' && B::agingBucket(60) === '31-60' && B::agingBucket(61) === '60+', 'aging buckets 1-30 / 31-60 / 60+');
ok(B::contractState('active', '2026-01-01', '2026-12-31', '2026-06-01', 30) === 'active' && B::contractState('active', '2026-01-01', '2026-12-31', '2026-12-01', 30) === 'expiring' && B::contractState('active', '2026-01-01', '2026-12-31', '2026-12-31', 30) === 'expiring' && B::contractState('active', '2026-01-01', '2026-12-31', '2027-01-01', 30) === 'ended', 'contract: auto-flag N days before the end, ended after');
ok(B::contractState('draft', '2026-01-01', '2026-12-31', '2027-06-01', 30) === 'draft' && B::contractState('terminated', '2026-01-01', '2026-12-31', '2026-02-01', 30) === 'terminated', 'contract: draft and terminated are manual');
ok(B::consumption(100, 135.5) === 35.5 && B::consumption(100, 90) === null && B::consumption(9990, 10, 10000) === 20.0 && B::utilityCharge(35.5, 650) === 23075, 'utilities: consumption, rollover, charge');
ok(B::fill('Hello {tenant}, pay {amount} by {due_date} {x}', ['tenant' => 'Acme', 'amount' => '22 500', 'due_date' => '05.04.2026']) === 'Hello Acme, pay 22 500 by 05.04.2026 {x}', 'templates: placeholders');

echo $fail ? "\n$fail FAILED\n" : "\nAll good\n"; exit($fail ? 1 : 0);
