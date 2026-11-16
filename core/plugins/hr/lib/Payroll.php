<?php
declare(strict_types=1);
namespace SysferaHr;

/**
 * Payroll calculation and the export contract for the (future) Accounting module. Pure functions: money is integer kopecks, percents are basis points (1% = 100).
 *
 * Pay profile: {type: fixed|hourly|per_task|commission, base_kop, mission_bonus_kop, commission_bp, gi: {per_issue_kop, per_receipt_kop, per_return_kop, per_count_kop},
 *               deductions: [{code, label, kind: fixed|percent, value}]}      (percent value in basis points of the gross accrual)
 * Activity of the period: {planned_days, unpaid_days, hours, missions_completed, commission_base_kop, gi: {issues, receipts, returns, counts}}
 */
final class Payroll
{
    public const TYPES = ['fixed', 'hourly', 'per_task', 'commission'];
    public const SCHEMA = 'sysfera.payroll.export/1';

    /** half-up integer division */
    public static function div(int $n, int $d): int { return $d === 0 ? 0 : intdiv(2 * $n + ($n >= 0 ? $d : -$d), 2 * $d); }

    public static function normalize(array $p): array
    {
        $type = (string)($p['type'] ?? 'fixed'); if (!in_array($type, self::TYPES, true)) throw new Err('bad_pay_type', 'type must be one of ' . implode(', ', self::TYPES), 422);
        $int = function (string $k) use ($p): int { $v = $p[$k] ?? 0; if (!is_numeric($v) || (int)$v < 0 || (int)$v != $v) throw new Err('bad_amount', "$k must be a non-negative integer (kopecks / basis points)", 422); return (int)$v; };
        $gi = []; foreach (['per_issue_kop', 'per_receipt_kop', 'per_return_kop', 'per_count_kop'] as $k) { $v = $p['gi'][$k] ?? 0; if (!is_numeric($v) || (int)$v < 0) throw new Err('bad_amount', "gi.$k", 422); $gi[$k] = (int)$v; }
        $ded = [];
        foreach ((array)($p['deductions'] ?? []) as $d) {
            $kind = (string)($d['kind'] ?? 'fixed'); $v = $d['value'] ?? 0;
            if (!in_array($kind, ['fixed', 'percent'], true) || !is_numeric($v) || (int)$v < 0 || ($kind === 'percent' && (int)$v > 10000)) throw new Err('bad_deduction', 'deduction: kind fixed|percent, value >= 0 (percent in basis points, max 10000)', 422);
            $ded[] = ['code' => substr(preg_replace('/[^a-z0-9_]/', '', strtolower((string)($d['code'] ?? 'deduction'))) ?: 'deduction', 0, 30), 'label' => substr(trim((string)($d['label'] ?? '')), 0, 80), 'kind' => $kind, 'value' => (int)$v];
        }
        return ['type' => $type, 'base_kop' => $int('base_kop'), 'mission_bonus_kop' => $int('mission_bonus_kop'), 'commission_bp' => min(10000, $int('commission_bp')), 'gi' => $gi, 'deductions' => $ded, 'currency' => 'RUB'];
    }

    /** @return array{lines: list<array>, gross_kop:int, deductions_kop:int, net_kop:int} */
    public static function calc(array $profile, array $act): array
    {
        $p = self::normalize($profile); $lines = [];
        $line = function (string $code, string $kind, string $label, float|int $qty, int $rate, int $amount, string $src) use (&$lines) { if ($amount !== 0 || $kind === 'deduction') $lines[] = ['code' => $code, 'kind' => $kind, 'label' => $label, 'qty' => $qty, 'rate_kop' => $rate, 'amount_kop' => $amount, 'source' => $src]; };
        $planned = max(0, (int)($act['planned_days'] ?? 0)); $unpaid = max(0, min($planned, (int)($act['unpaid_days'] ?? 0)));
        if ($p['type'] === 'fixed' || ($p['type'] === 'commission' && $p['base_kop'] > 0)) {
            $worked = $planned > 0 ? $planned - $unpaid : 1; $den = $planned > 0 ? $planned : 1;
            $line($p['type'] === 'fixed' ? 'base_fixed' : 'base_guaranteed', 'accrual', 'Base salary', $planned > 0 ? $worked : 1, $p['base_kop'], self::div($p['base_kop'] * $worked, $den), 'profile');
        }
        if ($p['type'] === 'hourly') { $h = max(0.0, round((float)($act['hours'] ?? 0), 2)); $line('base_hourly', 'accrual', 'Hourly pay', $h, $p['base_kop'], (int)round($p['base_kop'] * $h), 'schedule/teams'); }
        $missions = max(0, (int)($act['missions_completed'] ?? 0));
        if ($p['mission_bonus_kop'] > 0) $line('bonus_mission', 'accrual', 'Missions completed', $missions, $p['mission_bonus_kop'], $missions * $p['mission_bonus_kop'], 'missions');
        if ($p['commission_bp'] > 0) { $b = max(0, (int)($act['commission_base_kop'] ?? 0)); $line('commission', 'accrual', 'Commission', $b, $p['commission_bp'], self::div($b * $p['commission_bp'], 10000), 'input'); }
        foreach (['per_issue_kop' => ['issues', 'bonus_gi_issue'], 'per_receipt_kop' => ['receipts', 'bonus_gi_receipt'], 'per_return_kop' => ['returns', 'bonus_gi_return'], 'per_count_kop' => ['counts', 'bonus_gi_count']] as $rate => [$k, $code]) {
            $n = max(0, (int)($act['gi'][$k] ?? 0)); if ($p['gi'][$rate] > 0) $line($code, 'accrual', 'Goods issue: ' . $k, $n, $p['gi'][$rate], $n * $p['gi'][$rate], 'goodsissue');
        }
        $gross = array_sum(array_map(fn($l) => $l['amount_kop'], $lines)); $ded = 0;
        foreach ($p['deductions'] as $d) { $amt = $d['kind'] === 'fixed' ? $d['value'] : self::div($gross * $d['value'], 10000); $ded += $amt; $line($d['code'], 'deduction', $d['label'] ?: $d['code'], 1, $d['kind'] === 'fixed' ? $d['value'] : $d['value'], $amt, 'profile'); }
        return ['lines' => $lines, 'gross_kop' => $gross, 'deductions_kop' => $ded, 'net_kop' => $gross - $ded];
    }

