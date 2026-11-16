<?php
declare(strict_types=1);
namespace SysferaGi;

/**
 * The document-exchange engine between the head-office system and the pickup-warehouse app.
 * Every operation of the app is a CONFIRMATION of a document the office created; every one carries a document UUID, so a replay
 * returns the first result and never creates a duplicate. No storage and no HTTP in here: Store and the plugin do that.
 *
 * $actor = ['kind' => 'operator'|'office'|'system', 'user' => int|null, 'name' => string, 'warehouse' => int|null, 'device' => string|null]
 * $cfg   = ['secret' => code hashing key, 'code_ttl' => seconds, 'id_threshold_kop' => int (0 = off), 'overdue_days' => int]
 */
final class Exchange
{
    use Extras;
    public const PERMS = ['receive', 'issue', 'return', 'count', 'shelf'];
    public const CONDITIONS = ['ok', 'opened', 'damaged'];
    private const SYSTEM = ['kind' => 'system', 'name' => 'sweep', 'user' => null, 'warehouse' => null, 'device' => null];
    private const SKEW = 300;
    private const STALE = 30 * 86400;
    private $clock; private $onCode;

    public function __construct(private Store $s, private array $cfg, ?callable $onCode = null, ?callable $clock = null)
    {
        $this->clock = $clock ?? 'time'; $this->onCode = $onCode;
    }

    public function now(): int { return (int)($this->clock)(); }
    private function stamp(?int $t = null): string { return date('Y-m-d H:i:s', $t ?? $this->now()); }

    /* ------------------------------------------------------------ helpers */

