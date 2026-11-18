<?php
declare(strict_types=1);
/** Logic tests of the Contact Log rules (Svc + Report) on an in-memory store; no database, no network. Run: php tests/contactlog.php */
$dir = dirname(__DIR__) . '/core/plugins/contactlog';
foreach (['Err', 'Store', 'Report', 'Svc'] as $c) require "$dir/lib/$c.php";
require __DIR__ . '/ContactlogMemStore.php';
use SysferaContactlog\{Svc, Err, Report};
$fail = 0; function ok(bool $c, string $n): void { global $fail; echo ($c ? '  ok   ' : '  FAIL ') . $n . "\n"; if (!$c) $fail++; }
function refused(callable $f, string $code): bool { try { $f(); } catch (Err $e) { return $e->errCode === $code; } return false; }
$en = require "$dir/lang/en.php"; $ru = require "$dir/lang/ru.php";

$now = 1_800_000_000; $st = new ContactlogMemStore(); $sent = []; $mailAnswer = null;
$svc = new Svc($st, function () use (&$now) { return $now; }, function ($to, $s, $t, $h) use (&$sent, &$mailAnswer) { $sent[] = compact('to', 's', 't', 'h'); return $mailAnswer; });
$A = 2; $B = 3;

/* ---------- cards ---------- */
$c = $svc->save($A, 0, ['name' => '  Ivan  ', 'surname' => 'Petrov', 'mobile' => '+7 900', 'email' => 'ivan@example.com', 'status' => 'call_later', 'theme' => 'Flat', 'notes' => "line1\nline2", 'next_call' => '2026-12-01']);
ok($c['id'] > 0 && $c['name'] === 'Ivan' && $c['status'] === 'call_later' && $c['next_call'] === '2026-12-01' && $c['created_at'] === $now && $c['logs'] === [], 'create: trimmed, saved, no logs yet');
ok(refused(fn() => $svc->save($A, 0, ['name' => '  ']), 'name_required'), 'name is required');
ok(refused(fn() => $svc->save($A, 0, ['name' => 'x', 'email' => 'not-mail']), 'email_invalid'), 'bad e-mail refused');
ok(refused(fn() => $svc->save($A, 0, ['name' => 'x', 'status' => 'nope']), 'status_invalid'), 'unknown status refused');
ok(refused(fn() => $svc->save($A, 0, ['name' => 'x', 'next_call' => '2026-02-30']), 'date_invalid') && refused(fn() => $svc->save($A, 0, ['name' => 'x', 'next_call' => 'tomorrow']), 'date_invalid'), 'impossible / non-ISO dates refused');
ok(refused(fn() => $svc->save(0, 0, ['name' => 'x']), 'forbidden'), 'a guest cannot create');
$d = $svc->save($A, 0, ['name' => 'Default']); ok($d['status'] === 'busy' && $d['next_call'] === '' && $d['email'] === '', 'defaults: first status, empty date');
ok(strlen($svc->save($A, 0, ['name' => str_repeat('a', 300)])['name']) === 100, 'long names are cut to 100 characters');
$now += 60; $u = $svc->save($A, $c['id'], ['name' => 'Ivan', 'status' => 'actual', 'next_call' => '']); ok($u['status'] === 'actual' && $u['next_call'] === '' && $u['created_at'] === 1_800_000_000 && $u['surname'] === '', 'update keeps the creation time, can clear the date');

/* ---------- privacy: every user is alone in their data ---------- */
ok(refused(fn() => $svc->save($B, $c['id'], ['name' => 'hack']), 'not_found') && refused(fn() => $svc->delete($B, $c['id']), 'not_found') && refused(fn() => $svc->addLog($B, $c['id'], 'call'), 'not_found') && refused(fn() => $svc->clearLog($B, $c['id']), 'not_found') && refused(fn() => $svc->sendReport($B, $c['id'], 'b@example.com', $en), 'not_found'), "another user cannot edit, delete, log, clear or report on someone's card");
ok($svc->cards($B) === [] && $svc->cards(0) === [] && count($svc->cards($A)) === 3, 'lists are per user; a guest has none');

