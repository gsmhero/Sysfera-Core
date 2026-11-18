<?php
declare(strict_types=1);
/** Renders every Missions view with sample data; any PHP warning/notice or missing translation fails the test. No database needed. Run: php tests/missionsviews.php */
if (!function_exists('mb_substr')) { function mb_substr(string $s, int $a = 0, ?int $l = null): string { return $l === null ? substr($s, $a) : substr($s, $a, $l); } }
if (!function_exists('mb_strtoupper')) { function mb_strtoupper(string $s): string { return strtoupper($s); } }
if (!function_exists('e')) { function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); } }
if (!function_exists('csrf_field')) { function csrf_field(): string { return '<input type="hidden" name="_t" value="x">'; } }
if (!function_exists('url')) { function url(string $p = '/'): string { return '/' . ltrim($p, '/'); } }
if (!function_exists('t')) { function t(string $k, array $v = []): string { return $k; } }
$root = dirname(__DIR__);
require $root . '/core/app/TaskTypes.php'; require $root . '/core/app/I18n.php'; require $root . '/core/plugins/missions/lib/Mission.php';
set_error_handler(function (int $no, string $msg, string $file, int $line): bool { throw new ErrorException("$msg in " . basename($file) . ":$line"); });
$lang = [];
foreach (['en', 'ru'] as $l) $lang[$l] = require $root . "/core/plugins/missions/lang/$l.php";

final class FakeAuth { public function id(): int { return 1; } public function isAdmin(): bool { return true; } }
final class FakeDb { public function val(string $q, array $a = []) { return 2; } }
final class FakeSf {
    public function __construct(public string $l) {}
    public function t(string $k, array $v = []): string { $s = $GLOBALS['lang'][$this->l][$k] ?? throw new RuntimeException("missing key $k ({$this->l})"); foreach ($v as $a => $b) $s = str_replace('{' . $a . '}', (string)$b, $s); return $s; }
    public function url(string $p = ''): string { return '/p/missions/' . ltrim($p, '/'); }
    public function asset(string $f): string { return '/assets/p/missions/' . $f; }
    public function auth(): FakeAuth { return new FakeAuth(); }
    public function db(): FakeDb { return new FakeDb(); }
    public function can(string $c): bool { return true; }
}
$fail = 0;
function ok(bool $c, string $n): void { global $fail; echo ($c ? '  ok   ' : '  FAIL ') . $n . "\n"; if (!$c) $fail++; }
function render(string $l, string $view, array $vars): string {
    global $root; \Sysfera\I18n::setLang($l); $sf = new FakeSf($l); extract($vars, EXTR_SKIP);
    ob_start(); try { require "$root/core/plugins/missions/views/$view.php"; } catch (Throwable $e) { ob_end_clean(); throw $e; }
    return (string)ob_get_clean();
}
$T = \Sysfera\TaskTypes::all(null, null, true); $O = \Sysfera\TaskTypes::all(null, 'missions');
$m = ['id' => 1, 'user_id' => 1, 'title' => 'Win <b>client</b>', 'type' => 'sales', 'category_id' => null, 'description' => 'Text', 'due_date' => null, 'status' => 'active', 'shared' => 0, 'lucky_way' => '[1,2]', 'lang' => '', 'announce' => 0, 'hide_files' => 0];
$task = fn(int $id, ?int $p, ?string $br, string $type, array $d = [], array $x = []) => $x + ['id' => $id, 'mission_id' => 1, 'user_id' => 1, 'parent_id' => $p, 'branch' => $br, 'title' => "Task $id", 'type' => $type, 'cost' => '10.00', 'description' => 'd',
    'result_good' => 'ok', 'result_alt' => '', 'result_bad' => 'no', 'type_data' => $d ? json_encode($d) : null, 'scheduled_at' => '2030-01-01 10:00:00', 'reminder' => 1, 'completed' => 0];
$tasks = [$task(1, null, null, 'call', ['contact_id' => '5', 'phone' => '+7 900 1']), $task(2, 1, 'good', 'meeting', ['place' => 'Tverskaya 1', 'link' => 'https://x.y']), $task(3, 2, 'good', 'finish_mission'),
    $task(4, 1, 'bad', 'buy_product', ['url' => 'https://basebase.ru/product/7']), $task(5, 1, 'alt', 'Email', ['to' => 'a@b.co', 'subject' => 'S'], ['completed' => 1]), $task(6, 5, 'good', 'go_to_task', ['target' => '1']), $task(7, 5, 'bad', 'old_removed_type')];
