<?php
declare(strict_types=1);
/** Renders the Contact Log page (en and ru, user and administrator, with and without cards); a PHP warning or a missing translation fails. Also: en/ru have the same keys and
 *  placeholders, every key used by the code and the script exists, every error code has a message, the migration creates exactly the tables the store knows, the script talks
 *  only to API operations that plugin.php serves, the manifest is valid, the .gitignore lets the plugin in, no credentials. Run: php tests/contactlogviews.php */
if (!function_exists('e')) { function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); } }
if (!function_exists('csrf_token')) { function csrf_token(): string { return 'tok'; } }
if (!function_exists('url')) { function url(string $p = '/'): string { return '/' . ltrim($p, '/'); } }
$dir = dirname(__DIR__) . '/core/plugins/contactlog';
foreach (['Err', 'Store', 'Report', 'Svc', 'DbStore'] as $c) require "$dir/lib/$c.php";
use SysferaContactlog\{Svc, DbStore};
$fail = 0; function ok(bool $c, string $n): void { global $fail; echo ($c ? '  ok   ' : '  FAIL ') . $n . "\n"; if (!$c) $fail++; }
set_error_handler(function (int $no, string $msg, string $file, int $line): bool { throw new ErrorException("$msg in " . basename($file) . ":$line"); });
$en = require "$dir/lang/en.php"; $ru = require "$dir/lang/ru.php";

final class FakeSf {
    public array $manifest = ['version' => '0.1.0']; public array $missing = [];
    public function __construct(private array $l) {}
    public function t(string $k, array $v = []): string { if (!isset($this->l[$k])) { $this->missing[] = $k; return 'contactlog.' . $k; } $s = $this->l[$k]; foreach ($v as $a => $b) $s = str_replace('{' . $a . '}', (string)$b, $s); return $s; }
    public function url(string $p = ''): string { return '/p/contactlog/' . ltrim($p, '/'); }
    public function asset(string $f): string { return '/assets/p/contactlog/' . $f; }
}
$render = function (FakeSf $sf, array $vars) use ($dir) { extract($vars); ob_start(); try { require "$dir/views/index.php"; } catch (Throwable $e) { ob_end_clean(); throw $e; } return (string)ob_get_clean(); };
$card = ['id' => 1, 'name' => 'Anna </script><b>', 'second_name' => '', 'surname' => 'S', 'mobile' => '1', 'messenger' => '@a', 'email' => 'a@b.co', 'status' => 'busy', 'theme' => 'T', 'notes' => 'n', 'next_call' => '2026-12-31', 'created_at' => 1800000000, 'logs' => [['id' => 1, 'action' => 'call', 'at' => 1800000001]]];

$n = 0;
foreach (['en' => $en, 'ru' => $ru] as $lang => $L) foreach ([['admin', true], ['user', false]] as [$who, $adm]) foreach ([[], [$card]] as $cards) {
    $sf = new FakeSf($L);
    try {
        $html = $render($sf, ['sf' => $sf, 'lang' => $lang, 'L' => $L, 'cards' => $cards, 'copyEmail' => 'c@d.co', 'ownEmail' => 'o@p.co', 'userName' => 'Olga', 'isAdmin' => $adm, 'api' => '/p/contactlog/api/']); $n++;
        ok(strlen($html) > 500 && !$sf->missing && !preg_match('~(?<![/\w])contactlog\.(?!css|js)[a-z_]~', $html), "$lang/$who/" . count($cards) . ' cards renders' . ($sf->missing ? ' (missing: ' . implode(',', array_unique($sf->missing)) . ')' : ''));
        ok(str_contains($html, 'id="clBoot"') && !str_contains($html, '</script><b>') && str_contains($html, 'contactlog.js?v=0.1.0'), "$lang/$who: data embedded as escaped JSON, script loaded");
        ok(str_contains($html, '/settings') === $adm, "$lang/$who: the e-mail settings shortcut is for administrators only");
        preg_match('~<script type="application/json" id="clBoot">(.*?)</script>~s', $html, $m); $j = json_decode($m[1] ?? '', true);
        ok(is_array($j) && $j['L'] === $L && $j['cards'] === $cards && $j['statuses'] === Svc::STATUSES && $j['token'] === 'tok', "$lang/$who: boot JSON round-trips");
    } catch (Throwable $e) { ok(false, "$lang/$who — " . $e->getMessage() . ' @' . basename($e->getFile()) . ':' . $e->getLine()); }
}
ok($n === 8, "rendered $n pages");

