<?php
declare(strict_types=1);
/** Logic tests of the Contracts plugin: the whole life of a contract against an in-memory store. Run: php tests/contracts.php */
foreach (['mb_substr', 'mb_strlen', 'mb_strtolower'] as $f) if (!function_exists($f)) eval("function $f(string \$s, int \$a = 0, ?int \$l = null) { return " . ($f === 'mb_strlen' ? 'strlen($s)' : ($f === 'mb_strtolower' ? 'strtolower($s)' : '$l === null ? substr($s, $a) : substr($s, $a, $l)')) . '; }');
$dir = dirname(__DIR__) . '/core/plugins/contracts';
foreach (['Err', 'Ctx', 'Store', 'Svc'] as $c) require "$dir/lib/$c.php";
require __DIR__ . '/ContractsMemStore.php';
use SysferaContracts\{Svc, Ctx, Err};
$fail = 0; function ok(bool $c, string $n): void { global $fail; echo ($c ? '  ok   ' : '  FAIL ') . $n . "\n"; if (!$c) $fail++; }
function err(callable $f): string { try { $f(); return ''; } catch (Err $e) { return $e->errCode; } }

$now = strtotime('2026-11-01 10:00:00'); $st = new ContractsMemStore(); $mails = [];
$people = [1 => 'Admin', 2 => 'Anna Author', 3 => 'Boris Builder', 4 => 'Clara Coder', 5 => 'Dan Outsider'];
$users = fn(array $ids) => array_intersect_key(array_map(fn($n) => ['id' => 0, 'name' => $n, 'email' => 'x@x.x', 'lang' => 'en'], $people), array_flip($ids));
$svc = new Svc($st, function () use (&$now) { return $now; }, $users, function (int $uid, string $kind, array $d, string $url) use (&$mails) { $mails[] = [$uid, $kind, $url]; }, ['stale_days' => 30]);
$adm = new Ctx(1, true, 'Admin'); $anna = new Ctx(2, false, 'Anna Author'); $boris = new Ctx(3, false, 'Boris Builder'); $clara = new Ctx(4, false, 'Clara Coder'); $dan = new Ctx(5, false, 'Dan Outsider');
foreach (['Software', 'Design'] as $n) $st->insert('categories', ['name' => $n, 'name_ru' => null]);

/* ---------- create / edit ---------- */
$form = ['title' => 'Build a landing page', 'category_id' => 1, 'location' => 'Remote', 'description' => 'One page, responsive', 'currency' => 'USD', 'publish' => 1, 'todos' => [['name' => 'Design', 'price' => '100', 'days' => 2], ['name' => 'Code', 'price' => '300,50', 'days' => 3], ['name' => '', 'price' => '9']]];
ok(err(fn() => $svc->saveContract($anna, 0, ['title' => ''] + $form)) === 'title', 'create: a title is required');
ok(err(fn() => $svc->saveContract($anna, 0, ['category_id' => 0] + $form)) === 'category_required', 'create: a category is required while categories exist');
ok(err(fn() => $svc->saveContract($anna, 0, ['todos' => []] + $form)) === 'steps_required', 'create: at least one step');
ok(err(fn() => $svc->saveContract($anna, 0, ['todos' => [['name' => 'x', 'price' => '-5', 'days' => 1]]] + $form)) === 'step_numbers', 'create: a negative price is refused');
ok(err(fn() => $svc->saveContract($anna, 0, ['deadline' => '2026-13-45'] + $form)) === 'deadline', 'create: a bad date is refused');
$k = $svc->saveContract($anna, 0, $form); $cid = (int)$k['id'];
ok($k['status'] === 'published' && $k['published_at'] !== null && $k['currency'] === 'USD' && $svc->totals($cid) === ['price' => 400.5, 'days' => 5, 'steps' => 2], 'create: published, steps without a name dropped, "300,50" read as 300.50');
ok($k['deadline'] === '2026-11-06', 'create: the deadline defaults to today + the days of the steps');
ok(err(fn() => $svc->saveContract($boris, $cid, $form)) === 'forbidden', 'edit: only the author');
$svc->saveContract($adm, $cid, ['title' => 'Build a landing page v2'] + $form); ok($svc->contract($cid)['title'] === 'Build a landing page v2', 'edit: an administrator may edit');
$svc->saveContract($anna, 0, ['title' => 'Draft one', 'publish' => 0] + $form); ok($svc->contract(2)['status'] === 'draft', 'create: a draft');
ok(count($svc->listing([])['rows']) === 1, 'list: drafts are not public');

