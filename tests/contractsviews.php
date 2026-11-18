<?php
declare(strict_types=1);
/** Renders every Contracts page with real sample data (Svc + in-memory store) for the author, the contractor, an outsider and an administrator, in English and in Russian;
 *  a PHP warning or a missing translation fails. Also: en/ru have the same keys and placeholders, every key used by the code exists, every error code has a message,
 *  the migration creates every table the store may touch, the manifest is valid, the .gitignore lets the plugin in. Run: php tests/contractsviews.php */
foreach (['mb_substr', 'mb_strlen', 'mb_strtolower'] as $f) if (!function_exists($f)) eval("function $f(string \$s, int \$a = 0, ?int \$l = null) { return " . ($f === 'mb_strlen' ? 'strlen($s)' : ($f === 'mb_strtolower' ? 'strtolower($s)' : '$l === null ? substr($s, $a) : substr($s, $a, $l)')) . '; }');
if (!function_exists('e')) { function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); } }
if (!function_exists('csrf_field')) { function csrf_field(): string { return '<input type="hidden" name="_t" value="x">'; } }
if (!function_exists('url')) { function url(string $p = '/'): string { return '/' . ltrim($p, '/'); } }
$dir = dirname(__DIR__) . '/core/plugins/contracts';
foreach (['Err', 'Ctx', 'Store', 'Svc', 'Notice', 'DbStore'] as $c) require "$dir/lib/$c.php";
require __DIR__ . '/ContractsMemStore.php';
use SysferaContracts\{Svc, Ctx, Err, Notice};
$fail = 0; function ok(bool $c, string $n): void { global $fail; echo ($c ? '  ok   ' : '  FAIL ') . $n . "\n"; if (!$c) $fail++; }
set_error_handler(function (int $no, string $msg, string $file, int $line): bool { throw new ErrorException("$msg in " . basename($file) . ":$line"); });
$en = require "$dir/lang/en.php"; $ru = require "$dir/lang/ru.php";

final class FakeSf {
    public array $manifest = ['version' => '0.1.0']; public array $missing = [];
    public function __construct(private array $l) {}
    public function t(string $k, array $v = []): string { if (!isset($this->l[$k])) { $this->missing[] = $k; return 'contracts.' . $k; } $s = $this->l[$k]; foreach ($v as $a => $b) $s = str_replace('{' . $a . '}', (string)$b, $s); return $s; }
    public function url(string $p = ''): string { return '/p/contracts/' . ltrim($p, '/'); }
    public function asset(string $f): string { return '/assets/p/contracts/' . $f; }
}

