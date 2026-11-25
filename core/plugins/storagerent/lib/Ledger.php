<?php
declare(strict_types=1);
namespace SysferaStoragerent;

/**
 * Invoices, payments, penalties, write-offs, refunds and utilities. The arithmetic is in Billing (pure); this class applies it to stored data.
 *
 * Money model:
 *  - an invoice has items; net / vat / total come from the items and the tenant's VAT settings (Billing::vat);
 *  - settled(invoice) = payments allocated to it + write-offs + credit notes; outstanding = total - settled;
 *  - a payment is spread over the tenant's open invoices, oldest due date first (FIFO), the rest stays as credit and is used by the next invoices;
 *  - a penalty accrues on the unpaid principal of a late invoice (Billing::penalty); it is billed as a line of the next monthly invoice or by "bill penalties".
 */
final class Ledger
{
    public const ADJ_KINDS = ['writeoff', 'credit_note', 'penalty_waive'];
    public const METHODS = ['bank', 'cash', 'card', 'other'];

    public function __construct(private Store $st, private $now, private $today) {}

    private function money(mixed $v, bool $positive = true): int
    {
        $k = Billing::kop($v) ?? throw new Err('amount_invalid'); if ($positive && $k <= 0) throw new Err('amount_invalid'); return $k;
    }

    /* ================================================================== reading invoices */
    public function settled(int $invoiceId): int
    {
        $s = 0;
        foreach ($this->st->find('allocations', ['invoice_id' => $invoiceId]) as $a) $s += (int)$a['amount'];
        foreach ($this->st->find('adjustments', ['invoice_id' => $invoiceId]) as $a) if ($a['kind'] !== 'penalty_waive') $s += (int)$a['amount'];
        return $s;
    }
    /** Payments and write-offs that lowered the unpaid part of the invoice, with their day: the input of Billing::penalty. */
    private function reductions(int $invoiceId): array
    {
        $r = [];
        foreach ($this->st->find('allocations', ['invoice_id' => $invoiceId]) as $a) { $p = $this->st->one('payments', ['id' => (int)$a['payment_id']]); if ($p) $r[] = [(string)$p['pay_date'], (int)$a['amount']]; }
        foreach ($this->st->find('adjustments', ['invoice_id' => $invoiceId]) as $a) if ($a['kind'] !== 'penalty_waive') $r[] = [(string)$a['adj_date'], (int)$a['amount']];
        return $r;
    }
    /** Penalty accrued up to $asOf on this invoice (after waivers) and the part of it not yet billed. */
    public function penaltyOf(array $inv, ?array $contract, string $asOf): array
    {
        if (!$contract || in_array($inv['stored_status'], ['draft', 'cancelled'], true) || (int)$inv['principal'] <= 0) return ['accrued' => 0, 'unbilled' => 0];
        $raw = Billing::penalty((int)$inv['principal'], (string)$inv['due_date'], (int)$contract['grace_days'], (string)$contract['penalty_type'], (float)$contract['penalty_value'], $asOf, $this->reductions((int)$inv['id']), (int)$contract['penalty_cap_pct']);
        $waived = 0; foreach ($this->st->find('adjustments', ['invoice_id' => (int)$inv['id'], 'kind' => 'penalty_waive']) as $a) $waived += (int)$a['amount'];
        $acc = max(0, $raw - $waived);
        return ['accrued' => $acc, 'unbilled' => max(0, $acc - (int)$inv['penalty_billed'])];
    }
    public function invoice(int $id): ?array
    {
        $inv = $this->st->one('invoices', ['id' => $id]); if (!$inv) return null; return $this->view($inv);
    }
    private function view(array $inv): array
    {
        $today = ($this->today)(); $settled = $this->settled((int)$inv['id']); $k = $this->st->one('contracts', ['id' => (int)$inv['contract_id']]);
        $pen = $this->penaltyOf($inv, $k, $today); $t = $this->st->one('tenants', ['id' => (int)$inv['tenant_id']]);
        $status = Billing::invoiceStatus((string)$inv['stored_status'], (int)$inv['total'], $settled, (string)$inv['due_date'], $today);
        return array_replace($inv, ['status' => $status, 'settled' => $settled, 'outstanding' => in_array($status, ['draft', 'cancelled'], true) ? 0 : (int)$inv['total'] - $settled,
            'late' => Billing::isLate((string)$inv['stored_status'], (int)$inv['total'], $settled, (string)$inv['due_date'], $today), 'days_late' => max(0, Billing::days((string)$inv['due_date'], $today)),
            'penalty_accrued' => $pen['accrued'], 'penalty_unbilled' => $pen['unbilled'], 'tenant_name' => $t['name'] ?? '', 'contract_number' => $k['number'] ?? '']);
    }
    public function items(int $invoiceId): array { return $this->st->find('invoice_items', ['invoice_id' => $invoiceId], 'id'); }
    public function invoices(array $where = [], int $limit = 5000): array
    {
        return array_map(fn($i) => $this->view($i), $this->st->find('invoices', $where, 'due_date DESC', $limit));
    }
    public function allocations(int $invoiceId): array
    {
        return array_map(function ($a) { $p = $this->st->one('payments', ['id' => (int)$a['payment_id']]) ?? []; return $a + ['pay_date' => $p['pay_date'] ?? '', 'method' => $p['method'] ?? '', 'reference' => $p['reference'] ?? '']; }, $this->st->find('allocations', ['invoice_id' => $invoiceId]));
    }
    public function adjustments(int $invoiceId): array { return $this->st->find('adjustments', ['invoice_id' => $invoiceId], 'id'); }