/* ---------- log ---------- */
$l1 = $svc->addLog($A, $c['id'], 'call'); $now += 5; $l2 = $svc->addLog($A, $c['id'], 'success');
ok(count($l2) === 2 && $l2[0]['action'] === 'call' && $l2[1]['action'] === 'success' && $l2[1]['at'] === $now, 'log entries are appended with their time, oldest first');
ok(refused(fn() => $svc->addLog($A, $c['id'], 'dance'), 'action_invalid'), 'unknown action refused');
for ($i = 0; $i < Svc::MAX_LOGS + 5; $i++) $last = $svc->addLog($A, $d['id'], 'email'); ok(count($last) === Svc::MAX_LOGS, 'a card keeps at most ' . Svc::MAX_LOGS . ' log entries');
$svc->clearLog($A, $c['id']); $mine = array_values(array_filter($svc->cards($A), fn($x) => $x['id'] === $c['id']))[0]; ok($mine['logs'] === [], 'clear log empties it');

/* ---------- ordering and delete ---------- */
$cards = $svc->cards($A); ok($cards[0]['created_at'] >= $cards[count($cards) - 1]['created_at'], 'cards come newest first');
$svc->addLog($A, $c['id'], 'call'); $svc->delete($A, $c['id']);
ok(count($svc->cards($A)) === 2 && !array_filter($st->t['logs'] ?? [], fn($l) => (int)$l['card_id'] === $c['id']), 'delete removes the card and its log');
ok(refused(fn() => $svc->delete($A, $c['id']), 'not_found'), 'deleting twice is "not found"');

/* ---------- profile (copy address) ---------- */
ok($svc->copyEmail($A) === '' && $svc->saveCopyEmail($A, ' copy@example.com ') === 'copy@example.com' && $svc->copyEmail($A) === 'copy@example.com' && $svc->copyEmail($B) === '', 'copy address is per user');
ok($svc->saveCopyEmail($A, 'other@example.com') === 'other@example.com' && count($st->t['profiles']) === 1, 'saving again updates the same row');
ok(refused(fn() => $svc->saveCopyEmail($A, 'bad'), 'email_invalid') && $svc->saveCopyEmail($A, '') === '' && $svc->copyEmail($A) === '', 'invalid refused; empty clears');

/* ---------- report: the platform mailer, honest answers ---------- */
$card = $svc->save($A, 0, ['name' => 'Anna <b>', 'surname' => 'Smith', 'mobile' => '123', 'status' => 'application', 'notes' => '<script>x</script>', 'next_call' => '2026-12-31']); $svc->addLog($A, $card['id'], 'call');
$svc->saveCopyEmail($A, 'copy@example.com'); $sent = [];
$r = $svc->sendReport($A, $card['id'], 'me@example.com', $en, 'Europe/Moscow');
ok($r['recipients'] === 2 && $sent[0]['to'] === ['me@example.com', 'copy@example.com'], 'report goes to the account address and the copy address');
ok(str_contains($sent[0]['s'], 'Card Report: Anna <b> Smith') && !str_contains($sent[0]['s'], "\n"), 'subject names the card');
ok(str_contains($sent[0]['h'], 'Anna &lt;b&gt;') && str_contains($sent[0]['h'], '&lt;script&gt;') && !str_contains($sent[0]['h'], '<script>'), 'html part escapes user text');
ok(str_contains($sent[0]['t'], 'Status: Application created') && str_contains($sent[0]['t'], 'Phone call') && str_contains($sent[0]['t'], 'Next Call: 2026-12-31') && str_contains($sent[0]['t'], '[2027-01-15 11:'), 'text part: status, log line with a time in the given zone, next call');
$sent = []; $svc->sendReport($A, $card['id'], 'copy@example.com', $ru); ok($sent[0]['to'] === ['copy@example.com'] && str_contains($sent[0]['t'], 'Статус: Заявка создана') && str_contains($sent[0]['t'], 'Звонок'), 'a duplicate address is mailed once; Russian report');
$svc->saveCopyEmail($A, ''); ok(refused(fn() => $svc->sendReport($A, $card['id'], '', $en), 'no_recipient') && refused(fn() => $svc->sendReport($A, $card['id'], 'broken', $en), 'no_recipient'), 'no valid address: refused before mailing');
$mailAnswer = 'not_configured'; ok(refused(fn() => $svc->sendReport($A, $card['id'], 'me@example.com', $en), 'mail_not_configured'), 'mailer not set up: an honest error, not a silent success');
$mailAnswer = 'TLS handshake failed'; try { $svc->sendReport($A, $card['id'], 'me@example.com', $en); ok(false, 'smtp failure surfaced'); } catch (Err $e) { ok($e->errCode === 'mail_failed' && $e->http === 502 && $e->getMessage() === 'TLS handshake failed', 'SMTP failure is an error (no fallback that pretends to deliver); detail kept for the log'); }
$rep = Report::build($svc->cards($A)[0], $en, $now); ok(str_contains($rep['html'], '</html>') && Report::when(0, 'Nowhere/Land') === '1970-01-01 00:00', 'report html is complete; a bad time zone falls back to UTC');

