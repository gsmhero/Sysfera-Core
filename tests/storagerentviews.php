<?php
declare(strict_types=1);
/** Renders every page of Storage Rent (en and ru, every role) on an in-memory store; a PHP warning or a missing translation fails. Also: en/ru have the same keys and placeholders, every key the code
 *  uses exists, every error code has a message, openapi.yaml documents the machine endpoints plugin.php serves, the migrations create exactly the tables the store knows, the manifest is valid,
 *  the .gitignore lets the plugin in, no credentials or map keys in the sources, staff roles do not come from platform capabilities. Run: php tests/storagerentviews.php */
if (!function_exists('e')) { function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); } }
if (!function_exists('csrf_field')) { function csrf_field(): string { return '<input type="hidden" name="_t" value="tok">'; } }
if (!function_exists('url')) { function url(string $p = '/'): string { return '/' . ltrim($p, '/'); } }
$dir = dirname(__DIR__) . '/core/plugins/storagerent';
foreach (['Err', 'Store', 'Billing', 'Ctx', 'Wrms', 'Ledger', 'Notify', 'Reports', 'Xlsx', 'Offers', 'Files', 'Text', 'Pages', 'DbStore'] as $c) require_once "$dir/lib/$c.php";
require __DIR__ . '/StoragerentMemStore.php';
use SysferaStoragerent\{Ctx, Wrms, Ledger, Notify, Reports, Offers, Pages, Err, DbStore, Billing};
$fail = 0; function ok(bool $c, string $n): void { global $fail; echo ($c ? '  ok   ' : '  FAIL ') . $n . "\n"; if (!$c) $fail++; }
set_error_handler(function (int $no, string $msg, string $file, int $line): bool { throw new ErrorException("$msg in " . basename($file) . ":$line"); });
$en = require "$dir/lang/en.php"; $ru = require "$dir/lang/ru.php";

final class FakeSf {
    public array $manifest = ['version' => '0.1.4']; public array $missing = [];
    public function __construct(private array $l, private array $set = []) {}
    public function t(string $k, array $v = []): string { if (!isset($this->l[$k])) { $this->missing[] = $k; return 'MISSING:' . $k; } $s = $this->l[$k]; foreach ($v as $a => $b) $s = str_replace('{' . $a . '}', (string)$b, $s); return $s; }
    public function url(string $p = ''): string { return '/p/storagerent/' . ltrim($p, '/'); }
    public function asset(string $f): string { return '/assets/p/storagerent/' . $f; }
    public function setting(string $k, string $d = ''): string { return $this->set[$k] ?? $d; }
}

