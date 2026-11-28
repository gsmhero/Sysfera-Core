<?php
declare(strict_types=1);
/** Interface sets: the rules, the menu they filter and the three places that show them. No database. Run: php tests/interfacesets.php */
if (!function_exists('mb_strtolower')) { function mb_strtolower(string $s): string { return strtolower($s); } }
if (!function_exists('mb_strtoupper')) { function mb_strtoupper(string $s): string { return strtoupper($s); } }
if (!function_exists('mb_substr')) { function mb_substr(string $s, int $a = 0, ?int $l = null): string { return $l === null ? substr($s, $a) : substr($s, $a, $l); } }
require dirname(__DIR__) . '/core/app/bootstrap.php';
require __DIR__ . '/InterfaceSetsMemStore.php';

use Sysfera\{App, Auth, Db, I18n, InterfaceSets, View};

set_error_handler(function (int $no, string $msg, string $file, int $line): bool { if (!(error_reporting() & $no)) return false; throw new ErrorException("$msg in " . basename($file) . ":$line"); });
$fail = 0;
function ok(bool $cond, string $name): void { global $fail; echo ($cond ? '  ok   ' : '  FAIL ') . $name . "\n"; if (!$cond) $fail++; }

$ui = new InterfaceSets(new InterfaceSetsMemStore());
$allowed = ['crm', 'wms', 'messenger'];

/* create, rename, delete */
$a = $ui->create(1, '  Office   work ');
ok(is_int($a) && $ui->find(1, $a)['name'] === 'Office work' && $ui->find(1, $a)['items'] === [] && $ui->active(1) === null, 'create: name is trimmed, the set starts empty and is not switched on');
ok($ui->create(1, '   ') === 'ui.need_name', 'create: a name is required');
ok($ui->create(1, 'OFFICE WORK') === 'ui.name_taken', 'create: names are unique for a user');
$other = $ui->create(2, 'Office work');
ok(is_int($other), 'create: another user may use the same name');
$long = $ui->create(1, str_repeat('x', 200));
ok(strlen($ui->find(1, $long)['name']) === InterfaceSets::MAX_NAME, 'create: a long name is cut');
ok($ui->save(1, $a, 'Office', ['crm', 'wms', 'evil', ['x']], $allowed) === null && $ui->find(1, $a)['name'] === 'Office' && $ui->find(1, $a)['items'] === ['crm', 'wms'], 'save: rename and ticks; unknown components are ignored');
ok($ui->save(1, $a, '', [], $allowed) === 'ui.need_name' && $ui->find(1, $a)['items'] === ['crm', 'wms'], 'save: a refused save changes nothing');
$ui->save(1, $a, 'Office', ['crm', 'wms'], $allowed);
$ui->save(1, $a, 'Office', ['messenger'], ['crm', 'messenger']);
ok($ui->find(1, $a)['items'] === ['wms', 'messenger'], 'save: the tick of a component the user cannot see now is kept');

/* another user's sets cannot be touched */
ok($ui->save(1, $other, 'Hacked', [], $allowed) === 'ui.not_found', 'ownership: no save');
$ui->delete(1, $other); $ui->activate(1, $other);
ok($ui->find(2, $other)['name'] === 'Office work' && $ui->active(2) === null && $ui->active(1) === null, 'ownership: no delete, no switch');

/* switch */
$b = $ui->create(1, 'Shop');
$ui->activate(1, $a); $ui->activate(1, $b);
ok($ui->active(1)['id'] === $b && count(array_filter($ui->all(1), fn($s) => $s['active'])) === 1, 'switch: one set at a time');
$ui->activate(1, 0);
ok($ui->active(1) === null, 'switch: back to all components');

/* the checkboxes under a component */
$ui->assign(1, 'crm', [(string)$a, (string)$b, (string)$other]);
ok(in_array('crm', $ui->find(1, $a)['items'], true) && $ui->find(1, $b)['items'] === ['crm'] && $ui->find(2, $other)['items'] === [], 'assign: ticked sets of this user only');
$ui->assign(1, 'crm', [(string)$b]);
ok($ui->find(1, $a)['items'] === ['wms', 'messenger'] && $ui->find(1, $b)['items'] === ['crm'], 'assign: unticked sets lose the component, the rest stays');

