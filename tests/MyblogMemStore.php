<?php
declare(strict_types=1);

use SysferaBlog\Store;

/** In-memory Store for the My Blog tests (same behaviour as DbStore). */
final class MyblogMemStore implements Store
{
    public array $posts = [], $hub = [], $users = [1 => ['name' => 'Olga <b>', 'status' => 'active'], 2 => ['name' => 'Ivan', 'status' => 'active']], $bases = [];
    private int $seq = 0, $hseq = 0;

    private function join(array $p): array { $u = $this->users[$p['owner_id']] ?? null; return $p + ['author' => $u['name'] ?? null, 'author_status' => $u['status'] ?? null]; }
    private function newest(array $r): array { usort($r, fn($a, $b) => [$b['created_at'], $b['id']] <=> [$a['created_at'], $a['id']]); return $r; }

    public function post(int $id): ?array { return isset($this->posts[$id]) ? $this->join($this->posts[$id]) : null; }
    public function insert(array $row): int { $id = ++$this->seq; $this->posts[$id] = ['id' => $id] + $row; return $id; }
    public function update(int $id, array $set): void { if (isset($this->posts[$id])) $this->posts[$id] = array_merge($this->posts[$id], $set); }
    public function purge(int $id): void { unset($this->posts[$id]); }
    public function ownerPosts(int $owner): array { return $this->newest(array_map(fn($p) => $this->join($p), array_values(array_filter($this->posts, fn($p) => $p['owner_id'] === $owner)))); }
    public function publicPosts(?int $owner, string $q, int $limit, int $offset): array
    {
        $r = array_filter(array_map(fn($p) => $this->join($p), array_values($this->posts)), fn($p) => $p['status'] === 'published' && !$p['deleted'] && $p['author_status'] === 'active' && ($owner === null || $p['owner_id'] === $owner)
            && ($q === '' || stripos($p['title'] . ' ' . $p['body'] . ' ' . $p['author'], $q) !== false));
        $r = $this->newest(array_values($r));
        return [array_slice($r, $offset, $limit), count($r)];
    }
    public function hubDirty(int $limit): array { return array_slice(array_values(array_map(fn($p) => $this->join($p), array_filter($this->posts, fn($p) => !empty($p['hub_dirty'])))), 0, $limit); }
    public function userName(int $uid): ?string { $u = $this->users[$uid] ?? null; return $u && $u['status'] === 'active' ? $u['name'] : null; }

    public function publicAuthors(string $q, int $limit): array
    {
        $out = [];
        foreach ($this->publicPosts(null, '', 1000, 0)[0] as $p) { if (stripos($p['author'], $q) === false) continue; $u = $p['owner_id']; $out[$u] ??= ['uid' => $u, 'name' => $p['author'], 'posts' => 0]; $out[$u]['posts']++; }
        return array_slice(array_values($out), 0, $limit);
    }
    public function hubGet(string $domain, int $postId): ?array { return $this->hub["$domain#$postId"] ?? null; }
    public function hubPut(string $domain, int $postId, array $row): void { $k = "$domain#$postId"; $this->hub[$k] = array_merge($this->hub[$k] ?? ['id' => ++$this->hseq, 'domain' => $domain, 'post_id' => $postId, 'hidden' => 0, 'new' => true], $row); }
    public function hubDelete(string $domain, int $uid, int $postId): void { $k = "$domain#$postId"; if (isset($this->hub[$k]) && $this->hub[$k]['uid'] === $uid && !$this->hub[$k]['hidden']) unset($this->hub[$k]); }
    public function hubNewToday(string $domain, int $uid): int { return count(array_filter($this->hub, fn($h) => $h['domain'] === $domain && $h['uid'] === $uid)); }
    public function hubList(string $q, int $limit, int $offset): array
    {
        $r = array_values(array_filter($this->hub, fn($h) => !$h['hidden'] && ($q === '' || stripos($h['title'] . ' ' . $h['excerpt'] . ' ' . $h['author'] . ' ' . $h['shop'], $q) !== false)));
        usort($r, fn($a, $b) => [$b['created'], $b['id']] <=> [$a['created'], $a['id']]);
        return [array_map(fn($h) => $h + ['base' => $this->bases[$h['domain']] ?? ''], array_slice($r, $offset, $limit)), count($r)];
    }
    public function hubAll(int $limit): array { return array_slice(array_values($this->hub), 0, $limit); }
    public function hubHide(int $id, bool $hidden): void { foreach ($this->hub as &$h) if ($h['id'] === $id) $h['hidden'] = $hidden ? 1 : 0; }
}
