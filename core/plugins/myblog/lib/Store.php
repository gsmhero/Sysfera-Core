<?php
declare(strict_types=1);
namespace SysferaBlog;

use Sysfera\Db;

/** Everything My Blog reads and writes. The tests use an in-memory implementation. */
interface Store
{
    public function post(int $id): ?array;
    public function insert(array $row): int;
    public function update(int $id, array $set): void;
    public function purge(int $id): void;
    /** All posts of one author (drafts and deleted ones too), newest first. */
    public function ownerPosts(int $owner): array;
    /** Published posts of active users, newest first, each with 'author'. @return array{0:array,1:int} rows, total */
    public function publicPosts(?int $owner, string $q, int $limit, int $offset): array;
    /** Posts still to be sent to the hub, oldest change first. */
    public function hubDirty(int $limit): array;
    public function userName(int $uid): ?string;

    /* hub site only */
    public function hubGet(string $domain, int $postId): ?array;
    public function hubPut(string $domain, int $postId, array $row): void;
    public function hubDelete(string $domain, int $uid, int $postId): void;
    public function hubNewToday(string $domain, int $uid): int;
    /** Visible posts, newest first, each with 'base' (the installation's registered address, '' if unknown). @return array{0:array,1:int} */
    public function hubList(string $q, int $limit, int $offset): array;
    /** Everything the hub holds, for the moderator (hidden ones first, then newest). */
    public function hubAll(int $limit): array;
    public function hubHide(int $id, bool $hidden): void;
}

final class DbStore implements Store
{
    private const COLS = ['owner_id', 'title', 'body', 'cover', 'cover_url', 'products', 'status', 'scope', 'origin', 'bb_post_id', 'bb_url', 'bb_updated', 'bb_dirty', 'hub_dirty', 'deleted', 'created_at', 'updated_at'];
    private const HUB = ['uid', 'author', 'shop', 'title', 'excerpt', 'cover', 'n_products', 'created'];
    private const POST = 'SELECT p.*, u.name AS author, u.status AS author_status FROM {p}myblog_posts p LEFT JOIN {p}users u ON u.id = p.owner_id';

    public function __construct(private Db $db) {}

    private static function like(string $q): string { return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%'; }

    public function post(int $id): ?array { return $this->db->row(self::POST . ' WHERE p.id = ?', [$id]); }

    public function insert(array $row): int
    {
        $r = array_intersect_key($row, array_flip(self::COLS));
        $this->db->q('INSERT INTO {p}myblog_posts (' . implode(',', array_keys($r)) . ') VALUES (' . implode(',', array_fill(0, count($r), '?')) . ')', array_values($r));
        return $this->db->insertId();
    }

    public function update(int $id, array $set): void
    {
        $r = array_intersect_key($set, array_flip(self::COLS));
        if ($r) $this->db->q('UPDATE {p}myblog_posts SET ' . implode(',', array_map(fn($k) => "$k = ?", array_keys($r))) . ' WHERE id = ?', [...array_values($r), $id]);
    }

    public function purge(int $id): void { $this->db->q('DELETE FROM {p}myblog_posts WHERE id = ?', [$id]); }

    public function ownerPosts(int $owner): array { return $this->db->rows(self::POST . ' WHERE p.owner_id = ? ORDER BY p.created_at DESC, p.id DESC', [$owner]); }

    public function publicPosts(?int $owner, string $q, int $limit, int $offset): array
    {
        $w = "p.status = 'published' AND p.deleted = 0 AND u.status = 'active'"; $a = [];
        if ($owner !== null) { $w .= ' AND p.owner_id = ?'; $a[] = $owner; }
        if ($q !== '') { $w .= ' AND (p.title LIKE ? OR p.body LIKE ? OR u.name LIKE ?)'; array_push($a, self::like($q), self::like($q), self::like($q)); }
        $from = "FROM {p}myblog_posts p JOIN {p}users u ON u.id = p.owner_id WHERE $w";
        return [$this->db->rows("SELECT p.*, u.name AS author $from ORDER BY p.created_at DESC, p.id DESC LIMIT " . max(1, $limit) . ' OFFSET ' . max(0, $offset), $a), (int)$this->db->val("SELECT COUNT(*) $from", $a)];
    }

    public function hubDirty(int $limit): array { return $this->db->rows(self::POST . ' WHERE p.hub_dirty = 1 ORDER BY p.updated_at, p.id LIMIT ' . max(1, $limit)); }

    public function userName(int $uid): ?string { $n = $this->db->val("SELECT name FROM {p}users WHERE id = ? AND status = 'active'", [$uid]); return $n === null ? null : (string)$n; }

    public function hubGet(string $domain, int $postId): ?array { return $this->db->row('SELECT * FROM {p}myblog_hub WHERE domain = ? AND post_id = ?', [$domain, $postId]); }

    public function hubPut(string $domain, int $postId, array $row): void
    {
        $r = array_intersect_key($row, array_flip(self::HUB));   // "hidden" is not in the list: publishing again never undoes the moderator
        $cols = array_keys($r);
        $this->db->q('INSERT INTO {p}myblog_hub (domain, post_id, ' . implode(',', $cols) . ') VALUES (?,?,' . implode(',', array_fill(0, count($cols), '?')) . ') ON DUPLICATE KEY UPDATE ' . implode(',', array_map(fn($k) => "$k = VALUES($k)", $cols)), [$domain, $postId, ...array_values($r)]);
    }

    public function hubDelete(string $domain, int $uid, int $postId): void { $this->db->q('DELETE FROM {p}myblog_hub WHERE domain = ? AND uid = ? AND post_id = ? AND hidden = 0', [$domain, $uid, $postId]); }   // a hidden post stays, so removing and publishing again cannot undo the moderator

    public function hubNewToday(string $domain, int $uid): int { return (int)$this->db->val('SELECT COUNT(*) FROM {p}myblog_hub WHERE domain = ? AND uid = ? AND received > (NOW() - INTERVAL 1 DAY)', [$domain, $uid]); }

    public function hubList(string $q, int $limit, int $offset): array
    {
        $w = 'h.hidden = 0'; $a = [];
        if ($q !== '') { $w .= ' AND (h.title LIKE ? OR h.excerpt LIKE ? OR h.author LIKE ? OR h.shop LIKE ?)'; $a = array_fill(0, 4, self::like($q)); }
        $rows = $this->db->rows("SELECT h.*, COALESCE(i.base, '') AS base FROM {p}myblog_hub h LEFT JOIN {p}msg_installs i ON i.domain = h.domain WHERE $w ORDER BY h.created DESC, h.id DESC LIMIT " . max(1, $limit) . ' OFFSET ' . max(0, $offset), $a);
        return [$rows, (int)$this->db->val("SELECT COUNT(*) FROM {p}myblog_hub h WHERE $w", $a)];
    }

    public function hubAll(int $limit): array { return $this->db->rows('SELECT * FROM {p}myblog_hub ORDER BY hidden DESC, id DESC LIMIT ' . max(1, $limit)); }
    public function hubHide(int $id, bool $hidden): void { $this->db->q('UPDATE {p}myblog_hub SET hidden = ? WHERE id = ?', [$hidden ? 1 : 0, $id]); }
}
