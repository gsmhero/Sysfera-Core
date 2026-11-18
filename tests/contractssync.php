<?php
declare(strict_types=1);
/** Tests of the Bablito synchronisation: signing, the rules of Sync (newest change wins, e-mail matching, dry run) against an in-memory store and a fake Bablito. Run: php tests/contractssync.php */
foreach (['mb_substr', 'mb_strlen', 'mb_strtolower'] as $f) if (!function_exists($f)) eval("function $f(string \$s, int \$a = 0, ?int \$l = null) { return " . ($f === 'mb_strlen' ? 'strlen($s)' : ($f === 'mb_strtolower' ? 'strtolower($s)' : '$l === null ? substr($s, $a) : substr($s, $a, $l)')) . '; }');
$dir = dirname(__DIR__) . '/core/plugins/contracts';
foreach (['Err', 'Ctx', 'Store', 'Svc', 'Bablito', 'Sync'] as $c) require "$dir/lib/$c.php";
require __DIR__ . '/ContractsMemStore.php';
use SysferaContracts\{Bablito, Sync, Svc, Err};
$fail = 0; function ok(bool $c, string $n): void { global $fail; echo ($c ? '  ok   ' : '  FAIL ') . $n . "\n"; if (!$c) $fail++; }
function err(callable $f): string { try { $f(); return ''; } catch (Err $e) { return $e->errCode; } }

/* ---------- signing ---------- */
$key = str_repeat('k', 32); $vec = Bablito::sig($key, '1700000000', 'post', '/api/sysfera/users', '{"a":1}');
ok($vec === hash_hmac('sha256', "1700000000\nPOST\n/api/sysfera/users\n" . hash('sha256', '{"a":1}'), $key) && strlen($vec) === 64, 'signature: HMAC-SHA256 over timestamp, METHOD, uri and body hash');
ok(Bablito::validBase('https://www.bablito.ru') && !Bablito::validBase('http://www.bablito.ru') && !Bablito::validBase('https://u:p@bablito.ru') && !Bablito::validBase('https://bablito.ru/?x=1') && !Bablito::validBase('ftp://bablito.ru'), 'base address: https only, no credentials, no query');

/* ---------- a fake Bablito (same rules as app/pages/sysfera_api.php on the other side) ---------- */
$R = ['now' => strtotime('2026-11-10 12:00:00'), 'next' => 100, 'bad' => false, 'down' => false, 'calls' => [], 'offers' => [],
  'sections' => [['id' => 7, 'title' => 'About the company', 'type' => 'company', 'position' => 1, 'fields' => [['id' => 70, 'label' => 'Tax number', 'type' => 'text', 'options' => '', 'required' => true, 'default_public' => false, 'position' => 1]]],
                 ['id' => 8, 'title' => 'Skills', 'type' => 'self', 'position' => 2, 'fields' => [['id' => 80, 'label' => 'Level', 'type' => 'select', 'options' => "junior\nsenior", 'required' => false, 'default_public' => true, 'position' => 1], ['id' => 81, 'label' => 'Site', 'type' => 'url', 'options' => '', 'required' => false, 'default_public' => true, 'position' => 2]]]],
  'contracts' => [
    ['id' => 501, 'title' => 'Logo design', 'category' => 'Design', 'location' => 'Remote', 'description' => 'A logo', 'currency' => 'RUB', 'payment_type' => 'step', 'prepay_required' => true, 'deadline' => '2026-12-31', 'created_at' => '2026-11-08 09:00:00', 'author' => ['id' => 10, 'name' => 'Anna'], 'steps' => [['name' => 'Sketch', 'description' => '', 'price' => 100, 'days' => 2], ['name' => 'Final', 'description' => 'files', 'price' => 250.5, 'days' => 3]]],
    ['id' => 502, 'title' => 'Fix a tap', 'category' => 'Unknown cat', 'location' => '', 'description' => '', 'currency' => 'xx', 'payment_type' => 'finish', 'prepay_required' => false, 'deadline' => null, 'created_at' => 'bad', 'author' => ['id' => 11, 'name' => 'Taken'], 'steps' => []],
    ['id' => 503, 'title' => 'Write a text', 'category' => 'design', 'location' => 'Kazan', 'description' => 'x', 'currency' => 'USD', 'payment_type' => 'finish', 'prepay_required' => false, 'deadline' => null, 'created_at' => '2026-11-09 09:00:00', 'author' => ['id' => 10, 'name' => 'Anna'], 'steps' => [['name' => 'All', 'description' => '', 'price' => 50, 'days' => 1]]]],
  'users' => [10 => ['id' => 10, 'email' => 'anna@x.test', 'name' => 'Anna', 'types' => ['self'], 'sysfera_id' => null], 11 => ['id' => 11, 'email' => 'taken@x.test', 'name' => 'Taken', 'types' => ['personal'], 'sysfera_id' => 99]],
  'values' => [10 => [81 => ['value' => 'https://anna.test', 'is_public' => 1, 'updated_at' => '2026-11-05 10:00:00'], 80 => ['value' => 'senior', 'is_public' => 1, 'updated_at' => '2026-11-09 10:00:00']]]];
