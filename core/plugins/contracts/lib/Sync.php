<?php
declare(strict_types=1);
namespace SysferaContracts;

/**
 * Synchronisation with Bablito.ru (rules only; the transfer is Bablito.php, the storage a Store, so it is tested without a network or a database).
 *  - structure: Bablito is the master of sections and fields; they are mirrored here (matched by remote_id, else by status + title / section + label). Nothing is ever deleted here.
 *  - users: matched by e-mail; a match is linked automatically, an unmatched user is only listed - an account on Bablito is created when an administrator (or the user) asks.
 *  - profile: all three statuses; per field the newest change (UTC) wins, ties go to Bablito; an empty value never deletes; statuses only accumulate.
 * $dry = true changes nothing on either side and returns what would be done.
 */
final class Sync
{
    private const LEGACY = '2000-01-01 00:00:00';
    public function __construct(private Store $s, private Bablito $b, private $now, private $users) {}

    private static function t(?string $d): int { return $d ? (int)strtotime($d . ' UTC') : 0; }
    private function stamp(): string { return gmdate('Y-m-d H:i:s', ($this->now)()); }
    public function link(int $uid): ?array { return $this->s->one('links', ['user_id' => $uid]); }

    /** Mirrors the sections and fields of Bablito. */
    public function structure(bool $dry): array
    {
        $r = ['sections_new' => 0, 'sections_updated' => 0, 'fields_new' => 0, 'fields_updated' => 0];
        $secs = $this->s->find('psections'); $flds = $this->s->find('pfields');
        foreach ($this->b->fields() as $rs) {
            if (!is_array($rs) || !in_array($rs['type'] ?? '', Svc::PARTY_TYPES, true) || trim((string)($rs['title'] ?? '')) === '') continue;
            $rid = (int)$rs['id']; $title = mb_substr(trim((string)$rs['title']), 0, 120);
            $row = ['title' => $title, 'type' => $rs['type'], 'position' => (int)($rs['position'] ?? 0), 'remote_id' => $rid];
            $local = null; foreach ($secs as $x) if ((int)($x['remote_id'] ?? 0) === $rid) $local = $x;
            if (!$local) foreach ($secs as $x) if (empty($x['remote_id']) && $x['type'] === $rs['type'] && mb_strtolower($x['title']) === mb_strtolower($title)) $local = $x;
            if (!$local) { $r['sections_new']++; $local = ['id' => 0] + $row; if (!$dry) { $local['id'] = $this->s->insert('psections', $row); $secs[] = $local; } }
            elseif (array_diff_assoc($row, array_intersect_key($local, $row)) !== []) { $r['sections_updated']++; if (!$dry) $this->s->update('psections', (int)$local['id'], $row); }
            foreach ((array)($rs['fields'] ?? []) as $rf) {
                if (!is_array($rf) || trim((string)($rf['label'] ?? '')) === '') continue;
                $fid = (int)$rf['id']; $label = mb_substr(trim((string)$rf['label']), 0, 120); $type = in_array($rf['type'] ?? '', Svc::FIELD_TYPES, true) ? $rf['type'] : 'text';
                $frow = ['section_id' => (int)$local['id'], 'label' => $label, 'type' => $type, 'options' => $type === 'select' ? mb_substr(trim((string)($rf['options'] ?? '')), 0, 4000) : null,
                    'required' => !empty($rf['required']) ? 1 : 0, 'default_public' => !empty($rf['default_public']) ? 1 : 0, 'position' => (int)($rf['position'] ?? 0), 'remote_id' => $fid];
                $lf = null; foreach ($flds as $x) if ((int)($x['remote_id'] ?? 0) === $fid) $lf = $x;
                if (!$lf && $local['id']) foreach ($flds as $x) if (empty($x['remote_id']) && (int)$x['section_id'] === (int)$local['id'] && mb_strtolower($x['label']) === mb_strtolower($label)) $lf = $x;
                if (!$lf) { $r['fields_new']++; if (!$dry) { $frow['id'] = $this->s->insert('pfields', $frow); $flds[] = $frow; } }
                else { $cur = array_intersect_key($lf, $frow); if (array_map('strval', $cur) !== array_map('strval', $frow)) { $r['fields_updated']++; if (!$dry) $this->s->update('pfields', (int)$lf['id'], $frow); } }
            }
        }
        return $r;
    }

    private function member(int $uid): ?array
    {
        $u = ($this->users)([$uid])[$uid] ?? null; if (!$u) return null;
        $p = $this->s->one('profiles', ['user_id' => $uid]); $have = explode(',', (string)($p['user_types'] ?? ''));
        $u['types'] = array_values(array_filter(Svc::PARTY_TYPES, fn($t) => in_array($t, $have, true))) ?: ['personal'];
        $u['lang'] = ($u['lang'] ?? '') === 'ru' ? 'ru' : 'en'; return $u;
    }
    private function store(int $uid, array $remote, string $how): void
    {
        $this->s->insert('links', ['user_id' => $uid, 'remote_id' => (int)$remote['id'], 'remote_email' => mb_substr((string)$remote['email'], 0, 190), 'how' => $how, 'linked_at' => $this->stamp(), 'synced_at' => null]);
    }

    /** Creates (or links) the account of one Sysfera user on Bablito. Status: created, linked, exists, conflict, refused, would_*. */
    public function createAccount(int $uid, bool $dry, bool $link = false): array
    {
        if ($this->link($uid)) return ['status' => 'linked'];
        $u = $this->member($uid); if (!$u) throw new Err('not_found', '', 404);
        $a = $this->b->account($u, $link, $dry); $st = (string)($a['status'] ?? 'refused');
        if (!$dry && in_array($st, ['created', 'linked'], true) && !empty($a['user']['id'])) $this->store($uid, $a['user'], $st === 'created' ? 'created' : 'email');
        return ['status' => $st, 'detail' => (string)($a['detail'] ?? '')];
    }

