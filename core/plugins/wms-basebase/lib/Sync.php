<?php
declare(strict_types=1);
namespace SysferaBb;

use Sysfera\Db;

/** Moves data between the WMS and Basebase: products + stock out, orders in, shipment status back. */
final class Sync
{
    /**
     * @param array $wms services of the WMS plugin ('docs' => SysferaWms\Docs, 'stock' => SysferaWms\Stock)
     * @param \Closure $cfg  fn(string $key, string $default = ''): string
     * @param \Closure $set  fn(string $key, string $value): void
     */
    public function __construct(private Db $db, private array $wms, private Client $api, private \Closure $cfg, private \Closure $set, private \Sysfera\Repo $contacts, private ?\Closure $log = null) {}

    private function c(string $k, string $d = ''): string { return ($this->cfg)($k, $d); }
    private function note(string $type, array $data): void { if ($this->log) ($this->log)($type, $data); }
    public function sellerId(): int { return (int)$this->c('seller_id', '0'); }
    public function ready(): bool { return $this->api->configured() && $this->sellerId() > 0; }
    public function warehouse(): int
    {
        $w = (int)$this->c('warehouse_id', '0');
        if ($w && $this->db->val('SELECT 1 FROM {p}wms_warehouses WHERE id = ? AND active = 1', [$w])) return $w;
        return (int)$this->db->val('SELECT id FROM {p}wms_warehouses WHERE active = 1 ORDER BY is_default DESC, id LIMIT 1');
    }
    public function stockOf(int $pid, int $warehouse): float { return (float)$this->wms['stock']->available($pid, $warehouse); }
    private function base(string $ext = ''): string { return '/sellers/' . $this->sellerId() . '/products' . ($ext === '' ? '' : '/' . rawurlencode($ext)); }

    /* ---------------- products & stock ---------------- */

    /** Create/update the product on Basebase. @return ?string error text, null when fine */
    public function pushProduct(int $pid): ?string
    {
        $map = $this->db->row('SELECT * FROM {p}wmsbb_products WHERE product_id = ?', [$pid]);
        $p = $this->db->row('SELECT * FROM {p}wms_products WHERE id = ?', [$pid]);
        if (!$map || !$p) return 'missing';
        if ($p['archived'] || !$map['published']) return $this->hide($map);
        if ($pr = Bb::problems($p, $map)) return $this->fail($pid, $pr[0]);
        if (!$this->ready()) return $this->fail($pid, 'not_connected');
        $avail = $this->warehouse() ? $this->wms['stock']->available($pid, $this->warehouse()) : 0.0;
        $body = Bb::product($p, $map, $avail);
        $r = $this->api->request('PUT', $this->base($map['external_id']), $body, 10);
        if ($r['error'] !== null) return $this->fail($pid, $r['error']);
        $this->db->q('UPDATE {p}wmsbb_products SET synced_at = NOW(), last_stock = ?, dirty = 0, last_error = NULL WHERE product_id = ?', [$body['stock'], $pid]);
        return null;
    }

    private function hide(array $map): ?string
    {
        if (!$this->ready() || !$map['synced_at']) return null;   // never published: nothing to hide
        $r = $this->api->request('DELETE', $this->base($map['external_id']));
        if ($r['error'] !== null && $r['code'] !== 404) return $this->fail((int)$map['product_id'], $r['error']);
        $this->db->q('UPDATE {p}wmsbb_products SET synced_at = NULL, last_stock = NULL, dirty = 0, last_error = NULL WHERE product_id = ?', [$map['product_id']]);
        return null;
    }

