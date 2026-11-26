<?php
declare(strict_types=1);
/** Renders every My Blog page (en and ru); a PHP warning, a missing translation or unescaped user text fails. Also: language parity, error keys, migrations, manifest, .gitignore. Run: php tests/myblogviews.php */
require dirname(__DIR__) . '/core/app/bootstrap.php';
$dir = SF_ROOT . '/plugins/myblog';
foreach (['Text', 'Store', 'Posts', 'Network', 'Profile'] as $f) require "$dir/lib/$f.php";
require __DIR__ . '/MyblogMemStore.php';
use SysferaBlog\{Posts, Text};
use Sysfera\View;

$fail = 0;
function ok(bool $c, string $n): void { global $fail; echo ($c ? '  ok   ' : '  FAIL ') . $n . "\n"; if (!$c) $fail++; }
set_error_handler(function (int $no, string $msg, string $file, int $line): bool { throw new ErrorException("$msg in " . basename($file) . ":$line"); });
$en = require "$dir/lang/en.php"; $ru = require "$dir/lang/ru.php";
final class FakeSf {
    public array $manifest = ['version' => '0.1.0']; public array $missing = [];
    public function __construct(private array $l) {}
    public function t(string $k, array $v = []): string { if (!isset($this->l[$k])) { $this->missing[] = $k; return 'MISSING:' . $k; } $s = $this->l[$k]; foreach ($v as $a => $b) $s = str_replace('{' . $a . '}', (string)$b, $s); return $s; }
    public function url(string $p = ''): string { return '/p/myblog' . ($p === '' ? '' : '/' . ltrim($p, '/')); }
    public function asset(string $f): string { return '/assets/p/myblog/' . $f; }
}
if (PHP_SAPI === 'cli') $_SESSION = ['csrf' => 'tok'];

$st = new MyblogMemStore(); $posts = new Posts($st, fn() => 1_790_000_000);
$id = $posts->save(1, false, 0, ['title' => 'Title <script>x</script>', 'body' => "Body <img src=x onerror=1>\nline", 'products' => [5]], [5], str_repeat('a', 32) . '.png');
$d = $posts->save(1, false, 0, ['title' => 'Draft', 'status' => 'draft'], []);
$l = $posts->save(1, false, 0, ['title' => 'Local', 'scope' => 'local'], []);
$cards = array_map(fn($p) => Posts::card($p, fn($u) => $u), $st->ownerPosts(1));
$remote = SysferaBlog\Network::clean(['title' => 'Far <b>', 'url' => 'https://far.ru/p/myblog/post/1', 'author' => 'A<', 'author_url' => 'https://far.ru/p/myblog/u/1', 'domain' => 'far.ru', 'excerpt' => '"q"', 'cover' => 'https://far.ru/p/myblog/cover/x.png', 'date' => '2026-01-01', 'products' => 2, 'id' => 1, 'uid' => 1]);
$product = ['bb_id' => 5, 'owner_id' => 1, 'title' => 'Mask "A" <i>', 'price' => 1450.0, 'image' => 'https://a/1.jpg', 'url' => 'https://www.basebase.ru/product/5', 'category' => 'Skin', 'sku' => 'M1'];
$page = fn($items, $pages = 1) => ['items' => $items, 'total' => count($items), 'page' => 1, 'pages' => $pages];
$p = $st->post($id) + []; $p['bb_url'] = 'https://www.basebase.ru/blog/1';

