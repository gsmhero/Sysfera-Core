<?php
declare(strict_types=1);
namespace SysferaWms;

use Sysfera\Db;

/**
 * Warehouse product import: field catalog (how marketplace / 1C / generic headers map to WMS product fields),
 * row validation (pure, unit-tested in tests/wmsimport.php) and the database writes.
 * Parsing, header detection, mapping and templates are the shared SheetReader + ImportKit (copies of Basebase's).
 *
 * Messages are returned as [key, vars] pairs and translated by the view with the plugin's lang files (imp_e_<key> / imp_w_<key>).
 */
final class ImportWms
{
    public const MAX_IMAGES = 8;

    public static function catalog(): array
    {
        static $c;
        return $c ??= [
            'fields' => [
                'sku' => ['kind' => 'text', 'unique' => true, 'rules' => [
                    '/^(артикул|артикул продавца|артикул поставщика|артикул товара|артикул товара продавца|артикул wb|арт|vendor ?code|vendorcode|supplier ?article|supplierarticle|seller ?sku|sellersku|offer ?id|offerid|article|item ?code|код товара|код поставщика|код номенклатуры|код)$/u',
                    '/^(sku|ску|ид товара|id товара|product ?id|идентификатор товара|id предложения)$/u',
                ]],
                'name' => ['kind' => 'text', 'unique' => true, 'rules' => [
                    '/^(название товара|наименование товара|название|наименование|номенклатура|имя товара|товар|name|title|product ?name|product ?title|item ?name)$/u',
                ]],
                'barcode' => ['kind' => 'text', 'unique' => true, 'rules' => [
                    '/^(штрихкод|штрих код|штрихкоды|баркод|barcode|ean|ean13|gtin|upc)$/u',
                ]],
                'unit' => ['kind' => 'text', 'unique' => true, 'rules' => [
                    '/^(единица измерения|ед изм|ед|единица|unit|uom|unit of measure|measure)$/u',
                ]],
                'group' => ['kind' => 'text', 'unique' => true, 'rules' => [
                    '/^(группа|группа товаров|товарная группа|категория|категория товара|category|group|раздел|предмет)$/u',
                ]],
                'images' => ['kind' => 'urls', 'unique' => false, 'rules' => [
                    '/^(ссылка на главное фото|главное фото|основное фото|главное изображение|основное изображение|main ?image|main ?photo|image ?url|image|photo|picture)$/u',
                    '/^(ссылки на дополнительные фото|дополнительные фото|дополнительные изображения|additional ?images?|extra ?images?|gallery)$/u',
                    '/^(ссылки? на фото|ссылки? на изображени[яе]|ссылки? на картинк[иу]|фото|изображени[яе]|картинк[аи]|фотографии|медиафайлы|медиа|изображение товара|url картинки|url изображения|pictures?|images?|photos?|img|image ?urls?|picture ?urls?|фото товара)$/u',
                ]],
                'buy' => ['kind' => 'money', 'unique' => true, 'rules' => [
                    '/^(закупочная цена|цена закупки|закупка|цена закупа|себестоимость|оптовая цена|цена опт|цена оптовая|purchase ?price|buy ?price|buying ?price|cost|cost ?price|wholesale ?price|цена поставщика)$/u',
                ]],
                'sell' => ['kind' => 'money', 'unique' => true, 'rules' => [
                    '/^(цена|цена на сайте|цена товара|цена продажи|розничная цена|цена розничная|цена для покупателя|предельная цена|цена с ндс|цена продавца|цена реализации|price|retail ?price|selling ?price|sell ?price|regular ?price|current ?price|цена после скидки|стоимость)$/u',
                ]],
                'promo' => ['kind' => 'money', 'unique' => true, 'rules' => [
                    '/^(акционная цена|цена со скидкой|цена по акции|промо цена|промо|promo|promo ?price|promotional ?price|sale ?price|discount ?price|special ?price)$/u',
                ]],
                'qty' => ['kind' => 'money', 'unique' => true, 'rules' => [
                    '/^(остаток|остатки|остаток на складе|остаток товара|количество на складе|количество в наличии|в наличии|наличие|stock|qty|quantity|stock ?quantity|in ?stock|available|on ?hand)$/u',
                    '/^доступно к продаже по схеме (fbs|realfbs)$/u',
                    '/^(доступно к продаже|доступно|остаток fbs|остаток fbo)/u',
                    '/^(кол во|количество)$/u',
                ]],
                'min' => ['kind' => 'money', 'unique' => true, 'rules' => [
                    '/^(минимальный остаток|мин остаток|неснижаемый остаток|минимум|min ?stock|minimum ?stock|reorder ?level)$/u',
                ]],
                'short' => ['kind' => 'text', 'unique' => true, 'rules' => [
                    '/^(краткое описание|короткое описание|short ?description|summary|подзаголовок|subtitle|анонс)$/u',
                ]],
                'desc' => ['kind' => 'text', 'unique' => true, 'rules' => [
                    '/^(описание|описание товара|подробное описание|полное описание|аннотация|description|full ?description|long ?description|annotation|о товаре)$/u',
                ]],
            ],
            'skip' => [
                '/^(№|n|#|номер|ошибка|ошибки|недочеты|недочет|предупреждения|предупреждение|error|errors|warnings?)$/u',
                '/(rich|json|хештег|хэштег|hashtag)/u',
                '/^(ндс|размер ндс|vat|рассрочка|ускоренный сбор отзывов|сбор первых отзывов|объединить в похожие товары|название модели|артикул фото|образец цвета|количество товара в уеи|минимальное количество оптом|окпд2|тн вэд коды еаэс|нужен код маркировки)$/u',
                '/^(вес в упаковке|ширина упаковки|высота упаковки|длина упаковки|объемный вес|объем товара)$/u',
                '/^(ozon product id|контент-рейтинг|контент рейтинг|метки|отзывы|рейтинг|причины скрытия|дата создания|статус товара|зарезервировано|зарезервировано на моих складах)$/u',
                '/(зачеркнутая цена|цена до скидки|старая цена|old ?price|базовая цена|рекомендуемая цена|min ?price|минимальная цена|цена до акции)/u',
            ],
        ];
    }

