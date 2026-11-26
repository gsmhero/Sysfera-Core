<?php
declare(strict_types=1);
namespace SysferaBlog;

/** Small pure helpers: text, covers, Basebase product codes. Works without mbstring. */
final class Text
{
    public const TITLE_MAX = 255, BODY_MAX = 20000, EXCERPT = 220, MAX_PRODUCTS = 24, MAX_COVER = 5 * 1024 * 1024;

    /** Length and cut in characters (UTF-8). */
    public static function len(string $s): int { return (int)preg_match_all('/./us', $s); }
    public static function cut(string $s, int $max): string { return preg_match('/^.{0,' . $max . '}/us', $s, $m) ? $m[0] : substr($s, 0, $max); }

    /** One line, no control characters. */
    public static function line(string $s, int $max): string { return self::cut(trim((string)preg_replace('/[\x00-\x1F\x7F]+/', ' ', $s)), $max); }

    /** The short version of a post. */
    public static function excerpt(string $body, int $max = self::EXCERPT): string
    {
        $s = trim((string)preg_replace('/\s+/u', ' ', $body));
        if (self::len($s) <= $max) return $s;
        $c = self::cut($s, $max - 1);
        $w = (string)preg_replace('/\s+\S*$/u', '', $c);
        return rtrim($w !== '' && strlen($w) > strlen($c) / 2 ? $w : $c) . '…';
    }

    /** A cover file this plugin stored itself (random name, so a draft's picture cannot be guessed). */
    public static function coverName(string $s): bool { return (bool)preg_match('/^[a-f0-9]{32}\.(jpg|png|webp|gif)$/D', $s); }

    /** A picture hosted by Basebase (the only foreign address that is passed around between sites). */
    public static function basebaseUrl(string $u): bool { return strlen($u) <= 500 && (bool)preg_match('~^https://(www\.)?basebase\.ru/[A-Za-z0-9._/%?=&-]*$~D', $u); }

    public static function httpUrl(string $u): bool { return (bool)preg_match('~^https?://[^\s<>"\'\\\\]+$~D', $u); }

    /** Basebase product number from the product address the connector remembers, e.g. https://www.basebase.ru/product/15 */
    public static function productId(string $url): int { return self::httpUrl($url) && preg_match('~/product/(\d{1,10})(?:[?#].*)?$~D', $url, $m) ? (int)$m[1] : 0; }

    public static function origin(string $url): string
    {
        $p = parse_url($url);
        return $p && !empty($p['host']) && in_array($p['scheme'] ?? '', ['https', 'http'], true) ? $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '') : '';
    }

    /** The product embed code, character for character what the "Embed" button of the product page on basebase.ru copies. */
    public static function embedCode(string $productUrl): string
    {
        $id = self::productId($productUrl); $host = self::origin($productUrl);
        if (!$id || $host === '') return '';
        return '<iframe src="' . $host . '/embed/product/' . $id . '" width="100%" height="260" style="border:0;max-width:720px" loading="lazy" title="basebase.ru"></iframe><script async src="' . $host . '/assets/embed.js"></script>';
    }

    /** @return list<int> unique positive numbers */
    public static function ids(mixed $v): array
    {
        $v = is_string($v) ? json_decode($v, true) : $v;
        $out = [];
        foreach (is_array($v) ? $v : [] as $x) if (is_scalar($x) && (int)$x > 0) $out[(int)$x] = (int)$x;
        return array_slice(array_values($out), 0, self::MAX_PRODUCTS);
    }

    /** Type of an uploaded picture by its content: extension, or '' when it is not an accepted picture. */
    public static function imageExt(string $head): string
    {
        if (str_starts_with($head, "\x89PNG\r\n")) return 'png';
        if (str_starts_with($head, "\xFF\xD8\xFF")) return 'jpg';
        if (str_starts_with($head, 'GIF87a') || str_starts_with($head, 'GIF89a')) return 'gif';
        if (str_starts_with($head, 'RIFF') && substr($head, 8, 4) === 'WEBP') return 'webp';
        return '';
    }
}