/* ---------- a populated marketplace ---------- */
$now = strtotime('2026-11-17 10:00:00'); $st = new ContractsMemStore();
$people = [1 => 'Admin', 2 => 'Anna Author', 3 => 'Boris Builder', 4 => 'Clara Coder', 5 => 'Dan Outsider'];
$svc = new Svc($st, function () use (&$now) { return $now; }, fn(array $ids) => array_intersect_key(array_map(fn($n) => ['id' => 0, 'name' => $n, 'email' => 'x@x.x', 'lang' => 'en'], $people), array_flip($ids)), null, []);
$adm = new Ctx(1, true, 'Admin'); $anna = new Ctx(2, false, 'Anna Author'); $boris = new Ctx(3, false, 'Boris Builder'); $clara = new Ctx(4, false, 'Clara Coder'); $dan = new Ctx(5, false, 'Dan Outsider');
foreach ([['Software', 'Программирование'], ['Design', 'Дизайн']] as [$a, $b]) $st->insert('categories', ['name' => $a, 'name_ru' => $b]);
$sec = $svc->saveSection(0, 'Company details', 'company', 1); $svc->saveField(0, $sec, 'Tax ID', 'text', '', true, false, 1); $svc->saveField(0, $sec, 'Regime', 'select', "A\nB", false, true, 2);
$svc->saveProfile($boris, ['types' => ['company', 'personal'], 'about' => 'Builder', 'notify_email' => '1'], [1 => ['value' => '123', 'public' => ''], 2 => ['value' => 'A', 'public' => '1']]);
$form = fn($t, $steps, $pay = 1) => ['title' => $t, 'category_id' => 1, 'location' => 'Remote', 'description' => "Line one\nLine two <b>", 'currency' => 'EUR', 'publish' => $pay, 'prepay_required' => 1, 'todos' => $steps];
$pub = (int)$svc->saveContract($anna, 0, $form('Landing page', [['name' => 'Design', 'price' => '100', 'days' => 2, 'description' => 'Figma'], ['name' => 'Code', 'price' => '300', 'days' => 3]]))['id'];
$draft = (int)$svc->saveContract($anna, 0, $form('A draft', [['name' => 'x', 'price' => '1', 'days' => 1]], 0))['id'];
$oB = $svc->makeOffer($boris, $pub, ['items' => [['type' => 'todo', 'id' => 2, 'price' => '250', 'days' => 2], ['type' => 'extra', 'name' => 'Deploy', 'price' => '40', 'days' => 1], ['type' => 'todo', 'id' => 1, 'price' => '120', 'days' => 2]], 'payment_type' => 'step', 'notes' => 'Ready']);
$svc->makeOffer($clara, $pub, [], true); $svc->comment($boris, $pub, 'public', 'Question'); $svc->comment($anna, $pub, 'public', 'Answer', 3); $svc->comment($boris, $pub, 'offer', 'About my offer', 0, $oB);
$svc->watchToggle($dan, $pub);
$work = (int)$svc->saveContract($anna, 0, $form('In work', [['name' => 'A', 'price' => '10', 'days' => 1], ['name' => 'B', 'price' => '20', 'days' => 1], ['name' => 'C', 'price' => '30', 'days' => 1]]))['id'];
$svc->acceptOffer($anna, $svc->makeOffer($boris, $work, ['items' => [], 'payment_type' => 'step'])); [$s1, $s2] = array_map(fn($t) => (int)$t['id'], $svc->steps($work));
$svc->comment($boris, $work, 'work', 'Started'); $svc->comment($anna, $work, 'todo', 'On step', 0, 0, $s1); $svc->done($boris, $s1); $svc->accept($anna, $s1); $svc->addFile($anna, $work, 'file', 'a.pdf', 'brief.pdf'); $svc->prepay($anna, $work);
$done = (int)$svc->saveContract($anna, 0, $form('Finished', [['name' => 'Only', 'price' => '50', 'days' => 1]]))['id']; $svc->acceptOffer($anna, $svc->makeOffer($boris, $done, [], true));
$only = (int)$svc->steps($done)[0]['id']; $svc->done($boris, $only); $inv = $svc->accept($anna, $only)['invoice']; $svc->payInvoice($anna, $inv); $svc->review($anna, $done, 3, 5, 'Great');
$svc->fire($anna, $svc->acceptOffer($anna, $svc->makeOffer($clara, (int)$svc->saveContract($anna, 0, $form('Fired', [['name' => 'z', 'price' => '5', 'days' => 1]]))['id'], [], true)));
$svc->clearNotices($dan);