    /** Persists the derived status of the invoice (so lists and exports can filter by it without recomputing). */
    public function refresh(int $invoiceId): void
    {
        $inv = $this->st->one('invoices', ['id' => $invoiceId]); if (!$inv) return;
        $s = Billing::invoiceStatus((string)$inv['stored_status'], (int)$inv['total'], $this->settled($invoiceId), (string)$inv['due_date'], ($this->today)());
        if ($s !== $inv['status']) $this->st->update('invoices', $invoiceId, ['status' => $s]);
    }

    /* ================================================================== building an invoice */
    private function nextInvoiceNumber(): string
    {
        $n = 0; foreach (($this->st instanceof OwnerStore ? $this->st->unscoped() : $this->st)->find('invoices', [], 'id DESC', 30) as $i) if (preg_match('/^INV-(\d+)$/', (string)$i['number'], $m)) $n = max($n, (int)$m[1]);
        return sprintf('INV-%06d', $n + 1);
    }
    private function newInvoice(array $row): int { return $this->st->insert('invoices', $row + ['net' => 0, 'vat' => 0, 'total' => 0, 'principal' => 0, 'penalty_billed' => 0]); }
    /** Recomputes net / vat / total / principal of an invoice from its items and the tenant's VAT settings. */
    public function recalc(int $invoiceId): void
    {
        $inv = $this->st->one('invoices', ['id' => $invoiceId]) ?? throw new Err('not_found', '', 404); $t = $this->st->one('tenants', ['id' => (int)$inv['tenant_id']]) ?? [];
        $vatable = 0; $plain = 0; $pen = 0;
        foreach ($this->items($invoiceId) as $it) { if ((int)$it['vatable']) $vatable += (int)$it['amount']; else $plain += (int)$it['amount']; if ($it['kind'] === 'penalty') $pen += (int)$it['amount']; }
        $v = Billing::vat((string)($t['vat_mode'] ?? 'none'), (float)($t['vat_rate'] ?? 0), $vatable, $plain);
        $this->st->update('invoices', $invoiceId, ['net' => $v['net'], 'vat' => $v['vat'], 'total' => $v['total'], 'principal' => max(0, $v['total'] - $pen)]);
        $this->refresh($invoiceId);
    }
    private function addItem(int $invoiceId, string $kind, string $desc, float $qty, int $amount, bool $vatable, ?int $ref = null): void
    {
        $this->st->insert('invoice_items', ['invoice_id' => $invoiceId, 'kind' => $kind, 'description' => Wrms::clean($desc, 255), 'qty' => $qty, 'amount' => $amount, 'vatable' => $vatable ? 1 : 0, 'ref_id' => $ref]);
    }