    /** Push only the stock number of a published product. */
    public function pushStock(int $pid): ?string
    {
        $map = $this->db->row('SELECT * FROM {p}wmsbb_products WHERE product_id = ? AND published = 1', [$pid]);
        if (!$map) return null;
        if (!$map['synced_at']) return $this->pushProduct($pid);
        if (!$this->ready()) { $this->db->q('UPDATE {p}wmsbb_products SET dirty = 1 WHERE product_id = ?', [$pid]); return 'not_connected'; }
        $units = Bb::stockUnits($this->warehouse() ? $this->wms['stock']->available($pid, $this->warehouse()) : 0.0);
        if ($map['last_stock'] !== null && (int)$map['last_stock'] === $units && !$map['dirty']) return null;
        $r = $this->api->request('PATCH', $this->base($map['external_id']) . '/stock', ['stock' => $units], 5);
        if ($r['code'] === 404) return $this->pushProduct($pid);
        if ($r['error'] !== null) return $this->fail($pid, $r['error']);
        $this->db->q('UPDATE {p}wmsbb_products SET last_stock = ?, dirty = 0, last_error = NULL WHERE product_id = ?', [$units, $pid]);
        return null;
    }

    /** @return array{ok:int,failed:int} */
    public function pushAll(bool $onlyDirty = false): array
    {
        $ok = $failed = 0;
        $ids = $this->db->rows('SELECT product_id FROM {p}wmsbb_products WHERE published = 1' . ($onlyDirty ? ' AND (dirty = 1 OR synced_at IS NULL)' : '') . ' LIMIT 500');
        foreach ($ids as $r) { $this->pushProduct((int)$r['product_id']) === null ? $ok++ : $failed++; }
        return ['ok' => $ok, 'failed' => $failed];
    }

    private function fail(int $pid, string $e): string
    {
        $this->db->q('UPDATE {p}wmsbb_products SET dirty = 1, last_error = ? WHERE product_id = ?', [mb_substr($e, 0, 250), $pid]);
        return $e;
    }

    /* ---------------- orders in ---------------- */

    /** Poll orders after the last seen item id. @return int number of new items */
    public function pull(): int
    {
        if (!$this->ready()) return 0;
        $new = 0; $since = (int)$this->c('since_id', '0');
        for ($page = 0; $page < 5; $page++) {
            $r = $this->api->request('GET', '/sellers/' . $this->sellerId() . '/orders?since_id=' . $since . '&limit=100', null, 10);
            if ($r['error'] !== null) { ($this->set)('last_error', $r['error']); break; }
            $items = (array)($r['data']['items'] ?? []);
            foreach ($items as $raw) {
                $it = Bb::item((array)$raw); if (!$it['item_id']) continue;
                if (!$this->db->val('SELECT 1 FROM {p}wmsbb_items WHERE item_id = ?', [$it['item_id']])) $new++;
                $this->handleItem($it);
                $since = max($since, $it['item_id']);
            }
            ($this->set)('since_id', (string)$since);
            if (count($items) < 100) break;
        }
        ($this->set)('last_poll', (string)time());
        return $new;
    }

    /** Create (once) a posted customer order for a Basebase order item. */
    public function handleItem(array $it): void
    {
        $cur = $this->db->row('SELECT * FROM {p}wmsbb_items WHERE item_id = ?', [$it['item_id']]);
        if ($cur) {
            if ($cur['status'] !== $it['status']) $this->setStatus($cur, $it['status']);
            if ($cur['doc_id']) return;   // already turned into an order; retry() handles failed ones
        } else {
            $this->db->q('INSERT INTO {p}wmsbb_items (item_id, order_id, external_id, title, qty, price, seller_receives, customer, status) VALUES (?,?,?,?,?,?,?,?,?)',
                [$it['item_id'], $it['order_id'], $it['external_id'] ?: null, mb_substr($it['title'], 0, 250), $it['qty'], $it['price'], $it['seller_receives'], json_encode($it['customer'], JSON_UNESCAPED_UNICODE), mb_substr($it['status'], 0, 20)]);
        }
        $this->makeOrder($it);
    }

    /** Try again to create/post the WMS order of an item that failed earlier (unknown product, not enough stock...). */
    public function retry(int $itemId): ?string
    {
        $row = $this->db->row('SELECT * FROM {p}wmsbb_items WHERE item_id = ?', [$itemId]);
        if (!$row) return 'missing';
        $it = Bb::item(['item_id' => $row['item_id'], 'order_id' => $row['order_id'], 'external_id' => $row['external_id'], 'title' => $row['title'], 'qty' => $row['qty'], 'price' => $row['price'],
            'seller_receives' => $row['seller_receives'], 'status' => $row['status'], 'customer' => json_decode((string)$row['customer'], true) ?: []]);
        $this->makeOrder($it);
        return $this->db->val('SELECT error FROM {p}wmsbb_items WHERE item_id = ?', [$itemId]) ?: null;
    }