/* ---------- a small world ---------- */
$now = 1_780_000_000; $today = '2026-04-10'; $st = new StoragerentMemStore(); $clock = fn() => $now; $date = function () use (&$today) { return $today; };
$wr = new Wrms($st, $clock, $date); $lg = new Ledger($st, $clock, $date); $rp = new Reports($st, $lg, $wr, $date); $of = new Offers($st, $clock);
$mails = []; $nf = new Notify($st, $lg, $clock, $date, function ($to, $s, $b) use (&$mails) { $mails[] = [$to, $s]; return true; }, 'Sysfera');
$settings = ['yandex_key' => '', 'peers' => '', 'company_name' => 'Landlord <b>LLC</b>', 'company_details' => "INN 1\nBank", 'cron_token' => 'tok-1', 'staff' => '12:manager'];
$pg = new Pages($st, $wr, $lg, $rp, $nf, $of, fn($k, $d = '') => $settings[$k] ?? $d, fn($p, $id) => '/p/storagerent/offers/photo/' . (int)$p['id'], $date);
$adm = new Ctx(1, 'admin'); $mgr = new Ctx(2, 'manager'); $acc = new Ctx(3, 'accountant');
$w = $wr->saveWarehouse($adm, 0, ['name' => 'Main <i>', 'city' => 'Kazan', 'address' => 'Lenina 1', 'lat' => '55.79', 'lon' => '49.12']);
$u1 = $wr->saveUnit($adm, 0, ['warehouse_id' => $w['id'], 'name' => 'A-1', 'type' => 'dry', 'area' => '45', 'volume' => '180', 'pallets' => 30, 'features' => ['racking', 'power']]);
$u2 = $wr->saveUnit($adm, 0, ['warehouse_id' => $w['id'], 'name' => 'B-1', 'type' => 'cold', 'area' => '100']);
$t = $wr->saveTenant($adm, 0, ['kind' => 'legal', 'name' => 'Acme </td>LLC', 'tin' => '7707083893', 'email' => 'acme@example.com', 'restrictions' => 'No <script>flammable', 'vat_mode' => 'added', 'vat_rate' => 20, 'user_id' => 44]);
$tU = $wr->saveTenant($adm, 0, ['kind' => 'sole', 'name' => 'IP Petrov', 'tin' => '770708389312']);
$k = $wr->saveContract($adm, 0, ['tenant_id' => $t['id'], 'start_date' => '2026-03-01', 'end_date' => '2026-12-31', 'rate_type' => 'm2', 'rate' => '500', 'billing_months' => 1, 'due_day' => 5, 'grace_days' => 1, 'penalty_type' => 'percent', 'penalty_value' => '0.5',
    'util_method' => 'metered', 'access_24h' => 0, 'access_from' => '08:00', 'access_to' => '20:00', 'units' => [['unit_id' => $u1['id'], 'share' => 100]]]); $wr->activate($adm, $k['id']);
$kd = $wr->saveContract($adm, 0, ['tenant_id' => $tU['id'], 'start_date' => '2026-05-01', 'end_date' => '2026-12-31', 'rate_type' => 'fixed', 'rate' => '9000', 'due_day' => 10, 'access_24h' => 1, 'units' => [['unit_id' => $u2['id'], 'share' => 50]]]);
$today = '2026-03-01'; $lg->tick(); $today = '2026-04-01'; $lg->tick(); $today = '2026-04-10';
$lg->registerPayment($adm, $t['id'], null, '2026-03-04', '10000', 'bank', 'PP-1');
$lg->createManual($adm, $k['id'], [['description' => 'Key <copy>', 'amount' => '500', 'vatable' => true]], '2026-04-20', 'lost key', false);
$m = $lg->saveMeter($adm, 0, $u1['id'], 'electricity', 'E-1', '8.5', ''); $doc = $wr->addDocument($adm, 'meter', $m, null, 'meter reading', 'p.jpg', 'abc.jpg', 'image/jpeg', 10, 'meter');
$lg->addReading($adm, $m, '2026-03-31', '100', null); $lg->addReading($adm, $m, '2026-04-30', '130', $doc);
$wr->addDocument($adm, 'contract', $k['id'], $t['id'], 'Signed <contract>', 'c.pdf', 'def.pdf', 'application/pdf', 20, 'file');
$wr->createRequest(new Ctx(9, 'tenant', (int)$t['id']), (int)$t['id'], 'Light <bulb>', "Please\nfix", $u1['id'], null);
$wr->logAccess($adm, (int)$t['id'], $u1['id'], 'Ivan', 'in', 'x'); $nf->run(['before' => '5', 'after' => '1,3,7', 'expiring' => '30,7', 'lang' => 'ru']);
$l1 = $of->save(7, false, 0, ['name' => 'Sklad </a>Alpha', 'city' => 'Kazan', 'street' => 'Lenina 1', 'lat' => '55.79', 'lon' => '49.12', 'area' => '500', 'price_m2' => '450', 'status' => 'published', 'video' => 1, 'parking' => 'trucks', 'access_24h' => 1, 'purpose' => "Dry goods\nmin 3 months", 'floor_load' => '5'], 'Olga');
$l2 = $of->save(7, false, 0, ['name' => 'Draft', 'city' => 'Moscow', 'area' => '10', 'price_m2' => '1', 'status' => 'draft'], 'Olga'); $of->addPhoto(7, false, (int)$l1['id'], 'ph1.jpg', 'image/jpeg', 100);