    /**
     * Creates the invoices that are due on $today for every active contract (rent + utilities + penalties), once per contract and period.
     * Safe to run any number of times. Returns the ids of the invoices created.
     * @return int[]
     */
    public function generate(?string $today = null): array
    {
        $today ??= ($this->today)(); $made = [];
        foreach ($this->st->find('contracts', ['status' => 'active']) as $k) {
            foreach (Billing::periods((string)$k['start_date'], (string)$k['end_date'], (int)$k['billing_months'], $today) as [$ps, $pe]) {
                if ($this->st->one('invoices', ['contract_id' => (int)$k['id'], 'kind' => 'rent', 'period_start' => $ps])) continue;
                $made[] = $this->st->atomic(fn() => $this->rentInvoice($k, $ps, $pe, $today));
            }
        }
        foreach ($made as $id) $this->applyCredit((int)$this->st->one('invoices', ['id' => $id])['tenant_id']);
        return $made;
    }
    private function rentInvoice(array $k, string $ps, string $pe, string $today): int
    {
        $lines = []; foreach ($this->st->find('contract_units', ['contract_id' => (int)$k['id']]) as $l) $lines[] = $l + ['unit' => $this->st->one('units', ['id' => (int)$l['unit_id']]) ?? []];
        $rent = Billing::monthlyRent((string)$k['rate_type'], (int)$k['rate'], Wrms::rentLines($lines)); $months = (int)$k['billing_months'];
        $amount = $rent['amount'] * $months; [$cov, $tot] = Billing::coverage($ps, $pe, (string)$k['start_date'], (string)$k['end_date']);
        $partial = (int)$k['prorate'] && $cov < $tot;
        if ($partial) $amount = Billing::rdiv($amount * $cov, $tot);
        $due = Billing::dueDate($ps, (int)$k['due_day']);
        $inv = $this->newInvoice(['number' => $this->nextInvoiceNumber(), 'contract_id' => (int)$k['id'], 'tenant_id' => (int)$k['tenant_id'], 'kind' => 'rent', 'period_start' => $ps, 'period_end' => $pe,
            'issue_date' => $today, 'due_date' => $due, 'stored_status' => 'issued', 'status' => 'issued', 'reason' => '', 'created_by' => 0, 'created_at' => ($this->now)()]);
        $label = Billing::dmy($ps) . ' - ' . Billing::dmy($pe);
        $this->addItem($inv, 'rent', "rent|$label|" . $rent['unit'] . '|' . Billing::dec((int)$k['rate']) . ($partial ? "|$cov/$tot" : ''), $rent['qty'], $amount, true);
        if ($k['util_method'] === 'fixed' && (int)$k['util_fixed'] > 0) $this->addItem($inv, 'utility', "utility_fixed|$label", 1, (int)$k['util_fixed'] * $months, true);
        if ($k['util_method'] === 'metered') $this->billReadings($inv, $k, $pe);
        $pen = $this->unbilledPenalties((int)$k['tenant_id'], $today, (int)$k['id'], $inv);
        if ($pen['sum'] > 0) $this->addItem($inv, 'penalty', 'penalty|' . implode(',', array_map(fn($i) => $i['number'], $pen['items'])), 1, $pen['sum'], false);
        foreach ($pen['items'] as $p) $this->st->update('invoices', (int)$p['id'], ['penalty_billed' => (int)$p['penalty_billed'] + $p['unbilled']]);
        $this->recalc($inv); return $inv;
    }
    /** Unbilled penalties of the tenant's late invoices (other than $exceptInvoice) of the same contract as of $asOf. */
    private function unbilledPenalties(int $tenantId, string $asOf, int $contractId, int $exceptInvoice = 0): array
    {
        $k = $this->st->one('contracts', ['id' => $contractId]); $items = []; $sum = 0;
        foreach ($this->st->find('invoices', ['contract_id' => $contractId], 'id') as $i) {
            if ((int)$i['id'] === $exceptInvoice || $i['kind'] === 'penalty') continue;
            $p = $this->penaltyOf($i, $k, $asOf); if ($p['unbilled'] > 0) { $items[] = $i + ['unbilled' => $p['unbilled']]; $sum += $p['unbilled']; }
        }
        return ['items' => $items, 'sum' => $sum];
    }
    /** Bills the penalties accrued so far on a contract as an invoice of its own, due immediately (the "bill penalties" button). */
    public function billPenalties(Ctx $c, int $contractId): ?int
    {
        $c->require('write'); $k = $this->st->one('contracts', ['id' => $contractId]) ?? throw new Err('not_found', '', 404); $today = ($this->today)();
        $pen = $this->unbilledPenalties((int)$k['tenant_id'], $today, $contractId); if ($pen['sum'] <= 0) return null;
        return $this->st->atomic(function () use ($c, $k, $pen, $today) {
            $inv = $this->newInvoice(['number' => $this->nextInvoiceNumber(), 'contract_id' => (int)$k['id'], 'tenant_id' => (int)$k['tenant_id'], 'kind' => 'penalty', 'period_start' => null, 'period_end' => null, 'issue_date' => $today,
                'due_date' => $today, 'stored_status' => 'issued', 'status' => 'issued', 'reason' => 'penalties', 'created_by' => $c->uid, 'created_at' => ($this->now)()]);
            $this->addItem($inv, 'penalty', 'penalty|' . implode(',', array_map(fn($i) => $i['number'], $pen['items'])), 1, $pen['sum'], false);
            foreach ($pen['items'] as $p) $this->st->update('invoices', (int)$p['id'], ['penalty_billed' => (int)$p['penalty_billed'] + $p['unbilled']]);
            $this->recalc($inv); $this->applyCredit((int)$k['tenant_id']); return $inv;
        });
    }
    /** Manual invoice (draft or issued). $items = list of ['description','amount' (text),'qty'?,'vatable'?]; a reason is required and logged. */
    public function createManual(Ctx $c, int $contractId, array $items, string $due, string $reason, bool $issue = true): int
    {
        $c->require('write'); $k = $this->st->one('contracts', ['id' => $contractId]) ?? throw new Err('not_found', '', 404);
        $reason = Wrms::clean($reason, 255); if ($reason === '') throw new Err('reason_required');
        $d = Billing::parseDate($due) ?? throw new Err('date_invalid'); $items = array_values(array_filter($items, fn($i) => trim((string)($i['description'] ?? '')) !== '' || trim((string)($i['amount'] ?? '')) !== ''));
        if (!$items) throw new Err('items_required');
        return $this->st->atomic(function () use ($c, $k, $items, $d, $reason, $issue) {
            $inv = $this->newInvoice(['number' => $this->nextInvoiceNumber(), 'contract_id' => (int)$k['id'], 'tenant_id' => (int)$k['tenant_id'], 'kind' => 'manual', 'period_start' => null, 'period_end' => null, 'issue_date' => ($this->today)(),
                'due_date' => $d, 'stored_status' => $issue ? 'issued' : 'draft', 'status' => $issue ? 'issued' : 'draft', 'reason' => $reason, 'created_by' => $c->uid, 'created_at' => ($this->now)()]);
            foreach ($items as $i) {
                $desc = Wrms::clean($i['description'] ?? '', 255); if ($desc === '') throw new Err('items_required');
                $this->addItem($inv, 'other', $desc, (float)str_replace(',', '.', (string)($i['qty'] ?? 1)) ?: 1.0, $this->money($i['amount'] ?? '', false), !empty($i['vatable']));
            }
            $this->recalc($inv); (new Wrms($this->st, $this->now, $this->today))->audit($c->uid, 'invoice', $inv, 'create_manual', ['reason' => $reason]);
            if ($issue) $this->applyCredit((int)$k['tenant_id']);
            return $inv;
        });
    }
    public function issue(Ctx $c, int $id): void
    {
        $c->require('write'); $inv = $this->st->one('invoices', ['id' => $id]) ?? throw new Err('not_found', '', 404); if ($inv['stored_status'] !== 'draft') throw new Err('state_invalid');
        $this->st->update('invoices', $id, ['stored_status' => 'issued', 'issue_date' => ($this->today)()]); $this->refresh($id); $this->applyCredit((int)$inv['tenant_id']);
    }
    /** Cancels an invoice: its payments go back to the tenant's credit; a reason is required. */
    public function cancel(Ctx $c, int $id, string $reason): void
    {
        $c->require('write'); $inv = $this->st->one('invoices', ['id' => $id]) ?? throw new Err('not_found', '', 404); $reason = Wrms::clean($reason, 255); if ($reason === '') throw new Err('reason_required');
        if ($inv['stored_status'] === 'cancelled') throw new Err('state_invalid');
        $this->st->atomic(function () use ($c, $id, $inv, $reason) {
            foreach ($this->st->find('allocations', ['invoice_id' => $id]) as $a) $this->st->delete('allocations', (int)$a['id']);
            $this->st->update('invoices', $id, ['stored_status' => 'cancelled', 'status' => 'cancelled', 'reason' => $reason]);
            (new Wrms($this->st, $this->now, $this->today))->audit($c->uid, 'invoice', $id, 'cancel', ['reason' => $reason]); $this->applyCredit((int)$inv['tenant_id']);
        });
    }

