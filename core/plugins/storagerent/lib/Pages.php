<?php
declare(strict_types=1);
namespace SysferaStoragerent;

/**
 * Builds the data of every page (the views only print it). Each method checks the permission it needs through Ctx and returns ['view' => name, 'title' => lang key, ...data].
 * Pure given the services, so the tests render every page for every role without a database.
 */
final class Pages
{
    public function __construct(private Store $st, private Wrms $wrms, private Ledger $ledger, private Reports $reports, private Notify $notify, private Offers $offers, private $setting, private $photoUrl, private $today) {}

    private function s(string $k, string $d = ''): string { return (string)($this->setting)($k, $d); }
    private function names(string $table, string $col = 'name'): array
    {
        $o = []; foreach ($this->st->find($table, []) as $r) $o[(int)$r['id']] = (string)$r[$col]; return $o;
    }
    /** The notification schedule and language as set in Settings. */
    public function notifySettings(): array
    {
        return ['before' => $this->s('notify_before', '5'), 'after' => $this->s('notify_after', '1,3,7'), 'expiring' => $this->s('notify_expiring', '30,7'), 'lang' => $this->s('notify_lang', 'ru') === 'en' ? 'en' : 'ru'];
    }

    /* ================================================================== WRMS */
    public function dashboard(Ctx $c): array
    {
        $c->require('view'); $d = $this->reports->dashboard(); $ar = $this->reports->arrears(); $new = count(array_filter($this->wrms->requests(), fn($r) => $r['status'] === 'new'));
        return ['view' => 'dashboard', 'title' => 'wrms_dashboard', 'd' => $d, 'buckets' => $ar['buckets'], 'new_requests' => $new, 'finance' => $c->can('finance')];
    }
    public function units(Ctx $c): array
    {
        $c->require('view'); $units = $this->wrms->units(); $tenants = $this->names('tenants'); $who = [];
        foreach ($this->st->find('contract_units', []) as $l) {
            $k = $this->st->one('contracts', ['id' => (int)$l['contract_id']]); if (!$k || !in_array($k['status'], ['active', 'draft'], true) || $k['end_date'] < ($this->today)()) continue;
            $who[(int)$l['unit_id']][] = ['tenant' => $tenants[(int)$k['tenant_id']] ?? '', 'tenant_id' => (int)$k['tenant_id'], 'share' => (float)$l['share_pct'], 'draft' => $k['status'] === 'draft', 'contract_id' => (int)$k['id']];
        }
        $plan = []; foreach ($units as $u) $plan[(int)$u['warehouse_id']][] = $u;
        return ['view' => 'units', 'title' => 'wrms_units', 'warehouses' => $this->wrms->warehouses(), 'units' => $units, 'who' => $who, 'plan' => $plan, 'canWrite' => $c->can('write'),
            'types' => Wrms::UNIT_TYPES, 'features' => Wrms::FEATURES];
    }
    public function tenants(Ctx $c, string $q = '', string $status = ''): array
    {
        $c->require('view'); $rows = [];
        foreach ($this->wrms->tenants($q, $status) as $t) $rows[] = $t + ['debt' => $c->can('finance') ? $this->ledger->balance((int)$t['id'])['debt'] : 0];
        return ['view' => 'tenants', 'title' => 'wrms_tenants', 'rows' => $rows, 'q' => $q, 'status' => $status, 'canWrite' => $c->can('write'), 'finance' => $c->can('finance'), 'statuses' => Wrms::TENANT_STATUS];
    }
    public function tenant(Ctx $c, int $id): array
    {
        $c->require('view'); $t = $id > 0 ? ($this->wrms->tenant($id) ?? throw new Err('not_found', '', 404)) : ['id' => 0, 'kind' => 'legal', 'name' => '', 'tin' => '', 'contact' => '', 'phone' => '', 'email' => '', 'address' => '', 'restrictions' => '', 'status' => 'active', 'vat_mode' => 'none', 'vat_rate' => 20, 'note' => '', 'user_id' => null];
        return ['view' => 'tenant', 'title' => 'wrms_tenants', 't' => $t, 'contracts' => $id ? $this->wrms->contracts(['tenant_id' => $id]) : [], 'balance' => $id && $c->can('finance') ? $this->ledger->balance($id) : null,
            'docs' => $id ? $this->wrms->documents('tenant', $id) : [], 'audit' => $id && $c->can('audit') ? $this->wrms->auditLog('tenant', $id) : [], 'canWrite' => $c->can('write'), 'finance' => $c->can('finance'),
            'requests' => $id ? $this->wrms->requests($id) : [], 'access' => $id ? $this->wrms->accessLog($id, 20) : [], 'kinds' => Wrms::TENANT_KINDS, 'statuses' => Wrms::TENANT_STATUS, 'vatModes' => Billing::VAT_MODES];
    }
    public function contracts(Ctx $c, string $state = ''): array
    {
        $c->require('view'); $rows = array_values(array_filter($this->wrms->contracts(), fn($k) => $state === '' || $k['state'] === $state));
        return ['view' => 'contracts', 'title' => 'wrms_contracts', 'rows' => $rows, 'state' => $state, 'canWrite' => $c->can('write'), 'finance' => $c->can('finance'), 'states' => ['draft', 'active', 'expiring', 'ended', 'terminated']];
    }
    public function contract(Ctx $c, int $id): array
    {
        $k = $this->wrms->contract($id) ?? throw new Err('not_found', '', 404);
        if (!$c->seesTenant((int)$k['tenant_id'])) throw new Err('forbidden', '', 403);
        $staff = $c->staff();
        return ['view' => 'contract', 'title' => 'wrms_contracts', 'k' => $k, 'versions' => $staff ? $this->wrms->versions($id) : [], 'docs' => $this->wrms->documents('contract', $id),
            'invoices' => $c->can('finance') || $c->role === 'tenant' ? $this->ledger->invoices(['contract_id' => $id]) : [], 'canWrite' => $c->can('write'), 'staff' => $staff, 'today' => ($this->today)(), 'tenantView' => $c->role === 'tenant'];
    }
    public function contractPrint(Ctx $c, int $id): array
    {
        $p = $this->contract($c, $id); $p['view'] = 'contract_print'; $p['bare'] = true; $p['company'] = $this->s('company_name'); $p['company_details'] = $this->s('company_details'); return $p;
    }
    public function contractForm(Ctx $c, int $id, int $tenantId = 0): array
    {
        $c->require('write'); $k = $id > 0 ? ($this->wrms->contract($id) ?? throw new Err('not_found', '', 404)) : null;
        if ($k && $k['status'] === 'terminated') throw new Err('contract_closed');
        $today = ($this->today)();
        $blank = ['id' => 0, 'tenant_id' => $tenantId, 'start_date' => $today, 'end_date' => Billing::addDays(Billing::addMonths($today, 12), -1), 'rate_type' => 'm2', 'rate' => 0, 'billing_months' => 1, 'due_day' => 5, 'grace_days' => 0, 'penalty_type' => 'none', 'penalty_value' => 0,
            'penalty_cap_pct' => 0, 'deposit' => 0, 'util_method' => 'included', 'util_fixed' => 0, 'access_24h' => 1, 'access_from' => '08:00', 'access_to' => '20:00', 'notice_days' => 30, 'flag_days' => 30, 'prorate' => 0, 'note' => '', 'lines' => []];
        return ['view' => 'contract_form', 'title' => 'wrms_contracts', 'k' => $k ?? $blank, 'tenants' => array_values(array_filter($this->wrms->tenants(), fn($t) => $t['status'] !== 'archived' || ($k && (int)$k['tenant_id'] === (int)$t['id']))),
            'units' => $this->wrms->units(), 'tariff' => $c->can('tariff'), 'rateTypes' => Billing::RATE_TYPES, 'penaltyTypes' => Billing::PENALTY_TYPES, 'utilMethods' => Billing::UTIL_METHODS, 'warehouses' => $this->names('warehouses')];
    }
    public function invoices(Ctx $c, string $status = '', int $tenantId = 0, string $q = ''): array
    {
        $c->require('finance'); $rows = $this->ledger->invoices($tenantId ? ['tenant_id' => $tenantId] : []);
        if ($status !== '') $rows = array_values(array_filter($rows, fn($i) => $i['status'] === $status || ($status === 'late' && $i['late'])));
        if ($q !== '') $rows = array_values(array_filter($rows, fn($i) => stripos($i['number'] . ' ' . $i['tenant_name'] . ' ' . $i['contract_number'], $q) !== false));
        $sum = 0; foreach ($rows as $i) $sum += $i['outstanding'];
        return ['view' => 'invoices', 'title' => 'wrms_invoices', 'rows' => $rows, 'status' => $status, 'tenantId' => $tenantId, 'q' => $q, 'sum' => $sum, 'canWrite' => $c->can('write'), 'tenants' => $this->names('tenants'),
            'contracts' => $c->can('write') ? array_values(array_filter($this->wrms->contracts(), fn($k) => $k['status'] !== 'draft')) : [], 'statuses' => ['draft', 'issued', 'partially_paid', 'paid', 'overdue', 'cancelled', 'late']];
    }
    public function invoice(Ctx $c, int $id): array
    {
        $i = $this->ledger->invoice($id) ?? throw new Err('not_found', '', 404); if (!$c->seesTenant((int)$i['tenant_id'])) throw new Err('forbidden', '', 403);
        if ($c->role === 'tenant' && in_array($i['stored_status'], ['draft'], true)) throw new Err('not_found', '', 404);
        return ['view' => 'invoice', 'title' => 'wrms_invoices', 'i' => $i, 'items' => $this->ledger->items($id), 'allocs' => $this->ledger->allocations($id), 'adjs' => $this->ledger->adjustments($id), 'docs' => $this->wrms->documents('invoice', $id),
            'tenant' => $this->wrms->tenant((int)$i['tenant_id']) ?? [], 'contract' => $this->wrms->contract((int)$i['contract_id']), 'canWrite' => $c->can('write'), 'tenantView' => $c->role === 'tenant', 'company' => $this->s('company_name'), 'company_details' => $this->s('company_details'),
            'adjKinds' => Ledger::ADJ_KINDS];
    }
    public function invoicePrint(Ctx $c, int $id): array { $p = $this->invoice($c, $id); $p['view'] = 'invoice_print'; $p['bare'] = true; return $p; }
    public function payments(Ctx $c, int $tenantId = 0): array
    {
        $c->require('finance'); $rows = $this->ledger->payments($tenantId ? ['tenant_id' => $tenantId] : []); $open = [];
        foreach ($rows as $p) if ($p['kind'] === 'payment') $open[(int)$p['tenant_id']] ??= array_values(array_filter($this->ledger->invoices(['tenant_id' => (int)$p['tenant_id']]), fn($i) => !in_array($i['stored_status'], ['draft', 'cancelled'], true)));
        $credit = []; foreach ($this->wrms->tenants() as $t) { $cr = $this->ledger->credit((int)$t['id']); if ($cr > 0) $credit[(int)$t['id']] = $cr; }
        return ['view' => 'payments', 'title' => 'wrms_payments', 'rows' => $rows, 'tenants' => $this->names('tenants'), 'contracts' => array_values(array_filter($this->wrms->contracts(), fn($k) => $k['status'] === 'active')), 'open' => $open, 'credit' => $credit,
            'tenantId' => $tenantId, 'canWrite' => $c->can('write'), 'methods' => Ledger::METHODS, 'today' => ($this->today)()];
    }
    public function utilities(Ctx $c, int $meterId = 0): array
    {
        $c->require('view'); $meters = $this->ledger->meters();
        return ['view' => 'utilities', 'title' => 'wrms_utilities', 'meters' => $meters, 'units' => $this->names('units'), 'readings' => $meterId ? $this->ledger->readings($meterId) : [], 'meterId' => $meterId, 'canWrite' => $c->can('write'), 'tariff' => $c->can('tariff'), 'today' => ($this->today)(),
            'types' => ['electricity', 'water', 'heat', 'gas']];
    }
    public function reports(Ctx $c, string $name = '', string $from = '', string $to = ''): array
    {
        $c->require('finance'); $today = ($this->today)(); $from = Billing::parseDate($from) ?? Billing::addMonths(Billing::monthStart($today), -5); $to = Billing::parseDate($to) ?? Billing::addDays(Billing::addMonths(Billing::monthStart($today), 1), -1);
        $t = in_array($name, Reports::REPORTS, true) ? $this->reports->run($name, $from, $to) : null;
        return ['view' => 'reports', 'title' => 'wrms_reports', 'names' => Reports::REPORTS, 'name' => $t ? $name : '', 'from' => $from, 'to' => $to, 't' => $t, 'canExport' => $c->can('export')];
    }
    public function notifications(Ctx $c): array
    {
        $c->require('view'); $tpl = []; foreach (Notify::EVENTS as $e) foreach (['ru', 'en'] as $l) { $db = $this->st->one('templates', ['event' => $e, 'lang' => $l]); [$sub, $body] = $this->notify->template($e, $l); $tpl[$e][$l] = ['subject' => $sub, 'body' => $body, 'custom' => (bool)$db]; }
        return ['view' => 'notifications', 'title' => 'wrms_notifications', 'tpl' => $tpl, 'events' => Notify::EVENTS, 'placeholders' => Notify::PLACEHOLDERS, 'log' => $this->notify->recent(100), 'tenants' => $this->names('tenants'), 'set' => $this->notifySettings(), 'tariff' => $c->can('tariff')];
    }
    public function access(Ctx $c): array
    {
        $c->require('view'); return ['view' => 'access', 'title' => 'wrms_access', 'rows' => $this->wrms->accessLog(0, 200), 'tenants' => $this->names('tenants'), 'units' => $this->names('units'), 'canWrite' => $c->can('write')];
    }
    public function documents(Ctx $c, string $q = ''): array
    {
        $c->require('view'); return ['view' => 'documents', 'title' => 'wrms_documents', 'rows' => $this->wrms->searchDocuments($q), 'q' => $q, 'tenants' => $this->names('tenants'), 'canWrite' => $c->can('write')];
    }
    public function requests(Ctx $c): array
    {
        $c->require('view'); return ['view' => 'requests', 'title' => 'wrms_requests', 'rows' => $this->wrms->requests(), 'tenants' => $this->names('tenants'), 'units' => $this->names('units'), 'canWrite' => $c->can('write'), 'statuses' => Wrms::REQUEST_STATUS];
    }
    public function portal(Ctx $c): array
    {
        if ($c->role !== 'tenant') throw new Err('forbidden', '', 403); $tid = $c->tenantId; $t = $this->wrms->tenant($tid) ?? throw new Err('not_found', '', 404);
        $inv = array_values(array_filter($this->ledger->invoices(['tenant_id' => $tid]), fn($i) => $i['stored_status'] !== 'draft'));
        return ['view' => 'portal', 'title' => 'portal', 't' => $t, 'contracts' => $this->wrms->contracts(['tenant_id' => $tid]), 'invoices' => $inv, 'payments' => $this->ledger->payments(['tenant_id' => $tid]), 'balance' => $this->ledger->balance($tid),
            'requests' => $this->wrms->requests($tid), 'docs' => $this->wrms->documents('tenant', $tid), 'units' => $this->names('units')];
    }
    public function settings(Ctx $c, bool $platformAdmin = false): array
    {
        $c->require('settings'); return ['view' => 'settings', 'title' => 'settings', 'platformAdmin' => $platformAdmin, 'v' => ['staff' => $this->s('staff'), 'yandex_key' => $this->s('yandex_key'), 'peers' => $this->s('peers'), 'company_name' => $this->s('company_name'), 'company_details' => $this->s('company_details'),
            'notify_lang' => $this->notifySettings()['lang'], 'cron_token' => $this->s('cron_token')] + $this->notifySettings()];
    }

