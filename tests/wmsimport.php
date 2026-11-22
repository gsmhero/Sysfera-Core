<?php
declare(strict_types=1);
/**
 * Tests for the WMS product importer (no database): xlsx/csv reader, header detection, field mapping, templates, row planning,
 * every importer screen rendered in English and Russian, and the language files. Fixtures have the structure of real OZON exports
 * (category template with group captions / "required" row / help row / hidden service sheets, the "Товары" report with a title row)
 * plus Wildberries-style windows-1251 csv, Yandex-style utf-8 csv with BOM and an English csv. Run: php tests/wmsimport.php
 * (set IMPORT_REAL_DIR to a folder with real exports to check them too)
 */
require __DIR__ . '/XlsxWriter.php';
$dir = dirname(__DIR__) . '/core/plugins/wms';
foreach (['W', 'SheetReader', 'ImportKit', 'ImportWms'] as $c) require "$dir/lib/$c.php";
if (!function_exists('e')) { function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); } }
if (!function_exists('csrf_field')) { function csrf_field(): string { return '<input type="hidden" name="_t" value="x">'; } }

use SysferaWms\{ImportKit, ImportWms, SheetReader};

$fail = 0;
function ok(bool $c, string $n): void { global $fail; echo ($c ? '  ok   ' : '  FAIL ') . $n . "\n"; if (!$c) $fail++; }
function same($a, $b, string $n): void { ok($a === $b, $n . ($a === $b ? '' : ' (got ' . json_encode($a, JSON_UNESCAPED_UNICODE) . ', expected ' . json_encode($b, JSON_UNESCAPED_UNICODE) . ')')); }

$cat = ImportWms::catalog();
$tmp = sys_get_temp_dir() . '/wmsimp-' . bin2hex(random_bytes(4)); mkdir($tmp);
function put(string $name, string $data): string { global $tmp; $p = "$tmp/$name"; file_put_contents($p, $data); return $p; }
function field_of(array $g, array $headers, string $header): string { foreach ($headers as $c => $h) if ($h === $header) return $g[$c]['field']; return '?'; }

/* ---------- helpers ---------- */
same(ImportKit::parseMoney('445,00'), 445.0, 'money 445,00');
same(ImportKit::parseMoney('1 450,50 ₽'), 1450.5, 'money with spaces and ruble sign');
same(ImportKit::parseMoney('1,450.50'), 1450.5, 'money en thousands');
same(ImportKit::parseMoney('abc'), null, 'money garbage');
same(ImportKit::norm('Цена на сайте, ₽'), 'цена на сайте', 'norm header');
same(ImportKit::norm('offer-id'), 'offer id', 'norm dash');
same(ImportKit::splitUrls("https://a.ru/1.jpg\nhttps://a.ru/2.jpg; https://a.ru/3.jpg"), ['https://a.ru/1.jpg', 'https://a.ru/2.jpg', 'https://a.ru/3.jpg'], 'split urls');

/* ---------- OZON category template ---------- */
$hdr = ['№', 'Артикул*', 'Название товара', 'Предельная цена, руб.*', 'Зачёркнутая цена, руб.', 'НДС, %*', 'Рассрочка', 'SKU', 'Штрихкод (Серийный номер / EAN)', 'ТН ВЭД коды ЕАЭС*', 'Вес в упаковке, г*',
    'Ссылка на главное фото*', 'Ссылки на дополнительные фото', 'Бренд*', 'Название модели (для объединения в одну карточку)', 'Цвет товара', 'Аннотация', 'Rich-контент JSON', 'Количество, штук', 'Ошибка', 'Недочёты'];
