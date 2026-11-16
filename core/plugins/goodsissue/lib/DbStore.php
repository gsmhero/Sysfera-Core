<?php
declare(strict_types=1);
namespace SysferaGi;

use Sysfera\Db;

/** MySQL storage of the exchange engine. The audit log has an INSERT only path (no update / delete anywhere in this plugin). */
final class DbStore implements Store
{
    public function __construct(private Db $db) {}

    public function transfer(string $uuid): ?array { return $this->db->row('SELECT * FROM {p}gi_transfers WHERE uuid = ?', [$uuid]); }
    public function saveTransfer(array $t): void
    {
        $this->db->q('INSERT INTO {p}gi_transfers (uuid, warehouse_id, status, checksum, created_by, created_at, delivered_at, confirmed_at) VALUES (?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE status = VALUES(status), delivered_at = VALUES(delivered_at), confirmed_at = VALUES(confirmed_at)',
            [$t['uuid'], $t['warehouse_id'], $t['status'], $t['checksum'], $t['created_by'] ?? '', $t['created_at'], $t['delivered_at'] ?? null, $t['confirmed_at'] ?? null]);
    }
    public function items(string $tu): array { return $this->db->rows('SELECT * FROM {p}gi_items WHERE transfer_uuid = ? ORDER BY id', [$tu]); }
    public function saveItems(string $tu, array $items): void
    {
        foreach ($items as $i) {
            if (!empty($i['id'])) $this->db->q('UPDATE {p}gi_items SET received_qty = ?, state = ? WHERE id = ?', [$i['received_qty'], $i['state'], $i['id']]);
            else $this->db->q('INSERT INTO {p}gi_items (transfer_uuid, order_uuid, sku, name, qty, weight_g, barcode, price_rub, received_qty, state) VALUES (?,?,?,?,?,?,?,?,?,?)',
                [$tu, $i['order_uuid'], $i['sku'], $i['name'], $i['qty'], $i['weight_g'], $i['barcode'], $i['price_rub'], $i['received_qty'], $i['state']]);
        }
    }
    public function order(string $uuid): ?array { return $this->db->row('SELECT * FROM {p}gi_orders WHERE uuid = ?', [$uuid]); }
    public function saveOrder(array $o): void
    {
        $this->db->q('INSERT INTO {p}gi_orders (uuid, number, warehouse_id, transfer_uuid, customer_name, customer_phone, customer_email, total_rub, status, version, ready_at, issued_at, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE status = VALUES(status), version = VALUES(version), ready_at = VALUES(ready_at), issued_at = VALUES(issued_at), updated_at = VALUES(updated_at)',
            [$o['uuid'], $o['number'], $o['warehouse_id'], $o['transfer_uuid'], $o['customer_name'], $o['customer_phone'], $o['customer_email'] ?? '', $o['total_rub'], $o['status'], $o['version'], $o['ready_at'] ?? null, $o['issued_at'] ?? null, $o['created_at'], $o['updated_at']]);
    }
    public function ordersOfTransfer(string $tu): array { return $this->db->rows('SELECT * FROM {p}gi_orders WHERE transfer_uuid = ? ORDER BY number, uuid', [$tu]); }
    public function ordersOfWarehouse(int $w): array { return $this->db->rows('SELECT * FROM {p}gi_orders WHERE warehouse_id = ?', [$w]); }
    public function orderItems(string $ou): array { return $this->db->rows('SELECT * FROM {p}gi_items WHERE order_uuid = ? ORDER BY id', [$ou]); }

