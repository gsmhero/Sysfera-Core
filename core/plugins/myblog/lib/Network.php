<?php
declare(strict_types=1);
namespace SysferaBlog;

/**
 * The two ends of the shared feed.
 * On the hub: publish / remove / feed (called through HubServer, so the caller's domain and user are already verified).
 * Every address in the feed is built here from the installation's registered address, never taken from the caller.
 */
final class BlogHub
{
    public const NEW_PER_DAY = 20, PER_PAGE = 24;

    public function __construct(private Store $st, private \Closure $now) {}

    /** @throws BlogError */
    public function publish(string $domain, int $uid, array $in): array
    {
        $str = fn(string $k): string => is_scalar($in[$k] ?? null) ? (string)$in[$k] : '';
        $pid = (int)($in['post_id'] ?? 0);
        $title = Text::line($str('title'), Text::TITLE_MAX);
        if ($uid < 1 || $pid < 1 || $title === '') throw new BlogError('bad_post');
        $old = $this->st->hubGet($domain, $pid);
        if (!$old && $this->st->hubNewToday($domain, $uid) >= self::NEW_PER_DAY) throw new BlogError('post_rate');
        $cover = $str('cover');
        if ($cover !== '' && !Text::coverName($cover) && !Text::basebaseUrl($cover)) $cover = '';
        $now = ($this->now)(); $t = strtotime($str('created')) ?: $now;
        $this->st->hubPut($domain, $pid, ['uid' => $uid, 'author' => Text::line($str('author'), 80), 'shop' => Text::line($str('shop'), 120), 'title' => $title, 'excerpt' => Text::cut(Text::excerpt($str('excerpt')), 400),
            'cover' => $cover, 'n_products' => max(0, min(Text::MAX_PRODUCTS, (int)($in['products'] ?? 0))), 'created' => date('Y-m-d H:i:s', min($t, $now))]);   // a date in the future would pin a post to the top
        return ['ok' => true];
    }

    public function remove(string $domain, int $uid, int $postId): array { $this->st->hubDelete($domain, $uid, $postId); return ['ok' => true]; }

    /** @param \Closure(string):string $ownBase address of an installation the hub has no registration for (the hub site itself) */
    public function feed(array $q, \Closure $ownBase): array
    {
        $per = max(1, min(48, (int)($q['per'] ?? self::PER_PAGE))); $page = max(1, min(1000, (int)($q['page'] ?? 1)));
        [$rows, $total] = $this->st->hubList(Text::line((string)($q['q'] ?? ''), 100), $per, ($page - 1) * $per);
        $items = [];
        foreach ($rows as $r) {
            $base = rtrim((string)($r['base'] ?? ''), '/'); if ($base === '') $base = rtrim($ownBase((string)$r['domain']), '/');
            if (!Text::httpUrl($base)) continue;
            $c = (string)$r['cover'];
            $items[] = ['id' => (int)$r['post_id'], 'uid' => (int)$r['uid'], 'title' => (string)$r['title'], 'excerpt' => (string)$r['excerpt'], 'url' => $base . '/p/myblog/post/' . (int)$r['post_id'],
                'cover' => Text::coverName($c) ? $base . '/p/myblog/cover/' . $c : (Text::basebaseUrl($c) ? $c : ''), 'author' => (string)($r['shop'] !== '' ? $r['shop'] : $r['author']),
                'author_url' => $base . '/p/myblog/u/' . (int)$r['uid'], 'domain' => (string)$r['domain'], 'date' => substr((string)$r['created'], 0, 10), 'products' => (int)$r['n_products']];
        }
        return ['items' => $items, 'total' => $total, 'page' => $page, 'per' => $per, 'pages' => max(1, (int)ceil($total / $per))];
    }
}

/** On an installation: send changed posts to the hub and read the shared feed. */
final class Network
{
    /** @param \Closure(string $method, string $op, array $query, ?array $body, string $asUid):array $call a signed hub call (see SysferaMsg\Client::call) */
    public function __construct(private Store $st, private \Closure $call) {}

    /** Sends the waiting changes. @param \Closure(int):string $shop shop name of a user ('' when none) @return int how many the hub accepted */
    public function push(\Closure $shop, int $limit = 10): int
    {
        $done = 0; $stop = [];
        foreach ($this->st->hubDirty($limit) as $p) {
            $id = (int)$p['id']; $uid = (int)$p['owner_id'];
            if (isset($stop[$uid])) continue;
            $public = !$p['deleted'] && $p['status'] === 'published' && $p['scope'] === 'global' && ($p['author_status'] ?? '') === 'active';
            $cover = Posts::picture($p) ?: (string)($p['cover_url'] ?? '');
            $r = ($this->call)('POST', $public ? 'blog.publish' : 'blog.remove', [], $public
                ? ['post_id' => $id, 'title' => (string)$p['title'], 'excerpt' => Text::excerpt((string)$p['body']), 'author' => (string)($p['author'] ?? ''), 'shop' => $shop($uid), 'cover' => $cover, 'products' => count(Text::ids($p['products'])), 'created' => date('c', strtotime((string)$p['created_at']) ?: time())]
                : ['post_id' => $id], (string)$uid);
            if (!$r['ok']) {
                if ($r['error'] === 'post_rate') { $stop[$uid] = 1; continue; }   // tomorrow
                if ($r['error'] === 'bad_post') { $this->st->update($id, ['hub_dirty' => 0]); continue; }
                break;   // hub unreachable or without the blog: try again later
            }
            $this->st->update($id, ['hub_dirty' => 0]); $done++;
            Posts::purgeIfDone($this->st, $id);
        }
        return $done;
    }

    /** One page of the shared feed, with anything unexpected removed. null = the hub cannot be reached. */
    public function feed(int $page = 1, int $per = BlogHub::PER_PAGE, string $q = ''): ?array
    {
        $r = ($this->call)('GET', 'blog.feed', ['page' => $page, 'per' => $per, 'q' => $q], null, '0');
        if (!$r['ok'] || !is_array($r['data']['items'] ?? null)) return null;
        $items = [];
        foreach ($r['data']['items'] as $i) if (is_array($i) && ($c = self::clean($i))) $items[] = $c;
        return ['items' => $items, 'total' => (int)($r['data']['total'] ?? 0), 'page' => max(1, (int)($r['data']['page'] ?? 1)), 'pages' => max(1, (int)($r['data']['pages'] ?? 1))];
    }

    public static function clean(array $i): ?array
    {
        $s = fn(string $k): string => is_scalar($i[$k] ?? null) ? (string)$i[$k] : '';
        $url = fn(string $k): string => Text::httpUrl($s($k)) ? $s($k) : '';
        if ($url('url') === '' || $s('title') === '') return null;
        return ['id' => (int)$s('id'), 'uid' => (int)$s('uid'), 'title' => Text::cut($s('title'), Text::TITLE_MAX), 'excerpt' => Text::cut($s('excerpt'), 400), 'url' => $url('url'), 'cover' => $url('cover'),
            'author' => Text::cut($s('author'), 120), 'author_url' => $url('author_url'), 'domain' => Text::cut($s('domain'), 190), 'date' => substr($s('date'), 0, 10), 'products' => max(0, (int)$s('products'))];
    }
}
