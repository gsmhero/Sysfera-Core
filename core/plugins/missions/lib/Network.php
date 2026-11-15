<?php
declare(strict_types=1);
namespace SysferaMissions;

use Sysfera\Db;
use Sysfera\TaskTypes;

/**
 * The public mission library of the whole Sysfera network. The hub (core.sysfera.ru) keeps it in {p}mis_net:
 * missions published by sites (source "site:<domain>") and missions pulled from tactify.ru (source "tactify").
 * The hub is the only one that talks to Tactify (signed API, secret kept on the hub only).
 * Uploaded files are never part of a published mission. All text coming from outside is cut to size and stripped of HTML.
 */
final class Network
{
    public const MAX_TASKS = 300;
    public const MAX_JSON = 900000;
    public const PUBLISH_PER_DAY = 100;
    public const HIDE_AFTER_REPORTS = 3;
    public const PULL_EVERY = 900;        // seconds between pulls from Tactify
    public const API_PATH = '/api/v1/library.php';

    public static function s(mixed $v, int $n): string { return mb_substr(trim(strip_tags((string)$v)), 0, $n); }

    /**
     * A mission from a site (sysfera-mission export) or from Tactify (parent_link_id style) as one clean sysfera-mission array, or null when unusable.
     * Parent links become indexes + branch, types become codes, task data keeps only short scalar values.
     */
    public static function sanitize(array $in): ?array
    {
        $title = self::s($in['title'] ?? '', 255); if ($title === '') return null;
        $tasks = array_values(array_filter((array)($in['tasks'] ?? []), 'is_array'));
        if (count($tasks) > self::MAX_TASKS) $tasks = array_slice($tasks, 0, self::MAX_TASKS);
        $tactify = isset($tasks[0]) && array_key_exists('parent_link_id', $tasks[0]);
        $pos = []; foreach ($tasks as $i => $t) if (isset($t['id'])) $pos[(string)$t['id']] = $i;
        $out = [];
        foreach ($tasks as $i => $t) {
            $parent = null; $branch = null;
            if ($tactify) {
                if (preg_match('/^(.+)_(good|alt|unexpected|bad)$/', (string)($t['parent_link_id'] ?? ''), $m) && isset($pos[$m[1]]) && $pos[$m[1]] < $i) { $parent = $pos[$m[1]]; $branch = $m[2] === 'unexpected' ? 'bad' : $m[2]; }
            } elseif (isset($t['parent']) && is_numeric($t['parent']) && (int)$t['parent'] >= 0 && (int)$t['parent'] < $i) {
                $parent = (int)$t['parent']; $branch = in_array($t['branch'] ?? '', ['good', 'alt', 'bad'], true) ? $t['branch'] : 'good';
            }
            $d = $t['type_data'] ?? $t['typeData'] ?? [];
            if (is_string($d)) $d = json_decode($d, true);
            $data = [];
            foreach (is_array($d) ? array_slice($d, 0, 20, true) : [] as $k => $v) if (is_string($k) && preg_match('/^[a-z][a-z0-9_]{0,23}$/i', $k) && is_scalar($v)) $data[$k] = self::s($v, 1000);
            $out[] = ['parent' => $parent, 'branch' => $branch, 'title' => self::s($t['title'] ?? '', 255) ?: '—', 'type' => TaskTypes::normalize((string)($t['type'] ?? 'note')) ?: 'note',
                'cost' => round(max(0, min(9999999999, (float)($t['cost'] ?? 0))), 2), 'description' => mb_substr(trim(strip_tags((string)($t['description'] ?? ''))), 0, 5000),
                'result_good' => self::s($t['result_good'] ?? '', 255), 'result_alt' => self::s($t['result_alt'] ?? '', 255), 'result_bad' => self::s($t['result_bad'] ?? $t['result_unexpected'] ?? '', 255), 'type_data' => $data ?: new \stdClass()];
        }
        $r = ['format' => 'sysfera-mission', 'version' => 1, 'title' => $title, 'type' => self::s($in['type'] ?? '', 100), 'description' => mb_substr(trim(strip_tags((string)($in['description'] ?? ''))), 0, 5000), 'tasks' => $out];
        return strlen((string)json_encode($r, JSON_UNESCAPED_UNICODE)) > self::MAX_JSON ? null : $r;
    }

    /** https://host[:port] of an address typed by the administrator (any path is dropped); '' when it is not an https address */
    public static function baseUrl(string $u): string
    {
        $p = parse_url(trim($u)); if (!$p || strtolower((string)($p['scheme'] ?? '')) !== 'https' || empty($p['host'])) return '';
        return 'https://' . strtolower($p['host']) . (isset($p['port']) ? ':' . (int)$p['port'] : '');
    }

