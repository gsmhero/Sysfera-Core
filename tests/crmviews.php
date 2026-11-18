<?php
declare(strict_types=1);
/** Renders every CRM view with sample data; any PHP warning/notice fails the test. No database needed. Run: php tests/crmviews.php */
if (!function_exists('mb_substr')) { function mb_substr(string $s, int $start, ?int $len = null): string { return $len === null ? substr($s, $start) : substr($s, $start, $len); } }
if (!function_exists('mb_strtoupper')) { function mb_strtoupper(string $s): string { return strtoupper($s); } }
if (!function_exists('mb_strimwidth')) { function mb_strimwidth(string $s, int $a, int $w, string $t = ''): string { return strlen($s) > $w ? substr($s, 0, $w - strlen($t)) . $t : $s; } }
if (!function_exists('e')) { function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); } }
if (!function_exists('csrf_field')) { function csrf_field(): string { return '<input type="hidden" name="_t" value="x">'; } }
if (!function_exists('csrf_token')) { function csrf_token(): string { return 'x'; } }
if (!function_exists('url')) { function url(string $p = '/'): string { return '/' . ltrim($p, '/'); } }

$dir = dirname(__DIR__) . '/core/plugins/crm';
if (!function_exists('t')) { function t(string $k, array $v = []): string { return $k; } }
require dirname(__DIR__) . '/core/app/TaskTypes.php'; require dirname(__DIR__) . '/core/app/I18n.php'; require dirname(__DIR__) . '/core/app/Hooks.php'; require dirname(__DIR__) . '/core/app/Attach.php';
require $dir . '/lib/Fields.php'; require $dir . '/lib/Pipe.php'; require $dir . '/lib/Discuss.php';
if (!function_exists('human_size')) { function human_size(int $b): string { return $b . ' B'; } }
$en = require $dir . '/lang/en.php';

set_error_handler(function (int $no, string $msg, string $file, int $line): bool { throw new ErrorException("$msg in " . basename($file) . ":$line"); });

final class FakeAuth { public function id(): int { return 1; } public function isAdmin(): bool { return true; } }
final class FakeSf {
    public array $manifest = ['version' => '0.1.0'];
    public function t(string $k, array $v = []): string { global $en; $s = $en[$k] ?? throw new RuntimeException("missing key $k"); foreach ($v as $a => $b) $s = str_replace('{' . $a . '}', (string)$b, $s); return $s; }
    public function url(string $p = ''): string { return '/p/crm/' . ltrim($p, '/'); }
    public function asset(string $f): string { return '/assets/p/crm/' . $f; }
    public function auth(): FakeAuth { return new FakeAuth(); }
    public function can(string $c): bool { return true; }
    public function db() { return null; }   // the task types fall back to the built-in list
    public function setting(string $k, string $d = ''): string { return $d; }
}

$fail = 0;
function ok(bool $c, string $n): void { global $fail; echo ($c ? '  ok   ' : '  FAIL ') . $n . "\n"; if (!$c) $fail++; }
function render(string $view, array $vars): string {
    $sf = new FakeSf(); extract($vars, EXTR_SKIP);
    ob_start(); try { require dirname(__DIR__) . "/core/plugins/crm/views/$view.php"; } catch (Throwable $e) { ob_end_clean(); throw $e; }
    return (string)ob_get_clean();
}

$full = \SysferaCrm\Pipe::profile(['scope' => 'all'] + array_fill_keys(\SysferaCrm\Pipe::PERMS, 1));
$none = \SysferaCrm\Pipe::profile([]);
$users = [['id' => 1, 'name' => 'Ann', 'email' => 'a@x.y', 'role' => 'admin'], ['id' => 2, 'name' => 'Bob <b>', 'email' => 'b@x.y', 'role' => 'user']];
$stages = [['id' => 1, 'pipeline_id' => 1, 'name' => 'New', 'prob' => 10, 'kind' => 'open', 'color' => '#94a3b8', 'sort' => 10, 'n' => 1, 'sum' => 100.0, 'deals' => 1],
           ['id' => 2, 'pipeline_id' => 1, 'name' => 'Lost', 'prob' => 0, 'kind' => 'lost', 'color' => '#dc2626', 'sort' => 20, 'n' => 0, 'sum' => 0.0, 'deals' => 0]];
$pipes = [['id' => 1, 'name' => 'Sales', 'color' => '#4f46e5', 'stages' => $stages, 'deals' => 1]];
$defs = [['id' => 1, 'entity' => 'deal', 'pipeline_id' => null, 'code' => 'src', 'label' => 'Source', 'type' => 'select', 'options' => ['Web', 'Call'], 'required' => false],
         ['id' => 2, 'entity' => 'deal', 'pipeline_id' => 1, 'code' => 'note', 'label' => 'Note', 'type' => 'textarea', 'options' => [], 'required' => true],
         ['id' => 3, 'entity' => 'contact', 'pipeline_id' => null, 'code' => 'tags', 'label' => 'Tags', 'type' => 'multiselect', 'options' => ['a', 'b'], 'required' => false],
         ['id' => 4, 'entity' => 'contact', 'pipeline_id' => null, 'code' => 'vip', 'label' => 'VIP', 'type' => 'checkbox', 'options' => [], 'required' => false]];
