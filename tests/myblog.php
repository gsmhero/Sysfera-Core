<?php
declare(strict_types=1);
/** My Blog: posts, permissions, the shared feed (hub), sending to the hub, synchronisation with Basebase, product embed codes. No database. Run: php tests/myblog.php */
$root = dirname(__DIR__) . '/core/plugins';
foreach (['Text', 'Media', 'Store', 'Posts', 'Network', 'BbSync'] as $f) require "$root/myblog/lib/$f.php";
require "$root/wms-basebase/lib/Bb.php"; require "$root/wms-basebase/lib/Shop.php";
require __DIR__ . '/MyblogMemStore.php';
use SysferaBlog\{BbSync, BlogError, BlogHub, Media, Network, Posts, Text};

$fail = 0;
function ok(bool $c, string $n): void { global $fail; echo ($c ? '  ok   ' : '  FAIL ') . $n . "\n"; if (!$c) $fail++; }
function err(callable $f): string { try { $f(); } catch (BlogError $e) { return $e->key; } return ''; }

/* text */
ok(Text::excerpt("Привет   мир\n\nснова") === 'Привет мир снова' && Text::len(Text::excerpt(str_repeat('слово ', 100), 50)) <= 50 && str_ends_with(Text::excerpt(str_repeat('слово ', 100), 50), 'слово…'), 'excerpt cuts whole words, in characters');
ok(Text::coverName(str_repeat('a', 32) . '.jpg') && !Text::coverName('../x.jpg') && !Text::coverName(str_repeat('a', 32) . ".jpg\n") && !Text::coverName(str_repeat('a', 32) . '.php'), 'cover names');
ok(Text::basebaseUrl('https://www.basebase.ru/uploads/a.jpg') && !Text::basebaseUrl('https://basebase.ru.evil.com/a.jpg') && !Text::basebaseUrl('http://basebase.ru/a.jpg') && !Text::basebaseUrl('https://basebase.ru/a"><script>'), 'only basebase.ru pictures are foreign covers');
ok(Text::imageExt("\x89PNG\r\n\x1a\n") === 'png' && Text::imageExt('<svg onload=1>') === '' && Text::imageExt('<?php') === '', 'picture type by content');
ok(Text::embedCode('https://www.basebase.ru/product/15') === '<iframe src="https://www.basebase.ru/embed/product/15" width="100%" height="260" style="border:0;max-width:720px" loading="lazy" title="basebase.ru"></iframe><script async src="https://www.basebase.ru/assets/embed.js"></script>', 'embed code is the one basebase.ru copies');
ok(Text::embedCode('javascript:alert(1)') === '' && Text::embedCode('https://www.basebase.ru/shop/x') === '', 'no embed code without a product address');
ok(Text::ids('[3,"4",3,-1,"x"]') === [3, 4] && Text::ids(null) === [], 'product ids');

/* the Basebase connector's product view */
[$w, $a] = \SysferaBb\Shop::where(['owner' => 7, 'q' => '50%']);
ok(str_contains($w, 'm.published = 1') && str_contains($w, 'p.owner_id = ?') && $a === [7, '%50\\%%', '%50\\%%'], 'shop product filter');
$x = \SysferaBb\Shop::product(['bb_url' => 'https://www.basebase.ru/product/9', 'images' => '', 'p_images' => "https://a/1.jpg\nhttps://a/2.jpg", 'owner_id' => 7, 'name' => 'Cup', 'sell_price' => '250.00', 'category' => 'Home', 'sku' => 'C1']);
ok($x['bb_id'] === 9 && $x['image'] === 'https://a/1.jpg' && $x['price'] === 250.0, 'shop product');

