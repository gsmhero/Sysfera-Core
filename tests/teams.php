<?php
declare(strict_types=1);
/** Tests for the Teams component's pure logic (roles, mentions, files, calendar) and its language files. Run: php tests/teams.php */
foreach (['mb_substr' => fn(string $s, int $a, ?int $l = null) => $l === null ? substr($s, $a) : substr($s, $a, $l), 'mb_strlen' => fn(string $s) => strlen($s), 'mb_strtolower' => fn(string $s) => strtolower($s),
          'mb_strpos' => fn(string $h, string $n, int $o = 0) => strpos($h, $n, $o)] as $fn => $impl) {
    if (!function_exists($fn)) eval('function ' . $fn . '(...$a) { global $__mb; return ($__mb["' . $fn . '"])(...$a); }');
    $GLOBALS['__mb'][$fn] = $impl;
}
$dir = dirname(__DIR__) . '/core/plugins/teams';
require $dir . '/lib/Tm.php';

use SysferaTeams\Tm;

$fail = 0;
function ok(bool $c, string $n): void { global $fail; echo ($c ? '  ok   ' : '  FAIL ') . $n . "\n"; if (!$c) $fail++; }

/* roles */
ok(Tm::can('member', 'post') && Tm::can('member', 'task') && !Tm::can('member', 'channel') && !Tm::can('member', 'members') && !Tm::can('member', 'moderate'), 'member: works, does not manage');
ok(Tm::can('admin', 'channel') && Tm::can('admin', 'members') && Tm::can('admin', 'moderate') && !Tm::can('admin', 'roles') && !Tm::can('admin', 'delete_team'), 'admin: manages people and channels, not roles or the team');
ok(Tm::can('owner', 'roles') && Tm::can('owner', 'delete_team') && Tm::can('owner', 'post'), 'owner: everything');
ok(!Tm::can(null, 'read') && !Tm::can('stranger', 'read') && !Tm::can('member', 'nonsense'), 'no role or unknown action: nothing');

/* mentions */
$people = [1 => 'Ann', 2 => 'Ann Lee', 3 => 'Bob', 4 => 'Al'];
ok(Tm::mentions('hi @Bob!', $people) === [3], 'mention at the end with punctuation');
ok(Tm::mentions('@ann lee please', $people) === [2], 'longest name wins, case-insensitive (Ann Lee, not Ann)');
ok(Tm::mentions('hello @Annie', $people) === [], 'a longer word is not a mention (@Annie is not Ann)');
ok(Tm::mentions('@Bob @Ann', $people, 3) === [1], 'the author is never notified of his own mention');
ok(Tm::mentions('no mentions here', $people) === [], 'no mentions');

/* message text */
ok(Tm::text('  hello  ')[0] === 'hello' && Tm::text('')[1] === 'e_empty' && Tm::text('', true)[1] === null, 'text: trimmed, empty only allowed with a file');
ok(Tm::text(str_repeat('a', 4001))[1] === 'e_long' && Tm::text(str_repeat('a', 4000))[1] === null, 'text: 4000 characters at most');
ok(Tm::text("a\n\n\n\n\n\nb")[0] === "a\n\n\nb", 'text: runs of blank lines are shortened');

/* files */
ok(Tm::file('Report 2026.PDF', 1000) === ['Report 2026.pdf', 'pdf', 'application/pdf'], 'file: extension and mime come from the name');
ok(Tm::file('a.php', 10) === null && Tm::file('a.html', 10) === null && Tm::file('a.svg', 10) === null && Tm::file('a.exe', 10) === null && Tm::file('noext', 10) === null, 'file: scripts, HTML, SVG, executables are refused');
ok(Tm::file('a.png', Tm::MAX_FILE) !== null && Tm::file('a.png', Tm::MAX_FILE + 1) === null && Tm::file('a.png', 0) === null, 'file: 5 MB limit, empty files refused');
ok(Tm::file('..\\..\\evil/../x.txt', 5)[0] === 'x.txt', 'file: path parts are stripped');
ok(Tm::file('a<b>"c.txt', 5)[0] === 'a_b__c.txt' || Tm::file('a<b>"c.txt', 5)[0] === 'a_b_c.txt', 'file: odd characters in the name are replaced');
ok(Tm::file('shell.php.jpg', 5)[1] === 'jpg', 'file: only the last extension counts and the type is the image one');