/* ---------- pages ---------- */
$sf = new FakeSf($en); $ctxs = ['author' => $anna, 'contractor' => $boris, 'outsider' => $dan, 'admin' => $adm];
$vars = function (Ctx $c, string $lang) use ($svc, $pub, $draft, $work, $done, $oB, $inv, $sec, $st, $en, $ru) {
    $L = $lang === 'ru' ? $ru : $en; $base = ['me' => $c, 'svc' => $svc, 'unread' => $svc->unread($c->uid), 'lang' => $lang, 'L' => $L];
    $try = function (callable $f, $d = null) { try { return $f(); } catch (Err $e) { return $d; } };
    $pg = fn(int $id, int $thr = 0) => $try(fn() => $svc->page($c, $id, $thr)); $v = [];
    $v['list'] = ['tab' => 'list', 'list' => $svc->listing(['q' => 'page']), 'f' => ['q' => 'page'], 'cats' => $svc->categories()];
    $v['list-empty'] = ['tab' => 'list', 'list' => $svc->listing(['q' => 'zzzz']), 'f' => ['q' => 'zzzz', 'from' => '1'], 'cats' => $svc->categories()];
    foreach (['created', 'working', 'offers', 'archive', 'watch'] as $tab) $v["mine-$tab"] = ['tab' => 'mine', 'rows' => $svc->mine($c, $tab), 'sub' => $tab];
    $v['edit-new'] = ['tab' => 'mine', 'k' => null, 'steps' => [], 'locked' => false, 'files' => [], 'cats' => $svc->categories(), 'myTypes' => $svc->userTypes($c->uid), 'form' => null];
    if ($c->uid === 2 || $c->admin) { $k = $svc->contract($pub); $v['edit-locked'] = ['tab' => 'mine', 'k' => $k, 'steps' => $svc->steps($pub), 'locked' => true, 'files' => [], 'cats' => $svc->categories(), 'myTypes' => ['company', 'personal'], 'form' => ['title' => 'x', 'todos' => [['name' => 'a', 'price' => '1', 'days' => 1]]]]; $k = $svc->contract($draft); $v['edit-draft'] = ['tab' => 'mine', 'k' => $k, 'steps' => $svc->steps($draft), 'locked' => false, 'files' => $svc->page($c, $work)['files'], 'cats' => $svc->categories(), 'myTypes' => ['personal'], 'form' => null]; }
    foreach (['pub' => $pub, 'draft' => $draft, 'work' => $work, 'done' => $done] as $n => $id) if ($p = $pg($id)) $v["view-$n"] = ['tab' => 'list', 'draft' => null, 'payOpen' => 0] + $p;
    if ($p = $pg($pub, 4)) $v['view-pub-thread'] = ['tab' => 'list', 'draft' => ['items' => [['type' => 'todo', 'id' => 1, 'price' => 'oops', 'days' => '1'], ['type' => 'extra', 'name' => 'mine', 'price' => '5', 'days' => '1']], 'notes' => 'n', 'payment_type' => 'step', 'offerer_type' => 'personal'], 'payOpen' => 0] + $p;
    if ($p = $try(fn() => $svc->offerPage($c, $oB))) { $v['offer'] = ['tab' => 'list', 'draft' => null, 'edit' => false, 'myTypes' => ['company', 'personal']] + $p; $v['offer-edit'] = ['tab' => 'list', 'draft' => null, 'edit' => true, 'myTypes' => ['company', 'personal']] + $p; $v['offer-draft'] = ['tab' => 'list', 'draft' => ['items' => [['type' => 'todo', 'id' => 1, 'price' => '9', 'days' => '1']], 'notes' => 'x'], 'edit' => true, 'myTypes' => ['personal']] + $p; }
    foreach (['pay', 'receive'] as $tab) $v["invoices-$tab"] = ['tab' => 'invoices', 'list' => $svc->invoices($c, $tab), 'sub' => $tab, 'sort' => 'date', 'dir' => 'desc', 'q' => '', 'counts' => $svc->invoiceCounts($c)];
    $v['invoices-search'] = ['tab' => 'invoices', 'list' => $svc->invoices($c, 'pay', 'zzz'), 'sub' => 'pay', 'sort' => 'contract', 'dir' => 'asc', 'q' => 'zzz', 'counts' => $svc->invoiceCounts($c)];
    if ($p = $try(fn() => $svc->invoicePage($c, $inv))) $v['invoice'] = ['tab' => 'invoices'] + $p;
    $v['notices'] = ['tab' => 'notices', 'list' => $svc->notices($c)];
    $v['user'] = ['tab' => 'list', 'uid' => 3, 'canReview' => $svc->reviewable($c, 3)] + $svc->userPage(3);
    $v['profile'] = ['tab' => 'profile', 'profile' => $svc->profile($c->uid), 'types' => $svc->userTypes($c->uid), 'structure' => $svc->structure(Svc::PARTY_TYPES), 'values' => $svc->values($c->uid)];
    if ($c->admin) $v['admin'] = ['tab' => 'admin', 'cats' => $svc->categories(), 'structure' => $svc->structure(), 'staleDays' => 30];
    foreach ($v as $k => $vars) $v[$k] = $vars + $base; return $v;
};
$render = function (string $view, array $vars) use (&$sf) { extract($vars); ob_start(); try { require dirname(__DIR__) . "/core/plugins/contracts/views/$view.php"; } catch (Throwable $e) { ob_end_clean(); throw $e; } return (string)ob_get_clean(); };
$n = 0;
foreach (['en' => $en, 'ru' => $ru] as $lang => $L) foreach ($ctxs as $who => $c) {
    $sf = new FakeSf($L);
    foreach ($vars($c, $lang) as $name => $v) {
        $view = preg_replace('/-.*/', '', $name); $view = ['view' => 'view', 'mine' => 'mine', 'list' => 'list', 'edit' => 'edit', 'offer' => 'offer', 'invoices' => 'invoices'][$view] ?? $view; $sf->missing = [];
        $v['sf'] = $sf;
        try { $html = $render($view, $v); $n++; ok(strlen($html) > 80 && !$sf->missing && !preg_match('~(?<![/\w])contracts\.(?!css|js)[a-z_]~', $html), "$lang/$who/$name renders" . ($sf->missing ? ' (missing: ' . implode(',', array_unique($sf->missing)) . ')' : '')); }
        catch (Throwable $e) { ok(false, "$lang/$who/$name — " . $e->getMessage() . " @" . basename($e->getFile()) . ":" . $e->getLine()); }
    }
}
ok($n > 150, "rendered $n pages");
$h = $render('view', ['tab' => 'list', 'draft' => null, 'payOpen' => 0] + $vars($anna, 'en')['view-pub'] + ['sf' => new FakeSf($en)]);
ok(str_contains($h, 'Landing page') && str_contains($h, 'Line two &lt;b&gt;') && !str_contains($h, 'Line two <b>'), 'view: text is escaped');
ok(str_contains($h, '/p/contracts/o/') && str_contains($h, 'Accept offer'), 'view: the author sees the offers with accept / reject');
$h = $render('view', ['tab' => 'list', 'draft' => null, 'payOpen' => 0] + $vars($dan, 'en')['view-pub'] + ['sf' => new FakeSf($en)]);
ok(str_contains($h, 'Send counteroffer') && str_contains($h, 'Accept contract as is') && !str_contains($h, 'Accept offer'), 'view: an outsider can make an offer, not accept one');
$h = $render('view', ['tab' => 'list', 'draft' => null, 'payOpen' => 0] + $vars($boris, 'en')['view-work'] + ['sf' => new FakeSf($en)]);
ok(str_contains($h, 'Invoices') && str_contains($h, 'Mark as done') && !str_contains($h, 'Dismiss contractor'), 'view: the contractor works the steps and sees the invoices, cannot dismiss');
$h = $render('view', ['tab' => 'list', 'draft' => null, 'payOpen' => 0] + $vars($anna, 'en')['view-work'] + ['sf' => new FakeSf($en)]);
ok(str_contains($h, 'Dismiss contractor') && str_contains($h, 'Accept and open invoice') === false, 'view: the author may dismiss; accept needs a done step');
$h = $render('invoices', $vars($boris, 'ru')['invoices-receive'] + ['sf' => new FakeSf($ru)]); ok(str_contains($h, 'Подтвердить оплату') && str_contains($h, 'CT-'), 'invoices: the payee sees "Confirm payment" (ru)');
$h = $render('notices', $vars($boris, 'ru')['notices'] + ['sf' => new FakeSf($ru)]); ok(str_contains($h, 'сделал(а) предложение') === false && str_contains($h, 'принял'), 'notices: the text is built from the notice kind in the reader\'s language');