$http = function (string $m, string $url, array $h, string $body) use (&$R, $key): array {
    $hd = []; foreach ($h as $l) { [$a, $b] = explode(': ', $l, 2); $hd[$a] = $b; }
    $p = parse_url($url); $uri = $p['path'] . (isset($p['query']) ? '?' . $p['query'] : ''); $R['calls'][] = "$m $uri";
    if ($R['down']) throw new RuntimeException('connection refused');
    if ($R['bad'] || ($hd['X-Sysfera-Signature'] ?? '') !== Bablito::sig($key, $hd['X-Sysfera-Timestamp'] ?? '', $m, $uri, $body)) return [401, '{"error":"bad_signature"}'];
    $in = $body !== '' ? json_decode($body, true) : []; parse_str($p['query'] ?? '', $q); $dry = !empty($in['dry_run']); $j = fn($d, int $s = 200) => [$s, json_encode($d)];
    $uj = fn($u) => $u ? ['id' => $u['id'], 'email' => $u['email'], 'name' => $u['name'], 'types' => $u['types'], 'sysfera_id' => $u['sysfera_id']] : null;
    if ($p['path'] === '/api/sysfera/ping') return $j(['ok' => true, 'users' => count($R['users'])]);
    if ($p['path'] === '/api/sysfera/contracts' && $m === 'POST') { $R['pushed'] = $in['contracts']; $R['push_dry'] = $dry; return $j(['dry_run' => $dry, 'new' => count($in['contracts']), 'updated' => 0, 'removed' => 1, 'rejected' => []]); }
    if ($p['path'] === '/api/sysfera/contracts') { $all = array_values(array_filter($R['contracts'], fn($c) => $c['id'] > (int)($q['after'] ?? 0))); $cut = array_slice($all, 0, 2); return $j(['contracts' => $cut, 'has_more' => count($all) > 2]); }
    if ($p['path'] === '/api/sysfera/offers') {
        $uidr = (int)($m === 'GET' ? ($q['user_id'] ?? 0) : ($in['user_id'] ?? 0)); $cid = (int)($m === 'GET' ? ($q['contract_id'] ?? 0) : ($in['contract_id'] ?? 0));
        if (!isset($R['users'][$uidr]) || $R['users'][$uidr]['sysfera_id'] === null) return $j(['error' => 'no_user'], 404);
        $ct = null; foreach ($R['contracts'] as $c) if ($c['id'] === $cid) $ct = $c; if (!$ct) return $j(['error' => 'no_contract'], 404);
        $mine = $R['offers'][$uidr . '-' . $cid] ?? null;
        if ($m === 'GET') return $j(['offer' => $mine, 'contract_status' => 'published']);
        $R['offer_in'] = $in;
        if ($ct['author']['id'] === $uidr) return $j(['error' => 'not_allowed'], 409);
        if ($mine) return $j(['error' => 'already_offered', 'offer' => $mine], 409);
        if (isset($in['steps_count']) && $in['steps_count'] !== count($ct['steps'])) return $j(['error' => 'contract_changed'], 409);
        $price = 0; $days = 0; foreach ($ct['steps'] as $i => $t) { $x = null; foreach ($in['items'] as $it) if ($it['n'] === $i + 1) $x = $it; if ($in['mode'] === 'accept' || !$x) { $price += $t['price']; $days += $t['days']; } elseif (empty($x['excluded'])) { $price += (float)$x['price']; $days += (int)$x['days']; } }
        if ($price <= 0) return $j(['error' => 'bad_offer', 'detail' => 'Enter a valid offer price.'], 422);
        if ($dry) return $j(['dry_run' => true, 'would_offer' => ['price' => $price, 'days' => $days]]);
        $R['offers'][$uidr . '-' . $cid] = ['id' => 900 + count($R['offers']), 'price' => $price, 'days' => $days, 'status' => 'pending', 'url' => 'https://www.bablito.ru/offer/900']; return $j(['offer' => $R['offers'][$uidr . '-' . $cid]], 201);
    }
    if ($p['path'] === '/api/sysfera/fields') return $j(['sections' => $R['sections']]);
    if ($p['path'] === '/api/sysfera/users' && $m === 'GET') { foreach ($R['users'] as $u) if ($u['email'] === strtolower($q['email'] ?? '')) return $j(['user' => $uj($u)]); return $j(['user' => null]); }
    if ($p['path'] === '/api/sysfera/users') {
        $found = null; foreach ($R['users'] as $u) if ($u['email'] === $in['email']) $found = $u;
        if ($found) {
            if ($found['sysfera_id'] !== null && $found['sysfera_id'] !== $in['sysfera_id']) return $j(['status' => 'conflict', 'detail' => 'linked elsewhere'], 409);
            if ($found['sysfera_id'] === null && empty($in['link'])) return $j(['status' => 'exists', 'user' => $uj($found)]);
            $was = $found['sysfera_id']; if ($was === null && !$dry) $R['users'][$found['id']]['sysfera_id'] = $in['sysfera_id'];
            return $j(['status' => $R['users'][$found['id']]['sysfera_id'] === null ? 'would_link' : 'linked', 'user' => $uj($R['users'][$found['id']])]);
        }
        if ($dry) return $j(['status' => 'would_create', 'user' => ['email' => $in['email']]]);
        $id = $R['next']++; $R['users'][$id] = ['id' => $id, 'email' => $in['email'], 'name' => $in['name'], 'types' => $in['types'], 'sysfera_id' => $in['sysfera_id']]; return $j(['status' => 'created', 'user' => $uj($R['users'][$id])], 201);
    }
    if (preg_match('~^/api/sysfera/profile/(\d+)$~', $p['path'], $mm)) {
        $id = (int)$mm[1]; if (!isset($R['users'][$id])) return $j(['error' => 'no_user'], 404);
        if ($m === 'GET') { $v = []; foreach ($R['values'][$id] ?? [] as $f => $x) $v[] = ['field_id' => $f] + $x; return $j(['user' => $uj($R['users'][$id]), 'values' => $v]); }
        $rep = ['applied' => [], 'skipped_newer_here' => [], 'unchanged' => [], 'rejected' => []];
        foreach ($in['values'] ?? [] as $v) {
            $cur = $R['values'][$id][$v['field_id']] ?? null;
            if ($cur && $cur['value'] === $v['value'] && $cur['is_public'] === $v['is_public']) $rep['unchanged'][] = $v['field_id'];
            elseif ($cur && strtotime($cur['updated_at'] . ' UTC') >= strtotime($v['updated_at'] . ' UTC')) $rep['skipped_newer_here'][] = $v['field_id'];
            else { $rep['applied'][] = $v['field_id']; if (!$dry) $R['values'][$id][$v['field_id']] = ['value' => $v['value'], 'is_public' => $v['is_public'], 'updated_at' => $v['updated_at']]; }
        }
        if (!$dry && isset($in['types'])) $R['users'][$id]['types'] = array_values(array_unique(array_merge($R['users'][$id]['types'], $in['types'])));
        return $j(['dry_run' => $dry] + $rep);
    }
    return [404, 'not found'];
};
$mk = fn() => new Bablito('https://www.bablito.ru', $key, $http, function () use (&$R) { return $R['now']; });
$st = new ContractsMemStore(); $people = [1 => ['id' => 1, 'name' => 'Anna', 'email' => 'Anna@X.test', 'lang' => 'ru'], 2 => ['id' => 2, 'name' => 'Zed', 'email' => 'zed@x.test', 'lang' => 'en'], 3 => ['id' => 3, 'name' => 'Tom', 'email' => 'taken@x.test', 'lang' => 'en']];
$users = fn(?array $ids) => $ids === null ? $people : array_intersect_key($people, array_flip($ids));
$sync = new Sync($st, $mk(), function () use (&$R) { return $R['now']; }, $users);

