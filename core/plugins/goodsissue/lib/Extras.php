<?php
declare(strict_types=1);
namespace SysferaGi;

/**
 * The 0.2 extensions of the Exchange engine (a trait only to keep the files readable; it uses the private helpers of Exchange):
 * operator permissions, per-warehouse settings, storage rules, customer returns with an office-approved refund flow and the weekly registry,
 * blind stock counts with adjustment acts, shelf cells, courier handover helpers, and the sweep (overdue, auto-refund, offline warehouses, stuck shipments).
 */
trait Extras
{
    /* ------------------------------------------------------------ permissions, per-warehouse settings */

    /** an operator may carry a list of permissions ('perms' => [...]); without the key everything of the role is allowed */
    private function need(array $actor, string $perm): void
    {
        if (isset($actor['perms']) && is_array($actor['perms']) && !in_array($perm, $actor['perms'], true)) throw new Err('forbidden', 'this account may not do that (' . $perm . ')', 403);
    }

    private function rec(string $kind, string $ref, int $wid, string $status, array $data, ?array $old = null): array
    {
        $r = ['kind' => $kind, 'ref' => $ref, 'warehouse_id' => $wid, 'status' => $status, 'data' => $data, 'created_at' => $old['created_at'] ?? $this->stamp(), 'updated_at' => $this->stamp()];
        $this->s->saveRecord($r); return $r;
    }

    /** the settings of one warehouse: the global ones, overridden where the office set a value for it */
    public function wcfg(int $wid): array
    {
        $o = $this->s->record('wcfg', (string)$wid)['data'] ?? [];
        return array_merge($this->cfg, array_intersect_key($o, array_flip(['id_threshold_kop', 'photo_threshold_kop', 'code_ttl', 'overdue_days', 'refund_days'])));
    }

    /** blank = use the global value. id_threshold / photo_threshold: rubles, code_ttl_h / overdue_days / refund_days: whole numbers */
    public function setWarehouseConfig(int $wid, array $in, array $actor): array
    {
        $d = [];
        foreach (['id_threshold' => 'id_threshold_kop', 'photo_threshold' => 'photo_threshold_kop'] as $f => $k) if (trim((string)($in[$f] ?? '')) !== '') $d[$k] = Units::kop(Units::rub(str_replace(',', '.', trim((string)$in[$f])), $f));
        foreach (['code_ttl_h' => ['code_ttl', 3600, 1, 720], 'overdue_days' => ['overdue_days', 1, 1, 365], 'refund_days' => ['refund_days', 1, 0, 365]] as $f => [$k, $mul, $min, $max]) {
            $v = trim((string)($in[$f] ?? '')); if ($v === '') continue;
            if (!ctype_digit($v) || (int)$v < $min || (int)$v > $max) throw new Err('bad_setting', "$f must be a whole number $min..$max", 422);
            $d[$k] = (int)$v * $mul;
        }
        $this->rec('wcfg', (string)$wid, $wid, 'on', $d, $this->s->record('wcfg', (string)$wid));
        $this->log($actor, 'wcfg.set', 'warehouse', (string)$wid, $d);
        return $d;
    }

    /* ------------------------------------------------------------ storage rules per item (SKU) */

    /** max_days: storage days after which an unclaimed order is "overdue"; refund_days: after that the order is refunded automatically (0 = off) */
    public function setRule(string $sku, array $in, array $actor): void
    {
        $sku = mb_substr(trim($sku), 0, 64); if (!preg_match('/^[\p{L}\p{N}._\-\/ ]{1,64}$/u', $sku)) throw new Err('bad_sku', 'sku is required', 422);
        $v = [];
        foreach (['max_days', 'refund_days'] as $f) { $x = trim((string)($in[$f] ?? '0')); if (!ctype_digit($x) || (int)$x > 365) throw new Err('bad_setting', "$f must be a whole number 0..365", 422); $v[$f] = (int)$x; }
        if ($v['refund_days'] > 0 && $v['max_days'] > 0 && $v['refund_days'] < $v['max_days']) throw new Err('bad_setting', 'refund_days cannot be shorter than max_days', 422);
        $this->rec('rule', $sku, 0, ($v['max_days'] || $v['refund_days']) ? 'on' : 'off', $v, $this->s->record('rule', $sku));
        $this->log($actor, 'rule.set', 'rule', $sku, $v);
    }
    /** @return array<string, array{max_days:int, refund_days:int}> active rules by SKU */
    public function rules(): array
    {
        $o = []; foreach ($this->s->records('rule', 0, 'on') as $r) $o[$r['ref']] = $r['data']; return $o;
    }
    /** @return array{0:int,1:int} [days until overdue, days until the automatic refund (0 = never)] for an order's items: the strictest item rule, else the warehouse default */
    private function days(array $o, array $items, array $rules): array
    {
        $c = $this->wcfg((int)$o['warehouse_id']); $m = []; $r = [];
        foreach ($items as $i) { $x = $rules[$i['sku']] ?? null; if ($x && $x['max_days'] > 0) $m[] = $x['max_days']; if ($x && $x['refund_days'] > 0) $r[] = $x['refund_days']; }
        return [$m ? min($m) : (int)($c['overdue_days'] ?? 7), $r ? min($r) : (int)($c['refund_days'] ?? 0)];
    }
    /** unix time when an order that is ready for issue becomes overdue (null when it is not waiting) */
    public function overdueAt(array $o, array $rules = []): ?int
    {
        if ($o['status'] !== 'ready_for_issue' || empty($o['ready_at'])) return null;
        return strtotime((string)$o['ready_at']) + $this->days($o, $this->s->orderItems($o['uuid']), $rules)[0] * 86400;
    }