/* posts */
$t = 1_790_000_000; $now = function () use (&$t) { return $t; };
$st = new MyblogMemStore(); $posts = new Posts($st, $now);
ok(err(fn() => $posts->save(1, false, 0, ['title' => '  '], [])) === 'need_title' && err(fn() => $posts->save(1, false, 0, ['title' => str_repeat('я', 256)], [])) === 'long_title' && err(fn() => $posts->save(1, false, 0, ['title' => 'a', 'body' => str_repeat('b', 20001)], [])) === 'long_body', 'validation');
$p1 = $posts->save(1, false, 0, ['title' => 'First', 'body' => "Text\r\nmore", 'products' => ['5', '6', '99'], 'scope' => 'everything', 'status' => 'x'], [5, 6]);
$r = $st->post($p1);
ok($r['products'] === '[5,6]' && $r['scope'] === 'global' && $r['status'] === 'published' && $r['body'] === "Text\nmore" && $r['hub_dirty'] === 1 && $r['bb_dirty'] === 1, 'a post: only own products, safe defaults, waits to be sent');
ok(err(fn() => $posts->save(2, false, $p1, ['title' => 'hack'], [])) === 'no_post' && err(fn() => $posts->delete(2, false, $p1)) === 'no_post', 'another user cannot change or delete it');
$posts->save(1, false, $p1, ['title' => 'First', 'products' => [5]], []);
ok($st->post($p1)['products'] === '[5]', 'a product already attached stays allowed');
$draft = $posts->save(1, false, 0, ['title' => 'Secret', 'status' => 'draft'], []);
$local = $posts->save(2, false, 0, ['title' => 'Only here', 'scope' => 'local'], []);
ok(Posts::readable($st->post($p1), 0, false) && !Posts::readable($st->post($draft), 0, false) && !Posts::readable($st->post($draft), 2, false) && Posts::readable($st->post($draft), 1, false) && Posts::readable($st->post($draft), 2, true) && !Posts::readable(null, 1, true), 'guests read published posts only');
$st->users[2]['status'] = 'blocked';
ok(!Posts::readable($st->post($local), 0, false) && $st->publicPosts(null, '', 10, 0)[1] === 1, 'a blocked author disappears from public pages');
$st->users[2]['status'] = 'active';
$card = Posts::card($st->post($p1), fn($u) => '/sf' . $u);
ok($card['url'] === "/sf/p/myblog/post/$p1" && $card['author_url'] === '/sf/p/myblog/u/1' && $card['cover'] === '', 'card links');

/* hub */
$hubSt = new MyblogMemStore(); $hubSt->bases = ['shop.ru' => 'https://shop.ru/sf'];
$hub = new BlogHub($hubSt, $now);
$hub->publish('shop.ru', 4, ['post_id' => 1, 'title' => 'Hello', 'excerpt' => 'x', 'author' => 'A', 'cover' => 'https://evil.com/x.jpg', 'created' => '2099-01-01T00:00:00Z', 'url' => 'https://evil.com']);
$hub->publish('new.ru', 1, ['post_id' => 2, 'title' => 'Second', 'cover' => str_repeat('b', 32) . '.png', 'created' => date('c', $t - 100), 'shop' => 'Shop N']);
$f = $hub->feed([], fn($d) => "https://$d");
$i = array_column($f['items'], null, 'title');
ok($f['total'] === 2 && $i['Hello']['url'] === 'https://shop.ru/sf/p/myblog/post/1' && $i['Hello']['author_url'] === 'https://shop.ru/sf/p/myblog/u/4' && $i['Hello']['cover'] === '', 'the hub builds every link itself and drops foreign pictures');
ok($i['Second']['cover'] === 'https://new.ru/p/myblog/cover/' . str_repeat('b', 32) . '.png' && $i['Second']['author'] === 'Shop N', 'cover and shop name');
ok($hubSt->hub['shop.ru#1']['created'] === date('Y-m-d H:i:s', $t), 'a date in the future is clamped');
$hub->remove('new.ru', 9, 2);
ok(count($hubSt->hub) === 2, 'only the author removes a post');
ok($hub->feed(['q' => 'second'], fn($d) => "https://$d")['total'] === 1, 'search');
$hubSt->hubHide($hubSt->hub['shop.ru#1']['id'], true); $hub->remove('shop.ru', 4, 1); $hub->publish('shop.ru', 4, ['post_id' => 1, 'title' => 'Again']);
ok($hub->feed([], fn($d) => "https://$d")['total'] === 1, 'publishing again does not undo the moderator');
ok(err(fn() => $hub->publish('shop.ru', 0, ['post_id' => 3, 'title' => 'x'])) === 'bad_post' && err(fn() => $hub->publish('shop.ru', 4, ['post_id' => 3, 'title' => ['a']])) === 'bad_post', 'bad posts refused');
for ($n = 10; $n < 30; $n++) $hub->publish('shop.ru', 8, ['post_id' => $n, 'title' => "p$n"]);
ok(err(fn() => $hub->publish('shop.ru', 8, ['post_id' => 40, 'title' => 'x'])) === 'post_rate' && err(fn() => $hub->publish('shop.ru', 8, ['post_id' => 10, 'title' => 'edit'])) === '', 'daily limit counts new posts only');