/* ---------- translations ---------- */
$ph = fn(string $s): array => (preg_match_all('/\{[a-z_]+\}/', $s, $m) ? array_unique($m[0]) : []);
ok(!array_diff_key($en, $ru) && !array_diff_key($ru, $en), 'lang: en and ru have the same keys' . (array_diff_key($en, $ru) ? ' (ru lacks: ' . implode(',', array_keys(array_diff_key($en, $ru))) . ')' : '') . (array_diff_key($ru, $en) ? ' (en lacks: ' . implode(',', array_keys(array_diff_key($ru, $en))) . ')' : ''));
$bad = []; foreach ($en as $k => $s) { if ($ph($s) != $ph($ru[$k] ?? '')) $bad[] = $k; if (trim((string)($ru[$k] ?? '')) === '') $bad[] = $k; } ok(!$bad, 'lang: same placeholders, no empty ru text' . ($bad ? ' (' . implode(',', $bad) . ')' : ''));
$src = ''; foreach (array_merge(glob("$dir/views/*.php"), ["$dir/plugin.php", "$dir/lib/Svc.php", "$dir/lib/Notice.php"]) as $f) $src .= file_get_contents($f) . "\n";
$used = []; if (preg_match_all("/->t\\('([a-z0-9_]+)'/", $src, $m)) $used = array_merge($used, $m[1]); if (preg_match_all("/\\?\\s*'(flash_[a-z_]+)'\\s*:\\s*'(flash_[a-z_]+)'/", $src, $m)) $used = array_merge($used, $m[1], $m[2]);
$families = ['st_' => ['draft', 'published', 'active', 'paused', 'fulfilled', 'terminated'], 'pay_' => Svc::PAYMENT_TYPES, 'pt_' => Svc::PARTY_TYPES, 'as_' => Svc::PARTY_TYPES, 'inv_' => Svc::INVOICE_STATUS, 'ft_' => Svc::FIELD_TYPES, 'mine_' => ['created', 'working', 'offers', 'archive', 'watch'], 'nt_' => Notice::KINDS];
foreach ($families as $p => $vals) foreach ($vals as $x) $used[] = $p . $x;
if (preg_match_all("/new Err\\('([a-z_]+)'/", $src, $m)) foreach ($m[1] as $code) $used[] = 'err_' . $code;
foreach (['err_file_big', 'err_file_fail', 'err_server', 'menu', 'title'] as $x) $used[] = $x;
$used = array_values(array_filter($used, fn($k) => !str_ends_with($k, '_'))); $miss = array_values(array_diff(array_unique($used), array_keys($en))); ok(!$miss, 'lang: every key the code uses exists' . ($miss ? ' (missing: ' . implode(',', $miss) . ')' : ''));
$unused = array_values(array_filter(array_diff(array_keys($en), array_unique($used)), fn($k) => !str_starts_with($k, 'nt_l_') && !str_starts_with($k, 'mail_') && !str_contains($src, "'$k'"))); ok(!$unused, 'lang: no dead keys' . ($unused ? ' (' . implode(',', $unused) . ')' : ''));
foreach (Notice::KINDS as $k) { $d = ['who' => 'Anna', 'title' => 'T', 'step' => 'S', 'extra' => 'X', 'comment' => 'C']; $lines = Notice::lines($ru, $k, $d); if (!str_contains($lines[0], 'Anna ') || !str_contains(implode("\n", $lines), 'Шаг: S')) { ok(false, "notice $k"); } }
ok(Notice::headline($en, 'stale', ['who' => '']) === 'The contract found no contractor in time, was removed from the list and moved to your archive' && Notice::link('http://h/x', 'view/3#c') === 'http://h/x/view/3#c', 'notice: system messages have no actor; links are joined');
/* every kind used by Svc::notify exists */
preg_match_all("/notify\\('([a-z_]+)'|notify\\(\\\$[^,]+\\? '([a-z_]+)' : '([a-z_]+)'|notify\\(\\\$left === 0 \\? '([a-z_]+)' : '([a-z_]+)'|notify\\(\\\$t\\['done_at'\\] \\? '([a-z_]+)' : '([a-z_]+)'/", file_get_contents("$dir/lib/Svc.php"), $m);
$kinds = array_unique(array_filter(array_merge(...array_slice($m, 1)))); $unknown = array_diff($kinds, Notice::KINDS); ok(count($kinds) >= 20 && !$unknown, 'notice: every kind the rules send has a text (' . count($kinds) . ' kinds)' . ($unknown ? ' unknown: ' . implode(',', $unknown) : ''));