/* form posts */
$r = $ui->handle(1, ['do' => 'create', 'name' => 'New'], $allowed); $n = $ui->all(1)[count($ui->all(1)) - 1]['id'];
ok($r === ["/profile?set=$n#interface-sets", 'ui.created', 'ok'], 'post create: opens the new set for ticking');
ok($ui->handle(1, ['do' => 'create', 'name' => ['x']], $allowed)[1] === 'ui.need_name', 'post create: odd input is refused');
ok($ui->handle(1, ['do' => 'save', 'id' => (string)$n, 'name' => 'New', 'items' => ['wms']], $allowed) === ['/profile#interface-sets', 'ui.saved', 'ok'] && $ui->find(1, $n)['items'] === ['wms'], 'post save');
ok($ui->handle(1, ['do' => 'switch', 'set' => (string)$n], $allowed) === ['/', null, 'ok'] && $ui->active(1)['id'] === $n, 'post switch: back to the dashboard');
ok($ui->handle(1, ['do' => 'assign', 'slug' => 'messenger', 'sets' => [(string)$n]], $allowed)[0] === '/plugins#c-messenger' && $ui->find(1, $n)['items'] === ['wms', 'messenger'], 'post assign');
ok($ui->handle(1, ['do' => 'assign', 'slug' => '"><x', 'sets' => [(string)$n]], $allowed) === ['/plugins', 'ui.not_found', 'bad'], 'post assign: only components the user has');
$ui->handle(1, ['do' => 'delete', 'id' => (string)$n], $allowed);
ok($ui->find(1, $n) === null && $ui->active(1) === null, 'post delete: removing the chosen set shows everything again');
for ($i = 0; $i < InterfaceSets::MAX_SETS + 2; $i++) $last = $ui->create(3, "s$i");
ok($last === 'ui.too_many' && count($ui->all(3)) === InterfaceSets::MAX_SETS, 'limit of sets');
$ui->deleteUser(3);
ok($ui->all(3) === [] && $ui->all(1) !== [], 'a deleted user loses only their own sets');

/* the menu panel, through App::menu() */
$auth = new Auth(new Db([]));
$set = function (object $o, string $prop, mixed $v): void { (new ReflectionProperty($o, $prop))->setValue($o, $v); };
$signIn = function (array $u) use ($set, $auth): void { $set($auth, 'loaded', true); $set($auth, 'user', $u); };
$app = (new ReflectionClass(App::class))->newInstanceWithoutConstructor();
$store = new InterfaceSetsMemStore(); $sets = new InterfaceSets($store);
$set($app, 'auth', $auth); $set($app, 'uiSets', $sets); $set($app, 'plugins', new \Sysfera\Plugins(new Db([]), $app));
$app->addMenu(['label' => 'nav.dashboard', 'path' => '/', 'order' => 0]);
foreach (['/plugins', '/users', '/task-types', '/settings', '/upgrade'] as $p) $app->addMenu(['label' => "core$p", 'path' => $p, 'cap' => '__admin__']);
$counted = 0;
$app->addMenu(['label' => 'menu', 'slug' => 'crm', 'path' => '/p/crm/', 'cap' => 'crm.use']);
$app->addMenu(['label' => 'menu', 'slug' => 'wms', 'path' => '/p/wms/', 'cap' => 'wms.use', 'badge' => function () use (&$counted) { $counted++; return 3; }]);
$app->addMenu(['label' => 'menu', 'slug' => 'messenger', 'path' => '/p/messenger/']);
$paths = fn(): array => array_column($app->menu(), 'path');
$core = ['/', '/plugins', '/users', '/task-types', '/settings', '/upgrade'];

$signIn(['id' => 7, 'role' => 'admin', 'caps_list' => []]);
$everything = $paths();
ok(count($everything) === 9, 'menu: no sets, everything as before');
$s = $sets->create(7, 'Empty');
ok($paths() === $everything, 'menu: a set that is not switched on changes nothing');
$sets->activate(7, $s); $counted = 0;
$left = $paths(); sort($left); $want = $core; sort($want);
ok($left === $want && $counted === 0, 'menu: an empty set keeps the Dashboard and administration, hidden counters are not computed');
$sets->save(7, $s, 'Empty', ['messenger'], ['crm', 'wms', 'messenger']);
ok(in_array('/p/messenger/', $paths(), true) && !in_array('/p/crm/', $paths(), true) && !in_array('/p/wms/', $paths(), true), 'menu: only ticked components');
$sets->activate(7, 0);
ok($paths() === $everything, 'menu: "all components" shows everything again');

$signIn(['id' => 8, 'role' => 'user', 'caps_list' => ['crm.use']]);
ok(array_keys($app->menuComponents()) === ['crm', 'messenger'], 'user: only components with access can be ticked');
$s = $sets->create(8, 'Mine');
$sets->handle(8, ['do' => 'save', 'id' => (string)$s, 'name' => 'Mine', 'items' => ['crm', 'wms']], array_keys($app->menuComponents()));
$sets->activate(8, $s);
ok($paths() === ['/', '/p/crm/'] && $sets->find(8, $s)['items'] === ['crm'], 'user: a set never gives access');
$names = $app->menuComponents();
ok(($names['messenger'] ?? 'messenger') !== 'messenger', 'user: a hidden component can still be ticked');