    private function log(array $actor, string $action, string $entity, string $uuid, array $data = [], ?int $clientTs = null, ?string $codeHash = null): void
    {
        $this->s->audit(['ts' => $this->stamp(), 'client_ts' => $clientTs !== null ? $this->stamp($clientTs) : null, 'actor' => ($actor['kind'] ?? 'system') . ':' . ($actor['name'] ?? ''),
            'action' => $action, 'entity' => $entity, 'entity_uuid' => $uuid, 'device_id' => $actor['device'] ?? null, 'code_hash' => $codeHash, 'data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    }

    private static function canon(mixed $v): mixed
    {
        if (!is_array($v)) return $v;
        if (array_is_list($v)) return array_map([self::class, 'canon'], $v);
        ksort($v); return array_map([self::class, 'canon'], $v);
    }
    private static function fp(array $in): string { return hash('sha256', json_encode(self::canon($in), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); }

    /** the original result of a document that was already processed (null when it is new) */
    private function replay(string $uuid, string $type, string $fp): ?array
    {
        $d = $this->s->doc($uuid);
        if (!$d) return null;
        if ($d['type'] !== $type || $d['fingerprint'] !== $fp) throw new Err('idempotency_conflict', 'this document uuid was already used for different content', 409);
        return ['replayed' => true] + (json_decode((string)$d['result'], true) ?: []);
    }

    private function keep(string $uuid, string $type, int $wid, string $ref, string $fp, array $result, ?int $clientTs, array $actor, array $payload = [], string $day = ''): array
    {
        $ok = $this->s->saveDoc(['uuid' => $uuid, 'type' => $type, 'warehouse_id' => $wid, 'ref' => $ref, 'fingerprint' => $fp, 'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'result' => json_encode($result, JSON_UNESCAPED_UNICODE), 'day' => $day !== '' ? $day : date('Y-m-d', $this->now()), 'client_ts' => $clientTs !== null ? $this->stamp($clientTs) : null,
            'created_at' => $this->stamp(), 'actor' => ($actor['kind'] ?? '') . ':' . ($actor['name'] ?? ''), 'device_id' => $actor['device'] ?? null]);
        if (!$ok) return ['replayed' => true] + (json_decode((string)($this->s->doc($uuid)['result'] ?? '[]'), true) ?: []);   // a parallel duplicate won the race
        return $result;
    }

    /** the time the device says the action happened; kept as is unless it is in the future (then the server time) */
    private function clientTs(mixed $v): array
    {
        if ($v === null || $v === '') return [null, []];
        if (!is_int($v) && !(is_string($v) && ctype_digit($v))) throw new Err('bad_time', 'client_ts must be unix seconds', 422);
        $v = (int)$v; $now = $this->now(); $flags = [];
        if ($v > $now + self::SKEW) { $flags[] = 'clock_ahead'; $v = $now; }
        if ($v < $now - self::STALE) $flags[] = 'stale';
        return [$v, $flags];
    }

    private function own(?array $row, array $actor): array
    {
        if (!$row || (($actor['warehouse'] ?? null) !== null && (int)$row['warehouse_id'] !== (int)$actor['warehouse'])) throw new Err('not_found', 'not found', 404);
        return $row;
    }

    public function orderView(array $o, bool $items = true): array
    {
        $v = ['uuid' => $o['uuid'], 'number' => $o['number'], 'status' => $o['status'], 'version' => (int)$o['version'], 'transfer_uuid' => $o['transfer_uuid'], 'customer_name' => $o['customer_name'], 'customer_phone' => $o['customer_phone'],
            'total_rub' => $o['total_rub'], 'ready_at' => $o['ready_at'], 'issued_at' => $o['issued_at'], 'id_check' => $this->idCheck($o), 'photo_required' => $this->photoRequired($o), 'shelf' => $o['shelf'] ?? null, 'handover' => $o['handover'] ?? null];
        if ($items) $v['items'] = array_map(fn($i) => ['barcode' => $i['barcode'], 'sku' => $i['sku'], 'name' => $i['name'], 'qty' => (int)$i['qty'], 'weight_g' => (int)$i['weight_g'], 'price_rub' => $i['price_rub'], 'received_qty' => (int)$i['received_qty'], 'state' => $i['state']], $this->s->orderItems($o['uuid']));
        return $v;
    }
    public function idCheck(array $o): bool { $t = (int)($this->wcfg((int)$o['warehouse_id'])['id_threshold_kop'] ?? 0); return $t > 0 && Units::kop($o['total_rub']) >= $t; }

    public function transferView(string $uuid, array $actor): array
    {
        $t = $this->own($this->s->transfer($uuid), $actor);
        return ['uuid' => $t['uuid'], 'warehouse_id' => (int)$t['warehouse_id'], 'status' => $t['status'], 'checksum' => $t['checksum'], 'units' => ['weight' => 'g', 'money' => 'RUB'], 'created_at' => $t['created_at'],
            'orders' => array_map(fn($o) => $this->orderView($o), $this->s->ordersOfTransfer($uuid))];
    }

    private function move(array &$o, string $to, string $by, array $actor, array $data = [], ?int $clientTs = null): void
    {
        $from = $o['status']; Machine::go($from, $to, $by);
        $o['status'] = $to; $o['version'] = (int)$o['version'] + 1; $o['updated_at'] = $this->stamp();
        if ($to === 'ready_for_issue' && empty($o['ready_at'])) $o['ready_at'] = $this->stamp();
        if ($to === 'issued') $o['issued_at'] = $this->stamp();
        $this->s->saveOrder($o);
        $this->log($actor, 'order.' . $to, 'order', $o['uuid'], ['from' => $from] + $data, $clientTs);
    }

    private function issueCode(array $o, array $actor): string
    {
        $code = Code::make(); $ttl = (int)($this->wcfg((int)$o['warehouse_id'])['code_ttl'] ?? 172800);
        $this->s->saveCode(['order_uuid' => $o['uuid'], 'code_hash' => Code::hash((string)$this->cfg['secret'], $o['uuid'], $code), 'expires_at' => $this->now() + $ttl,
            'attempts' => 0, 'used_at' => null, 'created_at' => $this->stamp()]);
        $this->log($actor, 'code.issued', 'order', $o['uuid'], ['expires' => $this->stamp($this->now() + $ttl)], null, Code::hash((string)$this->cfg['secret'], $o['uuid'], $code));
        if ($this->onCode) ($this->onCode)($o, $code);   // delivery to the customer (SMS / push / e-mail) is the plugin's business; the app never gets the code
        return $code;
    }

    /* ------------------------------------------------------------ office: transfer order */

    /** @return array result; a repeated submission returns the original result with replayed = true */
    public function createTransfer(array $in, array $actor): array
    {
        $m = Manifest::parse($in); $uuid = strtolower((string)$in['uuid']);
        $wid = (int)($in['warehouse_id'] ?? 0); if ($wid < 1) throw new Err('bad_warehouse', 'warehouse_id is required', 422);
        if ($t = $this->s->transfer($uuid)) {
            if (!hash_equals($t['checksum'], $m['checksum']) || (int)$t['warehouse_id'] !== $wid) throw new Err('idempotency_conflict', 'this manifest uuid exists with different contents', 409);
            return ['replayed' => true, 'uuid' => $uuid, 'status' => $t['status'], 'checksum' => $t['checksum'], 'orders' => count($this->s->ordersOfTransfer($uuid))];
        }
        $this->s->saveTransfer(['uuid' => $uuid, 'warehouse_id' => $wid, 'status' => 'created', 'checksum' => $m['checksum'], 'created_by' => $actor['name'] ?? '', 'created_at' => $this->stamp(), 'delivered_at' => null, 'confirmed_at' => null]);
        foreach ($m['orders'] as $o) $this->s->saveOrder($o + ['warehouse_id' => $wid, 'transfer_uuid' => $uuid, 'status' => 'created', 'version' => 1, 'ready_at' => null, 'issued_at' => null, 'created_at' => $this->stamp(), 'updated_at' => $this->stamp()]);
        $this->s->saveItems($uuid, $m['items']);
        $this->log($actor, 'transfer.created', 'transfer', $uuid, ['warehouse' => $wid, 'orders' => count($m['orders']), 'items' => count($m['items']), 'checksum' => $m['checksum']]);
        return ['uuid' => $uuid, 'status' => 'created', 'checksum' => $m['checksum'], 'orders' => count($m['orders'])];
    }

    /* ------------------------------------------------------------ app: manifest delivery and receipt */

    /** the app fetches a manifest: created -> transferred (repeatable) */
    public function deliver(string $uuid, array $actor): array
    {
        $t = $this->own($this->s->transfer($uuid), $actor);
        if ($t['status'] === 'created') {
            $t['status'] = 'transferred'; $t['delivered_at'] = $this->stamp(); $this->s->saveTransfer($t);
            foreach ($this->s->ordersOfTransfer($uuid) as $o) $this->move($o, 'transferred', 'system', $actor);
            $this->log($actor, 'transfer.delivered', 'transfer', $uuid);
        }
        return $this->transferView($uuid, $actor);
    }

    /**
     * Receipt of a manifest: scans of the physical packages + reported discrepancies. Clean -> every order is ready_for_issue (a code is issued);
     * a difference blocks only the affected orders; "reject" blocks all of them. Whatever the office changed in the meantime wins (conflict).
     */
    public function receipt(string $tu, array $in, array $actor): array
    {
        $this->need($actor, 'receive');
        $doc = Manifest::uuid($in['doc_uuid'] ?? null, 'doc_uuid'); $fp = self::fp($in);
        if ($r = $this->replay($doc, 'receipt', $fp)) return $r;
        $t = $this->own($this->s->transfer($tu), $actor); $wid = (int)$t['warehouse_id'];
        [$cts, $flags] = $this->clientTs($in['client_ts'] ?? null);
        if (!hash_equals($t['checksum'], strtolower((string)($in['checksum'] ?? '')))) throw new Err('manifest_changed', 'the manifest checksum differs from the server copy: fetch it again', 409, ['server_checksum' => $t['checksum']]);
        $decision = (string)($in['decision'] ?? 'confirm');
        if (!in_array($decision, ['confirm', 'reject'], true)) throw new Err('bad_decision', 'decision is confirm or reject', 422);
        if ($t['status'] === 'created') throw new Err('not_delivered', 'fetch the manifest first', 409);
        if ($t['status'] !== 'transferred') {   // somebody (another device, the office) was first: the server copy wins, nothing is applied
            $res = ['doc_uuid' => $doc, 'conflict' => true, 'reason' => 'transfer_' . $t['status'], 'server' => $this->transferView($tu, $actor)];
            $this->log($actor, 'receipt.conflict', 'transfer', $tu, ['doc' => $doc, 'server_status' => $t['status']] + ($flags ? ['flags' => $flags] : []), $cts);
            return $this->keep($doc, 'receipt', $wid, $tu, $fp, $res, $cts, $actor, $in);
        }

        $items = $this->s->items($tu); $byBc = []; foreach ($items as $k => $i) $byBc[$i['barcode']] = $k;
        $scanned = []; $unknown = [];
        foreach ((array)($in['scans'] ?? []) as $sc) {
            $bc = trim((string)($sc['barcode'] ?? '')); $q = Units::qty($sc['qty'] ?? 1, 'scan.qty');
            if (!isset($byBc[$bc])) { $unknown[$bc] = ($unknown[$bc] ?? 0) + $q; continue; }
            $scanned[$bc] = ($scanned[$bc] ?? 0) + $q;
        }
        $reported = [];
        foreach ((array)($in['discrepancies'] ?? []) as $d) {
            $kind = (string)($d['kind'] ?? ''); if (!in_array($kind, ['missing', 'damaged', 'extra', 'wrong_weight', 'unknown'], true)) throw new Err('bad_discrepancy', 'unknown discrepancy kind', 422);
            $bc = trim((string)($d['barcode'] ?? '')); $reported[$bc][] = ['kind' => $kind, 'qty' => isset($d['qty']) ? Units::qty($d['qty'], 'discrepancy.qty', true) : null, 'note' => mb_substr(trim((string)($d['note'] ?? '')), 0, 300)];
        }
        $damaged = [];
        foreach ($items as $k => &$i) {
            $bc = $i['barcode']; $sc = $scanned[$bc] ?? 0; $qty = (int)$i['qty']; $dm = 0; $wrongW = false;
            foreach ($reported[$bc] ?? [] as $d) { if ($d['kind'] === 'damaged') $dm += $d['qty'] ?? $sc; if ($d['kind'] === 'wrong_weight') $wrongW = true; }
            $dm = min($dm, $sc);
            $state = $sc === 0 ? 'missing' : ($dm > 0 ? 'damaged' : ($sc < $qty ? 'short' : ($sc > $qty ? 'extra' : ($wrongW ? 'weight' : 'ok'))));
            $i['received_qty'] = $sc - $dm; $i['state'] = $state;
        }
        unset($i);
        $this->s->saveItems($tu, $items);

        $orders = $this->s->ordersOfTransfer($tu); $out = []; $blocked = 0; $incidents = 0;
        foreach ($orders as $o) {
            $its = array_values(array_filter($items, fn($i) => $i['order_uuid'] === $o['uuid']));
            $bad = array_values(array_filter($its, fn($i) => $i['state'] !== 'ok'));
            if ($decision === 'reject' || $bad) {
                $this->move($o, 'blocked', 'operator', $actor, ['doc' => $doc, 'reason' => $decision === 'reject' ? 'rejected' : 'discrepancy'], $cts);
                $this->s->addIncident(['warehouse_id' => $wid, 'kind' => 'receipt_discrepancy', 'ref' => $o['uuid'], 'severity' => 'high', 'status' => 'open', 'created_at' => $this->stamp(),
                    'details' => json_encode(['transfer' => $tu, 'doc' => $doc, 'decision' => $decision, 'items' => array_map(fn($i) => ['barcode' => $i['barcode'], 'name' => $i['name'], 'expected' => (int)$i['qty'], 'received' => (int)$i['received_qty'], 'state' => $i['state'], 'reported' => $reported[$i['barcode']] ?? []], $bad ?: $its)], JSON_UNESCAPED_UNICODE)]);
                $blocked++; $incidents++;
            } else {
                $this->move($o, 'received', 'operator', $actor, ['doc' => $doc], $cts);
                $this->move($o, 'ready_for_issue', 'system', $actor, ['doc' => $doc]);
                $this->issueCode($o, ['kind' => 'system', 'name' => 'exchange', 'device' => $actor['device'] ?? null]);
            }
            $out[] = $this->orderView($o) + [];
        }
        if ($unknown || isset($reported[''])) {
            $this->s->addIncident(['warehouse_id' => $wid, 'kind' => 'unknown_package', 'ref' => $tu, 'severity' => 'medium', 'status' => 'open', 'created_at' => $this->stamp(), 'details' => json_encode(['transfer' => $tu, 'unknown' => $unknown, 'reported' => $reported[''] ?? []], JSON_UNESCAPED_UNICODE)]);
            $incidents++;
        }
        $t['status'] = $blocked ? 'disputed' : 'confirmed'; $t['confirmed_at'] = $this->stamp(); $this->s->saveTransfer($t);
        $this->log($actor, $blocked ? 'receipt.discrepancy' : 'receipt.confirmed', 'transfer', $tu, ['doc' => $doc, 'decision' => $decision, 'blocked_orders' => $blocked, 'unknown' => $unknown] + ($flags ? ['flags' => $flags] : []), $cts);
        $res = ['doc_uuid' => $doc, 'conflict' => false, 'transfer' => ['uuid' => $tu, 'status' => $t['status']], 'orders' => $out, 'incidents' => $incidents];
        return $this->keep($doc, 'receipt', $wid, $tu, $fp, $res, $cts, $actor, $in);
    }

    /* ------------------------------------------------------------ app: issue to the customer */

    /** ONLY with status ready_for_issue AND the valid one-time code (+ ID check above the threshold). Needs the server: it is never queued offline. */
    public function issue(string $ou, array $in, array $actor): array
    {
        $this->need($actor, 'issue');
        $doc = Manifest::uuid($in['doc_uuid'] ?? null, 'doc_uuid');
        $evidence = (string)($in['signature'] ?? ''); $evHash = $evidence !== '' ? hash('sha256', $evidence) : '';
        [$photo, $photoHash] = self::photo($in['photo'] ?? null); $courier = $this->courierIn($in);
        $fp = self::fp(['order' => $ou, 'code' => (string)($in['code'] ?? ''), 'id_checked' => !empty($in['id_checked']), 'evidence' => $evHash, 'photo' => $photoHash, 'courier' => $courier]);
        if ($r = $this->replay($doc, 'issue_act', $fp)) return $r;
        $o = $this->own($this->s->order($ou), $actor); $wid = (int)$o['warehouse_id'];
        [$cts, $flags] = $this->clientTs($in['client_ts'] ?? null);
        if ($o['status'] !== 'ready_for_issue') {
            $this->log($actor, 'issue.refused', 'order', $ou, ['reason' => 'status_' . $o['status'], 'doc' => $doc], $cts);
            throw new Err($o['status'] === 'issued' ? 'already_issued' : ($o['status'] === 'blocked' ? 'order_blocked' : 'not_ready'), 'order status is ' . $o['status'], 409, ['server' => $this->orderView($o, false)]);
        }
        $entered = trim((string)($in['code'] ?? '')); $rec = $this->s->code($ou);
        $hash = Code::hash((string)$this->cfg['secret'], $ou, $entered);
        if ($p = Code::problem($rec, $this->now())) { $this->log($actor, 'issue.refused', 'order', $ou, ['reason' => $p, 'doc' => $doc], $cts, $hash); throw new Err($p, 'the code cannot be used: ' . $p, 403); }
        if (!preg_match('/^\d{6}$/', $entered) || !hash_equals((string)$rec['code_hash'], $hash)) {
            $rec['attempts'] = (int)$rec['attempts'] + 1; $this->s->saveCode($rec);
            $this->log($actor, 'issue.code_failed', 'order', $ou, ['attempt' => $rec['attempts'], 'doc' => $doc], $cts, $hash);
            if ($rec['attempts'] >= Code::MAX_ATTEMPTS) $this->s->addIncident(['warehouse_id' => $wid, 'kind' => 'code_locked', 'ref' => $ou, 'severity' => 'high', 'status' => 'open', 'created_at' => $this->stamp(), 'details' => json_encode(['order' => $o['number'], 'device' => $actor['device'] ?? null])]);
            throw new Err('code_wrong', 'wrong code', 403, ['attempts_left' => max(0, Code::MAX_ATTEMPTS - (int)$rec['attempts'])]);
        }
        if ($this->idCheck($o) && empty($in['id_checked'])) { $this->log($actor, 'issue.refused', 'order', $ou, ['reason' => 'id_check_required', 'doc' => $doc], $cts, $hash); throw new Err('id_check_required', 'the customer\'s ID must be checked for this order', 403); }
        if (strlen($evidence) < 20) throw new Err('evidence_required', 'the customer signature or photo is required', 422);
        if ($photo === '' && $this->photoRequired($o)) { $this->log($actor, 'issue.refused', 'order', $ou, ['reason' => 'photo_required', 'doc' => $doc], $cts, $hash); throw new Err('photo_required', 'a photo of the handover is required for this order', 422); }

        $rec['used_at'] = $this->stamp(); $this->s->saveCode($rec);
        $o['handover'] = $courier ? 'courier' : 'customer'; $o['courier'] = $courier ? json_encode($courier, JSON_UNESCAPED_UNICODE) : null;
        $extra = ['handover' => $o['handover']] + ($courier ? ['courier' => $courier] : []) + ($photoHash !== '' ? ['photo_sha256' => $photoHash] : []);
        $this->move($o, 'issued', 'operator', $actor, ['doc' => $doc, 'id_checked' => !empty($in['id_checked']), 'evidence_sha256' => $evHash] + $extra + ($flags ? ['flags' => $flags] : []), $cts);
        $res = ['doc_uuid' => $doc, 'order' => $this->orderView($o, false), 'act' => ['doc_uuid' => $doc, 'evidence_sha256' => $evHash, 'issued_at' => $o['issued_at'], 'id_checked' => !empty($in['id_checked'])] + $extra];
        $this->log($actor, 'issue.act', 'order', $ou, ['doc' => $doc, 'evidence_sha256' => $evHash] + $extra, $cts, $hash);
        return $this->keep($doc, 'issue_act', $wid, $ou, $fp, $res, $cts, $actor, ['id_checked' => !empty($in['id_checked']), 'evidence_sha256' => $evHash] + $extra);
    }

    /* ------------------------------------------------------------ app: end-of-day stock count */

    public function count(array $in, array $actor): array
    {
        $this->need($actor, 'count');
        $doc = Manifest::uuid($in['doc_uuid'] ?? null, 'doc_uuid'); $fp = self::fp($in);
        if ($r = $this->replay($doc, 'count', $fp)) return $r;
        $wid = (int)($actor['warehouse'] ?? 0); if ($wid < 1) throw new Err('forbidden', 'a count belongs to a warehouse', 403);
        [$cts, $flags] = $this->clientTs($in['client_ts'] ?? null);
        $lines = []; foreach ((array)($in['lines'] ?? []) as $l) { $bc = trim((string)($l['barcode'] ?? '')); if ($bc === '') continue; $lines[$bc] = ($lines[$bc] ?? 0) + Units::qty($l['qty'] ?? 0, 'count.qty', true); }
        $day = (string)($in['day'] ?? ''); $today = date('Y-m-d', $this->now());
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) || abs(strtotime($day) - strtotime($today)) > 2 * 86400) $day = $today;
        $this->log($actor, 'count.submitted', 'warehouse', (string)$wid, ['doc' => $doc, 'day' => $day, 'lines' => count($lines)] + ($flags ? ['flags' => $flags] : []), $cts);
        return $this->keep($doc, 'count', $wid, (string)$wid, $fp, ['doc_uuid' => $doc, 'conflict' => false, 'day' => $day, 'lines' => count($lines)], $cts, $actor, ['day' => $day, 'lines' => $lines], $day);
    }

    /* ------------------------------------------------------------ app: offline queue */

    /**
     * A batch of actions queued offline: {type: receipt|return|count, doc_uuid, ref, client_ts, payload}. They run in the order they happened
     * (original client_ts); each is independent and idempotent. Where the server moved on meanwhile the answer says conflict + the server copy.
     * Issuing is never accepted here (it needs the live check of the code).
     */
    public function sync(array $actions, array $actor): array
    {
        if (count($actions) > 200) throw new Err('too_many', 'at most 200 actions per sync', 413);
        usort($actions, fn($a, $b) => ((int)($a['client_ts'] ?? 0)) <=> ((int)($b['client_ts'] ?? 0)));
        $out = [];
        foreach ($actions as $a) {
            $type = (string)($a['type'] ?? ''); $doc = (string)($a['doc_uuid'] ?? ''); $ref = (string)($a['ref'] ?? '');
            $p = (array)($a['payload'] ?? []) + ['doc_uuid' => $doc, 'client_ts' => $a['client_ts'] ?? null];
            try {
                $r = match ($type) {
                    'receipt' => $this->receipt($ref, $p, $actor),
                    'return' => $this->returnAction($ref, $p, $actor),
                    'count' => $this->count($p, $actor),
                    'shelf' => $this->setShelf($ref, $p, $actor),
                    'stocktake' => $this->stocktakeSubmit($ref, $p, $actor),
                    'issue' => throw new Err('online_only', 'issuing needs the live verification of the code', 409),
                    default => throw new Err('bad_type', 'unknown action type', 422),
                };
                $out[] = ['doc_uuid' => $doc, 'type' => $type, 'ok' => true, 'conflict' => !empty($r['conflict']), 'replayed' => !empty($r['replayed']), 'result' => $r];
            } catch (Err $e) {
                $out[] = ['doc_uuid' => $doc, 'type' => $type, 'ok' => false, 'error' => $e->errCode, 'message' => $e->getMessage(), 'server' => $e->extra['server'] ?? null];
            }
        }
        return $out;
    }

    /* ------------------------------------------------------------ office: resolve a dispute, codes */

    /** a blocked order: release (-> ready_for_issue, a new code) or send back (-> return_requested); its open incidents are closed */
    public function resolve(string $ou, string $resolution, string $note, array $actor): array
    {
        $o = $this->s->order($ou); if (!$o) throw new Err('not_found', 'not found', 404);
        if (!in_array($resolution, ['release', 'return'], true)) throw new Err('bad_resolution', 'release or return', 422);
        $this->move($o, $resolution === 'release' ? 'ready_for_issue' : 'return_requested', 'office', $actor, ['resolution' => $resolution, 'note' => $note]);
        $items = $this->s->orderItems($ou); foreach ($items as &$i) if ($i['state'] !== 'ok') $i['state'] = $resolution === 'release' ? 'accepted' : $i['state']; unset($i);
        $this->s->saveItems((string)$o['transfer_uuid'], $items);
        while ($inc = $this->s->openIncident('receipt_discrepancy', $ou)) $this->s->closeIncident((int)$inc['id'], (string)($actor['name'] ?? ''), $note);
        $t = $this->s->transfer((string)$o['transfer_uuid']);
        if ($t && $t['status'] === 'disputed' && !array_filter($this->s->ordersOfTransfer($t['uuid']), fn($x) => $x['status'] === 'blocked')) { $t['status'] = 'confirmed'; $this->s->saveTransfer($t); }
        $code = $resolution === 'release' ? $this->issueCode($o, $actor) : null;
        return ['order' => $this->orderView($o, false), 'code' => $code];
    }

    /** a new code for an order that is ready_for_issue (the old one stops working) */
    public function newCode(string $ou, array $actor): string
    {
        $o = $this->s->order($ou); if (!$o) throw new Err('not_found', 'not found', 404);
        if ($o['status'] !== 'ready_for_issue') throw new Err('not_ready', 'only for orders that are ready for issue', 409);
        return $this->issueCode($o, $actor);
    }

    /* ------------------------------------------------------------ reconciliation */

    /**
     * End-of-day report of one warehouse: expected stock (from the documents) vs the actual count of the day, issued / not issued / overdue.
     * Every difference becomes an incident for the office. One report per warehouse and day: repeated runs return the stored one.
     */
    public function reconcile(int $wid, string $day, bool $force = false): array
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) throw new Err('bad_day', 'day is YYYY-MM-DD', 422);
        if (!$force && ($r = $this->s->report($wid, $day))) return ['replayed' => true] + (json_decode((string)$r['body'], true) ?: []);
        $issued = []; $notIssued = []; $overdue = []; $exp = $this->expectedStock($wid); $rules = $this->rules(); $dayEnd = strtotime($day . ' 23:59:59');
        foreach ($this->s->ordersOfWarehouse($wid) as $o) {
            if ($o['issued_at'] && substr((string)$o['issued_at'], 0, 10) === $day) $issued[] = ['uuid' => $o['uuid'], 'number' => $o['number'], 'total_rub' => $o['total_rub'], 'handover' => $o['handover'] ?? 'customer'];
            if ($o['status'] === 'ready_for_issue') {
                $row = ['uuid' => $o['uuid'], 'number' => $o['number'], 'ready_at' => $o['ready_at'], 'total_rub' => $o['total_rub'], 'shelf' => $o['shelf'] ?? null];
                $notIssued[] = $row; $due = $this->overdueAt($o, $rules); if ($due !== null && $dayEnd > $due) $overdue[] = $row;
            }
        }
        $count = $this->s->docs($wid, 'count', $day); $counted = $count !== [];
        $act = []; if ($counted) { $last = end($count); $act = json_decode((string)$last['payload'], true)['lines'] ?? []; }
        $lines = []; $inc = 0; $totE = 0; $totA = 0;
        foreach (array_unique(array_merge(array_keys($exp), array_keys($act))) as $bc) {
            $e = (int)($exp[$bc]['qty'] ?? 0); $a = $counted ? (int)($act[$bc] ?? 0) : null; $totE += $e; $totA += (int)$a;
            $lines[] = ['barcode' => $bc, 'sku' => $exp[$bc]['sku'] ?? '', 'name' => $exp[$bc]['name'] ?? '(not in any document)', 'expected' => $e, 'actual' => $a, 'diff' => $a === null ? null : $a - $e];
            if ($a !== null && $a !== $e && !$this->s->openIncident('stock_diff', "$wid:$day:$bc")) { $this->s->addIncident(['warehouse_id' => $wid, 'kind' => 'stock_diff', 'ref' => "$wid:$day:$bc", 'severity' => 'high', 'status' => 'open', 'created_at' => $this->stamp(),
                'details' => json_encode(['day' => $day, 'barcode' => $bc, 'expected' => $e, 'actual' => $a], JSON_UNESCAPED_UNICODE)]); $inc++; }
        }
        if (!$counted && $totE > 0 && !$this->s->openIncident('no_count', "$wid:$day")) { $this->s->addIncident(['warehouse_id' => $wid, 'kind' => 'no_count', 'ref' => "$wid:$day", 'severity' => 'medium', 'status' => 'open', 'created_at' => $this->stamp(), 'details' => json_encode(['day' => $day, 'expected_pieces' => $totE])]); $inc++; }
        foreach ($overdue as $o) if (!$this->s->openIncident('overdue', $o['uuid'])) { $this->s->addIncident(['warehouse_id' => $wid, 'kind' => 'overdue', 'ref' => $o['uuid'], 'severity' => 'low', 'status' => 'open', 'created_at' => $this->stamp(), 'details' => json_encode($o)]); $inc++; }
        usort($lines, fn($a, $b) => [(int)($a['diff'] === 0 || $a['diff'] === null), $a['barcode']] <=> [(int)($b['diff'] === 0 || $b['diff'] === null), $b['barcode']]);
        $rep = ['warehouse_id' => $wid, 'day' => $day, 'generated_at' => $this->stamp(), 'counted' => $counted, 'expected_pieces' => $totE, 'actual_pieces' => $counted ? $totA : null,
            'lines' => $lines, 'issued' => $issued, 'not_issued' => $notIssued, 'overdue' => $overdue, 'incidents' => $inc, 'ok' => $counted && $inc === 0];
        $this->s->saveReport(['warehouse_id' => $wid, 'day' => $day, 'body' => json_encode($rep, JSON_UNESCAPED_UNICODE), 'created_at' => $this->stamp()]);
        $this->log(['kind' => 'system', 'name' => 'reconcile'], 'reconcile.done', 'warehouse', (string)$wid, ['day' => $day, 'incidents' => $inc, 'counted' => $counted]);
        return $rep;
    }
}
