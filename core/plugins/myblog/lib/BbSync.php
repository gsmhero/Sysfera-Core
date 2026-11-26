<?php
declare(strict_types=1);
namespace SysferaBlog;

/**
 * Keeps one user's posts and the blog of their Basebase shop in step (contract: docs/BASEBASE-BLOG-API.md).
 * Local changes are sent first; then Basebase is read. A post changed here and not yet sent always wins over the Basebase copy.
 */
final class BbSync
{
    /**
     * @param \Closure(string $method, string $suffix, ?array $body):array $call request on /sellers/{id}<suffix> → ['code', 'data', 'error']
     * @param \Closure(string):string $coverUrl public https address of a cover stored here
     * @param string $site short fingerprint of this installation, part of external_id
     */
    public function __construct(private Store $st, private \Closure $call, private \Closure $coverUrl, private string $site, private \Closure $now) {}

    public static function siteId(string $domain): string { return substr(hash('sha256', strtolower($domain)), 0, 8); }
    public function externalId(int $postId): string { return 'sf-' . $this->site . '-' . $postId; }

    /** Basebase without the blog endpoints answers 404 "Unknown endpoint." */
    public static function unsupported(array $r): bool { return $r['code'] === 404 && stripos((string)$r['error'], 'unknown endpoint') !== false; }

