<?php
declare(strict_types=1);
/** Renders every Teams view for every role with sample data; any PHP warning/notice fails the test. No database needed. Run: php tests/teamsviews.php */
if (!function_exists('mb_substr')) { function mb_substr(string $s, int $start, ?int $len = null): string { return $len === null ? substr($s, $start) : substr($s, $start, $len); } }
if (!function_exists('mb_strtoupper')) { function mb_strtoupper(string $s): string { return strtoupper($s); } }
if (!function_exists('e')) { function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); } }
if (!function_exists('csrf_field')) { function csrf_field(): string { return '<input type="hidden" name="_t" value="x">'; } }
if (!function_exists('csrf_token')) { function csrf_token(): string { return 'x'; } }
if (!function_exists('human_size')) { function human_size(int $b): string { return $b . ' B'; } }
if (!class_exists('Sysfera\Demo')) eval('namespace Sysfera; final class Demo { public static function on(): bool { return false; } }');
$dir = dirname(__DIR__) . '/core/plugins/teams';
require $dir . '/lib/Tm.php';
$en = require $dir . '/lang/en.php';
set_error_handler(function (int $no, string $msg, string $file, int $line): bool { throw new ErrorException("$msg in " . basename($file) . ":$line"); });

final class FakeAuth { public function id(): int { return 1; } public function isAdmin(): bool { return true; } }
final class FakeSf {
    public array $manifest = ['version' => '0.1.0'];
    public function t(string $k, array $v = []): string { global $en; $s = $en[$k] ?? throw new RuntimeException("missing key $k"); foreach ($v as $a => $b) $s = str_replace('{' . $a . '}', (string)$b, $s); return $s; }
    public function url(string $p = ''): string { return '/p/teams/' . ltrim($p, '/'); }
    public function asset(string $f): string { return '/assets/p/teams/' . $f; }
    public function auth(): FakeAuth { return new FakeAuth(); }
    public function can(string $c): bool { return true; }
}
$fail = 0;
function ok(bool $c, string $n): void { global $fail; echo ($c ? '  ok   ' : '  FAIL ') . $n . "\n"; if (!$c) $fail++; }
function render(string $view, array $vars): string {
    $sf = new FakeSf(); extract($vars, EXTR_SKIP);
    ob_start(); try { require dirname(__DIR__) . "/core/plugins/teams/views/$view.php"; } catch (Throwable $e) { ob_end_clean(); throw $e; }
    return (string)ob_get_clean();
}

