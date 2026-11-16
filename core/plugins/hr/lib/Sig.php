<?php
declare(strict_types=1);
namespace SysferaHr;

/** HMAC-SHA256 transport signatures of the machine API (same scheme as Goods issue). Request: ts, method, path, sha256(body). Answer: ts, status, sha256(body). Window 5 minutes. */
final class Sig
{
    public const WINDOW = 300;
    public static function request(string $key, string $ts, string $method, string $path, string $body): string { return hash_hmac('sha256', $ts . "\n" . strtoupper($method) . "\n" . $path . "\n" . hash('sha256', $body), $key); }
    public static function response(string $key, string $ts, int $status, string $body): string { return hash_hmac('sha256', $ts . "\n" . $status . "\n" . hash('sha256', $body), $key); }
    public static function fresh(string $ts, int $now): bool { return ctype_digit($ts) && abs($now - (int)$ts) <= self::WINDOW; }
    public static function ok(string $expected, string $given): bool { return $given !== '' && hash_equals($expected, strtolower($given)); }
}
