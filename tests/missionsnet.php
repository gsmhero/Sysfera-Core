<?php
declare(strict_types=1);
/** Network library of Missions: cleaning of published missions, Sysfera ⇄ Tactify conversion, request signature. No database, no network. Run: php tests/missionsnet.php */
$root = dirname(__DIR__);
if (!function_exists('mb_substr')) { function mb_substr(string $s, int $a = 0, ?int $l = null): string { return $l === null ? substr($s, $a) : substr($s, $a, $l); } }
require $root . '/core/app/TaskTypes.php'; require $root . '/core/plugins/missions/lib/Network.php';
use SysferaMissions\Network;

$fail = 0;
function ok(bool $c, string $n): void { global $fail; echo ($c ? '  ok   ' : '  FAIL ') . $n . "\n"; if (!$c) $fail++; }

// a mission as a site exports it
$site = ['format' => 'sysfera-mission', 'title' => ' Win <b>big</b> client ', 'type' => 'Sales', 'description' => "Plan <script>x</script>\nsecond line", 'tasks' => [
    ['parent' => null, 'branch' => null, 'title' => 'Call', 'type' => 'call', 'cost' => 5, 'description' => '', 'result_good' => 'Yes', 'result_alt' => '', 'result_bad' => 'No', 'type_data' => ['phone' => '+7 900', 'evil' => ['a' => 1], 'contact_id' => '5']],
    ['parent' => 0, 'branch' => 'good', 'title' => 'Meet', 'type' => 'meeting', 'cost' => 0, 'description' => 'd', 'result_good' => '', 'result_alt' => '', 'result_bad' => '', 'type_data' => []],
    ['parent' => 9, 'branch' => 'bad', 'title' => 'Orphan', 'type' => 'unknown thing', 'cost' => -3, 'description' => '', 'result_good' => '', 'result_alt' => '', 'result_bad' => '', 'type_data' => []],
    ['parent' => 0, 'branch' => 'bad', 'title' => 'Fail', 'type' => 'finish_mission', 'cost' => 0, 'description' => '', 'result_good' => '', 'result_alt' => '', 'result_bad' => '', 'type_data' => []],
]];
$c = Network::sanitize($site);
ok($c && $c['title'] === 'Win big client' && !str_contains($c['description'], '<') && $c['format'] === 'sysfera-mission', 'title trimmed, HTML stripped');
ok($c['tasks'][0]['type_data'] == ['phone' => '+7 900', 'contact_id' => '5'] || (array)$c['tasks'][0]['type_data'] === ['phone' => '+7 900', 'contact_id' => '5'], 'task data: only short scalar values');
ok($c['tasks'][1]['parent'] === 0 && $c['tasks'][1]['branch'] === 'good' && $c['tasks'][3]['branch'] === 'bad', 'parent and branch kept');
ok($c['tasks'][2]['parent'] === null && $c['tasks'][2]['type'] === 'unknown_thing' && $c['tasks'][2]['cost'] === 0.0, 'a parent that does not exist is dropped, negative cost is 0');
ok(Network::sanitize(['title' => '  ', 'tasks' => []]) === null, 'no title = rejected');
$many = ['title' => 'x', 'tasks' => array_fill(0, 500, ['title' => 't', 'type' => 'note'])];
ok(count(Network::sanitize($many)['tasks']) === Network::MAX_TASKS, 'at most ' . Network::MAX_TASKS . ' tasks');
ok(Network::sanitize(['title' => 'big', 'description' => str_repeat('é', 6000), 'tasks' => array_fill(0, 300, ['title' => str_repeat('a', 255), 'type' => 'note', 'description' => str_repeat('b', 5000)])]) === null, 'too large (>900 KB) = rejected');

// to Tactify and back
$t = Network::toTactify($c, 'site:shop.example.ru:7', 'Ann', 'Sales', 'Продажи');
ok($t['external_id'] === 'site:shop.example.ru:7' && $t['author'] === 'Ann' && count($t['tasks']) === 4, 'Tactify body');
ok($t['tasks'][0]['parent_link_id'] === '' && $t['tasks'][1]['parent_link_id'] === 'k0_good' && $t['tasks'][3]['parent_link_id'] === 'k0_unexpected', 'parent links in Tactify form (bad = unexpected)');
$back = Network::sanitize(['title' => $t['title'], 'type' => $t['type'], 'description' => $t['description'], 'tasks' => $t['tasks']]);
ok($back['tasks'][1]['parent'] === 0 && $back['tasks'][1]['branch'] === 'good' && $back['tasks'][3]['branch'] === 'bad' && $back['tasks'][0]['parent'] === null, 'Tactify form comes back to the same tree');
// a real Tactify mission
$tm = ['title' => 'Lead', 'type' => 'Sales', 'description' => '', 'tasks' => [
    ['id' => 'T-101', 'title' => 'Call', 'type' => 'Call', 'cost' => '10.00', 'parent_link_id' => 'mission_M-1001', 'result_good' => 'ok', 'result_alt' => 'Alternative', 'result_unexpected' => 'Failed', 'typeData' => ['phone' => '1']],
    ['id' => 'T-102', 'title' => 'Done', 'type' => 'Finish Mission', 'cost' => '0', 'parent_link_id' => 'T-101_good', 'typeData' => []]]];
$f = Network::sanitize($tm);
ok($f['tasks'][0]['type'] === 'call' && $f['tasks'][1]['type'] === 'finish_mission' && $f['tasks'][1]['parent'] === 0 && $f['tasks'][1]['branch'] === 'good' && $f['tasks'][0]['parent'] === null, 'a Tactify mission becomes a Sysfera one');

// signature must match api/v1/library.php on Tactify: ts \n METHOD \n script?query \n sha256(body)
$s = Network::sign('secret', 1700000000, 'GET', 'id=M-1', '');
ok($s === hash_hmac('sha256', "1700000000\nGET\n/api/v1/library.php?id=M-1\n" . hash('sha256', ''), 'secret'), 'signature format');
ok(Network::baseUrl('https://tactify.ru/library.php') === 'https://tactify.ru' && Network::baseUrl('http://tactify.ru') === '' && Network::baseUrl('https://Tactify.ru:8443/x?y') === 'https://tactify.ru:8443' && Network::baseUrl('javascript:alert(1)') === '', 'address of Tactify: https only, path dropped');
ok(Network::extFor(['source' => 'site:shop.example.ru', 'ext_id' => '7']) === 'site:shop.example.ru:7' && strlen(Network::extFor(['source' => 'site:' . str_repeat('a', 150), 'ext_id' => '7'])) === 120, 'external id for Tactify');
echo $fail ? "\n$fail FAILED\n" : "\nAll good\n";
exit($fail ? 1 : 0);