    private function makeOrder(array $it): void
    {
        $id = $it['item_id']; $docs = $this->wms['docs'];
        $err = function (string $e) use ($id, $it): void {
            $prev = $this->db->val('SELECT error FROM {p}wmsbb_items WHERE item_id = ?', [$id]);
            $this->db->q('UPDATE {p}wmsbb_items SET error = ? WHERE item_id = ?', [mb_substr($e, 0, 250), $id]);
            if ($prev !== mb_substr($e, 0, 250)) $this->note('order.failed', ['item' => $id, 'order' => $it['order_id'], 'error' => $e]);   // tell once, not on every retry
        };
        $row = $this->db->row('SELECT * FROM {p}wmsbb_items WHERE item_id = ?', [$id]);
        $map = $it['external_id'] !== '' ? $this->db->row('SELECT product_id FROM {p}wmsbb_products WHERE external_id = ?', [$it['external_id']]) : null;
        if (!$map) { $err('err_unknown_product'); return; }
        if (!$this->warehouse()) { $err('err_no_warehouse'); return; }
        $doc = $row['doc_id'] ? $docs->find((int)$row['doc_id']) : null;
        if (!$doc) {
            $docId = $docs->save(['type' => 'sale', 'warehouse_id' => $this->warehouse(), 'contact_id' => $this->contactFor($it['customer']), 'moment' => date('Y-m-d H:i:s'),
                'comment' => Bb::comment($it), 'source' => 'basebase', 'ref' => 'item:' . $id], [['product_id' => (int)$map['product_id'], 'qty' => $it['qty'], 'price' => $it['price']]]);
            $this->db->q('UPDATE {p}wmsbb_items SET doc_id = ?, product_id = ? WHERE item_id = ?', [$docId, $map['product_id'], $id]);
            $doc = $docs->find($docId);
        }
        if ($doc['status'] === 'draft') {
            $problems = $docs->post((int)$doc['id']);
            if ($problems) { $err(implode(' | ', $problems)); return; }
            $this->note('order.received', ['id' => (int)$doc['id'], 'number' => $doc['number'], 'item' => $id, 'order' => $it['order_id']]);
        }
        $this->db->q('UPDATE {p}wmsbb_items SET error = NULL WHERE item_id = ?', [$id]);
        if (($it['status'] ?? 'pending') === 'pending') $this->tell($id, 'prepare');
    }

    private function contactFor(array $c): ?int
    {
        if ($c['name'] === '' && $c['phone'] === '') return null;
        if ($c['phone'] !== '' && ($id = $this->db->val('SELECT id FROM {p}contacts WHERE phone = ? ORDER BY id LIMIT 1', [$c['phone']]))) {
            $this->tagCustomer((int)$id);
            return (int)$id;
        }
        return $this->contacts->create(['type' => 'person', 'name' => mb_substr($c['name'] !== '' ? $c['name'] : $c['phone'], 0, 190), 'phone' => $c['phone'] ?: null, 'address' => $c['address'] ?: null,
            'source' => 'basebase', 'custom' => ['roles' => ['customer']]]);
    }

    private function tagCustomer(int $id): void
    {
        $row = $this->contacts->find($id); if (!$row) return;
        $custom = (array)($row['custom'] ?? []); $roles = (array)($custom['roles'] ?? []);
        if (!in_array('customer', $roles, true)) { $custom['roles'] = [...$roles, 'customer']; $this->contacts->update($id, ['custom' => $custom]); }
    }

    /* ---------------- status ---------------- */

