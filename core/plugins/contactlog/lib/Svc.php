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

    /** Cards of the user with their logs, newest first. */
    public function cards(int $uid): array
    {
        if ($uid <= 0) return [];
        $cards = $this->st->find('cards', ['user_id' => $uid], 'created_at DESC', self::MAX_CARDS);
        $logs = [];
        if ($cards) foreach ($this->st->find('logs', ['card_id' => array_map(fn($c) => (int)$c['id'], $cards)], 'id', 500000) as $l) $logs[(int)$l['card_id']][] = self::logView($l);
        return array_map(fn($c) => self::view($c, $logs[(int)$c['id']] ?? []), $cards);
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
        $next = self::clean($in['next_call'] ?? '', 10);
        if ($next !== '') { $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $next); if (!$d || $d->format('Y-m-d') !== $next) throw new Err('date_invalid'); }
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
