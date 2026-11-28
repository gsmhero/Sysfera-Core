<?php
declare(strict_types=1);
namespace SysferaHr;

/**
 * Vacancies shared between all Sysfera sites through the Messenger hub.
 * An installation sends the FULL list of its public vacancies (Recruit::shared); the hub replaces what it had for that domain,
 * so a vacancy that was closed, archived or sent back to draft disappears with the next send.
 * The domain is the one the hub has verified, and every address in the feed is built on the hub from the registered address.
 */
final class VacancyHub
{
    public const PER_SITE = 50, FEED = 20, KEEP_DAYS = 30;

    public function __construct(private Store $st, private \Closure $now) {}

    public function replace(string $domain, array $in): array
    {
        $rows = [];
        foreach (is_array($in['items'] ?? null) ? $in['items'] : [] as $i) {
            if (!is_array($i) || !($c = VacancyFeed::fields($i))) continue;
            $now = ($this->now)(); $t = strtotime($c['published_at']) ?: $now;
            $rows[$c['uuid']] = ['domain' => $domain, 'uuid' => $c['uuid'], 'title' => $c['title'], 'data' => json_encode(['salary' => $c['salary'], 'excerpt' => $c['excerpt'], 'details' => $c['details']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'published' => date('Y-m-d H:i:s', min($t, $now)), 'received' => date('Y-m-d H:i:s', $now)];   // a date in the future would pin a vacancy to the top
            if (count($rows) >= self::PER_SITE) break;
        }
        $this->st->atomic(function () use ($domain, $rows) {
            foreach ($this->st->find('hub', ['domain' => $domain]) as $old) $this->st->delete('hub', (int)$old['id']);
            foreach ($rows as $r) $this->st->insert('hub', $r);
        });
        return ['ok' => true, 'count' => count($rows)];
    }

    /** The newest vacancies of the OTHER sites. A site that has not sent its list for KEEP_DAYS is left out. @param \Closure(string):string $base address of an installation */
    public function feed(string $caller, \Closure $base): array
    {
        $items = []; $since = date('Y-m-d H:i:s', ($this->now)() - self::KEEP_DAYS * 86400);
        foreach ($this->st->find('hub', [], 'published DESC') as $r) {
            if ($r['domain'] === $caller || $r['received'] < $since) continue;
            $url = rtrim($base((string)$r['domain']), '/') . '/p/hr/v/' . $r['uuid']; if (!VacancyFeed::httpUrl($url)) continue;
            $d = json_decode((string)$r['data'], true);
            $items[] = ['uuid' => $r['uuid'], 'title' => $r['title'], 'url' => $url, 'domain' => $r['domain'], 'published_at' => $r['published']] + (is_array($d) ? $d : []);
            if (count($items) >= self::FEED) break;
        }
        return ['items' => $items];
    }
}

/** What travels between the sites, checked on both ends: only these fields exist. */
final class VacancyFeed
{
    public const FACTS = ['work_format' => 20, 'location' => 200, 'schedule' => 20, 'hours' => 100, 'employment' => 20];
    public const EXCERPT = 240;

    public static function httpUrl(string $u): bool { return (bool)preg_match('~^https?://[^\s<>"\'\\\\]+$~D', $u); }
    public static function text(mixed $v, int $max): string
    {
        if (!is_scalar($v)) return ''; $s = trim((string)preg_replace('/[\x00-\x20\x7F]+/', ' ', (string)$v));
        return preg_match('/^.{0,' . $max . '}/us', $s, $m) ? rtrim($m[0]) : '';   // '' for invalid UTF-8
    }
    /** the shared fields of one vacancy, or null when it has no valid id or title */
    public static function fields(array $i): ?array
    {
        $uuid = self::text($i['uuid'] ?? null, 36); $title = self::text($i['title'] ?? null, 160);
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D', $uuid) || $title === '') return null;
        $d = []; foreach (self::FACTS as $k => $max) if (($x = self::text(is_array($i['details'] ?? null) ? ($i['details'][$k] ?? null) : null, $max)) !== '') $d[$k] = $x;
        return ['uuid' => $uuid, 'title' => $title, 'salary' => self::text($i['salary'] ?? null, 60), 'excerpt' => self::text($i['excerpt'] ?? null, self::EXCERPT), 'details' => $d, 'published_at' => self::text($i['published_at'] ?? null, 19)];
    }
    /** a feed item received from the hub, with anything unexpected removed */
    public static function clean(mixed $i): ?array
    {
        if (!is_array($i) || !($c = self::fields($i))) return null;
        $url = is_scalar($i['url'] ?? null) ? (string)$i['url'] : ''; if (!self::httpUrl($url)) return null;
        return $c + ['url' => $url, 'domain' => self::text($i['domain'] ?? null, 190)];
    }
}