$broken = new InterfaceSets(new class implements \Sysfera\SetStore {
    public function all(int $user): array { throw new RuntimeException('no table'); }
    public function create(int $user, string $name): int { return 0; }
    public function update(int $user, int $id, string $name, array $items): void {}
    public function delete(int $user, int $id): void {}
    public function activate(int $user, int $id): void {}
    public function deleteUser(int $user): void {}
});
$set($app, 'uiSets', $broken);
$errLog = SF_ROOT . '/storage/error.log'; $before = is_file($errLog) ? file_get_contents($errLog) : null;
ok($paths() === ['/', '/p/crm/', '/p/messenger/'], 'menu: a storage problem never breaks the menu');
$before === null ? @unlink($errLog) : file_put_contents($errLog, $before);

/* Profile, Dashboard and Components in both languages */
$list = [['id' => 1, 'name' => 'Set "one" <b>', 'items' => ['crm'], 'active' => true], ['id' => 2, 'name' => 'Set two', 'items' => [], 'active' => false]];
foreach (['en', 'ru'] as $l) {
    I18n::setLang($l);
    $block = fn(?array $edit) => View::file(SF_ROOT . '/views/_interface_sets.php', ['sets' => $list, 'edit' => $edit, 'components' => $edit ? ['crm' => 'CRM', 'wms' => 'Warehouse'] : []]);
    $h = $block(null);
    ok(str_contains($h, 'value="create"') && str_contains($h, '?set=2#interface-sets') && substr_count($h, 'value="delete"') === 2 && !str_contains($h, '<b></b>') && !str_contains($h, 'Set "one" <b>') && !preg_match('/>ui\./', $h), "$l: profile list with Edit and Delete, names escaped");
    $h = $block($list[0]);
    ok(str_contains($h, 'value="save"') && preg_match('/value="crm" checked/', $h) === 1 && preg_match('/value="wms">/', $h) === 1 && !preg_match('/>ui\./', $h), "$l: profile edit view with ticks");
    $dash = fn(array $s) => View::file(SF_ROOT . '/views/dashboard.php', ['counts' => ['contacts' => 0, 'orders' => 0, 'invoices' => 0], 'plugins' => [1], 'widgets' => [], 'isAdmin' => false, 'sets' => $s]);
    $h = $dash($list);
    ok(str_contains($h, 'data-autosubmit') && str_contains($h, '<option value="0">' . e(t('ui.all'))) && str_contains($h, '<option value="1" selected>') && t('ui.all') !== 'ui.all', "$l: dashboard switch");
    ok(!str_contains($dash([]), 'ui-switch'), "$l: no switch without sets");
    $m = ['slug' => 'crm', 'name' => 'CRM', 'description' => 'd', 'version' => '1.0.0'];
    $page = fn(array $s) => View::file(SF_ROOT . '/views/plugins.php', ['available' => ['crm' => $m, 'x' => ['slug' => 'x'] + $m], 'installed' => ['crm' => ['enabled' => 1, 'version' => '1.0.0'], 'x' => ['enabled' => 0, 'version' => '1.0.0']], 'remote' => [], 'updates' => [], 'problems' => fn() => [], 'sets' => $s, 'inMenu' => ['crm' => 'CRM']]);
    $h = $page($list);
    ok(substr_count($h, 'value="assign"') === 1 && substr_count($h, 'name="sets[]"') === 2 && preg_match('/value="1" checked/', $h) === 1, "$l: components page checkboxes, only for components in the menu");
    ok(!str_contains($page([]), 'ui-assign'), "$l: components page unchanged without sets");
}

/* texts, migration */
$en = require SF_ROOT . '/lang/en.php'; $ru = require SF_ROOT . '/lang/ru.php';
$k = fn(array $d) => array_values(array_filter(array_keys($d), fn($x) => str_starts_with($x, 'ui.')));
ok($k($en) !== [] && $k($en) === $k($ru), 'texts: the same keys in en and ru');
$src = file_get_contents(SF_ROOT . '/app/InterfaceSets.php'); preg_match_all("/'(ui\.[a-z_]+)'/", $src, $mm);
ok($mm[1] !== [] && !array_diff($mm[1], array_keys($en)), 'texts: every message of the rules exists');
$sql = (string)file_get_contents(SF_ROOT . '/app/migrations/006_interface_sets.sql');
ok(str_contains($sql, 'CREATE TABLE IF NOT EXISTS {p}ui_sets') && count(array_filter(array_map('trim', preg_split('/;\s*\n/', str_replace("\r\n", "\n", $sql) . "\n")))) === 1, 'migration: one additive statement with the table prefix');

echo $fail ? "\n$fail FAILED\n" : "\nAll good\n";
exit($fail ? 1 : 0);