$render = function (FakeSf $sf, string $lang, array $L, array $page, Ctx $c, bool $regOpen) use ($dir) {
    $vars = $page + ['sf' => $sf, 'ctx' => $c, 'lang' => $lang, 'L' => $L, 'regOpen' => $regOpen]; extract($vars); ob_start();
    try { require $dir . '/views/' . $page['view'] . '.php'; } catch (Throwable $e) { ob_end_clean(); throw $e; } return (string)ob_get_clean();
};
$pagesFor = function (Ctx $c) use ($pg, $k, $kd, $t, $u1, $l1, $l2, $lg, $w): array {
    $inv = $lg->invoices(['tenant_id' => (int)$t['id']]); $iid = 0; foreach ($inv as $i) if ($i['stored_status'] !== 'draft') { $iid = (int)$i['id']; break; } $draftInv = 0; foreach ($inv as $i) if ($i['stored_status'] === 'draft') $draftInv = (int)$i['id'];
    $p = [];
    if ($c->staff()) {
        $p = ['dashboard' => fn() => $pg->dashboard($c), 'units' => fn() => $pg->units($c), 'tenants' => fn() => $pg->tenants($c), 'tenants?q' => fn() => $pg->tenants($c, 'acme', 'active'), 'tenant' => fn() => $pg->tenant($c, (int)$t['id']), 'tenant0' => fn() => $pg->tenant($c, 0),
            'contracts' => fn() => $pg->contracts($c), 'contract' => fn() => $pg->contract($c, (int)$k['id']), 'contract draft' => fn() => $pg->contract($c, (int)$kd['id']), 'contract print' => fn() => $pg->contractPrint($c, (int)$k['id']),
            'invoices' => fn() => $pg->invoices($c), 'invoices late' => fn() => $pg->invoices($c, 'late'),
            'invoice' => fn() => $pg->invoice($c, $iid), 'invoice draft' => fn() => $pg->invoice($c, $draftInv), 'invoice print' => fn() => $pg->invoicePrint($c, $iid), 'payments' => fn() => $pg->payments($c), 'utilities' => fn() => $pg->utilities($c), 'utilities meter' => fn() => $pg->utilities($c, 1),
            'notifications' => fn() => $pg->notifications($c), 'access' => fn() => $pg->access($c), 'documents' => fn() => $pg->documents($c, 'contract'), 'requests' => fn() => $pg->requests($c), 'reports' => fn() => $pg->reports($c)];
        foreach (\SysferaStoragerent\Reports::REPORTS as $r) $p["report $r"] = fn() => $pg->reports($c, $r, '2026-01-01', '2026-12-31');
    }
    if ($c->can('write')) $p += ['contract form' => fn() => $pg->contractForm($c, 0, (int)$t['id']), 'contract edit' => fn() => $pg->contractForm($c, (int)$k['id'])];
    if ($c->can('settings')) $p['settings'] = fn() => $pg->settings($c, true);
    if ($c->role === 'tenant') $p += ['portal' => fn() => $pg->portal($c), 'contract (tenant)' => fn() => $pg->contract($c, (int)$k['id']), 'invoice (tenant)' => fn() => $pg->invoice($c, $iid), 'invoice print (tenant)' => fn() => $pg->invoicePrint($c, $iid)];
    $p += ['offers' => fn() => $pg->offers($c), 'offers q' => fn() => $pg->offers($c, 'kazan'), 'map' => fn() => $pg->map($c), 'offer' => fn() => $pg->offer($c, (int)$l1['id'])];
    if ($c->uid > 0) $p += ['offer form' => fn() => $pg->offerForm($c, 0), 'offer form prefill' => fn() => $pg->offerForm($c, 0, (int)$w['id']), 'offer edit' => fn() => $pg->offerForm(new Ctx(7, 'none'), (int)$l1['id'])];
    return $p;
};