    /* ------------------------------------------------------------ photo, courier */

    /** @return array{0:string,1:string} [data URL, sha256] or ['',''] when none was sent */
    private static function photo(mixed $v): array
    {
        if ($v === null || $v === '') return ['', ''];
        if (!is_string($v) || strlen($v) > 900_000 || !preg_match('~^data:image/(jpeg|png|webp);base64,([A-Za-z0-9+/]+={0,2})$~', $v, $m) || strlen((string)base64_decode($m[2], true)) < 100) throw new Err('bad_photo', 'photo must be a JPEG / PNG / WebP data URL (up to 900 KB)', 422);
        return [$v, hash('sha256', $v)];
    }
    public function photoRequired(array $o): bool { $t = (int)($this->wcfg((int)$o['warehouse_id'])['photo_threshold_kop'] ?? 0); return $t > 0 && Units::kop($o['total_rub']) >= $t; }

    public function couriers(): array { return array_values((array)($this->cfg['couriers'] ?? ['boxberry', 'yandex', 'cdek', 'other'])); }
    /** @return array|null normalised courier (null = handover to the customer) */
    private function courierIn(array $in): ?array
    {
        $h = (string)($in['handover'] ?? 'customer'); if (!in_array($h, ['customer', 'courier'], true)) throw new Err('bad_handover', 'handover is customer or courier', 422);
        if ($h === 'customer') return null;
        $c = (array)($in['courier'] ?? []); $svc = strtolower(trim((string)($c['service'] ?? ''))); $name = mb_substr(trim((string)($c['name'] ?? '')), 0, 120); $wb = trim((string)($c['waybill'] ?? ''));
        if (!in_array($svc, $this->couriers(), true)) throw new Err('bad_courier', 'courier service is not on the list: ' . implode(', ', $this->couriers()), 422);
        if (mb_strlen($name) < 2) throw new Err('bad_courier', 'the courier name is required', 422);
        if (!preg_match('~^[A-Za-z0-9._\-/]{3,40}$~', $wb)) throw new Err('bad_courier', 'the waybill / tracking number is required (3-40 characters)', 422);
        return ['service' => $svc, 'name' => $name, 'waybill' => $wb];
    }

    /* ------------------------------------------------------------ returns */

    /** the amount to refund for lines [{barcode, qty}] of an order's items, and the cleaned lines */
    private function returnLines(array $orderItems, mixed $asked): array
    {
        $by = []; foreach ($orderItems as $i) $by[$i['barcode']] = $i;
        $want = []; foreach ((array)$asked as $l) { $bc = trim((string)($l['barcode'] ?? '')); if ($bc === '') continue; if (!isset($by[$bc])) throw new Err('bad_return_item', "barcode $bc is not in this order", 422); $want[$bc] = ($want[$bc] ?? 0) + Units::qty($l['qty'] ?? 1, 'items.qty'); }
        if (!$want) foreach ($by as $bc => $i) $want[$bc] = (int)$i['qty'];
        $lines = []; $kop = 0;
        foreach ($want as $bc => $q) { if ($q > (int)$by[$bc]['qty']) throw new Err('bad_return_item', "more pieces of $bc than were ordered", 422); $kop += Units::kop($by[$bc]['price_rub']) * $q; $lines[] = ['barcode' => $bc, 'name' => $by[$bc]['name'], 'qty' => $q, 'price_rub' => $by[$bc]['price_rub']]; }
        return [$lines, Units::fmt($kop)];
    }

