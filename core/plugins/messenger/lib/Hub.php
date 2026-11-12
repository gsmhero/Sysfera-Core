<?php
declare(strict_types=1);
namespace SysferaMsg;

use Sysfera\Db;

final class HubError extends \RuntimeException
{
    public function __construct(public readonly string $key, public readonly int $status = 400) { parent::__construct($key); }
}

/** Who is calling and what they sent. */
final class Ctx
{
    /** @param array<string,string> $q query  @param array $in JSON body */
    public function __construct(public readonly string $domain, public readonly string $uid, public readonly array $q = [], public readonly array $in = [], public readonly string $raw = '')
    {
    }
    public function sid(): string { return $this->uid . '@' . $this->domain; }
}

/**
 * The hub: runs on core.sysfera.ru (setting "hub_mode"). It keeps every installation's users, messages and files, and the
 * components built on it (Activity) add their own operations with HubServer::register().
 * Installations are trusted for the users of THEIR OWN domain only (ids look like 12@shop.example.ru).
 */
final class HubServer
{
    /** @var array<string,callable(Ctx,Db,HubServer):array> */
    private static array $ops = [];
    public static function register(string $op, callable $fn, bool $site = false): void { self::$ops[$op] = $fn; if ($site) self::$site[$op] = true; }
    /** @var array<string,bool> */
    private static array $site = [];

    public function __construct(private Db $db, private string $storage) {}

    public function db(): Db { return $this->db; }

    /** Runs one signed-in operation. @return array result (JSON-able); a file download returns ['_file' => [...]] */
    public function dispatch(string $op, Ctx $c): array
    {
        $own = ['me', 'search', 'send', 'upload', 'inbox', 'thread', 'file', 'block', 'unread'];
        if (in_array($op, $own, true)) { if ($c->uid === '0') throw new HubError('bad_user', 400); return $this->{'op' . ucfirst($op)}($c); }
        if (isset(self::$ops[$op])) {
            // user "0" = the installation itself (read-only public data, e.g. the widget feed); only operations marked with registerSite() allow it
            if ($c->uid === '0') { if (!isset(self::$site[$op])) throw new HubError('bad_user', 400); }
            else $this->touch($c, null);
            return (self::$ops[$op])($c, $this->db, $this);
        }
        throw new HubError('unknown_op', 404);
    }

    /* ---------------- installations ---------------- */

    /** Registers (or re-keys) an installation after its domain has proved itself. Returns the new shared key. */
    public function enroll(string $domain, string $base): string
    {
        $key = bin2hex(random_bytes(32));
        $this->db->q('INSERT INTO {p}msg_installs (domain, secret, base) VALUES (?,?,?) ON DUPLICATE KEY UPDATE secret = VALUES(secret), base = VALUES(base), enrolled_at = NOW()', [$domain, $key, mb_substr($base, 0, 300)]);
        return $key;
    }
    public function installKey(string $domain): string { return (string)$this->db->val('SELECT secret FROM {p}msg_installs WHERE domain = ?', [$domain]); }
    public function lastEnroll(string $domain): int { return (int)strtotime((string)$this->db->val('SELECT enrolled_at FROM {p}msg_installs WHERE domain = ?', [$domain]) ?: '0'); }