$g = array_fill(0, 21, ''); foreach ([2 => "Название\nпояснение", 3 => "Цены\nЗаполняйте только при создании", 10 => "Информация о товаре\nБлок можно не заполнять", 15 => "Характеристики\nБлок"] as $c => $v) $g[$c] = $v;
$req = ['', 'Обязательное поле', '', 'Обязательное поле', '', 'Обязательное поле', '', '', '', 'Обязательное поле', 'Обязательное поле', 'Обязательное поле', '', 'Обязательное поле ϟ  Влияет на продвижение', '', 'Ⓜ️ Множественный выбор', '', '', '', '', ''];
$help = ['', 'Введите артикул товара или его номер в вашей системе. Это значение нужно для сопоставления', 'Ознакомьтесь с Требованиями к названию, чтобы повысить конверсию и качество карточки товара', 'Цена, которую вы устанавливаете вручную. Подробнее по ссылке', 'Цена, которую покупатель видит зачёркнутой', 'Укажите ставку НДС. Если товар не облагается', 'Добавим товару пометку', 'Заполните поле, если хотите скопировать чужой товар', 'Введите штрихкод товара от производителя', 'Выберите одно значение из списка', 'Можем взять дополнительную плату', 'Вы можете загрузить товары в xls-шаблоне и API', 'Вы можете загрузить', 'Среди брендов есть продвигаемые', 'Заполните данное поле любым одинаковым значением', 'Основной или доминирующий цвет товара', 'Описание товара, маркетинговый текст', 'Добавьте расширенное описание', 'Сколько штук', 'Критичная ошибка', 'Устаревшее или незаполненное значение'];
$r1 = [1, 'заправка_540бакк', 'Ароматизатор для автомобиля', 1450, 1550, 'Не облагается', 'Нет', '', 'OZN2039218712', '', 45, 'http://cdn1.ozone.ru/s3/multimedia-1-d/7513612717.jpg', "https://cdn1.ozone.ru/s3/multimedia-1-a/7514965342.jpg\nhttps://cdn1.ozone.ru/s3/multimedia-1-x/7514954313.jpg", 'Нет бренда', '019cc322ac6a7945', 'прозрачный', 'Уникальный ароматизатор для автомобиля станет отличным подарком', "{\n \"content\": []\n}", 1, '', ''];
$r2 = [2, 'бакарат', 'Ароматизатор для автомобиля Бакарат', 490, 999, 'Не облагается', 'Нет', '', 'OZN1664023629', '', 45, 'https://cdn1.ozone.ru/s3/multimedia-1-o/7514805148.jpg', '', 'Нет бренда', '019cc322ac6a794b', 'черный', 'Бакара — унисекс композиция', '', 1, '', ''];
$r3 = [3, 'бакарат', 'Дубль артикула', 500, '', 'Не облагается', 'Нет', '', '', '', 45, '', '', 'Нет бренда', '', '', '', '', 1, '', ''];
$r4 = [4, '', 'Без артикула', 'бесплатно', '', 'Не облагается', 'Нет', '', '', '', 45, '', '', 'Нет бренда', '', '', '', '', 1, '', ''];
$ozon = XlsxWriter::build([
    ['name' => 'Шаблон', 'rows' => [$g, $hdr, $req, $help, $r1, $r2, $r3, $r4]],
    ['name' => 'configs', 'hidden' => true, 'rows' => [['PRODUCTS_TITLE_ROW_INDEX', 'PRODUCTS_FIRST_DATA_ROW_INDEX'], ['XLS_TEMPLATE_INFO_BASE64', 'Qs9C+0LIifSwiOTcxMzk3OTA5Ijp7IklEIjo5NzEzOTc5']]],
    ['name' => 'Озон.Видео', 'rows' => [[], [], [], [], ['Да', '3301909000 - Прочие масла'], ['3302909000 - Прочие смеси', '3307900008 - Косметические']]],
    ['name' => 'validation', 'hidden' => true, 'rows' => array_map(fn($i) => ['3921190000 - Прочие плиты', "знач $i", 'x', 'y', 'z', 'q'], range(1, 60))],
]);
$sheets = SheetReader::read(put('ozon.xlsx', $ozon), 'ozon.xlsx');
same(count($sheets), 4, 'ozon: four sheets');
same($sheets[0]['rows'][4][1], 'заправка_540бакк', 'ozon: shared string (plain and rich-text entries)');
same($sheets[0]['rows'][4][3], '1450', 'ozon: number cell');
$t = ImportKit::detect($sheets, $cat);
same([$t['sheet'], $t['header_row'], count($t['rows']), array_key_first($t['rows'])], ['Шаблон', 1, 4, 4], 'ozon: data sheet, header row, rows below the required/help rows');
same($t['groups'][10], 'Информация о товаре', 'ozon: group caption is the section');
$gs = ImportKit::guess($t['headers'], $cat); $H = $t['headers'];
same([field_of($gs, $H, 'Артикул*'), field_of($gs, $H, 'Название товара'), field_of($gs, $H, 'Предельная цена, руб.*'), field_of($gs, $H, 'Штрихкод (Серийный номер / EAN)')], ['sku', 'name', 'sell', 'barcode'], 'ozon map: sku, name, price, barcode');
same([field_of($gs, $H, 'Ссылка на главное фото*'), field_of($gs, $H, 'Ссылки на дополнительные фото'), field_of($gs, $H, 'Аннотация')], ['images', 'images', 'desc'], 'ozon map: images, description');
same([field_of($gs, $H, 'SKU'), field_of($gs, $H, 'Зачёркнутая цена, руб.'), field_of($gs, $H, 'Rich-контент JSON'), field_of($gs, $H, 'Ошибка'), field_of($gs, $H, 'Название модели (для объединения в одну карточку)')], ['skip', 'skip', 'skip', 'skip', 'skip'], 'ozon map: service columns skipped');
same([field_of($gs, $H, 'Бренд*'), field_of($gs, $H, 'Цвет товара')], ['as_is', 'as_is'], 'ozon map: characteristics kept as specifications');
$rows = ImportKit::extract($t, $gs, $cat);
same([count($rows), $rows[0]['line'], $rows[0]['v']['sku'], $rows[0]['v']['sell'], $rows[0]['v']['barcode']], [4, 5, 'заправка_540бакк', 1450.0, 'OZN2039218712'], 'ozon: rows extracted');
same(count($rows[0]['v']['images']), 3, 'ozon: images merged from two columns');
same(ImportWms::duplicates($rows), [7 => 6], 'ozon: duplicate SKU reported against the first row');
$p1 = ImportWms::plan($rows[0], null, ['stock' => 'new']);
same([$p1['action'], $p1['d']['name'], $p1['d']['images'][0]], ['create', 'Ароматизатор для автомобиля', 'https://cdn1.ozone.ru/s3/multimedia-1-d/7513612717.jpg'], 'plan: new product, http image upgraded to https');
ok(isset($p1['d']['short']), 'plan: short description derived from the description for a new product');
$p4 = ImportWms::plan($rows[3], null, ['stock' => 'new']);
same([$p4['action'], $p4['errors'][0][0]], ['error', 'bad_number'], 'plan: unparsable price is an error');
$ex = ['id' => 9, 'sku' => 'заправка_540бакк', 'name' => 'Old', 'sell_price' => '1450.00', 'promo_price' => '0.00'];
same(ImportWms::plan($rows[0], $ex, ['stock' => 'new'])['action'], 'update', 'plan: same SKU updates (re-import does not duplicate)');
$noName = ['line' => 3, 'v' => ['sku' => 'X1', 'sell' => 5.0], 'attrs' => [], 'errors' => []];
same(ImportWms::plan($noName, null, [])['errors'][0][0], 'no_name', 'plan: new product needs a name');
same(ImportWms::plan($noName, ['id' => 1, 'sell_price' => '5'], [])['action'], 'update', 'plan: update does not');
$neg = ['line' => 3, 'v' => ['name' => 'N', 'sku' => 'N1', 'buy' => -1.0, 'qty' => 2.0], 'attrs' => [], 'errors' => []];
same(ImportWms::plan($neg, null, [])['errors'][0], ['negative', ['field' => 'buy']], 'plan: negative price');
$pr = ['line' => 3, 'v' => ['name' => 'N', 'sku' => 'N1', 'sell' => 100.0, 'promo' => 150.0, 'qty' => 4.5], 'attrs' => [], 'errors' => []];
$pp = ImportWms::plan($pr, null, ['stock' => 'new']);
ok(!isset($pp['d']['promo']) && $pp['warnings'][0][0] === 'promo_ignored' && $pp['d']['qty'] === 4.5, 'plan: promo above price ignored, fractional quantity kept');
ok(!isset(ImportWms::plan($pr, null, ['stock' => 'none'])['d']['qty']), 'plan: quantities ignored when the seller says so');
same(array_column(ImportWms::mergeSpecs([['section' => 'A', 'name' => 'Бренд', 'value' => 'old'], ['section' => 'A', 'name' => 'Свой', 'value' => 'keep']], [['section' => 'A', 'name' => 'бренд', 'value' => 'new'], ['section' => 'B', 'name' => 'Цвет', 'value' => 'x']]), 'value'), ['new', 'keep', 'x'], 'specs: merge');

