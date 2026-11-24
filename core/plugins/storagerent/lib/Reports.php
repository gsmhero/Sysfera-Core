<?php
declare(strict_types=1);
namespace SysferaStoragerent;

/**
 * Dashboard figures and reports. Every report is a table: ['cols' => [key => label-key], 'rows' => [[...]], 'sum' => [...]] so one result feeds the HTML page,
 * the CSV, the XLSX and the printable (PDF) view. Money cells are kopecks (int) and are marked by MONEY; the renderers format them.
 */
final class Reports
{
    public const REPORTS = ['revenue_month', 'revenue_tenant', 'revenue_unit', 'arrears', 'occupancy', 'utilities'];
    public const MONEY = ['invoiced', 'received', 'outstanding', 'penalty', 'total', 'rent', 'charge'];

    public function __construct(private Store $st, private Ledger $ledger, private Wrms $wrms, private $today) {}

    /** occupancy % (area-weighted, units under maintenance do not count as capacity), revenue of the month, arrears, expiring contracts. */
    public function dashboard(): array
    {
        $today = ($this->today)(); $ms = Billing::monthStart($today); $me = Billing::addDays(Billing::addMonths($ms, 1), -1);
        $cap = 0.0; $rented = 0.0; $units = ['free' => 0, 'reserved' => 0, 'occupied' => 0, 'maintenance' => 0];
        foreach ($this->wrms->units() as $u) {
            $units[$u['status']]++; if ($u['status'] === 'maintenance') continue;
            $a = (float)$u['area'] > 0 ? (float)$u['area'] : (float)$u['volume']; $cap += $a; $rented += $a * (float)$u['rented'] / 100;
        }
        $invoiced = 0; $received = 0;
        foreach ($this->st->find('invoices', []) as $i) if (!in_array($i['stored_status'], ['draft', 'cancelled'], true) && $i['issue_date'] >= $ms && $i['issue_date'] <= $me) $invoiced += (int)$i['total'];
        foreach ($this->st->find('payments', []) as $p) if ($p['pay_date'] >= $ms && $p['pay_date'] <= $me) $received += (int)$p['amount'];
        $arr = $this->arrears(); $total = 0; foreach ($arr['rows'] as $r) $total += $r['outstanding'] + $r['penalty'];
        return ['occupancy' => $cap > 0 ? round($rented / $cap * 100, 1) : 0.0, 'units' => $units, 'invoiced' => $invoiced, 'received' => $received, 'arrears' => $total,
            'arrears_invoices' => count($arr['rows']), 'expiring' => $this->wrms->expiring(30), 'month' => $ms];
    }

    public function run(string $name, string $from, string $to): array
    {
        return match ($name) {
            'revenue_month' => $this->revenueByMonth($from, $to), 'revenue_tenant' => $this->revenueByTenant($from, $to), 'revenue_unit' => $this->revenueByUnit($from, $to),
            'arrears' => $this->arrears(), 'occupancy' => $this->occupancy($from, $to), 'utilities' => $this->utilities($from, $to), default => throw new Err('bad_request'),
        };
    }
    private function live(): array { return array_values(array_filter($this->st->find('invoices', []), fn($i) => !in_array($i['stored_status'], ['draft', 'cancelled'], true))); }

