<?php
declare(strict_types=1);
namespace SysferaContactlog;

/**
 * The rules of the Contact Log. Everything is scoped to the acting user id: nobody (administrators included) reads or changes another user's cards.
 * Runs against any Store (the database one in production, an in-memory one in the tests); time and the mailer are injected.
 */
final class Svc
{
    public const STATUSES = ['busy', 'no_answer', 'sent_email', 'dropped', 'ignored', 'call_later', 'wrong_number', 'application', 'actual', 'not_relevant'];
    public const ACTIONS = ['call', 'email', 'messenger', 'success'];
    public const COLORS = ['busy' => '#ef4444', 'no_answer' => '#f59e0b', 'sent_email' => '#3b82f6', 'dropped' => '#6b7280', 'ignored' => '#6b7280', 'call_later' => '#8b5cf6',
        'wrong_number' => '#ef4444', 'application' => '#10b981', 'actual' => '#10b981', 'not_relevant' => '#6b7280'];
    public const MAX_CARDS = 3000;
    public const MAX_LOGS = 500;   // per card; the oldest entries go first

    /** @param callable():int $now  @param callable(string|array,string,string,?string):?string $mail like Plugin::mail (null = sent, text = why not) */
    public function __construct(private Store $st, private $now, private $mail) {}

    private static function clean(mixed $v, int $max): string
    {
        $s = trim(str_replace("\0", '', (string)$v));
        return function_exists('mb_substr') ? mb_substr($s, 0, $max) : substr($s, 0, $max);
    }
    private function mine(int $uid, int $id): array
    {
        $c = ($id > 0 && $uid > 0) ? $this->st->one('cards', ['id' => $id, 'user_id' => $uid]) : null;
        if (!$c) throw new Err('not_found', '', 404);
        return $c;
    }

    /**
     * Normalises a date to YYYY-MM-DD ('' = none). Accepts what <input type=date> submits (2026-12-01) and what a browser without a date picker lets people type
     * (01.12.2026, 1/12/2026 = day first, 2026/12/01), each with an optional time after it. Anything else, or an impossible day, is date_invalid.
     */
    public static function date(mixed $v): string
    {
        $s = trim((string)$v); if ($s === '') return '';
        if (!preg_match('~^(?:(\d{4})[-./](\d{1,2})[-./](\d{1,2})|(\d{1,2})[./-](\d{1,2})[./-](\d{4}))(?:[T\s]\d{1,2}:\d{2}(?::\d{2}(?:\.\d+)?)?(?:Z|[+-]\d{2}:?\d{2})?)?$~', $s, $m)) throw new Err('date_invalid');
        [$y, $mo, $d] = ($m[1] ?? '') !== '' ? [(int)$m[1], (int)$m[2], (int)$m[3]] : [(int)$m[6], (int)$m[5], (int)$m[4]];
        if (!checkdate($mo, $d, $y) || $y < 1900) throw new Err('date_invalid');
        return sprintf('%04d-%02d-%02d', $y, $mo, $d);
    }

