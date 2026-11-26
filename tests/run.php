<?php
declare(strict_types=1);
/** Tests for the parts of the core that need no database. Run: php tests/run.php */
require dirname(__DIR__) . '/core/app/bootstrap.php';

use Sysfera\{Router, Hooks, I18n, Plugins};

$fail = 0;
function ok(bool $cond, string $name): void { global $fail; echo ($cond ? '  ok   ' : '  FAIL ') . $name . "\n"; if (!$cond) $fail++; }

/* router */
$r = new Router();
$r->add('GET', '/users/{id}', fn() => 1);
$r->add('GET', '/assets/p/{slug}/{file*}', fn() => 2);
$r->add('*', '/login', fn() => 3);
ok(($r->match('GET', '/users/7')[1]['id'] ?? '') === '7', 'router: param');
ok(($r->match('GET', '/assets/p/crm/css/a.css')[1]['file'] ?? '') === 'css/a.css', 'router: splat');
ok($r->match('POST', '/login') !== null && $r->match('GET', '/login/') !== null, 'router: any method, trailing slash');
ok($r->match('POST', '/users/7') === null, 'router: method mismatch');

/* hooks */
Hooks::on('x.filter', fn($v) => $v . 'b', 20); Hooks::on('x.filter', fn($v) => $v . 'a', 10);
ok(Hooks::filter('x.filter', '') === 'ab', 'hooks: priority order');
$seen = 0; Hooks::on('x.do', function () use (&$seen) { throw new Exception('boom'); }); Hooks::on('x.do', function () use (&$seen) { $seen++; });
@Hooks::do('x.do');
ok($seen === 1, 'hooks: a broken listener does not stop the others');

/* versions */
ok(Plugins::satisfies('1.2.0', '>=1.0.0'), 'semver: >=');
ok(!Plugins::satisfies('0.9.0', '>=1.0.0'), 'semver: too old');
ok(Plugins::satisfies('1.5.0', '>=1.0.0,<2.0.0'), 'semver: range');
ok(!Plugins::satisfies('2.0.0', '>=1.0.0,<2.0.0'), 'semver: range upper');

/* i18n: every key of en exists in every other language, same placeholders */
$langs = I18n::languages();
ok(isset($langs['en'], $langs['ru']), 'i18n: en and ru present');
foreach (['lang' => SF_ROOT . '/lang', 'installer' => SF_ROOT . '/lang/install', 'hello' => SF_ROOT . '/plugins/hello/lang'] as $label => $dir) {
    $en = require "$dir/en.php";
    foreach (glob("$dir/*.php") as $f) {
        $code = basename($f, '.php'); if ($code === 'en' || $label === 'lang' && !preg_match('/^[a-z]{2,3}$/', $code)) continue;
        $d = require $f;
        $missing = array_diff(array_keys($en), array_keys($d));
        ok(!$missing, "i18n[$label/$code]: all keys translated" . ($missing ? ' (missing: ' . implode(', ', array_slice($missing, 0, 5)) . ')' : ''));
        $bad = [];
        foreach ($en as $k => $v) if ($k[0] !== '_' && isset($d[$k])) { preg_match_all('/\{[a-z]+\}/', $v, $a); preg_match_all('/\{[a-z]+\}/', $d[$k], $b); if (array_diff($a[0], $b[0])) $bad[] = $k; }
        ok(!$bad, "i18n[$label/$code]: placeholders kept" . ($bad ? ' (' . implode(', ', $bad) . ')' : ''));
    }
}

/* every t('key') used in the code exists in en.php */
$en = require SF_ROOT . '/lang/en.php'; $missing = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(SF_ROOT . '/app', FilesystemIterator::SKIP_DOTS)) as $f) {
    if (!str_ends_with($f->getFilename(), '.php')) continue;
    preg_match_all("/\bt\('([a-z_0-9]+\.[a-z_0-9]+)'/", (string)file_get_contents($f->getPathname()), $m);
    foreach ($m[1] as $k) if (!isset($en[$k])) $missing[$k] = 1;
}
foreach (glob(SF_ROOT . '/views/*.php') as $f) { preg_match_all("/\bt\('([a-z_0-9]+\.[a-z_0-9]+)'/", (string)file_get_contents($f), $m); foreach ($m[1] as $k) if (!isset($en[$k])) $missing[$k] = 1; }
ok(!$missing, 'i18n: every t() key used in core exists in en.php' . ($missing ? ' (missing: ' . implode(', ', array_keys($missing)) . ')' : ''));

