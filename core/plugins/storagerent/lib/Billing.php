<?php
declare(strict_types=1);
namespace SysferaStoragerent;

/**
 * The pure billing rules of Storage Rent (no database, no clock). Money is whole kopecks (int); dates are 'YYYY-MM-DD' strings; percentages are given as
 * decimal numbers (0.5 = 0.5 %). Everything that touches stored data is in Ledger.php and calls these functions.
 *
 * Conventions (documented in the plugin's README section of the report):
 *  - a contract rate is per month ("500 RUB per m2 per month"); a billing period of N months bills N times the monthly amount;
 *  - an invoice is generated for a period on the 1st day of the period's first month and falls due on the contract's due day of that month;
 *  - a payment received on day X counts as paid for day X, so the penalty of day X is not charged on it;
 *  - the penalty starts on day 1 after (due date + grace days) and is charged on the unpaid part of the invoice, day by day, never on VAT-free penalties themselves.
 */
final class Billing
{
    public const RATE_TYPES = ['m2', 'm3', 'pallet', 'fixed'];
    public const VAT_MODES = ['none', 'added', 'included'];
    public const PENALTY_TYPES = ['none', 'fixed', 'percent'];
    public const UTIL_METHODS = ['included', 'metered', 'fixed'];

    /** "22 500,50" / "22500.5" / 22500.5 → 2250050 kopecks. Null for text that is not an amount. */
    public static function kop(mixed $v): ?int
    {
        if (is_int($v)) return $v * 100;
        $s = str_replace(["\u{a0}", ' '], '', trim((string)$v)); $s = str_replace(',', '.', $s);
        if ($s === '' || !preg_match('/^-?\d{1,12}(\.\d{1,2})?$/', $s)) return null;
        $neg = $s[0] === '-'; $s = ltrim($s, '-'); [$r, $k] = array_pad(explode('.', $s), 2, '0');
        $n = (int)$r * 100 + (int)str_pad($k, 2, '0'); return $neg ? -$n : $n;
    }
    /** Kopecks → "22 500,00" (ru) or "22,500.00" (en). */
    public static function fmt(int $k, string $lang = 'ru'): string
    {
        $neg = $k < 0; $k = abs($k); $int = number_format(intdiv($k, 100), 0, '', $lang === 'ru' ? "\u{a0}" : ','); $s = $int . ($lang === 'ru' ? ',' : '.') . str_pad((string)($k % 100), 2, '0', STR_PAD_LEFT);
        return ($neg ? '-' : '') . $s;
    }
    /** Plain decimal for forms and CSV: 2250050 → "22500.50". */
    public static function dec(int $k): string { return ($k < 0 ? '-' : '') . intdiv(abs($k), 100) . '.' . str_pad((string)(abs($k) % 100), 2, '0', STR_PAD_LEFT); }
    /** Integer division rounded half away from zero. */
    public static function rdiv(int $num, int $den): int { return $num >= 0 ? intdiv($num * 2 + $den, 2 * $den) : -intdiv(-$num * 2 + $den, 2 * $den); }