/* ---------- OZON "Товары" report ---------- */
$rep = XlsxWriter::build([['name' => 'Товары', 'rows' => [
    ['Отчёт по товарам'],
    ['Артикул', 'Ozon Product ID', 'SKU', 'Штрихкод (Серийный номер / EAN)', 'Название товара', 'Контент-рейтинг', 'Бренд', 'Статус товара', 'Категория', 'Тип', 'Доступно к продаже по схеме FBO, шт.', 'Зарезервировано, шт', 'Доступно к продаже по схеме FBS, шт.', 'Доступно к продаже по схеме realFBS, шт.', 'Цена на сайте, ₽', 'Зачёркнутая цена, ₽', 'Размер НДС, %', 'Ошибки'],
    ['саваш', 1151615042, 1664023777, 'OZN1664023777', 'Ароматизатор для автомобиля Саваш', "'86.5", 'Нет бренда', 'Готов к продаже', 'Автоаксессуары', 'Ароматизатор автомобильный', 1, 0, 7, 0, '445,00', '999,00', '0%', ''],
    ['бакарат', 1151615071, 1664023629, 'OZN1664023629', 'Ароматизатор для автомобиля Бакарат', "'86.5", 'Нет бренда', 'Готов к продаже', 'Автоаксессуары', 'Ароматизатор автомобильный', 0, 0, 0, 0, '1 490,00', '999,00', '0%', 'Изображение: плохое'],
]]]);
$t2 = ImportKit::detect(SheetReader::read(put('report.xlsx', $rep), 'report.xlsx'), $cat);
same([$t2['header_row'], count($t2['rows']), $t2['groups']], [1, 2, []], 'report: header below the title row');
$gs2 = ImportKit::guess($t2['headers'], $cat); $H2 = $t2['headers'];
same([field_of($gs2, $H2, 'Артикул'), field_of($gs2, $H2, 'SKU'), field_of($gs2, $H2, 'Ozon Product ID'), field_of($gs2, $H2, 'Название товара'), field_of($gs2, $H2, 'Цена на сайте, ₽'), field_of($gs2, $H2, 'Категория')], ['sku', 'skip', 'skip', 'name', 'sell', 'group'], 'report map: article, name, price, category');
same([field_of($gs2, $H2, 'Доступно к продаже по схеме FBS, шт.'), field_of($gs2, $H2, 'Доступно к продаже по схеме FBO, шт.'), field_of($gs2, $H2, 'Доступно к продаже по схеме realFBS, шт.')], ['qty', 'skip', 'skip'], 'report map: FBS stock wins, other stock columns skipped');
$rows2 = ImportKit::extract($t2, $gs2, $cat);
same([$rows2[0]['v']['sell'], $rows2[1]['v']['sell'], $rows2[0]['v']['qty'], $rows2[0]['v']['group']], [445.0, 1490.0, 7.0, 'Автоаксессуары'], 'report: values');