    /** Dropdown order of the Fields Converter (lang key imp_f_<key>). */
    public const CHOICES = ['as_is', 'name', 'sku', 'barcode', 'unit', 'group', 'images', 'buy', 'sell', 'promo', 'qty', 'min', 'short', 'desc', 'custom', 'skip'];

    /** Same SKU / barcode twice in one file: the first row wins. @return array<int,int> line => first line */
    public static function duplicates(array $rows): array
    {
        $seen = []; $dupe = [];
        foreach ($rows as $r) {
            $k = self::key((string)($r['v']['sku'] ?? '')) ?: (($b = self::key((string)($r['v']['barcode'] ?? ''))) ? 'b:' . $b : '');
            if ($k === '') continue;
            if (isset($seen[$k])) $dupe[$r['line']] = $seen[$k]; else $seen[$k] = $r['line'];
        }
        return $dupe;
    }

    public static function key(string $s): string { return ImportKit::lower(trim($s)); }

    /**
     * Decides what to do with one extracted row.
     * @param array|null $existing the owner's product matched by SKU / barcode / name
     * @param array $opts ['stock' => 'new'|'set'|'none']
     * @return array{action:string,errors:list<array>,warnings:list<array>,d:array}  action: create | update | error
     */
    public static function plan(array $row, ?array $existing, array $opts): array
    {
        $v = $row['v']; $err = []; $warn = []; $d = [];
        foreach ($row['errors'] as $e) $err[] = ['bad_number', ['field' => $e['field'], 'value' => $e['value']]];

        foreach (['sku' => 80, 'barcode' => 80, 'unit' => 20] as $f => $max) {
            $x = trim((string)($v[$f] ?? ''));
            if ($x === '') continue;
            if (ImportKit::len($x) > $max) { $warn[] = ['cut', ['field' => $f, 'n' => $max]]; $x = ImportKit::cut($x, $max); }
            $d[$f] = $x;
        }
        $name = trim((string)($v['name'] ?? ''));
        if ($name !== '') { if (ImportKit::len($name) > 255) { $warn[] = ['cut', ['field' => 'name', 'n' => 255]]; $name = ImportKit::cut($name, 255); } $d['name'] = $name; }
        elseif (!$existing) $err[] = ['no_name', []];

        foreach (['buy', 'sell', 'promo', 'min'] as $f) {
            if (!array_key_exists($f, $v)) continue;
            if ($v[$f] < 0) { $err[] = ['negative', ['field' => $f]]; continue; }
            $d[$f] = round($v[$f], $f === 'min' ? 3 : 2);
        }
        if (isset($d['promo']) && $d['promo'] > 0) {
            $sell = $d['sell'] ?? ($existing ? (float)$existing['sell_price'] : 0.0);
            if ($sell > 0 && $d['promo'] >= $sell) { $warn[] = ['promo_ignored', []]; unset($d['promo']); }
        }
        if (array_key_exists('qty', $v)) {
            if ($v['qty'] < 0) $err[] = ['negative', ['field' => 'qty']];
            elseif (($opts['stock'] ?? 'new') !== 'none') $d['qty'] = round($v['qty'], 3);
        }
        foreach (['short' => 500, 'desc' => 4000] as $f => $max) {
            $x = trim((string)($v[$f] ?? ''));
            if ($x !== '') $d[$f] = ImportKit::cut($x, $max);
        }
        if (isset($d['desc']) && !isset($d['short']) && !$existing) $d['short'] = ImportKit::cut(trim(preg_replace('/\s+/u', ' ', $d['desc'])), 200);
        if (!empty($v['group'])) $d['group'] = ImportKit::cut(trim((string)$v['group']), 160);

        if (!empty($v['images'])) {
            $urls = [];
            foreach ($v['images'] as $u) { if (str_starts_with(strtolower($u), 'http://')) $u = 'https://' . substr($u, 7); if (ImportKit::len($u) <= 500) $urls[] = $u; }
            if (count($urls) > self::MAX_IMAGES) $warn[] = ['images_cut', ['n' => self::MAX_IMAGES]];
            if ($urls) $d['images'] = array_slice(array_values(array_unique($urls)), 0, self::MAX_IMAGES);
        }
        if ($row['attrs']) {
            if (count($row['attrs']) > 60) $warn[] = ['attrs_cut', ['n' => 60]];
            $d['attrs'] = array_slice($row['attrs'], 0, 60);
        }
        if (!$existing && !isset($d['sku']) && !isset($d['barcode'])) $warn[] = ['no_key', []];

        if ($err) return ['action' => 'error', 'errors' => $err, 'warnings' => $warn, 'd' => $d];
        return ['action' => $existing ? 'update' : 'create', 'errors' => [], 'warnings' => $warn, 'd' => $d];
    }