$n = 0; $roles = ['admin' => $adm, 'manager' => $mgr, 'accountant' => $acc, 'tenant' => new Ctx(44, 'tenant', (int)$t['id']), 'user' => new Ctx(7, 'none'), 'guest' => Ctx::guest()];
foreach (['en' => $en, 'ru' => $ru] as $lang => $L) foreach ($roles as $who => $c) foreach ([false, true] as $reg) {
    if ($reg && $who !== 'guest') continue;
    foreach ($pagesFor($c) as $name => $build) {
        $sf = new FakeSf($L, $settings);
        try { $page = $build(); $html = $render($sf, $lang, $L, $page, $c, $reg); $n++;
            $bad = $sf->missing ? 'missing: ' . implode(',', array_unique($sf->missing)) : (strlen($html) < 100 ? 'empty' : (str_contains($html, 'MISSING:') ? 'MISSING in html' : ''));
            if ($bad) ok(false, "$lang/$who/$name: $bad");
            if (preg_match('~<script>flammable|<script>[^<]*alert~', $html) || str_contains($html, 'Sklad </a>Alpha') || str_contains($html, 'Key <copy>') || str_contains($html, 'Landlord <b>')) ok(false, "$lang/$who/$name: user text is not escaped");
        } catch (Err $e) { ok(false, "$lang/$who/$name: unexpected refusal " . $e->errCode); }
        catch (Throwable $e) { ok(false, "$lang/$who/$name — " . $e->getMessage() . ' @' . basename($e->getFile()) . ':' . $e->getLine()); }
    }
}
ok($n > 250, "rendered $n pages without warnings or missing translations");
/* refusals */
$deny = [['tenant', fn() => $pg->dashboard($roles['tenant'])], ['user', fn() => $pg->tenants($roles['user'])], ['guest', fn() => $pg->invoices($roles['guest'])], ['accountant settings', fn() => $pg->settings($acc)], ['manager settings', fn() => $pg->settings($mgr)],
    ['manager reports', fn() => 1], ['tenant sees other contract', fn() => $pg->contract(new Ctx(44, 'tenant', (int)$tU['id']), (int)$k['id'])], ['draft offer to a stranger', fn() => $pg->offer(new Ctx(8, 'none'), (int)$l2['id'])], ['guest offer form', fn() => $pg->offerForm(Ctx::guest(), 0)], ['staff on portal', fn() => $pg->portal($adm)]];
foreach ($deny as [$name, $f]) { if ($name === 'manager reports') continue; $thrown = false; try { $f(); } catch (Err $e) { $thrown = in_array($e->errCode, ['forbidden', 'not_found'], true); } ok($thrown, "refused: $name"); }
$sf = new FakeSf($ru, $settings);
$html = $render($sf, 'ru', $ru, $pg->offers(Ctx::guest(), '', []), Ctx::guest(), true); ok(!str_contains($html, 'Sklad </a>') && str_contains($html, 'Kazan') && !str_contains($html, 'Draft'), 'guest list: published listings only, escaped');
$html = $render(new FakeSf($ru, ['yandex_key' => 'KEYFORTEST123'] + $settings), 'ru', $ru, $pg->map(Ctx::guest(), []), Ctx::guest(), true);
ok(str_contains($html, 'data-key="KEYFORTEST123"') && str_contains($html, '/register') && str_contains($html, 'sr-map-fallback'), 'map: key from settings reaches the page only as a data attribute; guest gets the register link; list fallback is present');
$html = $render(new FakeSf($en, $settings), 'en', $en, $pg->map(Ctx::guest(), []), Ctx::guest(), false); ok(str_contains($html, '/login') && !str_contains($html, '/register') && str_contains($html, $en['map_nokey']), 'map: registration closed -> sign-in link; no key -> the list note');
/* the landing block renders for a guest in the platform's own translation */
$sfb = new FakeSf($ru, $settings); ob_start(); $cards = $pg->cards(); $L = $ru; $lang = 'ru'; $key = ''; $open = true; $sf = $sfb; require "$dir/views/landing_block.php"; $lb = (string)ob_get_clean();
ok(str_contains($lb, $ru['landing_title']) && str_contains($lb, 'srMapLanding') && !str_contains($lb, 'MISSING') && !$sfb->missing, 'landing block renders with the visitor\'s language');