    /** a clean mission as the body of POST /api/v1/library.php on Tactify */
    public static function toTactify(array $c, string $ext, string $author, string $cat, string $catRu): array
    {
        $tasks = [];
        foreach ($c['tasks'] as $i => $t) {
            $tasks[] = ['id' => 'k' . $i, 'title' => $t['title'], 'type' => $t['type'], 'cost' => $t['cost'], 'description' => $t['description'],
                'parent_link_id' => $t['parent'] !== null ? 'k' . $t['parent'] . '_' . ($t['branch'] === 'bad' ? 'unexpected' : $t['branch']) : '',
                'result_good' => $t['result_good'], 'result_alt' => $t['result_alt'], 'result_unexpected' => $t['result_bad'], 'typeData' => $t['type_data']];
        }
        return ['external_id' => $ext, 'title' => $c['title'], 'type' => $c['type'], 'description' => $c['description'], 'category' => $cat, 'category_ru' => $catRu, 'author' => $author, 'tasks' => $tasks];
    }

    /* ---------------- Tactify (hub only) ---------------- */

    /** signature of a request to Tactify; must stay identical to api/v1/library.php there */
    public static function sign(string $secret, int $ts, string $method, string $query, string $body): string
    {
        return hash_hmac('sha256', $ts . "\n" . $method . "\n" . self::API_PATH . '?' . $query . "\n" . hash('sha256', $body), $secret);
    }

    /** @return array{0:int,1:array} [http code, json] ; code 0 = network failure */
    public static function tactify(string $base, string $secret, string $method, array $query = [], ?array $body = null, int $timeout = 6): array
    {
        $payload = $body === null ? '' : (string)json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $qs = http_build_query($query); $ts = time();
        $headers = ['Accept: application/json', 'Content-Type: application/json', 'X-Ts: ' . $ts, 'X-Sig: ' . self::sign($secret, $ts, $method, $qs, $payload), 'User-Agent: Sysfera-Hub'];
        [$code, $res, $err] = \SysferaMsg\Client::http($method, rtrim($base, '/') . self::API_PATH . ($qs !== '' ? '?' . $qs : ''), $headers, $payload, $timeout);
        $d = json_decode($res, true);
        return [$err !== null ? 0 : $code, is_array($d) ? $d : []];
    }

    /**
     * One round trip with Tactify (hub): pull its public missions, push the ones published by sites, remove what was withdrawn.
     * Self-healing: it compares lists instead of trusting events, so a failed push is simply retried next time. Stops after $seconds.
     * @return array{pulled:int,pushed:int,removed:int,error:?string}
     */
    public static function syncTactify(Db $db, string $base, string $secret, int $seconds = 10): array
    {
        $res = ['pulled' => 0, 'pushed' => 0, 'removed' => 0, 'error' => null]; $end = time() + $seconds;
        [$code, $list] = self::tactify($base, $secret, 'GET');
        if ($code !== 200 || empty($list['ok'])) { $res['error'] = ($code === 0 ? 'network (no answer from ' . $base . ')' : 'http_' . $code . (isset($list['error']) ? ' ' . self::s($list['error'], 60) : ' (not the Tactify API answer: check the address)')); return $res; }
        // 1. pull
        $have = []; foreach ($db->rows("SELECT ext_id, src_updated FROM {p}mis_net WHERE source = 'tactify'") as $r) $have[$r['ext_id']] = (string)$r['src_updated'];
        $seen = []; $listed = 0; $why = '';
        foreach ((array)($list['missions'] ?? []) as $m) { $listed++;
            $id = (string)($m['id'] ?? ''); if (!preg_match('/^M-\d{1,10}$/', $id)) continue; $seen[$id] = 1;
            $upd = preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/', (string)($m['updated_at'] ?? '')) ? $m['updated_at'] : null;
            if (isset($have[$id]) && $upd !== null && $have[$id] >= $upd) continue;
            if (time() > $end) break;
            [$c2, $one] = self::tactify($base, $secret, 'GET', ['id' => $id]);
            if ($c2 !== 200 || empty($one['mission'])) { $why = $why ?: "$id: http_$c2 " . self::s($one['error'] ?? '', 40); continue; }
            $mm = $one['mission']; $clean = self::sanitize($mm); if (!$clean) { $why = $why ?: "$id: rejected (empty title or too large)"; continue; }
            self::store($db, 'tactify', $id, '', $clean, self::s($mm['author'] ?? '', 100), self::s($mm['category'] ?? '', 100), self::s($mm['category_ru'] ?? '', 100), $upd);
            $res['pulled']++;
        }
        if ($listed === 0) $res['error'] = 'Tactify lists 0 public missions';
        elseif ($why !== '' && $res['pulled'] === 0) $res['error'] = "$listed listed, none stored: $why";
        if (time() <= $end) foreach ($have as $id => $_) if (!isset($seen[$id])) $db->q("DELETE FROM {p}mis_net WHERE source = 'tactify' AND ext_id = ?", [$id]);   // withdrawn on Tactify
        // 2. push what sites published and Tactify does not have (or has older)
        $onT = []; foreach ((array)($list['hub'] ?? []) as $h) if (isset($h['external_id'])) $onT[(string)$h['external_id']] = 1;
        $rows = $db->rows("SELECT * FROM {p}mis_net WHERE source LIKE 'site:%' AND hidden = 0 AND (tactify_pushed IS NULL OR tactify_pushed < updated_at) ORDER BY updated_at LIMIT 20");
        foreach ($rows as $r) {
            if (time() > $end) break;
            $clean = json_decode((string)$r['content'], true); if (!is_array($clean)) continue;
            [$c3, $pr] = self::tactify($base, $secret, 'POST', [], self::toTactify($clean, self::extFor($r), (string)$r['author'], (string)$r['category'], (string)$r['category_ru']), 8);
            if ($c3 !== 200 && $c3 !== 201) $res['error'] = $res['error'] ?: 'push of "' . mb_substr((string)$r['title'], 0, 40) . '" failed: ' . ($c3 === 0 ? 'network' : 'http_' . $c3 . ' ' . self::s($pr['error'] ?? '', 60));
            if ($c3 === 200 || $c3 === 201) { $db->q('UPDATE {p}mis_net SET tactify_pushed = ?, updated_at = updated_at WHERE id = ?', [$r['updated_at'], $r['id']]); $res['pushed']++; }
        }
        // 3. remove from Tactify what is gone here (withdrawn, hidden, deleted)
        $live = []; foreach ($db->rows("SELECT source, ext_id FROM {p}mis_net WHERE source LIKE 'site:%' AND hidden = 0") as $r) $live[self::extFor($r)] = 1;
        foreach ($onT as $ext => $_) {
            if (isset($live[$ext]) || time() > $end) continue;
            [$c4] = self::tactify($base, $secret, 'DELETE', ['external_id' => $ext]); if ($c4 === 200) $res['removed']++;
        }
        return $res;
    }

