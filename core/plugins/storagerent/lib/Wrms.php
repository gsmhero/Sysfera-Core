<?php
declare(strict_types=1);
namespace SysferaStoragerent;

/**
 * Warehouse structure, tenants, contracts, documents, on-site access log and tenant requests of the WRMS tab.
 * Runs against any Store (database in production, in memory in the tests); time is injected. Every public method that changes data takes the acting Ctx.
 */
final class Wrms
{
    public const UNIT_TYPES = ['dry', 'cold', 'yard', 'office'];
    public const FEATURES = ['racking', 'power', 'climate', 'heating', 'camera', 'fire'];
    public const MANUAL_STATUS = ['', 'reserved', 'maintenance'];
    public const TENANT_KINDS = ['legal', 'sole'];
    public const TENANT_STATUS = ['active', 'suspended', 'archived'];
    public const REQUEST_STATUS = ['new', 'in_progress', 'done'];
    public const DOC_OWNERS = ['tenant', 'contract', 'invoice', 'request', 'reading', 'meter'];
    public const DOC_EXT = ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'txt', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'odt', 'ods'];
    public const DOC_MAX = 8 * 1024 * 1024;

    /** @param callable():int $now unix seconds  @param callable():string $today YYYY-MM-DD */
    public function __construct(private Store $st, private $now, private $today) {}

    public static function clean(mixed $v, int $max): string
    {
        $s = trim(str_replace("\0", '', (string)$v));
        return function_exists('mb_substr') ? mb_substr($s, 0, $max) : substr($s, 0, $max);
    }
    private static function num(mixed $v, float $min = 0, float $max = 1e9): float
    {
        $s = str_replace([',', ' '], ['.', ''], trim((string)$v)); if ($s === '') return 0.0;
        if (!is_numeric($s)) throw new Err('number_invalid');
        $f = (float)$s; if ($f < $min || $f > $max) throw new Err('number_invalid'); return $f;
    }
    private function money(mixed $v): int
    {
        $k = Billing::kop($v); if ($k === null && trim((string)$v) !== '') throw new Err('amount_invalid');
        $k ??= 0; if ($k < 0) throw new Err('amount_invalid'); return $k;
    }
    public function audit(int $uid, string $entity, int $id, string $action, array $diff = []): void
    {
        $this->st->insert('audit', ['user_id' => $uid, 'entity' => $entity, 'entity_id' => $id, 'action' => $action, 'diff' => $diff ? json_encode($diff, JSON_UNESCAPED_UNICODE) : null, 'created_at' => ($this->now)()]);
    }
    /** Changed fields only: [field => [old, new]]. */
    public static function diff(array $old, array $new, array $fields): array
    {
        $d = []; foreach ($fields as $f) if ((string)($old[$f] ?? '') !== (string)($new[$f] ?? '')) $d[$f] = [$old[$f] ?? '', $new[$f] ?? ''];
        return $d;
    }

    /* ================================================================== warehouses and units */
    public function saveWarehouse(Ctx $c, int $id, array $in): array
    {
        $c->require('write');
        $name = self::clean($in['name'] ?? '', 150); if ($name === '') throw new Err('name_required');
        [$lat, $lon] = self::latlon($in['lat'] ?? '', $in['lon'] ?? '');
        $row = ['name' => $name, 'city' => self::clean($in['city'] ?? '', 100), 'address' => self::clean($in['address'] ?? '', 255), 'lat' => $lat, 'lon' => $lon, 'note' => self::clean($in['note'] ?? '', 2000)];
        if ($id > 0) { $old = $this->st->one('warehouses', ['id' => $id]) ?? throw new Err('not_found', '', 404); $this->st->update('warehouses', $id, $row); $this->audit($c->uid, 'warehouse', $id, 'update', self::diff($old, $row, array_keys($row))); }
        else { $id = $this->st->insert('warehouses', $row + ['owner_id' => $c->uid, 'created_at' => ($this->now)()]); $this->audit($c->uid, 'warehouse', $id, 'create'); }
        return $this->st->one('warehouses', ['id' => $id]);
    }
    /** @return array{0:?string,1:?string} */
    public static function latlon(mixed $lat, mixed $lon): array
    {
        $a = trim((string)$lat); $b = trim((string)$lon); if ($a === '' && $b === '') return [null, null];
        $a = str_replace(',', '.', $a); $b = str_replace(',', '.', $b);
        if (!is_numeric($a) || !is_numeric($b) || abs((float)$a) > 90 || abs((float)$b) > 180) throw new Err('coords_invalid');
        return [number_format((float)$a, 6, '.', ''), number_format((float)$b, 6, '.', '')];
    }
    public function warehouses(): array { return $this->st->find('warehouses', [], 'name'); }

    public function saveUnit(Ctx $c, int $id, array $in): array
    {
        $c->require('write');
        $name = self::clean($in['name'] ?? '', 100); if ($name === '') throw new Err('name_required');
        $wh = (int)($in['warehouse_id'] ?? 0); if (!$this->st->one('warehouses', ['id' => $wh])) throw new Err('warehouse_required');
        $type = (string)($in['type'] ?? 'dry'); if (!in_array($type, self::UNIT_TYPES, true)) throw new Err('type_invalid');
        $ms = (string)($in['status_manual'] ?? ''); if (!in_array($ms, self::MANUAL_STATUS, true)) throw new Err('status_invalid');
        $feat = array_values(array_intersect(self::FEATURES, (array)($in['features'] ?? [])));
        $row = ['warehouse_id' => $wh, 'name' => $name, 'type' => $type, 'area' => self::num($in['area'] ?? 0, 0, 1e6), 'volume' => self::num($in['volume'] ?? 0, 0, 1e7), 'pallets' => (int)self::num($in['pallets'] ?? 0, 0, 100000),
            'load_t' => self::num($in['load_t'] ?? 0, 0, 1000), 'height' => self::num($in['height'] ?? 0, 0, 100), 'dock' => !empty($in['dock']) ? 1 : 0, 'features' => implode(',', $feat), 'status_manual' => $ms,
            'plan_x' => (int)self::num($in['plan_x'] ?? 0, 0, 1000), 'plan_y' => (int)self::num($in['plan_y'] ?? 0, 0, 1000), 'plan_w' => max(1, (int)self::num($in['plan_w'] ?? 10, 0, 1000)), 'plan_h' => max(1, (int)self::num($in['plan_h'] ?? 6, 0, 1000)),
            'note' => self::clean($in['note'] ?? '', 2000)];
        if ($row['area'] <= 0 && $row['volume'] <= 0 && $row['pallets'] <= 0) throw new Err('size_required');
        if ($id > 0) { $old = $this->st->one('units', ['id' => $id]) ?? throw new Err('not_found', '', 404); $this->st->update('units', $id, $row); $this->audit($c->uid, 'unit', $id, 'update', self::diff($old, $row, array_keys($row))); }
        else { $id = $this->st->insert('units', $row + ['created_at' => ($this->now)()]); $this->audit($c->uid, 'unit', $id, 'create'); }
        return $this->st->one('units', ['id' => $id]);
    }
    public function deleteUnit(Ctx $c, int $id): void
    {
        $c->require('write');
        if ($this->st->one('contract_units', ['unit_id' => $id])) throw new Err('unit_in_use');
        $this->st->delete('units', $id); $this->audit($c->uid, 'unit', $id, 'delete');
    }
    /** Percent of the unit let on $date: [active share, draft (reserved) share]. Termination and end dates count. */
    public function rentedShare(int $unitId, string $date): array
    {
        $act = 0.0; $res = 0.0;
        foreach ($this->st->find('contract_units', ['unit_id' => $unitId]) as $l) {
            $k = $this->st->one('contracts', ['id' => (int)$l['contract_id']]); if (!$k || $k['start_date'] > $date || $k['end_date'] < $date) continue;
            if ($k['status'] === 'active') $act += (float)$l['share_pct']; elseif ($k['status'] === 'draft') $res += (float)$l['share_pct'];
        }
        return [$act, $res];
    }
    /** free / reserved / occupied / maintenance (+ the percent let). */
    public static function unitStatus(array $u, float $active, float $draft): string
    {
        if (($u['status_manual'] ?? '') === 'maintenance') return 'maintenance';
        if ($active > 0) return 'occupied';
        return ($u['status_manual'] ?? '') === 'reserved' || $draft > 0 ? 'reserved' : 'free';
    }
    public function units(?int $warehouseId = null): array
    {
        $today = ($this->today)(); $out = [];
        foreach ($this->st->find('units', $warehouseId ? ['warehouse_id' => $warehouseId] : [], 'name') as $u) {
            [$a, $d] = $this->rentedShare((int)$u['id'], $today);
            $out[] = $u + ['rented' => min(100.0, $a), 'status' => self::unitStatus($u, $a, $d), 'feature_list' => $u['features'] === '' ? [] : explode(',', (string)$u['features'])];
        }
        return $out;
    }

    /* ================================================================== tenants */
    public static function tinOk(string $tin, string $kind): bool { return $tin === '' || preg_match($kind === 'sole' ? '/^\d{12}$/' : '/^\d{10}$/', $tin) === 1; }
    public function saveTenant(Ctx $c, int $id, array $in): array
    {
        $c->require('write');
        $name = self::clean($in['name'] ?? '', 190); if ($name === '') throw new Err('name_required');
        $kind = (string)($in['kind'] ?? 'legal'); if (!in_array($kind, self::TENANT_KINDS, true)) throw new Err('kind_invalid');
        $tin = preg_replace('/\D/', '', (string)($in['tin'] ?? '')); if (!self::tinOk($tin, $kind)) throw new Err('tin_invalid');
        $email = self::clean($in['email'] ?? '', 190); if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Err('email_invalid');
        $status = (string)($in['status'] ?? 'active'); if (!in_array($status, self::TENANT_STATUS, true)) throw new Err('status_invalid');
        $vm = (string)($in['vat_mode'] ?? 'none'); if (!in_array($vm, Billing::VAT_MODES, true)) throw new Err('vat_invalid');
        $vr = self::num($in['vat_rate'] ?? 20, 0, 30);
        $uid = (int)($in['user_id'] ?? 0);
        if ($uid > 0) { $o = $this->st->one('tenants', ['user_id' => $uid]); if ($o && (int)$o['id'] !== $id) throw new Err('user_taken'); }
        $row = ['kind' => $kind, 'name' => $name, 'tin' => $tin, 'contact' => self::clean($in['contact'] ?? '', 150), 'phone' => self::clean($in['phone'] ?? '', 40), 'email' => $email,
            'address' => self::clean($in['address'] ?? '', 255), 'restrictions' => self::clean($in['restrictions'] ?? '', 2000), 'status' => $status, 'vat_mode' => $vm, 'vat_rate' => $vr,
            'note' => self::clean($in['note'] ?? '', 2000), 'user_id' => $uid > 0 ? $uid : null, 'updated_at' => ($this->now)()];
        if ($id > 0) {
            $old = $this->st->one('tenants', ['id' => $id]) ?? throw new Err('not_found', '', 404);
            $this->st->update('tenants', $id, $row); $this->audit($c->uid, 'tenant', $id, 'update', self::diff($old, $row, ['kind', 'name', 'tin', 'contact', 'phone', 'email', 'address', 'restrictions', 'status', 'vat_mode', 'vat_rate', 'note', 'user_id']));
        } else { $id = $this->st->insert('tenants', $row + ['created_at' => ($this->now)()]); $this->audit($c->uid, 'tenant', $id, 'create'); }
        return $this->st->one('tenants', ['id' => $id]);
    }
    public function tenant(int $id): ?array { return $this->st->one('tenants', ['id' => $id]); }
    public function tenantOfUser(int $uid): ?array { return $uid > 0 ? $this->st->one('tenants', ['user_id' => $uid]) : null; }
    public function tenants(string $q = '', string $status = ''): array
    {
        $rows = $this->st->find('tenants', $status !== '' ? ['status' => $status] : [], 'name'); $q = trim($q);
        if ($q !== '') $rows = array_values(array_filter($rows, fn($t) => stripos($t['name'] . ' ' . $t['tin'] . ' ' . $t['contact'] . ' ' . $t['email'] . ' ' . $t['phone'], $q) !== false));
        return $rows;
    }
    public function auditLog(string $entity, int $id): array { return $this->st->find('audit', ['entity' => $entity, 'entity_id' => $id], 'id DESC', 200); }

    /* ================================================================== contracts */
    private function lines(int $contractId): array
    {
        return array_map(function ($l) { $u = $this->st->one('units', ['id' => (int)$l['unit_id']]) ?? []; return $l + ['unit' => $u]; }, $this->st->find('contract_units', ['contract_id' => $contractId]));
    }
    /** Rent lines in the shape Billing::monthlyRent wants. */
    public static function rentLines(array $lines): array
    {
        return array_map(fn($l) => ['area' => (float)($l['unit']['area'] ?? 0), 'volume' => (float)($l['unit']['volume'] ?? 0), 'pallets' => (int)($l['unit']['pallets'] ?? 0), 'share' => (float)$l['share_pct']], $lines);
    }
    public function contract(int $id): ?array
    {
        $k = $this->st->one('contracts', ['id' => $id]); if (!$k) return null;
        $t = $this->st->one('tenants', ['id' => (int)$k['tenant_id']]) ?? [];
        $lines = $this->lines($id); $rent = Billing::monthlyRent((string)$k['rate_type'], (int)$k['rate'], self::rentLines($lines));
        return $k + ['state' => Billing::contractState((string)$k['status'], (string)$k['start_date'], (string)$k['end_date'], ($this->today)(), (int)$k['flag_days']), 'lines' => $lines, 'tenant' => $t, 'monthly' => $rent['amount'], 'qty' => $rent['qty']];
    }
    public function contracts(array $where = []): array
    {
        $out = []; foreach ($this->st->find('contracts', $where, 'id DESC') as $k) $out[] = $this->contract((int)$k['id']); return $out;
    }
    private function nextNumber(): string
    {
        $y = gmdate('Y', ($this->now)()); $n = 0;
        foreach ($this->st->find('contracts', [], 'id DESC', 50) as $k) if (preg_match('/^SR-' . $y . '-(\d+)$/', (string)$k['number'], $m)) $n = max($n, (int)$m[1]);
        return sprintf('SR-%s-%04d', $y, $n + 1);
    }
    /** Percent of a unit already promised to other contracts (active or draft, not terminated) in the date range. */
    private function booked(int $unitId, string $from, string $to, int $exceptContract): float
    {
        $sum = 0.0;
        foreach ($this->st->find('contract_units', ['unit_id' => $unitId]) as $l) {
            if ((int)$l['contract_id'] === $exceptContract) continue;
            $k = $this->st->one('contracts', ['id' => (int)$l['contract_id']]);
            if (!$k || !in_array($k['status'], ['active', 'draft'], true) || $k['start_date'] > $to || $k['end_date'] < $from) continue;
            $sum += (float)$l['share_pct'];
        }
        return $sum;
    }
    /** @param array $in contract fields; $in['units'] = list of ['unit_id'=>, 'share'=>] */
    public function saveContract(Ctx $c, int $id, array $in): array
    {
        $c->require('write');
        $old = $id > 0 ? ($this->st->one('contracts', ['id' => $id]) ?? throw new Err('not_found', '', 404)) : null;
        if ($old && $old['status'] === 'terminated') throw new Err('contract_closed');
        $tid = (int)($in['tenant_id'] ?? 0); $t = $this->st->one('tenants', ['id' => $tid]) ?? throw new Err('tenant_required');
        if ($t['status'] === 'archived' && (!$old || (int)$old['tenant_id'] !== $tid)) throw new Err('tenant_archived');
        $start = Billing::parseDate($in['start_date'] ?? '') ?? throw new Err('date_invalid'); $end = Billing::parseDate($in['end_date'] ?? '') ?? throw new Err('date_invalid');
        if ($end < $start) throw new Err('dates_order');
        $rt = (string)($in['rate_type'] ?? 'm2'); if (!in_array($rt, Billing::RATE_TYPES, true)) throw new Err('rate_type_invalid');
        $rate = $this->money($in['rate'] ?? '');
        $bm = (int)($in['billing_months'] ?? 1); if (!in_array($bm, [1, 2, 3, 6, 12], true)) throw new Err('billing_invalid');
        $dd = (int)($in['due_day'] ?? 5); if ($dd < 1 || $dd > 31) throw new Err('due_day_invalid');
        $pt = (string)($in['penalty_type'] ?? 'none'); if (!in_array($pt, Billing::PENALTY_TYPES, true)) throw new Err('penalty_invalid');
        $pv = $pt === 'fixed' ? (float)$this->money($in['penalty_value'] ?? '') : ($pt === 'percent' ? self::num($in['penalty_value'] ?? 0, 0, 100) : 0.0);
        $um = (string)($in['util_method'] ?? 'included'); if (!in_array($um, Billing::UTIL_METHODS, true)) throw new Err('util_invalid');
        $a24 = !empty($in['access_24h']) ? 1 : 0; $af = (string)($in['access_from'] ?? ''); $at = (string)($in['access_to'] ?? '');
        if (!$a24 && (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $af) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $at))) throw new Err('access_invalid');
        $lines = []; $seen = [];
        foreach ((array)($in['units'] ?? []) as $l) {
            $uid = (int)($l['unit_id'] ?? 0); if ($uid <= 0 || isset($seen[$uid])) continue; $seen[$uid] = 1;
            $u = $this->st->one('units', ['id' => $uid]) ?? throw new Err('unit_required');
            $share = self::num($l['share'] ?? 100, 0, 100); if ($share <= 0) throw new Err('share_invalid');
            if ($this->booked($uid, $start, $end, $id) + $share > 100.0001) throw new Err('unit_overbooked', (string)$u['name']);
            $lines[] = ['unit_id' => $uid, 'share_pct' => $share];
        }
        if (!$lines) throw new Err('unit_required');
        $row = ['tenant_id' => $tid, 'start_date' => $start, 'end_date' => $end, 'rate_type' => $rt, 'rate' => $rate, 'billing_months' => $bm, 'due_day' => $dd, 'grace_days' => (int)self::num($in['grace_days'] ?? 0, 0, 365),
            'penalty_type' => $pt, 'penalty_value' => $pv, 'penalty_cap_pct' => (int)self::num($in['penalty_cap_pct'] ?? 0, 0, 1000), 'deposit' => $this->money($in['deposit'] ?? ''), 'util_method' => $um,
            'util_fixed' => $this->money($in['util_fixed'] ?? ''), 'access_24h' => $a24, 'access_from' => $a24 ? '' : $af, 'access_to' => $a24 ? '' : $at, 'notice_days' => (int)self::num($in['notice_days'] ?? 30, 0, 365),
            'flag_days' => (int)self::num($in['flag_days'] ?? 30, 0, 365), 'prorate' => !empty($in['prorate']) ? 1 : 0, 'note' => self::clean($in['note'] ?? '', 2000), 'updated_at' => ($this->now)()];
        return $this->st->atomic(function () use ($c, $id, $old, $row, $lines) {
            if ($id > 0) { $this->st->update('contracts', $id, $row); foreach ($this->st->find('contract_units', ['contract_id' => $id]) as $l) $this->st->delete('contract_units', (int)$l['id']); }
            else $id = $this->st->insert('contracts', $row + ['number' => $this->nextNumber(), 'status' => 'draft', 'terminated_on' => null, 'termination_reason' => '', 'renewed_count' => 0, 'created_by' => $c->uid, 'created_at' => ($this->now)()]);
            foreach ($lines as $l) $this->st->insert('contract_units', $l + ['contract_id' => $id]);
            $this->audit($c->uid, 'contract', $id, $old ? 'update' : 'create', $old ? self::diff($old, $row, array_keys($row)) : []);
            if ($old && $old['status'] === 'active') $this->version($c, $id, 'edited');
            return $this->contract($id);
        });
    }
    /** Stores a snapshot of the contract (terms, units, tenant restriction) as a new version; returns the version number. */
    public function version(Ctx $c, int $id, string $note): int
    {
        $k = $this->contract($id) ?? throw new Err('not_found', '', 404);
        $n = 1; foreach ($this->st->find('contract_versions', ['contract_id' => $id], 'version DESC', 1) as $v) $n = (int)$v['version'] + 1;
        $snap = ['number' => $k['number'], 'tenant' => ['name' => $k['tenant']['name'] ?? '', 'tin' => $k['tenant']['tin'] ?? '', 'restrictions' => $k['tenant']['restrictions'] ?? ''],
            'terms' => array_intersect_key($k, array_flip(['start_date', 'end_date', 'rate_type', 'rate', 'billing_months', 'due_day', 'grace_days', 'penalty_type', 'penalty_value', 'penalty_cap_pct', 'deposit', 'util_method', 'util_fixed', 'access_24h', 'access_from', 'access_to', 'notice_days', 'status'])),
            'units' => array_map(fn($l) => ['unit' => $l['unit']['name'] ?? '', 'share' => (float)$l['share_pct'], 'area' => (float)($l['unit']['area'] ?? 0)], $k['lines'])];
        $this->st->insert('contract_versions', ['contract_id' => $id, 'version' => $n, 'note' => self::clean($note, 255), 'snapshot' => json_encode($snap, JSON_UNESCAPED_UNICODE), 'user_id' => $c->uid, 'created_at' => ($this->now)()]);
        return $n;
    }
    public function versions(int $id): array
    {
        return array_map(fn($v) => $v + ['data' => json_decode((string)$v['snapshot'], true) ?: []], $this->st->find('contract_versions', ['contract_id' => $id], 'version DESC'));
    }
    public function activate(Ctx $c, int $id): array
    {
        $c->require('write'); $k = $this->st->one('contracts', ['id' => $id]) ?? throw new Err('not_found', '', 404);
        if ($k['status'] !== 'draft') throw new Err('state_invalid');
        $t = $this->st->one('tenants', ['id' => (int)$k['tenant_id']]); if (!$t || $t['status'] !== 'active') throw new Err('tenant_not_active');
        $this->st->update('contracts', $id, ['status' => 'active', 'updated_at' => ($this->now)()]); $this->audit($c->uid, 'contract', $id, 'activate'); $this->version($c, $id, 'activated');
        return $this->contract($id);
    }
    public function terminate(Ctx $c, int $id, string $date, string $reason): array
    {
        $c->require('write'); $k = $this->st->one('contracts', ['id' => $id]) ?? throw new Err('not_found', '', 404);
        if ($k['status'] !== 'active') throw new Err('state_invalid');
        $d = Billing::parseDate($date) ?? throw new Err('date_invalid'); $reason = self::clean($reason, 255); if ($reason === '') throw new Err('reason_required');
        if ($d < $k['start_date']) throw new Err('dates_order');
        $this->st->update('contracts', $id, ['status' => 'terminated', 'terminated_on' => $d, 'termination_reason' => $reason, 'end_date' => min((string)$k['end_date'], $d), 'updated_at' => ($this->now)()]);
        $this->audit($c->uid, 'contract', $id, 'terminate', ['date' => $d, 'reason' => $reason]); $this->version($c, $id, 'terminated: ' . $reason);
        return $this->contract($id);
    }
    /** One-click extension: new end date (default: the same length again) and optionally a new rate (kopecks text). */
    public function renew(Ctx $c, int $id, ?string $newEnd = null, ?string $newRate = null): array
    {
        $c->require('write'); $k = $this->st->one('contracts', ['id' => $id]) ?? throw new Err('not_found', '', 404);
        if ($k['status'] !== 'active') throw new Err('state_invalid');
        $end = $newEnd !== null && trim($newEnd) !== '' ? (Billing::parseDate($newEnd) ?? throw new Err('date_invalid'))
            : Billing::addDays(Billing::addMonths((string)$k['end_date'], max(1, (int)round(Billing::days((string)$k['start_date'], (string)$k['end_date']) / 30.4))), 0);
        if ($end <= $k['end_date']) throw new Err('dates_order');
        $upd = ['end_date' => $end, 'renewed_count' => (int)$k['renewed_count'] + 1, 'updated_at' => ($this->now)()];
        if ($newRate !== null && trim($newRate) !== '') $upd['rate'] = $this->money($newRate);
        $this->st->update('contracts', $id, $upd);
        // overbooking check for the extended range
        foreach ($this->st->find('contract_units', ['contract_id' => $id]) as $l) if ($this->booked((int)$l['unit_id'], (string)$k['end_date'], $end, $id) + (float)$l['share_pct'] > 100.0001) { $this->st->update('contracts', $id, ['end_date' => $k['end_date'], 'rate' => $k['rate'], 'renewed_count' => $k['renewed_count']]); throw new Err('unit_overbooked'); }
        $this->audit($c->uid, 'contract', $id, 'renew', self::diff($k, $upd, ['end_date', 'rate'])); $this->version($c, $id, 'renewed to ' . $end);
        return $this->contract($id);
    }
    /** Active contracts whose state is "expiring" today (the auto-flag). */
    public function expiring(int $withinDays = 0): array
    {
        $today = ($this->today)(); $out = [];
        foreach ($this->st->find('contracts', ['status' => 'active']) as $k) {
            $left = Billing::days($today, (string)$k['end_date']); if ($left < 0) continue;
            if ($withinDays > 0 ? $left <= $withinDays : $left <= (int)$k['flag_days']) $out[] = $this->contract((int)$k['id']) + ['days_left' => $left];
        }
        usort($out, fn($a, $b) => $a['days_left'] <=> $b['days_left']); return $out;
    }

    /* ================================================================== documents (metadata; the file itself is stored by Files) */
    public function addDocument(Ctx $c, string $ownerType, int $ownerId, ?int $tenantId, string $title, string $fileName, string $stored, string $mime, int $size, string $kind = 'file'): int
    {
        if (!in_array($ownerType, self::DOC_OWNERS, true)) throw new Err('bad_request');
        $title = self::clean($title, 190); if ($title === '') $title = self::clean($fileName, 190);
        return $this->st->insert('documents', ['owner_type' => $ownerType, 'owner_id' => $ownerId, 'tenant_id' => $tenantId, 'kind' => self::clean($kind, 16), 'title' => $title, 'file_name' => self::clean($fileName, 190),
            'file_key' => $stored, 'mime' => self::clean($mime, 80), 'size' => $size, 'uploaded_by' => $c->uid, 'created_at' => ($this->now)()]);
    }
    public function document(int $id): ?array { return $this->st->one('documents', ['id' => $id]); }
    public function documents(string $ownerType, int $ownerId): array { return $this->st->find('documents', ['owner_type' => $ownerType, 'owner_id' => $ownerId], 'id DESC'); }
    public function deleteDocument(Ctx $c, int $id): ?array
    {
        $c->require('write'); $d = $this->st->one('documents', ['id' => $id]); if (!$d) throw new Err('not_found', '', 404);
        $this->st->delete('documents', $id); $this->audit($c->uid, 'document', $id, 'delete', ['title' => $d['title']]); return $d;
    }
    /** Search by title, file name, kind (the text inside files is not indexed). Staff only; the caller limits tenants to their own. */
    public function searchDocuments(string $q, int $tenantId = 0): array
    {
        $rows = $this->st->find('documents', $tenantId > 0 ? ['tenant_id' => $tenantId] : [], 'id DESC', 500); $q = trim($q);
        return $q === '' ? array_slice($rows, 0, 100) : array_values(array_filter($rows, fn($d) => stripos($d['title'] . ' ' . $d['file_name'] . ' ' . $d['kind'], $q) !== false));
    }
    /** Checks a file name and size before it is stored: returns the lower-case extension. */
    public static function checkUpload(string $name, int $size, array $allowed = self::DOC_EXT): string
    {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($ext === '' || !in_array($ext, $allowed, true)) throw new Err('file_type');
        if ($size <= 0) throw new Err('file_empty'); if ($size > self::DOC_MAX) throw new Err('file_big');
        return $ext;
    }

    /* ================================================================== on-site access log */
    /** Manual entry/exit record; flagged when the contract's access schedule does not allow that time. */
    public function logAccess(Ctx $c, int $tenantId, ?int $unitId, string $person, string $dir, string $note, ?int $at = null): array
    {
        $c->require('write'); $t = $this->st->one('tenants', ['id' => $tenantId]) ?? throw new Err('tenant_required');
        if (!in_array($dir, ['in', 'out'], true)) throw new Err('bad_request');
        $at ??= ($this->now)(); $hm = gmdate('H:i', $at); $date = gmdate('Y-m-d', $at); $outside = 0; $has = false;
        foreach ($this->st->find('contracts', ['tenant_id' => $tenantId, 'status' => 'active']) as $k) {
            if ($k['start_date'] > $date || $k['end_date'] < $date) continue; $has = true;
            if ((int)$k['access_24h'] || ($k['access_from'] <= $hm && $hm <= $k['access_to'])) { $outside = 0; break; } $outside = 1;
        }
        if (!$has) $outside = 1;
        $id = $this->st->insert('access_log', ['tenant_id' => $tenantId, 'unit_id' => $unitId ?: null, 'person' => self::clean($person, 150), 'direction' => $dir, 'happened_at' => $at, 'outside_schedule' => $outside, 'note' => self::clean($note, 255), 'user_id' => $c->uid]);
        return $this->st->one('access_log', ['id' => $id]);
    }
    public function accessLog(int $tenantId = 0, int $limit = 200): array { return $this->st->find('access_log', $tenantId > 0 ? ['tenant_id' => $tenantId] : [], 'happened_at DESC', $limit); }

    /* ================================================================== tenant requests (maintenance / storage) */
    public function createRequest(Ctx $c, int $tenantId, string $title, string $body, ?int $unitId = null, ?int $photoDocId = null): int
    {
        if (!$c->seesTenant($tenantId) || !$this->st->one('tenants', ['id' => $tenantId])) throw new Err('forbidden', '', 403);
        $title = self::clean($title, 190); if ($title === '') throw new Err('title_required');
        $n = ($this->now)();
        return $this->st->insert('requests', ['tenant_id' => $tenantId, 'unit_id' => $unitId ?: null, 'title' => $title, 'body' => self::clean($body, 4000), 'status' => 'new', 'photo_doc_id' => $photoDocId, 'created_at' => $n, 'updated_at' => $n]);
    }
    public function setRequestStatus(Ctx $c, int $id, string $status): void
    {
        $c->require('write'); if (!in_array($status, self::REQUEST_STATUS, true)) throw new Err('status_invalid');
        if (!$this->st->one('requests', ['id' => $id])) throw new Err('not_found', '', 404);
        $this->st->update('requests', $id, ['status' => $status, 'updated_at' => ($this->now)()]);
    }
    public function requests(int $tenantId = 0): array { return $this->st->find('requests', $tenantId > 0 ? ['tenant_id' => $tenantId] : [], 'id DESC', 300); }
}