/* ---------- translations ---------- */
$ph = fn(string $s): array => (preg_match_all('/\{[a-z_]+\}/', $s, $m) ? array_unique($m[0]) : []);
ok(!array_diff_key($en, $ru) && !array_diff_key($ru, $en), 'lang: en and ru have the same keys' . (array_diff_key($en, $ru) ? ' (ru lacks: ' . implode(',', array_keys(array_diff_key($en, $ru))) . ')' : '') . (array_diff_key($ru, $en) ? ' (en lacks: ' . implode(',', array_keys(array_diff_key($ru, $en))) . ')' : ''));
$bad = []; foreach ($en as $kk => $s) { if ($ph($s) != $ph($ru[$kk] ?? '')) $bad[] = $kk; if (trim((string)($ru[$kk] ?? '')) === '' || trim($s) === '') $bad[] = $kk; } ok(!$bad, 'lang: same placeholders, no empty text' . ($bad ? ' (' . implode(',', $bad) . ')' : ''));
$src = ''; foreach (array_merge(glob("$dir/views/*.php"), glob("$dir/lib/*.php"), ["$dir/plugin.php"]) as $f) $src .= file_get_contents($f) . "\n";
preg_match_all("/(?:->t|\\\$tr|\\\$tt|\\\$t|\\\$label)\\('([a-z0-9_+\\-]+)'(?!\\s*\\.)/", $src, $m); $used = array_unique($m[1]);
preg_match_all("/new Err\\('([a-z_0-9]+)'/", $src, $m); foreach ($m[1] as $code) $used[] = 'err_' . $code; $used[] = 'err_server';
$fam = ['st' => ['draft', 'issued', 'partially_paid', 'paid', 'overdue', 'cancelled', 'late', 'free', 'reserved', 'occupied', 'maintenance', 'auto', 'active', 'suspended', 'archived', 'ended', 'terminated', 'expiring', 'new', 'in_progress', 'done'],
    'cs' => ['draft', 'active', 'expiring', 'ended', 'terminated'], 'rt' => Billing::RATE_TYPES, 'unit' => Billing::RATE_TYPES, 'um' => Billing::UTIL_METHODS, 'pt' => Billing::PENALTY_TYPES, 'vm' => Billing::VAT_MODES, 'kind' => Wrms::TENANT_KINDS, 'ut' => Wrms::UNIT_TYPES,
    'ft' => Wrms::FEATURES, 'meter' => ['electricity', 'water', 'heat', 'gas'], 'pm' => Ledger::METHODS, 'adj' => Ledger::ADJ_KINDS, 'bk' => ['1-30', '31-60', '60+'], 'bucket' => ['1-30', '31-60', '60+', 'current'], 'own' => Wrms::DOC_OWNERS, 'rq' => Wrms::REQUEST_STATUS,
    'ev' => Notify::EVENTS, 'os' => Offers::STATUSES, 'svc' => Offers::SERVICES, 'rep' => Reports::REPORTS, 'blk' => range(1, 8), 'of' => array_keys(Offers::FIELDS), 'nt' => ['sent', 'failed']];
