<?php
declare(strict_types=1);
namespace SysferaBb;

/** What other components may know about a user's Basebase shop: the shop itself and its published products. Read only. */
final class Shop
{
    /** SQL parts for the published products. @param array $o owner (int), owners (list), q, category, min, max @return array{0:string,1:array} condition and parameters */
    public static function where(array $o): array
    {
        $w = ['m.published = 1', 'm.synced_at IS NOT NULL', "m.bb_url IS NOT NULL", "m.bb_url <> ''", 'p.archived = 0', 'p.owner_id IS NOT NULL', "u.status = 'active'"]; $a = [];
        if (isset($o['owner'])) { $w[] = 'p.owner_id = ?'; $a[] = (int)$o['owner']; }
        $like = fn(string $q): string => '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
        if (trim((string)($o['q'] ?? '')) !== '') { $w[] = '(p.name LIKE ? OR p.sku LIKE ?)'; array_push($a, $like(trim((string)$o['q'])), $like(trim((string)$o['q']))); }
        if ((string)($o['category'] ?? '') !== '') { $w[] = 'm.category = ?'; $a[] = (string)$o['category']; }
        if (is_numeric($o['min'] ?? null)) { $w[] = 'p.sell_price >= ?'; $a[] = (float)$o['min']; }
        if (is_numeric($o['max'] ?? null)) { $w[] = 'p.sell_price <= ?'; $a[] = (float)$o['max']; }
        return [implode(' AND ', $w), $a];
    }

    public static function product(array $r): array
    {
        $img = Bb::images((string)(($r['images'] ?? '') !== '' ? $r['images'] : ($r['p_images'] ?? '')));
        return ['bb_id' => preg_match('~/product/(\d+)~', (string)$r['bb_url'], $m) ? (int)$m[1] : 0, 'owner_id' => (int)$r['owner_id'], 'title' => (string)$r['name'], 'price' => (float)$r['sell_price'],
            'image' => $img[0] ?? '', 'url' => (string)$r['bb_url'], 'category' => (string)($r['category'] ?? ''), 'sku' => (string)($r['sku'] ?? '')];
    }
}