    /** Make sure the caller has a profile (activity and chat need a name to show). */
    private function touch(Ctx $c, ?array $profile): void
    {
        if (!preg_match('/^\d{1,10}$/', $c->uid)) throw new HubError('bad_user', 400);
        $name = Text::name((string)($profile['name'] ?? ''));
        $this->db->q('INSERT INTO {p}msg_users (sid, domain, uid, name, last_seen) VALUES (?,?,?,?,NOW())
            ON DUPLICATE KEY UPDATE last_seen = NOW()' . ($name !== '' ? ', name = VALUES(name)' : ''), [$c->sid(), $c->domain, (int)$c->uid, $name !== '' ? $name : 'User ' . $c->uid]);
    }

    public function userName(string $sid): ?string { $n = $this->db->val('SELECT name FROM {p}msg_users WHERE sid = ?', [$sid]); return $n === null || $n === false ? null : (string)$n; }

    /* ---------------- operations ---------------- */

    private function opMe(Ctx $c): array
    {
        $this->touch($c, $c->in);
        if (isset($c->in['email']) && is_string($c->in['email']) && $c->in['email'] !== '') $this->db->q('UPDATE {p}msg_users SET email_hash = ? WHERE sid = ?', [hash('sha256', strtolower(trim($c->in['email']))), $c->sid()]);
        if (array_key_exists('discoverable', $c->in)) $this->db->q('UPDATE {p}msg_users SET discoverable = ? WHERE sid = ?', [!empty($c->in['discoverable']) ? 1 : 0, $c->sid()]);
        $r = $this->db->row('SELECT discoverable FROM {p}msg_users WHERE sid = ?', [$c->sid()]);
        return ['sid' => $c->sid(), 'discoverable' => (int)($r['discoverable'] ?? 0), 'unread' => $this->unreadCount($c->sid())];
    }

    private function opSearch(Ctx $c): array
    {
        $this->touch($c, null);
        $q = trim((string)($c->q['q'] ?? ''));
        if ($q === '') return ['users' => []];
        $me = $c->sid();
        if (preg_match('/^\d{1,10}@[a-z0-9.-]+$/i', $q)) {   // exact Sysfera ID always resolves
            $rows = $this->db->rows('SELECT sid, name FROM {p}msg_users WHERE sid = ? AND sid <> ?', [strtolower($q), $me]);
        } elseif (str_contains($q, '@')) {                    // exact e-mail, only people who allowed it
            $rows = $this->db->rows('SELECT sid, name FROM {p}msg_users WHERE email_hash = ? AND discoverable = 1 AND sid <> ? LIMIT 20', [hash('sha256', strtolower($q)), $me]);
        } elseif ((function_exists('mb_strlen') ? mb_strlen($q) : strlen($q)) >= 3) {   // name prefix, only people who allowed it
            $rows = $this->db->rows('SELECT sid, name FROM {p}msg_users WHERE name LIKE ? AND discoverable = 1 AND sid <> ? ORDER BY name LIMIT 20', [str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%', $me]);
        } else { $rows = []; }
        return ['users' => array_map(fn($r) => ['sid' => $r['sid'], 'name' => $r['name']], $rows)];
    }

    private function opSend(Ctx $c): array
    {
        $this->touch($c, null);
        $me = $c->sid(); $to = strtolower(trim((string)($c->in['to'] ?? ''))); $text = trim((string)($c->in['text'] ?? '')); $file = (string)($c->in['file'] ?? '');
        if ($text === '' && $file === '') throw new HubError('empty', 400);
        if (strlen($text) > 6000 || (function_exists('mb_strlen') && mb_strlen($text) > 2000)) throw new HubError('too_long', 400);
        if ($to === $me || !$this->userName($to)) throw new HubError('no_user', 404);
        if ($this->db->val('SELECT 1 FROM {p}msg_blocks WHERE sid = ? AND blocked_sid = ?', [$to, $me])) throw new HubError('blocked', 403);
        if ((int)$this->db->val('SELECT COUNT(*) FROM {p}msg_messages WHERE from_sid = ? AND created > (NOW() - INTERVAL 1 MINUTE)', [$me]) >= 30) throw new HubError('rate', 429);
        if ($file !== '') {
            if (!$this->db->val('SELECT 1 FROM {p}msg_files WHERE id = ? AND owner_sid = ? AND attached = 0', [$file, $me])) throw new HubError('bad_file', 400);
        }
        $this->db->q('INSERT INTO {p}msg_messages (from_sid, to_sid, body, file_id) VALUES (?,?,?,?)', [$me, $to, $text, $file !== '' ? $file : null]);
        $id = $this->db->insertId();
        if ($file !== '') $this->db->q('UPDATE {p}msg_files SET attached = 1 WHERE id = ?', [$file]);
        return ['id' => $id];
    }

    private function opUpload(Ctx $c): array
    {
        $this->touch($c, null);
        $me = $c->sid(); $size = strlen($c->raw);
        if ($size < 1) throw new HubError('empty', 400);
        if ($size > Text::MAX_FILE) throw new HubError('file_big', 413);
        $f = Text::file((string)($c->q['name'] ?? ''));
        if (!$f) throw new HubError('file_type', 415);
        if ((int)$this->db->val('SELECT COUNT(*) FROM {p}msg_files WHERE owner_sid = ? AND created > (NOW() - INTERVAL 1 HOUR)', [$me]) >= 30) throw new HubError('rate', 429);
        $id = bin2hex(random_bytes(16));
        if (!is_dir($this->storage) && !@mkdir($this->storage, 0755, true)) throw new HubError('storage', 500);
        if (@file_put_contents($this->storage . '/' . $id . '.bin', $c->raw) === false) throw new HubError('storage', 500);
        $this->db->q('INSERT INTO {p}msg_files (id, owner_sid, name, mime, size) VALUES (?,?,?,?,?)', [$id, $me, $f[0], $f[1], $size]);
        return ['file' => $id, 'name' => $f[0], 'size' => $size];
    }

    private function opInbox(Ctx $c): array
    {
        $this->touch($c, null);
        $me = $c->sid();
        $rows = $this->db->rows('SELECT IF(from_sid = ?, to_sid, from_sid) AS peer, MAX(id) AS last_id, SUM(to_sid = ? AND read_at IS NULL) AS unread
            FROM {p}msg_messages WHERE from_sid = ? OR to_sid = ? GROUP BY peer ORDER BY last_id DESC LIMIT 100', [$me, $me, $me, $me]);
        $out = [];
        foreach ($rows as $r) {
            $m = $this->db->row('SELECT body, file_id, created, from_sid FROM {p}msg_messages WHERE id = ?', [(int)$r['last_id']]);
            $out[] = ['sid' => $r['peer'], 'name' => $this->userName($r['peer']) ?? $r['peer'], 'unread' => (int)$r['unread'],
                'last' => mb_substr((string)($m['body'] ?? ''), 0, 90) ?: ($m['file_id'] ? '📎' : ''), 'at' => $m['created'] ?? '', 'mine' => ($m['from_sid'] ?? '') === $me];
        }
        return ['chats' => $out, 'blocked' => array_column($this->db->rows('SELECT blocked_sid FROM {p}msg_blocks WHERE sid = ?', [$me]), 'blocked_sid')];
    }

    private function opThread(Ctx $c): array
    {
        $this->touch($c, null);
        $me = $c->sid(); $with = strtolower(trim((string)($c->q['with'] ?? ''))); $after = (int)($c->q['after'] ?? 0);
        $name = $this->userName($with);
        if ($name === null) throw new HubError('no_user', 404);
        $rows = $this->db->rows('SELECT m.id, m.from_sid, m.body, m.created, m.file_id, f.name AS fname, f.size AS fsize, f.mime AS fmime
            FROM {p}msg_messages m LEFT JOIN {p}msg_files f ON f.id = m.file_id
            WHERE ((m.from_sid = ? AND m.to_sid = ?) OR (m.from_sid = ? AND m.to_sid = ?)) AND m.id > ? ORDER BY m.id DESC LIMIT 200', [$me, $with, $with, $me, $after]);
        $rows = array_reverse($rows);
        $this->db->q('UPDATE {p}msg_messages SET read_at = NOW() WHERE to_sid = ? AND from_sid = ? AND read_at IS NULL', [$me, $with]);
        return ['peer' => ['sid' => $with, 'name' => $name], 'messages' => array_map(fn($r) => [
            'id' => (int)$r['id'], 'mine' => $r['from_sid'] === $me, 'text' => (string)$r['body'], 'at' => $r['created'],
            'file' => $r['file_id'] ? ['id' => $r['file_id'], 'name' => $r['fname'], 'size' => (int)$r['fsize'], 'image' => str_starts_with((string)$r['fmime'], 'image/')] : null], $rows),
            'blocked' => (bool)$this->db->val('SELECT 1 FROM {p}msg_blocks WHERE sid = ? AND blocked_sid = ?', [$me, $with])];
    }

    private function opFile(Ctx $c): array
    {
        $me = $c->sid(); $id = (string)($c->q['id'] ?? '');
        if (!preg_match('/^[a-f0-9]{32}$/', $id)) throw new HubError('no_file', 404);
        $f = $this->db->row('SELECT f.* FROM {p}msg_files f WHERE f.id = ? AND (f.owner_sid = ? OR EXISTS (SELECT 1 FROM {p}msg_messages m WHERE m.file_id = f.id AND m.to_sid = ?))', [$id, $me, $me]);
        $path = $this->storage . '/' . $id . '.bin';
        if (!$f || !is_file($path)) throw new HubError('no_file', 404);
        return ['_file' => ['name' => $f['name'], 'mime' => $f['mime'], 'size' => (int)$f['size'], 'path' => $path]];
    }

    private function opBlock(Ctx $c): array
    {
        $this->touch($c, null);
        $other = strtolower(trim((string)($c->in['sid'] ?? '')));
        if ($other === '' || $other === $c->sid()) throw new HubError('no_user', 404);
        if (!empty($c->in['on'])) $this->db->q('INSERT IGNORE INTO {p}msg_blocks (sid, blocked_sid) VALUES (?,?)', [$c->sid(), $other]);
        else $this->db->q('DELETE FROM {p}msg_blocks WHERE sid = ? AND blocked_sid = ?', [$c->sid(), $other]);
        return ['ok' => true];
    }

    private function opUnread(Ctx $c): array { $this->touch($c, null); return ['unread' => $this->unreadCount($c->sid())]; }
    private function unreadCount(string $sid): int { return (int)$this->db->val('SELECT COUNT(*) FROM {p}msg_messages WHERE to_sid = ? AND read_at IS NULL', [$sid]); }
}