$deal = ['id' => 7, 'pipeline_id' => 1, 'stage_id' => 1, 'title' => 'Big <deal>', 'contact_id' => 3, 'amount' => '1500.00', 'currency' => 'RUB', 'owner_id' => 1, 'expected_close' => '2026-12-01', 'lost_reason' => null, 'tags' => 'vip, b2b',
    'extra' => ['src' => 'Web', 'note' => 'hi'], 'closed_at' => null, 'created_at' => '2026-11-01 10:00:00', 'updated_at' => '2026-11-02 10:00:00', 'contact_name' => 'John', 'contact_company' => 'Acme', 'kind' => 'open', 'prob' => 10,
    'stage_name' => 'New', 'stage_color' => '#94a3b8', 'owner_name' => 'Ann', 'open_tasks' => 1, 'next_due' => '2026-11-12 09:00:00'];
$contact = ['id' => 3, 'name' => 'John', 'type' => 'person', 'email' => 'j@x.y', 'phone' => '+7 900', 'company' => 'Acme', 'address' => 'Moscow', 'notes' => 'n', 'custom' => ['crm' => ['tags' => ['a'], 'vip' => true]], 'owner_id' => 1];
$task = ['id' => 5, 'type' => 'call', 'title' => 'Call <John>', 'notes' => 'n', 'due_at' => '2026-11-12 09:00:00', 'done_at' => null, 'assignee_id' => 1, 'assignee_name' => 'Ann', 'deal_id' => 7, 'deal_title' => 'Big', 'contact_id' => 3, 'contact_name' => 'John'];
$tl = [['kind' => 'note', 'body' => "line1\nline2", 'user_name' => 'Ann', 'created_at' => '2026-11-02 10:00:00'], ['kind' => 'stage', 'body' => 'New → Won', 'user_name' => null, 'created_at' => '2026-11-03 10:00:00']];
$sum = \SysferaCrm\Pipe::summary([$deal], [1 => ['kind' => 'open', 'prob' => 10]]);
$now = strtotime('2026-11-13 12:00:00');