    /* ================================================================== payments */
    /** Tenant's money not matched to any invoice: payments - refunds - allocations. */
    public function credit(int $tenantId): int
    {
        $s = 0; foreach ($this->st->find('payments', ['tenant_id' => $tenantId]) as $p) { $s += (int)$p['amount']; foreach ($this->st->find('allocations', ['payment_id' => (int)$p['id']]) as $a) $s -= (int)$a['amount']; }
        return $s;
    }
    private function openInvoices(int $tenantId, ?int $contractId = null): array
    {
        $out = [];
        foreach ($this->st->find('invoices', ['tenant_id' => $tenantId]) as $i) {
            if ($contractId && (int)$i['contract_id'] !== $contractId) continue; if (in_array($i['stored_status'], ['draft', 'cancelled'], true)) continue;
            $o = (int)$i['total'] - $this->settled((int)$i['id']); if ($o > 0) $out[] = ['id' => (int)$i['id'], 'due' => (string)$i['due_date'], 'out' => $o];
        }
        return $out;
    }
    private function unallocated(int $paymentId): int
    {
        $p = $this->st->one('payments', ['id' => $paymentId]); if (!$p) return 0; $s = (int)$p['amount'];
        foreach ($this->st->find('allocations', ['payment_id' => $paymentId]) as $a) $s -= (int)$a['amount']; return $s;
    }
    /** Spreads every payment's unallocated money over the tenant's open invoices (FIFO). Called after each new invoice and payment. */
    public function applyCredit(int $tenantId): void
    {
        foreach ($this->st->find('payments', ['tenant_id' => $tenantId], 'pay_date') as $p) {
            if ($p['kind'] !== 'payment') continue; $left = $this->unallocated((int)$p['id']); if ($left <= 0) continue;
            [$al] = Billing::fifo($left, $this->openInvoices($tenantId, $p['contract_id'] ? (int)$p['contract_id'] : null));
            foreach ($al as $iid => $amt) { $this->st->insert('allocations', ['payment_id' => (int)$p['id'], 'invoice_id' => $iid, 'amount' => $amt]); $this->refresh($iid); }
        }
    }
    public function registerPayment(Ctx $c, int $tenantId, ?int $contractId, string $date, mixed $amount, string $method, string $reference, string $note = ''): array
    {
        $c->require('write'); if (!$this->st->one('tenants', ['id' => $tenantId])) throw new Err('tenant_required');
        if ($contractId && !$this->st->one('contracts', ['id' => $contractId, 'tenant_id' => $tenantId])) throw new Err('not_found', '', 404);
        $d = Billing::parseDate($date) ?? throw new Err('date_invalid'); $amt = $this->money($amount); if (!in_array($method, self::METHODS, true)) $method = 'other';
        return $this->st->atomic(function () use ($c, $tenantId, $contractId, $d, $amt, $method, $reference, $note) {
            $id = $this->st->insert('payments', ['tenant_id' => $tenantId, 'contract_id' => $contractId ?: null, 'pay_date' => $d, 'amount' => $amt, 'kind' => 'payment', 'method' => $method,
                'reference' => Wrms::clean($reference, 120), 'note' => Wrms::clean($note, 255), 'created_by' => $c->uid, 'created_at' => ($this->now)()]);
            $this->applyCredit($tenantId); return $this->payment($id);
        });
    }
    public function payment(int $id): ?array
    {
        $p = $this->st->one('payments', ['id' => $id]); if (!$p) return null;
        $al = $this->st->find('allocations', ['payment_id' => $id]); $t = $this->st->one('tenants', ['id' => (int)$p['tenant_id']]);
        return $p + ['allocations' => $al, 'unallocated' => $this->unallocated($id), 'tenant_name' => $t['name'] ?? ''];
    }
    public function payments(array $where = [], int $limit = 5000): array { return array_map(fn($p) => $this->payment((int)$p['id']), $this->st->find('payments', $where, 'pay_date DESC', $limit)); }
    /** Manual reassignment: replaces the allocations of a payment by $map (invoice id => amount text). The sum may not exceed the payment; each part only the invoice's open amount. */
    public function reassign(Ctx $c, int $paymentId, array $map): array
    {
        $c->require('write'); $p = $this->st->one('payments', ['id' => $paymentId]) ?? throw new Err('not_found', '', 404); if ($p['kind'] !== 'payment') throw new Err('state_invalid');
        return $this->st->atomic(function () use ($c, $p, $paymentId, $map) {
            $old = $this->st->find('allocations', ['payment_id' => $paymentId]); foreach ($old as $a) $this->st->delete('allocations', (int)$a['id']);
            $sum = 0; $touch = array_map(fn($a) => (int)$a['invoice_id'], $old);
            foreach ($map as $iid => $text) {
                $amt = Billing::kop($text) ?? 0; if ($amt <= 0) continue; $inv = $this->st->one('invoices', ['id' => (int)$iid]);
                if (!$inv || (int)$inv['tenant_id'] !== (int)$p['tenant_id'] || in_array($inv['stored_status'], ['draft', 'cancelled'], true)) throw new Err('not_found', '', 404);
                if ($amt > (int)$inv['total'] - $this->settled((int)$iid)) throw new Err('amount_too_big');
                $sum += $amt; $this->st->insert('allocations', ['payment_id' => $paymentId, 'invoice_id' => (int)$iid, 'amount' => $amt]); $touch[] = (int)$iid;
            }
            if ($sum > (int)$p['amount']) throw new Err('amount_too_big');
            foreach (array_unique($touch) as $i) $this->refresh($i);
            (new Wrms($this->st, $this->now, $this->today))->audit($c->uid, 'payment', $paymentId, 'reassign', ['map' => $map]);
            $this->applyCredit((int)$p['tenant_id']); return $this->payment($paymentId);
        });
    }
    /** Pays money back out of the tenant's credit (payments not matched to invoices). */
    public function refund(Ctx $c, int $tenantId, mixed $amount, string $date, string $reason): int
    {
        $c->require('write'); $amt = $this->money($amount); $d = Billing::parseDate($date) ?? throw new Err('date_invalid'); $reason = Wrms::clean($reason, 255); if ($reason === '') throw new Err('reason_required');
        if (!$this->st->one('tenants', ['id' => $tenantId])) throw new Err('tenant_required'); if ($amt > $this->credit($tenantId)) throw new Err('refund_too_big');
        $id = $this->st->insert('payments', ['tenant_id' => $tenantId, 'contract_id' => null, 'pay_date' => $d, 'amount' => -$amt, 'kind' => 'refund', 'method' => 'bank', 'reference' => '', 'note' => $reason, 'created_by' => $c->uid, 'created_at' => ($this->now)()]);
        (new Wrms($this->st, $this->now, $this->today))->audit($c->uid, 'payment', $id, 'refund', ['reason' => $reason]); return $id;
    }

