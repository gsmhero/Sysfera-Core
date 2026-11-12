<?php
declare(strict_types=1);
namespace SysferaMsg;

/** Request signing between an installation and the hub. Pure. */
final class Sig
{
    /** HMAC over: timestamp, method, request path+query, local user id and the SHA-256 of the body (so big uploads need not be held twice). */
    public static function sign(string $key, int $ts, string $method, string $uri, string $uid, string $body): string
    {
        return hash_hmac('sha256', $ts . "\n" . strtoupper($method) . "\n" . $uri . "\n" . $uid . "\n" . hash('sha256', $body), $key);
    }

    public static function verify(string $key, int $ts, string $method, string $uri, string $uid, string $body, string $sig, ?int $now = null): bool
    {
        if ($key === '' || abs(($now ?? time()) - $ts) > 300) return false;
        return hash_equals(self::sign($key, $ts, $method, $uri, $uid, $body), $sig);
    }
}

/** Small text helpers. Pure. */
final class Text
{
    /** Number of words (any run of letters/digits/apostrophes/hyphens; works for Russian too). */
    public static function words(string $s): int
    {
        return preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}\'’_-]*/u', $s) ?: 0;
    }

    /** Activity limits: title up to 20 words, text up to 50 words. Returns an error key or null. */
    public static function checkPost(string $title, string $body): ?string
    {
        $title = trim($title); $body = trim($body);
        if (self::words($title) < 1) return 'err_title_empty';
        if (self::words($title) > Activity::TITLE_WORDS) return 'err_title_long';
        if (self::words($body) < 1) return 'err_body_empty';
        if (self::words($body) > Activity::BODY_WORDS) return 'err_body_long';
        if (strlen($title) > 400 || strlen($body) > 1200) return 'err_chars';
        return null;
    }

    /** Single-line display name. */
    public static function name(string $s): string
    {
        $s = trim((string)preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $s));
        return function_exists('mb_substr') ? mb_substr($s, 0, 80) : substr($s, 0, 80);
    }

    /** Host name from a base URL like https://shop.example.ru/sf → shop.example.ru, or null when it is not an acceptable public name. */
    public static function domain(string $base): ?string
    {
        $p = parse_url(trim($base));
        if (!$p || ($p['scheme'] ?? '') !== 'https' || empty($p['host']) || isset($p['port']) || isset($p['user']) || isset($p['query'])) return null;
        $h = strtolower((string)$p['host']);
        if (!preg_match('/^(?=.{4,190}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,24}$/', $h) && !preg_match('/^(?=.{4,190}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+xn--[a-z0-9-]{2,40}$/', $h)) return null;
        $path = (string)($p['path'] ?? '');
        if ($path !== '' && $path !== '/' && !preg_match('~^(/[A-Za-z0-9_-][A-Za-z0-9._-]{0,59}){1,4}/?$~', $path)) return null;
        return $h;
    }

    /** Allowed attachment types: extension => MIME type used when the file is served (the browser's guess is never trusted). */
    public const FILES = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp',
        'pdf' => 'application/pdf', 'txt' => 'text/plain', 'csv' => 'text/csv',
        'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint', 'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'odt' => 'application/vnd.oasis.opendocument.text', 'ods' => 'application/vnd.oasis.opendocument.spreadsheet', 'zip' => 'application/zip',
    ];
    public const MAX_FILE = 5 * 1024 * 1024;

    /** Clean file name + its MIME type, or null if the type is not allowed. @return array{0:string,1:string}|null */
    public static function file(string $name): ?array
    {
        $name = trim((string)preg_replace('/[\x00-\x1F\x7F\/\\\\:*?"<>|]+/u', '_', basename(str_replace('\\', '/', $name))));
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($name === '' || !isset(self::FILES[$ext]) || preg_match('/\.(php\d?|phtml|phar|html?|js|exe|sh|bat|svg)\./i', $name)) return null;
        if (strlen($name) > 120) $name = substr(pathinfo($name, PATHINFO_FILENAME), 0, 100) . '.' . $ext;
        return [$name, self::FILES[$ext]];
    }
}

/** Limits of Sysfera Activity (used by the shared word counter above). */
final class Activity
{
    public const TITLE_WORDS = 20, BODY_WORDS = 50, POSTS_PER_DAY = 3, HIDE_AFTER_REPORTS = 3;
}