/* ---------- csv flavours ---------- */
$csvWb = "Артикул продавца;Наименование;Цена;Скидка, %;Остаток;Фото;Описание\r\nA-1;Футболка \"Лето\";1 299,00;10;5;https://img.wb.ru/1.jpg;Хлопок\r\nA-2;Шорты;999;;0;;\r\n";
function to1251(string $s): string { $out = ''; foreach (preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) as $ch) { $cp = mb_ord($ch); $out .= $cp < 0x80 ? $ch : ($cp >= 0x410 && $cp <= 0x44F ? chr(0xC0 + $cp - 0x410) : ($cp === 0x401 ? "\xA8" : ($cp === 0x451 ? "\xB8" : '?'))); } return $out; }
if (function_exists('mb_ord')) {
    $sh = SheetReader::read(put('wb.csv', to1251($csvWb)), 'wb.csv');
    same($sh[0]['rows'][0], ['Артикул продавца', 'Наименование', 'Цена', 'Скидка, %', 'Остаток', 'Фото', 'Описание'], 'csv windows-1251 + semicolon');
} else echo "  skip windows-1251 fixture (mbstring missing)\n";
$sh = SheetReader::read(put('wb8.csv', "\xEF\xBB\xBF" . $csvWb), 'wb8.csv');
$t3 = ImportKit::detect($sh, $cat); $gs3 = ImportKit::guess($t3['headers'], $cat); $H3 = $t3['headers'];
same(array_map(fn($h) => field_of($gs3, $H3, $h), ['Артикул продавца', 'Наименование', 'Цена', 'Остаток', 'Фото', 'Описание', 'Скидка, %']), ['sku', 'name', 'sell', 'qty', 'images', 'desc', 'as_is'], 'wb csv map');
$rows3 = ImportKit::extract($t3, $gs3, $cat);
same([$rows3[0]['v']['sell'], $rows3[1]['v']['qty']], [1299.0, 0.0], 'wb csv values (zero stock is a value)');