/* sending to the hub */
$calls = []; $answer = fn() => ['ok' => true, 'data' => [], 'error' => null];
$net = new Network($st, function (...$a) use (&$calls, &$answer) { $calls[] = $a; return $answer(...$a); });
ok($net->push(fn($u) => $u === 1 ? 'Olga shop' : '') === 3, 'all waiting posts are sent');
$by = []; foreach ($calls as $c) $by[$c[3]['post_id']] = $c;
ok($by[$p1][1] === 'blog.publish' && $by[$p1][4] === '1' && $by[$p1][3]['shop'] === 'Olga shop' && $by[$draft][1] === 'blog.remove' && $by[$local][1] === 'blog.remove' && !isset($by[$draft][3]['title']), 'drafts and local posts never leave the site');
ok($st->hubDirty(10) === [], 'nothing waits afterwards');
$posts->delete(1, false, $p1);
ok($st->post($p1) && $st->post($p1)['deleted'] === 1, 'a deleted post waits until the hub knows');
$answer = fn() => ['ok' => false, 'data' => [], 'error' => 'network'];
ok($net->push(fn() => '') === 0 && $st->post($p1)['hub_dirty'] === 1, 'unreachable hub: tried again later');
$answer = fn() => ['ok' => true, 'data' => [], 'error' => null]; $net->push(fn() => '');
ok($st->post($p1) === null, 'then it is removed for good');
$feed = (new Network($st, fn() => ['ok' => true, 'error' => null, 'data' => ['items' => [['title' => 'A', 'url' => 'javascript:alert(1)'], ['title' => 'B', 'url' => 'https://x.ru/p', 'cover' => 'data:x', 'author_url' => 'https://x.ru/u', 'excerpt' => ['no']], 'junk'], 'total' => 3]]))->feed();
ok(count($feed['items']) === 1 && $feed['items'][0]['cover'] === '' && $feed['items'][0]['excerpt'] === '', 'the feed received from the hub is checked');
ok((new Network($st, fn() => ['ok' => false, 'error' => 'network', 'data' => []]))->feed() === null, 'no feed without the hub');

