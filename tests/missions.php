<?php
declare(strict_types=1);
require dirname(__DIR__) . '/core/plugins/missions/lib/Mission.php';
use SysferaMissions\Mission;

if (!function_exists('mb_substr')) { function mb_substr(string $s, int $start, ?int $len = null): string { return $len === null ? substr($s, $start) : substr($s, $start, $len); } }   // CLI without mbstring

$fail = 0;
function ok(bool $c, string $n): void { global $fail; echo ($c ? '  ok   ' : '  FAIL ') . $n . "\n"; if (!$c) $fail++; }

ok(Mission::type('Call') === 'Call' && Mission::type('Hack') === 'Note', 'type: unknown falls back to Note');
ok(Mission::branch('unexpected') === 'bad' && Mission::branch('good') === 'good' && Mission::branch('x') === null, 'branch: Tactify "unexpected" is "bad", junk is none');
$d = Mission::cleanData('Email', ['to' => 'a@b.co', 'subject' => ' Hi ', 'body' => 'Text', 'phone' => '123', 'url' => 'https://x.y']);
ok($d === ['to' => 'a@b.co', 'subject' => 'Hi', 'body' => 'Text'], 'cleanData: only the fields of the type');
ok(Mission::cleanData('Email', ['to' => 'not-an-email']) === [], 'cleanData: invalid address dropped');
ok(Mission::cleanData('Visit URL', ['url' => 'javascript:alert(1)']) === [] && Mission::cleanData('Visit URL', ['url' => 'https://a.b/c']) === ['url' => 'https://a.b/c'], 'cleanData: only http(s) links');
ok(Mission::cleanData('Go to task', ['target' => '12']) === ['target' => '12'] && Mission::cleanData('Go to task', ['target' => 'x']) === [], 'cleanData: target is a task id');

$tasks = [
    ['id' => 1, 'parent_id' => null, 'branch' => null, 'type' => 'Call'],
    ['id' => 2, 'parent_id' => 1, 'branch' => 'bad', 'type' => 'Note'],
    ['id' => 3, 'parent_id' => 1, 'branch' => 'good', 'type' => 'Meeting'],
    ['id' => 4, 'parent_id' => 3, 'branch' => 'good', 'type' => 'Finish Mission'],
];
ok(Mission::luckyWay($tasks) === [1, 3, 4], 'lucky way: first task → good results → Finish Mission');
ok(Mission::luckyWay(array_slice($tasks, 0, 3)) === [], 'lucky way: none without a Finish Mission');
$loop = [['id' => 1, 'parent_id' => null, 'branch' => null, 'type' => 'Note'], ['id' => 2, 'parent_id' => 1, 'branch' => 'good', 'type' => 'Note'], ['id' => 3, 'parent_id' => 2, 'branch' => 'good', 'type' => 'Note']];
ok(Mission::luckyWay($loop) === [], 'lucky way: a chain without a finish ends');
$t = Mission::tree($tasks);
ok(count($t[0]['']) === 1 && count($t[1]['good']) === 1 && count($t[1]['bad']) === 1, 'tree: children grouped by parent and result');

echo $fail ? "\n$fail FAILED\n" : "\nAll good\n";
exit($fail ? 1 : 0);