/* ---------- listing: search, sort, filters ---------- */
$svc->saveContract($clara, 0, ['title' => 'Logo for a bakery', 'category_id' => 2, 'location' => 'Kazan', 'description' => 'Fresh bread brand', 'todos' => [['name' => 'Sketch', 'price' => '50', 'days' => 1]], 'publish' => 1]); // id 3
$l = $svc->listing([]); ok($l['total'] === 2 && $l['rows'][0]['title'] === 'Logo for a bakery', 'list: newest first');
$now += 60; $svc->saveContract($clara, 0, ['title' => 'Cheap thing', 'category_id' => 2, 'todos' => [['name' => 'a', 'price' => '10', 'days' => 1]], 'publish' => 1]); // id 4, newer
ok($svc->listing(['sort' => 'price'])['rows'][0]['id'] === $cid, 'list: sort by price');
ok(array_column($svc->listing(['category' => 2])['rows'], 'id') === [4, 3], 'list: category filter');
ok(array_column($svc->listing(['from' => '40', 'to' => '60'])['rows'], 'id') === [3], 'list: price range');
ok(array_column($svc->listing(['q' => 'BREAD'])['rows'], 'id') === [3] && array_column($svc->listing(['q' => 'kazan'])['rows'], 'id') === [3] && $svc->listing(['q' => 'nothing like this'])['total'] === 0, 'list: search is case-insensitive over title, description, location');
ok(count($svc->listing(['per' => 2, 'page' => 2])['rows']) === 1 && $svc->listing(['per' => 2])['total'] === 3, 'list: paging');
$svc->delete($clara, 4);

/* ---------- offers ---------- */
$steps = $svc->steps($cid); [$s1, $s2] = [(int)$steps[0]['id'], (int)$steps[1]['id']];
ok(err(fn() => $svc->makeOffer($anna, $cid, [])) === 'offer_not_allowed', 'offer: not on your own contract');
$counter = ['items' => [['type' => 'todo', 'id' => $s2, 'price' => '250', 'days' => '2'], ['type' => 'extra', 'name' => 'Deploy', 'price' => '40', 'days' => '1'], ['type' => 'todo', 'id' => $s1, 'price' => '120', 'days' => '2', 'excluded' => '']], 'notes' => 'I can start now', 'payment_type' => 'step'];
$mails = []; $oid = $svc->makeOffer($boris, $cid, $counter);
$o = $svc->offer($oid); ok((float)$o['price'] === 410.0 && (int)$o['days'] === 5 && $o['payment_type'] === 'step', 'offer: the total is the sum of the steps (250 + 40 + 120)');
ok(err(fn() => $svc->makeOffer($boris, $cid, $counter)) === 'offer_exists', 'offer: one per user');
ok($svc->unread(2) === 1 && $mails === [[2, 'offer_new', 'offer/' . $oid]], 'notify: the author gets a bell entry and an e-mail');
$oid2 = $svc->makeOffer($clara, $cid, [], true); ok((float)$svc->offer($oid2)['price'] === 400.5, 'offer: "accept as is" takes the author\'s prices');
ok(err(fn() => $svc->makeOffer($dan, $cid, ['items' => [['type' => 'todo', 'id' => $s1, 'excluded' => '1'], ['type' => 'todo', 'id' => $s2, 'excluded' => '1']]])) === 'offer_keep_one', 'offer: keep at least one step');
ok(err(fn() => $svc->makeOffer($dan, $cid, ['items' => [['type' => 'extra', 'name' => '', 'price' => '5']]])) === 'offer_extra', 'offer: an added step needs a name');
$svc->editOffer($boris, $oid, ['items' => [['type' => 'todo', 'id' => $s1, 'price' => '100', 'days' => '2'], ['type' => 'todo', 'id' => $s2, 'price' => '250', 'days' => '2']], 'payment_type' => 'step']); ok((float)$svc->offer($oid)['price'] === 350.0 && $svc->offer($oid)['seen_at'] === null, 'offer: edit changes the price and marks it unseen');
ok(err(fn() => $svc->editOffer($clara, $oid, [])) === 'forbidden', 'offer: only the offerer edits');
$pg = $svc->offerPage($anna, $oid); ok($svc->offer($oid)['seen_at'] !== null && count($pg['items']) === 2, 'offer page: the author opening it marks it seen');
ok(err(fn() => $svc->offerPage($dan, $oid)) === 'forbidden', 'offer page: nobody else');
ok(err(fn() => $svc->acceptOffer($boris, $oid)) === 'forbidden', 'accept: only the author');
$svc->saveContract($anna, $cid, ['title' => 'Build a landing page v2', 'description' => 'changed'] + $form + ['todos' => [['name' => 'ignored', 'price' => 1, 'days' => 1]]]);
ok(count($svc->steps($cid)) === 2, 'edit: steps are frozen once offers exist');
$od = $svc->makeOffer($dan, 3, [], true); ok(err(fn() => $svc->rejectOffer($boris, $od)) === 'forbidden', 'reject: only the author of that contract');
$svc->rejectOffer($clara, $od); ok($st->one('offers', ['id' => $od]) === null, 'reject: the offer disappears');
$ow = $svc->makeOffer($dan, $cid, [], true); $svc->withdrawOffer($dan, $ow); ok($st->one('offers', ['id' => $ow]) === null, 'withdraw: the offerer removes theirs');