    public function revenueByMonth(string $from, string $to): array
    {
        $m = []; $cur = Billing::monthStart($from);
        for ($i = 0; $i < 120 && $cur <= $to; $i++) { $m[substr($cur, 0, 7)] = ['month' => substr($cur, 0, 7), 'invoiced' => 0, 'received' => 0]; $cur = Billing::addMonths($cur, 1); }
        foreach ($this->live() as $i) { $k = substr((string)$i['issue_date'], 0, 7); if (isset($m[$k])) $m[$k]['invoiced'] += (int)$i['total']; }
        foreach ($this->st->find('payments', []) as $p) { $k = substr((string)$p['pay_date'], 0, 7); if (isset($m[$k])) $m[$k]['received'] += (int)$p['amount']; }
        return self::table(['month', 'invoiced', 'received'], array_values($m), ['invoiced', 'received']);
    }
    public function revenueByTenant(string $from, string $to): array
    {
        $rows = [];
        foreach ($this->st->find('tenants', [], 'name') as $t) $rows[(int)$t['id']] = ['tenant' => $t['name'], 'invoiced' => 0, 'received' => 0, 'outstanding' => 0];
        foreach ($this->ledger->invoices([], 50000) as $i) { if (in_array($i['stored_status'], ['draft', 'cancelled'], true) || $i['issue_date'] < $from || $i['issue_date'] > $to || !isset($rows[(int)$i['tenant_id']])) continue; $rows[(int)$i['tenant_id']]['invoiced'] += (int)$i['total']; $rows[(int)$i['tenant_id']]['outstanding'] += $i['outstanding']; }
        foreach ($this->st->find('payments', []) as $p) if ($p['pay_date'] >= $from && $p['pay_date'] <= $to && isset($rows[(int)$p['tenant_id']])) $rows[(int)$p['tenant_id']]['received'] += (int)$p['amount'];
        return self::table(['tenant', 'invoiced', 'received', 'outstanding'], array_values(array_filter($rows, fn($r) => $r['invoiced'] || $r['received'])), ['invoiced', 'received', 'outstanding']);
    }
    /** Rent of the period split over the contract's units in proportion to what each unit's share would cost. */
    public function revenueByUnit(string $from, string $to): array
    {
        $rows = [];
        foreach ($this->live() as $i) {
            if ($i['kind'] !== 'rent' || $i['issue_date'] < $from || $i['issue_date'] > $to) continue;
            $k = $this->wrms->contract((int)$i['contract_id']); if (!$k || !$k['lines']) continue;
            $rent = 0; foreach ($this->st->find('invoice_items', ['invoice_id' => (int)$i['id'], 'kind' => 'rent']) as $it) $rent += (int)$it['amount'];
            $w = []; foreach ($k['lines'] as $l) $w[(int)$l['unit_id']] = max(1, Billing::monthlyRent($k['rate_type'] === 'fixed' ? 'm2' : (string)$k['rate_type'], 100, Wrms::rentLines([$l]))['amount']); $sum = array_sum($w);
            foreach ($k['lines'] as $l) { $u = (int)$l['unit_id']; $rows[$u] ??= ['unit' => ($l['unit']['name'] ?? '#' . $u), 'rent' => 0]; $rows[$u]['rent'] += Billing::rdiv($rent * $w[$u], $sum); }
        }
        return self::table(['unit', 'rent'], array_values($rows), ['rent']);
    }
    /** Invoices with an unpaid part past their due date, oldest first, with the aging bucket (1-30, 31-60, 60+) and the penalty accrued and not yet billed. */
    public function arrears(): array
    {
        $rows = [];
        foreach ($this->ledger->invoices([], 50000) as $i) {
            if (!$i['late'] && $i['penalty_unbilled'] <= 0) continue;
            $rows[] = ['tenant' => $i['tenant_name'], 'contract' => $i['contract_number'], 'invoice' => $i['number'], 'due_date' => $i['due_date'], 'days_late' => $i['days_late'], 'bucket' => Billing::agingBucket($i['days_late']),
                'outstanding' => $i['outstanding'], 'penalty' => $i['penalty_unbilled'], 'status' => $i['status']];
        }
        usort($rows, fn($a, $b) => [$a['due_date'], $a['invoice']] <=> [$b['due_date'], $b['invoice']]);
        $sum = ['1-30' => 0, '31-60' => 0, '60+' => 0, 'current' => 0]; foreach ($rows as $r) $sum[$r['bucket']] += $r['outstanding'] + $r['penalty'];
        $t = self::table(['tenant', 'contract', 'invoice', 'due_date', 'days_late', 'bucket', 'outstanding', 'penalty', 'status'], $rows, ['outstanding', 'penalty']); $t['buckets'] = $sum; return $t;
    }
    /** Occupancy at the end of each month: percent of the area let (units under maintenance excluded). */
    public function occupancy(string $from, string $to): array
    {
        $rows = []; $cur = Billing::monthStart($from); $units = $this->st->find('units', []);
        for ($n = 0; $n < 120 && $cur <= $to; $n++) {
            $at = min(Billing::addDays(Billing::addMonths($cur, 1), -1), $to); $cap = 0.0; $rented = 0.0;
            foreach ($units as $u) {
                if ($u['status_manual'] === 'maintenance') continue; $a = (float)$u['area'] > 0 ? (float)$u['area'] : (float)$u['volume']; $cap += $a; $sh = 0.0;
                foreach ($this->st->find('contract_units', ['unit_id' => (int)$u['id']]) as $l) { $k = $this->st->one('contracts', ['id' => (int)$l['contract_id']]); if ($k && in_array($k['status'], ['active', 'terminated'], true) && $k['start_date'] <= $at && $k['end_date'] >= $at) $sh += (float)$l['share_pct']; }
                $rented += $a * min(100.0, $sh) / 100;
            }
            $rows[] = ['month' => substr($cur, 0, 7), 'area_total' => round($cap, 2), 'area_rented' => round($rented, 2), 'percent' => $cap > 0 ? round($rented / $cap * 100, 1) : 0.0]; $cur = Billing::addMonths($cur, 1);
        }
        return self::table(['month', 'area_total', 'area_rented', 'percent'], $rows, []);
    }
    public function utilities(string $from, string $to): array
    {
        $rows = [];
        foreach ($this->st->find('meters', [], 'id') as $m) {
            $u = $this->st->one('units', ['id' => (int)$m['unit_id']]); $cons = 0.0; $charge = 0;
            foreach ($this->st->find('readings', ['meter_id' => (int)$m['id']]) as $r) if ($r['read_date'] >= $from && $r['read_date'] <= $to) { $cons += (float)$r['consumption']; $charge += (int)$r['charge']; }
            if ($cons > 0 || $charge > 0) $rows[] = ['unit' => $u['name'] ?? '', 'meter' => $m['type'] . ($m['serial'] !== '' ? ' ' . $m['serial'] : ''), 'consumption' => round($cons, 3), 'charge' => $charge];
        }
        return self::table(['unit', 'meter', 'consumption', 'charge'], $rows, ['charge']);
    }