$people = [['id' => 1, 'name' => 'Ann', 'email' => 'a@x.y'], ['id' => 2, 'name' => 'Bob <b>', 'email' => 'b@x.y']];
$chans = [['id' => 10, 'name' => 'general', 'topic' => 'All', 'is_private' => 0, 'unread' => 3, 'mentions' => 1], ['id' => 11, 'name' => 'secret', 'topic' => '', 'is_private' => 1, 'unread' => 2, 'mentions' => 0]];
$task = ['id' => 5, 'title' => 'Fix <it>', 'descr' => 'd', 'col_id' => 1, 'assignee_id' => 2, 'aname' => 'Bob <b>', 'due' => '2026-11-01', 'prio' => 2, 'done_at' => null, 'sort' => 10, 'created_by' => 1];
$cols = [['id' => 1, 'name' => 'To do', 'is_done' => 0, 'sort' => 10], ['id' => 2, 'name' => 'Done', 'is_done' => 1, 'sort' => 20]];
$event = ['id' => 3, 'title' => 'Demo <day>', 'descr' => 'x', 'starts' => '2026-11-10 09:00:00', 'ends' => '2026-11-12 18:00:00', 'created_by' => 1];
$now = strtotime('2026-11-13 12:00:00'); $month = \SysferaTeams\Tm::month('2026-11', $now);
$msgs = [['id' => 1, 'user_id' => 2, 'uname' => 'Bob <b>', 'body' => 'hi <script>', 'created_at' => '2026-11-02 10:00:00', 'edited_at' => null, 'deleted' => 0, 'pinned' => 0, 'reply_to' => null, 'reactions' => [], 'file' => null]];
$cases = [];
foreach (['owner', 'admin', 'member', null] as $role) {
    $team = ['id' => 2, 'name' => 'Team <A>', 'descr' => 'About', 'privacy' => 'private', 'color' => '#4f46e5', 'role' => $role];
    $label = $role ?? 'guest';
    $cases["$label/index"] = ['index', ['mine' => [['id' => 2, 'name' => 'T', 'descr' => '', 'privacy' => 'private', 'color' => '#111111', 'n' => 3, 'role' => $role ?? 'member', 'unread' => 4]], 'open' => [['id' => 3, 'name' => 'Open', 'descr' => null, 'color' => '#222222', 'n' => 1]], 'canCreate' => true]];
    $cases["$label/preview"] = ['preview', ['t' => $team, 'n' => 5]];
    if ($role === null) continue;
    $cases["$label/empty"] = ['empty', ['t' => $team]];
    $cases["$label/channel"] = ['channel', ['t' => $team, 'c' => $chans[0] + ['id' => 10], 'chans' => $chans, 'msgs' => $msgs, 'pins' => [], 'lastRead' => 0, 'people' => $people, 'me' => 1, 'emoji' => \SysferaTeams\Tm::EMOJI, 'maxFile' => \SysferaTeams\Tm::MAX_FILE, 'chMembers' => [1, 2]]];
    $cases["$label/board"] = ['board', ['t' => $team, 'cols' => $cols, 'by' => [1 => [$task], 2 => []], 'people' => $people, 'f' => ['q' => 'x', 'mine' => true], 'edit' => null, 'today' => '2026-11-13', 'chans' => []]];
    $cases["$label/board-edit"] = ['board', ['t' => $team, 'cols' => $cols, 'by' => [1 => [$task]], 'people' => $people, 'f' => ['q' => '', 'mine' => false], 'edit' => $task, 'today' => '2026-11-13', 'chans' => []]];
    $cases["$label/calendar"] = ['calendar', ['t' => $team, 'm' => $month, 'by' => \SysferaTeams\Tm::byDay([$event]), 'upcoming' => [$event], 'edit' => null, 'chans' => []]];
    $cases["$label/calendar-edit"] = ['calendar', ['t' => $team, 'm' => $month, 'by' => [], 'upcoming' => [], 'edit' => $event, 'chans' => []]];
    $cases["$label/files"] = ['files', ['t' => $team, 'files' => [['id' => 9, 'name' => 'a<b>.pdf', 'size' => 2048, 'user_id' => 2, 'uname' => 'Bob', 'cname' => 'general', 'created_at' => '2026-11-02 10:00:00'], ['id' => 8, 'name' => 'b.txt', 'size' => 1, 'user_id' => 1, 'uname' => null, 'cname' => null, 'created_at' => '2026-11-02 10:00:00']], 'maxFile' => \SysferaTeams\Tm::MAX_FILE, 'chans' => []]];
    $cases["$label/members"] = ['members', ['t' => $team, 'mem' => [['id' => 1, 'name' => 'Ann', 'email' => 'a@x.y', 'role' => 'owner'], ['id' => 2, 'name' => 'Bob <b>', 'email' => 'b@x.y', 'role' => 'member'], ['id' => 4, 'name' => 'Cy', 'email' => 'c@x.y', 'role' => 'admin']], 'others' => [['id' => 7, 'name' => 'Zed', 'email' => 'z@x.y']], 'chans' => []]];
}
$out = [];
foreach ($cases as $name => [$view, $vars]) {
    try { $out[$name] = render($view, $vars); ok(strlen($out[$name]) > 100, "$name renders"); }
    catch (Throwable $e) { ok(false, "$name — " . $e->getMessage()); }
}
if (isset($out['owner/board'])) {
    ok(!str_contains($out['owner/board'], 'Fix <it>') && str_contains($out['owner/board'], 'Fix &lt;it&gt;'), 'task titles are escaped');
    ok(str_contains($out['owner/board'], 'class="tm-task prio2"') && str_contains($out['owner/board'], 'class="late"'), 'high priority and overdue are marked');
    ok(str_contains($out['owner/board'], '/col/save') && !str_contains($out['member/board'] ?? '', '/col/save'), 'only admins manage columns');
    ok(str_contains($out['owner/board-edit'] ?? '', 'value="Fix &lt;it&gt;"'), 'edit form is prefilled');
}
if (isset($out['owner/channel'])) {
    preg_match('#<script type="application/json" id="tmcfg">(.*?)</script>#s', $out['owner/channel'], $m);
    $cfg = json_decode($m[1] ?? '', true);
    ok(is_array($cfg) && ($cfg['msgs'][0]['body'] ?? '') === 'hi <script>', 'chat config is valid JSON and keeps messages as data');
    ok(!str_contains($m[1] ?? '', '<script') && !str_contains($out['owner/channel'], 'hi <script>'), 'chat config cannot break out of its script tag');
    ok(($cfg['canPin'] ?? null) === true && (json_decode(preg_match('#id="tmcfg">(.*?)</script>#s', $out['member/channel'] ?? '', $mm) ? $mm[1] : '', true)['canPin'] ?? null) === false, 'only moderators can pin');
}
ok(!str_contains($out['member/members'] ?? '', '/members/role') && str_contains($out['owner/members'] ?? '', '/members/role'), 'only owners change roles');
ok(!str_contains($out['member/members'] ?? '', '/members/remove') && str_contains($out['admin/members'] ?? '', '/members/remove'), 'members cannot remove people, admins can');
ok(!str_contains($out['guest/preview'] ?? 'x', 'tm-side') , 'preview has no channel sidebar');
ok(!str_contains($out['member/calendar'] ?? '', 'Demo &lt;day&gt;') === false, 'calendar shows events');

echo $fail ? "\n$fail FAILED\n" : "\nAll good\n";
exit($fail ? 1 : 0);