$yan = "\xEF\xBB\xBFoffer-id,name,price,oldprice,picture,description,barcode\n1001,\"Чайник, 2 л\",\"2 500\",3000,https://img.ya.ru/a.jpg https://img.ya.ru/b.jpg,\"Строка 1\nстрока 2\",4600000000000\n";
$t4 = ImportKit::detect(SheetReader::read(put('ya.csv', $yan), 'ya.csv'), $cat); $gs4 = ImportKit::guess($t4['headers'], $cat); $H4 = $t4['headers'];
same(array_map(fn($h) => field_of($gs4, $H4, $h), ['offer-id', 'name', 'price', 'picture', 'oldprice', 'barcode']), ['sku', 'name', 'sell', 'images', 'skip', 'barcode'], 'yandex csv map');
$rows4 = ImportKit::extract($t4, $gs4, $cat);
same([$rows4[0]['v']['name'], $rows4[0]['v']['sell'], count($rows4[0]['v']['images']), $rows4[0]['v']['desc']], ['Чайник, 2 л', 2500.0, 2, "Строка 1\nстрока 2"], 'yandex csv values');

$en = "SKU,Product Name,Purchase Price,Retail Price,Qty,Unit,Group\nB-7,Lamp,9.5,19.90,12,pcs,Lighting\n";
$t5 = ImportKit::detect(SheetReader::read(put('en.csv', $en), 'en.csv'), $cat); $gs5 = ImportKit::guess($t5['headers'], $cat); $H5 = $t5['headers'];
same(array_map(fn($h) => field_of($gs5, $H5, $h), ['SKU', 'Product Name', 'Purchase Price', 'Retail Price', 'Qty', 'Unit', 'Group']), ['sku', 'name', 'buy', 'sell', 'qty', 'unit', 'group'], 'english csv map');