/* ---------- private threads before the deal ---------- */
ok($svc->comment($boris, $cid, 'public', 'Is the logo ready?') !== null && $svc->comment($anna, $cid, 'public', 'Not yet', (int)$boris->uid) !== null, 'thread: the offerer and the author talk');
ok(err(fn() => $svc->comment($anna, $cid, 'public', 'x', 2)) === 'forbidden', 'thread: not with yourself');
$svc->comment($clara, $cid, 'public', 'Me too');
$pgA = $svc->page($anna, $cid, 4); $pgB = $svc->page($boris, $cid); $pgC = $svc->page($dan, $cid);
ok(count($pgA['threads']) === 2 && count($pgA['comments']) === 1 && $pgA['comments'][0]['body'] === 'Me too', 'thread: the author sees one thread per person');
ok(count($pgB['comments']) === 2 && $pgC['comments'] === [] && $pgB['offers'] === [], 'thread: another user sees only their own, and no offers');
ok($svc->comment($boris, $cid, 'public', '   ') === null, 'comment: an empty text is ignored');
ok(err(fn() => $svc->page($dan, 2)) === 'forbidden' && err(fn() => $svc->page($dan, 999)) === 'not_found', 'page: a draft is private; a missing contract is a 404');
ok($svc->watchToggle($dan, $cid) === true && $svc->isWatched($dan, $cid) && $svc->watchToggle($dan, $cid) === false, 'watch later: toggles');
ok(err(fn() => $svc->watchToggle($dan, 2)) === 'forbidden', 'watch later: published contracts only');

/* ---------- accept: terms are frozen, the others are told ---------- */
$mails = []; $svc->acceptOffer($anna, $oid); $k = $svc->contract($cid);
ok($k['status'] === 'active' && (int)$k['contractor_id'] === 3 && (float)$k['agreed_price'] === 350.0 && $k['payment_type'] === 'step' && (int)$k['agreed_days'] === 4 && $k['agreed_deadline'] === '2026-11-05', 'accept: the contract is active with the offer\'s terms');
$agreed = array_map(fn($t) => [(int)$t['id'], (float)$t['agreed_price']], $svc->steps($cid)); ok($agreed === [[$s1, 100.0], [$s2, 250.0]], 'accept: agreed prices frozen on the steps');
ok($st->one('offers', ['user_id' => 4]) === null && $svc->unread(4) >= 1 && in_array([3, 'offer_accepted', 'view/' . $cid], $mails, true), 'accept: the other offers are closed and their authors told');
ok(err(fn() => $svc->makeOffer($dan, $cid, [])) === 'offer_not_allowed' && err(fn() => $svc->saveContract($anna, $cid, $form)) === 'locked', 'accept: no more offers, no more editing');
ok(err(fn() => $svc->delete($anna, $cid)) === 'delete_active', 'delete: an active contract cannot be deleted');