/* ---------- transport ---------- */
ok(err(fn() => (new Bablito('https://www.bablito.ru', 'short', $http, fn() => 1))->ping()) === 'bablito_not_configured', 'client: refuses a short key');
$R['bad'] = true; ok(err(fn() => $mk()->ping()) === 'bablito_bad_key', 'client: 401 is reported as a bad key'); $R['bad'] = false;
$R['down'] = true; ok(err(fn() => $mk()->ping()) === 'bablito_unreachable', 'client: a network failure is reported'); $R['down'] = false;
ok(($mk()->ping()['users'] ?? 0) === 2, 'client: ping works with a valid signature');
ok(!str_contains(json_encode($R['calls']), $key), 'the key never appears in a request line');

/* ---------- structure ---------- */
$st->insert('psections', ['title' => 'skills', 'type' => 'self', 'position' => 9, 'remote_id' => null]); $st->insert('psections', ['title' => 'Mine only', 'type' => 'personal', 'position' => 1, 'remote_id' => null]);
$d = $sync->structure(true); ok($d['sections_new'] === 1 && $d['fields_new'] === 3 && count($st->t['psections']) === 2 && empty($st->t['pfields']), 'structure dry run: reports, changes nothing');
$r = $sync->structure(false); $secs = $st->t['psections']; $flds = array_values($st->t['pfields'] ?? []);
ok($r['sections_new'] === 1 && $r['sections_updated'] === 1 && count($secs) === 3 && count($flds) === 3, 'structure: a section is matched by status + title, the other is created');
ok($secs[1]['remote_id'] === 8 && $secs[1]['title'] === 'Skills' && $secs[1]['position'] === 2, 'structure: the matched section takes the title and position of Bablito');
ok($secs[2]['remote_id'] === null, 'structure: a local-only section is kept');
$byRemote = []; foreach ($flds as $f) $byRemote[$f['remote_id']] = $f;
ok($byRemote[70]['required'] === 1 && $byRemote[70]['default_public'] === 0 && $byRemote[80]['options'] === "junior\nsenior" && $byRemote[81]['type'] === 'url', 'structure: required / public flags, options and types are mirrored');
ok(array_sum($sync->structure(false)) === 0, 'structure: running again changes nothing');
$R['sections'][1]['fields'][0]['required'] = true; ok($sync->structure(false)['fields_updated'] === 1, 'structure: a changed flag on Bablito is picked up');

