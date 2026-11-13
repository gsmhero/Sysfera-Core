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

    /** The Sysfera user this Sync works for (0 = the whole site: cron, webhook, hooks that find the owner themselves). */
    private int $owner = 0;
    private const OWN = ['seller_id', 'warehouse_ids', 'since_id', 'last_poll'];

    public function forOwner(int $owner): self { $c = clone $this; $c->owner = max(0, $owner); return $c; }
    public function owner(): int { return $this->owner; }
    private function productOwner(int $pid): int { return (int)$this->db->val('SELECT owner_id FROM {p}wms_products WHERE id = ?', [$pid]); }
    public function forProduct(int $pid): self { return $this->forOwner($this->productOwner($pid)); }

    private function c(string $k, string $d = ''): string
    {
        if ($this->owner > 0 && in_array($k, self::OWN, true)) { $v = $this->seller()[$k] ?? ''; return (string)$v !== '' ? (string)$v : $d; }
        return ($this->cfg)($k, $d);
    }
    private function put(string $k, string $v): void
    {
        if ($this->owner > 0 && in_array($k, self::OWN, true)) {
            $this->seller();   // makes sure the row exists
            $this->db->q("UPDATE {p}wmsbb_sellers SET $k = ? WHERE owner_id = ?", [$v, $this->owner]);
            return;
        }
        ($this->set)($k, $v);
    }
    private function note(string $type, array $data): void { if ($this->log) ($this->log)($type, $data); }

    /** The seller row of this owner. Settings of the time before every user had a shop (one global seller) move to the user who owned the connection. */
    public function seller(): array
    {
        if ($this->owner <= 0) return [];
        $r = $this->db->row('SELECT * FROM {p}wmsbb_sellers WHERE owner_id = ?', [$this->owner]);
        if ($r) return $r;
        $legacy = (int)($this->cfg)('seller_id', '0'); $lo = (int)($this->cfg)('owner_id', '0');
        if ($legacy > 0 && !$this->db->val('SELECT 1 FROM {p}wmsbb_sellers WHERE seller_id = ?', [$legacy])
            && ($lo === $this->owner || ($lo === 0 && $this->owner === (int)$this->db->val("SELECT MIN(id) FROM {p}users WHERE role = 'admin'")))) {
            $this->db->q('INSERT INTO {p}wmsbb_sellers (owner_id, seller_id, warehouse_ids, since_id, last_poll) VALUES (?,?,?,?,?)',
                [$this->owner, $legacy, ($this->cfg)('warehouse_ids', '[]'), (int)($this->cfg)('since_id', '0'), (int)($this->cfg)('last_poll', '0')]);
            $this->db->q('UPDATE {p}wmsbb_items SET owner_id = ? WHERE owner_id IS NULL', [$this->owner]);
            $this->db->q('UPDATE {p}wms_products SET owner_id = ? WHERE id IN (SELECT product_id FROM {p}wmsbb_products)', [$this->owner]);
            $this->db->q("UPDATE {p}wms_docs SET owner_id = ? WHERE source = 'basebase'", [$this->owner]);
        } else $this->db->q('INSERT IGNORE INTO {p}wmsbb_sellers (owner_id) VALUES (?)', [$this->owner]);
        return $this->db->row('SELECT * FROM {p}wmsbb_sellers WHERE owner_id = ?', [$this->owner]) ?: [];
    }
    public function sellerId(): int { return (int)$this->c('seller_id', '0'); }
    public function ready(): bool { return $this->api->configured() && ($this->owner === 0 || $this->sellerId() > 0); }

    /** Make sure this user has a shop on Basebase (through the Agent account). @return ?string error, null when the shop exists */
    public function ensureSeller(): ?string
    {
        if ($this->owner <= 0) return 'no_owner';
        if ($this->sellerId() > 0) return null;
        if (!$this->api->configured()) return 'not_connected';
        $u = $this->db->row('SELECT name, email FROM {p}users WHERE id = ?', [$this->owner]);
        if (!$u || !filter_var((string)$u['email'], FILTER_VALIDATE_EMAIL)) return 'no_email';
        $shop = trim((string)($this->seller()['shop_name'] ?? '')) ?: (string)$u['name'];
        $r = $this->api->request('POST', '/sellers', ['email' => $u['email'], 'name' => mb_substr((string)$u['name'], 0, 120), 'shop_name' => mb_substr($shop, 0, 190), 'external_id' => 'sfu-' . $this->owner], 10);
        if ($r['error'] !== null || empty($r['data']['seller_id'])) return $r['error'] ?? 'unknown';
        $this->put('seller_id', (string)(int)$r['data']['seller_id']);
        return null;
    }
    public function warehouse(): int
    {
        $w = (int)$this->c('warehouse_id', '0');
        if ($w && $this->db->val('SELECT 1 FROM {p}wms_warehouses WHERE id = ? AND active = 1', [$w])) return $w;
        return (int)$this->db->val('SELECT id FROM {p}wms_warehouses WHERE active = 1 ORDER BY is_default DESC, id LIMIT 1');
    }
    /** Warehouses whose stock is sold on Basebase (setting warehouse_ids, JSON list; falls back to the old single warehouse_id, then to the default warehouse). */
    public function warehouses(): array
    {
        $ids = array_map('intval', (array)(json_decode($this->c('warehouse_ids', '[]'), true) ?: []));
        if (!$ids && (int)$this->c('warehouse_id', '0')) $ids = [(int)$this->c('warehouse_id', '0')];
        $out = [];
        foreach (array_unique($ids) as $w) if ($w > 0 && $this->db->val('SELECT 1 FROM {p}wms_warehouses WHERE id = ? AND active = 1', [$w])) $out[] = $w;
        if (!$out && ($d = (int)$this->db->val('SELECT id FROM {p}wms_warehouses WHERE active = 1 ORDER BY is_default DESC, id LIMIT 1'))) $out[] = $d;
        return $out;
    }
    private function type(int $pid): string { return (string)($this->db->val('SELECT ptype FROM {p}wms_products WHERE id = ?', [$pid]) ?: 'simple'); }
    /** Components of a variable / grouped / bundle product, with what is available of each. */
    public function parts(int $pid): array
    {
        $rows = $this->db->rows('SELECT l.qty, l.label, l.price AS own_price, c.id, c.name, c.sell_price, c.archived FROM {p}wms_product_links l JOIN {p}wms_products c ON c.id = l.child_id WHERE l.parent_id = ? ORDER BY l.sort, l.id', [$pid]);
        $out = []; foreach ($rows as $r) { if ($r['archived']) continue; $r['avail'] = $this->available((int)$r['id']); $out[] = $r; }
        return $out;
    }
    /** What can be promised to buyers in one warehouse (a bundle: how many sets can be made from the parts there). */
    public function availIn(int $pid, int $w): float
    {
        switch ($this->type($pid)) {
            case 'bundle':
                $m = INF;
                foreach ($this->db->rows('SELECT child_id, qty FROM {p}wms_product_links WHERE parent_id = ?', [$pid]) as $r) $m = min($m, (float)$this->wms['stock']->available((int)$r['child_id'], $w) / max(0.000001, (float)$r['qty']));
                return $m === INF ? 0.0 : max(0.0, $m);
            case 'simple': return (float)$this->wms['stock']->available($pid, $w);
            default: return 0.0;
        }
    }
    /** Available quantity of a product over all selected warehouses (variable: all its variations; virtual: always plenty). */
    public function available(int $pid): float
    {
        $t = $this->type($pid);
        if ($t === 'virtual' || $t === 'downloadable') return 999.0;
        if ($t === 'variable' || $t === 'grouped') { $s = 0.0; foreach ($this->parts($pid) as $x) $s += (float)$x['avail']; return $s; }
        $sum = 0.0; foreach ($this->warehouses() as $w) $sum += $this->availIn($pid, $w);
        return $sum;
    }
    /** Warehouse an incoming order is taken from: the first selected one that has enough, else the one with the most. */
    public function warehouseFor(int $pid, float $qty): int
    {
        $best = 0; $bestQty = -1.0;
        foreach ($this->warehouses() as $w) {
            $a = $this->availIn($pid, $w);
            if ($a + 0.0000001 >= $qty) return $w;
            if ($a > $bestQty) { $best = $w; $bestQty = $a; }
        }
        return $best;
    }
    public function stockOf(int $pid, int $warehouse): float { return $this->availIn($pid, $warehouse); }
    private function base(string $ext = ''): string { return '/sellers/' . $this->sellerId() . '/products' . ($ext === '' ? '' : '/' . rawurlencode($ext)); }

    /* ---------------- products & stock ---------------- */

    /** Create/update the product on Basebase. @return ?string error text, null when fine */
    public function pushProduct(int $pid): ?string
    {
        $map = $this->db->row('SELECT * FROM {p}wmsbb_products WHERE product_id = ?', [$pid]);
        $p = $this->db->row('SELECT * FROM {p}wms_products WHERE id = ?', [$pid]);
        if (!$map || !$p) return 'missing';
        if ((int)$p['owner_id'] !== $this->owner) return $this->forOwner((int)$p['owner_id'])->pushProduct($pid);   // always on behalf of the product's owner
        if ($this->owner > 0 && $this->sellerId() === 0 && !$p['archived'] && $map['published'] && ($e = $this->ensureSeller()) !== null) return $this->fail($pid, $e);
        if ($p['archived'] || !$map['published']) return $this->hide($map);
        $parts = in_array($p['ptype'], ['variable', 'bundle'], true) ? $this->parts($pid) : [];
        if ($pr = Bb::problems($p, $map, $parts)) return $this->fail($pid, $pr[0]);
        if (!$this->ready()) return $this->fail($pid, 'not_connected');
        $avail = $this->available($pid);
        $body = Bb::product($p, $map, $avail, $parts);
        $r = $this->api->request('PUT', $this->base($map['external_id']), $body, empty($body['images']) ? 10 : 60);   // Basebase downloads the photos while answering
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
        if (($o = $this->productOwner($pid)) !== $this->owner) return $this->forOwner($o)->pushStock($pid);
        if (!$map['synced_at']) return $this->pushProduct($pid);
        if ($this->type($pid) === 'variable') return $this->pushProduct($pid);   // variations carry their own stock: only a full push is correct
        if (!$this->ready()) { $this->db->q('UPDATE {p}wmsbb_products SET dirty = 1 WHERE product_id = ?', [$pid]); return 'not_connected'; }
        $units = Bb::stockUnits($this->available($pid));
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
        $ids = $this->db->rows('SELECT m.product_id FROM {p}wmsbb_products m JOIN {p}wms_products p ON p.id = m.product_id WHERE m.published = 1' . ($this->owner > 0 ? ' AND p.owner_id = ' . $this->owner : '') . ($onlyDirty ? ' AND (m.dirty = 1 OR m.synced_at IS NULL)' : '') . ' LIMIT 500');
        $ids = array_map(fn($r) => ['product_id' => $r['product_id']], $ids);
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
        if ($this->owner === 0) {   // the whole site: every user that has a shop
            $n = 0; foreach ($this->db->rows('SELECT owner_id FROM {p}wmsbb_sellers WHERE seller_id > 0') as $r) $n += $this->forOwner((int)$r['owner_id'])->pull();
            return $n;
        }
        if ($this->sellerId() === 0) return 0;
        $new = 0; $since = (int)$this->c('since_id', '0');
        for ($page = 0; $page < 5; $page++) {
            $r = $this->api->request('GET', '/sellers/' . $this->sellerId() . '/orders?since_id=' . $since . '&limit=100', null, 10);
            if ($r['error'] !== null) { ($this->set)('last_error', $r['error']); break; }   // site-wide note
            $items = (array)($r['data']['items'] ?? []);
            foreach ($items as $raw) {
                $it = Bb::item((array)$raw); if (!$it['item_id']) continue;
                if (!$this->db->val('SELECT 1 FROM {p}wmsbb_items WHERE item_id = ?', [$it['item_id']])) $new++;
                $this->handleItem($it);
                $since = max($since, $it['item_id']);
            }
            $this->put('since_id', (string)$since);
            if (count($items) < 100) break;
        }
        $this->put('last_poll', (string)time());
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
            $this->db->q('INSERT INTO {p}wmsbb_items (owner_id, item_id, order_id, external_id, title, variant, qty, price, seller_receives, customer, status) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                [$this->owner ?: null, $it['item_id'], $it['order_id'], $it['external_id'] ?: null, mb_substr($it['title'], 0, 250), mb_substr($it['variant'], 0, 120) ?: null, $it['qty'], $it['price'], $it['seller_receives'], json_encode($it['customer'], JSON_UNESCAPED_UNICODE), mb_substr($it['status'], 0, 20)]);
        }
        $this->makeOrder($it);
    }

    /** Try again to create/post the WMS order of an item that failed earlier (unknown product, not enough stock...). */
    public function retry(int $itemId): ?string
    {
        $row = $this->db->row('SELECT * FROM {p}wmsbb_items WHERE item_id = ?', [$itemId]);
        if (!$row) return 'missing';
        if ($this->owner > 0 && (int)$row['owner_id'] !== $this->owner) return 'missing';
        if ($this->owner === 0 && (int)$row['owner_id'] > 0) return $this->forOwner((int)$row['owner_id'])->retry($itemId);
        $it = Bb::item(['item_id' => $row['item_id'], 'order_id' => $row['order_id'], 'external_id' => $row['external_id'], 'title' => $row['title'], 'variant' => (string)($row['variant'] ?? ''), 'qty' => $row['qty'], 'price' => $row['price'],
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
        $map = $it['external_id'] !== '' ? $this->db->row('SELECT m.product_id FROM {p}wmsbb_products m JOIN {p}wms_products p ON p.id = m.product_id WHERE m.external_id = ?' . ($this->owner > 0 ? ' AND p.owner_id = ' . $this->owner : ''), [$it['external_id']]) : null;
        if (!$map) { $err('err_unknown_product'); return; }
        if (!$this->warehouse()) { $err('err_no_warehouse'); return; }
        if ($this->type((int)$map['product_id']) === 'variable') {   // the buyer chose a variation: it is a product of its own in the warehouse
            $vid = $this->variationFor((int)$map['product_id'], $it['variant']);
            if (!$vid) { $err('err_variant'); return; }
            $map['product_id'] = $vid;
        }
        $doc = $row['doc_id'] ? $docs->find((int)$row['doc_id']) : null;
        if (!$doc) {
            $docId = $docs->save(['type' => 'sale', 'warehouse_id' => $this->warehouseFor((int)$map['product_id'], (float)$it['qty']), 'contact_id' => $this->contactFor($it['customer']), 'moment' => date('Y-m-d H:i:s'),
                'comment' => Bb::comment($it), 'source' => 'basebase', 'owner_id' => $this->owner, 'ref' => 'item:' . $id], [['product_id' => (int)$map['product_id'], 'qty' => $it['qty'], 'price' => $it['price']]]);
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

    /** The warehouse product behind the variation name the buyer chose (the variation name, else the product name; a single variation needs no name). */
    private function variationFor(int $parent, string $name): int
    {
        $rows = $this->parts($parent); $name = mb_strtolower(trim($name));
        foreach ($rows as $x) if ($name !== '' && (mb_strtolower(trim((string)$x['label'])) === $name || mb_strtolower(trim((string)$x['name'])) === $name)) return (int)$x['id'];
        return ($name === '' && count($rows) === 1) ? (int)$rows[0]['id'] : 0;
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
        $o = (int)$this->db->val('SELECT owner_id FROM {p}wmsbb_items WHERE item_id = ?', [$itemId]);
        if ($o !== $this->owner) return $o > 0 && $this->owner === 0 ? $this->forOwner($o)->tell($itemId, $status) : 'missing';
        if (!$this->ready() || $this->sellerId() === 0) return 'not_connected';
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
        $o = (int)($d['seller_id'] ?? 0) > 0 ? (int)$this->db->val('SELECT owner_id FROM {p}wmsbb_sellers WHERE seller_id = ?', [(int)$d['seller_id']]) : 0;
        if ($o > 0) {
            if ($name === 'order.created') $this->forOwner($o)->handleItem(Bb::item($d + ['status' => 'pending']));
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
        $todo = [];
        foreach ($docs->lines($docId) as $l) foreach ($this->affected((int)$l['product_id']) as $x) $todo[$x] = 1;
        foreach (array_keys($todo) as $x) $this->pushStock((int)$x);
        // a shipment of a Basebase order was posted → tell the buyer's marketplace
        if ($doc['type'] === 'shipment' && $action === 'post' && $doc['parent_id']) {
            $it = $this->db->row('SELECT item_id, status FROM {p}wmsbb_items WHERE doc_id = ?', [$doc['parent_id']]);
            if ($it && !in_array($it['status'], ['shipped', 'on_delivery', 'delivered'], true)) {
                $pr = $docs->progress((int)$doc['parent_id']);
                if ($pr['done'] + 0.0000001 >= $pr['ordered']) $this->tell((int)$it['item_id'], 'shipped');
            }
        }
    }

    /** A product and everything whose stock depends on it: its parts (when it is a bundle) and the variable products / bundles it belongs to. @return int[] */
    private function affected(int $pid): array
    {
        $ids = [$pid];
        foreach ($this->db->rows('SELECT child_id FROM {p}wms_product_links WHERE parent_id = ?', [$pid]) as $r) $ids[] = (int)$r['child_id'];
        foreach ($ids as $i) foreach ($this->db->rows('SELECT parent_id FROM {p}wms_product_links WHERE child_id = ?', [$i]) as $r) $ids[] = (int)$r['parent_id'];
        return array_values(array_unique($ids));
    }

    /* ---------------- wallet & payout profile (live from Basebase, nothing sensitive is kept here) ---------------- */

    private function sellerCall(string $method, string $path, ?array $body = null): array
    {
        if ($this->owner <= 0 || !$this->api->configured()) return ['code' => 0, 'data' => [], 'error' => 'not_connected'];
        if ($this->sellerId() === 0) return ['code' => 0, 'data' => [], 'error' => 'no_shop'];
        return $this->api->request($method, '/sellers/' . $this->sellerId() . $path, $body, 10);
    }
    /** @return array{error:?string,data:array} balance + latest transactions */
    public function wallet(): array { return $this->sellerCall('GET', '/wallet'); }
    /** Ask Basebase to pay out; its answer {status: sent|review, message, balance} or an error such as profile_incomplete / too_small / insufficient. */
    public function withdraw(float $amount): array
    {
        if ($amount <= 0) return ['code' => 0, 'data' => [], 'error' => 'bad_amount'];
        $r = $this->sellerCall('POST', '/wallet/withdraw', ['amount' => round($amount, 2)]);
        $this->note('wallet.withdraw', ['amount' => round($amount, 2), 'result' => $r['error'] ?? (string)($r['data']['status'] ?? '')]);
        return $r;
    }
    public function profile(): array { return $this->sellerCall('GET', '/profile'); }
    public function saveProfile(string $type, array $fields): array
    {
        $clean = []; foreach ($fields as $id => $v) if (preg_match('/^\d{1,6}$/', (string)$id)) $clean[(string)$id] = mb_substr(trim((string)$v), 0, 190);
        return $this->sellerCall('PUT', '/profile', ['payout_type' => $type, 'fields' => $clean]);
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
