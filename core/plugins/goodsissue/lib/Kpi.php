<?php
declare(strict_types=1);
namespace SysferaGi;

/**
 * KPI of the pickup warehouses for the office: issue speed, storage days, discrepancy rate, operator activity and suspicious events.
 * Pure function of rows (orders, audit rows, transfers, incidents): the plugin loads them, the tests feed them.
 */
final class Kpi
{
    /** suspicious: this many failed code attempts / refused issues per order or device in the period */
    public const FAILED_CODES = 3;
    public const REFUSALS = 3;

    /**
     * @param array<int,string> $whs  warehouse id => name
     * @param list<array> $orders     rows of gi_orders
     * @param list<array> $audit      rows of gi_audit of the period (any order)
     * @param array<string,int> $transferWh  transfer uuid => warehouse id
     */
    public static function compute(array $whs, array $orders, array $audit, array $transferWh, int $now, int $days = 30): array
    {
        $from = $now - $days * 86400; $byOrder = []; foreach ($orders as $o) $byOrder[$o['uuid']] = $o;
        $W = []; foreach ($whs as $id => $name) $W[$id] = ['id' => $id, 'name' => $name, 'orders' => 0, 'issued' => 0, 'issue_hours' => [], 'issue_hours_avg' => null, 'issue_hours_median' => null, 'storage_days_avg' => null, 'waiting' => 0, 'waiting_days_avg' => null, 'oldest_waiting_days' => null,
            'received' => 0, 'discrepancy_orders' => 0, 'discrepancy_rate' => null, 'returns' => 0, 'courier' => 0, 'suspicious' => 0];
        $wait = [];
        foreach ($orders as $o) {
            $w = (int)$o['warehouse_id']; if (!isset($W[$w])) continue;
            $W[$w]['orders']++;
            $r = !empty($o['ready_at']) ? strtotime((string)$o['ready_at']) : null; $i = !empty($o['issued_at']) ? strtotime((string)$o['issued_at']) : null;
            if ($i !== null && $i >= $from) { $W[$w]['issued']++; if (($o['handover'] ?? '') === 'courier') $W[$w]['courier']++; if ($r !== null && $i >= $r) $W[$w]['issue_hours'][] = ($i - $r) / 3600; }
            if ($o['status'] === 'ready_for_issue' && $r !== null) { $W[$w]['waiting']++; $wait[$w][] = ($now - $r) / 86400; }
            if (in_array($o['status'], ['return_requested', 'returned'], true) && strtotime((string)$o['updated_at']) >= $from) $W[$w]['returns']++;
        }
        $ops = []; $failed = []; $refused = []; $reissued = []; $manual = []; $clock = [];
        $wOf = function (array $e) use ($byOrder, $transferWh): int {
            if ($e['entity'] === 'order') return (int)($byOrder[$e['entity_uuid']]['warehouse_id'] ?? 0);
            if ($e['entity'] === 'transfer') return (int)($transferWh[$e['entity_uuid']] ?? 0);
            if ($e['entity'] === 'warehouse') return (int)$e['entity_uuid'];
            return 0;
        };
        $recv = []; $disc = [];
        foreach ($audit as $e) {
            $ts = strtotime((string)$e['ts']); if ($ts < $from) continue;
            $d = json_decode((string)($e['data'] ?? ''), true) ?: []; $act = (string)$e['action']; $actor = (string)$e['actor']; $w = $wOf($e); $dev = (string)($e['device_id'] ?? '');
            if (str_starts_with($actor, 'operator:')) {
                $o = &$ops[$actor]; $o ??= ['actor' => substr($actor, 9), 'actions' => 0, 'issues' => 0, 'receipts' => 0, 'returns' => 0, 'counts' => 0, 'shelf' => 0, 'failed_codes' => 0, 'last' => 0, 'warehouses' => []];
                if (!in_array($act, ['auth.login', 'auth.failed', 'device.bound', 'device.pending'], true)) $o['actions']++;
                if ($act === 'issue.act') $o['issues']++;
                elseif (str_starts_with($act, 'receipt.') && $act !== 'receipt.conflict') $o['receipts']++;
                elseif ($act === 'order.return_requested' || $act === 'order.returned') $o['returns']++;
                elseif ($act === 'count.submitted' || $act === 'stocktake.submitted') $o['counts']++;
                elseif ($act === 'shelf.set') $o['shelf']++;
                elseif ($act === 'issue.code_failed') $o['failed_codes']++;
                $o['last'] = max($o['last'], $ts); if ($w) $o['warehouses'][$w] = $whs[$w] ?? (string)$w; unset($o);
            }
            if ($act === 'order.received' || ($act === 'order.blocked' && in_array($d['reason'] ?? '', ['discrepancy', 'rejected'], true))) { $recv[$e['entity_uuid']] = 1; if ($act === 'order.blocked') $disc[$e['entity_uuid']] = 1; }
            if ($act === 'issue.code_failed') $failed[$e['entity_uuid']][] = ['ts' => $ts, 'device' => $dev, 'actor' => $actor];
            if ($act === 'issue.refused' && $dev !== '') $refused[$dev][] = ['ts' => $ts, 'order' => $e['entity_uuid'], 'actor' => $actor];
            if ($act === 'code.issued' && str_starts_with($actor, 'office:')) $reissued[$e['entity_uuid']][] = ['ts' => $ts, 'actor' => $actor];
            if ($act === 'order.ready_for_issue' && str_starts_with($actor, 'office:') && ($d['from'] ?? '') === 'blocked') $manual[] = ['ts' => $ts, 'order' => $e['entity_uuid'], 'actor' => $actor, 'note' => (string)($d['note'] ?? '')];
            if (in_array('clock_ahead', (array)($d['flags'] ?? []), true) && $dev !== '') $clock[$dev][] = ['ts' => $ts, 'actor' => $actor];
        }
        foreach ($recv as $u => $_) { $w = (int)($byOrder[$u]['warehouse_id'] ?? 0); if (isset($W[$w])) { $W[$w]['received']++; if (isset($disc[$u])) $W[$w]['discrepancy_orders']++; } }
        $sus = []; $num = fn(string $u) => (string)($byOrder[$u]['number'] ?? substr($u, 0, 8)); $wh = fn(string $u) => (int)($byOrder[$u]['warehouse_id'] ?? 0);
        foreach ($failed as $u => $rows) if (count($rows) >= self::FAILED_CODES) $sus[] = ['type' => 'repeated_code', 'warehouse_id' => $wh($u), 'ref' => $num($u), 'order' => $u, 'count' => count($rows), 'who' => implode(', ', array_unique(array_column($rows, 'actor'))), 'device' => implode(', ', array_unique(array_filter(array_column($rows, 'device')))), 'ts' => max(array_column($rows, 'ts'))];
        foreach ($reissued as $u => $rows) if (count($rows) >= 2) $sus[] = ['type' => 'code_reissued', 'warehouse_id' => $wh($u), 'ref' => $num($u), 'order' => $u, 'count' => count($rows), 'who' => implode(', ', array_unique(array_column($rows, 'actor'))), 'device' => '', 'ts' => max(array_column($rows, 'ts'))];
        foreach ($manual as $m) $sus[] = ['type' => 'manual_override', 'warehouse_id' => $wh($m['order']), 'ref' => $num($m['order']), 'order' => $m['order'], 'count' => 1, 'who' => $m['actor'], 'device' => '', 'ts' => $m['ts']];
        foreach ($refused as $dev => $rows) if (count($rows) >= self::REFUSALS) $sus[] = ['type' => 'refused_burst', 'warehouse_id' => $wh($rows[0]['order']), 'ref' => $dev, 'order' => '', 'count' => count($rows), 'who' => implode(', ', array_unique(array_column($rows, 'actor'))), 'device' => $dev, 'ts' => max(array_column($rows, 'ts'))];
        foreach ($clock as $dev => $rows) $sus[] = ['type' => 'clock_ahead', 'warehouse_id' => 0, 'ref' => $dev, 'order' => '', 'count' => count($rows), 'who' => implode(', ', array_unique(array_column($rows, 'actor'))), 'device' => $dev, 'ts' => max(array_column($rows, 'ts'))];
        usort($sus, fn($a, $b) => [$b['count'], $b['ts']] <=> [$a['count'], $a['ts']]);
        foreach ($sus as $s) if (isset($W[$s['warehouse_id']])) $W[$s['warehouse_id']]['suspicious']++;
        $avg = fn(array $a) => $a ? round(array_sum($a) / count($a), 1) : null;
        foreach ($W as $id => &$w) {
            $h = $w['issue_hours']; sort($h); $n = count($h);
            $w['issue_hours_avg'] = $avg($h); $w['issue_hours_median'] = $n ? round(($h[intdiv($n - 1, 2)] + $h[intdiv($n, 2)]) / 2, 1) : null; $w['storage_days_avg'] = $h ? round(array_sum($h) / $n / 24, 1) : null; unset($w['issue_hours']);
            $w['waiting_days_avg'] = $avg($wait[$id] ?? []); $w['oldest_waiting_days'] = !empty($wait[$id]) ? round(max($wait[$id]), 1) : null;
            $w['discrepancy_rate'] = $w['received'] ? round(100 * $w['discrepancy_orders'] / $w['received'], 1) : null;
        }
        unset($w);
        $ops = array_values($ops); usort($ops, fn($a, $b) => $b['actions'] <=> $a['actions']);
        foreach ($ops as &$o) { $o['warehouses'] = array_values($o['warehouses']); $o['last'] = date('Y-m-d H:i:s', $o['last']); } unset($o);
        $t = ['orders' => 0, 'issued' => 0, 'waiting' => 0, 'received' => 0, 'discrepancy_orders' => 0, 'suspicious' => count($sus)]; foreach ($W as $w) foreach (array_keys($t) as $k) if ($k !== 'suspicious') $t[$k] += $w[$k];
        $t['discrepancy_rate'] = $t['received'] ? round(100 * $t['discrepancy_orders'] / $t['received'], 1) : null;
        return ['days' => $days, 'from' => date('Y-m-d', $from), 'to' => date('Y-m-d', $now), 'warehouses' => array_values($W), 'total' => $t, 'operators' => $ops, 'suspicious' => $sus];
    }
}