/* ---------- users ---------- */
$d = $sync->linkUsers(true);
ok($d['would_link'] === 1 && $d['linked'] === 0 && empty($st->t['links']) && $R['users'][10]['sysfera_id'] === null, 'users dry run: reports the match, changes nothing on either side');
ok(count($d['unmatched']) === 1 && $d['unmatched'][0]['id'] === 2 && count($d['conflicts']) === 1 && $d['conflicts'][0]['id'] === 3, 'users: unmatched are listed, a Bablito account linked elsewhere is a conflict');
$l = $sync->linkUsers(false); $link = $st->one('links', ['user_id' => 1]);
ok($l['linked'] === 1 && $link && $link['remote_id'] === 10 && $R['users'][10]['sysfera_id'] === 1, 'users: matched by e-mail (any letter case), linked on both sides');
ok($st->one('links', ['user_id' => 2]) === null && count($R['users']) === 2, 'users: nobody is created automatically');
ok($sync->createAccount(2, true)['status'] === 'would_create' && count($R['users']) === 2, 'create account dry run: nothing is created');
$c = $sync->createAccount(2, false); $lk = $st->one('links', ['user_id' => 2]);
ok($c['status'] === 'created' && $lk && $lk['how'] === 'created' && $R['users'][$lk['remote_id']]['sysfera_id'] === 2, 'create account: created on Bablito and linked');
ok($R['users'][$lk['remote_id']]['types'] === ['personal'], 'create account: statuses are sent');
ok(err(fn() => $sync->profile(3, true)) === 'not_linked', 'profile: an unlinked user is refused');