/* ---------- plugin.php agrees with the rules and the views ---------- */
$pp = file_get_contents("$dir/plugin.php"); preg_match_all('/\$svc\(\)->([a-zA-Z]+)\(/', $pp . $src, $m); $gone = array_values(array_filter(array_unique($m[1]), fn($x) => !method_exists(Svc::class, $x)));
ok(!$gone, 'plugin.php and the views call only methods that exist in Svc' . ($gone ? ' (missing: ' . implode(',', $gone) . ')' : ''));
ok(str_contains($pp, 'use SysferaContracts\{Err, Ctx, Svc, DbStore, Notice}') && !str_contains($pp, 'ReflectionClass'), 'plugin.php: imports');
preg_match_all("~\\\$sf->(?:route|api)\\('(GET|POST)', '([^']+)'~", $pp, $rm); $patterns = $rm[2];
preg_match_all("~sf->url\\('(/[^'?#]*)~", $src, $um); $dead = [];
foreach (array_unique($um[1]) as $u) { $hit = false; foreach ($patterns as $p) { $re = '~^' . preg_replace('~\\\\\{[a-z]+\\\\\}~', '[^/]+', preg_quote($p, '~')) . '$~'; if (preg_match($re, $u) || str_starts_with($p, $u)) { $hit = true; break; } } if (!$hit) $dead[] = $u; }
ok(!$dead && count($patterns) > 20, 'every plugin url used in the views is a route (' . count($patterns) . ' routes)' . ($dead ? ' (no route: ' . implode(', ', $dead) . ')' : ''));
foreach (['/c/' => ['offer', 'comment', 'upload', 'pause', 'fire', 'prepay', 'repeat', 'delete', 'watch'], '/o/' => ['accept', 'reject', 'delete', 'edit', 'comment'], '/t/' => ['done', 'accept', 'comment'], '/i/' => ['pay', 'confirm', 'number']] as $pre => $acts) {
    preg_match_all("~sf->url\\('" . preg_quote($pre, '~') . "' \\. [^)]*?'/([a-z]+)'~", $src, $am); $bad = array_diff(array_unique($am[1]), $acts); ok(!$bad, "views post only to actions that $pre handles" . ($bad ? ' (' . implode(',', $bad) . ')' : ''));
}

