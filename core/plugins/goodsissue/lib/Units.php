<?php
declare(strict_types=1);
namespace SysferaGi;

/**
 * Explicit unit validation: weights are WHOLE GRAMS, money is decimal RUBLES with at most 2 digits ("1250.50").
 * A kilogram value sent as grams (or a float for money) is a known integration failure class, so nothing is guessed:
 * floats, unit suffixes ("1.2kg") and negative numbers are refused.
 */
final class Units
{
    public const MAX_GRAMS = 2_000_000;   // 2 tonnes in one position is already a mistake

    public static function grams(mixed $v, string $field = 'weight_g'): int
    {
        if (is_string($v) && preg_match('/^\d{1,8}$/', $v)) $v = (int)$v;
        if (!is_int($v) || $v < 0 || $v > self::MAX_GRAMS) throw new Err('unit_weight', "$field must be a whole number of grams (0.." . self::MAX_GRAMS . ')', 422);
        return $v;
    }

    /** @return string normalised "1250.50" */
    public static function rub(mixed $v, string $field = 'price_rub'): string
    {
        if (is_int($v) && $v >= 0) $v = (string)$v;
        if (!is_string($v) || !preg_match('/^\d{1,10}(\.\d{1,2})?$/', $v)) throw new Err('unit_money', "$field must be a decimal string in rubles with at most 2 digits, e.g. \"1250.50\"", 422);
        return self::fmt(self::kop($v));
    }

    public static function kop(string $rub): int
    {
        [$a, $b] = array_pad(explode('.', $rub, 2), 2, '0');
        return (int)$a * 100 + (int)str_pad($b, 2, '0');
    }
    public static function fmt(int $kop): string { return intdiv($kop, 100) . '.' . str_pad((string)($kop % 100), 2, '0', STR_PAD_LEFT); }

    public static function qty(mixed $v, string $field = 'qty', bool $zero = false): int
    {
        if (is_string($v) && preg_match('/^\d{1,6}$/', $v)) $v = (int)$v;
        if (!is_int($v) || $v < ($zero ? 0 : 1) || $v > 100000) throw new Err('unit_qty', "$field must be a whole number of pieces", 422);
        return $v;
    }

    /** the manifest declares its units; anything else than grams / RUB is refused */
    public static function declared(mixed $u): void
    {
        if (!is_array($u) || ($u['weight'] ?? null) !== 'g' || ($u['money'] ?? null) !== 'RUB') throw new Err('unit_mismatch', 'units must be {"weight":"g","money":"RUB"}', 422);
    }
}