/* ---------- user choices & templates ---------- */
$hs = ['Артикул*', 'Название товара', 'Цена', 'Цена в акции'];
$ch = ImportKit::cleanChoice($hs, ['sku', 'name', 'sell', 'sell'], ['', '', '', ''], $cat);
same([$ch[2]['field'], $ch[3]['field']], ['sell', 'skip'], 'choice: the same target twice -> the later column is skipped');
$ch = ImportKit::cleanChoice($hs, ['evil', 'custom', 'skip', 'as_is'], ['', 'Мой <b>ключ</b>', '', ''], $cat);
same([$ch[0]['field'], $ch[1]['field'], $ch[1]['name']], ['as_is', 'custom', 'Мой <b>ключ</b>'], 'choice: unknown value -> as_is; custom name kept (escaped on output)');
$choice = [['field' => 'sku', 'name' => ''], ['field' => 'name', 'name' => ''], ['field' => 'custom', 'name' => 'Модель'], ['field' => 'skip', 'name' => '']];
$tm = ImportKit::templateMapping($hs, $choice, [0 => 1, 2 => 1]);
same(array_keys($tm), ['артикул', 'цена'], 'template: only ticked columns are saved');
$tpl = ['id' => 1, 'name' => 'Моя', 'signature' => ImportKit::signature($hs), 'mapping' => $tm];
same(ImportKit::pickTemplate(array_reverse($hs), [$tpl])['id'], 1, 'template: header order does not matter');
same(ImportKit::pickTemplate(['Артикул', 'Цена', 'Вес'], [$tpl])['id'], 1, 'template: a similar file still matches');
same(ImportKit::pickTemplate(['Foo', 'Bar'], [$tpl]), null, 'template: an unrelated file does not');
$ap = ImportKit::applyTemplate(ImportKit::guess($hs, $cat), $hs, $tpl);
same([$ap[0]['field'], $ap[2]['field'], $ap[2]['name']], ['sku', 'custom', 'Модель'], 'template: saved choice overrides the guess');

/* ---------- robustness ---------- */
foreach (['junk.xlsx' => 'not a zip at all', 'old.xls' => "\xD0\xCF\x11\xE0" . str_repeat("\0", 50), 'cut.xlsx' => substr($ozon, 0, intdiv(strlen($ozon), 2))] as $n => $data) {
    try { SheetReader::read(put($n, $data), $n); ok(false, "$n is rejected"); } catch (RuntimeException $e) { ok(true, "$n is rejected with a message"); }
}
same(ImportKit::detect([['name' => 'x', 'rows' => [['only one']], 'hidden' => false]], $cat), null, 'detect: nothing to import');
$big = SheetReader::read(put('big.xlsx', XlsxWriter::build([['name' => 'S', 'rows' => array_merge([['Артикул', 'Название', 'Цена']], array_map(fn($i) => ["A$i", "Товар $i", 100 + $i], range(1, 3000)))]])), 'big.xlsx');
same(count(ImportKit::detect($big, $cat)['rows']), 3000, 'big sheet read (the page rejects more than ' . ImportKit::MAX_ROWS . ')');