/* a counteroffer with its own order, an unnecessary step and an added step */
$k2 = $svc->saveContract($anna, 0, ['title' => 'Order test', 'category_id' => 1, 'publish' => 1, 'todos' => [['name' => 'A', 'price' => '10', 'days' => 1], ['name' => 'B', 'price' => '20', 'days' => 1], ['name' => 'C', 'price' => '30', 'days' => 1]]]);
[$a, $b, $c3] = array_map(fn($t) => (int)$t['id'], $svc->steps((int)$k2['id']));
$oo = $svc->makeOffer($boris, (int)$k2['id'], ['items' => [['type' => 'todo', 'id' => $c3, 'price' => '35', 'days' => 2], ['type' => 'extra', 'name' => 'Extra', 'price' => '5', 'days' => 1], ['type' => 'todo', 'id' => $a, 'price' => '10', 'days' => 1, 'excluded' => '1'], ['type' => 'todo', 'id' => $b, 'price' => '20', 'days' => 1]]]);
$svc->acceptOffer($anna, (int)$k2['id'] ? $oo : 0);
$names = array_column($svc->steps((int)$k2['id']), 'name'); ok($names === ['C', 'Extra', 'B'], 'accept: the offerer\'s order, the unnecessary step dropped, their own step added (' . implode(',', $names) . ')');
ok((float)$svc->contract((int)$k2['id'])['agreed_price'] === 60.0, 'accept: the total counts only the kept steps');
/* an old-style offer with a total only is spread over the steps in proportion */
$k3 = $svc->saveContract($anna, 0, ['title' => 'Spread', 'category_id' => 1, 'publish' => 1, 'todos' => [['name' => 'A', 'price' => '100', 'days' => 3], ['name' => 'B', 'price' => '300', 'days' => 3]]]);
$st->insert('offers', ['contract_id' => $k3['id'], 'user_id' => 3, 'price' => 200, 'days' => 4, 'notes' => '', 'payment_type' => 'finish', 'offerer_type' => 'personal', 'status' => 'pending', 'seen_at' => null, 'created_at' => $svc->now()]);
$svc->acceptOffer($anna, (int)$st->one('offers', ['contract_id' => $k3['id']])['id']); $sp = array_map(fn($t) => (float)$t['agreed_price'], $svc->steps((int)$k3['id']));
ok($sp === [50.0, 150.0] && array_sum(array_column($svc->steps((int)$k3['id']), 'agreed_days')) === 4, 'accept: a total is spread over the steps (50 + 150, 4 days)');