/* ---------- profile: the newest change wins ---------- */
$fid = fn(int $remote) => (int)array_values(array_filter($st->t['pfields'], fn($f) => $f['remote_id'] === $remote))[0]['id'];
$f70 = $fid(70); $f80 = $fid(80); $f81 = $fid(81);
$st->insert('pvalues', ['user_id' => 1, 'field_id' => $f70, 'value' => 'TAX-1', 'is_public' => 0, 'updated_at' => '2026-11-08 08:00:00']);          // only here: pushed
$st->insert('pvalues', ['user_id' => 1, 'field_id' => $f80, 'value' => 'junior', 'is_public' => 1, 'updated_at' => '2026-11-01 08:00:00']);          // older here: Bablito's "senior" wins
$st->insert('pvalues', ['user_id' => 1, 'field_id' => $f81, 'value' => 'https://new.test', 'is_public' => 1, 'updated_at' => '2026-11-09 08:00:00']); // newer here: pushed
$st->insert('profiles', ['user_id' => 1, 'user_types' => 'personal', 'about' => '', 'notify_email' => 1, 'rating_avg' => 0, 'rating_count' => 0]);
$d = $sync->profile(1, true);
ok($d['pulled'] === 1 && $d['types'] === 'self,personal' && ($R['values'][10][81]['value'] ?? '') === 'https://anna.test' && !isset($R['values'][10][70]) && $st->one('pvalues', ['field_id' => $f80])['value'] === 'junior', 'profile dry run: reports, changes nothing on either side');
ok($st->one('profiles', ['user_id' => 1])['user_types'] === 'personal', 'profile dry run: statuses untouched');
$rep = $sync->profile(1, false); $v80 = $st->one('pvalues', ['field_id' => $f80]);
ok($rep['pulled'] === 1 && $v80['value'] === 'senior' && $v80['updated_at'] === '2026-11-09 10:00:00', 'profile: the newer Bablito value replaces the local one and keeps its change time');
ok($rep['pushed'] === 2 && $R['values'][10][70]['value'] === 'TAX-1' && $R['values'][10][70]['is_public'] === 0 && $R['values'][10][81]['value'] === 'https://new.test', 'profile: values only here, or newer here, are sent (with the public flag)');
ok($st->one('profiles', ['user_id' => 1])['user_types'] === 'self,personal' && $R['users'][10]['types'] === ['self', 'personal'], 'profile: statuses are merged on both sides (they only accumulate)');
ok($st->one('links', ['user_id' => 1])['synced_at'] !== null, 'profile: the link remembers the time of the last sync');
$again = $sync->profile(1, false); ok($again['pulled'] === 0 && $again['pushed'] === 0 && $again['unchanged'] === 3, 'profile: a second run changes nothing');
$st->update('pvalues', (int)$st->one('pvalues', ['field_id' => $f70])['id'], ['value' => '']); $R['now'] += 60; $e = $sync->profile(1, false);
ok($e['pulled'] === 1 && $st->one('pvalues', ['field_id' => $f70])['value'] === 'TAX-1' && $R['values'][10][70]['value'] === 'TAX-1', 'profile: an empty value never deletes, here or there');
$st->update('pvalues', (int)$st->one('pvalues', ['field_id' => $f81])['id'], ['value' => 'https://later.test', 'updated_at' => '2026-11-10 11:00:00']); $sync->profile(1, false);
ok($R['values'][10][81]['value'] === 'https://later.test', 'profile: a later local edit is sent');
$R['values'][10][81] = ['value' => 'https://remote-wins.test', 'is_public' => 1, 'updated_at' => '2026-11-10 11:00:00']; $sync->profile(1, false);
ok($st->one('pvalues', ['field_id' => $f81])['value'] === 'https://remote-wins.test', 'profile: at the same time Bablito wins (it is the master)');

