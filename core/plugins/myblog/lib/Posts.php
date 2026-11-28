<?php
declare(strict_types=1);
namespace SysferaBlog;

final class BlogError extends \RuntimeException
{
    public function __construct(public readonly string $key) { parent::__construct($key); }
}

/** Writing and showing the posts of this site. */
final class Posts
{
    public function __construct(private Store $st, private \Closure $now) {}

    private function stamp(): string { return date('Y-m-d H:i:s', ($this->now)()); }

    /** The author, or an administrator of the site, may change a post. */
    public function editable(int $uid, bool $admin, int $id): array
    {
        $p = $this->st->post($id);
        if (!$p || $p['deleted'] || (!$admin && (int)$p['owner_id'] !== $uid)) throw new BlogError('no_post');
        return $p;
    }

    /** May this visitor read the post? Published posts of active authors are public; drafts are for the author and administrators. */
    public static function readable(?array $p, int $uid, bool $admin): bool
    {
        if (!$p || $p['deleted']) return false;
        if ($admin || ($uid > 0 && (int)$p['owner_id'] === $uid)) return true;
        return $p['status'] === 'published' && ($p['author_status'] ?? '') === 'active';
    }

    /**
     * Creates or updates a post.
     * @param array $in title, body, status, scope, products[], remove_cover, videos (one link per line), remove_images[]
     * @param list<int> $own Basebase product numbers of the post's author (products already attached stay allowed)
     * @param ?string $cover file name of a newly uploaded picture
     * @param list<string> $added file names of newly uploaded gallery pictures
     */
    public function save(int $uid, bool $admin, int $id, array $in, array $own, ?string $cover = null, array $added = []): int
    {
        $old = $id ? $this->editable($uid, $admin, $id) : null;
        $str = fn(string $k): string => is_string($in[$k] ?? null) ? $in[$k] : '';
        $title = Text::line($str('title'), Text::TITLE_MAX + 1);
        $body = trim(str_replace("\r\n", "\n", $str('body')));
        if ($title === '') throw new BlogError('need_title');
        if (Text::len($title) > Text::TITLE_MAX) throw new BlogError('long_title');
        if (Text::len($body) > Text::BODY_MAX) throw new BlogError('long_body');
        $allowed = array_merge($own, $old ? Text::ids($old['products']) : []);
        $products = array_values(array_intersect(Text::ids($in['products'] ?? []), $allowed));
        $row = ['title' => $title, 'body' => $body, 'products' => json_encode($products),
            'status' => $str('status') === 'draft' ? 'draft' : 'published', 'scope' => $str('scope') === 'local' ? 'local' : 'global',
            'bb_dirty' => 1, 'hub_dirty' => 1, 'updated_at' => $this->stamp()];
        $gone = is_array($in['remove_images'] ?? null) ? $in['remove_images'] : [];
        $images = array_merge(array_values(array_diff($old ? Media::images($old['images'] ?? null) : [], $gone)), Media::images($added));
        if (count($images) > Media::MAX_IMAGES) throw new BlogError('many_images');
        $row['images'] = json_encode($images);
        if (array_key_exists('videos', $in)) {
            $videos = Media::parseLines($str('videos'));
            if ($videos === null) throw new BlogError('bad_video');
            if (count($videos) > Media::MAX_VIDEOS) throw new BlogError('many_videos');
            $row['videos'] = json_encode($videos, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        if ($cover !== null && Text::coverName($cover)) { $row['cover'] = $cover; $row['cover_url'] = null; }
        elseif (!empty($in['remove_cover'])) { $row['cover'] = null; $row['cover_url'] = null; }
        if ($old) { $this->st->update($id, $row); return $id; }
        return $this->st->insert($row + ['owner_id' => $uid, 'origin' => 'sysfera', 'deleted' => 0, 'cover' => null, 'cover_url' => null, 'bb_post_id' => null, 'bb_url' => null, 'bb_updated' => null, 'created_at' => $this->stamp()]);
    }

    /** Marks the post deleted; it is removed for good once the hub and Basebase have been told. */
    public function delete(int $uid, bool $admin, int $id): void
    {
        $this->editable($uid, $admin, $id);
        $this->st->update($id, ['deleted' => 1, 'hub_dirty' => 1, 'bb_dirty' => 1, 'updated_at' => $this->stamp()]);
        self::purgeIfDone($this->st, $id);
    }

    /** A deleted post nobody else has to hear about any more can go. */
    public static function purgeIfDone(Store $st, int $id): void
    {
        $p = $st->post($id);
        if (!$p || !$p['deleted']) return;
        if (!$p['bb_post_id'] && $p['bb_dirty']) { $st->update($id, ['bb_dirty' => 0]); $p['bb_dirty'] = 0; }   // never reached Basebase
        if (!$p['bb_dirty'] && !$p['hub_dirty']) $st->purge($id);
    }

    /** The picture that stands for a post: its cover, else the first gallery picture. '' when it has none stored here. */
    public static function picture(array $p): string
    {
        if (!empty($p['cover'])) return (string)$p['cover'];
        return empty($p['cover_url']) ? (Media::images($p['images'] ?? null)[0] ?? '') : '';
    }

    /** A post of this site as a card. @param \Closure(string):string $url path inside the site => address */
    public static function card(array $p, \Closure $url): array
    {
        $own = self::picture($p);
        $cover = $own !== '' ? $url('/p/myblog/cover/' . $own) : (string)($p['cover_url'] ?? '');
        return ['id' => (int)$p['id'], 'uid' => (int)$p['owner_id'], 'title' => (string)$p['title'], 'excerpt' => Text::excerpt((string)$p['body']), 'url' => $url('/p/myblog/post/' . (int)$p['id']), 'cover' => $cover,
            'author' => (string)($p['author'] ?? ''), 'author_url' => $url('/p/myblog/u/' . (int)$p['owner_id']), 'domain' => '', 'date' => substr((string)$p['created_at'], 0, 10),
            'products' => count(Text::ids($p['products'])), 'status' => (string)$p['status'], 'scope' => (string)$p['scope']];
    }
}
