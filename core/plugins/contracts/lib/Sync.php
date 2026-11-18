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
    /** $urlFor(contract id): the absolute address of a contract page here (for the copies on Bablito); null = unknown, nothing is pushed. */
    public function __construct(private Store $s, private Bablito $b, private $now, private $users, private $urlFor = null) {}

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

    private function hasCategory(string $name): bool { $n = mb_strtolower($name); foreach ($this->s->find('categories') as $c) if (mb_strtolower((string)$c['name']) === $n || mb_strtolower((string)($c['name_ru'] ?? '')) === $n) return true; return false; }

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

    /** The address to open Bablito for this user already signed in, going on to $path. A user without a Bablito account is registered (matched by e-mail, else created; the account has no password, Bablito e-mails a link to choose one). */
    public function goUrl(int $uid, string $path): string { return $this->b->loginUrl($this->remoteUser($uid), $path); }

    /** The Bablito id of this user; an account is registered first when there is none. */
    private function remoteUser(int $uid): int
    {
        $l = $this->link($uid);
        if (!$l) {
            $x = $this->createAccount($uid, false, true);
            if (!in_array($x['status'], ['created', 'linked'], true)) throw new Err($x['status'] === 'exists' ? 'bablito_exists' : 'bablito_refused', $x['detail']);
            $l = $this->link($uid); if (!$l) throw new Err('bablito_refused');
        }
        return (int)$l['remote_id'];
    }

    /**
     * The signed-in user makes an offer on a Bablito contract without leaving Sysfera. $in: mode (accept|counter), notes, payment_type, items[n] = [price, days, excluded] (n = step number, 1-based).
     * @return array{id:int,price:float,days:int,status:string,url:string}
     */
    public function makeOffer(int $uid, int $remoteContract, array $in): array
    {
        $r = $this->s->one('remote', ['remote_id' => $remoteContract]); if (!$r) throw new Err('not_found', '', 404);
        $steps = json_decode((string)$r['steps_json'], true); $n = is_array($steps) ? count($steps) : 0;
        $body = ['mode' => ($in['mode'] ?? '') === 'accept' ? 'accept' : 'counter', 'steps_count' => $n, 'notes' => mb_substr(trim((string)($in['notes'] ?? '')), 0, 4000), 'payment_type' => ($in['payment_type'] ?? '') === 'step' ? 'step' : 'finish', 'items' => []];
        foreach ((array)($in['items'] ?? []) as $k => $it) if (is_array($it) && (int)$k >= 1 && (int)$k <= $n) $body['items'][] = ['n' => (int)$k, 'price' => trim((string)($it['price'] ?? '')), 'days' => trim((string)($it['days'] ?? '')), 'excluded' => !empty($it['excluded'])];
        $a = $this->b->offer($this->remoteUser($uid), $remoteContract, $body);
        if (($a['_status'] ?? 0) === 409) throw new Err(match ($a['error'] ?? '') { 'already_offered' => 'offer_already', 'contract_changed' => 'offer_changed', default => 'offer_not_allowed' }, (string)($a['detail'] ?? ''), 409);
        return (array)($a['offer'] ?? []);
    }

    /** The offer this user already has on a Bablito contract (null when none, or when Bablito cannot be asked). */
    public function myOffer(int $uid, int $remoteContract): ?array
    {
        $l = $this->link($uid); if (!$l) return null;
        try { return $this->b->myOffer((int)$l['remote_id'], $remoteContract); } catch (Err $e) { return null; }
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

    /** Copies the contracts published on Bablito into {p}ct_remote (read-only; offers are made on Bablito). A contract that is no longer published there is removed here. */
    public function contracts(bool $dry): array
    {
        $r = ['total' => 0, 'new' => 0, 'updated' => 0, 'removed' => 0]; $seen = []; $after = 0; $complete = false; $have = [];
        foreach ($this->s->find('remote') as $x) $have[(int)$x['remote_id']] = $x;
        for ($page = 0; $page < 50 && !$complete; $page++) {
            $a = $this->b->contracts($after); $list = (array)($a['contracts'] ?? []);
            foreach ($list as $c) {
                $rid = (int)($c['id'] ?? 0); if ($rid <= 0 || trim((string)($c['title'] ?? '')) === '') continue;
                $after = max($after, $rid); $steps = []; $sum = 0.0; $days = 0;
                foreach (array_slice((array)($c['steps'] ?? []), 0, 100) as $t) {
                    if (!is_array($t)) continue; $pr = max(0.0, (float)($t['price'] ?? 0)); $d = max(0, (int)($t['days'] ?? 0));
                    $steps[] = ['name' => mb_substr(trim((string)($t['name'] ?? '')), 0, 190), 'description' => mb_substr(trim((string)($t['description'] ?? '')), 0, 2000), 'price' => $pr, 'days' => $d]; $sum += $pr; $days += $d;
                }
                $dl = (string)($c['deadline'] ?? ''); $cr = (string)($c['created_at'] ?? '');
                $row = ['remote_id' => $rid, 'title' => mb_substr(trim((string)$c['title']), 0, 190), 'category' => mb_substr(trim((string)($c['category'] ?? '')), 0, 80), 'location' => mb_substr(trim((string)($c['location'] ?? '')), 0, 190),
                    'description' => mb_substr(trim((string)($c['description'] ?? '')), 0, 8000), 'currency' => preg_match('/^[A-Z]{3}$/', (string)($c['currency'] ?? '')) ? $c['currency'] : 'RUB',
                    'payment_type' => ($c['payment_type'] ?? '') === 'step' ? 'step' : 'finish', 'prepay_required' => !empty($c['prepay_required']) ? 1 : 0, 'deadline' => preg_match('/^\d{4}-\d{2}-\d{2}/', $dl) ? substr($dl, 0, 10) : null,
                    'author_name' => mb_substr(trim((string)($c['author']['name'] ?? '')), 0, 120), 'author_remote_id' => (int)($c['author']['id'] ?? 0), 'total_price' => number_format($sum, 2, '.', ''), 'total_days' => $days,
                    'steps_json' => (string)json_encode($steps, JSON_UNESCAPED_UNICODE), 'created_at' => preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $cr) ? $cr : $this->stamp()];
                $seen[$rid] = 1; $r['total']++; $old = $have[$rid] ?? null;
                if (!$dry && $row['category'] !== '' && !$this->hasCategory($row['category'])) $this->s->insert('categories', ['name' => $row['category'], 'name_ru' => null]);
                if (!$old) { $r['new']++; if (!$dry) $this->s->insert('remote', $row + ['synced_at' => $this->stamp()]); }
                else {
                    $diff = false; foreach ($row as $k => $v) if ((string)($old[$k] ?? '') !== (string)($v ?? '')) $diff = true;
                    if ($diff) { $r['updated']++; if (!$dry) $this->s->update('remote', (int)$old['id'], $row + ['synced_at' => $this->stamp()]); }
                }
            }
            if (empty($a['has_more']) || !$list) $complete = true;
        }
        if ($complete) foreach ($have as $rid => $x) if (!isset($seen[$rid])) { $r['removed']++; if (!$dry) $this->s->delete('remote', (int)$x['id']); }
        return $r;
    }

    /** Pushes the contracts published here to Bablito (read-only copies there, offers are made here). The whole list is sent, so a contract that is no longer published disappears there. */
    public function pushContracts(bool $dry): array
    {
        $list = $this->s->find('contracts', ['status' => 'published'], 'id', 500);
        $cats = []; foreach ($this->s->find('categories') as $c) $cats[(int)$c['id']] = $c['name'];
        $names = $list ? ($this->users)(array_values(array_unique(array_map('intval', array_column($list, 'user_id'))))) : [];
        $items = []; $skipped = 0;
        foreach ($list as $k) {
            $url = $this->urlFor ? ($this->urlFor)((int)$k['id']) : null;
            if (!is_string($url) || !str_starts_with($url, 'https://')) { $skipped++; continue; }
            $steps = []; foreach ($this->s->find('todos', ['contract_id' => (int)$k['id']], 'position') as $t) $steps[] = ['name' => (string)$t['name'], 'description' => (string)($t['description'] ?? ''), 'price' => (float)$t['price'], 'days' => (int)$t['days']];
            $items[] = ['id' => (int)$k['id'], 'title' => (string)$k['title'], 'category' => (string)($cats[(int)($k['category_id'] ?? 0)] ?? ''), 'location' => (string)$k['location'], 'description' => (string)$k['description'], 'currency' => (string)$k['currency'],
                'payment_type' => (string)$k['payment_type'], 'prepay_required' => (bool)$k['prepay_required'], 'deadline' => $k['deadline'] ?: null, 'created_at' => (string)($k['published_at'] ?: $k['created_at']),
                'author' => ['name' => (string)($names[(int)$k['user_id']]['name'] ?? '')], 'steps' => $steps, 'url' => $url];
        }
        if ($list && !$items) return ['new' => 0, 'updated' => 0, 'removed' => 0, 'rejected' => 0, 'skipped' => $skipped]; // no public address known: do not wipe what Bablito has
        $a = $this->b->pushContracts($items, $dry);
        return ['new' => (int)($a['new'] ?? 0), 'updated' => (int)($a['updated'] ?? 0), 'removed' => (int)($a['removed'] ?? 0), 'rejected' => count((array)($a['rejected'] ?? [])), 'skipped' => $skipped];
    }
    private function safePush(bool $dry): array { try { return $this->pushContracts($dry); } catch (Err $e) { return ['error' => $e->errCode]; } }

    private function safeContracts(bool $dry): array { try { return $this->contracts($dry); } catch (Err $e) { return ['error' => $e->errCode]; } }

    /** Structure, then users, then the profile of every linked user (at most $max per run). */
    public function all(bool $dry, int $max = 200): array
    {
        $rep = ['dry' => $dry, 'time' => $this->stamp(), 'structure' => $this->structure($dry), 'users' => $this->linkUsers($dry), 'contracts' => $this->safeContracts($dry), 'pushed' => $this->safePush($dry), 'profiles' => ['ok' => 0, 'pulled' => 0, 'pushed' => 0, 'failed' => []]];
        $n = 0; foreach ($this->s->find('links', [], 'synced_at') as $l) {
            if ($n++ >= $max) break;
            try { $x = $this->profile((int)$l['user_id'], $dry); $rep['profiles']['ok']++; $rep['profiles']['pulled'] += $x['pulled']; $rep['profiles']['pushed'] += $x['pushed']; }
            catch (Err $e) { $rep['profiles']['failed'][] = ['id' => (int)$l['user_id'], 'why' => $e->errCode]; }
        }
        return $rep;
    }
}