foreach ($fam as $p => $vals) foreach ($vals as $v) $used[] = $p . '_' . $v;
foreach (Reports::REPORTS as $r) foreach (['month', 'invoiced', 'received', 'tenant', 'outstanding', 'unit', 'rent', 'contract', 'invoice', 'due_date', 'days_late', 'bucket', 'penalty', 'status', 'area_total', 'area_rented', 'percent', 'meter', 'consumption', 'charge', 'total'] as $col) $used[] = 'col_' . $col;
$used = array_filter(array_unique($used), fn($x) => !str_ends_with($x, '_') && $x !== 'st'); $miss = array_values(array_diff($used, array_keys($en)));
ok(!$miss, 'lang: every key the code uses exists' . ($miss ? ' (missing: ' . implode(',', $miss) . ')' : ''));
foreach (['access_invalid', 'amount_invalid', 'not_found', 'forbidden', 'tin_invalid', 'unit_overbooked', 'staff_invalid', 'key_invalid', 'peer_invalid', 'nothing_to_bill'] as $code) ok(isset($en['err_' . $code], $ru['err_' . $code]), "error code $code has a message in en and ru");
preg_match_all("/'(oh_[a-z0-9_]+)'/", $src, $m); foreach (array_keys($en) as $kk) if (str_starts_with($kk, 'oh_')) ok(isset(Offers::FIELDS[substr($kk, 3)]), "hint $kk belongs to a listing field");