/* Basebase synchronisation */
$st = new MyblogMemStore(); $posts = new Posts($st, $now);
$remote = []; $log = []; $seq = 100; $ver = 0; $mode = 'ok';
$api = function (string $m, string $path, ?array $body) use (&$remote, &$log, &$seq, &$ver, &$mode) {
    $log[] = "$m $path";
    if ($mode === 'old') return ['code' => 404, 'data' => [], 'error' => 'Unknown endpoint.'];
    if ($mode === 'down') return ['code' => 0, 'data' => [], 'error' => 'network'];
    $okr = fn($d) => ['code' => 200, 'data' => $d, 'error' => null];
    if ($m === 'GET' && $path === '/posts') return $okr(['posts' => array_values($remote), 'complete' => $mode !== 'partial']);
    if ($m === 'GET') return $okr(['sets' => [['set_id' => 1, 'title' => 'Set', 'items' => 2, 'sum' => 10, 'url' => 'https://www.basebase.ru/set/1'], ['title' => 'bad', 'url' => 'javascript:1']]]);
    $id = (int)basename($path);
    if ($m === 'DELETE') { unset($remote[$id]); return $okr([]); }
    if ($m === 'POST') $id = ++$seq; elseif (!isset($remote[$id])) return ['code' => 404, 'data' => [], 'error' => 'Post not found.'];
    $remote[$id] = ['post_id' => $id, 'external_id' => $body['external_id'], 'title' => $body['title'], 'body' => $body['body'], 'status' => $body['status'], 'products' => array_map(fn($p) => ['product_id' => $p], $body['products']),
        'cover_url' => isset($body['cover_url']) ? 'https://www.basebase.ru/uploads/c.jpg' : ($remote[$id]['cover_url'] ?? ''), 'url' => "https://www.basebase.ru/blog/$id", 'created_at' => '2026-11-01T10:00:00+03:00', 'updated_at' => 'v' . (++$ver), 'sent_cover' => $body['cover_url'] ?? null];
    return $okr(['post_id' => $id, 'url' => $remote[$id]['url'], 'updated_at' => $remote[$id]['updated_at']]);
};
$sync = new BbSync($st, $api, fn($n) => "https://s.ru/p/myblog/cover/$n", BbSync::siteId('s.ru'), $now);
$a = $posts->save(1, false, 0, ['title' => 'Mine', 'body' => 'b', 'products' => [5]], [5], str_repeat('c', 32) . '.jpg');
$mode = 'old'; $r = $sync->run(1);
ok($r['status'] === 'unsupported' && $st->post($a)['bb_dirty'] === 1, 'Basebase without the blog endpoints: reported, nothing lost');
$mode = 'down'; ok($sync->run(1)['status'] === 'error', 'network error');
$mode = 'ok'; $r = $sync->run(1); $bbid = (int)$st->post($a)['bb_post_id'];
ok($r['status'] === 'ok' && $r['pushed'] === 1 && $r['pulled'] === 0 && $bbid === 101 && $st->post($a)['bb_dirty'] === 0 && $remote[101]['sent_cover'] === 'https://s.ru/p/myblog/cover/' . str_repeat('c', 32) . '.jpg' && $st->post($a)['cover'] !== null, 'a new post is sent with its cover and keeps the local picture');
ok(strlen($remote[101]['external_id']) <= 80 && preg_match('/^sf-[0-9a-f]{8}-\d+$/', $remote[101]['external_id']) === 1, 'external id');
$remote[101]['title'] = 'Edited there'; $remote[101]['updated_at'] = 'v-there'; $st->update($a, ['hub_dirty' => 0]);
$r = $sync->run(1);
ok($r['pulled'] === 1 && $st->post($a)['title'] === 'Edited there' && $st->post($a)['hub_dirty'] === 1 && $st->post($a)['cover'] === null && $st->post($a)['cover_url'] === 'https://www.basebase.ru/uploads/c.jpg', 'an edit on Basebase comes here');
$posts->save(1, false, $a, ['title' => 'Edited here', 'products' => [5]], [5]); $remote[101]['title'] = 'Also there'; $remote[101]['updated_at'] = 'v-also';
$sync->run(1);
ok($remote[101]['title'] === 'Edited here' && $st->post($a)['title'] === 'Edited here', 'an unsent local edit wins');
$remote[200] = ['post_id' => 200, 'title' => 'From Basebase', 'body' => 'hi', 'status' => 'published', 'products' => [['product_id' => 8]], 'cover_url' => 'https://evil.com/x.jpg', 'url' => 'https://www.basebase.ru/blog/200', 'created_at' => '2026-10-01T00:00:00+03:00', 'updated_at' => 'z'];
$r = $sync->run(1); $new = array_values(array_filter($st->posts, fn($p) => $p['bb_post_id'] === 200))[0] ?? null;
ok($r['pulled'] === 1 && $new && $new['origin'] === 'basebase' && $new['products'] === '[8]' && $new['cover_url'] === null && $new['bb_dirty'] === 0 && $new['hub_dirty'] === 1, 'a post written on Basebase appears here');
ok($sync->run(1)['pulled'] === 0, 'running again changes nothing');
unset($remote[200]); $mode = 'partial'; $sync->run(1);
ok(!$st->post($new['id'])['deleted'], 'nothing is removed on a partial list');
$mode = 'ok'; $r = $sync->run(1);
ok($r['removed'] === 1 && $st->post($new['id'])['deleted'] === 1, 'deleted on Basebase, deleted here');
$posts->delete(1, false, $a); $log = []; $sync->run(1);
ok(in_array('DELETE /posts/101', $log, true) && !isset($remote[101]), 'deleted here, deleted on Basebase');
$sets = $sync->sets();
ok(count($sets) === 1 && $sets[0]['title'] === 'Set', 'sets of the shop');

