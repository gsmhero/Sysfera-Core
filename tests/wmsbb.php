<?php
declare(strict_types=1);
/** Tests for the Basebase connector's protocol helpers (pure PHP). Run: php tests/wmsbb.php */
if (!function_exists('mb_substr')) { function mb_substr(string $s, int $start, ?int $len = null): string { return $len === null ? substr($s, $start) : substr($s, $start, $len); } }   // CLI without mbstring
require dirname(__DIR__) . '/core/plugins/wms-basebase/lib/Bb.php';
if (!function_exists('mb_strtolower')) { function mb_strtolower(string $s): string { return strtolower($s); } }

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
ok($b['fields']['Штрихкод'] === '460' && $b['fields']['Вес, кг'] === '0.35', 'specs and images');
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
ok($bf['short_description'] === 'Short' && $bf['images'] === ['https://a/x.jpg', 'https://a/y.jpg'], 'short description and product photos are sent');
ok($bf['fields']['Материал'] === 'Керамика' && $bf['fields']['Штрихкод'] === '460' && !in_array('Пусто', array_keys($bf['fields']), true) && in_array(['section' => 'Размеры', 'name' => 'Высота', 'value' => '10 см'], $bf['fields'], true), 'own specifications are merged with SKU/barcode/weight');
ok(!isset($b['variants']) && !isset($b['short_description']), 'a simple product has neither');
/* product types */
$parts = [['name' => 'Shirt M', 'label' => 'M', 'own_price' => 0, 'sell_price' => 2000, 'qty' => 1, 'avail' => 4.0], ['name' => 'Shirt L', 'label' => '', 'own_price' => 2200, 'sell_price' => 2000, 'qty' => 1, 'avail' => 3.9], ['name' => 'Shirt XL', 'label' => '', 'own_price' => 0, 'sell_price' => 2500, 'qty' => 1, 'avail' => 0.0]];
$pv = ['ptype' => 'variable', 'name' => 'Shirt', 'sell_price' => 2000, 'description' => ''];
$bv = Bb::product($pv, ['external_id' => 'S', 'category' => 'C'], 0.0, $parts);
ok(count($bv['variants']) === 3 && $bv['variants'][0] === ['name' => 'M', 'stock' => 4] && $bv['variants'][1] === ['name' => 'Shirt L', 'stock' => 3, 'price' => 2200.0] && $bv['variants'][2]['price'] === 2500.0 && $bv['stock'] === 7, 'variable: variations come from warehouse products (stock floored, own price only when it differs)');
ok(Bb::problems(['ptype' => 'variable', 'name' => 'x', 'sell_price' => 0], ['category' => 'C'], $parts) === [] && Bb::problems(['ptype' => 'variable', 'name' => 'x', 'sell_price' => 0], ['category' => 'C'], []) === ['err_price', 'err_parts'], 'variable: price may come from the variations; variations are required');
$bb = Bb::product(['ptype' => 'bundle', 'name' => 'Set', 'sell_price' => 100, 'description' => ''], ['external_id' => 'B', 'category' => 'C'], 0.0, [['name' => 'A', 'qty' => 2, 'avail' => 7.0], ['name' => 'B', 'qty' => 1, 'avail' => 2.0]]);
ok($bb['stock'] === 2 && $bb['fields']['Состав'] === 'A × 2, B × 1' && !isset($bb['variants']), 'bundle: stock from the scarcest part, composition shown');
$bs = Bb::product(['ptype' => 'downloadable', 'name' => 'Book', 'sell_price' => 100, 'description' => ''], ['external_id' => 'D', 'category' => 'C'], 0.0);
ok($bs['stock'] === 999 && $bs['fields']['Тип товара'] === 'Цифровой товар', 'downloadable: never out of stock');
ok(Bb::problems(['ptype' => 'grouped', 'name' => 'G', 'sell_price' => 1], ['category' => 'C']) === ['err_grouped'], 'grouped: not sold as one item');

echo $fail ? "\n$fail FAILED\n" : "\nAll good\n";
exit($fail ? 1 : 0);