foreach (['full' => $full, 'none' => $none] as $label => $prof) {
    $cases = [
        'dashboard' => ['sum' => $sum, 'wonMonth' => 0.0, 'tasks' => [$task], 'feed' => [['kind' => 'note', 'body' => 'x', 'user_name' => 'Ann', 'created_at' => '2026-11-02 10:00:00', 'deal_title' => 'Big', 'deal_id' => 7]], 'funnel' => [['p' => $pipes[0], 'rows' => $stages]], 'recent' => [$deal], 'nowTs' => $now],
        'deals' => ['pipelines' => $pipes, 'pid' => 1, 'stages' => $stages, 'deals' => [$deal], 'board' => [1 => [$deal], 2 => []], 'f' => ['pipeline' => 1, 'q' => '', 'owner' => null, 'tag' => '', 'status' => ''], 'view' => 'board', 'users' => $users, 'sum' => $sum, 'defs' => $defs, 'cur' => 'RUB', 'sortBy' => ''],
        'deals-list' => ['pipelines' => $pipes, 'pid' => 1, 'stages' => $stages, 'deals' => [$deal], 'board' => [], 'f' => ['pipeline' => 1, 'q' => 'x', 'owner' => 1, 'tag' => 'vip', 'status' => 'open'], 'view' => 'list', 'users' => $users, 'sum' => $sum, 'defs' => $defs, 'cur' => 'RUB', 'sortBy' => 'amount'],
        'deal' => ['d' => $deal, 'contact' => $contact, 'stages' => $stages, 'defs' => $defs, 'users' => $users, 'reasons' => ['x'], 'cur' => 'RUB', 'tasks' => [$task], 'timeline' => $tl, 'pipeline' => $pipes[0], 'nowTs' => $now, 'pipelines' => $pipes],
        'contacts' => ['rows' => [$contact + ['deals' => 2]], 'total' => 1, 'pg' => 1, 'pages' => 2, 'f' => ['q' => 'j', 'type' => 'person']],
        'contact' => ['c' => $contact, 'crm' => $contact['custom']['crm'], 'defs' => [$defs[2], $defs[3]], 'deals' => [$deal], 'tasks' => [$task], 'timeline' => $tl, 'nowTs' => $now],
        'taskview' => ['comments' => [['id' => 1, 'task_id' => 5, 'user_id' => 1, 'user_name' => 'Ann <b>', 'body' => "hello <i>x</i>\nline2", 'created_at' => '2026-11-12 10:00:00', 'files' => [['id' => 9, 'original_name' => 'shot.png', 'stored_name' => 'aa.png', 'size' => 2048], ['id' => 10, 'original_name' => 'a <b>.pdf', 'stored_name' => 'bb.pdf', 'size' => 5000000]]], ['id' => 2, 'task_id' => 5, 'user_id' => 2, 'user_name' => null, 'body' => null, 'created_at' => '2026-11-12 11:00:00', 'files' => []]], 'task' => $task, 'curContact' => $contact, 'deal' => ['id' => 7, 'title' => 'Big'], 'assignee' => 'Ann', 'listBack' => '/p/crm/tasks', 'nowTs' => $now],
        'taskedit' => ['task' => $task, 'curContact' => $contact, 'users' => $users, 'listBack' => '/p/crm/tasks', 'backTo' => '/p/crm/task/5?back=%2Fp%2Fcrm%2Ftasks'],
        'tasks' => ['rows' => [$task, ['done_at' => '2026-11-01 10:00:00'] + $task, ['due_at' => null] + $task], 'show' => 'mine', 'users' => $users, 'nowTs' => $now, 'assignee' => 0],
        'reports' => ['pl' => $pipes, 'pid' => 1, 'from' => '2026-01-01', 'to' => '2026-11-13', 'sum' => $sum, 'perStage' => $stages, 'byOwner' => ['Ann' => ['won_n' => 1, 'won_sum' => 5.0, 'lost_n' => 1]], 'reasons' => ['Price' => 2], 'byMonth' => ['2026-10' => 100.0], 'closedSum' => $sum],
        'import' => ['res' => ['ok' => 3, 'skip' => 1], 'pipelines' => $pipes],
        'settings' => ['pl' => $pipes, 'defs' => $defs, 'profs' => [['id' => 1, 'name' => 'Manager', 'p' => $full]], 'assign' => [2 => 1], 'users' => $users, 'reasons' => ['Price'], 'cur' => 'RUB', 'defProf' => 1],
    ];
    foreach ($cases as $name => $vars) {
        if ($label === 'none' && $name === 'settings') continue;
        $view = $name === 'deals-list' ? 'deals' : $name;
        try { $html = render($view, $vars + ['tab' => $view, 'prof' => $prof]); ok(strlen($html) > 200, "$label: $name renders"); }
        catch (Throwable $e) { ok(false, "$label: $name — " . $e->getMessage()); }
    }
}
$h = render('deal', $cases['deal'] + ['tab' => 'deals', 'prof' => $full]);
ok(!str_contains($h, 'Big <deal>') && str_contains($h, 'Big &lt;deal&gt;'), 'titles are escaped');
ok(str_contains($h, 'name="f[src]"') && str_contains($h, 'name="f[note]"') && str_contains($h, 'name="title" value="Big &lt;deal&gt;"'), 'deal form shows custom fields and keeps the deal after them');
$hc = render('contact', $cases['contact'] + ['tab' => 'contacts', 'prof' => $full]);
ok(str_contains($hc, 'name="f[tags][]"') && str_contains($hc, 'name="f[vip]"') && str_contains($hc, 'value="a" checked'), 'contact form shows multiselect and checkbox fields with values');
ok(!str_contains(render('tasks', $cases['tasks'] + ['tab' => 'tasks', 'prof' => $full]), 'Call <John>'), 'task titles are escaped');