    /** Cards of the user with their logs, newest first. */
    public function cards(int $uid): array
    {
        if ($uid <= 0) return [];
        $cards = $this->st->find('cards', ['user_id' => $uid], 'created_at DESC', self::MAX_CARDS);
        $logs = [];
        if ($cards) foreach ($this->st->find('logs', ['card_id' => array_map(fn($c) => (int)$c['id'], $cards)], 'id', 500000) as $l) $logs[(int)$l['card_id']][] = self::logView($l);
        return array_map(fn($c) => self::view($c, $logs[(int)$c['id']] ?? []), $cards);
    }
    /** For the dashboard: the next contacts planned (a date set, overdue ones first) and the latest contacts (by the last logged action). Only the user's own cards. */
    public function glance(int $uid, string $today, int $n = 3): array
    {
        $cards = $this->cards($uid); $next = []; $recent = [];
        foreach ($cards as $c) {
            if ($c['next_call'] !== '') $next[] = $c;
            $last = $c['logs'] ? max(array_column($c['logs'], 'at')) : 0;
            if ($last > 0) { $acts = $c['logs']; usort($acts, fn($a, $b) => [$a['at'], $a['id']] <=> [$b['at'], $b['id']]); $recent[] = $c + ['last_at' => $last, 'last_action' => (string)end($acts)['action']]; }
        }
        usort($next, fn($a, $b) => [$a['next_call'], $a['id']] <=> [$b['next_call'], $b['id']]);
        usort($recent, fn($a, $b) => [$b['last_at'], $b['id']] <=> [$a['last_at'], $a['id']]);
        return ['next' => array_slice($next, 0, $n), 'recent' => array_slice($recent, 0, $n), 'today' => $today];
    }
    private static function logView(array $l): array { return ['id' => (int)$l['id'], 'action' => (string)$l['action'], 'at' => (int)$l['created_at']]; }
    private static function view(array $c, array $logs): array
    {
        return ['id' => (int)$c['id'], 'name' => (string)$c['name'], 'second_name' => (string)$c['second_name'], 'surname' => (string)$c['surname'], 'mobile' => (string)$c['mobile'], 'messenger' => (string)$c['messenger'],
            'email' => (string)$c['email'], 'status' => (string)$c['status'], 'theme' => (string)$c['theme'], 'notes' => (string)($c['notes'] ?? ''), 'next_call' => (string)($c['next_call'] ?? ''),
            'created_at' => (int)$c['created_at'], 'logs' => $logs];
    }
    private function logs(int $cardId): array { return array_map([self::class, 'logView'], $this->st->find('logs', ['card_id' => $cardId], 'id', 500000)); }

    /** Create (id 0) or update a card. Returns the card as the page shows it. */
    public function save(int $uid, int $id, array $in): array
    {
        if ($uid <= 0) throw new Err('forbidden', '', 403);
        $name = self::clean($in['name'] ?? '', 100); if ($name === '') throw new Err('name_required');
        $email = self::clean($in['email'] ?? '', 190); if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Err('email_invalid');
        $status = (string)($in['status'] ?? self::STATUSES[0]); if (!in_array($status, self::STATUSES, true)) throw new Err('status_invalid');
        $next = self::date($in['next_call'] ?? '');
        $row = ['name' => $name, 'second_name' => self::clean($in['second_name'] ?? '', 100), 'surname' => self::clean($in['surname'] ?? '', 100), 'mobile' => self::clean($in['mobile'] ?? '', 40),
            'messenger' => self::clean($in['messenger'] ?? '', 100), 'email' => $email, 'status' => $status, 'theme' => self::clean($in['theme'] ?? '', 190), 'notes' => self::clean($in['notes'] ?? '', 4000),
            'next_call' => $next === '' ? null : $next, 'updated_at' => ($this->now)()];
        return $this->st->atomic(function () use ($uid, $id, $row) {
            if ($id > 0) { $this->mine($uid, $id); $this->st->update('cards', $id, $row); }
            else {
                if (count($this->st->find('cards', ['user_id' => $uid], 'id', self::MAX_CARDS + 1)) >= self::MAX_CARDS) throw new Err('too_many');
                $id = $this->st->insert('cards', $row + ['user_id' => $uid, 'created_at' => ($this->now)()]);
            }
            return self::view($this->mine($uid, $id), $this->logs($id));
        });
    }

    public function delete(int $uid, int $id): void
    {
        $this->mine($uid, $id);
        $this->st->atomic(function () use ($id) { $this->wipeLogs($id); $this->st->delete('cards', $id); });
    }
    private function wipeLogs(int $id): void { foreach ($this->st->find('logs', ['card_id' => $id], 'id', 500000) as $l) $this->st->delete('logs', (int)$l['id']); }