    /**
     * kind = request: the customer declares a return at the warehouse. Reason, item condition, photo (required for damaged goods) and the lines are captured
     *   as a return document (status pending); the office approves or rejects the refund. issued -> return_requested.
     * kind = receive: the goods are physically taken back (return_requested -> returned).
     */
    public function returnAction(string $ou, array $in, array $actor): array
    {
        $this->need($actor, 'return');
        $doc = Manifest::uuid($in['doc_uuid'] ?? null, 'doc_uuid'); $fp = self::fp($in + ['order' => $ou]);
        if ($r = $this->replay($doc, 'return', $fp)) return $r;
        $o = $this->own($this->s->order($ou), $actor); $wid = (int)$o['warehouse_id']; [$cts] = $this->clientTs($in['client_ts'] ?? null);
        $kind = (string)($in['kind'] ?? ''); if (!in_array($kind, ['request', 'receive'], true)) throw new Err('bad_kind', 'kind is request or receive', 422);
        $to = $kind === 'request' ? 'return_requested' : 'returned';
        if (!Machine::can($o['status'], $to, 'operator')) {   // already handled elsewhere: the server copy wins
            $res = ['doc_uuid' => $doc, 'conflict' => true, 'reason' => 'order_' . $o['status'], 'server' => $this->orderView($o, false)];
            $this->log($actor, 'return.conflict', 'order', $ou, ['doc' => $doc, 'kind' => $kind, 'server_status' => $o['status']], $cts);
            return $this->keep($doc, 'return', $wid, $ou, $fp, $res, $cts, $actor, ['kind' => $kind]);
        }
        $reason = mb_substr(trim((string)($in['reason'] ?? '')), 0, 300);
        $cond = (string)($in['condition'] ?? 'ok'); if (!in_array($cond, self::CONDITIONS, true)) throw new Err('bad_condition', 'condition is ok, opened or damaged', 422);
        $ret = null; $photoSha = '';
        if ($kind === 'request') {
            if (mb_strlen($reason) < 3) throw new Err('reason_required', 'a reason is required', 422);
            [$ph, $photoSha] = self::photo($in['photo'] ?? null);
            if ($cond === 'damaged' && $ph === '') throw new Err('photo_required', 'a photo of the damage is required', 422);
            [$lines, $refund] = $this->returnLines($this->s->orderItems($ou), $in['items'] ?? null);
            $ret = ['ref' => $doc, 'status' => 'pending', 'refund_rub' => $refund, 'photo_sha256' => $photoSha];
            $this->rec('return', $doc, $wid, 'pending', ['order_uuid' => $ou, 'number' => $o['number'], 'customer' => $o['customer_name'], 'source' => 'customer', 'reason' => $reason, 'condition' => $cond, 'photo_sha256' => $photoSha,
                'items' => $lines, 'refund_rub' => $refund, 'total_rub' => $o['total_rub'], 'prev_status' => $o['status'], 'requested_by' => $actor['name'] ?? '', 'requested_at' => $this->stamp($cts ?? $this->now()), 'device' => $actor['device'] ?? null]);
        } else {
            foreach ($this->s->records('return', $wid) as $r) if (($r['data']['order_uuid'] ?? '') === $ou && in_array($r['status'], ['pending', 'approved'], true)) { $d = $r['data']; $d['received_at'] = $this->stamp(); $d['received_condition'] = $cond; $this->rec('return', $r['ref'], $wid, $r['status'], $d, $r); break; }
        }
        $this->move($o, $to, 'operator', $actor, ['doc' => $doc, 'reason' => $reason, 'condition' => $cond] + ($photoSha !== '' ? ['photo_sha256' => $photoSha] : []), $cts);
        if ($kind === 'request' || $cond !== 'ok') $this->s->addIncident(['warehouse_id' => $wid, 'kind' => $kind === 'request' ? 'return_request' : 'return_damaged', 'ref' => $ou, 'severity' => 'medium', 'status' => 'open', 'created_at' => $this->stamp(),
            'details' => json_encode(['order' => $o['number'], 'reason' => $reason, 'condition' => $cond, 'return' => $doc], JSON_UNESCAPED_UNICODE)]);
        return $this->keep($doc, 'return', $wid, $ou, $fp, ['doc_uuid' => $doc, 'conflict' => false, 'order' => $this->orderView($o, false)] + ($ret ? ['return' => $ret] : []), $cts, $actor, ['kind' => $kind, 'condition' => $cond, 'photo_sha256' => $photoSha]);
    }