/* ---------- regression: "date error" when creating a contact ---------- */
// exactly what the page posts for a new card (every field a string; id empty; the date as <input type=date> gives it)
$form = ['id' => '', 'name' => 'Olga', 'second_name' => '', 'surname' => '', 'mobile' => '', 'messenger' => '', 'email' => '', 'status' => 'busy', 'theme' => '', 'notes' => '', 'next_call' => '2026-12-01'];
$r = $svc->save($A, (int)$form['id'], $form); ok($r['next_call'] === '2026-12-01' && $r['id'] > 0, 'the form as the page posts it (ISO date, empty id) creates the card');
$r = $svc->save($A, (int)$form['id'], ['next_call' => ''] + $form); ok($r['next_call'] === '' && $st->t['cards'][$r['id']]['next_call'] === null, 'the form with no date stores NULL (not an empty string, which MySQL DATE rejects)');
foreach (['01.12.2026', '1.12.2026', '01/12/2026', '2026/12/01', '2026-12-01T00:00', '2026-12-01 09:30', '2026-12-01T09:30:00Z', ' 2026-12-01 ', '1-12-2026'] as $v) ok($svc->save($A, 0, ['next_call' => $v] + $form)['next_call'] === '2026-12-01', "date typed or sent as " . json_encode($v) . ' is normalised to 2026-12-01');
foreach (['2026-02-30', '31.04.2026', '2026-13-01', 'tomorrow', '12/2026', '2026-12-01junk', '0000-00-00', '1850-01-01', "2026-12-01\n2026-12-02"] as $v) ok(refused(fn() => $svc->save($A, 0, ['next_call' => $v] + $form), 'date_invalid'), 'refused: ' . json_encode($v));
ok(Svc::date(null) === '' && Svc::date('2028-02-29') === '2028-02-29' && refused(fn() => Svc::date('2027-02-29'), 'date_invalid'), 'leap days are checked');

/* ---------- the pieces the page needs ---------- */
ok(count(Svc::STATUSES) === 10 && !array_diff(Svc::STATUSES, array_keys(Svc::COLORS)) && !array_diff(array_map(fn($s) => "st_$s", Svc::STATUSES), array_keys($en)) && !array_diff(array_map(fn($s) => "log_$s", Svc::ACTIONS), array_keys($ru)), 'every status has a colour and a name, every action a log text (en and ru)');