$bad = []; $all = '';
foreach (['en' => $en, 'ru' => $ru] as $lang => $L) {
    $sf = new FakeSf($L);
    $prof = fn(array $v): array => $v + ['uid' => 1, 'name' => 'N<', 'shopName' => 'S<', 'about' => "About <i>\nline", 'tab' => 'posts', 'postCount' => 2, 'productCount' => 1, 'd' => $page([]), 'items' => [], 'f' => SysferaBlog\Profile::filters([]), 'cats' => [], 'page' => 1, 'pages' => 1, 'sets' => null, 'own' => false];
    $start = fn(array $v): array => $v + ['d' => $page($cards, 2), 'q' => '', 'scope' => 'global', 'offline' => false, 'people' => [], 'products' => []];
    $cases = [
        'start' => ['start', $start([])], 'start search' => ['start', $start(['q' => '"x<', 'offline' => true, 'people' => [['uid' => 1, 'name' => 'S<', 'products' => 2, 'posts' => 1]], 'products' => [$product]])], 'start nothing' => ['start', $start(['d' => $page([]), 'q' => 'zz', 'scope' => 'local'])],
        'index mine' => ['index', ['d' => $page($cards), 'f' => 'mine', 'q' => '"x', 'offline' => false, 'me' => 1, 'shop' => ['shop_name' => 'S<'], 'state' => 'unsupported', 'hubMod' => true]],
        'index all' => ['index', ['d' => $page([$remote], 3), 'f' => 'all', 'q' => '', 'offline' => true, 'me' => 1, 'shop' => null, 'state' => 'error', 'hubMod' => false]],
        'index empty' => ['index', ['d' => $page([]), 'f' => 'site', 'q' => '', 'offline' => false, 'me' => 2, 'shop' => null, 'state' => '', 'hubMod' => false]],
        'edit new' => ['edit', ['p' => null, 'form' => null, 'products' => [], 'shop' => null]],
        'edit' => ['edit', ['p' => $p, 'form' => null, 'products' => [$product], 'shop' => ['shop_name' => 'S']]],
        'edit form' => ['edit', ['p' => null, 'form' => ['id' => 0, 'title' => 'T<', 'products' => ['5']], 'products' => [$product], 'shop' => null]],
        'products' => ['products', ['products' => [$product], 'shop' => ['shop_name' => 'S']]], 'products none' => ['products', ['products' => [], 'shop' => null]],
        'post mine' => ['post', ['p' => $p, 'card' => Posts::card($p, fn($u) => $u), 'attached' => [$product], 'mine' => true]],
        'post draft' => ['post', ['p' => $st->post($d), 'card' => Posts::card($st->post($d), fn($u) => $u), 'attached' => [], 'mine' => false]],
        'widget' => ['widget', ['items' => [$remote, $cards[0]], 'shared' => true]], 'widget local' => ['widget', ['items' => [], 'shared' => false]],
        'profile card' => ['profile_card', ['uid' => 1, 'about' => 'About <i>']],
        'profile posts' => ['profile', $prof(['tab' => 'posts', 'd' => $page($cards, 2), 'pages' => 2])], 'profile posts empty' => ['profile', $prof(['tab' => 'posts', 'd' => $page([]), 'about' => ''])],
        'profile products' => ['profile', $prof(['tab' => 'products', 'items' => [$product], 'cats' => ['Skin' => 1, 'C<' => 2], 'f' => SysferaBlog\Profile::filters(['q' => '"x<', 'cat' => 'Skin', 'min' => '1']), 'page' => 2, 'pages' => 3])],
        'profile products own' => ['profile', $prof(['tab' => 'products', 'items' => [$product], 'own' => true])], 'profile products none' => ['profile', $prof(['tab' => 'products', 'productCount' => 0])],
        'profile sets' => ['profile', $prof(['tab' => 'sets', 'sets' => [['id' => 1, 'title' => 'Set <i>', 'items' => 2, 'sum' => 99.0, 'url' => 'https://www.basebase.ru/set/1']]])], 'profile sets none' => ['profile', $prof(['tab' => 'sets'])],
'notice' => ['notice', ['msg' => 'e_no_post']],
        'moderate' => ['moderate', ['rows' => [['id' => 1, 'title' => 'x<', 'excerpt' => 'y', 'author' => 'a', 'domain' => 'd', 'created' => '2026-01-01 00:00:00', 'hidden' => 0], ['id' => 2, 'title' => 'z', 'excerpt' => '', 'author' => '', 'domain' => 'd', 'created' => '2026-01-01', 'hidden' => 1]]]], 'moderate none' => ['moderate', ['rows' => []]],
    ];
    foreach ($cases as $name => [$view, $vars]) {
        try { $html = View::file("$dir/views/$view.php", $vars + ['sf' => $sf]); } catch (Throwable $e) { $bad[] = "$lang $name: " . $e->getMessage(); continue; }
        $all .= $html;
        if (preg_match('~<script>x</script>|<img src=x|<i>|S<[^/]|T<[^/]~', $html)) $bad[] = "$lang $name: unescaped text";
        if ($name === 'profile products' && str_contains($html, 'data-mb-copy')) $bad[] = "$lang $name: a visitor gets the owner's button";
        if ($name === 'profile products own' && !str_contains($html, e(Text::embedCode($product['url'])))) $bad[] = "$lang $name: embed code missing";
        if ($view === 'products' && $vars['products'] && !str_contains($html, e(Text::embedCode($product['url'])))) $bad[] = "$lang $name: embed code missing";
    }
    if ($sf->missing) $bad[] = "$lang missing keys: " . implode(', ', array_unique($sf->missing));
}
ok(!$bad, 'every page renders in en and ru, user text is escaped' . ($bad ? "\n    " . implode("\n    ", $bad) : ''));
ok(str_contains($all, 'name="_t"') && !str_contains($all, 'MISSING:'), 'forms carry the CSRF token');

