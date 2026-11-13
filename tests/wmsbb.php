<?php
declare(strict_types=1);
/** Tests for the Basebase connector's protocol helpers (pure PHP). Run: php tests/wmsbb.php */
if (!function_exists('mb_substr')) { function mb_substr(string $s, int $start, ?int $len = null): string { return $len === null ? substr($s, $start) : substr($s, $start, $len); } }   // CLI without mbstring
require dirname(__DIR__) . '/core/plugins/wms-basebase/lib/Bb.php';

use SysferaBb\Bb;

$fail = 0;
function ok(bool $c, string $n): void { global $fail; echo ($c ? '  ok   ' : '  FAIL ') . $n . "\n"; if (!$c) $fail++; }

/* signing — must match Basebase's bb_sign(): hash_hmac('sha256', ts "\n" METHOD "\n" uri "\n" body, secret) */
$sig = Bb::sign('s3cret', 1700000000, 'post', '/api/basebase/webhook', '{"a":1}');
ok($sig === hash_hmac('sha256', "1700000000\nPOST\n/api/basebase/webhook\n{\"a\":1}", 's3cret'), 'signature follows the contract');
ok(Bb::verify('s3cret', 1700000000, 'POST', '/api/basebase/webhook', '{"a":1}', $sig, 1700000100), 'valid signature accepted');
ok(!Bb::verify('s3cret', 1700000000, 'POST', '/api/basebase/webhook', '{"a":2}', $sig, 1700000100), 'changed body rejected');
ok(!Bb::verify('other', 1700000000, 'POST', '/api/basebase/webhook', '{"a":1}', $sig, 1700000100), 'wrong secret rejected');
ok(!Bb::verify('s3cret', 1700000000, 'POST', '/api/basebase/webhook', '{"a":1}', $sig, 1700000301), 'older than 5 minutes rejected');
ok(!Bb::verify('', 1700000000, 'POST', '/x', '', 'abc', 1700000000), 'empty secret never verifies');

/* external ids */
ok(Bb::externalId(['id' => 5, 'sku' => 'WM 0042/B']) === 'WM-0042-B', 'SKU is made URL-safe');
ok(Bb::externalId(['id' => 5, 'sku' => '', 'code' => '00007']) === '00007', 'falls back to the product code');
ok(Bb::externalId(['id' => 5]) === 'wms-5', 'falls back to wms-<id>');
ok(Bb::externalId(['id' => 5, 'sku' => 'A'], 'CUSTOM') === 'CUSTOM', 'explicit id wins');

/* stock + promo */
ok(Bb::stockUnits(12.9) === 12 && Bb::stockUnits(-3) === 0 && Bb::stockUnits(0.0) === 0, 'stock is whole units, never negative');

/* images */
ok(Bb::images("https://a/1.jpg, http://bad/2.jpg\nhttps://a/1.jpg\nhttps://b/3.png") === ['https://a/1.jpg', 'https://b/3.png'], 'only unique https images');
ok(count(Bb::images(implode(' ', array_map(fn($i) => "https://x/$i.jpg", range(1, 12))))) === 8, 'at most 8 images');

/* product payload */
$p = ['id' => 1, 'name' => 'Кружка', 'description' => 'Керамика', 'sell_price' => '2450.00', 'sku' => 'WM-1', 'barcode' => '460', 'weight' => '0.350'];
$map = ['external_id' => 'WM-1', 'category' => 'Кухня', 'images' => 'https://a/1.jpg'];
$b = Bb::product($p, $map, 12.4);
ok($b['title'] === 'Кружка' && $b['price'] === 2450.0 && !isset($b['promo_price']) && $b['stock'] === 12 && $b['category'] === 'Кухня', 'product payload');
$bp = Bb::product($p + ['promo_price' => '1800.00'], $map, 12.4);
ok($bp['promo_price'] === 1800.0 && !isset(Bb::product($p + ['promo_price' => '2450'], $map, 1)['promo_price']) && !isset(Bb::product($p + ['promo_price' => '3000'], $map, 1)['promo_price']), 'promo price is sent only when positive and below the selling price');
ok($b['fields']['Штрихкод'] === '460' && $b['fields']['Вес, кг'] === '0.35' && $b['images'] === ['https://a/1.jpg'], 'specs and images');
ok(Bb::problems(['name' => 'x', 'sell_price' => 0], ['category' => 'a']) === ['err_price'], 'price is required');
ok(Bb::problems(['name' => 'x', 'sell_price' => 5], ['category' => ' ']) === ['err_category'], 'category is required');