    /* ------------------------------------------------------------------ dates */
    public static function isDate(mixed $s): bool { return is_string($s) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m) && checkdate((int)$m[2], (int)$m[3], (int)$m[1]); }
    public static function ts(string $d): int { return (int)gmmktime(0, 0, 0, (int)substr($d, 5, 2), (int)substr($d, 8, 2), (int)substr($d, 0, 4)); }
    public static function days(string $from, string $to): int { return intdiv(self::ts($to) - self::ts($from), 86400); }
    public static function addDays(string $d, int $n): string { return gmdate('Y-m-d', self::ts($d) + $n * 86400); }
    public static function dim(int $y, int $m): int { return (int)gmdate('t', (int)gmmktime(0, 0, 0, $m, 1, $y)); }
    public static function addMonths(string $d, int $n): string
    {
        $y = (int)substr($d, 0, 4); $m = (int)substr($d, 5, 2) - 1 + $n; $y += intdiv($m, 12); $m %= 12; if ($m < 0) { $m += 12; $y--; }
        return sprintf('%04d-%02d-%02d', $y, $m + 1, min((int)substr($d, 8, 2), self::dim($y, $m + 1)));
    }
    public static function monthStart(string $d): string { return substr($d, 0, 8) . '01'; }
    public static function dmy(?string $d): string { return $d && self::isDate($d) ? substr($d, 8, 2) . '.' . substr($d, 5, 2) . '.' . substr($d, 0, 4) : ''; }
    /** The due day of the month of $periodStart, cut to the month's length (due day 31 in February = the 28th). */
    public static function dueDate(string $periodStart, int $dueDay): string
    {
        $y = (int)substr($periodStart, 0, 4); $m = (int)substr($periodStart, 5, 2);
        return sprintf('%04d-%02d-%02d', $y, $m, max(1, min($dueDay, self::dim($y, $m))));
    }
    /** Normalises what a form or an import sends (2026-12-01, 01.12.2026) to YYYY-MM-DD; null when it is not a real date. */
    public static function parseDate(mixed $v): ?string
    {
        $s = trim((string)$v);
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $s, $m)) [$y, $mo, $d] = [(int)$m[1], (int)$m[2], (int)$m[3]];
        elseif (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $s, $m)) [$y, $mo, $d] = [(int)$m[3], (int)$m[2], (int)$m[1]];
        else return null;
        return checkdate($mo, $d, $y) && $y >= 1990 && $y < 2200 ? sprintf('%04d-%02d-%02d', $y, $mo, $d) : null;
    }

    /* ------------------------------------------------------------------ rent */
    /**
     * Rent for ONE month of the contract's units, in kopecks (before VAT).
     * @param array $lines list of ['area'=>float m2 of the unit, 'volume'=>float m3, 'pallets'=>int, 'share'=>float percent of the unit rented]
     * @return array{amount:int,qty:float,unit:string}
     */
    public static function monthlyRent(string $rateType, int $rate, array $lines): array
    {
        if ($rateType === 'fixed') return ['amount' => $rate, 'qty' => 1.0, 'unit' => 'fixed'];
        $amount = 0; $qty = 0.0;
        foreach ($lines as $l) {
            $share = (int)round(((float)($l['share'] ?? 100)) * 100);              // hundredths of a percent
            if ($rateType === 'pallet') {
                $n = (int)($l['pallets'] ?? 0); $q = self::rdiv($n * $share, 10000);   // pallets of the share, whole places
                if ($q < 1 && $n > 0 && $share > 0) $q = 1;
                $amount += $rate * $q; $qty += $q; continue;
            }
            $base = (int)round((float)($rateType === 'm3' ? ($l['volume'] ?? 0) : ($l['area'] ?? 0)) * 100);   // hundredths of m2 / m3
            $amount += self::rdiv($rate * $base * $share, 100 * 10000);
            $qty += $base * $share / 1000000;
        }
        return ['amount' => $amount, 'qty' => round($qty, 3), 'unit' => $rateType];
    }
    /** Share of the period the contract covers as [days covered, days in period]; (n, n) = the whole period. */
    public static function coverage(string $periodStart, string $periodEnd, string $contractStart, ?string $contractEnd): array
    {
        $total = self::days($periodStart, $periodEnd) + 1;
        $from = max($periodStart, $contractStart); $to = $contractEnd !== null && $contractEnd !== '' ? min($periodEnd, $contractEnd) : $periodEnd;
        $cov = $to < $from ? 0 : self::days($from, $to) + 1;
        return [$cov, $total];
    }
    /** The billing periods of a contract that must exist on $today: list of [start, end] (periods are aligned to calendar months from the start month). */
    public static function periods(string $start, ?string $end, int $months, string $today, int $max = 60): array
    {
        $months = max(1, $months); $out = []; $p = self::monthStart($start);
        for ($i = 0; $i < $max; $i++) {
            if ($p > $today || ($end !== null && $end !== '' && $p > $end)) break;
            $pe = self::addDays(self::addMonths($p, $months), -1); $out[] = [$p, $pe]; $p = self::addMonths($p, $months);
        }
        return $out;
    }

    /* ------------------------------------------------------------------ VAT */
    /**
     * @param string $mode none | added (VAT is added on top of the amounts) | included (the amounts already contain VAT)
     * @param float $rate percent, e.g. 20
     * @return array{net:int,vat:int,total:int} $vatable = sum of the lines VAT applies to, $plain = sum of the lines it does not (penalties)
     */
    public static function vat(string $mode, float $rate, int $vatable, int $plain = 0): array
    {
        $bp = (int)round($rate * 100);
        if ($mode === 'added' && $bp > 0) { $v = self::rdiv($vatable * $bp, 10000); return ['net' => $vatable + $plain, 'vat' => $v, 'total' => $vatable + $plain + $v]; }
        if ($mode === 'included' && $bp > 0) { $v = self::rdiv($vatable * $bp, 10000 + $bp); return ['net' => $vatable - $v + $plain, 'vat' => $v, 'total' => $vatable + $plain]; }
        return ['net' => $vatable + $plain, 'vat' => 0, 'total' => $vatable + $plain];
    }

    /* ------------------------------------------------------------------ penalty */
    /**
     * Penalty accrued on one invoice up to and including $asOf.
     * @param int $principal what the invoice asked for (kopecks, penalty lines excluded)
     * @param string $type fixed (value = kopecks per late day) | percent (value = percent of the unpaid part per late day) | none
     * @param array $reductions list of [date, kopecks]: payments and write-offs that reduced the unpaid part, with the day they happened
     * @param int $capPct 0 = no cap; otherwise the penalty never exceeds this percent of the principal
     */
    public static function penalty(int $principal, string $due, int $grace, string $type, float $value, string $asOf, array $reductions = [], int $capPct = 0): int
    {
        if ($type === 'none' || $value <= 0 || $principal <= 0) return 0;
        $first = self::addDays($due, max(0, $grace) + 1);
        if ($asOf < $first) return 0;
        $n = min(self::days($first, $asOf) + 1, 3650);
        usort($reductions, fn($a, $b) => strcmp((string)$a[0], (string)$b[0]));
        $unpaid = $principal; $ri = 0; $numer = 0; $fixed = 0; $bp = (int)round($value * 100);
        for ($k = 0; $k < $n; $k++) {
            $day = self::addDays($first, $k);
            while ($ri < count($reductions) && (string)$reductions[$ri][0] <= $day) { $unpaid -= (int)$reductions[$ri][1]; $ri++; }
            if ($unpaid <= 0) break;
            if ($type === 'percent') $numer += $unpaid * $bp; else $fixed += (int)round($value);
        }
        $pen = $type === 'percent' ? self::rdiv($numer, 10000) : $fixed;
        if ($capPct > 0) $pen = min($pen, self::rdiv($principal * $capPct, 100));
        return max(0, $pen);
    }

    /* ------------------------------------------------------------------ statuses and payments */
    /** Stored status draft / cancelled stay; otherwise paid, partially_paid, overdue or issued from the numbers. */
    public static function invoiceStatus(string $stored, int $total, int $settled, string $due, string $today): string
    {
        if ($stored === 'draft' || $stored === 'cancelled') return $stored;
        if ($total - $settled <= 0) return 'paid';
        if ($settled > 0) return 'partially_paid';
        return $today > $due ? 'overdue' : 'issued';
    }
    /** An invoice with an unpaid part past its due date, whatever its status label (a part-paid invoice can be late too). */
    public static function isLate(string $stored, int $total, int $settled, string $due, string $today): bool
    {
        return !in_array($stored, ['draft', 'cancelled'], true) && $total - $settled > 0 && $today > $due;
    }
    /**
     * First in, first out: spreads $amount over the open invoices, oldest due date first.
     * @param array $open list of ['id'=>int,'due'=>string,'out'=>int] (any order)
     * @return array{0:array<int,int>,1:int} [invoice id => kopecks, what is left over]
     */
    public static function fifo(int $amount, array $open): array
    {
        usort($open, fn($a, $b) => [$a['due'], $a['id']] <=> [$b['due'], $b['id']]); $out = [];
        foreach ($open as $o) { if ($amount <= 0) break; $take = min($amount, (int)$o['out']); if ($take > 0) { $out[(int)$o['id']] = $take; $amount -= $take; } }
        return [$out, max(0, $amount)];
    }
    public static function agingBucket(int $daysLate): string { return $daysLate <= 0 ? 'current' : ($daysLate <= 30 ? '1-30' : ($daysLate <= 60 ? '31-60' : '60+')); }

    /** Displayed state of a contract: draft / active / expiring / ended / terminated. $flagDays = N days before the end that mark "expiring". */
    public static function contractState(string $stored, string $start, string $end, string $today, int $flagDays): string
    {
        if ($stored === 'draft' || $stored === 'terminated') return $stored;
        if ($end < $today) return 'ended';
        return self::days($today, $end) <= $flagDays ? 'expiring' : 'active';
    }

    /* ------------------------------------------------------------------ utilities */
    /** Consumption of a meter between two readings; a reading below the previous one is a mistake (null) unless $rollover = the meter's capacity (it went round to zero). */
    public static function consumption(float $prev, float $now, ?float $rollover = null): ?float
    {
        if ($now >= $prev) return round($now - $prev, 3);
        return $rollover !== null && $rollover > $prev ? round($rollover - $prev + $now, 3) : null;
    }
    /** Charge for a consumption at a rate in kopecks per unit. */
    public static function utilityCharge(float $consumption, int $rateKop): int { return (int)round($consumption * $rateKop); }

    /* ------------------------------------------------------------------ text */
    /** {placeholder} substitution for notification templates; unknown placeholders stay as they are. */
    public static function fill(string $tpl, array $vars): string
    {
        return preg_replace_callback('/\{([a-z_]+)\}/', fn($m) => array_key_exists($m[1], $vars) ? (string)$vars[$m[1]] : $m[0], $tpl);
    }
}
