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
require $dir . '/lib/Fields.php'; require $dir . '/lib/Pipe.php';
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

echo $fail ? "\n$fail FAILED\n" : "\nAll good\n";
exit($fail ? 1 : 0);