/* ---------- screens: rendered with real data, en + ru; warnings and missing translations fail ---------- */
final class FakeSf {
    public array $manifest = ['version' => '0.5.0']; public array $missing = [];
    public function __construct(private array $l) {}
    public function t(string $k, array $v = []): string { if (!isset($this->l[$k])) { $this->missing[] = $k; return 'wms.' . $k; } $s = $this->l[$k]; foreach ($v as $a => $b) $s = str_replace('{' . $a . '}', (string)$b, $s); return $s; }
    public function url(string $p = ''): string { return '/p/wms/' . ltrim($p, '/'); }
    public function asset(string $f): string { return '/assets/p/wms/' . $f; }
    public function can(string $c): bool { return true; }
}
set_error_handler(function (int $no, string $msg, string $file, int $line): bool { throw new ErrorException("$msg in " . basename($file) . ":$line"); });
$langs = ['en' => require "$dir/lang/en.php", 'ru' => require "$dir/lang/ru.php"];
$render = function (FakeSf $sf, array $vars): string { $v = $vars + ['sf' => $sf]; return (function () use ($v) { extract($v); ob_start(); try { include dirname(__DIR__) . '/core/plugins/wms/views/import.php'; return (string)ob_get_clean(); } catch (Throwable $e) { ob_end_clean(); throw $e; } })(); };
$job = ['name' => 'Ароматизатор_20.11.2026.xlsx', 'sheets' => [['name' => 'Шаблон', 'rows' => 8, 'hidden' => false], ['name' => 'Ещё', 'rows' => 3, 'hidden' => false], ['name' => 'configs', 'rows' => 2, 'hidden' => true]], 'table' => $t, 'choice' => null, 'opts' => ['stock' => 'new', 'wh' => 1]];
$choice = ImportKit::cleanChoice($t['headers'], array_column($gs, 'field'), array_column($gs, 'name'), $cat);
$planned = []; foreach ($rows as $r) { $planned[] = ['row' => $r, 'plan' => ImportWms::plan($r, null, ['stock' => 'new']), 'ex' => null]; }
$planned[] = ['row' => $rows[2], 'plan' => ['action' => 'error', 'errors' => [['duplicate', ['line' => 6]]], 'warnings' => [], 'd' => []], 'ex' => null];
$planned[] = ['row' => $rows[0], 'plan' => ImportWms::plan($rows[0], $ex, ['stock' => 'set']), 'ex' => $ex];
$whs = [['id' => 1, 'name' => 'Main', 'use_cells' => 0]];
$report = ['created' => 2, 'updated' => 1, 'failed' => 1, 'errors' => [[7, [['bad_number', ['field' => 'sell', 'value' => 'x']]]], [8, [['duplicate', ['line' => 5]]]]], 'warnings' => [[5, ['no_key', []]], [6, ['cut', ['field' => 'name', 'n' => 255]]]], 'docs' => [['id' => 5, 'number' => 'RC-00005', 'type' => 'receipt', 'lines' => 2, 'ok' => true], ['id' => 6, 'number' => 'IV-00001', 'type' => 'inventory', 'lines' => 1, 'ok' => false]], 'units' => 12.5, 'template' => 'Озон'];
$screens = [
    'upload' => ['job' => null, 'token' => '', 'step' => 'upload', 'templates' => []],
    'map' => ['job' => $job, 'token' => str_repeat('a', 24), 'step' => 'map', 'templates' => []],
    'map+template' => ['job' => ['choice' => $choice, 'ticked' => [1, 2], 'tpl_name' => 'T'] + $job, 'token' => str_repeat('a', 24), 'step' => 'map', 'templates' => [['id' => 1, 'name' => 'Озон', 'signature' => ImportKit::signature($t['headers']), 'mapping' => ImportKit::templateMapping($t['headers'], $choice, [1 => 1])]]],
    'preview' => ['job' => ['choice' => $choice] + $job, 'token' => str_repeat('a', 24), 'step' => 'preview', 'templates' => [], 'plans' => $planned],
    'done' => ['job' => ['report' => $report] + $job, 'token' => str_repeat('a', 24), 'step' => 'done', 'templates' => []],
];
foreach ($langs as $code => $l) {
    foreach ($screens as $name => $vars) {
        $sf = new FakeSf($l);
        try { $html = $render($sf, $vars + ['tab' => 'products', 'cat' => $cat, 'whs' => $whs, 'owner' => 1, 'plans' => []]); ok($sf->missing === [] && strlen($html) > 300, "screen $name ($code)" . ($sf->missing ? ' missing: ' . implode(',', array_unique($sf->missing)) : '')); }
        catch (Throwable $e) { ok(false, "screen $name ($code): " . $e->getMessage()); }
    }
}
$sf = new FakeSf($langs['en']);
$html = $render($sf, $screens['map'] + ['tab' => 'products', 'cat' => $cat, 'whs' => $whs, 'owner' => 1]);
ok(str_contains($html, 'Fields Converter') && str_contains($html, 'name="field[1]"') && str_contains($html, 'value="skip"') && str_contains($html, 'Start Import') === false && str_contains($html, 'Upload another file'), 'converter markup: table, dropdown, buttons');
$html = $render($sf, $screens['preview'] + ['tab' => 'products', 'cat' => $cat, 'whs' => $whs, 'owner' => 1]);
ok(str_contains($html, 'Row 7') && str_contains($html, 'Duplicate'), 'preview lists the row errors with row numbers');