/* ---------- work: steps, invoices, comments, pause ---------- */
ok(err(fn() => $svc->done($anna, $s1)) === 'forbidden', 'step: only the contractor marks it done');
ok(err(fn() => $svc->accept($anna, $s1)) === 'forbidden', 'step: it must be done before the author accepts');
$svc->comment($boris, $cid, 'work', 'Started'); $svc->comment($anna, $cid, 'todo', 'Use the blue one', 0, 0, $s1);
ok(err(fn() => $svc->comment($dan, $cid, 'work', 'hello')) === 'forbidden' && count($svc->page($anna, $cid)['comments']) === 1, 'comments: only the two participants, work chat on the page');
$svc->pause($anna, $cid); ok($svc->contract($cid)['status'] === 'paused' && err(fn() => $svc->done($boris, $s1)) === 'forbidden', 'pause: work stops'); $svc->pause($anna, $cid);
ok($svc->done($boris, $s1)['done'] === true && $svc->done($boris, $s1)['done'] === false, 'step: done can be taken back'); $svc->done($boris, $s1);
$r = $svc->accept($anna, $s1); $inv = $r['invoice']; $i = $svc->invoice($inv);
ok($inv > 0 && $i['number'] === 'CT-' . $inv && (float)$i['amount'] === 100.0 && $i['status'] === 'waiting' && (int)$i['payer_id'] === 2 && (int)$i['payee_id'] === 3 && $i['currency'] === 'USD', 'accept: step-by-step contracts invoice each accepted step');
ok(err(fn() => $svc->done($boris, $s2)) === 'prev_unpaid', 'step-by-step: the next step waits until the previous one is paid');
$svc->accept($anna, $s1); ok($st->one('invoices', ['id' => $inv]) === null && $svc->steps($cid)[0]['accepted_at'] === null, 'accept again: takes the acceptance back and removes the waiting invoice');
$inv = $svc->accept($anna, $s1)['invoice']; ok(count($svc->invoices($anna, 'pay')) === 1 && count($svc->invoices($boris, 'receive')) === 1 && $svc->invoices($anna, 'receive') === [], 'invoices: to pay / to receive');
ok($svc->invoiceCounts($anna) === ['pay' => 1, 'receive' => 0] && $svc->invoiceCounts($boris) === ['pay' => 0, 'receive' => 0], 'invoices: counters');
ok(err(fn() => $svc->payInvoice($boris, $inv)) === 'forbidden', 'invoice: only the payer pays');
ok(err(fn() => $svc->setNumber($anna, $inv, 'X-1')) === 'forbidden', 'invoice: only the issuer sets the number');
$svc->setNumber($boris, $inv, 'BB-2026-001'); ok($svc->invoice($inv)['number'] === 'BB-2026-001' && err(fn() => $svc->setNumber($boris, $inv, "bad'num")) === 'invoice_number', 'invoice: a custom number, validated');
$svc->payInvoice($anna, $inv); ok($svc->invoice($inv)['status'] === 'pending' && $svc->invoiceCounts($boris)['receive'] === 1, 'invoice: paid -> pending, the contractor must confirm');
ok(err(fn() => $svc->accept($anna, $s1)) === 'already_paying', 'accept: cannot be withdrawn once the payment started');
$svc->confirmInvoice($boris, $inv); ok($svc->invoice($inv)['status'] === 'paid' && $svc->invoice($inv)['paid_at'] !== null && err(fn() => $svc->setNumber($boris, $inv, 'Z')) === 'forbidden', 'invoice: confirmed -> paid, the number is locked');
ok(count($svc->invoices($boris, 'receive', 'bb-2026')) === 1 && $svc->invoices($boris, 'receive', 'nothing') === [] && count($svc->invoices($boris, 'receive', 'design')) === 1, 'invoices: search by number and step name');
$svc->done($boris, $s2); $svc->accept($anna, $s2);
$st->insert('invoices', ['contract_id' => $k2['id'], 'todo_id' => null, 'payer_id' => 2, 'payee_id' => 3, 'number' => 'CT-ZZ', 'amount' => 1, 'currency' => 'RUB', 'status' => 'waiting', 'created_at' => '2026-10-01 00:00:00', 'paid_at' => null]);
$sortC = array_column($svc->invoices($anna, 'pay', '', 'contract', 'asc'), 'contract_title'); $sortD = array_column($svc->invoices($anna, 'pay', '', 'date', 'asc'), 'number');
ok($sortC === ['Build a landing page v2', 'Build a landing page v2', 'Order test'] && $sortD[0] === 'CT-ZZ', 'invoices: sort by contract and by date, both ways');
ok($svc->contract($cid)['status'] === 'fulfilled' && count($svc->contractInvoices($anna, $cid)) === 2 && $svc->contractInvoices($dan, $cid) === [] && count($svc->contractInvoices($adm, $cid)) === 2, 'fulfilled: the invoices block in the contract is private to the two sides (and the administrator)');
ok(err(fn() => $svc->invoicePage($dan, $inv)) === 'forbidden' && $svc->invoicePage($boris, $inv)['canNumber'] === false, 'invoice page: private');

/* whole-contract payment: one invoice when the last step is accepted */
$k4 = $svc->saveContract($anna, 0, ['title' => 'Finish pay', 'category_id' => 1, 'publish' => 1, 'todos' => [['name' => 'A', 'price' => '70', 'days' => 1], ['name' => 'B', 'price' => '30', 'days' => 1]]]);
$svc->acceptOffer($anna, $svc->makeOffer($boris, (int)$k4['id'], [], true)); [$x, $y] = array_map(fn($t) => (int)$t['id'], $svc->steps((int)$k4['id']));
$svc->done($boris, $x); ok($svc->accept($anna, $x)['invoice'] === 0, 'accept: no invoice yet when paying at the end'); $svc->done($boris, $y); $fin = $svc->accept($anna, $y);
ok($fin['fulfilled'] && (float)$svc->invoice($fin['invoice'])['amount'] === 100.0 && $svc->invoice($fin['invoice'])['todo_id'] === null, 'accept: the last step fulfils the contract and issues one invoice for the whole');

/* ---------- reviews ---------- */
ok(err(fn() => $svc->review($dan, $cid, 3, 5, 'x')) === 'review_not_allowed' && err(fn() => $svc->review($anna, $cid, 3, 9, 'x')) === 'review_not_allowed', 'review: only the two sides, rating 1..5');
$svc->review($anna, $cid, 3, 5, 'Great'); $svc->review($boris, $cid, 2, 4, 'Pays on time'); $svc->review($anna, (int)$k4['id'], 3, 3, '');
ok(err(fn() => $svc->review($anna, $cid, 3, 5, '')) === 'review_exists' && (float)$svc->profile(3)['rating_avg'] === 4.0 && (int)$svc->profile(3)['rating_count'] === 2, 'review: once per contract, the average is recomputed (5 and 3 -> 4.0)');
$up = $svc->userPage(3); ok(count($up['reviews']) === 2 && $up['user']['name'] === 'Boris Builder', 'user page: reviews');