/* ---------- the public profile ---------- */
require dirname(__DIR__) . '/core/plugins/myblog/lib/Profile.php';
$P = SysferaBlog\Profile::class;
$list = [['title' => 'Lip patch', 'sku' => 'LP-1', 'price' => 145.0, 'category' => 'Skin'], ['title' => 'Nose mask', 'sku' => 'NM-2', 'price' => 250.0, 'category' => 'Cosmetics'], ['title' => 'Bag', 'sku' => '', 'price' => 900.0, 'category' => '']];
$names = fn(array $in): array => array_column($P::filter($list, $P::filters($in)), 'title');
ok(count($names([])) === 3 && $names(['q' => 'lip']) === ['Lip patch'] && $names(['q' => 'nm-2']) === ['Nose mask'], 'products: search by name or SKU');
ok($names(['min' => '200', 'max' => '300']) === ['Nose mask'] && $names(['min' => '1 000']) === [] && count($names(['min' => 'abc', 'max' => '-5'])) === 3, 'products: price range, junk ignored');
ok($names(['cat' => 'Skin']) === ['Lip patch'] && $names(['cat' => ['x']]) === ['Lip patch', 'Nose mask', 'Bag'], 'products: category');
ok($P::categories($list) === ['Cosmetics' => 1, 'Skin' => 1], 'categories with counts');
ok(!$P::visible(null, 5, 5, true) && !$P::visible('A', 0, 0, false) && $P::visible('A', 0, 0, true) && $P::visible('A', 1, 0, false) && $P::visible('A', 0, 1, false), 'a profile is public only once something is public');
$people = $P::people([['owner_id' => 2, 'shop_name' => 'Olga Shop', 'products' => 3]], [['uid' => 2, 'name' => 'Olga', 'posts' => 1], ['uid' => 3, 'name' => 'Oleg', 'posts' => 4]]);
ok(count($people) === 2 && $people[0] === ['uid' => 2, 'name' => 'Olga Shop', 'products' => 3, 'posts' => 1] && $people[1]['name'] === 'Oleg', 'search: one line per user, shop name first');

/* videos */
$src = fn(string $u): string => Media::video($u)['src'];
$yt = 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ';
ok($src('https://www.youtube.com/watch?v=dQw4w9WgXcQ&list=x') === $yt && $src('https://youtu.be/dQw4w9WgXcQ?si=a') === $yt && $src('https://m.youtube.com/shorts/dQw4w9WgXcQ') === $yt && $src('https://youtu.be/dQw4w9WgXcQ?t=1m5s') === $yt . '?start=65', 'YouTube links');
$vk = 'https://vk.com/video_ext.php?oid=-123&id=456&hd=2';
ok($src('https://vk.com/video-123_456') === $vk && $src('https://vkvideo.ru/video-123_456') === $vk && $src('https://vk.com/videos-123?z=video-123_456%2Fpl') === $vk && $src('https://vk.ru/clip-123_456') === $vk
    && $src('https://vk.com/video_ext.php?oid=-123&id=456&hash=abcdef0123456789') === 'https://vk.com/video_ext.php?oid=-123&id=456&hash=abcdef0123456789&hd=2', 'VK Video links');