    /* ================================================================== write-offs, credit notes, waivers */
    /** kind: writeoff (forgive unpaid money), credit_note (reduce the invoice), penalty_waive (cancel accrued penalty). A reason is required. */
    public function adjust(Ctx $c, int $invoiceId, string $kind, mixed $amount, string $reason): int
    {
        $c->require('write'); if (!in_array($kind, self::ADJ_KINDS, true)) throw new Err('bad_request');
        $inv = $this->invoice($invoiceId) ?? throw new Err('not_found', '', 404); $reason = Wrms::clean($reason, 255); if ($reason === '') throw new Err('reason_required');
        $amt = $this->money($amount); $limit = $kind === 'penalty_waive' ? $inv['penalty_accrued'] : $inv['outstanding'];
        if (in_array($inv['status'], ['draft', 'cancelled'], true) || $amt > $limit) throw new Err('amount_too_big');
        $id = $this->st->insert('adjustments', ['invoice_id' => $invoiceId, 'kind' => $kind, 'amount' => $amt, 'adj_date' => ($this->today)(), 'reason' => $reason, 'user_id' => $c->uid, 'created_at' => ($this->now)()]);
        (new Wrms($this->st, $this->now, $this->today))->audit($c->uid, 'invoice', $invoiceId, $kind, ['amount' => $amt, 'reason' => $reason]); $this->refresh($invoiceId); return $id;
    }