    /* ================================================================== Offers */
    /** Cards of this domain and of the peer domains. $remote: list of cards already fetched (the caller owns the network). */
    public function cards(string $q = '', array $remote = []): array
    {
        $local = $this->offers->cards($this->offers->listings($q), $this->photoUrl);
        if ($q !== '') $remote = array_values(array_filter($remote, fn($c) => stripos($c['name'] . ' ' . $c['city'] . ' ' . $c['street'], $q) !== false));
        return array_merge($local, $remote);
    }
    public function offers(Ctx $c, string $q = '', array $remote = []): array
    {
        $mine = $c->uid > 0 ? $this->offers->cards($this->offers->listings('', $c->uid), $this->photoUrl) : [];
        $statuses = []; if ($c->uid > 0) foreach ($this->offers->listings('', $c->uid) as $l) $statuses[(int)$l['id']] = (string)$l['status'];
        return ['view' => 'offers', 'title' => 'offers', 'cards' => $this->cards($q, $remote), 'mine' => $mine, 'mineStatus' => $statuses, 'q' => $q, 'signedIn' => $c->uid > 0, 'key' => $this->s('yandex_key') !== '', 'staff' => $c->staff(), 'favs' => $this->offers->favoriteRefs($c->uid)];
    }
    public function map(Ctx $c, array $remote = []): array
    {
        return ['view' => 'map', 'title' => 'offers_map', 'cards' => $this->cards('', $remote), 'signedIn' => $c->uid > 0];
    }
    /** The favorites tab of a signed-in user. */
    public function favorites(Ctx $c): array
    {
        if ($c->uid <= 0) throw new Err('forbidden', '', 403);
        $items = $this->offers->favorites($c->uid, $this->photoUrl);
        return ['view' => 'favorites', 'title' => 'offers', 'items' => $items, 'signedIn' => true, 'favs' => array_fill_keys(array_column($items, 'ref'), true), 'key' => false];
    }
    public function offer(Ctx $c, int $id, array $remote = []): array
    {
        $l = $this->offers->get($id); if (!$l || ($l['status'] !== 'published' && $l['user_id'] !== $c->uid && $c->role !== 'admin')) throw new Err('not_found', '', 404);
        return ['view' => 'offer', 'title' => 'offers', 'l' => $l, 'card' => $this->offers->card($l, $this->photoUrl), 'd' => $l['d'], 'owner' => $c->uid > 0 && ((int)$l['user_id'] === $c->uid || $c->role === 'admin'), 'signedIn' => $c->uid > 0, 'key' => $this->s('yandex_key') !== '',
            'favs' => $this->offers->favoriteRefs($c->uid), 'isAuthor' => $c->uid > 0 && (int)$l['user_id'] === $c->uid, 'hasContacts' => Offers::hasContacts($l['d'])];
    }
    /** The listing form; $prefill from a WRMS warehouse (?wh=ID) when the user owns it (or is WRMS staff). */
    public function offerForm(Ctx $c, int $id, int $warehouseId = 0): array
    {
        if ($c->uid <= 0) throw new Err('forbidden', '', 403);
        $l = $id > 0 ? ($this->offers->get($id) ?? throw new Err('not_found', '', 404)) : null; if ($l && (int)$l['user_id'] !== $c->uid && $c->role !== 'admin') throw new Err('forbidden', '', 403);
        $mineWh = array_values(array_filter($this->wrms->warehouses(), fn($w) => (int)$w['owner_id'] === $c->uid || $c->staff()));
        $values = $l ? ['name' => $l['name'], 'city' => $l['city'], 'street' => $l['street'], 'lat' => (string)$l['lat'], 'lon' => (string)$l['lon'], 'area' => (string)$l['area'], 'price_m2' => Billing::dec((int)$l['price_m2']), 'status' => $l['status'], 'warehouse_id' => $l['warehouse_id']] + $l['d'] : ['status' => 'published', 'access_24h' => 1];
        if (!$l && $warehouseId > 0) foreach ($mineWh as $w) if ((int)$w['id'] === $warehouseId) $values = $this->offers->prefill($w, $this->wrms->units($warehouseId)) + $values;
        return ['view' => 'offer_form', 'title' => 'offers', 'l' => $l, 'v' => $values, 'warehouses' => $mineWh, 'wh' => $warehouseId, 'key' => $this->s('yandex_key') !== '', 'photos' => $l ? $l['photos'] : [], 'maxPhotos' => Offers::MAX_PHOTOS];
    }
}
