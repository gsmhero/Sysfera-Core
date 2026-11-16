<?php
declare(strict_types=1);
namespace SysferaHr;

/** Work schedules, leave arithmetic and the approval chain (manager -> HR). Pure functions. */
final class TimeOff
{
    public const KINDS = ['vacation', 'sick', 'unpaid'];
    /** pending_manager -> pending_hr -> approved ; rejected / cancelled are final */
    public const STATUSES = ['pending_manager', 'pending_hr', 'approved', 'rejected', 'cancelled'];
    public const DEFAULT_WEEK = [1 => 8, 2 => 8, 3 => 8, 4 => 8, 5 => 8, 6 => 0, 7 => 0];

    /** @return array<int,float> ISO weekday (1 = Monday) => hours */
    public static function week(mixed $json): array
    {
        $w = is_array($json) ? $json : (array)json_decode((string)$json, true); $out = [];
        for ($d = 1; $d <= 7; $d++) { $h = $w[$d] ?? $w[(string)$d] ?? 0; $out[$d] = max(0.0, min(24.0, (float)$h)); }
        return $out;
    }
    /** working days (hours > 0) and hours of [from, to] inclusive, minus the dates in $skip (holidays, approved leave) */
    public static function span(array $week, string $from, string $to, array $skip = []): array
    {
        $a = strtotime($from . ' 12:00:00'); $b = strtotime($to . ' 12:00:00'); $days = 0; $hours = 0.0;
        if ($a === false || $b === false || $b < $a || ($b - $a) > 400 * 86400) return ['days' => 0, 'hours' => 0.0];
        for ($t = $a; $t <= $b; $t += 86400) {
            if (in_array(date('Y-m-d', $t), $skip, true)) continue;
            $h = $week[(int)date('N', $t)]; if ($h > 0) { $days++; $hours += $h; }
        }
        return ['days' => $days, 'hours' => $hours];
    }
    public static function dates(string $from, string $to): array
    {
        $out = []; $a = strtotime($from . ' 12:00:00'); $b = strtotime($to . ' 12:00:00'); if ($a === false || $b === false) return [];
        for ($t = $a; $t <= $b && count($out) < 400; $t += 86400) $out[] = date('Y-m-d', $t); return $out;
    }
    public static function overlap(string $a1, string $a2, string $b1, string $b2): bool { return $a1 <= $b2 && $b1 <= $a2; }

    /** vacation days accrued from the hire date: annual/12 per full month, capped at the year-end rule of the caller; returns tenths-rounded float */
    public static function accrued(string $hire, string $asOf, float $annual): float
    {
        $h = new \DateTimeImmutable($hire); $n = new \DateTimeImmutable($asOf); if ($n < $h) return 0.0;
        $d = $h->diff($n); $months = $d->y * 12 + $d->m;
        return round($annual * $months / 12, 1);
    }
    public static function balance(string $hire, string $asOf, float $annual, float $usedDays, float $carry = 0.0): array
    {
        $acc = self::accrued($hire, $asOf, $annual) + $carry;
        return ['accrued' => round($acc, 1), 'used' => round($usedDays, 1), 'available' => round($acc - $usedDays, 1)];
    }

    /** next status of the chain; $kind sick leave still needs HR to confirm but not the manager's consent... it follows the same chain for simplicity. */
    public static function next(string $status, bool $hasManager): string
    {
        return match ($status) { 'pending_manager' => 'pending_hr', 'pending_hr' => 'approved', default => throw new Err('bad_state', "cannot approve a request that is $status", 409) };
    }
    public static function first(bool $hasManager): string { return $hasManager ? 'pending_manager' : 'pending_hr'; }
}