    /**
     * The export document: the whole contract between HR and Accounting (see schemas/payroll-export.schema.json).
     * @param list<array{employee: array, calc: array}> $items
     */
    public static function export(string $from, string $to, array $items, string $generatedAt): array
    {
        $rows = [];
        foreach ($items as $it) {
            $rows[] = ['employee' => ['uuid' => $it['employee']['uuid'], 'number' => (string)$it['employee']['number'], 'name' => $it['employee']['full_name']], 'currency' => 'RUB', 'lines' => $it['calc']['lines'],
                'totals' => ['gross_kop' => $it['calc']['gross_kop'], 'deductions_kop' => $it['calc']['deductions_kop'], 'net_kop' => $it['calc']['net_kop']]];
        }
        usort($rows, fn($a, $b) => strcmp($a['employee']['uuid'], $b['employee']['uuid']));
        $sum = hash('sha256', json_encode([$from, $to, $rows], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $h = sha1("sysfera-payroll|$from|$to|$sum");   // idempotent: the same period and numbers always give the same export id
        $id = sprintf('%s-%s-5%s-a%s-%s', substr($h, 0, 8), substr($h, 8, 4), substr($h, 13, 3), substr($h, 17, 3), substr($h, 20, 12));
        return ['schema' => self::SCHEMA, 'export_id' => $id, 'generated_at' => $generatedAt, 'period' => ['from' => $from, 'to' => $to], 'currency' => 'RUB', 'items' => $rows,
            'totals' => ['employees' => count($rows), 'gross_kop' => array_sum(array_map(fn($r) => $r['totals']['gross_kop'], $rows)), 'deductions_kop' => array_sum(array_map(fn($r) => $r['totals']['deductions_kop'], $rows)), 'net_kop' => array_sum(array_map(fn($r) => $r['totals']['net_kop'], $rows))],
            'checksum_sha256' => $sum];
    }

    /** structural check of an export document against the contract (the same rules as the JSON Schema; no validator library needed) */
    public static function validate(array $d): array
    {
        $e = [];
        if (($d['schema'] ?? '') !== self::SCHEMA) $e[] = 'schema';
        foreach (['export_id', 'generated_at', 'checksum_sha256'] as $k) if (!is_string($d[$k] ?? null) || $d[$k] === '') $e[] = $k;
        foreach (['from', 'to'] as $k) if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($d['period'][$k] ?? ''))) $e[] = "period.$k";
        foreach ((array)($d['items'] ?? []) as $i => $it) {
            if (!preg_match('/^[0-9a-f-]{36}$/', (string)($it['employee']['uuid'] ?? ''))) $e[] = "items.$i.employee.uuid";
            foreach ((array)($it['lines'] ?? []) as $j => $l) {
                if (!in_array($l['kind'] ?? '', ['accrual', 'deduction'], true)) $e[] = "items.$i.lines.$j.kind";
                if (!is_int($l['amount_kop'] ?? null)) $e[] = "items.$i.lines.$j.amount_kop";
            }
            $g = 0; $dd = 0; foreach ((array)($it['lines'] ?? []) as $l) { if (($l['kind'] ?? '') === 'accrual') $g += (int)$l['amount_kop']; else $dd += (int)$l['amount_kop']; }
            if (($it['totals']['gross_kop'] ?? null) !== $g || ($it['totals']['deductions_kop'] ?? null) !== $dd || ($it['totals']['net_kop'] ?? null) !== $g - $dd) $e[] = "items.$i.totals";
        }
        return $e;
    }
}