    /* ================================================================== balances and periodic run */
    /** What the tenant owes: open invoices + penalties not yet billed - unmatched credit. */
    public function balance(int $tenantId): array
    {
        $out = 0; $pen = 0; $late = 0;
        foreach ($this->invoices(['tenant_id' => $tenantId]) as $i) { $out += $i['outstanding']; if ($i['late']) $late += $i['outstanding']; $pen += $i['penalty_unbilled']; }
        return ['outstanding' => $out, 'late' => $late, 'penalty_unbilled' => $pen, 'credit' => $this->credit($tenantId), 'debt' => max(0, $out + $pen - $this->credit($tenantId))];
    }
    /** The daily run: generate invoices, refresh all statuses. Returns ids of new invoices. */
    public function tick(?string $today = null): array
    {
        $made = $this->generate($today);
        foreach ($this->st->find('invoices', []) as $i) if (!in_array($i['stored_status'], ['draft', 'cancelled'], true)) $this->refresh((int)$i['id']);
        return $made;
    }

    /* ================================================================== utilities */
    public function saveMeter(Ctx $c, int $id, int $unitId, string $type, string $serial, mixed $rate, ?string $rollover = null): int
    {
        $c->require('tariff'); if (!in_array($type, ['electricity', 'water', 'heat', 'gas'], true)) throw new Err('type_invalid');
        if (!$this->st->one('units', ['id' => $unitId])) throw new Err('unit_required');
        $row = ['unit_id' => $unitId, 'type' => $type, 'serial' => Wrms::clean($serial, 60), 'rate' => $this->money($rate === '' || $rate === null ? '0' : $rate, false), 'rollover' => $rollover !== null && trim($rollover) !== '' && is_numeric(str_replace(',', '.', $rollover)) ? (float)str_replace(',', '.', $rollover) : null];
        if ($id > 0) { if (!$this->st->one('meters', ['id' => $id])) throw new Err('not_found', '', 404); $this->st->update('meters', $id, $row); return $id; }
        return $this->st->insert('meters', $row);
    }
    public function meters(?int $unitId = null): array
    {
        return array_map(function ($m) { $last = $this->st->find('readings', ['meter_id' => (int)$m['id']], 'read_date DESC', 1)[0] ?? null; $u = $this->st->one('units', ['id' => (int)$m['unit_id']]); return $m + ['last' => $last, 'unit_name' => $u['name'] ?? '']; },
            $this->st->find('meters', $unitId ? ['unit_id' => $unitId] : [], 'id'));
    }
    /** Monthly meter reading: consumption against the previous reading, charge at the meter's rate. A lower value is refused unless the meter has a rollover. */
    public function addReading(Ctx $c, int $meterId, string $date, mixed $value, ?int $photoDocId = null): array
    {
        $c->require('write'); $m = $this->st->one('meters', ['id' => $meterId]) ?? throw new Err('not_found', '', 404);
        $d = Billing::parseDate($date) ?? throw new Err('date_invalid'); $s = str_replace([',', ' '], ['.', ''], trim((string)$value)); if (!is_numeric($s) || (float)$s < 0) throw new Err('number_invalid');
        $prev = $this->st->find('readings', ['meter_id' => $meterId], 'read_date DESC', 1)[0] ?? null; $cons = 0.0;
        if ($prev) {
            if ($d < $prev['read_date']) throw new Err('reading_order');
            $cons = Billing::consumption((float)$prev['value'], (float)$s, $m['rollover'] !== null ? (float)$m['rollover'] : null) ?? throw new Err('reading_lower');
        }
        $id = $this->st->insert('readings', ['meter_id' => $meterId, 'read_date' => $d, 'value' => (float)$s, 'consumption' => $cons, 'charge' => Billing::utilityCharge($cons, (int)$m['rate']), 'photo_doc_id' => $photoDocId, 'billed_invoice_id' => null, 'user_id' => $c->uid, 'created_at' => ($this->now)()]);
        return $this->st->one('readings', ['id' => $id]);
    }
    public function readings(int $meterId): array { return $this->st->find('readings', ['meter_id' => $meterId], 'read_date DESC', 200); }
    /** Adds the unbilled readings of the contract's units (up to the period end) to the invoice. A unit shared by several metered contracts is billed to the one with the biggest share. */
    private function billReadings(int $invoiceId, array $k, string $periodEnd): void
    {
        foreach ($this->st->find('contract_units', ['contract_id' => (int)$k['id']]) as $l) {
            if (!$this->billsUnit($k, $l)) continue;
            foreach ($this->st->find('meters', ['unit_id' => (int)$l['unit_id']]) as $m) foreach ($this->st->find('readings', ['meter_id' => (int)$m['id'], 'billed_invoice_id' => null], 'read_date') as $r) {
                if ($r['read_date'] > $periodEnd || (float)$r['consumption'] <= 0) { if ($r['read_date'] <= $periodEnd) $this->st->update('readings', (int)$r['id'], ['billed_invoice_id' => $invoiceId]); continue; }
                $u = $this->st->one('units', ['id' => (int)$l['unit_id']]);
                $this->addItem($invoiceId, 'utility', 'utility|' . $m['type'] . '|' . ($u['name'] ?? '') . '|' . Billing::dmy((string)$r['read_date']) . '|' . rtrim(rtrim(number_format((float)$r['consumption'], 3, '.', ''), '0'), '.'), (float)$r['consumption'], (int)$r['charge'], true, (int)$r['id']);
                $this->st->update('readings', (int)$r['id'], ['billed_invoice_id' => $invoiceId]);
            }
        }
    }
    private function billsUnit(array $k, array $line): bool
    {
        $best = null; $bestShare = -1.0;
        foreach ($this->st->find('contract_units', ['unit_id' => (int)$line['unit_id']]) as $o) {
            $oc = $this->st->one('contracts', ['id' => (int)$o['contract_id']]); if (!$oc || $oc['status'] !== 'active' || $oc['util_method'] !== 'metered') continue;
            if ((float)$o['share_pct'] > $bestShare || ((float)$o['share_pct'] === $bestShare && (int)$oc['id'] < (int)$best)) { $best = (int)$oc['id']; $bestShare = (float)$o['share_pct']; }
        }
        return $best === (int)$k['id'];
    }
}