$h = render('taskview', ['comments' => [['id' => 1, 'task_id' => 5, 'user_id' => 1, 'user_name' => 'Ann <b>', 'body' => "hi <i>x</i>\nl2", 'created_at' => '2026-11-12 10:00:00', 'files' => [['id' => 9, 'original_name' => 'shot.png', 'stored_name' => 'aa.png', 'size' => 2048], ['id' => 10, 'original_name' => 'a <b>.pdf', 'stored_name' => 'bb.pdf', 'size' => 5000]]]], 'task' => $task, 'curContact' => $contact, 'deal' => null, 'assignee' => 'Ann', 'listBack' => '/p/crm/tasks', 'nowTs' => $now, 'tab' => 'tasks', 'prof' => $full]);
ok(str_contains($h, 'id="discuss"') && str_contains($h, '/p/crm/task/5/comment') && str_contains($h, 'enctype="multipart/form-data"') && str_contains($h, 'name="files[]"') && str_contains($h, '/p/crm/task/5/file/9') && str_contains($h, '<img src="/p/crm/task/5/file/9"'), 'task view: the discussion with the form, the files and an inline image');
ok(!str_contains($h, '<i>x</i>') && str_contains($h, 'Ann &lt;b&gt;') && !str_contains($h, 'a <b>.pdf') && str_contains($h, '/comment/1/delete'), 'task view: comment text, names and file names are escaped; the author can delete');
$h = render('taskview', ['comments' => [], 'task' => $task, 'curContact' => $contact, 'deal' => null, 'assignee' => 'Ann', 'listBack' => '/p/crm/tasks', 'nowTs' => $now, 'tab' => 'tasks', 'prof' => $none]);
ok(str_contains($h, 'No comments yet') && str_contains($h, '/p/crm/task/5/comment'), 'task view: empty discussion still offers the form');
/* missions of the library on a task (the Missions component supplies the hooks) */
require_once dirname(__DIR__) . '/core/app/Attach.php';
\Sysfera\Hooks::on('attach.missions', fn($c) => ['find' => '/p/missions/api/missions-find']);
\Sysfera\Hooks::on('attach.missions.info', fn(array $acc, array $ids) => array_merge($acc, array_map(fn($id) => ['id' => $id, 'label' => 'Cold outreach <b> (Library)', 'url' => '/p/missions/m/' . $id], array_filter($ids, fn($x) => $x === 3))));
$tm = ['tmissions' => '[3,4]'] + $task;
$h = render('taskedit', ['task' => $tm, 'curContact' => $contact, 'users' => $users, 'listBack' => '/p/crm/tasks', 'backTo' => '/p/crm/task/5', 'tab' => 'tasks', 'prof' => $full]);
ok(str_contains($h, 'data-name="mission"') && str_contains($h, '/p/missions/api/missions-find') && str_contains($h, 'name="mission[]" value="3"') && !str_contains($h, 'value="4"') && !str_contains($h, '<b>'), 'task form: a Missions picker, the attached mission as a chip (escaped, only the ones that still exist)');
$h = render('taskview', ['comments' => [], 'task' => $tm, 'curContact' => $contact, 'deal' => null, 'assignee' => 'Ann', 'listBack' => '/p/crm/tasks', 'nowTs' => $now, 'tab' => 'tasks', 'prof' => $full]);
ok(str_contains($h, 'att.missions') && str_contains($h, '/p/missions/m/3') && str_contains($h, 'Cold outreach &lt;b&gt;'), 'task view: the attached mission is listed and links to it');
$_POST['mission'] = ['3', '3', 'x', '0', '7']; ok(\Sysfera\Attach::missionIds() === [3, 7], 'attach: mission ids are cleaned (numbers only, no duplicates)'); unset($_POST['mission']);
$sqlm = (string)file_get_contents(dirname(__DIR__) . '/core/plugins/crm/migrations/005_task_missions.sql'); ok(str_contains($sqlm, 'ADD COLUMN tmissions') && !preg_match('/\b(DROP|DELETE|TRUNCATE)\b/i', $sqlm), 'migration 005: one new column, nothing destructive');
$h = render('taskedit', ['task' => $task, 'curContact' => $contact, 'users' => $users, 'listBack' => '/p/crm/tasks', 'backTo' => '/p/crm/task/5', 'tab' => 'tasks', 'prof' => $full]);
ok(substr_count($h, 'class="cp-q"') >= 1 && str_contains($h, 'type="hidden" name="td[') && !preg_match('~<select[^>]*class="cp-sel"~', $h) && str_contains($h, 'class="att-res cp-res"') && str_contains($h, 'value="John · Acme"'), 'task form: the client is one search field with results while typing (the chosen client is kept in a hidden field)');
$h = render('taskview', ['comments' => [], 'task' => $task, 'curContact' => $contact, 'deal' => ['id' => 7, 'title' => 'Big <deal>'], 'assignee' => 'Ann Lee', 'listBack' => '/p/crm/tasks', 'nowTs' => $now, 'tab' => 'tasks', 'prof' => $full]);
ok(str_contains($h, 'class="crm-th"') && str_contains($h, 'class="crm-facts"') && substr_count($h, 'class="crm-fact') >= 4 && str_contains($h, '>AL<') && str_contains($h, 'badge bad') && str_contains($h, 'Big &lt;deal&gt;'), 'task view: header card, fact tiles (due, assignee with initials, client, deal), overdue badge, escaped');
$h = render('taskview', ['comments' => [], 'task' => ['notes' => '', 'report' => '', 'done_at' => '2026-11-12 10:00:00'] + $task, 'curContact' => null, 'deal' => null, 'assignee' => '', 'listBack' => '/p/crm/tasks', 'nowTs' => $now, 'tab' => 'tasks', 'prof' => $none]);
ok(!str_contains($h, 'crm-tbody') && str_contains($h, 'badge ok') && str_contains($h, '>?<'), 'task view: a task without notes or attachments has no empty body card; done badge; no assignee');
echo $fail ? "\n$fail FAILED\n" : "\nAll good\n";
exit($fail ? 1 : 0);
