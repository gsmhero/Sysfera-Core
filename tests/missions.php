<?php
declare(strict_types=1);
require dirname(__DIR__) . '/core/app/TaskTypes.php';
require dirname(__DIR__) . '/core/plugins/missions/lib/Mission.php';
use SysferaMissions\Mission;
use Sysfera\TaskTypes;

if (!function_exists('mb_substr')) { function mb_substr(string $s, int $start, ?int $len = null): string { return $len === null ? substr($s, $start) : substr($s, $start, $len); } }   // CLI without mbstring

$fail = 0;
function ok(bool $c, string $n): void { global $fail; echo ($c ? '  ok   ' : '  FAIL ') . $n . "\n"; if (!$c) $fail++; }

$T = TaskTypes::all(null, null, true);   // built-in list (no database)
ok(Mission::type('Call', $T) === 'call' && Mission::type('Finish Mission', $T) === 'finish_mission' && Mission::type('Visit URL', $T) === 'visit_url' && Mission::type('Hack', $T) === 'note', 'type: old names map to codes, unknown falls back to note');
ok(Mission::branch('unexpected') === 'bad' && Mission::branch('good') === 'good' && Mission::branch('x') === null, 'branch: Tactify "unexpected" is "bad", junk is none');
$d = Mission::cleanData('email', ['to' => 'a@b.co', 'subject' => ' Hi ', 'body' => 'Text', 'phone' => '123', 'url' => 'https://x.y'], $T);
ok($d === ['to' => 'a@b.co', 'subject' => 'Hi', 'body' => 'Text'], 'cleanData: only the fields of the type');
ok(Mission::cleanData('email', ['to' => 'not-an-email'], $T) === [], 'cleanData: invalid address dropped');
ok(Mission::cleanData('visit_url', ['url' => 'javascript:alert(1)'], $T) === [] && Mission::cleanData('visit_url', ['url' => 'https://a.b/c'], $T) === ['url' => 'https://a.b/c'], 'cleanData: only http(s) links');
ok(Mission::cleanData('go_to_task', ['target' => '12'], $T) === ['target' => '12'] && Mission::cleanData('go_to_task', ['target' => 'x'], $T) === [], 'cleanData: target is a task id');
ok(Mission::cleanData('call', ['phone' => '+7 (900) 123-45-67', 'contact_id' => '5'], $T) === ['contact_id' => '5', 'phone' => '+7 (900) 123-45-67'] && Mission::cleanData('call', ['phone' => '<script>'], $T) === [], 'cleanData: phone and CRM contact id');
ok(Mission::cleanData('buy_product', ['url' => 'https://basebase.ru/product/7'], $T) === ['url' => 'https://basebase.ru/product/7'] && Mission::cleanData('buy_product', ['url' => 'ftp://x'], $T) === [], 'cleanData: Basebase shop link is a http(s) link');
ok(Mission::cleanData('Email', ['to' => 'a@b.co'], $T) === ['to' => 'a@b.co'], 'cleanData: accepts an old type name');