    /** the id a site mission has on Tactify (≤120 characters, characters Tactify accepts) */
    public static function extFor(array $row): string { return substr((string)preg_replace('/[^A-Za-z0-9:._@-]/', '_', (string)$row['source'] . ':' . $row['ext_id']), 0, 120); }

    /* ---------------- storage ---------------- */

    public static function store(Db $db, string $source, string $ext, string $ownerSid, array $clean, string $author, string $cat, string $catRu, ?string $srcUpdated = null): void
    {
        $db->q('INSERT INTO {p}mis_net (source, ext_id, owner_sid, title, type, category, category_ru, description, author, tasks_n, content, src_updated) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE title = VALUES(title), type = VALUES(type), category = VALUES(category), category_ru = VALUES(category_ru), description = VALUES(description), author = VALUES(author),
                tasks_n = VALUES(tasks_n), content = VALUES(content), src_updated = VALUES(src_updated), updated_at = NOW()',
            [$source, $ext, $ownerSid, $clean['title'], $clean['type'], $cat, $catRu, $clean['description'] ?: null, $author, count($clean['tasks']), json_encode($clean, JSON_UNESCAPED_UNICODE), $srcUpdated ?? date('Y-m-d H:i:s')]);
    }

    /** a reader reports a mission; three different reporters hide it. false when it does not exist */
    public static function report(Db $db, int $id, string $sid): bool
    {
        if (!$db->val('SELECT 1 FROM {p}mis_net WHERE id = ?', [$id])) return false;
        $db->q('INSERT IGNORE INTO {p}mis_net_reports (net_id, sid) VALUES (?,?)', [$id, $sid]);
        $n = (int)$db->val('SELECT COUNT(*) FROM {p}mis_net_reports WHERE net_id = ?', [$id]);
        $db->q('UPDATE {p}mis_net SET reports = ?, hidden = IF(? >= ?, 1, hidden), updated_at = updated_at WHERE id = ?', [$n, $n, self::HIDE_AFTER_REPORTS, $id]);
        return true;
    }

    /** one visible mission with its content, or null */
    public static function one(Db $db, int $id): ?array
    {
        $r = $db->row('SELECT id, source, title, type, category, category_ru, description, author, tasks_n, content, updated_at FROM {p}mis_net WHERE id = ? AND hidden = 0', [$id]);
        if (!$r) return null;
        $r['content'] = json_decode((string)$r['content'], true) ?: [];
        return $r;
    }

    /** list for the library tab: [rows, total] */
    public static function search(Db $db, string $q, string $cat, string $src, int $limit = 60, int $offset = 0): array
    {
        $w = ' WHERE hidden = 0'; $a = [];
        if ($q !== '') { $w .= ' AND (title LIKE ? OR type LIKE ? OR description LIKE ?)'; array_push($a, "%$q%", "%$q%", "%$q%"); }
        if ($cat !== '') { $w .= ' AND (category = ? OR category_ru = ?)'; array_push($a, $cat, $cat); }
        if ($src === 'tactify') $w .= " AND source = 'tactify'"; elseif ($src === 'sites') $w .= " AND source LIKE 'site:%'";
        $total = (int)$db->val("SELECT COUNT(*) FROM {p}mis_net$w", $a);
        $rows = $db->rows("SELECT id, source, title, type, category, category_ru, description, author, tasks_n, updated_at FROM {p}mis_net$w ORDER BY updated_at DESC, id DESC LIMIT " . max(1, min(100, $limit)) . ' OFFSET ' . max(0, $offset), $a);
        return [$rows, $total];
    }
}