/* ---------- contracts published on Bablito ---------- */
$st->insert('categories', ['name' => 'Design', 'name_ru' => 'Дизайн']);
$d = $sync->contracts(true); ok($d['total'] === 3 && $d['new'] === 3 && empty($st->t['remote']), 'contracts dry run: counts (over several pages), copies nothing');
$c = $sync->contracts(false); ok($c['new'] === 3 && count($st->t['remote']) === 3, 'contracts: all published ones are copied, page after page');
$r501 = $st->one('remote', ['remote_id' => 501]); $r502 = $st->one('remote', ['remote_id' => 502]);
ok($r501['total_price'] === '350.50' && $r501['total_days'] === 5 && $r501['payment_type'] === 'step' && $r501['prepay_required'] === 1 && $r501['author_name'] === 'Anna' && count(json_decode($r501['steps_json'], true)) === 2, 'contracts: total price and days come from the steps, steps and author are kept');
ok($r502['currency'] === 'RUB' && $r502['created_at'] !== 'bad' && $r502['deadline'] === null, 'contracts: a bad currency / date falls back to a safe value');
ok(array_sum($sync->contracts(false)) === 3 && $sync->contracts(false)['updated'] === 0, 'contracts: running again changes nothing');
$R['contracts'][0]['title'] = 'Logo design v2'; unset($R['contracts'][1]); $R['contracts'] = array_values($R['contracts']);
$u = $sync->contracts(false); ok($u['updated'] === 1 && $u['removed'] === 1 && $st->one('remote', ['remote_id' => 502]) === null && $st->one('remote', ['remote_id' => 501])['title'] === 'Logo design v2', 'contracts: a changed one is updated, one no longer published there is removed');
$R['down'] = true; ok(err(fn() => $sync->contracts(false)) === 'bablito_unreachable' && count($st->t['remote']) === 2, 'contracts: when Bablito cannot be reached nothing is removed'); $R['down'] = false;
$cs = new Svc($st, fn() => $R['now'], fn($ids) => [], null, ['bablito_base' => 'https://www.bablito.ru/']); $rl = $cs->listing(['per' => 50]);
$rem = array_values(array_filter($rl['rows'], fn($k) => !empty($k['remote'])));
ok(count($rem) === 2 && $rem[0]['status'] === 'published' && $rem[0]['remote_url'] === 'https://www.bablito.ru/contract/' . $rem[0]['remote_id'] && $rem[0]['user_id'] === 0, 'listing: the copies are listed with a link to the original');
ok(count(array_filter($cs->listing(['category' => 1, 'per' => 50])['rows'], fn($k) => !empty($k['remote']))) === 2 && $cs->listing(['category' => 2, 'per' => 50])['total'] === 0, 'listing: a copy takes the category of the same name (any letter case)');
ok($cs->listing(['q' => 'kazan'])['total'] === 1 && $cs->listing(['from' => '300', 'to' => '400'])['total'] === 1, 'listing: search and price filters work on the copies');
ok(err(fn() => $cs->remote(9999)) === 'not_found' && $cs->remote(501)['step_list'][1]['name'] === 'Final', 'remote contract: its own page, unknown id is not found');

/* ---------- contracts created here are pushed to Bablito ---------- */
$repairId = $st->insert('categories', ['name' => 'Repair', 'name_ru' => 'Ремонт']);
$st->insert('contracts', ['user_id' => 1, 'title' => 'Paint a fence', 'category_id' => $repairId, 'location' => 'Tver', 'description' => 'Two coats', 'status' => 'published', 'currency' => 'RUB', 'payment_type' => 'step', 'prepay_required' => 1, 'deadline' => '2026-12-20', 'published_at' => '2026-11-12 10:00:00', 'created_at' => '2026-11-11 10:00:00']);
$st->insert('contracts', ['user_id' => 2, 'title' => 'A draft', 'category_id' => 1, 'location' => '', 'description' => '', 'status' => 'draft', 'currency' => 'RUB', 'payment_type' => 'finish', 'prepay_required' => 0, 'deadline' => null, 'published_at' => null, 'created_at' => '2026-11-11 11:00:00']);
$st->insert('todos', ['contract_id' => 1, 'position' => 2, 'name' => 'Second coat', 'description' => '', 'price' => 40, 'days' => 2]); $st->insert('todos', ['contract_id' => 1, 'position' => 1, 'name' => 'First coat', 'description' => 'primer', 'price' => 60, 'days' => 1]);
$ps = new Sync($st, $mk(), fn() => $R['now'], $users, fn(int $id) => 'https://core.sysfera.ru/p/contracts/view/' . $id);
$d = $ps->pushContracts(true); ok($R['push_dry'] === true && count($R['pushed']) === 1, 'push (dry run): the request says dry_run and carries the published contract only');
$x = $ps->pushContracts(false); $it = $R['pushed'][0];
ok($R['push_dry'] === false && $it['id'] === 1 && $it['title'] === 'Paint a fence' && $it['category'] === 'Repair' && $it['author']['name'] === 'Anna' && $it['url'] === 'https://core.sysfera.ru/p/contracts/view/1' && $it['prepay_required'] === true && $it['payment_type'] === 'step', 'push: title, category name, author name, link and flags are sent');
ok(array_column($it['steps'], 'name') === ['First coat', 'Second coat'] && $x['new'] === 1 && $x['removed'] === 1, 'push: steps in order, counts come back');
$np = new Sync($st, $mk(), fn() => $R['now'], $users, null); $R['pushed'] = 'untouched'; $z = $np->pushContracts(false);
ok($R['pushed'] === 'untouched' && $z['skipped'] === 1, 'push: without a public https address nothing is sent (Bablito keeps what it has)');
$http2 = null; $bad = new Sync($st, $mk(), fn() => $R['now'], $users, fn(int $id) => 'http://plain.example/' . $id); $R['pushed'] = 'untouched'; $bad->pushContracts(false); ok($R['pushed'] === 'untouched', 'push: only https addresses are accepted');

