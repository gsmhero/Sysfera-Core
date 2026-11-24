<?php
declare(strict_types=1);
namespace SysferaStoragerent;

/**
 * E-mail notifications to tenants: invoice issued, payment due soon, overdue, contract expiring.
 * The schedule is two comma lists ("5" days before the due date; "1,3,7" days after it). Each message is sent once per (event, object, threshold): the notify_log
 * has a unique key for it, so running the job every hour (or after a gap of several days) never repeats or floods: after a gap only the highest threshold reached is sent.
 * Templates have {placeholders}; a template saved in the database wins over the built-in one for that event and language.
 */
final class Notify
{
    public const EVENTS = ['invoice_issued', 'due_soon', 'overdue', 'contract_expiring'];
    public const PLACEHOLDERS = ['tenant', 'amount', 'due_date', 'invoice', 'contract', 'days', 'end_date', 'penalty', 'site'];
    public const DEFAULTS = [
        'en' => [
            'invoice_issued' => ['Invoice {invoice} issued', "Hello, {tenant}!\n\nInvoice {invoice} under contract {contract} for {amount} has been issued. Due date: {due_date}.\n\n{site}"],
            'due_soon' => ['Payment due in {days} day(s): {invoice}', "Hello, {tenant}!\n\nA reminder: invoice {invoice} for {amount} is due on {due_date} ({days} day(s) left).\n\n{site}"],
            'overdue' => ['Overdue invoice {invoice}', "Hello, {tenant}!\n\nInvoice {invoice} for {amount} was due on {due_date} and is {days} day(s) overdue. Accrued penalty: {penalty}. Please pay as soon as possible.\n\n{site}"],
            'contract_expiring' => ['Contract {contract} expires on {end_date}', "Hello, {tenant}!\n\nYour contract {contract} ends on {end_date} ({days} day(s) left). Contact us if you want to extend it.\n\n{site}"],
        ],
        'ru' => [
            'invoice_issued' => ['Выставлен счёт {invoice}', "Здравствуйте, {tenant}!\n\nПо договору {contract} выставлен счёт {invoice} на сумму {amount}. Срок оплаты: {due_date}.\n\n{site}"],
            'due_soon' => ['Скоро срок оплаты: {invoice}', "Здравствуйте, {tenant}!\n\nНапоминаем: счёт {invoice} на сумму {amount} нужно оплатить до {due_date} (осталось дней: {days}).\n\n{site}"],
            'overdue' => ['Просрочен счёт {invoice}', "Здравствуйте, {tenant}!\n\nСчёт {invoice} на сумму {amount} должен был быть оплачен {due_date}, просрочка — {days} дн. Начислена неустойка: {penalty}. Пожалуйста, оплатите как можно скорее.\n\n{site}"],
            'contract_expiring' => ['Договор {contract} заканчивается {end_date}', "Здравствуйте, {tenant}!\n\nВаш договор {contract} заканчивается {end_date} (осталось дней: {days}). Если хотите продлить его, свяжитесь с нами.\n\n{site}"],
        ],
    ];

    /** @param callable(string|array,string,string,?string):?string $mail like Plugin::mail (null = sent, text = why not) */
    public function __construct(private Store $st, private Ledger $ledger, private $now, private $today, private $mail, private string $site = '') {}

    /** "5, 3,x,5" → [3,5] (positive integers, unique, ascending). */
    public static function parseList(string $s): array
    {
        $a = []; foreach (preg_split('/[\s,;]+/', trim($s)) ?: [] as $x) if (ctype_digit($x) && (int)$x > 0 && (int)$x <= 365) $a[(int)$x] = 1; $a = array_keys($a); sort($a); return $a;
    }
    public function template(string $event, string $lang): array
    {
        $db = $this->st->one('templates', ['event' => $event, 'lang' => $lang]);
        return $db ? [(string)$db['subject'], (string)$db['body']] : (self::DEFAULTS[$lang][$event] ?? self::DEFAULTS['en'][$event]);
    }
    public function saveTemplate(Ctx $c, string $event, string $lang, string $subject, string $body): void
    {
        $c->require('tariff'); if (!in_array($event, self::EVENTS, true) || !in_array($lang, ['en', 'ru'], true)) throw new Err('bad_request');
        $subject = Wrms::clean($subject, 190); $body = Wrms::clean($body, 4000); if ($subject === '' || $body === '') throw new Err('template_empty');
        $row = $this->st->one('templates', ['event' => $event, 'lang' => $lang]);
        if ($row) $this->st->update('templates', (int)$row['id'], ['subject' => $subject, 'body' => $body]); else $this->st->insert('templates', ['event' => $event, 'lang' => $lang, 'subject' => $subject, 'body' => $body]);
    }
    public function resetTemplate(Ctx $c, string $event, string $lang): void
    {
        $c->require('tariff'); $row = $this->st->one('templates', ['event' => $event, 'lang' => $lang]); if ($row) $this->st->delete('templates', (int)$row['id']);
    }