/* ---------- migration, manifest, repository ---------- */
$sql = file_get_contents("$dir/migrations/001_contracts.sql"); preg_match_all('/CREATE TABLE IF NOT EXISTS \{p\}ct_([a-z_]+)/', $sql, $m);
ok(!array_diff(\SysferaContracts\DbStore::TABLES, $m[1]) && !array_diff($m[1], \SysferaContracts\DbStore::TABLES), 'migration: exactly the tables the store knows (' . count($m[1]) . ')');
ok(!preg_match('/\b(DROP|TRUNCATE|DELETE FROM)\b/i', $sql) && substr_count($sql, 'CREATE TABLE') === substr_count($sql, 'IF NOT EXISTS {p}ct_'), 'migration: additive only, every name prefixed');
$mf = json_decode(file_get_contents("$dir/plugin.json"), true); ok(($mf['slug'] ?? '') === 'contracts' && preg_match('/^\d+\.\d+\.\d+$/', $mf['version'] ?? '') && !empty($mf['name']['en']) && !empty($mf['name']['ru']) && !empty($mf['description']['ru']), 'manifest: slug, version, names and descriptions in en and ru');
ok(str_contains(file_get_contents(dirname(__DIR__) . '/.gitignore'), '!core/plugins/contracts/'), '.gitignore: the plugin is allowed in');
ok(!preg_match('/(password|passwd|secret|api[_-]?key|token)\s*[=:]\s*[\'"][^\'"\s]{6,}/i', $src . $sql), 'no credentials in the plugin sources');
ok(!is_file("$dir/.env") && !glob("$dir/*.env"), 'no .env copied from the original app');

echo $fail ? "\n$fail FAILED\n" : "\nAll good\n"; exit($fail ? 1 : 0);