/* ---------- sign in on Bablito from Sysfera ---------- */
$url = $sync->goUrl(1, '/contract/501'); $q = []; parse_str((string)parse_url($url, PHP_URL_QUERY), $q);
ok(str_starts_with($url, 'https://www.bablito.ru/sysfera-login?') && (int)$q['u'] === 10 && $q['r'] === '/contract/501' && $q['e'] > $R['now'] && $q['e'] <= $R['now'] + 180 && preg_match('/^[a-f0-9]{32}$/', $q['n']), 'sign-in link: the linked Bablito user, short life, one-time number, target path');
ok($q['s'] === hash_hmac('sha256', "login\n10\n{$q['e']}\n{$q['n']}\n/contract/501", $key) && !str_contains($url, $key), 'sign-in link: signed with the shared key, the key itself is not in it');
ok($sync->goUrl(1, '/contract/501') !== $url, 'sign-in link: every link has its own number');
ok(str_contains($mk()->loginUrl(10, '//evil.example'), 'r=%2Fcontracts'), 'sign-in link: only a path on Bablito is accepted as the target');
$st->insert('profiles', ['user_id' => 3, 'user_types' => 'personal', 'about' => '', 'notify_email' => 1, 'rating_avg' => 0, 'rating_count' => 0]);
$people2 = $people + [4 => ['id' => 4, 'name' => 'New Person', 'email' => 'newbie@x.test', 'lang' => 'en']]; $before = count($R['users']);
$sync2 = new Sync($st, $mk(), fn() => $R['now'], fn(?array $ids) => $ids === null ? $people2 : array_intersect_key($people2, array_flip($ids)));
$u4 = $sync2->goUrl(4, '/contracts'); $l4 = $st->one('links', ['user_id' => 4]);
ok(count($R['users']) === $before + 1 && $l4 && $l4['how'] === 'created' && str_contains($u4, 'u=' . $l4['remote_id']), 'sign-in: a user without a Bablito account is registered first, then signed in');
ok(err(fn() => $sync->goUrl(3, '/contracts')) === 'bablito_refused', 'sign-in: an account that belongs to another Sysfera user is refused');
/* ---------- an offer made from Sysfera (the user stays here) ---------- */
$people3 = $people2 + [5 => ['id' => 5, 'name' => 'Eve', 'email' => 'eve@x.test', 'lang' => 'en']];
$sy = new Sync($st, $mk(), fn() => $R['now'], fn(?array $ids) => $ids === null ? $people3 : array_intersect_key($people3, array_flip($ids))); $sy->contracts(false); // copies of the Bablito contracts
ok($sy->myOffer(2, 501) === null, 'offer: nothing yet');
$o = $sy->makeOffer(2, 501, ['mode' => 'accept']);
ok($o['status'] === 'pending' && abs($o['price'] - 350.5) < 0.001 && $o['days'] === 5 && $R['offer_in']['steps_count'] === 2 && $R['users'][$R['offer_in']['user_id']]['email'] === 'zed@x.test', 'offer: "accept as is" goes in as the linked Bablito user with the author\'s own prices');
ok(err(fn() => $sy->makeOffer(2, 501, ['mode' => 'accept'])) === 'offer_already', 'offer: a second offer on the same contract is refused');
ok($sy->myOffer(2, 501)['status'] === 'pending', 'offer: the user sees their offer');
ok(err(fn() => $sy->makeOffer(1, 501, ['mode' => 'accept'])) === 'offer_not_allowed', 'offer: the author of the contract cannot offer on it');
$o = $sy->makeOffer(5, 501, ['mode' => 'counter', 'payment_type' => 'step', 'notes' => 'hi', 'items' => [1 => ['price' => '90', 'days' => '1'], 2 => ['price' => '999', 'days' => '9', 'excluded' => '1'], 7 => ['price' => '1', 'days' => '1']]]);
ok(abs($o['price'] - 90.0) < 0.001 && $R['offer_in']['payment_type'] === 'step' && $R['offer_in']['notes'] === 'hi' && count($R['offer_in']['items']) === 2 && $R['offer_in']['items'][1]['excluded'] === true, 'offer: a counteroffer sends price, days, the "unnecessary" mark by step number; unknown steps are dropped');
ok(err(fn() => $sy->makeOffer(5, 9999, ['mode' => 'accept'])) === 'not_found', 'offer: a contract that is not in the copies is not found');
$R['contracts'][0]['steps'][] = ['name' => 'Extra', 'description' => '', 'price' => 1, 'days' => 1];
ok(err(fn() => $sy->makeOffer(4, 501, ['mode' => 'accept'])) === 'offer_changed', 'offer: when Bablito has different steps than our copy the user is asked to reload'); array_pop($R['contracts'][0]['steps']);
$R['down'] = true; ok($sy->myOffer(2, 501) === null, 'offer: when Bablito cannot be asked the page still opens'); $R['down'] = false;
/* ---------- everything at once ---------- */
$all = $sync->all(true);  ok(isset($all['pushed']['new']) && isset($all['contracts']['total']) && $all['dry'] === true && $all['profiles']['ok'] === 4 && $all['users']['conflicts'] && $all['users']['unmatched'] === [], 'sync all (dry): report for structure, users and every linked profile');
$all = $sync->all(false); ok($all['dry'] === false && $all['profiles']['failed'] === [] && $all['profiles']['ok'] === 4, 'sync all: no failures');