    /** One click of Call / Email / Msg / Success: adds a time-stamped entry to the card's log. Returns the log. */
    public function addLog(int $uid, int $id, string $action): array
    {
        $this->mine($uid, $id); if (!in_array($action, self::ACTIONS, true)) throw new Err('action_invalid');
        return $this->st->atomic(function () use ($id, $action) {
            $this->st->insert('logs', ['card_id' => $id, 'action' => $action, 'created_at' => ($this->now)()]);
            $all = $this->st->find('logs', ['card_id' => $id], 'id', self::MAX_LOGS + 50);
            for ($i = 0, $extra = count($all) - self::MAX_LOGS; $i < $extra; $i++) $this->st->delete('logs', (int)$all[$i]['id']);
            return $this->logs($id);
        });
    }
    public function clearLog(int $uid, int $id): void
    {
        $this->mine($uid, $id);
        $this->st->atomic(fn() => $this->wipeLogs($id));
    }

    /* ---------------- profile: the copy address of the reports ---------------- */
    public function copyEmail(int $uid): string { return (string)($this->st->one('profiles', ['user_id' => $uid])['copy_email'] ?? ''); }
    public function saveCopyEmail(int $uid, string $email): string
    {
        if ($uid <= 0) throw new Err('forbidden', '', 403);
        $email = self::clean($email, 190); if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Err('email_invalid');
        $p = $this->st->one('profiles', ['user_id' => $uid]);
        if ($p) $this->st->update('profiles', (int)$p['id'], ['copy_email' => $email]); else $this->st->insert('profiles', ['user_id' => $uid, 'copy_email' => $email]);
        return $email;
    }

    /**
     * E-mail the report of a card to the user's own address and, if set, the copy address, through the platform mailer.
     * The mailer's answer is never hidden: a refusal (not set up, SMTP failure) is an error, never a silent fallback to something else.
     * @param array $L language array of the viewer
     * @return array{recipients:int}
     */
    /** What to look the contact up with on Sysfera Messenger: a Sysfera ID typed in the Messenger field (12@site.ru), else the e-mail address ('' = nothing to look up). */
    public function peerQuery(int $uid, int $id): string
    {
        $c = $this->mine($uid, $id); $m = strtolower(trim((string)$c['messenger'])); $e = trim((string)$c['email']);
        if (preg_match('/^\d{1,10}@[a-z0-9.-]+$/', $m)) return $m;
        return $e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL) ? strtolower($e) : '';
    }

    /** An e-mail to the contact, sent through the platform mailer in the name of the signed-in user. Logs "Email" on the card. */
    public function sendMail(int $uid, int $id, string $subject, string $body, string $fromEmail, string $fromName): array
    {
        $c = $this->mine($uid, $id); $to = trim((string)$c['email']);
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) throw new Err('email_invalid');
        $subject = self::clean(str_replace(["\r", "\n"], ' ', $subject), 190); $body = self::clean($body, 10000);
        if ($subject === '' || $body === '') throw new Err('mail_empty');
        $err = ($this->mail)([$to], $subject, $body, null, filter_var($fromEmail, FILTER_VALIDATE_EMAIL) ? [$fromEmail, $fromName] : null);
        if ($err !== null) throw new Err($err === 'not_configured' ? 'mail_not_configured' : ($err === 'no_recipient' ? 'email_invalid' : 'mail_failed'), $err, $err === 'not_configured' ? 409 : 502);
        return $this->addLog($uid, $id, 'email');
    }

    public function sendReport(int $uid, int $id, string $ownEmail, array $L, string $tz = 'UTC'): array
    {
        $c = self::view($this->mine($uid, $id), $this->logs($id));
        $to = [];
        foreach ([$ownEmail, $this->copyEmail($uid)] as $a) {
            $a = trim($a);
            if ($a !== '' && filter_var($a, FILTER_VALIDATE_EMAIL) && !in_array(strtolower($a), array_map('strtolower', $to), true)) $to[] = $a;
        }
        if (!$to) throw new Err('no_recipient');
        $r = Report::build($c, $L, ($this->now)(), $tz);
        $err = ($this->mail)($to, $r['subject'], $r['text'], $r['html']);
        if ($err !== null) throw new Err($err === 'not_configured' ? 'mail_not_configured' : ($err === 'no_recipient' ? 'no_recipient' : 'mail_failed'), $err, $err === 'not_configured' ? 409 : 502);
        return ['recipients' => count($to)];
    }
}