/* ---------- language files ---------- */
$used = [];
foreach (['views/import.php', 'lib/ImportFlow.php', 'lib/ImportWms.php'] as $f) { preg_match_all("/t\\('(imp_[a-z_0-9]+)'/", (string)file_get_contents("$dir/$f"), $m); $used = array_merge($used, $m[1]); }
foreach (ImportWms::CHOICES as $k) $used[] = 'imp_f_' . $k;
foreach (['bad_number', 'no_name', 'negative', 'duplicate', 'save_failed'] as $k) $used[] = 'imp_e_' . $k;
foreach (['cut', 'promo_ignored', 'images_cut', 'attrs_cut', 'no_key'] as $k) $used[] = 'imp_w_' . $k;
$used = array_filter($used, fn($k) => !str_ends_with($k, '_')); $missing = []; foreach (array_unique($used) as $k) foreach ($langs as $c => $l) if (!isset($l[$k])) $missing[] = "$c:$k";
ok($missing === [], 'every imp_ key used by the code exists in en and ru' . ($missing ? ': ' . implode(', ', $missing) : ''));
$ph = fn(string $s) => (function () use ($s) { preg_match_all('/\{(\w+)\}/', $s, $m); sort($m[1]); return $m[1]; })();
$bad = []; foreach ($langs['en'] as $k => $v) { if (!isset($langs['ru'][$k])) $bad[] = "ru lacks $k"; elseif ($ph($v) !== $ph($langs['ru'][$k])) $bad[] = "placeholders differ in $k"; }
foreach ($langs['ru'] as $k => $v) if (!isset($langs['en'][$k])) $bad[] = "en lacks $k";
ok($bad === [], 'en and ru have the same keys and placeholders' . ($bad ? ': ' . implode(', ', $bad) : ''));
$man = json_decode((string)file_get_contents("$dir/plugin.json"), true);
ok(version_compare($man['version'], '0.5.0', '>='), 'plugin version bumped (0.5.0+)');
ok(is_file("$dir/migrations/007_import_templates.sql") && str_contains((string)file_get_contents("$dir/migrations/007_import_templates.sql"), 'wms_import_templates'), 'migration creates the templates table');

/* ---------- real exports, when available ---------- */
$real = getenv('IMPORT_REAL_DIR') ?: (getenv('USERPROFILE') ? getenv('USERPROFILE') . '/Downloads' : '');
$files = $real && is_dir($real) ? array_filter(glob($real . '/*_20.11.2026.xlsx') ?: [], fn($f) => !str_contains(basename($f), '~$')) : [];
foreach ($files as $f) {
    $tt = ImportKit::detect(SheetReader::read($f, $f), $cat);
    ok($tt !== null && $tt['header_row'] === 1 && count($tt['rows']) > 0, 'real file parsed: ' . basename($f));
    $g2 = ImportKit::guess($tt['headers'], $cat); $map = array_column($g2, 'field');
    ok(in_array('sku', $map, true) && in_array('name', $map, true) && in_array('sell', $map, true), 'real file maps sku/name/price: ' . basename($f));
    foreach (ImportKit::extract($tt, $g2, $cat) as $r) { $pl = ImportWms::plan($r, null, ['stock' => 'new']); if ($pl['action'] === 'error' && !$pl['errors']) ok(false, 'real row without reason'); }
}
echo $files ? '  real export files checked: ' . count($files) . "\n" : "  real export files not found: skipped\n";

foreach (glob("$tmp/*") as $x) unlink($x);
rmdir($tmp);
echo $fail ? "\n$fail FAILED\n" : "\nAll good\n";
exit($fail ? 1 : 0);