/* ---------- translations ---------- */
$ph = fn(string $s): array => (preg_match_all('/\{[a-z_]+\}/', $s, $m) ? array_unique($m[0]) : []);
ok(!array_diff_key($en, $ru) && !array_diff_key($ru, $en), 'lang: en and ru have the same keys' . (array_diff_key($en, $ru) ? ' (ru lacks: ' . implode(',', array_keys(array_diff_key($en, $ru))) . ')' : '') . (array_diff_key($ru, $en) ? ' (en lacks: ' . implode(',', array_keys(array_diff_key($ru, $en))) . ')' : ''));
$bad = []; foreach ($en as $k => $s) { if ($ph($s) != $ph($ru[$k] ?? '')) $bad[] = $k; if (trim((string)($ru[$k] ?? '')) === '') $bad[] = $k; } ok(!$bad, 'lang: same placeholders, no empty ru text' . ($bad ? ' (' . implode(',', $bad) . ')' : ''));
$view = file_get_contents("$dir/views/index.php"); $js = file_get_contents("$dir/assets/contactlog.js"); $pp = file_get_contents("$dir/plugin.php"); $svcSrc = file_get_contents("$dir/lib/Svc.php"); $rep = file_get_contents("$dir/lib/Report.php");
$used = [];
foreach ([[$view, "/->t\\('([a-z0-9_]+)'/"], [$js, "/\\bt\\('([a-z0-9_]+)'/"], [$js, "/t\\([a-z]+ \\? '([a-z_]+)' : '([a-z_]+)'\\)/"], [$js, "/t\\('(?:st|log)_'/"], [$rep, "/\\\$L\\['([a-z_]+)'\\]/"], [$svcSrc . $pp, "/new Err\\('([a-z_]+)'/"]] as [$src, $re]) if (preg_match_all($re, $src, $m)) foreach (array_slice($m, 1) as $col) foreach ($col as $x) { if ($x !== '') $used[] = (str_contains($re, 'Err') ? 'err_' : '') . $x; }
preg_match_all("/t\\(\\w+ \\? '(\\w+)' : '(\\w+)'\\)/", $js, $m); foreach (array_merge($m[1], $m[2]) as $x) $used[] = $x;
foreach (Svc::STATUSES as $s) $used[] = "st_$s"; foreach (Svc::ACTIONS as $a) $used[] = "log_$a";
foreach (['err_server', 'err_network', 'err_bad_request', 'err_not_found', 'err_forbidden', 'err_mail_not_configured', 'err_mail_failed', 'menu', 'title', 'mail_settings_help', 'rep_title'] as $x) $used[] = $x;
$used = array_filter($used, fn($k) => !str_ends_with($k, '_')); $miss = array_values(array_diff(array_unique($used), array_keys($en))); ok(!$miss, 'lang: every key the code uses exists' . ($miss ? ' (missing: ' . implode(',', $miss) . ')' : ''));
$unused = array_values(array_diff(array_keys($en), array_unique($used), ['saved', 'forbidden', 'not_found'])); $unused = array_values(array_filter($unused, fn($k) => !str_contains($view . $js . $rep, "'$k'")));
ok(!$unused, 'lang: no dead keys' . ($unused ? ' (' . implode(',', $unused) . ')' : ''));
preg_match_all("/\\\$e->errCode === '([a-z_]+)'|'(mail_[a-z_]+|no_recipient)'/", $svcSrc . $pp, $m);
foreach (['name_required', 'email_invalid', 'status_invalid', 'date_invalid', 'too_many', 'action_invalid', 'no_recipient', 'mail_not_configured', 'mail_failed', 'not_found', 'forbidden'] as $code) ok(isset($en['err_' . $code], $ru['err_' . $code]), "error code $code has a message in en and ru");