    private static function table(array $cols, array $rows, array $money): array
    {
        $sum = []; foreach ($money as $c) $sum[$c] = array_sum(array_map(fn($r) => (int)$r[$c], $rows));
        return ['cols' => $cols, 'rows' => $rows, 'sum' => $sum];
    }

    /* ---------------------------------------------------------------- exports */
    /** @param callable(string):string $label column key → header text; money cells are written as decimals */
    public static function matrix(array $t, callable $label): array
    {
        $m = [array_map($label, $t['cols'])];
        foreach ($t['rows'] as $r) $m[] = array_map(fn($c) => in_array($c, self::MONEY, true) ? (float)Billing::dec((int)$r[$c]) : $r[$c], $t['cols']);
        if ($t['sum']) { $tot = []; foreach ($t['cols'] as $i => $c) $tot[] = $i === 0 ? $label('total') : (isset($t['sum'][$c]) ? (float)Billing::dec((int)$t['sum'][$c]) : ''); $m[] = $tot; }
        return $m;
    }
    /** CSV with a UTF-8 BOM and ";" so that Excel in the Russian locale opens it in columns. Cells starting with = + - @ are prefixed with ' (formula injection). */
    public static function csv(array $matrix): string
    {
        $o = "\xEF\xBB\xBF";
        foreach ($matrix as $row) $o .= implode(';', array_map(function ($v) {
            $s = is_float($v) ? number_format($v, 2, '.', '') : (string)$v; if ($s !== '' && !is_numeric($s) && str_contains('=+-@', $s[0])) $s = "'" . $s;
            return preg_match('/[;"\r\n]/', $s) ? '"' . str_replace('"', '""', $s) . '"' : $s;
        }, $row)) . "\r\n";
        return $o;
    }
}