$files = [1 => [['id' => 9, 'task_id' => 1, 'name' => 'a.png', 'ext' => 'png', 'size' => 2048], ['id' => 10, 'task_id' => 1, 'name' => 'b.pdf', 'ext' => 'pdf', 'size' => 4096]]];
$base = ['m' => $m, 'tasks' => $tasks, 'tree' => \SysferaMissions\Mission::tree($tasks), 'lucky' => [1, 2, 3], 'own' => true, 'author' => null, 'cats' => [['id' => 1, 'name' => 'Sales', 'name_ru' => 'Продажи', 'color' => '#111111']], 'edit' => 0, 'add' => '',
    'types' => $T, 'offered' => $O, 'files' => $files, 'teams' => [['id' => 3, 'name' => 'Sales team']], 'sharedTeams' => [3], 'crm' => true, 'contactName' => [5 => 'Ann <i>'], 'shopLinks' => [['url' => 'https://basebase.ru/product/7', 'title' => 'Lamp']],
    'starred' => true, 'announceOn' => true, 'langs' => ['en' => 'English', 'ru' => 'Русский'], 'setup' => ['yandex_maps_key' => 'k-1', 'mapbox_key' => '', 'tactify_url' => '']];
foreach (['en', 'ru'] as $l) {
    $_GET = ['view' => 'tree']; $h = render($l, 'mission', $base);
    ok(str_contains($h, 'Win &lt;b&gt;client') && !str_contains($h, '<b>client</b>'), "$l: mission page, title escaped");
    $_GET = []; $hl = render($l, 'mission', $base);
    ok(str_contains($hl, 'class="tcard') && str_contains($hl, '?t=') && str_contains($hl, '?view=tree') && !str_contains($hl, 'class="mpath"'), "$l: mission page lists the first tasks as cards");
    $_GET = ['t' => (string)$tasks[0]['id']]; $hf = render($l, 'mission', $base);
    ok(str_contains($hf, 'class="rpath"') && str_contains($hf, 'class="crumbs"'), "$l: task page shows the result path cards");
    $_GET = ['t' => (string)$tasks[0]['id']]; $hs = render($l, 'mission', ['add' => $tasks[0]['id'] . ':good'] + $base);
    ok(strpos($hs, 'id="taskform"') > strpos($hs, 'class="rpath"') && strpos($hs, 'id="taskform"') < strpos($hs, '‹ '), "$l: sub-task form is under the previous task");
    $_GET = ['view' => 'tree'];
    $_GET = ['view' => 'tree'];
    ok(str_contains($h, 'openstreetmap') === ($l === 'en') && str_contains($h, 'yandex.ru/maps/?text') === ($l === 'ru') && str_contains($h, 'class="tmap"') && str_contains($h, 'api-maps.yandex.ru'), "$l: map block with a link fallback, Yandex loaded when a key is set");
    ok(str_contains($h, '/product/7') && str_contains($h, 'Ann &lt;i&gt;') && str_contains($h, '/file/9') && str_contains($h, 'Sales team'), "$l: shop link, CRM contact, attachment and team are shown");
    ok(str_contains(render($l, 'mission', ['edit' => 1] + $base), 'name="td[contact_id]"') && str_contains(render($l, 'mission', ['add' => '1:good'] + $base), 'id="taskform"'), "$l: task form with the contact picker");
    $h = render($l, 'mission', ['own' => false, 'author' => 'Bob', 'teams' => [], 'sharedTeams' => [], 'shopLinks' => [], 'announceOn' => false] + $base);
    ok(!str_contains($h, 'task_delete') && !str_contains($h, 'file_add'), "$l: a shared mission is read-only");
    $h = render($l, 'index', ['rows' => [$m + ['cat' => null, 'cat_ru' => null, 'color' => null, 'n' => 3, 'done' => 1, 'total' => 50, 'starred' => 1]], 'withMe' => [['id' => 5, 'title' => 'Team one', 'status' => 'active', 'author' => 'Bob', 'starred' => 0]], 'cats' => [], 'st' => '', 'q' => '', 'cat' => 0, 'bm' => true, 'langs' => ['en' => 'English'], 'cronUrl' => 'https://x/api']);
    ok(str_contains($h, 'Team one') && str_contains($h, 'fill="currentColor"'), "$l: list with bookmarks and missions shared with my teams");
    $h = render($l, 'notes', ['rows' => [['id' => 1, 'kind' => 'reminder', 'body' => 'Rem <x>', 'mission_id' => 1, 'read_at' => null, 'created_at' => '2030-01-01 10:00:00'], ['id' => 2, 'kind' => 'team', 'body' => 'Done', 'mission_id' => null, 'read_at' => '2030-01-01 11:00:00', 'created_at' => '2030-01-01 10:00:00']]]);
    ok(str_contains($h, 'Rem &lt;x&gt;'), "$l: notification list");
    $h = render($l, 'setup', ['hub' => false, 'own' => ['yandex_maps_key' => '', 'mapbox_key' => '', 'tactify_url' => ''], 'now' => ['yandex_maps_key' => 'abcdefghij', 'mapbox_key' => '', 'tactify_url' => 'https://www.tactify.ru', '_from_hub' => 1, '_age' => 600], 'keys' => ['yandex_maps_key', 'mapbox_key', 'tactify_url']]);
    ok(str_contains($h, 'abcd…hij') && !str_contains($h, 'abcdefghij'), "$l: setup page masks the keys");
    $h = render($l, 'setup', ['hub' => true, 'own' => ['yandex_maps_key' => 'K', 'mapbox_key' => '', 'tactify_url' => ''], 'now' => [], 'keys' => ['yandex_maps_key', 'mapbox_key', 'tactify_url']]);
    ok(str_contains($h, 'name="yandex_maps_key"'), "$l: setup page on the hub");
    $h = render($l, 'library', ['rows' => [], 'cats' => [], 'q' => '', 'cat' => 0]);
    ok($h !== '', "$l: library");
    $nrow = ['id' => 4, 'source' => 'site:shop.example.ru', 'title' => 'Net <b>one</b>', 'type' => 'Sales', 'category' => 'Sales', 'category_ru' => 'Продажи', 'description' => 'D', 'author' => 'Ann', 'tasks_n' => 3, 'updated_at' => '2030-01-01 10:00:00'];
    $h = render($l, 'library', ['rows' => [], 'cats' => [], 'q' => '', 'cat' => 0, 'src' => 'net', 'net' => ['rows' => [$nrow, ['source' => 'tactify'] + $nrow], 'total' => 60, 'err' => null, 'page' => 1]]);
    ok(str_contains($h, '/library/net/4') && str_contains($h, 'Net &lt;b&gt;one') && str_contains($h, 'tactify.ru') && str_contains($h, 'shop.example.ru') && str_contains($h, 'page=3'), "$l: network library list with sources and pages");
    ok(render($l, "library", ["rows" => [], "cats" => [], "q" => "", "cat" => 0, "src" => "net", "net" => ["rows" => [], "total" => 0, "err" => "not_connected", "page" => 1]]) !== "", "$l: not connected message renders");
    $h = render($l, 'netmission', ['m' => $nrow + ['content' => ['tasks' => [['title' => 'T <i>1</i>', 'type' => 'call', 'parent' => null, 'branch' => null], ['title' => 'T2', 'type' => 'zzz', 'parent' => 0, 'branch' => 'bad']]]], 'types' => $T, 'id' => 4]);
    ok(str_contains($h, 'T &lt;i&gt;1') && str_contains($h, 'name="do" value="report"') && str_contains($h, 'name="do" value="import"') && str_contains($h, '<svg'), "$l: network mission preview, import and report");
    $h = render($l, 'setup', ['hub' => true, 'own' => ['yandex_maps_key' => '', 'mapbox_key' => '', 'tactify_url' => ''], 'now' => [], 'keys' => ['yandex_maps_key', 'mapbox_key', 'tactify_url'],
        'net' => ['has' => true, 'status' => ['at' => '2030-01-01 10:00:00', 'pulled' => 2, 'pushed' => 1, 'removed' => 0, 'error' => null], 'rows' => [['id' => 4, 'source' => 'tactify', 'title' => 'X <b>y</b>', 'author' => 'Bob', 'hidden' => 1, 'reports' => 2, 'updated_at' => 'x']], 'total' => 1]]);
    ok(str_contains($h, 'name="tactify_secret"') && str_contains($h, 'value="net_sync"') && str_contains($h, 'X &lt;b&gt;y') && str_contains($h, 'value="net_delete"'), "$l: hub setup shows the Tactify key, sync and moderation");
}
ok(!array_diff_key($lang['en'], $lang['ru']) && !array_diff_key($lang['ru'], $lang['en']), 'every translation key exists in en and ru');
echo $fail ? "\n$fail FAILED\n" : "\nAll good\n";
exit($fail ? 1 : 0);