    public function doc(string $uuid): ?array { return $this->db->row('SELECT * FROM {p}gi_docs WHERE uuid = ?', [$uuid]); }
    public function saveDoc(array $d): bool
    {
        try {
            $this->db->q('INSERT INTO {p}gi_docs (uuid, type, warehouse_id, ref, fingerprint, payload, result, day, client_ts, created_at, actor, device_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
                [$d['uuid'], $d['type'], $d['warehouse_id'], $d['ref'], $d['fingerprint'], $d['payload'], $d['result'], $d['day'], $d['client_ts'], $d['created_at'], $d['actor'], $d['device_id']]);
            return true;
        } catch (\PDOException $e) { if ($e->getCode() === '23000') return false; throw $e; }   // duplicate primary key: a parallel request saved it first
    }
    public function docs(int $w, string $type, string $day = ''): array
    {
        return $this->db->rows('SELECT * FROM {p}gi_docs WHERE warehouse_id = ? AND type = ?' . ($day !== '' ? ' AND day = ?' : '') . ' ORDER BY created_at, uuid', $day !== '' ? [$w, $type, $day] : [$w, $type]);
    }

    public function code(string $ou): ?array { return $this->db->row('SELECT * FROM {p}gi_codes WHERE order_uuid = ?', [$ou]); }
    public function saveCode(array $c): void
    {
        $this->db->q('INSERT INTO {p}gi_codes (order_uuid, code_hash, expires_at, attempts, used_at, created_at) VALUES (?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE code_hash = VALUES(code_hash), expires_at = VALUES(expires_at), attempts = VALUES(attempts), used_at = VALUES(used_at), created_at = VALUES(created_at)',
            [$c['order_uuid'], $c['code_hash'], $c['expires_at'], $c['attempts'], $c['used_at'], $c['created_at']]);
    }

    public function audit(array $e): array
    {
        $pdo = $this->db->pdo(); $own = !$pdo->inTransaction(); if ($own) $pdo->beginTransaction();
        try {
            $prev = (string)($this->db->val('SELECT hash FROM {p}gi_audit ORDER BY id DESC LIMIT 1 FOR UPDATE') ?? Audit::GENESIS);
            $e = Audit::seal($e, $prev);
            $this->db->q('INSERT INTO {p}gi_audit (ts, client_ts, actor, action, entity, entity_uuid, device_id, code_hash, data, prev_hash, hash) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                [$e['ts'], $e['client_ts'], $e['actor'], $e['action'], $e['entity'], $e['entity_uuid'], $e['device_id'], $e['code_hash'], $e['data'], $e['prev_hash'], $e['hash']]);
            $e['id'] = $this->db->insertId();
            if ($own) $pdo->commit();
        } catch (\Throwable $x) { if ($own && $pdo->inTransaction()) $pdo->rollBack(); throw $x; }
        return $e;
    }

    public function addIncident(array $i): int
    {
        $this->db->q('INSERT INTO {p}gi_incidents (warehouse_id, kind, ref, severity, status, details, created_at) VALUES (?,?,?,?,?,?,?)', [$i['warehouse_id'], $i['kind'], $i['ref'], $i['severity'], $i['status'], $i['details'] ?? null, $i['created_at']]);
        return $this->db->insertId();
    }
    public function openIncident(string $kind, string $ref): ?array { return $this->db->row("SELECT * FROM {p}gi_incidents WHERE kind = ? AND ref = ? AND status = 'open' LIMIT 1", [$kind, $ref]); }
    public function closeIncident(int $id, string $by, string $note): void { $this->db->q("UPDATE {p}gi_incidents SET status = 'resolved', note = ?, closed_by = ?, closed_at = NOW() WHERE id = ?", [mb_substr($note, 0, 300), mb_substr($by, 0, 120), $id]); }
    public function report(int $w, string $day): ?array { return $this->db->row('SELECT * FROM {p}gi_reports WHERE warehouse_id = ? AND day = ?', [$w, $day]); }
    public function saveReport(array $r): void
    {
        $this->db->q('INSERT INTO {p}gi_reports (warehouse_id, day, body, created_at) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE body = VALUES(body), created_at = VALUES(created_at)', [$r['warehouse_id'], $r['day'], $r['body'], $r['created_at']]);
    }
}
