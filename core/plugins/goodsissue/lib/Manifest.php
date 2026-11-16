<?php
declare(strict_types=1);
namespace SysferaGi;

/** Validation and checksum of a transfer order (manifest). */
final class Manifest
{
    public static function uuid(mixed $v, string $field = 'uuid'): string
    {
        if (!is_string($v) || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $v)) throw new Err('bad_uuid', "$field must be a UUID", 422);
        return strtolower($v);
    }
    public static function newUuid(): string
    {
        $b = random_bytes(16); $b[6] = chr((ord($b[6]) & 0x0f) | 0x40); $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }

    /** @return array{orders: list<array>, items: list<array>, checksum: string} cleaned data */
    public static function parse(array $in): array
    {
        self::uuid($in['uuid'] ?? null);
        Units::declared($in['units'] ?? null);
        $orders = []; $items = []; $seen = [];
        if (!is_array($in['orders'] ?? null) || !$in['orders']) throw new Err('empty_manifest', 'a manifest needs at least one order', 422);
        foreach ($in['orders'] as $o) {
            $ou = self::uuid($o['uuid'] ?? null, 'order.uuid');
            if (isset($orders[$ou])) throw new Err('duplicate_order', "order $ou is listed twice", 422);
            if (!is_array($o['items'] ?? null) || !$o['items']) throw new Err('empty_order', "order $ou has no items", 422);
            $orders[$ou] = ['uuid' => $ou, 'number' => mb_substr(trim((string)($o['number'] ?? '')), 0, 40), 'customer_name' => mb_substr(trim((string)($o['customer_name'] ?? '')), 0, 160),
                'customer_phone' => mb_substr(trim((string)($o['customer_phone'] ?? '')), 0, 40), 'customer_email' => mb_substr(trim((string)($o['customer_email'] ?? '')), 0, 160), 'total_rub' => Units::rub($o['total_rub'] ?? null, 'total_rub')];
            $sum = 0;
            foreach ($o['items'] as $it) {
                $bc = trim((string)($it['barcode'] ?? ''));
                if (!preg_match('/^[A-Za-z0-9._\-]{3,64}$/', $bc)) throw new Err('bad_barcode', "barcode '$bc' is not valid", 422);
                if (isset($seen[$bc])) throw new Err('duplicate_barcode', "barcode $bc is used twice in the manifest", 422);
                $seen[$bc] = 1;
                $price = Units::rub($it['price_rub'] ?? null, 'price_rub'); $qty = Units::qty($it['qty'] ?? null);
                $sum += Units::kop($price) * $qty;
                $items[] = ['order_uuid' => $ou, 'sku' => mb_substr(trim((string)($it['sku'] ?? '')), 0, 64), 'name' => mb_substr(trim((string)($it['name'] ?? '')), 0, 190), 'qty' => $qty,
                    'weight_g' => Units::grams($it['weight_g'] ?? null), 'barcode' => $bc, 'price_rub' => $price, 'received_qty' => 0, 'state' => 'expected'];
            }
            if ($sum !== Units::kop($orders[$ou]['total_rub'])) throw new Err('total_mismatch', "order $ou: total_rub does not equal the sum of its items", 422);
        }
        $sum = self::checksum($items);
        if (isset($in['checksum']) && !hash_equals($sum, strtolower((string)$in['checksum']))) throw new Err('checksum_mismatch', 'checksum does not match the contents', 422);
        return ['orders' => array_values($orders), 'items' => $items, 'checksum' => $sum];
    }

    /** SHA-256 of the contents: items sorted by barcode, only the fields the warehouse can verify physically */
    public static function checksum(array $items): string
    {
        usort($items, fn($a, $b) => strcmp($a['barcode'], $b['barcode']));
        $c = array_map(fn($i) => [$i['order_uuid'], $i['sku'], (int)$i['qty'], (int)$i['weight_g'], $i['barcode'], $i['price_rub']], $items);
        return hash('sha256', json_encode($c, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