    /**
     * What should be sent today: list of ['dedupe','event','tenant_id','vars','skip'(bool: a lower threshold passed over)].
     * Pure given the data; send() does the delivery.
     */
    public function plan(array $settings): array
    {
        $today = ($this->today)(); $before = self::parseList((string)($settings['before'] ?? '5')); $after = self::parseList((string)($settings['after'] ?? '1,3,7')); $exp = self::parseList((string)($settings['expiring'] ?? '30,7'));
        $plan = [];
        foreach ($this->ledger->invoices([], 20000) as $i) {
            if (in_array($i['stored_status'], ['draft', 'cancelled'], true)) continue;
            $base = ['invoice' => $i['number'], 'contract' => $i['contract_number'], 'amount' => $i['outstanding'] > 0 ? $i['outstanding'] : $i['total'], 'due_date' => $i['due_date'], 'penalty' => $i['penalty_accrued']];
            if ($i['issue_date'] === $today || Billing::days((string)$i['issue_date'], $today) <= 2) $plan[] = ['dedupe' => 'invoice_issued:' . $i['id'], 'event' => 'invoice_issued', 'tenant_id' => (int)$i['tenant_id'], 'vars' => $base + ['days' => 0], 'skip' => false];
            if ($i['outstanding'] <= 0) continue;
            $left = Billing::days($today, (string)$i['due_date']);
            if ($left >= 0) {
                // a reminder N days before the due date only makes sense for an invoice issued before that day; the "issued" e-mail already carries the due date
                $hit = array_values(array_filter($before, fn($d) => $left <= $d)); sort($hit);
                if ($hit) { $top = $hit[0]; foreach ($hit as $d) $plan[] = ['dedupe' => "due_soon:{$i['id']}:$d", 'event' => 'due_soon', 'tenant_id' => (int)$i['tenant_id'], 'vars' => $base + ['days' => $left], 'skip' => $d !== $top || (string)$i['issue_date'] > Billing::addDays((string)$i['due_date'], -$d)]; }
            }
            else { $late = -$left; $hit = array_values(array_filter($after, fn($d) => $late >= $d)); if ($hit) { $top = max($hit); foreach ($hit as $d) $plan[] = ['dedupe' => "overdue:{$i['id']}:$d", 'event' => 'overdue', 'tenant_id' => (int)$i['tenant_id'], 'vars' => $base + ['days' => $late], 'skip' => $d !== $top]; } }
        }
        foreach ($this->st->find('contracts', ['status' => 'active']) as $k) {
            $left = Billing::days($today, (string)$k['end_date']); if ($left < 0) continue;
            $hit = array_values(array_filter($exp, fn($d) => $left <= $d)); sort($hit); if (!$hit) continue;
            foreach ($hit as $d) $plan[] = ['dedupe' => "contract_expiring:{$k['id']}:{$k['end_date']}:$d", 'event' => 'contract_expiring', 'tenant_id' => (int)$k['tenant_id'], 'vars' => ['invoice' => '', 'contract' => $k['number'], 'amount' => 0, 'due_date' => '', 'end_date' => $k['end_date'], 'days' => $left, 'penalty' => 0], 'skip' => $d !== $hit[0]];
        }
        return $plan;
    }
    /** Sends what plan() lists and has not been sent. Returns [sent, failed, skipped]. */
    public function run(array $settings): array
    {
        $sent = $failed = $skipped = 0;
        foreach ($this->plan($settings) as $p) {
            if ($this->st->one('notify_log', ['dedupe' => $p['dedupe']])) continue;
            $t = $this->st->one('tenants', ['id' => $p['tenant_id']]); $to = $t ? trim((string)$t['email']) : '';
            if ($p['skip'] || !$t || $t['status'] === 'archived') { $this->log($p, $to, 'skipped', ''); $skipped++; continue; }
            if ($to === '') { $this->log($p, '', 'no_email', ''); $skipped++; continue; }
            $lang = (string)($settings['lang'] ?? 'ru'); [$subj, $body] = $this->template($p['event'], $lang === 'en' ? 'en' : 'ru');
            $v = $p['vars']; $vars = ['tenant' => $t['name'], 'amount' => Billing::fmt((int)$v['amount'], $lang) . ' RUB', 'due_date' => Billing::dmy((string)$v['due_date']), 'invoice' => $v['invoice'], 'contract' => $v['contract'],
                'days' => $v['days'], 'end_date' => Billing::dmy((string)($v['end_date'] ?? '')), 'penalty' => Billing::fmt((int)$v['penalty'], $lang) . ' RUB', 'site' => $this->site];
            $err = ($this->mail)($to, Billing::fill($subj, $vars), Billing::fill($body, $vars), null);
            if ($err === null) { $this->log($p, $to, 'sent', ''); $sent++; } else $failed++;   // a failure is not logged: the next run tries again
        }
        return [$sent, $failed, $skipped];
    }
    private function log(array $p, string $to, string $status, string $err): void
    {
        $this->st->insert('notify_log', ['dedupe' => $p['dedupe'], 'event' => $p['event'], 'tenant_id' => $p['tenant_id'], 'to_email' => $to, 'status' => $status, 'error' => Wrms::clean($err, 255), 'created_at' => ($this->now)()]);
    }
    public function recent(int $limit = 200): array { return $this->st->find('notify_log', [], 'id DESC', $limit); }
}