/* order item: polling shape and webhook shape give the same result */
$poll = ['order_id' => 1055, 'item_id' => 8841, 'status' => 'pending', 'customer' => ['name' => ' Иван ', 'phone' => '+7 900', 'address' => 'Москва', 'delivery' => 'CDEK', 'notes' => ''],
    'product' => ['external_id' => 'WM-1', 'title' => 'Кружка', 'variant' => ''], 'qty' => 2, 'price' => 2450.0, 'seller_receives' => 4425.0];
$hook = ['order_id' => 1055, 'item_id' => 8841, 'title' => 'Кружка', 'external_id' => 'WM-1', 'variant' => '', 'qty' => 2, 'price' => 2450.0, 'seller_receives' => 4425.0,
    'customer' => ['name' => 'Иван', 'phone' => '+7 900', 'address' => 'Москва', 'delivery' => 'CDEK', 'notes' => ''], 'seller_id' => 7];
$a = Bb::item($poll); $h = Bb::item($hook + ['status' => 'pending']);
ok($a === $h, 'poll and webhook items normalise to the same array');
ok($a['external_id'] === 'WM-1' && $a['qty'] === 2.0 && $a['customer']['name'] === 'Иван', 'item fields');
ok(str_contains(Bb::comment($a), 'Basebase #1055 / 8841') && str_contains(Bb::comment($a), 'CDEK'), 'order comment mentions Basebase ids and delivery');

ok(Bb::releasesReserve('returned') && !Bb::releasesReserve('shipped'), 'only returned/cancelled release the reserve');
ok(Bb::categoryNames([['id' => 1, 'name' => 'Дом', 'children' => [['id' => 2, 'name' => 'Кухня']]], ['id' => 3, 'name' => 'Дом']]) === ['Дом', 'Кухня'], 'category names are flattened and unique');

// ---- product structure (options, specifications, photos, short description) ----
$full = $p + ['short_desc' => 'Short', 'images' => "https://a/x.jpg\nhttps://a/y.jpg", 'variants' => json_encode([['name' => 'M', 'price' => 0, 'stock' => 4], ['name' => 'L', 'price' => 2600, 'stock' => 3], ['name' => '', 'stock' => 9]]),
    'specs' => json_encode([['section' => '', 'name' => 'Материал', 'value' => 'Керамика'], ['section' => 'Размеры', 'name' => 'Высота', 'value' => '10 см'], ['section' => '', 'name' => 'Пусто', 'value' => '']])];
$bf = Bb::product($full, array_merge($map, ['images' => '']), 99.0);
ok($bf['short_description'] === 'Short' && $bf['images'] === ['https://a/x.jpg', 'https://a/y.jpg'], 'short description and product photos are sent; mapping photos win when filled');
ok($bf['variants'] === [['name' => 'M', 'stock' => 4], ['name' => 'L', 'stock' => 3, 'price' => 2600.0]] && $bf['stock'] === 7, 'options are sent with their own stock and the total replaces the warehouse stock');
ok($bf['fields']['Материал'] === 'Керамика' && $bf['fields']['Штрихкод'] === '460' && !in_array('Пусто', array_keys($bf['fields']), true) && in_array(['section' => 'Размеры', 'name' => 'Высота', 'value' => '10 см'], $bf['fields'], true), 'own specifications are merged with SKU/barcode/weight');
ok(Bb::product($full, $map, 99.0)['images'] === ['https://a/1.jpg'], 'photos entered in the Basebase catalog override the product photos');
ok(!isset($b['variants']) && !isset($b['short_description']), 'a simple product has neither');
echo $fail ? "\n$fail FAILED\n" : "\nAll good\n";
exit($fail ? 1 : 0);
