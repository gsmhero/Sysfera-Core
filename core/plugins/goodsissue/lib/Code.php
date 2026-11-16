<?php
declare(strict_types=1);
namespace SysferaGi;

/** One-time verification codes: 6 digits, bound to ONE order, limited lifetime and attempts. Only a keyed hash is stored. */
final class Code
{
    public const MAX_ATTEMPTS = 5;

    public static function make(): string { return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT); }
    public static function hash(string $secret, string $orderUuid, string $code): string { return hash_hmac('sha256', $orderUuid . ':' . trim($code), $secret); }

    /** @return null if usable, else the reason */
    public static function problem(?array $rec, int $now): ?string
    {
        if (!$rec) return 'code_missing';
        if (!empty($rec['used_at'])) return 'code_used';
        if ((int)$rec['expires_at'] < $now) return 'code_expired';
        if ((int)$rec['attempts'] >= self::MAX_ATTEMPTS) return 'code_locked';
        return null;
    }
}