/* ---------- plugin.php, the script and the API agree ---------- */
preg_match_all("/case '([a-z]+)':/", $pp, $m); $ops = array_merge($m[1], ['cards']);
preg_match_all("/call\\('([a-z]+)'/", $js, $m); $calls = array_unique($m[1]); $gone = array_diff($calls, $ops);
ok(count($calls) === 6 && !$gone, 'the script calls only API operations plugin.php serves (' . implode(',', $calls) . ')' . ($gone ? ' missing: ' . implode(',', $gone) : ''));
preg_match_all('/\$svc\(\)->([a-zA-Z]+)\(/', $pp, $m); $nomethod = array_values(array_filter(array_unique($m[1]), fn($x) => !method_exists(Svc::class, $x))); ok(!$nomethod, 'plugin.php calls only methods that exist in Svc' . ($nomethod ? ' (' . implode(',', $nomethod) . ')' : ''));
$oa = file_get_contents("$dir/openapi.yaml"); foreach ($ops as $op) ok(str_contains($oa, "  /$op:"), "openapi.yaml documents /$op");
ok(str_contains($pp, "csrf") === false && !str_contains($js, 'innerHTML') && str_contains($js, "set('_t', boot.token)"), 'script: CSRF token on every POST, no innerHTML');
ok(!preg_match('~https?://~', $js . $view . file_get_contents("$dir/assets/contactlog.css")), 'no external hosts (fonts, icon CDNs) in the page');
ok(!str_contains($pp, 'mail(') || str_contains($pp, '$sf->mail('), 'e-mail goes through the platform mailer') ; ok(!preg_match('/[^>$\w]mail\s*\(\s*\$/', $svcSrc . $pp . $rep), 'no direct PHP mail() call');
ok(!preg_match('/fsockopen|stream_socket_client|smtp_pass|smtpPass/i', $svcSrc . $pp . $rep . $js . $view), 'no own SMTP code or credentials');

/* ---------- migration, manifest, repository ---------- */
$sql = file_get_contents("$dir/migrations/001_contactlog.sql"); preg_match_all('/CREATE TABLE IF NOT EXISTS \{p\}cl_([a-z_]+)/', $sql, $m);
ok(!array_diff(DbStore::TABLES, $m[1]) && !array_diff($m[1], DbStore::TABLES), 'migration: exactly the tables the store knows (' . count($m[1]) . ')');
ok(!preg_match('/\b(DROP|TRUNCATE|DELETE FROM|ALTER)\b/i', $sql) && substr_count($sql, 'CREATE TABLE') === substr_count($sql, 'IF NOT EXISTS {p}cl_'), 'migration: additive only, every name prefixed');
$mf = json_decode(file_get_contents("$dir/plugin.json"), true); ok(($mf['slug'] ?? '') === 'contactlog' && preg_match('/^\d+\.\d+\.\d+$/', $mf['version'] ?? '') && ($mf['name']['en'] ?? '') === 'Contact Log' && !empty($mf['name']['ru']) && !empty($mf['description']['en']) && !empty($mf['description']['ru']), 'manifest: slug, version, names and descriptions in en and ru');
ok(str_contains(file_get_contents(dirname(__DIR__) . '/.gitignore'), '!core/plugins/contactlog/'), '.gitignore: the plugin is allowed in');
ok(!preg_match('/(password|passwd|secret|api[_-]?key|token)\s*[=:]\s*[\'"][^\'"\s]{6,}/i', $pp . $svcSrc . $rep . $js . $view . $sql), 'no credentials in the plugin sources');
ok(!glob("$dir/*.log") && !glob("$dir/*.env") && !is_file("$dir/send-report-minimal.php") && !is_file("$dir/index.html"), 'the source files (mailer endpoint, debug log, html) were not copied in');

echo $fail ? "\n$fail FAILED\n" : "\nAll good\n"; exit($fail ? 1 : 0);
