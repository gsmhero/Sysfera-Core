<?php
declare(strict_types=1);
namespace SysferaBlog;

/** The Products tab of an author's public profile: the filter sidebar. Pure. */
final class Profile
{
    public const PER_PAGE = 24, ABOUT_MAX = 600;
    public const TABS = ['posts', 'products', 'sets'];

    private static function lower(string $s): string { return function_exists('mb_strtolower') ? mb_strtolower($s) : strtolower($s); }

    /** The filter as it came in the address: q (name or SKU), min, max (price), cat (category). */
    public static function filters(array $in): array
    {
        $s = fn(string $k): string => is_string($in[$k] ?? null) ? Text::line($in[$k], 120) : '';
        $price = function (string $v): string { $v = str_replace([' ', ','], ['', '.'], $v); return is_numeric($v) && (float)$v >= 0 && (float)$v < 1e12 ? $v : ''; };
        return ['q' => $s('q'), 'min' => $price($s('min')), 'max' => $price($s('max')), 'cat' => $s('cat')];
    }

    public static function filter(array $products, array $f): array
    {
        $q = self::lower($f['q'] ?? '');
        return array_values(array_filter($products, fn(array $p): bool =>
            ($q === '' || str_contains(self::lower($p['title'] . ' ' . ($p['sku'] ?? '')), $q))
            && (($f['min'] ?? '') === '' || (float)$p['price'] >= (float)$f['min'])
            && (($f['max'] ?? '') === '' || (float)$p['price'] <= (float)$f['max'])
            && (($f['cat'] ?? '') === '' || ($p['category'] ?? '') === $f['cat'])));
    }

    /** @return array<string,int> category => number of products, alphabetically */
    public static function categories(array $products): array
    {
        $c = [];
        foreach ($products as $p) if (($p['category'] ?? '') !== '') $c[$p['category']] = ($c[$p['category']] ?? 0) + 1;
        ksort($c);
        return $c;
    }

    /** People and shops found by the site search: shops with products and authors of posts, one line per user. */
    public static function people(array $shops, array $authors): array
    {
        $out = [];
        foreach ($shops as $s) $out[(int)$s['owner_id']] = ['uid' => (int)$s['owner_id'], 'name' => (string)$s['shop_name'], 'products' => (int)$s['products'], 'posts' => 0];
        foreach ($authors as $a) { $out[$a['uid']] ??= ['uid' => $a['uid'], 'name' => $a['name'], 'products' => 0, 'posts' => 0]; $out[$a['uid']]['posts'] = $a['posts']; }
        return array_values($out);
    }

    /** Somebody has a public profile once something of his is public; the person and administrators always see it. */
    public static function visible(?string $name, int $posts, int $products, bool $own): bool { return $name !== null && ($own || $posts > 0 || $products > 0); }
}