/* ---------- fire, prepay, repeat, delete, archive ---------- */
$k5 = $svc->saveContract($anna, 0, ['title' => 'Fire me', 'category_id' => 2, 'prepay_required' => 1, 'publish' => 1, 'todos' => [['name' => 'A', 'price' => '10', 'days' => 1]]]); $f5 = (int)$k5['id'];
$svc->acceptOffer($anna, $svc->makeOffer($clara, $f5, [], true));
ok(err(fn() => $svc->prepay($boris, $f5)) === 'forbidden', 'prepay: the author only'); $svc->prepay($anna, $f5); ok($svc->contract($f5)['prepaid_at'] !== null && err(fn() => $svc->prepay($anna, $f5)) === 'forbidden', 'prepay: marked once');
$svc->fire($anna, $f5); ok($svc->contract($f5)['status'] === 'terminated', 'fire: the contract goes to the archive');
$mine = $svc->mine($clara, 'archive'); ok(in_array($f5, array_column($mine, 'id'), true) && !in_array($f5, array_column($svc->mine($anna, 'created'), 'id'), true), 'my contracts: archive shows both sides, created hides finished ones');
$rep = $svc->repeat($anna, $f5); ok($svc->contract($rep)['status'] === 'draft' && count($svc->steps($rep)) === 1 && $svc->steps($rep)[0]['accepted_at'] === null && $svc->contract($rep)['contractor_id'] === null, 'repeat: a clean draft with the author\'s steps');
ok(err(fn() => $svc->repeat($clara, $f5)) === 'forbidden', 'repeat: the author only');
$svc->addFile($anna, $rep, 'file', 'abc123.pdf', 'brief.pdf'); ok(err(fn() => $svc->addFile($anna, $rep, 'file', 'x.php', 'shell.php')) === 'file_type' && err(fn() => $svc->addFile($dan, $rep, 'file', 'x.pdf', 'a.pdf')) === 'forbidden', 'files: allow-list of extensions, participants only');
ok(Svc::safeName('..\\..\\evil<name>.pdf') === 'evil_name_.pdf', 'files: names are sanitised');
$names = $svc->delete($anna, $rep); ok($names === ['abc123.pdf'] && $svc->contract($rep) === null && $st->find('todos', ['contract_id' => $rep]) === [], 'delete: removes steps and files with it');
$names = $svc->delete($adm, $cid); ok($svc->contract($cid) === null && $st->find('invoices', ['contract_id' => $cid]) === [] && $st->find('comments', ['contract_id' => $cid]) === [], 'delete: an administrator may; invoices and comments go too');

$now = strtotime('2026-12-01 00:00:00'); $stale = $svc->saveContract($anna, 0, ['title' => 'Nobody comes', 'category_id' => 1, 'publish' => 1, 'todos' => [['name' => 'A', 'price' => '10', 'days' => 1]]]); $sid = (int)$stale['id']; $svc->makeOffer($boris, $sid, [], true);
ok($svc->archiveStale() === 0, 'archive: a fresh contract stays'); $now += 31 * 86400; $before = $svc->unread(2);
ok($svc->archiveStale() === 2 && $svc->contract(3)["status"] === "terminated" && $svc->contract($sid)['status'] === 'terminated' && $st->find('offers', ['contract_id' => $sid]) === [] && $svc->unread(2) === $before + 1, 'archive: after 30 days without a contractor it leaves the listing, offers are dropped, the author is told');