    /** Imported characteristics replace the stored ones with the same section+name; the rest stay. */
    public static function mergeSpecs(array $old, array $new): array
    {
        $idx = [];
        foreach ($old as $i => $a) $idx[self::key(($a['section'] ?? '') . '|' . ($a['name'] ?? ''))] = $i;
        foreach ($new as $a) {
            $k = self::key($a['section'] . '|' . $a['name']);
            if (isset($idx[$k])) $old[$idx[$k]] = $a; else { $old[] = $a; $idx[$k] = count($old) - 1; }
        }
        return array_values($old);
    }

    /* ---------------- database ---------------- */

    /** The owner's live product for this row: SKU, else barcode, else (rows with neither) the exact name. */
    public static function find(Db $db, int $owner, array $row): ?array
    {
        $v = $row['v'];
        if (trim((string)($v['sku'] ?? '')) !== '') return $db->row('SELECT * FROM {p}wms_products WHERE owner_id = ? AND archived = 0 AND sku = ? ORDER BY id LIMIT 1', [$owner, trim((string)$v['sku'])]);
        if (trim((string)($v['barcode'] ?? '')) !== '') return $db->row('SELECT * FROM {p}wms_products WHERE owner_id = ? AND archived = 0 AND barcode = ? ORDER BY id LIMIT 1', [$owner, trim((string)$v['barcode'])]);
        $n = trim((string)($v['name'] ?? ''));
        return $n === '' ? null : $db->row("SELECT * FROM {p}wms_products WHERE owner_id = ? AND archived = 0 AND (sku IS NULL OR sku = '') AND (barcode IS NULL OR barcode = '') AND LOWER(name) = LOWER(?) ORDER BY id LIMIT 1", [$owner, $n]);
    }