/* ---------- local edits are stamped (Svc::saveValues) ---------- */
$clock = strtotime('2026-11-12 09:00:00'); $svc = new Svc($st, function () use (&$clock) { return $clock; }, fn($ids) => [], null, []);
$svc->saveValues(5, ['self'], [$f80 => ['value' => 'junior', 'public' => 1]]); $row = $st->one('pvalues', ['user_id' => 5, 'field_id' => $f80]);
ok($row['updated_at'] === '2026-11-12 09:00:00', 'local edit: a new value gets its change time (UTC)');
$clock += 3600; $svc->saveValues(5, ['self'], [$f80 => ['value' => 'junior', 'public' => 1]]);
ok($st->one('pvalues', ['user_id' => 5, 'field_id' => $f80])['updated_at'] === '2026-11-12 09:00:00', 'local edit: saving without a change keeps the time');
$svc->saveValues(5, ['self'], [$f80 => ['value' => 'senior', 'public' => 1]]);
ok($st->one('pvalues', ['user_id' => 5, 'field_id' => $f80])['updated_at'] === '2026-11-12 10:00:00', 'local edit: a changed value gets the new time');

/* ---------- files ---------- */
$src = file_get_contents("$dir/lib/Bablito.php") . file_get_contents("$dir/lib/Sync.php") . file_get_contents("$dir/plugin.php");
ok(!preg_match('/(secret|api[_-]?key|token)\s*[=:]\s*[\'"][^\'"\s]{16,}/i', $src), 'no key or secret written into the sources');
ok(str_contains($src, "getenv('SYSFERA_BABLITO_KEY')") && !str_contains($src, 'bablito_key\', $'), 'the key comes from the environment or the write-only setting');
$mig = file_get_contents("$dir/migrations/002_bablito.sql"); ok(!preg_match('/\b(DROP|TRUNCATE|DELETE FROM|MODIFY|CHANGE)\b/i', $mig) && str_contains($mig, '{p}ct_links'), 'migration 002: additive only');

echo $fail ? "\n$fail FAILED\n" : "\nAll good\n"; exit($fail ? 1 : 0);