/* ---------- notifications ---------- */
ok($svc->unread(2) > 0, 'notices: unread count'); $svc->markRead($anna); ok($svc->unread(2) === 0, 'notices: opening the list marks them read');
$one = (int)$svc->notices($anna)[0]['id']; $n = count($svc->notices($anna)); $svc->clearNotices($anna, $one); ok(count($svc->notices($anna)) === $n - 1, 'notices: clear one'); $svc->clearNotices($anna); ok($svc->notices($anna) === [] && $svc->notices($boris) !== [], 'notices: clear all (only mine)');
$svc->saveProfile($boris, ['types' => ['self', 'company'], 'about' => 'x', 'notify_email' => '']); $mails = []; $kk = $svc->saveContract($anna, 0, ['title' => 'Mail test', 'category_id' => 1, 'publish' => 1, 'todos' => [['name' => 'A', 'price' => '10', 'days' => 1]]]);
$svc->makeOffer($boris, (int)$kk['id'], [], true); $svc->makeOffer($clara, (int)$kk['id'], [], true); ok(count($mails) === 2 && $svc->unread(2) > 0, 'notices: e-mail follows the profile switch (the bell always works)');
$svc->saveProfile($anna, ['types' => ['self'], 'notify_email' => '1']); $mails = []; $svc->saveProfile($boris, ['types' => ['company'], 'notify_email' => '1']);
ok($svc->userTypes(3) === ['company'] && Svc::typesFrom(['nonsense']) === ['personal'] && $svc->userTypes(4) === ['personal'], 'profile: statuses company / self / personal, never empty');
$ot = $svc->saveContract($boris, 0, ['title' => 'Typed', 'category_id' => 1, 'author_type' => 'self', 'todos' => [['name' => 'A', 'price' => '1', 'days' => 1]]]); ok($ot['author_type'] === 'company', 'contract: author type is limited to the author\'s statuses');

/* ---------- profile fields + CSV ---------- */
$sec = $svc->saveSection(0, 'Company details', 'company', 1); $sec2 = $svc->saveSection(0, 'About me', 'personal', 2);
$f1 = $svc->saveField(0, $sec, 'Tax ID', 'text', '', true, false, 1); $f2 = $svc->saveField(0, $sec, 'Tax regime', 'select', "General\nSimplified", false, true, 2); $f3 = $svc->saveField(0, $sec2, 'Site', 'url', '', false, true, 1);
ok(err(fn() => $svc->saveField(0, 99, 'x', 'text', '', false, false, 0)) === 'field_label' && err(fn() => $svc->saveSection(0, ' ', 'self', 0)) === 'section_title', 'fields: validated');
ok(count($svc->structure(['company'])) === 1 && count($svc->structure()) === 2, 'fields: sections by status');
ok(err(fn() => $svc->saveProfile($boris, ['types' => ['company']], [$f2 => ['value' => 'General']])) === 'field_required', 'fields: a required field must be filled');
ok(err(fn() => $svc->saveProfile($boris, ['types' => ['company']], [$f1 => ['value' => '123'], $f2 => ['value' => 'Nope']])) === 'field_invalid', 'fields: a choice must be one of the options');
$svc->saveProfile($boris, ['types' => ['company'], 'notify_email' => '1'], [$f1 => ['value' => '236705279082', 'public' => ''], $f2 => ['value' => 'Simplified', 'public' => '1'], $f3 => ['value' => 'https://ignored.example']]);
$v = $svc->values(3); ok($v[$f1]['value'] === '236705279082' && (int)$v[$f1]['is_public'] === 0 && !isset($v[$f3]), 'fields: saved; the fields of other statuses are ignored');
$pub = array_column($svc->userPage(3)['public'], 'value', 'label'); ok($pub === ['Tax regime' => 'Simplified'], 'user page: only public values are shown');
$csv = $svc->fieldsCsv(); $lines = explode("\n", trim($csv));
ok(str_starts_with($csv, "\xEF\xBB\xBFstatus;status_ru;section_id;section;") && count($lines) === 4 && str_contains($lines[1], 'company;Компания;') && str_contains($csv, '"General | Simplified";0;1;2'), 'csv: BOM, ";" separator, the status in two languages, options on one line');
ok(strpos($csv, 'company;') < strpos($csv, 'personal;'), 'csv: statuses in the order self, company, personal');
$svc->deleteSection($sec); ok(count($svc->structure()) === 1 && $svc->values(3) === [], 'fields: deleting a section removes its fields and the stored values');
ok(err(fn() => $svc->saveCategory(0, 'software', '')) === 'category_dup' && $svc->saveCategory(0, 'Legal', 'Юридические') > 2, 'categories: unique names');
$svc->deleteCategory(1); ok($svc->contract((int)$kk['id'])['category_id'] === null, 'categories: deleting one detaches the contracts');

echo $fail ? "\n$fail FAILED\n" : "\nAll good\n"; exit($fail ? 1 : 0);
