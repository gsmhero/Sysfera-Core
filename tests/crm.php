<?php
declare(strict_types=1);
/** Tests for the CRM component's pure logic (fields, pipeline maths, permissions, CSV) and its language files. Run: php tests/crm.php */
if (!function_exists('mb_substr')) { function mb_substr(string $s, int $start, ?int $len = null): string { return $len === null ? substr($s, $start) : substr($s, $start, $len); } }
if (!function_exists('mb_strlen')) { function mb_strlen(string $s): int { return strlen($s); } }
if (!function_exists('mb_strtolower')) { function mb_strtolower(string $s): string { return strtolower($s); } }   // CLI without mbstring
$dir = dirname(__DIR__) . '/core/plugins/crm';
require $dir . '/lib/Fields.php'; require $dir . '/lib/Pipe.php';

use SysferaCrm\{Fields, Pipe};

$fail = 0;
function ok(bool $c, string $n): void { global $fail; echo ($c ? '  ok   ' : '  FAIL ') . $n . "\n"; if (!$c) $fail++; }

/* field codes and options */
ok(Fields::code('Lead source') === 'lead_source', 'code from label');
ok(Fields::code('Источник') === 'f', 'non-latin label gets a safe code');
ok(Fields::code('Lead source', ['lead_source']) === 'lead_source_2', 'codes stay unique');
ok(Fields::code('1st') === 'f_1st', 'code starts with a letter');
ok(Fields::options("a, b\nb; c ,,") === ['a', 'b', 'c'], 'options split and deduplicated');

/* field validation */
$defs = [
    ['code' => 'budget', 'label' => 'Budget', 'type' => 'money', 'required' => true],
    ['code' => 'due', 'label' => 'Due', 'type' => 'date'],
    ['code' => 'kind', 'label' => 'Kind', 'type' => 'select', 'options' => ['A', 'B']],
    ['code' => 'tags', 'label' => 'Tags', 'type' => 'multiselect', 'options' => ['x', 'y', 'z']],
    ['code' => 'vip', 'label' => 'VIP', 'type' => 'checkbox'],
    ['code' => 'mail', 'label' => 'Mail', 'type' => 'email'],
    ['code' => 'site', 'label' => 'Site', 'type' => 'url'],
    ['code' => 'tel', 'label' => 'Tel', 'type' => 'phone'],
];
[$v, $e] = Fields::clean($defs, ['budget' => '1 250,5', 'due' => '2026-02-30', 'kind' => 'C', 'tags' => ['x', 'q'], 'mail' => 'bad', 'site' => 'javascript:alert(1)', 'tel' => 'abc']);
ok(count($e) === 6, 'six invalid values are reported (got ' . count($e) . ')');
ok(($v['budget'] ?? null) === 1250.5, 'money accepts comma and spaces');
[$v, $e] = Fields::clean($defs, ['budget' => '10', 'due' => '2026-02-28', 'kind' => 'B', 'tags' => ['x', 'z'], 'vip' => '1', 'mail' => 'A@B.RU', 'site' => 'https://a.b/c', 'tel' => '+7 (900) 123-45-67']);
ok(!$e && $v['due'] === '2026-02-28' && $v['kind'] === 'B' && $v['tags'] === ['x', 'z'] && $v['vip'] === true && $v['mail'] === 'a@b.ru' && $v['tel'] === '+7 (900) 123-45-67', 'valid values are normalised');
[$v, $e] = Fields::clean($defs, []);
ok($e === ['Budget: required'] && $v['vip'] === false, 'required field is enforced, checkbox defaults to false');
ok(Fields::show($defs[0], 1250.5) === '1 250.50' && Fields::show($defs[3], ['x', 'y']) === 'x, y' && Fields::show($defs[4], true) === '✓', 'values are shown readably');
$all = [['entity' => 'deal', 'pipeline_id' => null, 'code' => 'a'], ['entity' => 'deal', 'pipeline_id' => 2, 'code' => 'b'], ['entity' => 'deal', 'pipeline_id' => 3, 'code' => 'c'], ['entity' => 'contact', 'pipeline_id' => null, 'code' => 'd']];
ok(array_column(Fields::applicable($all, 'deal', 2), 'code') === ['a', 'b'], 'deal fields: global plus those of the pipeline');
ok(array_column(Fields::applicable($all, 'contact'), 'code') === ['d'], 'contact fields');

/* pipeline maths */
$stages = [1 => ['kind' => 'open', 'prob' => 10], 2 => ['kind' => 'open', 'prob' => 50], 3 => ['kind' => 'won', 'prob' => 100], 4 => ['kind' => 'lost', 'prob' => 0]];
$deals = [['amount' => '1000', 'stage_id' => 1], ['amount' => '2000', 'stage_id' => 2], ['amount' => '500', 'stage_id' => 3], ['amount' => '300', 'stage_id' => 3], ['amount' => '700', 'stage_id' => 4], ['amount' => '9', 'stage_id' => 99]];
$s = Pipe::summary($deals, $stages);
ok($s['open_n'] === 2 && $s['open_sum'] === 3000.0 && $s['weighted'] === 1100.0, 'open total and weighted forecast');
ok($s['won_n'] === 2 && $s['won_sum'] === 800.0 && $s['lost_n'] === 1 && $s['win_rate'] === 66.7, 'won/lost totals and win rate');
ok(Pipe::summary([], $stages)['win_rate'] === null, 'no closed deals: win rate is empty');
ok(array_map('count', Pipe::board($deals, [1, 2, 3, 4])) === [1 => 1, 2 => 1, 3 => 2, 4 => 1], 'board groups deals by stage and drops unknown stages');
ok(Pipe::nextSort([['sort' => 10], ['sort' => 30]]) === 40 && Pipe::nextSort([]) === 10, 'next sort position');