    /** office: approve (optionally a smaller refund) or reject a pending return */
    public function decideReturn(string $ref, string $decision, string $refund, string $note, array $actor): array
    {
        $r = $this->s->record('return', $ref); if (!$r) throw new Err('not_found', 'not found', 404);
        if (!in_array($decision, ['approve', 'reject'], true)) throw new Err('bad_decision', 'approve or reject', 422);
        if ($r['status'] !== 'pending') throw new Err('already_decided', 'this return was already decided: ' . $r['status'], 409);
        $d = $r['data']; $o = $this->s->order($d['order_uuid']); $note = mb_substr(trim($note), 0, 300);
        if ($decision === 'approve') {
            if (trim($refund) !== '') { $refund = Units::rub(str_replace(',', '.', trim($refund)), 'refund'); if (Units::kop($refund) > Units::kop($d['total_rub'])) throw new Err('refund_too_high', 'the refund cannot exceed the order total', 422); } else $refund = $d['refund_rub'];
            $d['refund_rub'] = $refund;
        } elseif ($o && $o['status'] === 'return_requested') $this->move($o, in_array($d['prev_status'], ['issued', 'ready_for_issue'], true) ? $d['prev_status'] : 'issued', 'office', $actor, ['rejected_return' => $ref, 'note' => $note]);
        $d['decided_by'] = $actor['name'] ?? ''; $d['decided_at'] = $this->stamp(); $d['note'] = $note;
        $this->rec('return', $ref, (int)$r['warehouse_id'], $decision === 'approve' ? 'approved' : 'rejected', $d, $r);
        while ($inc = $this->s->openIncident('return_request', $d['order_uuid'])) $this->s->closeIncident((int)$inc['id'], (string)($actor['name'] ?? ''), $note);
        while ($inc = $this->s->openIncident('refund_due', $d['order_uuid'])) $this->s->closeIncident((int)$inc['id'], (string)($actor['name'] ?? ''), $note);
        $this->log($actor, 'return.' . ($decision === 'approve' ? 'approved' : 'rejected'), 'order', $d['order_uuid'], ['return' => $ref, 'refund_rub' => $decision === 'approve' ? $refund : null, 'note' => $note]);
        return ['ref' => $ref, 'status' => $decision === 'approve' ? 'approved' : 'rejected', 'refund_rub' => $d['refund_rub']];
    }

    /** office: the money was paid back (approved -> refunded) */
    public function markRefunded(string $ref, string $note, array $actor): array
    {
        $r = $this->s->record('return', $ref); if (!$r) throw new Err('not_found', 'not found', 404);
        if ($r['status'] !== 'approved') throw new Err('not_approved', 'only an approved return can be marked as refunded', 409);
        $d = $r['data']; $d['refunded_by'] = $actor['name'] ?? ''; $d['refunded_at'] = $this->stamp(); $d['refund_note'] = mb_substr(trim($note), 0, 300);
        $this->rec('return', $ref, (int)$r['warehouse_id'], 'refunded', $d, $r);
        $this->log($actor, 'return.refunded', 'order', $d['order_uuid'], ['return' => $ref, 'refund_rub' => $d['refund_rub']]);
        return ['ref' => $ref, 'status' => 'refunded'];
    }

    public static function weekStart(string $day): string { $t = strtotime($day); return date('Y-m-d', $t - ((int)date('N', $t) - 1) * 86400); }