/* calendar */
$now = strtotime('2026-11-13 10:00:00');
$m = Tm::month('2026-11', $now);
ok($m['ym'] === '2026-11' && $m['prev'] === '2026-10' && $m['next'] === '2026-12' && count($m['weeks']) === 6, 'month: Nov 2026 spans 6 weeks (starts on Sunday)');
ok($m['weeks'][0][0]['date'] === '2026-10-26' && !$m['weeks'][0][0]['in'] && $m['weeks'][0][6]['date'] === '2026-11-01' && $m['weeks'][0][6]['in'], 'month: weeks start on Monday');
$today = array_values(array_filter(array_merge(...$m['weeks']), fn($d) => $d['today']));
ok(count($today) === 1 && $today[0]['date'] === '2026-11-13', 'month: today is marked once');
ok(Tm::month('garbage', $now)['ym'] === '2026-11' && Tm::month('2026-13', $now)['ym'] === '2026-11', 'month: a bad value falls back to the current month');
ok(Tm::month('2026-12', $now)['next'] === '2027-01' && Tm::month('2027-01', $now)['prev'] === '2026-12', 'month: year rollover');
$by = Tm::byDay([['id' => 1, 'starts' => '2026-11-10 09:00:00', 'ends' => '2026-11-12 18:00:00'], ['id' => 2, 'starts' => '2026-11-11 10:00:00', 'ends' => null]]);
ok(array_keys($by) === ['2026-11-10', '2026-11-11', '2026-11-12'] && count($by['2026-11-11']) === 2, 'events are listed on every day they cover');
ok(Tm::when('2026-02-30') === null && Tm::when('2026-02-28') === '2026-02-28 00:00:00' && Tm::when('2026-02-28', '09:05') === '2026-02-28 09:05:00' && Tm::when('2026-02-28', '25:00') === '2026-02-28 00:00:00', 'when(): validates date and time');
ok(Tm::clip("a   b\n c", 10) === 'a b c' && Tm::clip(str_repeat('x', 20), 5) === 'xxxx…', 'clip');
ok(Tm::nextSort([['sort' => 10], ['sort' => 40]]) === 50 && Tm::nextSort([]) === 10, 'next sort');

/* language files: same keys, and every key the code uses exists */
$en = require $dir . '/lang/en.php'; $ru = require $dir . '/lang/ru.php';
ok(array_diff_key($en, $ru) === [] && array_diff_key($ru, $en) === [], 'en and ru have the same keys');
$used = [];
foreach (array_merge([$dir . '/plugin.php'], glob($dir . '/views/*.php')) as $f) {
    $src = (string)file_get_contents($f);
    preg_match_all('/\$sf->t\(\s*\'([a-z_0-9]+)\'/', $src, $mm); foreach ($mm[1] as $k) if (!str_ends_with($k, '_')) $used[$k] = basename($f);
}
$missing = array_filter($used, fn($f, $k) => !isset($en[$k]), ARRAY_FILTER_USE_BOTH);
ok(!$missing, 'every translation key used by PHP exists in en.php' . ($missing ? ' (missing: ' . implode(', ', array_keys($missing)) . ')' : ''));
$dyn = ['role_' => Tm::ROLES, 'month_' => range(1, 12), 'd_' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun']]; $gaps = [];
foreach ($dyn as $pre => $keys) foreach ($keys as $k) if (!isset($en[$pre . $k])) $gaps[] = $pre . $k;
foreach (['prio_low', 'prio_mid', 'prio_high', 'e_empty', 'e_long'] as $k) if (!isset($en[$k])) $gaps[] = $k;
ok(!$gaps, 'dynamic keys exist' . ($gaps ? ' (missing: ' . implode(', ', $gaps) . ')' : ''));
$js = (string)file_get_contents($dir . '/assets/teams.js'); preg_match_all('/\bT\.([a-z_]+)/', $js, $jm); $jgaps = array_values(array_filter(array_unique($jm[1]), fn($k) => !isset($en[$k])));
ok(!$jgaps, 'every translation key used by the JavaScript exists' . ($jgaps ? ' (missing: ' . implode(', ', $jgaps) . ')' : ''));
$m = json_decode((string)file_get_contents($dir . '/plugin.json'), true);
ok(($m['slug'] ?? '') === 'teams' && isset($m['capabilities']['teams.use'], $m['capabilities']['teams.create']), 'manifest is valid');

echo $fail ? "\n$fail FAILED\n" : "\nAll good\n";
exit($fail ? 1 : 0);