/* landing page: every component has a title and a description in every language */
$en = require SF_ROOT . '/lang/en.php'; $ru = require SF_ROOT . '/lang/ru.php';
$miss = [];
foreach (\Sysfera\Landing::components() as $c) foreach (['', '_d'] as $sfx) foreach (['en' => $en, 'ru' => $ru] as $l => $d) if (empty($d['land.c.' . $c['slug'] . $sfx])) $miss[] = "$l:" . $c['slug'] . $sfx;
ok(!$miss, 'landing: all component texts exist in en and ru' . ($miss ? ' (missing ' . implode(', ', $miss) . ')' : ''));
$lk = fn(array $d) => array_keys(array_filter($d, fn($k) => str_starts_with($k, 'land.'), ARRAY_FILTER_USE_KEY));
ok($lk($en) === $lk($ru) || array_diff($lk($en), $lk($ru)) === [], 'landing: en and ru have the same keys');
ok(\Sysfera\Landing::enabled(null) && \Sysfera\Landing::enabled('1') && !\Sysfera\Landing::enabled('0'), 'landing: on by default, off only when set to 0');

/* the start page follows the installed and offered components */
$dyn = \Sysfera\Landing::all(['wms' => ['name' => 'W'], 'storagerent' => ['name' => ['en' => 'Storage Rent', 'ru' => 'Склад'], 'description' => ['en' => 'First sentence. Second one.', 'ru' => 'Первое. Второе.']], 'accounting' => ['name' => 'A']], ['newthing' => ['name' => ['en' => 'New thing'], 'description' => 'Does X.']], 'ru');
$by = array_column($dyn, null, 'slug');
ok(isset($by['storagerent']) && $by['storagerent']['title'] === 'Склад' && $by['storagerent']['desc'] === 'Первое.' && isset($by['newthing']) && $by['newthing']['title'] === 'New thing', 'landing: installed and offered components get a card by themselves');
ok(count(array_keys(array_column($dyn, 'slug'), 'wms')) === 1 && $by['accounting']['state'] === 'ready' && $by['vacancy']['state'] === 'soon' && array_column(\Sysfera\Landing::all(['hr' => ['name' => 'HR']]), 'state', 'slug')['vacancy'] === 'ready' && !in_array('hr', array_column(\Sysfera\Landing::all(['hr' => ['name' => 'HR']]), 'slug'), true), 'landing: no duplicate for a curated component; an announced one becomes ready once it exists');

/* the public pages render in every language, search text is escaped */
foreach (['en', 'ru'] as $l) {
    I18n::setLang($l);
    $html = [
        'start' => \Sysfera\View::file(SF_ROOT . '/views/landing.php', ['feed' => ['html' => '<p>posts</p>', 'global' => true], 'scope' => 'local', 'q' => '"<x', 'blocks' => '<b>block</b>']),
        'one site' => \Sysfera\View::file(SF_ROOT . '/views/landing.php', ['feed' => ['html' => '', 'global' => false], 'scope' => 'local', 'q' => '', 'blocks' => '']),
        'components' => \Sysfera\View::file(SF_ROOT . '/views/components.php', ['open' => true, 'all' => \Sysfera\Landing::all([], [], $l), 'blocks' => '']),
        'how' => \Sysfera\View::file(SF_ROOT . '/views/how.php', ['open' => false]),
    ];
    ok(str_contains($html['start'], '<p>posts</p>') && str_contains($html['start'], '<b>block</b>') && !str_contains($html['start'], '"<x') && str_contains($html['start'], 'scope=global') && str_contains($html['start'], e(t('land.feed_local'))), "start page ($l): search, switch and posts");
    ok(!str_contains($html['one site'], 'land-switch'), "start page ($l): no switch without the shared feed");
    ok(substr_count($html['components'], 'land-card') >= count(\Sysfera\Landing::components()) && !preg_match('/>land\./', $html['components'] . $html['how']) && str_contains($html['how'], e(t('land.f_search_d'))), "components and how-it-works pages ($l)");
}
I18n::setLang('en');

/* plugin manifests */
foreach (glob(SF_ROOT . '/plugins/*/plugin.json') as $f) ok(Plugins::readManifest($f) !== null, 'manifest valid: ' . basename(dirname($f)));

/* migrations: statements split the way the Migrator does and every table is prefixed */
$sql = str_replace("\r\n", "\n", (string)file_get_contents(SF_ROOT . '/app/migrations/001_core.sql'));
$parts = array_values(array_filter(array_map('trim', preg_split('/;\s*\n/', $sql . "\n")), fn($s) => $s !== ''));
ok(count($parts) === 9, 'migration: 9 statements (got ' . count($parts) . ')');
ok(!preg_grep('/CREATE TABLE IF NOT EXISTS (?!\{p\})/', $parts), 'migration: all tables use {p} prefix');

echo $fail ? "\n$fail FAILED\n" : "\nAll good\n";
exit($fail ? 1 : 0);