/* task states */
$now = strtotime('2026-11-13 12:00:00');
ok(Pipe::taskState('2026-11-12', null, $now) === 'overdue', 'yesterday (date only) is overdue');
ok(Pipe::taskState('2026-11-13 00:00:00', null, $now) === 'today', 'today (date only) is today, not overdue');
ok(Pipe::taskState('2026-11-13 09:00:00', null, $now) === 'overdue', 'earlier today with a time is overdue');
ok(Pipe::taskState('2026-11-13 18:00:00', null, $now) === 'today', 'later today with a time is today');
ok(Pipe::taskState('2026-11-20', null, $now) === 'upcoming' && Pipe::taskState(null, null, $now) === 'none' && Pipe::taskState('2020-01-01', '2026-01-01 10:00:00', $now) === 'done', 'upcoming, none, done');

/* permission profiles */
$p = Pipe::profile(['scope' => 'weird', 'edit' => 1, 'hack' => 1]);
ok($p['scope'] === 'own' && $p['edit'] === true && $p['delete'] === false && !isset($p['hack']), 'profile keeps only known keys; unknown scope becomes own');
$d = Pipe::defaultProfiles();
ok(!$d['Manager']['delete'] && $d['Manager']['scope'] === 'own' && $d['Team lead']['scope'] === 'all' && $d['Team lead']['export'] && !$d['Observer']['edit'], 'default profiles');
ok(Pipe::visible($d['Manager'], 5, 5) && !Pipe::visible($d['Manager'], 6, 5) && Pipe::visible($d['Manager'], null, 5) && Pipe::visible($d['Observer'], 6, 5), 'own scope sees own and unowned records; all scope sees everything');

/* CSV */
$rows = Pipe::csvParse("\xEF\xBB\xBFname;email;notes\r\n\"Smith; John\";a@b.c;\"said \"\"hi\"\"\nsecond line\"\r\nAnna;;\r\n\r\n");
ok(count($rows) === 3 && $rows[1][0] === 'Smith; John' && $rows[1][2] === "said \"hi\"\nsecond line" && $rows[2] === ['Anna', '', ''], 'CSV: BOM, semicolon, quotes, line breaks, empty lines');
ok(Pipe::csvParse("a,b\n1,2")[1] === ['1', '2'] && Pipe::csvParse("a\tb\n1\t2")[1] === ['1', '2'], 'CSV: comma and tab delimiters are detected');
ok(Pipe::csvLine(['a;b', 'x"y', '=SUM(A1)', '-5', '+7 900', 'ok']) === '"a;b";"x""y";\'=SUM(A1);-5;\'+7 900;ok', 'CSV line: quoting and formula-injection guard (numbers stay numbers)');

/* language files: same keys, and every key the code uses exists */
$en = require $dir . '/lang/en.php'; $ru = require $dir . '/lang/ru.php';
ok(array_diff_key($en, $ru) === [] && array_diff_key($ru, $en) === [], 'en and ru have the same keys');
$used = [];
foreach (array_merge([$dir . '/plugin.php'], glob($dir . '/views/*.php')) as $f) {
    $src = (string)file_get_contents($f);
    preg_match_all('/\$sf->t\(\s*\'([a-z_0-9]+)\'/', $src, $m); foreach ($m[1] as $k) if (!str_ends_with($k, "_")) $used[$k] = basename($f);   // keys ending in _ are built dynamically (checked below)
}
$missing = array_filter($used, fn($f, $k) => !isset($en[$k]), ARRAY_FILTER_USE_BOTH);
ok(!$missing, 'every translation key used exists in en.php' . ($missing ? ' (missing: ' . implode(', ', array_keys($missing)) . ')' : ''));
$dyn = ['tt_' => Pipe::TASK_TYPES, 'ft_' => Fields::TYPES, 'perm_' => Pipe::PERMS, 's_' => Pipe::KINDS, 'ent_' => Fields::ENTITIES, 'tl_' => ['note', 'stage', 'created', 'edit', 'task', 'task_done'],
        'g_' => ['overdue', 'today', 'upcoming', 'none', 'done'], 'tf_' => ['mine', 'all', 'done'], 'scope_' => ['own', 'all'], 'prof_' => ['manager', 'team_lead', 'observer']];
$gaps = []; foreach ($dyn as $pre => $keys) foreach ($keys as $k) if (!isset($en[$pre . $k])) $gaps[] = $pre . $k;
ok(!$gaps, 'dynamic translation keys exist' . ($gaps ? ' (missing: ' . implode(', ', $gaps) . ')' : ''));
$m = json_decode((string)file_get_contents($dir . '/plugin.json'), true);
ok(($m['slug'] ?? '') === 'crm' && isset($m['capabilities']['crm.use']), 'manifest is valid');

echo $fail ? "\n$fail FAILED\n" : "\nAll good\n";
exit($fail ? 1 : 0);
