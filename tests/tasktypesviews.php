<?php
declare(strict_types=1);
/** Renders the Task types admin view with the built-in types; any warning/error fails. No database. Run: php tests/tasktypesviews.php */
if (!function_exists("mb_substr")) { function mb_substr(string $s, int $a = 0, ?int $l = null): string { return $l === null ? substr($s, $a) : substr($s, $a, $l); } }
if (!function_exists("mb_strtoupper")) { function mb_strtoupper(string $s): string { return strtoupper($s); } }
if (!function_exists('e')) { function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); } }
if (!function_exists('csrf_field')) { function csrf_field(): string { return ''; } }
if (!function_exists('t')) { function t(string $k, array $v = []): string { return $k; } }
$root = dirname(__DIR__);
require $root . '/core/app/TaskTypes.php'; require $root . '/core/app/I18n.php';
set_error_handler(function (int $no, string $msg, string $file, int $line): bool { throw new ErrorException("$msg in " . basename($file) . ":$line"); });
$types = \Sysfera\TaskTypes::all(null, null, true); $kinds = \Sysfera\TaskTypes::KINDS; $apps = \Sysfera\TaskTypes::APPS;
$fail = 0;
foreach (['en', 'ru'] as $l) {
    \Sysfera\I18n::setLang($l);
    ob_start(); try { require $root . '/core/views/tasktypes.php'; $h = (string)ob_get_clean(); } catch (Throwable $e) { ob_end_clean(); echo "  FAIL $l: " . $e->getMessage() . "\n"; $fail++; continue; }
    $ok = str_contains($h, 'name="apps[]"') && str_contains($h, 'name="f_key[]"') && str_contains($h, 'name="icon" value="phone"') && str_contains($h, '<svg') && !preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $h);
    echo ($ok ? '  ok   ' : '  FAIL ') . "$l: task types view renders\n"; if (!$ok) $fail++;
}
echo $fail ? "\n$fail FAILED\n" : "\nAll good\n";
exit($fail ? 1 : 0);