/* ---------- e-mail to the contact and the Messenger look-up ---------- */
$out = []; $ans = null;
$sv2 = new Svc($st, function () use (&$now) { return $now; }, function ($to, $s, $t, $h, $from = null) use (&$out, &$ans) { $out[] = compact('to', 's', 't', 'from'); return $ans; });
$k = $sv2->save($A, 0, ['name' => 'Mail Target', 'email' => 'Target@Example.com', 'messenger' => '@nick']);
$logs = $sv2->sendMail($A, $k['id'], "Hello\r\nBcc: x@y.z", "Body text", 'me@example.com', 'Me');
ok(count($out) === 1 && $out[0]['to'] === ['Target@Example.com'] && !str_contains($out[0]['s'], "\n") && $out[0]['t'] === 'Body text' && $out[0]['from'] === ['me@example.com', 'Me'], 'mail: goes to the contact, in the name of the user, a header injection in the subject is flattened');
ok(count($logs) === 1 && $logs[0]['action'] === 'email', 'mail: an "Email" entry is logged');
ok(refused(fn() => $sv2->sendMail($A, $k['id'], '', 'x', 'me@example.com', 'Me'), 'mail_empty') && refused(fn() => $sv2->sendMail($A, $k['id'], 'x', '  ', 'me@example.com', 'Me'), 'mail_empty'), 'mail: subject and text are required');
$nm = $sv2->save($A, 0, ['name' => 'No Mail']); ok(refused(fn() => $sv2->sendMail($A, $nm['id'], 'x', 'y', 'me@example.com', 'Me'), 'email_invalid'), 'mail: a card without an address is refused');
ok(refused(fn() => $sv2->sendMail($B, $k['id'], 'x', 'y', 'me@example.com', 'Me'), 'not_found'), 'mail: only the owner of the card');
$ans = 'not_configured'; try { $sv2->sendMail($A, $k['id'], 'x', 'y', 'me@example.com', 'Me'); $code = ''; } catch (Err $e) { $code = $e->errCode; } ok($code === 'mail_not_configured' && count($sv2->save($A, $k['id'], ['name' => 'Mail Target', 'email' => 'Target@Example.com'])['logs']) === 1, 'mail: an unconfigured mailer is reported and nothing is logged'); $ans = null;
ok($sv2->peerQuery($A, $k['id']) === 'target@example.com', 'messenger look-up: by e-mail when no Sysfera ID is given');
$sid = $sv2->save($A, 0, ['name' => 'Has Sid', 'email' => 'a@b.co', 'messenger' => ' 12@Site.RU ']); ok($sv2->peerQuery($A, $sid['id']) === '12@site.ru', 'messenger look-up: a Sysfera ID in the Messenger field wins');
ok($sv2->peerQuery($A, $nm['id']) === '', 'messenger look-up: nothing to look up without an ID or an e-mail');
/* dashboard glance: next planned contacts (overdue first) and the latest logged contacts, own cards only */
$g0 = $svc->glance(77, '2026-11-17'); ok($g0['next'] === [] && $g0['recent'] === [], 'glance: a user without cards gets nothing');
$g1 = $svc->save(77, 0, ['name' => 'Late', 'next_call' => '2026-11-10', 'mobile' => '1']); $g2 = $svc->save(77, 0, ['name' => 'Soon', 'next_call' => '2026-11-20']); $g3 = $svc->save(77, 0, ['name' => 'Later', 'next_call' => '2026-12-31']); $g4 = $svc->save(77, 0, ['name' => 'Nodate']); $g5 = $svc->save(77, 0, ['name' => 'Fifth', 'next_call' => '2027-01-01']);
$svc->save(78, 0, ['name' => 'Other user', 'next_call' => '2026-01-01']);
$gl = $svc->glance(77, '2026-11-17'); ok(array_column($gl['next'], 'name') === ['Late', 'Soon', 'Later'] && $gl['recent'] === [], 'glance: three nearest dates, overdue first; cards without a date or log are left out; nobody else\'s cards');
$now += 10; $svc->addLog(77, $g2['id'], 'email'); $now += 10; $svc->addLog(77, $g4['id'], 'call'); $now += 10; $svc->addLog(77, $g2['id'], 'messenger'); $now += 10; $svc->addLog(77, $g1['id'], 'call'); $now += 10; $svc->addLog(77, $g3['id'], 'call'); $now += 10; $svc->addLog(77, $g5['id'], 'success');
$gl = $svc->glance(77, '2026-11-17'); ok(array_column($gl['recent'], 'name') === ['Fifth', 'Later', 'Late'] && $gl['recent'][0]['last_action'] === 'success', 'glance: the three latest contacts by the last logged action');
$gl2 = $svc->glance(77, '2026-11-17', 1); ok(count($gl2['next']) === 1 && count($gl2['recent']) === 1, 'glance: the size can be limited');
echo $fail ? "\n$fail FAILED\n" : "\nAll good\n"; exit($fail ? 1 : 0);