    /** Matches every unlinked user by e-mail. @return array{linked:int,would_link:int,unmatched:array,conflicts:array} */
    public function linkUsers(bool $dry): array
    {
        $r = ['linked' => 0, 'would_link' => 0, 'unmatched' => [], 'conflicts' => []];
        $linked = []; foreach ($this->s->find('links') as $l) $linked[(int)$l['user_id']] = 1;
        foreach (($this->users)(null) as $u) {
            $uid = (int)$u['id']; if (isset($linked[$uid]) || !filter_var($u['email'], FILTER_VALIDATE_EMAIL)) continue;
            $m = $this->b->user($u['email']);
            if ($m === null) { $r['unmatched'][] = ['id' => $uid, 'name' => (string)$u['name'], 'email' => (string)$u['email']]; continue; }
            if (isset($m['sysfera_id']) && (int)$m['sysfera_id'] !== $uid) { $r['conflicts'][] = ['id' => $uid, 'email' => (string)$u['email']]; continue; }
            $x = $this->createAccount($uid, $dry, true);
            if ($x['status'] === 'linked') $r['linked']++; elseif ($x['status'] === 'would_link') $r['would_link']++; else $r['conflicts'][] = ['id' => $uid, 'email' => (string)$u['email']];
        }
        return $r;
    }

    /** Two-way sync of one linked user's statuses and field values, newest change wins. */
    public function profile(int $uid, bool $dry): array
    {
        $l = $this->link($uid); if (!$l) throw new Err('not_linked');
        $remote = $this->b->profile((int)$l['remote_id']);
        $byRemote = []; $byLocal = []; foreach ($this->s->find('pfields') as $f) if (!empty($f['remote_id'])) { $byRemote[(int)$f['remote_id']] = $f; $byLocal[(int)$f['id']] = (int)$f['remote_id']; }
        $rv = []; foreach ((array)($remote['values'] ?? []) as $v) $rv[(int)$v['field_id']] = $v;
        $lv = []; foreach ($this->s->find('pvalues', ['user_id' => $uid]) as $v) $lv[(int)$v['field_id']] = $v;
        $rep = ['pulled' => 0, 'pushed' => 0, 'unchanged' => 0, 'rejected' => 0, 'skipped' => 0]; $push = [];
        foreach ($byRemote as $rid => $f) {
            $r = $rv[$rid] ?? null; $x = $lv[(int)$f['id']] ?? null;
            $rHas = $r && trim((string)$r['value']) !== ''; $xHas = $x && trim((string)$x['value']) !== '';
            if ($rHas && $xHas && (string)$r['value'] === (string)$x['value'] && (int)$r['is_public'] === (int)$x['is_public']) { $rep['unchanged']++; continue; }
            if ($rHas && (!$xHas || self::t($r['updated_at'] ?? null) >= self::t($x['updated_at'] ?? null))) {
                $rep['pulled']++;
                if (!$dry) {
                    $row = ['value' => mb_substr((string)$r['value'], 0, 4000), 'is_public' => !empty($r['is_public']) ? 1 : 0, 'updated_at' => (string)$r['updated_at']];
                    if ($x) $this->s->update('pvalues', (int)$x['id'], $row); else $this->s->insert('pvalues', $row + ['user_id' => $uid, 'field_id' => (int)$f['id']]);
                }
            } elseif ($xHas) {
                $push[] = ['field_id' => $rid, 'value' => (string)$x['value'], 'is_public' => (int)$x['is_public'], 'updated_at' => $x['updated_at'] ?: self::LEGACY];
            }
        }
        $p = $this->s->one('profiles', ['user_id' => $uid]); $mine = explode(',', (string)($p['user_types'] ?? '')); $theirs = (array)($remote['user']['types'] ?? []);
        $union = array_values(array_filter(Svc::PARTY_TYPES, fn($t) => in_array($t, $mine, true) || in_array($t, $theirs, true))) ?: ['personal'];
        $rep['types'] = implode(',', $union);
        if (!$dry && $p && implode(',', $union) !== (string)$p['user_types']) $this->s->update('profiles', (int)$p['id'], ['user_types' => implode(',', $union)]);
        if ($push || array_diff($union, $theirs)) {
            $a = $this->b->pushProfile((int)$l['remote_id'], $union, $push, $dry);
            $rep['pushed'] = count((array)($a['applied'] ?? [])); $rep['rejected'] = count((array)($a['rejected'] ?? [])); $rep['skipped'] = count((array)($a['skipped_newer_here'] ?? []));
        }
        if (!$dry) $this->s->update('links', (int)$l['id'], ['synced_at' => $this->stamp()]);
        return $rep;
    }

    /** Structure, then users, then the profile of every linked user (at most $max per run). */
    public function all(bool $dry, int $max = 200): array
    {
        $rep = ['dry' => $dry, 'time' => $this->stamp(), 'structure' => $this->structure($dry), 'users' => $this->linkUsers($dry), 'profiles' => ['ok' => 0, 'pulled' => 0, 'pushed' => 0, 'failed' => []]];
        $n = 0; foreach ($this->s->find('links', [], 'synced_at') as $l) {
            if ($n++ >= $max) break;
            try { $x = $this->profile((int)$l['user_id'], $dry); $rep['profiles']['ok']++; $rep['profiles']['pulled'] += $x['pulled']; $rep['profiles']['pushed'] += $x['pushed']; }
            catch (Err $e) { $rep['profiles']['failed'][] = ['id' => (int)$l['user_id'], 'why' => $e->errCode]; }
        }
        return $rep;
    }
}
