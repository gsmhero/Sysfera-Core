<?php
declare(strict_types=1);
namespace SysferaBb;

/** Pure protocol helpers for Basebase Partner API v1 (docs/BASEBASE-SYSFERA-API-v1.md): signing and payload mapping. No database, no network. */
final class Bb
{
    public const MAX_AGE = 300;

    /** hex(HMAC-SHA256(secret, ts "\n" METHOD "\n" uri "\n" body)); $uri is path + query exactly as the other side sees it. */
    public static function sign(string $secret, int $ts, string $method, string $uri, string $body): string
    {
        return hash_hmac('sha256', $ts . "\n" . strtoupper($method) . "\n" . $uri . "\n" . $body, $secret);
    }

    public static function verify(string $secret, int $ts, string $method, string $uri, string $body, string $sig, ?int $now = null): bool
    {
        if ($secret === '' || $sig === '' || abs(($now ?? time()) - $ts) > self::MAX_AGE) return false;
        return hash_equals(self::sign($secret, $ts, $method, $uri, $body), $sig);
    }

    /** Stable id of a product on Basebase: explicit value, else SKU, else code, else wms-<id>. Only [A-Za-z0-9._-]. */
    public static function externalId(array $p, ?string $explicit = null): string
    {
        foreach ([$explicit, $p['sku'] ?? null, $p['code'] ?? null] as $v) {
            $v = preg_replace('/[^A-Za-z0-9._-]+/', '-', trim((string)$v)); $v = trim((string)$v, '-');
            if ($v !== '') return mb_substr($v, 0, 120);
        }
        return 'wms-' . (int)$p['id'];
    }

    /** Whole units that can be promised to buyers. */
    public static function stockUnits(float $available): int { return max(0, (int)floor($available + 0.0000001)); }

    /** What the seller receives per unit: price × percent. Percent ≥ 100 means "no promo". */
    public static function promo(float $price, int $pct): ?float
    {
        if ($pct <= 0 || $pct >= 100 || $price <= 0) return null;
        return round($price * $pct / 100, 2);
    }

    /** @return string[] image URLs (https only, max 8) from a newline/comma separated text */
    public static function images(?string $text): array
    {
        $out = [];
        foreach (preg_split('/[\s,]+/', (string)$text, -1, PREG_SPLIT_NO_EMPTY) as $u) if (preg_match('~^https://[^\s]+$~i', $u)) $out[] = $u;
        return array_slice(array_values(array_unique($out)), 0, 8);
    }

    /** Problems that make a product unpublishable (translation keys). */
    public static function problems(array $p, array $map): array
    {
        $e = [];
        if (trim((string)($p['name'] ?? '')) === '') $e[] = 'err_name';
        if ((float)($p['sell_price'] ?? 0) <= 0) $e[] = 'err_price';
        if (trim((string)($map['category'] ?? '')) === '') $e[] = 'err_category';
        return $e;
    }

    /** Body of PUT /sellers/{id}/products/{external_id} */
    public static function product(array $p, array $map, float $available, int $pct): array
    {
        $price = round((float)$p['sell_price'], 2);
        $fields = [];
        if (!empty($p['sku'])) $fields['Артикул'] = (string)$p['sku'];
        if (!empty($p['barcode'])) $fields['Штрихкод'] = (string)$p['barcode'];
        if ((float)($p['weight'] ?? 0) > 0) $fields['Вес, кг'] = rtrim(rtrim(number_format((float)$p['weight'], 3, '.', ''), '0'), '.');
        $body = ['title' => (string)$p['name'], 'description' => (string)($p['description'] ?? ''), 'category' => (string)$map['category'], 'price' => $price,
            'stock' => self::stockUnits($available), 'sku' => (string)$map['external_id'], 'fields' => $fields, 'images' => self::images($map['images'] ?? '')];
        if (($promo = self::promo($price, $pct)) !== null) $body['promo_price'] = $promo;
        return $body;
    }

    /** Normalised order item from either the polling API item or a webhook `order.created` payload. */
    public static function item(array $x): array
    {
        $c = (array)($x['customer'] ?? []);
        $pr = (array)($x['product'] ?? []);
        return [
            'item_id' => (int)($x['item_id'] ?? 0), 'order_id' => (int)($x['order_id'] ?? 0),
            'external_id' => (string)($x['external_id'] ?? $pr['external_id'] ?? ''), 'title' => (string)($x['title'] ?? $pr['title'] ?? ''), 'variant' => (string)($x['variant'] ?? $pr['variant'] ?? ''),
            'qty' => (float)($x['qty'] ?? 0), 'price' => (float)($x['price'] ?? 0), 'seller_receives' => (float)($x['seller_receives'] ?? 0), 'status' => (string)($x['status'] ?? 'pending'),
            'customer' => ['name' => trim((string)($c['name'] ?? '')), 'phone' => trim((string)($c['phone'] ?? '')), 'address' => trim((string)($c['address'] ?? '')),
                'delivery' => trim((string)($c['delivery'] ?? '')), 'notes' => trim((string)($c['notes'] ?? ''))],
        ];
    }

    /** Text for the WMS order comment. */
    public static function comment(array $it): string
    {
        $c = $it['customer'];
        $parts = ['Basebase #' . $it['order_id'] . ' / ' . $it['item_id'], $c['name'], $c['phone'], $c['address'], $c['delivery'] !== '' ? 'Доставка: ' . $c['delivery'] : '', $c['notes'] !== '' ? $c['notes'] : ''];
        return implode("\n", array_filter($parts, fn($s) => $s !== ''));
    }

    /** Basebase statuses after which the reserve should be released if nothing was shipped yet. */
    public static function releasesReserve(string $status): bool { return in_array($status, ['returned', 'cancelled', 'canceled'], true); }

    /** Parse a flat list of Basebase categories ([{id,name,children:[{id,name}]}]) into names usable in a datalist. */
    public static function categoryNames(array $tree): array
    {
        $out = [];
        foreach ($tree as $c) {
            if (!empty($c['name'])) $out[] = (string)$c['name'];
            foreach ((array)($c['children'] ?? []) as $ch) if (!empty($ch['name'])) $out[] = (string)$ch['name'];
        }
        return array_values(array_unique($out));
    }
}