    /** @return array{status:string,error:?string,pushed:int,pulled:int,removed:int} status: ok | unsupported | error */
    public function run(int $uid): array
    {
        $res = ['status' => 'ok', 'error' => null, 'pushed' => 0, 'pulled' => 0, 'removed' => 0];
        $stop = function (array $r) use (&$res): array { $res['status'] = self::unsupported($r) ? 'unsupported' : 'error'; $res['error'] = (string)$r['error']; return $res; };

        foreach ($this->st->ownerPosts($uid) as $p) {
            if (!$p['bb_dirty']) continue;
            $id = (int)$p['id']; $bb = (int)$p['bb_post_id'];
            if ($p['deleted']) {
                if ($bb) {
                    $r = ($this->call)('DELETE', '/posts/' . $bb, null);
                    if ($r['error'] !== null && ($r['code'] !== 404 || self::unsupported($r))) return $stop($r);
                }
                $this->st->update($id, ['bb_dirty' => 0]); Posts::purgeIfDone($this->st, $id); $res['pushed']++;
                continue;
            }
            $body = ['external_id' => $this->externalId($id), 'title' => (string)$p['title'], 'body' => (string)$p['body'], 'status' => (string)$p['status'], 'products' => Text::ids($p['products'])];
            if (!empty($p['cover'])) $body['cover_url'] = ($this->coverUrl)((string)$p['cover']);
            elseif (empty($p['cover_url'])) $body['remove_cover'] = true;
            $r = ($this->call)($bb ? 'PUT' : 'POST', $bb ? '/posts/' . $bb : '/posts', $body);
            if ($r['error'] !== null) {
                if (self::unsupported($r)) return $stop($r);
                if ($bb && $r['code'] === 404 && stripos((string)$r['error'], 'post not found') !== false) { $this->st->update($id, ['bb_post_id' => null, 'bb_url' => null]); continue; }   // sent anew next time
                if ($r['code'] === 422) { $res['status'] = 'error'; $res['error'] = (string)$r['error']; continue; }   // this post is refused; the others still go
                return $stop($r);
            }
            $new = (int)($r['data']['post_id'] ?? 0);
            if (!$new && !$bb) return $stop(['code' => 200, 'error' => 'bad_answer', 'data' => []]);
            $url = (string)($r['data']['url'] ?? '');
            $this->st->update($id, ['bb_post_id' => $new ?: $bb, 'bb_url' => Text::httpUrl($url) ? $url : $p['bb_url'], 'bb_updated' => Text::line((string)($r['data']['updated_at'] ?? ''), 40), 'bb_dirty' => 0]);
            $res['pushed']++;
        }

        $r = ($this->call)('GET', '/posts', null);
        if ($r['error'] !== null) return $stop($r);
        if (!is_array($r['data']['posts'] ?? null)) return $stop(['code' => 200, 'error' => 'bad_answer', 'data' => []]);
        $mine = $this->st->ownerPosts($uid); $byBb = []; $byExt = [];
        foreach ($mine as $p) { if ($p['bb_post_id']) $byBb[(int)$p['bb_post_id']] = $p; else $byExt[$this->externalId((int)$p['id'])] = $p; }
        $seen = [];
        foreach ($r['data']['posts'] as $x) {
            if (!is_array($x)) continue;
            $bb = (int)($x['post_id'] ?? 0); $title = Text::line((string)($x['title'] ?? ''), Text::TITLE_MAX);
            if ($bb < 1 || $title === '') continue;
            $seen[$bb] = 1;
            $p = $byBb[$bb] ?? $byExt[(string)($x['external_id'] ?? '')] ?? null;
            $upd = Text::line((string)($x['updated_at'] ?? ''), 40);
            if ($p && ($p['deleted'] || $p['bb_dirty'] || ($p['bb_post_id'] && $upd !== '' && $upd === (string)$p['bb_updated']))) continue;
            $url = (string)($x['url'] ?? ''); $cover = (string)($x['cover_url'] ?? '');
            $products = [];
            foreach ((array)($x['products'] ?? []) as $q) $products[] = is_array($q) ? (int)($q['product_id'] ?? 0) : (int)$q;
            $row = ['title' => $title, 'body' => Text::cut(trim(str_replace("\r\n", "\n", (string)($x['body'] ?? ''))), Text::BODY_MAX), 'status' => ($x['status'] ?? '') === 'draft' ? 'draft' : 'published',
                'products' => json_encode(Text::ids($products)), 'cover_url' => Text::basebaseUrl($cover) ? $cover : null, 'bb_post_id' => $bb, 'bb_url' => Text::httpUrl($url) ? $url : null, 'bb_updated' => $upd];
            $stamp = date('Y-m-d H:i:s', ($this->now)());
            if ($p) {
                $same = $row['title'] === $p['title'] && $row['body'] === (string)$p['body'] && $row['status'] === $p['status'] && $row['products'] === (string)$p['products'] && ($row['cover_url'] === null || $row['cover_url'] === $p['cover_url']);
                if ($same) { $this->st->update((int)$p['id'], ['bb_post_id' => $bb, 'bb_url' => $row['bb_url'], 'bb_updated' => $upd]); continue; }
                $this->st->update((int)$p['id'], $row + ['cover' => null, 'hub_dirty' => 1, 'updated_at' => $stamp]);
            } else {
                $created = strtotime((string)($x['created_at'] ?? '')) ?: ($this->now)();
                $this->st->insert($row + ['owner_id' => $uid, 'origin' => 'basebase', 'scope' => 'global', 'cover' => null, 'bb_dirty' => 0, 'hub_dirty' => 1, 'deleted' => 0, 'created_at' => date('Y-m-d H:i:s', $created), 'updated_at' => $stamp]);
            }
            $res['pulled']++;
        }
        if (($r['data']['complete'] ?? false) === true) foreach ($mine as $p) {   // gone from Basebase: only when the answer is the whole list
            if ($p['deleted'] || $p['bb_dirty'] || !$p['bb_post_id'] || isset($seen[(int)$p['bb_post_id']])) continue;
            $this->st->update((int)$p['id'], ['deleted' => 1, 'bb_post_id' => null, 'hub_dirty' => 1, 'updated_at' => date('Y-m-d H:i:s', ($this->now)())]); $res['removed']++;
        }
        return $res;
    }

    /** The shop's product sets, for the public profile. null = Basebase does not offer them (yet). */
    public function sets(): ?array
    {
        $r = ($this->call)('GET', '/sets', null);
        if ($r['error'] !== null || !is_array($r['data']['sets'] ?? null)) return null;
        $out = [];
        foreach ($r['data']['sets'] as $s) {
            if (!is_array($s)) continue;
            $url = (string)($s['url'] ?? ''); $title = Text::line((string)($s['title'] ?? ''), 190);
            if ($title === '' || !Text::httpUrl($url)) continue;
            $out[] = ['id' => (int)($s['set_id'] ?? 0), 'title' => $title, 'items' => max(0, (int)($s['items'] ?? 0)), 'sum' => max(0.0, (float)($s['sum'] ?? 0)), 'url' => $url];
        }
        return $out;
    }
}