ok(array_keys($en) === array_keys($ru) || (!array_diff_key($en, $ru) && !array_diff_key($ru, $en)), 'en and ru have the same keys');
$ph = []; foreach ($en as $k => $v) { preg_match_all('/\{[a-z]+\}/', $v, $a); preg_match_all('/\{[a-z]+\}/', $ru[$k] ?? '', $b); sort($a[0]); sort($b[0]); if ($a[0] !== $b[0]) $ph[] = $k; }
ok(!$ph, 'placeholders match ' . implode(' ', $ph));
$src = ''; foreach (array_merge(glob("$dir/*.php"), glob("$dir/lib/*.php"), glob("$dir/views/*.php")) as $f) $src .= file_get_contents($f);
preg_match_all("/(?:->t|\\\$err)\('([a-z_0-9]+)'[),]/", $src, $m);
preg_match_all("/BlogError\('([a-z_]+)'\)/", $src, $errs);
$used = array_unique(array_merge($m[1], array_map(fn($k) => "e_$k", $errs[1]), ['f_all', 'f_site', 'f_mine', 'hide', 'show', 'save', 'publish', 'edit_post', 'new_post', 'saved', 'saved_draft', 'w_title', 'w_title_local', 'none', 'none_mine', 'none_found', 'no_products', 'no_shop_products', 'tab_posts', 'tab_products', 'tab_sets', 'no_products_public']));
$miss = array_filter($used, fn($k) => !isset($en[$k]) && $k !== 'e_' && !str_ends_with($k, '_'));
ok(!$miss, 'every key the code uses exists' . ($miss ? ' (missing ' . implode(', ', $miss) . ')' : ''));

$sql = (string)file_get_contents("$dir/migrations/001_myblog.sql");
preg_match_all('/CREATE TABLE IF NOT EXISTS (\S+)/', $sql, $tb);
ok($tb[1] === ['{p}myblog_posts', '{p}myblog_hub'], 'migration creates the two tables');
$cols = (new ReflectionClass(SysferaBlog\DbStore::class))->getConstants();
ok(!array_filter(array_merge($cols['COLS'], $cols['HUB']), fn($c) => !preg_match('/^\s+' . $c . '\s/m', $sql)), 'every column the store writes exists');
ok(\Sysfera\Plugins::readManifest("$dir/plugin.json") !== null, 'manifest valid');
ok(str_contains((string)file_get_contents(dirname(__DIR__) . '/.gitignore'), '!core/plugins/myblog/'), '.gitignore lets the plugin in');
ok(is_file(SF_ROOT . '/views/public.php'), 'public page frame exists');

echo $fail ? "\n$fail FAILED\n" : "\nAll good\n";
exit($fail ? 1 : 0);