    /** the return registry of a period (return documents created in it) for one warehouse or all (0) */
    public function returnRegistry(string $from, string $to, int $wid = 0): array
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) throw new Err('bad_day', 'from / to are YYYY-MM-DD', 422);
        $rows = []; $tot = ['count' => 0, 'refund_kop' => 0, 'by_status' => []];
        foreach (array_reverse($this->s->records('return', $wid)) as $r) {
            $d = $r['data']; $day = substr((string)($d['requested_at'] ?? $r['created_at']), 0, 10); if ($day < $from || $day > $to) continue;
            $rows[] = ['ref' => $r['ref'], 'warehouse_id' => (int)$r['warehouse_id'], 'day' => $day, 'number' => $d['number'] ?? '', 'customer' => $d['customer'] ?? '', 'source' => $d['source'] ?? 'customer', 'reason' => $d['reason'] ?? '', 'condition' => $d['condition'] ?? 'ok',
                'status' => $r['status'], 'refund_rub' => $d['refund_rub'] ?? '0.00', 'pieces' => array_sum(array_column($d['items'] ?? [], 'qty')), 'received' => !empty($d['received_at']), 'decided_by' => $d['decided_by'] ?? ''];
            $tot['count']++; $tot['by_status'][$r['status']] = ($tot['by_status'][$r['status']] ?? 0) + 1; if (in_array($r['status'], ['approved', 'refunded'], true)) $tot['refund_kop'] += Units::kop($d['refund_rub'] ?? '0');
        }
        return ['from' => $from, 'to' => $to, 'warehouse_id' => $wid, 'rows' => $rows, 'count' => $tot['count'], 'by_status' => $tot['by_status'], 'refund_rub' => Units::fmt($tot['refund_kop'])];
    }

    /** the weekly registry (Monday..Sunday) of a warehouse; a finished week is stored once and sent to the office as a report */
    public function weeklyRegistry(int $wid, string $monday): array
    {
        $monday = self::weekStart($monday); $sunday = date('Y-m-d', strtotime($monday) + 6 * 86400);
        if ($r = $this->s->record('regweek', "$wid:$monday")) return $r['data'];
        $reg = $this->returnRegistry($monday, $sunday, $wid) + ['generated_at' => $this->stamp()];
        if ($sunday < date('Y-m-d', $this->now())) { $this->rec('regweek', "$wid:$monday", $wid, 'sent', $reg); $this->log(self::SYSTEM, 'registry.weekly', 'warehouse', (string)$wid, ['week' => $monday, 'returns' => $reg['count']]); }
        return $reg;
    }

    /** CSV of a registry (";" separated, UTF-8 with BOM for Excel; cells that start like a formula are neutralised) */
    public static function registryCsv(array $reg): string
    {
        $cell = function ($v): string { $v = (string)$v; if ($v !== '' && strpbrk($v[0], "=+-@\t\r") !== false) $v = "'" . $v; return '"' . str_replace('"', '""', $v) . '"'; };
        $head = ['day', 'warehouse', 'order', 'customer', 'source', 'reason', 'condition', 'status', 'pieces', 'refund_rub', 'goods_received', 'decided_by'];
        $out = [implode(';', $head)];
        foreach ($reg['rows'] as $r) $out[] = implode(';', array_map($cell, [$r['day'], $r['warehouse_id'], $r['number'], $r['customer'], $r['source'], $r['reason'], $r['condition'], $r['status'], $r['pieces'], $r['refund_rub'], $r['received'] ? 'yes' : 'no', $r['decided_by']]));
        return "\xEF\xBB\xBF" . implode("\r\n", $out) . "\r\n";
    }

    /* ------------------------------------------------------------ expected stock, blind stock counts, adjustment acts */

    /** what the documents say is on the shelves of a warehouse, corrected by the approved adjustment acts: [barcode => [sku, name, qty]] */
    public function expectedStock(int $wid): array
    {
        $exp = []; $add = function (string $bc, string $sku, string $name, int $q) use (&$exp) { $exp[$bc] ??= ['sku' => $sku, 'name' => $name, 'qty' => 0]; $exp[$bc]['qty'] += $q; };
        $back = []; foreach ($this->s->records('return', $wid) as $r) foreach ($r['data']['items'] ?? [] as $l) $back[$r['data']['order_uuid'] ?? ''][$l['barcode']] = ($back[$r['data']['order_uuid'] ?? ''][$l['barcode']] ?? 0) + (int)$l['qty'];
        foreach ($this->s->ordersOfWarehouse($wid) as $o) {
            if (!Machine::inWarehouse($o['status'])) continue;
            foreach ($this->s->orderItems($o['uuid']) as $i) $add($i['barcode'], $i['sku'], $i['name'], $o['status'] === 'returned' ? (int)($back[$o['uuid']][$i['barcode']] ?? $i['qty']) : (int)$i['received_qty']);
        }
        foreach ($this->s->records('adjust', $wid, 'approved') as $a) $add($a['data']['barcode'], '', $a['data']['name'], (int)$a['data']['diff']);
        return $exp;
    }

    /** office: start a blind stock count of a warehouse (one open count per warehouse) */
    public function stocktakeStart(int $wid, array $actor): array
    {
        foreach ($this->s->records('stocktake', $wid, 'open') as $r) return ['uuid' => $r['ref'], 'status' => 'open', 'items' => count($r['data']['items']), 'replayed' => true];
        $items = []; foreach ($this->expectedStock($wid) as $bc => $x) $items[] = ['barcode' => $bc, 'name' => $x['name']];
        usort($items, fn($a, $b) => strcmp($a['name'], $b['name']));
        $u = Manifest::newUuid();
        $this->rec('stocktake', $u, $wid, 'open', ['items' => $items, 'started_by' => $actor['name'] ?? '', 'started_at' => $this->stamp()]);
        $this->log($actor, 'stocktake.started', 'warehouse', (string)$wid, ['stocktake' => $u, 'items' => count($items)]);
        return ['uuid' => $u, 'status' => 'open', 'items' => count($items)];
    }

    /** what the app gets: names and barcodes only, NEVER an expected quantity */
    public function stocktakeView(array $actor): ?array
    {
        $wid = (int)($actor['warehouse'] ?? 0);
        foreach ($this->s->records('stocktake', $wid, 'open') as $r) return ['uuid' => $r['ref'], 'started_at' => $r['data']['started_at'] ?? '', 'items' => $r['data']['items']];
        return null;
    }

    public function stocktakeSubmit(string $ref, array $in, array $actor): array
    {
        $this->need($actor, 'count');
        $doc = Manifest::uuid($in['doc_uuid'] ?? null, 'doc_uuid'); $fp = self::fp($in + ['stocktake' => $ref]);
        if ($r = $this->replay($doc, 'stocktake', $fp)) return $r;
        $st = $this->s->record('stocktake', $ref); $wid = (int)($actor['warehouse'] ?? 0);
        if (!$st || $wid < 1 || (int)$st['warehouse_id'] !== $wid) throw new Err('not_found', 'not found', 404);
        [$cts] = $this->clientTs($in['client_ts'] ?? null);
        if ($st['status'] !== 'open') {
            $res = ['doc_uuid' => $doc, 'conflict' => true, 'reason' => 'stocktake_' . $st['status']];
            $this->log($actor, 'stocktake.conflict', 'warehouse', (string)$wid, ['doc' => $doc, 'stocktake' => $ref, 'server_status' => $st['status']], $cts);
            return $this->keep($doc, 'stocktake', $wid, $ref, $fp, $res, $cts, $actor, ['stocktake' => $ref]);
        }
        $counted = []; $names = [];
        foreach ((array)($in['lines'] ?? []) as $l) { $bc = trim((string)($l['barcode'] ?? '')); if ($bc === '') continue; $counted[$bc] = ($counted[$bc] ?? 0) + Units::qty($l['qty'] ?? 0, 'count.qty', true); if (!empty($l['name'])) $names[$bc] = mb_substr(trim((string)$l['name']), 0, 120); }
        $exp = $this->expectedStock($wid); $snap = array_column($st['data']['items'], 'name', 'barcode'); $n = 0; $diffs = [];
        foreach (array_unique(array_merge(array_keys($exp), array_keys($counted), array_keys($snap))) as $bc) {
            $e = (int)($exp[$bc]['qty'] ?? 0); $c = (int)($counted[$bc] ?? 0); if ($e === $c) continue;
            $n++; $name = $exp[$bc]['name'] ?? $snap[$bc] ?? $names[$bc] ?? '(unknown)';
            $diffs[] = $row = ['stocktake' => $ref, 'act' => 'ADJ-' . strtoupper(substr($ref, 0, 8)) . '-' . $n, 'barcode' => $bc, 'name' => $name, 'expected' => $e, 'counted' => $c, 'diff' => $c - $e, 'uncounted' => !array_key_exists($bc, $counted)];
            $this->rec('adjust', "$ref:$bc", $wid, 'proposed', $row);
        }
        $d = $st['data']; $d += ['counted' => $counted, 'submitted_by' => $actor['name'] ?? '', 'submitted_at' => $this->stamp(), 'differences' => $n];
        $this->rec('stocktake', $ref, $wid, $n ? 'submitted' : 'closed', $d, $st);
        if ($n) $this->s->addIncident(['warehouse_id' => $wid, 'kind' => 'stocktake_diff', 'ref' => $ref, 'severity' => 'high', 'status' => 'open', 'created_at' => $this->stamp(), 'details' => json_encode(['stocktake' => $ref, 'differences' => $n])]);
        $this->log($actor, 'stocktake.submitted', 'warehouse', (string)$wid, ['doc' => $doc, 'stocktake' => $ref, 'lines' => count($counted), 'differences' => $n], $cts);
        return $this->keep($doc, 'stocktake', $wid, $ref, $fp, ['doc_uuid' => $doc, 'conflict' => false, 'stocktake' => ['uuid' => $ref, 'status' => $n ? 'submitted' : 'closed'], 'differences' => $n], $cts, $actor, ['stocktake' => $ref, 'lines' => $counted]);
    }

    /** office: approve (the act corrects the expected stock from now on) or reject an adjustment line */
    public function decideAdjustment(string $ref, string $decision, string $note, array $actor): array
    {
        $a = $this->s->record('adjust', $ref); if (!$a) throw new Err('not_found', 'not found', 404);
        if (!in_array($decision, ['approve', 'reject'], true)) throw new Err('bad_decision', 'approve or reject', 422);
        if ($a['status'] !== 'proposed') throw new Err('already_decided', 'already decided: ' . $a['status'], 409);
        $d = $a['data']; $d['decided_by'] = $actor['name'] ?? ''; $d['decided_at'] = $this->stamp(); $d['note'] = mb_substr(trim($note), 0, 300);
        $this->rec('adjust', $ref, (int)$a['warehouse_id'], $decision === 'approve' ? 'approved' : 'rejected', $d, $a);
        $this->log($actor, 'adjust.' . ($decision === 'approve' ? 'approved' : 'rejected'), 'warehouse', (string)$a['warehouse_id'], ['act' => $d['act'], 'barcode' => $d['barcode'], 'diff' => $d['diff'], 'note' => $d['note']]);
        $left = array_filter($this->s->records('adjust', (int)$a['warehouse_id'], 'proposed'), fn($x) => ($x['data']['stocktake'] ?? '') === $d['stocktake']);
        if (!$left && ($st = $this->s->record('stocktake', $d['stocktake']))) {
            $this->rec('stocktake', $st['ref'], (int)$st['warehouse_id'], 'closed', $st['data'] + [], $st);
            while ($inc = $this->s->openIncident('stocktake_diff', $st['ref'])) $this->s->closeIncident((int)$inc['id'], (string)($actor['name'] ?? ''), 'all adjustments decided');
        }
        return ['ref' => $ref, 'status' => $decision === 'approve' ? 'approved' : 'rejected', 'stocktake_closed' => !$left];
    }

    /** the adjustment act of one stock count: every line with its decision */
    public function adjustmentAct(string $stocktake): array
    {
        $st = $this->s->record('stocktake', $stocktake); if (!$st) throw new Err('not_found', 'not found', 404);
        $lines = array_values(array_filter(array_reverse($this->s->records('adjust', (int)$st['warehouse_id'])), fn($a) => ($a['data']['stocktake'] ?? '') === $stocktake));
        return ['stocktake' => $stocktake, 'status' => $st['status'], 'warehouse_id' => (int)$st['warehouse_id'], 'started_at' => $st['data']['started_at'] ?? '', 'submitted_at' => $st['data']['submitted_at'] ?? '', 'submitted_by' => $st['data']['submitted_by'] ?? '',
            'lines' => array_map(fn($a) => $a['data'] + ['status' => $a['status'], 'ref' => $a['ref']], $lines)];
    }

    /* ------------------------------------------------------------ shelf cells */

    public static function shelfCode(string $v): string
    {
        $v = strtoupper(trim($v)); if (str_starts_with($v, 'SH:')) $v = substr($v, 3);
        if ($v !== '' && !preg_match('/^[A-Z0-9][A-Z0-9-]{0,19}$/', $v)) throw new Err('bad_shelf', 'a shelf cell is 1-20 letters, digits or "-"', 422);
        return $v;
    }

    /** put the goods of an order into a shelf cell ('' empties the cell field); documents are idempotent like all others */
    public function setShelf(string $ou, array $in, array $actor): array
    {
        $this->need($actor, 'shelf');
        $doc = Manifest::uuid($in['doc_uuid'] ?? null, 'doc_uuid'); $fp = self::fp($in + ['order' => $ou]);
        if ($r = $this->replay($doc, 'shelf', $fp)) return $r;
        $o = $this->own($this->s->order($ou), $actor); $wid = (int)$o['warehouse_id']; [$cts] = $this->clientTs($in['client_ts'] ?? null);
        $shelf = self::shelfCode((string)($in['shelf'] ?? ''));
        if (!Machine::inWarehouse($o['status'])) {
            $res = ['doc_uuid' => $doc, 'conflict' => true, 'reason' => 'order_' . $o['status'], 'server' => $this->orderView($o, false)];
            return $this->keep($doc, 'shelf', $wid, $ou, $fp, $res, $cts, $actor, ['shelf' => $shelf]);
        }
        $was = $o['shelf'] ?? null; $o['shelf'] = $shelf !== '' ? $shelf : null; $o['updated_at'] = $this->stamp(); $this->s->saveOrder($o);
        $this->log($actor, 'shelf.set', 'order', $ou, ['doc' => $doc, 'from' => $was, 'to' => $o['shelf']], $cts);
        return $this->keep($doc, 'shelf', $wid, $ou, $fp, ['doc_uuid' => $doc, 'conflict' => false, 'order' => $this->orderView($o, false)], $cts, $actor, ['shelf' => $shelf]);
    }

    /* ------------------------------------------------------------ sweep: overdue, auto-refund, offline warehouses, stuck shipments */

    /**
     * Periodic check (called by the office dashboard, a button or a scheduler). $presence = [warehouseId => ['name', 'active', 'operators', 'last_seen' (unix|null)]].
     * Idempotent: an alert that is already open is not raised again; one that no longer applies is closed.
     */
    public function sweep(array $presence): array
    {
        $now = $this->now(); $out = ['overdue' => 0, 'refunds' => 0, 'offline' => 0, 'stuck' => 0, 'closed' => 0, 'registries' => 0]; $rules = $this->rules();
        $stamp = $this->stamp();
        foreach ($presence as $wid => $w) {
            $wid = (int)$wid; if (empty($w['active'])) continue; $c = $this->wcfg($wid);
            foreach ($this->s->ordersOfWarehouse($wid) as $o) {
                if ($o['status'] !== 'ready_for_issue' || empty($o['ready_at'])) continue;
                $items = $this->s->orderItems($o['uuid']); [$over, $refund] = $this->days($o, $items, $rules); $age = $now - strtotime((string)$o['ready_at']);
                if ($refund > 0 && $age > $refund * 86400) { $this->autoRefund($o, $items, $refund); $out['refunds']++; }
                elseif ($age > $over * 86400 && !$this->s->openIncident('overdue', $o['uuid'])) {
                    $this->s->addIncident(['warehouse_id' => $wid, 'kind' => 'overdue', 'ref' => $o['uuid'], 'severity' => 'low', 'status' => 'open', 'created_at' => $stamp, 'details' => json_encode(['number' => $o['number'], 'ready_at' => $o['ready_at'], 'days' => $over, 'total_rub' => $o['total_rub']])]); $out['overdue']++;
                }
            }
            if (($w['operators'] ?? 0) > 0 && ($w['last_seen'] ?? null) !== null) {   // a warehouse whose app has not been seen for too long
                $hrs = (int)($this->cfg['offline_hours'] ?? 24); $off = $now - (int)$w['last_seen'] > $hrs * 3600; $open = $this->s->openIncident('offline', (string)$wid);
                if ($off && !$open) { $this->s->addIncident(['warehouse_id' => $wid, 'kind' => 'offline', 'ref' => (string)$wid, 'severity' => 'high', 'status' => 'open', 'created_at' => $stamp, 'details' => json_encode(['warehouse' => $w['name'] ?? '', 'last_seen' => date('Y-m-d H:i:s', (int)$w['last_seen']), 'hours' => $hrs])]); $out['offline']++; }
                elseif (!$off && $open) { $this->s->closeIncident((int)$open['id'], 'system', 'back online'); $out['closed']++; }
            }
        }
        $hrs = (int)($this->cfg['stuck_hours'] ?? 48);
        foreach ($this->s->transfersByStatus(['created', 'transferred']) as $t) {
            if (empty($presence[(int)$t['warehouse_id']]['active'])) continue;
            $since = strtotime((string)($t['delivered_at'] ?: $t['created_at']));
            if ($now - $since > $hrs * 3600 && !$this->s->openIncident('stuck_shipment', $t['uuid'])) {
                $this->s->addIncident(['warehouse_id' => (int)$t['warehouse_id'], 'kind' => 'stuck_shipment', 'ref' => $t['uuid'], 'severity' => 'high', 'status' => 'open', 'created_at' => $stamp, 'details' => json_encode(['status' => $t['status'], 'since' => date('Y-m-d H:i:s', $since), 'hours' => $hrs])]); $out['stuck']++;
            }
        }
        foreach ($this->s->openIncidents('stuck_shipment') as $i) { $t = $this->s->transfer($i['ref']); if (!$t || !in_array($t['status'], ['created', 'transferred'], true)) { $this->s->closeIncident((int)$i['id'], 'system', 'confirmed'); $out['closed']++; } }
        foreach ($this->s->openIncidents('overdue') as $i) { $o = $this->s->order($i['ref']); if (!$o || $o['status'] !== 'ready_for_issue') { $this->s->closeIncident((int)$i['id'], 'system', 'no longer waiting'); $out['closed']++; } }
        $lastWeek = self::weekStart(date('Y-m-d', $now - 7 * 86400));
        foreach ($presence as $wid => $w) if (!empty($w['active']) && !$this->s->record('regweek', "$wid:$lastWeek")) { $this->weeklyRegistry((int)$wid, $lastWeek); $out['registries']++; }
        if (array_sum($out)) $this->log(self::SYSTEM, 'sweep.done', 'warehouse', '0', $out);
        return $out;
    }

    /** an order that nobody collected: back to the head office, the refund is prepared (approved at once when the setting says so) */
    private function autoRefund(array $o, array $items, int $days): void
    {
        $wid = (int)$o['warehouse_id']; [$lines, $refund] = $this->returnLines($items, null); $auto = !empty($this->cfg['refund_auto']); $ref = Manifest::newUuid();
        $this->move($o, 'return_requested', 'system', self::SYSTEM, ['reason' => 'unclaimed', 'days' => $days]);
        $this->rec('return', $ref, $wid, $auto ? 'approved' : 'pending', ['order_uuid' => $o['uuid'], 'number' => $o['number'], 'customer' => $o['customer_name'], 'source' => 'unclaimed', 'reason' => "not collected within $days days", 'condition' => 'ok', 'photo_sha256' => '',
            'items' => $lines, 'refund_rub' => $refund, 'total_rub' => $o['total_rub'], 'prev_status' => 'ready_for_issue', 'requested_by' => 'system', 'requested_at' => $this->stamp()] + ($auto ? ['decided_by' => 'system', 'decided_at' => $this->stamp(), 'note' => 'automatic'] : []));
        while ($i = $this->s->openIncident('overdue', $o['uuid'])) $this->s->closeIncident((int)$i['id'], 'system', 'refund started');
        $this->s->addIncident(['warehouse_id' => $wid, 'kind' => 'refund_due', 'ref' => $o['uuid'], 'severity' => $auto ? 'low' : 'medium', 'status' => $auto ? 'resolved' : 'open', 'created_at' => $this->stamp(), 'details' => json_encode(['number' => $o['number'], 'refund_rub' => $refund, 'return' => $ref, 'auto' => $auto])]);
        $this->log(self::SYSTEM, 'refund.auto', 'order', $o['uuid'], ['return' => $ref, 'refund_rub' => $refund, 'approved' => $auto, 'days' => $days]);
    }
}