/* shared task types registry (also used by CRM) */
$crm = TaskTypes::all(null, 'crm'); $mis = TaskTypes::all(null, 'missions');
ok(isset($crm['todo'], $crm['call'], $crm['email'], $crm['meeting'], $crm['deadline'], $crm['buy_product']) && !isset($crm['finish_mission'], $crm['go_to_task']), 'types: CRM gets the common types, not the mission-flow ones');
ok(count(array_diff_key($crm, $mis)) === 0 && isset($mis['finish_mission'], $mis['go_to_task']), 'types: every CRM type is also a mission type');
ok(TaskTypes::normalize('Finish Mission') === 'finish_mission' && TaskTypes::normalize('Task Done') === 'task_done' && TaskTypes::normalize('e-mail') === 'email' && TaskTypes::normalize(' Visit  URL ') === 'visit_url', 'types: normalize old spellings');
$f = TaskTypes::fieldDefs([['key' => 'Bad Key', 'kind' => 'text'], ['key' => 'ok_1', 'kind' => 'weird', 'label' => 'L'], ['key' => 'ok_1', 'kind' => 'text']]);
ok(count($f) === 1 && $f[0]['key'] === 'ok_1' && $f[0]['kind'] === 'text', 'types: field definitions are tidied (bad keys and duplicates dropped, unknown kind = text)');
$custom = ['code' => 'visit', 'name' => 'Visit', 'name_ru' => 'Визит', 'icon' => '', 'color' => '#000000', 'apps' => 'crm', 'fields' => TaskTypes::fieldDefs([['key' => 'site', 'kind' => 'url'], ['key' => 'mail', 'kind' => 'email']]), 'sort' => 1, 'active' => 1, 'builtin' => 0];
ok(TaskTypes::clean($custom, ['site' => 'https://a.b', 'mail' => 'bad']) === ['site' => 'https://a.b'] && TaskTypes::label($custom, 'ru') === 'Визит' && TaskTypes::label($custom, 'en') === 'Visit', 'types: a type made in the editor validates its own fields and has two names');
ok(TaskTypes::fieldsFromPost(['f_key' => ['a', '', 'b'], 'f_kind' => ['phone', 'text', 'email'], 'f_label' => ['A', 'x', 'B'], 'f_label_ru' => ['А', '', '']]) === [['key' => 'a', 'kind' => 'phone', 'label' => 'A', 'label_ru' => 'А'], ['key' => 'b', 'kind' => 'email', 'label' => 'B', 'label_ru' => '']], 'types: editor form → field list (empty rows skipped)');
ok(TaskTypes::get($T, 'deleted_type')['code'] === 'note', 'types: a task of a deleted type still shows (as a note)');

/* attachments, maps, activity text */
ok(Mission::file('photo.JPG', 1000)[1] === 'jpg' && Mission::file('x.exe', 10) === null && Mission::file('a.pdf', Mission::MAX_FILE + 1) === null && Mission::file('../../etc/p.png', 5)[0] === 'p.png', 'files: type and size are checked, the path is dropped');
ok(str_contains(Mission::mapUrl('Tverskaya 1', 'ru'), 'yandex.ru/maps') && str_contains(Mission::mapUrl('A & B', 'en'), 'openstreetmap.org') && str_contains(Mission::mapUrl('A & B', 'en'), 'A%20%26%20B'), 'maps: Yandex for Russian, OpenStreetMap otherwise, encoded');
[$at, $ab] = Mission::announce(str_repeat('word ', 30), str_repeat('t ', 80), 'Done:');
ok(count(explode(' ', $at)) === 20 && count(explode(' ', $ab)) === 50, 'activity: title ≤ 20 words, text ≤ 50 words');
$tasks = [
    ['id' => 1, 'parent_id' => null, 'branch' => null, 'type' => 'call'],
    ['id' => 2, 'parent_id' => 1, 'branch' => 'bad', 'type' => 'note'],
    ['id' => 3, 'parent_id' => 1, 'branch' => 'good', 'type' => 'meeting'],
    ['id' => 4, 'parent_id' => 3, 'branch' => 'good', 'type' => 'finish_mission'],
];
ok(Mission::luckyWay($tasks) === [1, 3, 4], 'lucky way: first task → good results → Finish Mission');
ok(Mission::luckyWay(array_slice($tasks, 0, 3)) === [], 'lucky way: none without a Finish Mission');
$loop = [['id' => 1, 'parent_id' => null, 'branch' => null, 'type' => 'note'], ['id' => 2, 'parent_id' => 1, 'branch' => 'good', 'type' => 'note'], ['id' => 3, 'parent_id' => 2, 'branch' => 'good', 'type' => 'note']];
ok(Mission::luckyWay($loop) === [], 'lucky way: a chain without a finish ends');
$t = Mission::tree($tasks);
ok(count($t[0]['']) === 1 && count($t[1]['good']) === 1 && count($t[1]['bad']) === 1, 'tree: children grouped by parent and result');

echo $fail ? "\n$fail FAILED\n" : "\nAll good\n";
exit($fail ? 1 : 0);