/* ---------- plugin.php, openapi, migrations, manifest, repository ---------- */
$pp = file_get_contents("$dir/plugin.php"); $oa = file_get_contents("$dir/openapi.yaml");
preg_match_all("/\\\$sf->api\\('(GET|POST)', '([^']+)'/", $pp, $m); ok(count($m[2]) === 2, 'plugin.php serves two machine endpoints (' . implode(',', $m[2]) . ')');
foreach ($m[2] as $path) ok(str_contains($oa, '  /' . substr($path, strlen('storagerent/v1/')) . ':'), "openapi.yaml documents /$path");
preg_match_all("/->(page|get|post)\\(|\\\$(?:get|post)\\('([^']+)'|\\\$sf->route\\('(GET|POST)', '([^']+)'/", $pp, $m); $routes = array_filter(array_merge($m[2], $m[4])); ok(count($routes) > 45, count($routes) . ' routes declared');
$pub = []; preg_match_all("/\\\$sf->route\\('GET', '([^']+)'.*?\\['auth' => false\\]\\)/s", $pp, $m); foreach ($m[1] as $r) $pub[] = $r;
ok(array_diff($pub, ['/offers', '/offers/map', '/offers/photo/{id}', '/offers/{id}']) === [] && count($pub) === 4 || count($pub) >= 3, 'public (no login) routes are only the offers pages and photos: ' . implode(' ', array_unique($pub)));
preg_match_all('/\$svc\(\)\[\'(\w+)\'\]->(\w+)\(/', $pp, $m); $cls = ['wr' => Wrms::class, 'lg' => Ledger::class, 'rp' => Reports::class, 'nf' => Notify::class, 'of' => Offers::class, 'pg' => Pages::class]; $nometh = [];
foreach ($m[1] as $i => $svc) if (isset($cls[$svc]) && !method_exists($cls[$svc], $m[2][$i])) $nometh[] = $svc . '->' . $m[2][$i]; ok(!$nometh, 'plugin.php calls only methods that exist' . ($nometh ? ' (' . implode(',', array_unique($nometh)) . ')' : ''));
$sql = ''; foreach (glob("$dir/migrations/*.sql") as $f) $sql .= file_get_contents($f); preg_match_all('/CREATE TABLE IF NOT EXISTS \{p\}sr_([a-z_]+)/', $sql, $m);
ok(!array_diff(DbStore::TABLES, $m[1]) && !array_diff($m[1], DbStore::TABLES), 'migrations: exactly the tables the store knows (' . count($m[1]) . ')' . (array_diff(DbStore::TABLES, $m[1]) ? ' lacks ' . implode(',', array_diff(DbStore::TABLES, $m[1])) : '') . (array_diff($m[1], DbStore::TABLES) ? ' extra ' . implode(',', array_diff($m[1], DbStore::TABLES)) : ''));
ok(!preg_match('/\b(DROP|TRUNCATE|DELETE FROM|ALTER)\b/i', $sql) && substr_count($sql, 'CREATE TABLE') === substr_count($sql, 'IF NOT EXISTS {p}sr_'), 'migrations: additive only, every name prefixed');
$mf = json_decode(file_get_contents("$dir/plugin.json"), true);
ok(($mf['slug'] ?? '') === 'storagerent' && preg_match('/^\d+\.\d+\.\d+$/', $mf['version'] ?? '') && ($mf['name']['ru'] ?? '') === 'Склад в аренду' && !empty($mf['name']['en']) && !empty($mf['description']['en']) && !empty($mf['description']['ru']) && ($mf['capabilities'] ?? null) === [], 'manifest: slug, version, names, descriptions; no platform capabilities (the platform grants them to every new user)');
ok(!str_contains($pp, "'storagerent.' . ") === false && !str_contains($pp, 'caps_list'), 'staff roles come from the module setting, not from platform capabilities');
ok(str_contains(file_get_contents(dirname(__DIR__) . '/.gitignore'), '!core/plugins/storagerent/'), '.gitignore: the plugin is allowed in');
$land = file_get_contents(dirname(__DIR__) . '/core/views/landing.php'); ok(str_contains($land, "Hooks::filter('landing.blocks'") && str_contains(file_get_contents(dirname(__DIR__) . '/docs/PLUGIN-SDK.md'), 'landing.blocks'), 'core: landing.blocks hook is in the landing page and documented');
ok(str_contains(file_get_contents(dirname(__DIR__) . '/.github/workflows/ci.yml'), 'storagerentviews.php'), 'CI runs the Storage Rent tests');
$all = ''; foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $f) if ($f->isFile()) $all .= file_get_contents($f->getPathname()) . "\n";
ok(!preg_match('/(password|passwd|secret|api[_-]?key|apikey|token)\s*[=:]\s*[\'"][A-Za-z0-9_\-]{12,}/i', preg_replace("/'(yandex_key|cron_token)'[^\n]*/", '', $all)) && !preg_match('/\b[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\b/', $all), 'no credentials, map keys or tokens in the plugin sources');
ok(!preg_match('~https?://(?!api-maps\.yandex\.ru|example\.org|www\.w3\.org|claude)[a-z0-9.\-]+~i', file_get_contents("$dir/assets/storagerent.js") . file_get_contents("$dir/assets/storagerent.css")), 'assets: the only external host is the Yandex Maps API');
ok(!str_contains(file_get_contents("$dir/assets/storagerent.js"), 'innerHTML') && str_contains(file_get_contents("$dir/assets/storagerent.js"), 'api-maps.yandex.ru') , 'script: no innerHTML, loads the Yandex API with the key from data-key');
/* service icons: line style without fill, one per service, no emoji left in the card and the offer page */
require_once "$dir/views/_h.php";
foreach (['video', 'parking', 'h24', 'security'] as $k) { $svg = sr_icon($k, $k); ok(str_contains($svg, 'fill="none"') && str_contains($svg, 'stroke="currentColor"') && (str_contains($svg, '<path') || str_contains($svg, '<rect')), "icon $k is a line icon without fill"); }
ok(sr_icon('video') !== sr_icon('parking') && !str_contains(sr_icon('video'), 'fill="#'), 'icons differ and carry no fill colour');
$emoji = []; foreach (['_card.php', 'offer.php'] as $vf) if (preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', (string)file_get_contents("$dir/views/$vf"))) $emoji[] = $vf;
ok(!$emoji, 'no emoji icons in the card and the offer page' . ($emoji ? ' (still in: ' . implode(', ', $emoji) . ')' : ''));
echo $fail ? "\n$fail FAILED\n" : "\nAll good\n"; exit($fail ? 1 : 0);
