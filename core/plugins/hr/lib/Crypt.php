<?php
declare(strict_types=1);
namespace SysferaHr;

/**
 * Field encryption at rest (salary, passport, scans): authenticated, needs only the always-present hash extension.
 * Encrypt-then-MAC: keystream = HMAC-SHA256(encKey, nonce || counter) blocks, tag = HMAC-SHA256(macKey, version || nonce || ciphertext).
 * Subkeys are derived from the master key (HKDF-like), a fresh 16-byte nonce per value. Format: "h1:" . base64(nonce . tag . ciphertext).
 */
final class Crypt
{
    public const PREFIX = 'h1:';

    public static function seal(string $plain, string $key): string
    {
        $nonce = random_bytes(16);
        $ct = $plain ^ self::stream($nonce, strlen($plain), self::sub($key, 'enc'));
        $tag = hash_hmac('sha256', self::PREFIX . $nonce . $ct, self::sub($key, 'mac'), true);
        return self::PREFIX . base64_encode($nonce . $tag . $ct);
    }
    public static function open(string $sealed, string $key): ?string
    {
        if (!str_starts_with($sealed, self::PREFIX)) return null;
        $raw = base64_decode(substr($sealed, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) < 48) return null;
        $nonce = substr($raw, 0, 16); $tag = substr($raw, 16, 32); $ct = substr($raw, 48);
        if (!hash_equals(hash_hmac('sha256', self::PREFIX . $nonce . $ct, self::sub($key, 'mac'), true), $tag)) return null;
        return $ct ^ self::stream($nonce, strlen($ct), self::sub($key, 'enc'));
    }
    public static function isSealed(string $v): bool { return str_starts_with($v, self::PREFIX); }
    public static function newKey(): string { return bin2hex(random_bytes(32)); }
    private static function sub(string $key, string $label): string { return hash_hmac('sha256', 'sysfera-hr|' . $label, $key, true); }
    private static function stream(string $nonce, int $len, string $k): string
    {
        $out = ''; for ($i = 0; strlen($out) < $len; $i++) $out .= hash_hmac('sha256', $nonce . pack('N', $i), $k, true);
        return substr($out, 0, $len);
    }
}