    private function setStatus(array $row, string $status): void
    {
        $this->db->q('UPDATE {p}wmsbb_items SET status = ? WHERE item_id = ?', [mb_substr($status, 0, 20), $row['item_id']]);
        if (Bb::releasesReserve($status) && $row['doc_id']) {
            $docs = $this->wms['docs']; $doc = $docs->find((int)$row['doc_id']);
            if ($doc && $doc['status'] === 'posted' && !$docs->unpost((int)$doc['id'], true)) $this->note('order.cancelled', ['id' => (int)$doc['id'], 'number' => $doc['number']]);
            elseif ($doc && $doc['status'] === 'posted') $this->db->q('UPDATE {p}wmsbb_items SET error = ? WHERE item_id = ?', ['err_has_shipment', $row['item_id']]);
        }
    }

    /** Tell Basebase the item status; failures are kept in the item's error column and retried manually. */
    public function tell(int $itemId, string $status): ?string
    {
        if (!$this->ready()) return 'not_connected';
        $r = $this->api->request('PATCH', '/sellers/' . $this->sellerId() . '/orders/' . $itemId, ['status' => $status], 5);
        if ($r['error'] !== null) { $this->db->q('UPDATE {p}wmsbb_items SET error = ? WHERE item_id = ?', [mb_substr('bb: ' . $r['error'], 0, 250), $itemId]); return $r['error']; }
        $this->db->q('UPDATE {p}wmsbb_items SET status = ? WHERE item_id = ?', [$status, $itemId]);
        return null;
    }

    /* ---------------- webhook ---------------- */

    /** @return bool true when the event is acknowledged (handled or ignorable) */
    public function handleEvent(array $ev): bool
    {
        $uid = mb_substr((string)($ev['event_id'] ?? ''), 0, 80); $name = (string)($ev['event'] ?? ''); $d = (array)($ev['data'] ?? []);
        if ($uid === '' || $name === '') return false;
        if ($this->db->val('SELECT 1 FROM {p}wmsbb_events WHERE event_id = ?', [$uid])) return true;
        if ((int)($d['seller_id'] ?? 0) === $this->sellerId()) {
            if ($name === 'order.created') $this->handleItem(Bb::item($d + ['status' => 'pending']));
            elseif ($name === 'order.status_changed') {
                $row = $this->db->row('SELECT * FROM {p}wmsbb_items WHERE item_id = ?', [(int)($d['item_id'] ?? 0)]);
                if ($row) $this->setStatus($row, (string)($d['status'] ?? $row['status']));
            }
            else $this->note('bb.' . $name, ['seller' => (int)$d['seller_id']]);   // messages / wallet: logged only in v1
        }
        $this->db->q('INSERT IGNORE INTO {p}wmsbb_events (event_id, event) VALUES (?,?)', [$uid, mb_substr($name, 0, 60)]);
        return true;
    }

    /* ---------------- reaction to WMS documents ---------------- */

    public function onDocChanged(int $docId, string $action): void
    {
        $docs = $this->wms['docs']; $doc = $docs->find($docId); if (!$doc) return;
        foreach ($docs->lines($docId) as $l) $this->pushStock((int)$l['product_id']);
        // a shipment of a Basebase order was posted → tell the buyer's marketplace
        if ($doc['type'] === 'shipment' && $action === 'post' && $doc['parent_id']) {
            $it = $this->db->row('SELECT item_id, status FROM {p}wmsbb_items WHERE doc_id = ?', [$doc['parent_id']]);
            if ($it && !in_array($it['status'], ['shipped', 'on_delivery', 'delivered'], true)) {
                $pr = $docs->progress((int)$doc['parent_id']);
                if ($pr['done'] + 0.0000001 >= $pr['ordered']) $this->tell((int)$it['item_id'], 'shipped');
            }
        }
    }

    /* ---------------- connection ---------------- */

    public function ping(): array { return $this->api->request('GET', '/ping'); }

    public function refreshCategories(): array
    {
        $r = $this->api->request('GET', '/categories');
        if ($r['error'] !== null) return [];
        $tree = isset($r['data'][0]) ? $r['data'] : (array)($r['data']['categories'] ?? $r['data']['items'] ?? []);
        $names = Bb::categoryNames($tree);
        if ($names) ($this->set)('categories', json_encode($names, JSON_UNESCAPED_UNICODE));
        return $names;
    }
}