    private static function groupId(Db $db, string $name): int
    {
        static $cache = [];
        $k = self::key($name);
        if (!isset($cache[$k])) {
            $id = (int)$db->val('SELECT id FROM {p}wms_groups WHERE LOWER(name) = LOWER(?) ORDER BY id LIMIT 1', [$name]);
            if (!$id) { $db->q('INSERT INTO {p}wms_groups (name) VALUES (?)', [$name]); $id = $db->insertId(); }
            $cache[$k] = $id;
        }
        return $cache[$k];
    }

    private static function nextCode(Db $db): string
    {
        $k = 'wms.counter.product';
        $db->q('INSERT IGNORE INTO {p}settings (k, v) VALUES (?, ?)', [$k, '0']);
        $db->q('UPDATE {p}settings SET v = LAST_INSERT_ID(CAST(v AS UNSIGNED) + 1) WHERE k = ?', [$k]);
        return str_pad((string)$db->val('SELECT LAST_INSERT_ID()'), 5, '0', STR_PAD_LEFT);
    }

    /** Writes one planned row. @return array{id:int,created:bool} */
    public static function apply(Db $db, int $owner, array $plan, ?array $p): array
    {
        $d = $plan['d'];
        $specs = json_decode((string)($p['specs'] ?? '[]'), true) ?: [];
        if (!empty($d['attrs'])) $specs = self::mergeSpecs($specs, $d['attrs']);
        $images = isset($d['images']) ? W::images(implode("\n", $d['images'])) : (string)($p['images'] ?? '');
        $group = isset($d['group']) ? self::groupId($db, $d['group']) : ($p['group_id'] ?? null);
        $vals = [
            'name' => $d['name'] ?? $p['name'], 'sku' => $d['sku'] ?? ($p['sku'] ?? null), 'barcode' => $d['barcode'] ?? ($p['barcode'] ?? null),
            'unit' => $d['unit'] ?? ($p['unit'] ?? 'шт'), 'buy_price' => $d['buy'] ?? ($p['buy_price'] ?? 0), 'sell_price' => $d['sell'] ?? ($p['sell_price'] ?? 0),
            'promo_price' => $d['promo'] ?? ($p['promo_price'] ?? 0), 'min_stock' => $d['min'] ?? ($p['min_stock'] ?? 0),
            'description' => $d['desc'] ?? ($p['description'] ?? null), 'short_desc' => $d['short'] ?? ($p['short_desc'] ?? null),
            'images' => $images !== '' ? $images : null, 'specs' => $specs ? json_encode($specs, JSON_UNESCAPED_UNICODE) : null, 'group_id' => $group,
        ];
        if ($p) {
            $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($vals)));
            $db->q("UPDATE {p}wms_products SET $set WHERE id = ? AND owner_id = ?", [...array_values($vals), $p['id'], $owner]);
            return ['id' => (int)$p['id'], 'created' => false];
        }
        $cols = implode(', ', array_keys($vals));
        $db->q("INSERT INTO {p}wms_products (owner_id, code, source, $cols) VALUES (?,?,?," . implode(',', array_fill(0, count($vals), '?')) . ')', [$owner, self::nextCode($db), 'import', ...array_values($vals)]);
        return ['id' => $db->insertId(), 'created' => true];
    }

    /* ---------------- working files (uploaded file + parsed state), private to the owner ---------------- */

    public static function dir(): string { $d = SF_ROOT . '/storage/wms-import'; if (!is_dir($d)) @mkdir($d, 0755, true); return $d; }
    public static function path(int $owner, string $token, string $ext): ?string { return preg_match('/^[a-f0-9]{24}$/', $token) ? self::dir() . "/$owner-$token.$ext" : null; }
    public static function load(int $owner, string $token): ?array
    {
        $f = self::path($owner, $token, 'json');
        if (!$f || !is_file($f)) return null;
        $j = json_decode((string)file_get_contents($f), true);
        return is_array($j) ? $j : null;
    }
    public static function save(int $owner, string $token, array $job): void
    {
        $f = self::path($owner, $token, 'json');
        if ($f) file_put_contents($f, json_encode($job, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), LOCK_EX);
    }
    public static function sweep(): void
    {
        foreach (glob(self::dir() . '/*') ?: [] as $f) if (is_file($f) && filemtime($f) < time() - 2 * 86400) @unlink($f);
    }
}