ok($src('https://rutube.ru/video/' . str_repeat('a', 32) . '/') === 'https://rutube.ru/play/embed/' . str_repeat('a', 32) && $src('https://vimeo.com/123456') === 'https://player.vimeo.com/video/123456' && $src('https://dai.ly/x7abcd') === 'https://www.dailymotion.com/embed/video/x7abcd' && $src('https://ok.ru/video/987') === 'https://ok.ru/videoembed/987', 'Rutube, Vimeo, Dailymotion, OK');
ok(Media::video('https://a.example/m/film.MP4')['type'] === 'file' && Media::video('https://evil.example/watch?v=dQw4w9WgXcQ')['type'] === 'link' && Media::video('https://youtube.com.evil.example/watch?v=dQw4w9WgXcQ')['type'] === 'link' && Media::video('https://youtube.com/watch?v="><x')['type'] === 'link', 'other links are never framed');
ok(Media::parseLines(" https://youtu.be/dQw4w9WgXcQ \n\n<iframe width=\"560\" src=\"//vk.com/video_ext.php?oid=1&amp;id=2\" frameborder=\"0\"></iframe>\nrutube.ru/video/1/") === ['https://youtu.be/dQw4w9WgXcQ', 'https://vk.com/video_ext.php?oid=1&id=2', 'https://rutube.ru/video/1/'], 'typed links and pasted embed codes');
ok(Media::parseLines('javascript:alert(1)') === null && Media::parseLines('hello') === null && Media::parseLines('<iframe></iframe>') === null && Media::parseLines('') === [], 'lines that are not links are refused');

/* gallery */
$g = new MyblogMemStore(); $gp = new Posts($g, fn() => 1_790_000_000);
$img = fn(string $c): string => str_repeat($c, 32) . '.jpg';
$gid = $gp->save(1, false, 0, ['title' => 'G', 'videos' => 'https://youtu.be/dQw4w9WgXcQ'], [], null, [$img('a'), $img('b')]);
ok(Media::images($g->post($gid)['images']) === [$img('a'), $img('b')] && Posts::card($g->post($gid), fn($u) => $u)['cover'] === '/p/myblog/cover/' . $img('a'), 'gallery saved; its first picture stands in for a cover');
$gp->save(1, false, $gid, ['title' => 'G', 'remove_images' => [$img('a')]], [], null, [$img('c')]);
ok(Media::images($g->post($gid)['images']) === [$img('b'), $img('c')] && Media::videos($g->post($gid)['videos']) === ['https://youtu.be/dQw4w9WgXcQ'], 'pictures removed and added; videos kept when the field is not sent');
ok(err(fn() => $gp->save(1, false, $gid, ['title' => 'G'], [], null, array_map(fn($i) => md5((string)$i) . '.jpg', range(1, 11)))) === 'many_images', 'gallery limit');
ok(err(fn() => $gp->save(1, false, $gid, ['title' => 'G', 'videos' => 'nonsense'], [])) === 'bad_video' && err(fn() => $gp->save(1, false, $gid, ['title' => 'G', 'videos' => implode("\n", array_map(fn($i) => "https://v.example/$i", range(1, 9)))], [])) === 'many_videos', 'video checks');
ok(err(fn() => $gp->save(2, false, $gid, ['title' => 'x'], [])) === 'no_post' && err(fn() => $gp->delete(2, false, $gid)) === 'no_post', 'only the author edits or deletes');

echo $fail ? "\n$fail FAILED\n" : "\nAll good\n";
exit($fail ? 1 : 0);
