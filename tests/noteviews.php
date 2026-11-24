<?php
declare(strict_types=1);
/** Renders the Notes page (theme field, view switch, grouping by theme) with sample data; a warning or a missing translation fails. No database needed. Run: php tests/noteviews.php */
if (!function_exists('mb_substr')) { function mb_substr(string $s, int $a = 0, ?int $l = null): string { return $l === null ? substr($s, $a) : substr($s, $a, $l); } }
if (!function_exists('mb_strtolower')) { function mb_strtolower(string $s): string { return strtolower($s); } }
if (!function_exists('e')) { function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); } }
if (!function_exists('csrf_field')) { function csrf_field(): string { return '<input type="hidden" name="_t" value="x">'; } }
if (!function_exists('t')) { function t(string $k, array $v = []): string { return $k; } }
$dir = dirname(__DIR__) . '/core/plugins/hello';
$en = require "$dir/lang/en.php"; $ru = require "$dir/lang/ru.php";
set_error_handler(function (int $no, string $msg, string $file, int $line): bool { throw new ErrorException("$msg in " . basename($file) . ":$line"); });
$fail = 0; function ok(bool $c, string $n): void { global $fail; echo ($c ? '  ok   ' : '  FAIL ') . $n . "\n"; if (!$c) $fail++; }
final class FakeSf {
    public array $manifest = ['version' => '1.2.0']; public array $used = [];
    public function t(string $k, array $v = []): string { global $en; $this->used[$k] = 1; $s = $en[$k] ?? throw new RuntimeException("missing key $k"); foreach ($v as $a => $b) $s = str_replace('{' . $a . '}', (string)$b, $s); return $s; }
    public function url(string $p = ''): string { return '/p/hello/' . ltrim($p, '/'); }
}
function page(array $vars): string { $sf = new FakeSf(); extract($vars, EXTR_SKIP); ob_start(); try { require dirname(__DIR__) . '/core/plugins/hello/views/index.php'; } catch (Throwable $e) { ob_end_clean(); throw $e; } return (string)ob_get_clean(); }
$n = fn(int $id, string $title, string $theme, string $tags = '', string $body = '') => ['id' => $id, 'title' => $title, 'body' => $body, 'tags' => $tags, 'theme' => $theme, 'from_user_id' => null, 'author' => null, 'created_at' => '2026-11-24 11:35:00', 'updated_at' => null];
$base = ['q' => '', 'tag' => '', 'sort' => 'new', 'allTags' => ['work' => 2, 'ideas' => 1], 'themes' => ['Work', 'Zebra', 'Азбука'], 'uid' => 7, 'edit' => null, 'users' => [['id' => 2, 'name' => 'Ann']], 'myEmail' => 'me@x.ru'];
$notes = [$n(1, 'Plain', ''), $n(2, 'Zed <b>', 'Zebra', 'work'), $n(3, 'Alpha', 'work', 'work,ideas', 'body text'), $n(4, 'Beta', 'Work'), $n(5, 'Яблоко', 'Азбука'), $n(6, 'Second none', '')];

$h = page(array_replace($base, ['notes' => $notes]));
ok(substr_count($h, 'class="nt-group"') === 4, 'one collapsible group per theme (work/Work are one group)');
preg_match_all('/<span class="nt-gname[^"]*">([^<]*)<\/span><small class="nt-gcount">(\d+)/', $h, $m);
ok($m[1] === ['work', 'Zebra', 'Азбука', 'No theme'], 'themes alphabetical, "No theme" last');
ok($m[1][0] === 'work' && $m[2][0] === '2' && end($m[2]) === '2', 'note counts per group, first-seen spelling kept');
ok(strpos($h, 'Alpha') < strpos($h, 'Beta') && strpos($h, 'Яблоко') > strpos($h, 'Zed'), 'notes keep the incoming (sorted) order inside a group');
ok(str_contains($h, 'name="theme"') && str_contains($h, 'list="nt-themes"') && substr_count($h, '<option value="') >= 5, 'theme field with autocomplete');
ok(str_contains($h, 'data-view="card"') && str_contains($h, 'data-view="list"') && str_contains($h, 'sysfera.notes.view.7') && str_contains($h, 'try{'), 'view switch, remembered per user inside try/catch');
ok(str_contains($h, '#work <small>2</small>') && str_contains($h, 'Add note') && str_contains($h, 'placeholder="work, ideas, personal"'), 'tag chips with counts, New note card');
ok(!str_contains($h, 'Zed <b>') && str_contains($h, 'Zed &lt;b&gt;'), 'titles are escaped');
$h = page(array_replace($base, ['notes' => [], 'q' => 'x']));
ok(str_contains($h, 'No notes found.') && !str_contains($h, 'nt-group"'), 'empty search result');
$h = page(array_replace($base, ['notes' => $notes, 'edit' => $n(3, 'Alpha', 'work', 'work')]));
ok(str_contains($h, 'value="work"') && str_contains($h, 'Edit note') && str_contains($h, 'name="id" value="3"'), 'edit form carries the theme');

/* translations */
ok(array_keys($en) === array_keys($ru) || !array_diff(array_keys($en), array_keys($ru)), 'en and ru have the same keys');
$src = file_get_contents("$dir/views/index.php") . file_get_contents("$dir/plugin.php"); preg_match_all('/\$sf->t\(\'([a-z_0-9]+)\'/', $src, $mm);
$miss = array_diff(array_filter(array_unique($mm[1]), fn($k) => !str_ends_with($k, '_')), array_keys($en)); ok(!$miss, 'every key used exists in en.php' . ($miss ? ' (' . implode(', ', $miss) . ')' : ''));
foreach (['f_theme', 'no_theme', 'edit_note', 'view_card', 'view_list'] as $k) ok(isset($ru[$k]) && $ru[$k] !== $en[$k], "ru translation: $k");
ok(is_file("$dir/migrations/003_notes_theme.sql") && str_contains((string)file_get_contents("$dir/migrations/003_notes_theme.sql"), '{p}hello_notes ADD COLUMN theme'), 'additive migration adds the theme column');
ok(json_decode((string)file_get_contents("$dir/plugin.json"), true)['version'] === '1.2.1', 'plugin.json is 1.2.1');
echo $fail ? "\n$fail FAILED\n" : "\nAll good\n"; exit($fail ? 1 : 0);
