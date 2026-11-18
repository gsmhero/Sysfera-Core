<?php
declare(strict_types=1);
namespace SysferaContracts;

/**
 * Contracts: every rule of the original Bablito app, written against Store (no HTTP, no SQL). plugin.php only wires it to the platform.
 *
 *  draft -> published -> active (offer accepted) <-> paused -> fulfilled (all steps accepted) | terminated (dismissed / nobody came in time)
 *  Author = contract.user_id. Contractor = the offerer whose offer was accepted. A Sysfera administrator may do what an author may.
 */
final class Svc
{
    public const CURRENCIES = ['RUB' => '₽', 'USD' => '$', 'EUR' => '€'];
    public const PARTY_TYPES = ['company', 'self', 'personal'];
    public const PAYMENT_TYPES = ['finish', 'step'];
    public const FIELD_TYPES = ['text', 'textarea', 'number', 'url', 'date', 'select'];
    public const INVOICE_STATUS = ['waiting', 'pending', 'paid'];
    public const INVOICE_PREFIX = 'CT-';
    private const BLANK = ['contractor_id' => null, 'accepted_offer_id' => null, 'prepaid_at' => null, 'agreed_price' => null, 'agreed_days' => null, 'agreed_deadline' => null, 'started_at' => null, 'published_at' => null];
    public const FILE_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'txt', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'csv', 'odt', 'ods'];

    /** @param callable $now returns a unix time; $users(array $ids): [id => ['id','name','email','lang']]; $mail(int $uid, string $kind, array $data, string $url) is optional */
    public function __construct(private Store $s, private $now, private $users, private $mail = null, private array $cfg = []) {}

    public function now(): string { return date('Y-m-d H:i:s', ($this->now)()); }
    private function today(): string { return date('Y-m-d', ($this->now)()); }
    private function cfg(string $k, mixed $d): mixed { return $this->cfg[$k] ?? $d; }
    public static function num(mixed $v): float { return round((float)str_replace([' ', "\u{00A0}", ','], ['', '', '.'], (string)$v), 2); }
    public static function symbol(?string $cur): string { return self::CURRENCIES[$cur ?? 'RUB'] ?? '₽'; }

    /* ---------------------------------- users / profiles ---------------------------------- */

    public function userInfo(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids))); $got = $ids ? ($this->users)($ids) : []; $out = [];
        foreach ($ids as $id) { $u = $got[$id] ?? ['id' => $id, 'name' => '', 'email' => '', 'lang' => 'en']; $u['name'] = trim((string)$u['name']) !== '' ? $u['name'] : 'User #' . $id; $out[$id] = $u; }
        return $out;
    }
    public function userName(int $id): string { return $this->userInfo([$id])[$id]['name']; }

    public function profile(int $uid): array
    {
        $p = $this->s->one('profiles', ['user_id' => $uid]);
        if (!$p) { $this->s->insert('profiles', ['user_id' => $uid, 'user_types' => 'personal', 'about' => '', 'notify_email' => 1, 'rating_avg' => 0, 'rating_count' => 0]); $p = $this->s->one('profiles', ['user_id' => $uid]); }
        return $p;
    }
    /** Statuses of a user in the fixed order company, self, personal. Never empty. */
    public function userTypes(int $uid): array
    {
        $have = explode(',', (string)$this->profile($uid)['user_types']);
        return array_values(array_filter(self::PARTY_TYPES, fn($t) => in_array($t, $have, true))) ?: ['personal'];
    }
    public static function typesFrom(mixed $post): array
    {
        $sel = array_filter((array)$post, fn($t) => is_string($t));
        return array_values(array_filter(self::PARTY_TYPES, fn($t) => in_array($t, $sel, true))) ?: ['personal'];
    }
    public function saveProfile(Ctx $c, array $in, array $fieldPost = []): void
    {
        $p = $this->profile($c->uid); $types = self::typesFrom($in['types'] ?? []);
        $about = mb_substr(trim((string)($in['about'] ?? '')), 0, 4000);
        $this->s->atomic(function () use ($c, $p, $types, $in, $about, $fieldPost) {
            $this->s->update('profiles', (int)$p['id'], ['user_types' => implode(',', $types), 'about' => $about, 'notify_email' => empty($in['notify_email']) ? 0 : 1]);
            $this->saveValues($c->uid, $types, $fieldPost);
        });
    }

    /* ---------------------------------- categories ---------------------------------- */

    public function categories(): array { $r = $this->s->find('categories', [], 'name'); return $r; }
    public function saveCategory(int $id, string $name, string $nameRu): int
    {
        $name = trim($name); if ($name === '' || mb_strlen($name) > 80) throw new Err('category_name');
        foreach ($this->s->find('categories') as $x) if ((int)$x['id'] !== $id && mb_strtolower($x['name']) === mb_strtolower($name)) throw new Err('category_dup');
        $row = ['name' => $name, 'name_ru' => mb_substr(trim($nameRu), 0, 80) ?: null];
        if ($id) { $this->s->update('categories', $id, $row); return $id; }
        return $this->s->insert('categories', $row);
    }
    public function deleteCategory(int $id): void
    {
        $this->s->atomic(function () use ($id) { foreach ($this->s->find('contracts', ['category_id' => $id]) as $c) $this->s->update('contracts', (int)$c['id'], ['category_id' => null]); $this->s->delete('categories', $id); });
    }

    /* ---------------------------------- contracts ---------------------------------- */

    public function contract(int $id): ?array { return $id > 0 ? $this->s->one('contracts', ['id' => $id]) : null; }
    public function steps(int $cid): array { $r = $this->s->find('todos', ['contract_id' => $cid]); usort($r, fn($a, $b) => [(int)$a['position'], (int)$a['id']] <=> [(int)$b['position'], (int)$b['id']]); return $r; }
    public function totals(int $cid): array
    {
        $p = 0.0; $d = 0; $n = 0; foreach ($this->s->find('todos', ['contract_id' => $cid]) as $t) { $p += (float)$t['price']; $d += (int)$t['days']; $n++; }
        return ['price' => round($p, 2), 'days' => $d, 'steps' => $n];
    }
    public function isOwner(Ctx $c, array $k): bool { return (int)$k['user_id'] === $c->uid; }
    public function isContractor(Ctx $c, array $k): bool { return (int)($k['contractor_id'] ?? 0) === $c->uid && $c->uid > 0; }
    public function isParticipant(Ctx $c, array $k): bool { return $this->isOwner($c, $k) || $this->isContractor($c, $k); }
    public function canSee(Ctx $c, array $k): bool { return $k['status'] === 'published' || $c->admin || $this->isParticipant($c, $k); }
    public function isOverdue(array $k): bool
    {
        $d = $k['agreed_deadline'] ?? $k['deadline'] ?? null;
        return in_array($k['status'], ['active', 'paused'], true) && $d && strtotime($d . ' 23:59:59') < ($this->now)();
    }
    private function need(?array $k): array { if (!$k) throw new Err('not_found', '', 404); return $k; }
    private function load(int $id): array { return $this->need($this->contract($id)); }
    private function deny(): never { throw new Err('forbidden', '', 403); }

    /** Create ($id = 0) or edit a draft / published contract. $in: title, category_id, location, description, prepay_required, deadline, currency, publish, author_type, todos[] = [id, name, description, price, days]. */
    public function saveContract(Ctx $c, int $id, array $in): array
    {
        $k = $id ? $this->load($id) : null;
        if ($k && !$this->isOwner($c, $k) && !$c->admin) $this->deny();
        if ($k && !in_array($k['status'], ['draft', 'published'], true)) throw new Err('locked');
        $hasOffers = $k ? (bool)$this->s->find('offers', ['contract_id' => $id], 'id', 1) : false;
        $title = trim((string)($in['title'] ?? '')); if ($title === '' || mb_strlen($title) > 190) throw new Err('title');
        $loc = trim((string)($in['location'] ?? '')); if (mb_strlen($loc) > 190) throw new Err('location');
        $cat = (int)($in['category_id'] ?? 0); if ($cat && !$this->s->one('categories', ['id' => $cat])) $cat = 0;
        if (!$cat && $this->s->find('categories', [], 'id', 1)) throw new Err('category_required');
        $deadline = trim((string)($in['deadline'] ?? '')); if ($deadline !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $deadline) || !checkdate((int)substr($deadline, 5, 2), (int)substr($deadline, 8, 2), (int)substr($deadline, 0, 4)))) throw new Err('deadline');
        $cur = isset(self::CURRENCIES[(string)($in['currency'] ?? '')]) ? (string)$in['currency'] : 'RUB';
        $mine = $this->userTypes($k ? (int)$k['user_id'] : $c->uid); $at = in_array((string)($in['author_type'] ?? ''), $mine, true) ? (string)$in['author_type'] : $mine[0];
        $steps = [];
        if (!$hasOffers) {
            foreach ((array)($in['todos'] ?? []) as $t) {
                if (!is_array($t)) continue; $name = trim((string)($t['name'] ?? '')); if ($name === '') continue;
                $price = self::num($t['price'] ?? 0); $days = (int)($t['days'] ?? 1);
                if ($price < 0 || $price > 999999999 || $days < 0 || $days > 3650) throw new Err('step_numbers', $name);
                $steps[] = ['id' => (int)($t['id'] ?? 0), 'name' => mb_substr($name, 0, 190), 'description' => mb_substr(trim((string)($t['description'] ?? '')), 0, 4000), 'price' => $price, 'days' => $days];
            }
            if (!$steps) throw new Err('steps_required');
            if (count($steps) > 80) throw new Err('steps_many');
        }
        $status = !empty($in['publish']) ? 'published' : 'draft';
        return $this->s->atomic(function () use ($c, $k, $id, $in, $title, $loc, $cat, $deadline, $cur, $at, $steps, $status, $hasOffers) {
            if ($deadline === '' && !$hasOffers) $deadline = date('Y-m-d', ($this->now)() + 86400 * array_sum(array_column($steps, 'days')));
            $row = ['title' => $title, 'category_id' => $cat ?: null, 'location' => $loc, 'description' => mb_substr(trim((string)($in['description'] ?? '')), 0, 20000), 'prepay_required' => empty($in['prepay_required']) ? 0 : 1, 'deadline' => $deadline ?: null, 'currency' => $cur, 'author_type' => $at, 'status' => $status, 'allow_extra_todos' => 1, 'updated_at' => $this->now()];
            if ($status === 'published' && (!$k || $k['status'] !== 'published')) $row['published_at'] = $this->now();
            if ($k) { $this->s->update('contracts', (int)$k['id'], $row); $cid = (int)$k['id']; }
            else $cid = $this->s->insert('contracts', $row + self::BLANK + ['user_id' => $c->uid, 'payment_type' => 'finish', 'views' => 0, 'created_at' => $this->now()]);
            if (!$hasOffers) {
                $keep = []; $have = array_column($this->s->find('todos', ['contract_id' => $cid]), null, 'id');
                foreach ($steps as $pos => $t) {
                    $r = ['position' => $pos, 'name' => $t['name'], 'description' => $t['description'], 'price' => $t['price'], 'days' => $t['days']];
                    if ($t['id'] && isset($have[$t['id']])) { $this->s->update('todos', $t['id'], $r); $keep[] = $t['id']; }
                    else $keep[] = $this->s->insert('todos', $r + ['contract_id' => $cid, 'agreed_price' => null, 'agreed_days' => null, 'done_at' => null, 'accepted_at' => null]);
                }
                foreach ($have as $tid => $_) if (!in_array((int)$tid, $keep, true)) $this->s->delete('todos', (int)$tid);
            }
            return $this->load($cid);
        });
    }

    public function addView(int $id): void { if ($k = $this->contract($id)) $this->s->update('contracts', $id, ['views' => (int)$k['views'] + 1]); }

    public function delete(Ctx $c, int $id): array
    {
        $k = $this->load($id); if (!$this->isOwner($c, $k) && !$c->admin) $this->deny();
        if (in_array($k['status'], ['active', 'paused'], true)) throw new Err('delete_active');
        return $this->purge($id);
    }
    /** Removes a contract with everything that hangs on it; returns the stored file names so the caller can unlink them. */
    private function purge(int $id): array
    {
        $names = [];
        $this->s->atomic(function () use ($id, &$names) {
            foreach ($this->s->find('offers', ['contract_id' => $id]) as $o) $this->dropOffer((int)$o['id']);
            foreach (['todos', 'comments', 'reviews', 'watch', 'invoices'] as $t) foreach ($this->s->find($t, ['contract_id' => $id]) as $r) $this->s->delete($t, (int)$r['id']);
            foreach ($this->s->find('files', ['contract_id' => $id]) as $f) { $names[] = $f['stored_name']; $this->s->delete('files', (int)$f['id']); }
            $this->s->delete('contracts', $id);
        });
        return $names;
    }
    private function dropOffer(int $oid): void
    {
        foreach (['offer_todos', 'offer_extra'] as $t) foreach ($this->s->find($t, ['offer_id' => $oid]) as $r) $this->s->delete($t, (int)$r['id']);
        foreach ($this->s->find('comments', ['scope' => 'offer', 'offer_id' => $oid]) as $r) $this->s->delete('comments', (int)$r['id']);
        $this->s->delete('offers', $oid);
    }

    /** A fresh draft (new id) with the author's own content only: no comments, offers, invoices or what the contractor uploaded. */
    public function repeat(Ctx $c, int $id): int
    {
        $k = $this->load($id); if (!$this->isOwner($c, $k) || !in_array($k['status'], ['fulfilled', 'terminated'], true)) $this->deny();
        return $this->s->atomic(function () use ($c, $k, $id) {
            $n = $this->s->insert('contracts', ['user_id' => $c->uid, 'title' => $k['title'], 'category_id' => $k['category_id'], 'location' => $k['location'], 'description' => $k['description'], 'status' => 'draft', 'allow_extra_todos' => 1, 'prepay_required' => $k['prepay_required'], 'currency' => $k['currency'], 'author_type' => $k['author_type'], 'payment_type' => 'finish', 'views' => 0, 'created_at' => $this->now(), 'updated_at' => $this->now()] + self::BLANK);
            foreach ($this->steps($id) as $t) $this->s->insert('todos', ['contract_id' => $n, 'position' => $t['position'], 'name' => $t['name'], 'description' => $t['description'], 'price' => $t['price'], 'days' => $t['days'], 'agreed_price' => null, 'agreed_days' => null, 'done_at' => null, 'accepted_at' => null]);
            return $n;
        });
    }

    /** Published contracts with no contractor after cfg stale_days (30) leave the listing and go to the author's archive. Returns the number archived. */
    public function archiveStale(): int
    {
        $limit = ($this->now)() - 86400 * max(1, (int)$this->cfg('stale_days', 30)); $n = 0;
        foreach ($this->s->find('contracts', ['status' => 'published']) as $k) {
            if (strtotime((string)($k['published_at'] ?: $k['created_at'])) >= $limit) continue;
            $this->s->atomic(function () use ($k) { $this->s->update('contracts', (int)$k['id'], ['status' => 'terminated']); foreach ($this->s->find('offers', ['contract_id' => $k['id'], 'status' => 'pending']) as $o) $this->dropOffer((int)$o['id']); });
            $this->notify('stale', $k, null, [(int)$k['user_id']], ['url' => 'mine?tab=archive']); $n++;
        }
        return $n;
    }

    /* ---------------------------------- listing / my ---------------------------------- */

    private function card(array $k, array $names, array $profiles): array
    {
        $t = $this->totals((int)$k['id']); $steps = $this->steps((int)$k['id']); $acc = count(array_filter($steps, fn($x) => $x['accepted_at'] !== null));
        $offers = $this->s->find('offers', ['contract_id' => $k['id']]); $cat = $k['category_id'] ? $this->s->one('categories', ['id' => $k['category_id']]) : null;
        return $k + ['total_price' => $t['price'], 'total_days' => $t['days'], 'steps' => $t['steps'], 'accepted_steps' => $acc, 'offer_count' => count($offers), 'new_offers' => count(array_filter($offers, fn($o) => $o['seen_at'] === null && $o['status'] === 'pending')),
            'category' => $cat['name'] ?? '', 'category_ru' => ($cat['name_ru'] ?? '') ?: ($cat['name'] ?? ''), 'author_name' => $names[(int)$k['user_id']]['name'] ?? '', 'rating_avg' => (float)($profiles[(int)$k['user_id']]['rating_avg'] ?? 0), 'rating_count' => (int)($profiles[(int)$k['user_id']]['rating_count'] ?? 0)];
    }
    private function cards(array $list): array
    {
        $names = $this->userInfo(array_column($list, 'user_id')); $profiles = [];
        foreach ($list as $k) $profiles[(int)$k['user_id']] ??= $this->profile((int)$k['user_id']);
        return array_map(fn($k) => $this->card($k, $names, $profiles), $list);
    }

    /** The public listing. $f: category, from, to (price), q (title / description / location / category), sort date|price, page, per. */
    public function listing(array $f): array
    {
        $cat = (int)($f['category'] ?? 0); $from = trim((string)($f['from'] ?? '')); $to = trim((string)($f['to'] ?? '')); $q = mb_strtolower(trim((string)($f['q'] ?? '')));
        $sort = ($f['sort'] ?? 'date') === 'price' ? 'price' : 'date'; $page = max(1, (int)($f['page'] ?? 1)); $per = max(1, min(100, (int)($f['per'] ?? 20)));
        $list = $this->cards($this->s->find('contracts', ['status' => 'published'], 'created_at DESC'));
        $list = array_values(array_filter($list, function ($k) use ($cat, $from, $to, $q) {
            if ($cat > 0 && (int)$k['category_id'] !== $cat) return false;
            if ($from !== '' && is_numeric($from) && $k['total_price'] < (float)$from) return false;
            if ($to !== '' && is_numeric($to) && $k['total_price'] > (float)$to) return false;
            return $q === '' || str_contains(mb_strtolower($k['title'] . ' ' . $k['description'] . ' ' . $k['location'] . ' ' . $k['category'] . ' ' . $k['category_ru']), $q);
        }));
        usort($list, fn($a, $b) => $sort === 'price' ? [$b['total_price'], $b['id']] <=> [$a['total_price'], $a['id']] : [$b['created_at'], $b['id']] <=> [$a['created_at'], $a['id']]);
        return ['rows' => array_slice($list, ($page - 1) * $per, $per), 'total' => count($list), 'page' => $page, 'per' => $per, 'sort' => $sort];
    }

    /** "My contracts": created (draft / published / active / paused as author), working (as contractor), offers (my pending offers), archive, watch. */
    public function mine(Ctx $c, string $tab): array
    {
        $tab = in_array($tab, ['created', 'working', 'offers', 'archive', 'watch'], true) ? $tab : 'created';
        $mineAll = fn() => array_merge($this->s->find('contracts', ['user_id' => $c->uid]), $this->s->find('contracts', ['contractor_id' => $c->uid]));
        switch ($tab) {
            case 'created': $l = $this->s->find('contracts', ['user_id' => $c->uid, 'status' => ['draft', 'published', 'active', 'paused']]); break;
            case 'working': $l = $this->s->find('contracts', ['contractor_id' => $c->uid, 'status' => ['active', 'paused']]); break;
            case 'offers': $ids = array_column($this->s->find('offers', ['user_id' => $c->uid, 'status' => 'pending']), 'contract_id'); $l = $ids ? $this->s->find('contracts', ['id' => $ids]) : []; break;
            case 'watch': $ids = array_column($this->s->find('watch', ['user_id' => $c->uid]), 'contract_id'); $l = $ids ? $this->s->find('contracts', ['id' => $ids, 'status' => 'published']) : []; break;
            default: $l = array_values(array_filter($mineAll(), fn($k) => in_array($k['status'], ['fulfilled', 'terminated'], true)));
        }
        usort($l, fn($a, $b) => [$b['updated_at'] ?? '', $b['id']] <=> [$a['updated_at'] ?? '', $a['id']]);
        return $this->cards($l);
    }
    public function watchToggle(Ctx $c, int $id): bool
    {
        $k = $this->load($id); if ($k['status'] !== 'published') $this->deny();
        $w = $this->s->one('watch', ['user_id' => $c->uid, 'contract_id' => $id]);
        if ($w) { $this->s->delete('watch', (int)$w['id']); return false; }
        $this->s->insert('watch', ['user_id' => $c->uid, 'contract_id' => $id, 'created_at' => $this->now()]); return true;
    }
    public function isWatched(Ctx $c, int $id): bool { return (bool)$this->s->one('watch', ['user_id' => $c->uid, 'contract_id' => $id]); }

    /* ---------------------------------- offers ---------------------------------- */

    /** Reads an offer form (counteroffer or "accept as is"); returns [price, days, notes, items[], payment_type, offerer_type] or throws Err. */
    public function parseOffer(array $steps, array $in, bool $asIs, array $myTypes): array
    {
        $byId = []; foreach ($steps as $t) $byId[(int)$t['id']] = $t;
        $items = []; $seen = [];
        if (!$asIs) foreach ((array)($in['items'] ?? []) as $x) {
            if (!is_array($x)) continue; $p = trim((string)($x['price'] ?? '')); $d = trim((string)($x['days'] ?? ''));
            if (($x['type'] ?? '') === 'extra') {
                $name = trim((string)($x['name'] ?? '')); if ($name === '' && $p === '' && $d === '') continue;
                if ($name === '' || mb_strlen($name) > 190 || $p === '') throw new Err('offer_extra');
                if (count($items) >= 80) break;
                $items[] = ['type' => 'extra', 'id' => 0, 'name' => $name, 'description' => mb_substr(trim((string)($x['description'] ?? '')), 0, 4000), 'price' => self::num($p), 'days' => $d === '' ? 0 : (int)$d, 'excluded' => false];
            } else {
                $tid = (int)($x['id'] ?? 0); if (!isset($byId[$tid]) || isset($seen[$tid])) continue; $seen[$tid] = true;
                $items[] = ['type' => 'todo', 'id' => $tid, 'price' => $p === '' ? (float)$byId[$tid]['price'] : self::num($p), 'days' => $d === '' ? (int)$byId[$tid]['days'] : (int)$d, 'excluded' => !empty($x['excluded'])];
            }
        }
        foreach ($byId as $tid => $t) if (!isset($seen[$tid])) $items[] = ['type' => 'todo', 'id' => $tid, 'price' => (float)$t['price'], 'days' => (int)$t['days'], 'excluded' => false];
        $price = 0.0; $days = 0; $kept = 0;
        foreach ($items as $i => $it) {
            if ($it['price'] < 0 || $it['price'] > 999999999 || $it['days'] < 0 || $it['days'] > 3650) throw new Err('offer_numbers');
            $items[$i]['pos'] = $i; if (!$it['excluded']) { $price += $it['price']; $days += $it['days']; $kept++; }
        }
        if ($kept === 0) throw new Err('offer_keep_one');
        if ($price <= 0) throw new Err('offer_price');
        $notes = trim((string)($in['notes'] ?? '')); if (mb_strlen($notes) > 4000) throw new Err('notes_long');
        $ot = in_array((string)($in['offerer_type'] ?? ''), $myTypes, true) ? (string)$in['offerer_type'] : $myTypes[0];
        return ['price' => round($price, 2), 'days' => $days, 'notes' => $notes, 'items' => $items, 'payment_type' => ($in['payment_type'] ?? '') === 'step' ? 'step' : 'finish', 'offerer_type' => $ot];
    }
    private function saveItems(int $oid, array $items): void
    {
        foreach (['offer_todos', 'offer_extra'] as $t) foreach ($this->s->find($t, ['offer_id' => $oid]) as $r) $this->s->delete($t, (int)$r['id']);
        foreach ($items as $it) {
            if ($it['type'] === 'todo') $this->s->insert('offer_todos', ['offer_id' => $oid, 'todo_id' => $it['id'], 'price' => $it['price'], 'days' => $it['days'], 'position' => $it['pos'], 'excluded' => $it['excluded'] ? 1 : 0]);
            else $this->s->insert('offer_extra', ['offer_id' => $oid, 'position' => $it['pos'], 'name' => $it['name'], 'description' => $it['description'], 'price' => $it['price'], 'days' => $it['days']]);
        }
    }

    public function makeOffer(Ctx $c, int $cid, array $in, bool $asIs = false): int
    {
        $k = $this->load($cid);
        if ($this->isOwner($c, $k) || $k['status'] !== 'published') throw new Err('offer_not_allowed');
        if ($this->s->one('offers', ['contract_id' => $cid, 'user_id' => $c->uid])) throw new Err('offer_exists');
        $o = $this->parseOffer($this->steps($cid), $in, $asIs, $this->userTypes($c->uid));
        $oid = $this->s->atomic(function () use ($c, $cid, $o) {
            $oid = $this->s->insert('offers', ['contract_id' => $cid, 'user_id' => $c->uid, 'price' => $o['price'], 'days' => $o['days'], 'notes' => $o['notes'], 'payment_type' => $o['payment_type'], 'offerer_type' => $o['offerer_type'], 'status' => 'pending', 'seen_at' => null, 'created_at' => $this->now()]);
            $this->saveItems($oid, $o['items']); return $oid;
        });
        $this->notify('offer_new', $k, $c, [(int)$k['user_id']], ['url' => 'offer/' . $oid, 'extra' => self::symbol($k['currency']) . ' ' . $o['price'] . ' / ' . $o['days']]);
        return $oid;
    }
    public function editOffer(Ctx $c, int $oid, array $in): void
    {
        $o = $this->offer($oid); $k = $this->load((int)$o['contract_id']);
        if ((int)$o['user_id'] !== $c->uid || $o['status'] !== 'pending' || $k['status'] !== 'published') $this->deny();
        $p = $this->parseOffer($this->steps((int)$k['id']), $in, false, $this->userTypes($c->uid));
        $this->s->atomic(function () use ($oid, $p) { $this->s->update('offers', $oid, ['price' => $p['price'], 'days' => $p['days'], 'notes' => $p['notes'], 'payment_type' => $p['payment_type'], 'offerer_type' => $p['offerer_type'], 'seen_at' => null]); $this->saveItems($oid, $p['items']); });
        $this->notify('offer_edit', $k, $c, [(int)$k['user_id']], ['url' => 'offer/' . $oid]);
    }
    public function offer(int $id): array { return $this->need($this->s->one('offers', ['id' => $id])); }
    public function withdrawOffer(Ctx $c, int $oid): int
    {
        $o = $this->offer($oid); $k = $this->load((int)$o['contract_id']);
        if ((int)$o['user_id'] !== $c->uid || $o['status'] === 'accepted') $this->deny();
        $this->notify('offer_withdrawn', $k, $c, [(int)$k['user_id']]); $this->dropOffer($oid); return (int)$k['id'];
    }
    public function rejectOffer(Ctx $c, int $oid): int
    {
        $o = $this->offer($oid); $k = $this->load((int)$o['contract_id']);
        if (!$this->isOwner($c, $k) || $o['status'] !== 'pending') $this->deny();
        $this->notify('offer_rejected', $k, $c, [(int)$o['user_id']], ['url' => '']); $this->dropOffer($oid); return (int)$k['id'];
    }
    public function acceptOffer(Ctx $c, int $oid): int
    {
        $o = $this->offer($oid); $k = $this->load((int)$o['contract_id']);
        if (!$this->isOwner($c, $k) || $o['status'] !== 'pending' || $k['status'] !== 'published') $this->deny();
        $lost = array_column(array_filter($this->s->find('offers', ['contract_id' => $k['id'], 'status' => 'pending']), fn($x) => (int)$x['id'] !== $oid), 'user_id');
        $this->s->atomic(function () use ($k, $o, $oid) { $this->acceptTerms($k, $o); foreach ($this->s->find('offers', ['contract_id' => $k['id'], 'status' => 'pending']) as $x) if ((int)$x['id'] !== $oid) $this->dropOffer((int)$x['id']); });
        $this->notify('offer_accepted', $k, $c, [(int)$o['user_id']]);
        $this->notify('offer_lost', $k, $c, array_map('intval', $lost), ['url' => '']);
        return (int)$k['id'];
    }
    /** Freezes the accepted terms onto the contract and its steps (counteroffer: the offerer's order, unnecessary steps dropped, own steps added). */
    private function acceptTerms(array $k, array $o): void
    {
        $todos = $this->steps((int)$k['id']); $per = []; $ordered = false;
        foreach ($this->s->find('offer_todos', ['offer_id' => $o['id']]) as $r) { $per[(int)$r['todo_id']] = $r; if ($r['position'] !== null) $ordered = true; }
        $extras = $this->s->find('offer_extra', ['offer_id' => $o['id']], 'position');
        if ($ordered) {
            foreach ($todos as $t) {
                $r = $per[(int)$t['id']] ?? null;
                if ($r && (int)$r['excluded'] === 1) $this->s->delete('todos', (int)$t['id']);
                elseif ($r) $this->s->update('todos', (int)$t['id'], ['position' => (int)$r['position'], 'agreed_price' => $r['price'], 'agreed_days' => $r['days']]);
                else $this->s->update('todos', (int)$t['id'], ['agreed_price' => $t['price'], 'agreed_days' => $t['days']]);
            }
            foreach ($extras as $e) $this->s->insert('todos', ['contract_id' => $k['id'], 'position' => (int)$e['position'], 'name' => $e['name'], 'description' => $e['description'], 'price' => $e['price'], 'days' => $e['days'], 'agreed_price' => $e['price'], 'agreed_days' => $e['days'], 'done_at' => null, 'accepted_at' => null]);
        } else {   // an offer without per-step values: a total that is spread over the steps
            $n = count($todos); $perDays = array_sum(array_map(fn($r) => (int)$r['days'], $per)); $sumP = array_sum(array_map(fn($t) => (float)$t['price'], $todos)); $leftP = (float)$o['price']; $leftD = (int)$o['days'];
            foreach ($todos as $i => $t) {
                if (isset($per[(int)$t['id']])) { $p = (float)$per[(int)$t['id']]['price']; $d = (int)$per[(int)$t['id']]['days']; if ($perDays === 0 && $i === 0 && (int)$o['days'] > 0) $d = (int)$o['days']; }
                elseif ($i === $n - 1) { $p = max(0.0, $leftP); $d = max(0, $leftD); }
                else { $p = round((float)$o['price'] * ($sumP > 0 ? (float)$t['price'] / $sumP : 1 / $n), 2); $d = max(0, (int)round((int)$o['days'] / $n)); }
                $leftP -= $p; $leftD -= $d; $this->s->update('todos', (int)$t['id'], ['agreed_price' => round($p, 2), 'agreed_days' => $d]);
            }
            $pos = $n; foreach ($extras as $e) $this->s->insert('todos', ['contract_id' => $k['id'], 'position' => $pos++, 'name' => $e['name'], 'description' => $e['description'], 'price' => $e['price'], 'days' => $e['days'], 'agreed_price' => $e['price'], 'agreed_days' => $e['days'], 'done_at' => null, 'accepted_at' => null]);
        }
        $this->s->update('contracts', (int)$k['id'], ['status' => 'active', 'contractor_id' => $o['user_id'], 'accepted_offer_id' => $o['id'], 'payment_type' => $o['payment_type'] ?: 'finish', 'agreed_price' => $o['price'], 'agreed_days' => $o['days'], 'started_at' => $this->now(), 'agreed_deadline' => date('Y-m-d', ($this->now)() + 86400 * (int)$o['days']), 'updated_at' => $this->now()]);
        $this->s->update('offers', (int)$o['id'], ['status' => 'accepted']);
    }
    /** Offer page data. The author reading it marks it as seen. */
    public function offerPage(Ctx $c, int $oid): array
    {
        $o = $this->offer($oid); $k = $this->load((int)$o['contract_id']);
        if (!$this->isOwner($c, $k) && (int)$o['user_id'] !== $c->uid && !$c->admin) $this->deny();
        if ($this->isOwner($c, $k) && $o['seen_at'] === null) $this->s->update('offers', $oid, ['seen_at' => $this->now()]);
        $per = []; foreach ($this->s->find('offer_todos', ['offer_id' => $oid]) as $r) $per[(int)$r['todo_id']] = $r;
        $items = [];
        foreach ($this->steps((int)$k['id']) as $t) { $r = $per[(int)$t['id']] ?? null; $items[] = ['type' => 'todo', 'id' => (int)$t['id'], 'name' => $t['name'], 'description' => $t['description'], 'price' => $t['price'], 'days' => $t['days'], 'o_price' => $r['price'] ?? $t['price'], 'o_days' => $r['days'] ?? $t['days'], 'excluded' => !empty($r['excluded']), 'pos' => $r['position'] ?? null]; }
        foreach ($this->s->find('offer_extra', ['offer_id' => $oid], 'position') as $e) $items[] = ['type' => 'extra', 'id' => 0, 'name' => $e['name'], 'description' => $e['description'], 'price' => null, 'days' => null, 'o_price' => $e['price'], 'o_days' => $e['days'], 'excluded' => false, 'pos' => $e['position']];
        usort($items, fn($a, $b) => ($a['pos'] ?? 9999) <=> ($b['pos'] ?? 9999));
        return ['offer' => $o, 'contract' => $k, 'items' => $items, 'offerer' => $this->userInfo([(int)$o['user_id']])[(int)$o['user_id']], 'comments' => $this->comments(['contract_id' => $k['id'], 'scope' => 'offer', 'offer_id' => $oid]), 'isOwner' => $this->isOwner($c, $k)];
    }

    /* ---------------------------------- the contract page / work ---------------------------------- */

    public function page(Ctx $c, int $id, int $thread = 0): array
    {
        $k = $this->load($id); if (!$this->canSee($c, $k)) $this->deny();
        $author = $this->isOwner($c, $k) || $c->admin; $working = in_array($k['status'], ['active', 'paused', 'fulfilled', 'terminated'], true);
        $names = $this->userInfo(array_filter([(int)$k['user_id'], (int)($k['contractor_id'] ?? 0)]));
        $offers = []; $threads = []; $comments = [];
        if ($author && !$working) {
            $us = $this->userInfo(array_column($this->s->find('offers', ['contract_id' => $id]), 'user_id'));
            foreach ($this->s->find('offers', ['contract_id' => $id], 'created_at') as $o) $offers[] = $o + ['name' => $us[(int)$o['user_id']]['name'], 'rating_avg' => (float)$this->profile((int)$o['user_id'])['rating_avg'], 'rating_count' => (int)$this->profile((int)$o['user_id'])['rating_count']];
        }
        if ($k['status'] === 'published') {
            if ($author) {
                $by = []; foreach ($this->s->find('comments', ['contract_id' => $id, 'scope' => 'public']) as $m) if ($m['thread_user_id'] !== null) $by[(int)$m['thread_user_id']] = max($by[(int)$m['thread_user_id']] ?? 0, (int)$m['id']);
                arsort($by); $un = $this->userInfo(array_keys($by)); foreach ($by as $u => $_) $threads[] = ['uid' => $u, 'name' => $un[$u]['name']];
                $thread = $thread ?: (int)($threads[0]['uid'] ?? 0);
                if ($thread) $comments = $this->comments(['contract_id' => $id, 'scope' => 'public', 'thread_user_id' => $thread]);
            } elseif ($c->uid) { $thread = $c->uid; $comments = $this->comments(['contract_id' => $id, 'scope' => 'public', 'thread_user_id' => $c->uid]); }
        } elseif ($this->isParticipant($c, $k) || $c->admin) $comments = $this->comments(['contract_id' => $id, 'scope' => 'work']);
        $steps = $this->steps($id); $stepComments = [];
        if ($working && ($this->isParticipant($c, $k) || $c->admin)) foreach ($this->s->find('comments', ['contract_id' => $id, 'scope' => 'todo']) as $m) $stepComments[(int)$m['todo_id']][] = $m;
        $mine = $c->uid ? $this->s->one('offers', ['contract_id' => $id, 'user_id' => $c->uid]) : null;
        $reviews = $this->s->find('reviews', ['contract_id' => $id]);
        return ['contract' => $k, 'steps' => $steps, 'totals' => $this->totals($id), 'author' => $names[(int)$k['user_id']], 'contractor' => $k['contractor_id'] ? $names[(int)$k['contractor_id']] : null, 'files' => $this->s->find('files', ['contract_id' => $id]),
            'isOwner' => $this->isOwner($c, $k), 'isContractor' => $this->isContractor($c, $k), 'authorSide' => $author, 'working' => $working, 'offers' => $offers, 'myOffer' => $mine, 'canOffer' => $c->uid && !$this->isOwner($c, $k) && !$mine && $k['status'] === 'published',
            'threads' => $threads, 'thread' => $thread, 'comments' => $comments, 'stepComments' => $stepComments, 'invoices' => $this->contractInvoices($c, $id), 'reviews' => $reviews, 'myReview' => (bool)array_filter($reviews, fn($r) => (int)$r['reviewer_id'] === $c->uid),
            'watched' => $c->uid ? $this->isWatched($c, $id) : false, 'overdue' => $this->isOverdue($k), 'myTypes' => $c->uid ? $this->userTypes($c->uid) : ['personal'], 'userNames' => $this->userInfo(array_merge([(int)$k['user_id']], array_column($comments, 'user_id')))];
    }

    private function active(array $k): void { if ($k['status'] !== 'active') $this->deny(); }

    public function pause(Ctx $c, int $id): string
    {
        $k = $this->load($id); if (!$this->isOwner($c, $k)) $this->deny();
        if ($k['status'] === 'active') { $this->s->update('contracts', $id, ['status' => 'paused']); $this->notify('paused', $k, $c, [(int)$k['contractor_id']]); return 'paused'; }
        if ($k['status'] === 'paused') { $this->s->update('contracts', $id, ['status' => 'active']); $this->notify('resumed', $k, $c, [(int)$k['contractor_id']]); return 'active'; }
        return $k['status'];
    }
    public function fire(Ctx $c, int $id): void
    {
        $k = $this->load($id); if (!$this->isOwner($c, $k) || !in_array($k['status'], ['active', 'paused'], true)) $this->deny();
        $this->s->update('contracts', $id, ['status' => 'terminated']); $this->notify('fired', $k, $c, [(int)$k['contractor_id']], ['url' => 'mine?tab=archive']);
    }
    public function prepay(Ctx $c, int $id): void
    {
        $k = $this->load($id); if (!$this->isOwner($c, $k) || !$k['prepay_required'] || $k['prepaid_at'] || !in_array($k['status'], ['active', 'paused'], true)) $this->deny();
        $this->s->update('contracts', $id, ['prepaid_at' => $this->now()]); $this->notify('prepaid', $k, $c, [(int)$k['contractor_id']]);
    }
    public function done(Ctx $c, int $todoId): array
    {
        $t = $this->need($this->s->one('todos', ['id' => $todoId])); $k = $this->load((int)$t['contract_id']);
        if (!$this->isContractor($c, $k) || $k['status'] !== 'active' || $t['accepted_at']) $this->deny();
        if (!$t['done_at'] && $k['payment_type'] === 'step' && $this->hasUnpaid((int)$k['id'])) throw new Err('prev_unpaid');
        $this->s->update('todos', $todoId, ['done_at' => $t['done_at'] ? null : $this->now()]);
        $this->notify($t['done_at'] ? 'todo_undone' : 'todo_done', $k, $c, [(int)$k['user_id']], ['url' => 'view/' . $k['id'] . '#todo-' . $todoId, 'step' => $t['name']]);
        return ['contract' => (int)$k['id'], 'done' => !$t['done_at']];
    }
    /** Author accepts a step (step-by-step contract: an invoice for it) or takes the acceptance back while its invoice is still waiting. Returns ['contract', 'invoice' (id|0), 'fulfilled' bool]. */
    public function accept(Ctx $c, int $todoId): array
    {
        $t = $this->need($this->s->one('todos', ['id' => $todoId])); $k = $this->load((int)$t['contract_id']); $cid = (int)$k['id'];
        if (!$this->isOwner($c, $k) || $k['status'] !== 'active' || !$t['done_at']) $this->deny();
        $step = $k['payment_type'] === 'step'; $url = 'view/' . $cid . '#todo-' . $todoId;
        if ($t['accepted_at']) {
            $inv = $this->s->one('invoices', ['todo_id' => $todoId]);
            if ($inv && $inv['status'] !== 'waiting') throw new Err('already_paying');
            $this->s->atomic(function () use ($inv, $todoId) { if ($inv) $this->s->delete('invoices', (int)$inv['id']); $this->s->update('todos', $todoId, ['accepted_at' => null]); });
            $this->notify('todo_unaccepted', $k, $c, [(int)$k['contractor_id']], ['url' => $url, 'step' => $t['name']]);
            return ['contract' => $cid, 'invoice' => 0, 'fulfilled' => false];
        }
        $inv = 0; $left = 0;
        $this->s->atomic(function () use ($todoId, $k, $t, $step, $cid, &$inv, &$left) {
            $this->s->update('todos', $todoId, ['accepted_at' => $this->now()]);
            if ($step) $inv = $this->createInvoice($k, $t, (float)($t['agreed_price'] ?? $t['price']));
            $left = count(array_filter($this->s->find('todos', ['contract_id' => $cid]), fn($x) => $x['accepted_at'] === null));
            if ($left === 0) { $this->s->update('contracts', $cid, ['status' => 'fulfilled', 'updated_at' => $this->now()]); if (!$step) $inv = $this->createInvoice($k, null, (float)$k['agreed_price']); }
        });
        $this->notify($left === 0 ? 'contract_fulfilled' : 'todo_accepted', $k, $c, [(int)$k['contractor_id']], ['url' => $inv ? 'invoices?tab=receive' : $url, 'step' => $t['name'], 'extra' => $inv ? $this->invoiceLine($this->invoice($inv)) : '']);
        return ['contract' => $cid, 'invoice' => $inv, 'fulfilled' => $left === 0];
    }

    public function review(Ctx $c, int $cid, int $target, int $rating, string $body): void
    {
        $k = $this->contract($cid);
        $pair = $k && $k['status'] === 'fulfilled' && (((int)$k['user_id'] === $c->uid && (int)$k['contractor_id'] === $target) || ((int)$k['user_id'] === $target && (int)$k['contractor_id'] === $c->uid));
        if (!$pair || $target === $c->uid || $rating < 1 || $rating > 5) throw new Err('review_not_allowed');
        if ($this->s->one('reviews', ['contract_id' => $cid, 'reviewer_id' => $c->uid])) throw new Err('review_exists');
        $this->s->atomic(function () use ($c, $cid, $target, $rating, $body) {
            $this->s->insert('reviews', ['contract_id' => $cid, 'reviewer_id' => $c->uid, 'reviewed_id' => $target, 'rating' => $rating, 'body' => mb_substr(trim($body), 0, 2000), 'created_at' => $this->now()]);
            $all = $this->s->find('reviews', ['reviewed_id' => $target]); $p = $this->profile($target);
            $this->s->update('profiles', (int)$p['id'], ['rating_avg' => round(array_sum(array_column($all, 'rating')) / count($all), 2), 'rating_count' => count($all)]);
        });
        $this->notify('review', $k, $c, [$target], ['url' => 'user/' . $target, 'extra' => $rating . '/5']);
    }
    /** Rows of the counteroffer form: the contract's steps, or what was typed in a form that failed validation (kept, so nothing is lost). */
    public static function offerRows(array $steps, ?array $draft): array
    {
        $by = []; foreach ($steps as $t) $by[(int)$t['id']] = $t;
        $row = fn($t, $p, $d, $x = false) => ['type' => 'todo', 'id' => (int)$t['id'], 'name' => $t['name'], 'description' => $t['description'], 'price' => $t['price'], 'days' => $t['days'], 'o_price' => $p, 'o_days' => $d, 'excluded' => $x];
        if (!$draft) return array_map(fn($t) => $row($t, $t['price'], $t['days']), array_values($steps));
        $out = []; $seen = [];
        foreach ((array)($draft['items'] ?? []) as $x) {
            if (!is_array($x)) continue;
            if (($x['type'] ?? '') === 'extra') $out[] = ['type' => 'extra', 'id' => 0, 'name' => (string)($x['name'] ?? ''), 'description' => (string)($x['description'] ?? ''), 'price' => null, 'days' => null, 'o_price' => (string)($x['price'] ?? ''), 'o_days' => (string)($x['days'] ?? ''), 'excluded' => false];
            elseif (isset($by[(int)($x['id'] ?? 0)]) && !isset($seen[(int)$x['id']])) { $seen[(int)$x['id']] = 1; $out[] = $row($by[(int)$x['id']], (string)($x['price'] ?? ''), (string)($x['days'] ?? ''), !empty($x['excluded'])); }
        }
        foreach ($by as $id => $t) if (!isset($seen[$id])) $out[] = $row($t, $t['price'], $t['days']);
        return $out;
    }
    public function offerCount(int $cid): int { return count($this->s->find('offers', ['contract_id' => $cid])); }
    public function todo(int $id): ?array { return $this->s->one('todos', ['id' => $id]); }
    public function userExists(int $id): bool { return $id > 0 && (bool)($this->users)([$id]); }
    /** Fulfilled contracts shared with $target that $c has not reviewed yet: [[id, title]]. */
    public function reviewable(Ctx $c, int $target): array
    {
        if (!$c->uid || $c->uid === $target) return [];
        $out = [];
        foreach ($this->s->find('contracts', ['status' => 'fulfilled']) as $k) {
            $pair = ((int)$k['user_id'] === $c->uid && (int)$k['contractor_id'] === $target) || ((int)$k['user_id'] === $target && (int)$k['contractor_id'] === $c->uid);
            if ($pair && !$this->s->one('reviews', ['contract_id' => $k['id'], 'reviewer_id' => $c->uid])) $out[] = [(int)$k['id'], $k['title']];
        }
        return $out;
    }
    public function userPage(int $uid): array
    {
        $revs = $this->s->find('reviews', ['reviewed_id' => $uid], 'id DESC'); $names = $this->userInfo(array_merge([$uid], array_column($revs, 'reviewer_id')));
        $pub = []; foreach ($this->structure([]) as $sec) foreach ($sec['fields'] as $f) { $v = $this->values($uid)[(int)$f['id']] ?? null; if ($v && (int)$v['is_public'] && trim((string)$v['value']) !== '') $pub[] = ['label' => $f['label'], 'value' => $v['value']]; }
        return ['user' => $names[$uid], 'profile' => $this->profile($uid), 'reviews' => $revs, 'names' => $names, 'public' => $pub];
    }

    /* ---------------------------------- comments / files ---------------------------------- */

    public function comments(array $where): array { return $this->s->find('comments', $where, 'id'); }
    /** scope public: the author <-> one other user (a private thread); work / todo: the two participants; offer: the author and the offerer. Returns the comment or null (empty / too long). */
    public function comment(Ctx $c, int $cid, string $scope, string $body, int $thread = 0, int $offerId = 0, int $todoId = 0): ?array
    {
        $k = $this->load($cid); $owner = $this->isOwner($c, $k); $body = trim($body); $to = null; $url = 'view/' . $cid . '#comments'; $kind = 'work_comment'; $row = ['contract_id' => $cid, 'user_id' => $c->uid, 'thread_user_id' => null, 'offer_id' => null, 'todo_id' => null];
        if ($scope === 'public') {
            if ($k['status'] !== 'published' || !$c->uid) $this->deny();
            $thread = $owner ? $thread : $c->uid; if ($thread <= 0 || $thread === (int)$k['user_id']) $this->deny();
            $row['thread_user_id'] = $thread; $to = $owner ? $thread : (int)$k['user_id']; $kind = 'thread_comment'; $url = $owner ? $url : 'view/' . $cid . '?thread=' . $c->uid . '#comments';
        } elseif ($scope === 'offer') {
            $o = $this->offer($offerId); if ((int)$o['contract_id'] !== $cid || (!$owner && (int)$o['user_id'] !== $c->uid && !$c->admin)) $this->deny();
            $row['offer_id'] = $offerId; $to = $owner ? (int)$o['user_id'] : (int)$k['user_id']; $kind = 'offer_comment'; $url = 'offer/' . $offerId . '#comments';
        } elseif ($scope === 'todo') {
            $t = $this->need($this->s->one('todos', ['id' => $todoId])); if ((int)$t['contract_id'] !== $cid || !$this->isParticipant($c, $k)) $this->deny();
            $row['todo_id'] = $todoId; $to = $this->other($k, $c->uid); $kind = 'todo_comment'; $url = 'view/' . $cid . '#todo-' . $todoId;
        } else {
            if (!$this->isParticipant($c, $k) || in_array($k['status'], ['draft', 'published'], true)) $this->deny();
            $to = $this->other($k, $c->uid);
        }
        if ($body === '' || mb_strlen($body) > 4000) return null;
        $id = $this->s->insert('comments', $row + ['scope' => $scope, 'body' => $body, 'created_at' => $this->now()]);
        $this->notify($kind, $k, $c, $to ? [$to] : [], ['url' => $url, 'comment' => mb_substr($body, 0, 500), 'step' => isset($t) ? $t['name'] : '']);
        return ['id' => $id] + $row + ['scope' => $scope, 'body' => $body];
    }
    private function other(array $k, int $actor): ?int
    {
        if ($actor === (int)$k['user_id']) return (int)($k['contractor_id'] ?? 0) ?: null;
        return $actor === (int)($k['contractor_id'] ?? 0) ? (int)$k['user_id'] : null;
    }
    public static function safeName(string $n): string
    {
        $n = trim(preg_replace('/[\x00-\x1F\\\\\/<>:"|?*]+/u', '_', basename(str_replace('\\', '/', $n)))); return mb_substr($n === '' ? 'file' : $n, 0, 190);
    }
    public static function extOk(string $name): bool { return in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), self::FILE_EXT, true); }
    /** Registers an uploaded file (already stored under $stored). Author: while the contract is a draft / published / working; contractor: while working. */
    public function addFile(Ctx $c, int $cid, string $kind, string $stored, string $original): int
    {
        $k = $this->load($cid); $owner = $this->isOwner($c, $k) || $c->admin;
        if (!($owner && in_array($k['status'], ['draft', 'published', 'active', 'paused'], true)) && !($this->isContractor($c, $k) && in_array($k['status'], ['active', 'paused'], true))) $this->deny();
        if (!self::extOk($original)) throw new Err('file_type');
        $id = $this->s->insert('files', ['contract_id' => $cid, 'user_id' => $c->uid, 'kind' => $kind === 'image' ? 'image' : 'file', 'stored_name' => $stored, 'original_name' => self::safeName($original), 'by_contractor' => $this->isContractor($c, $k) ? 1 : 0, 'created_at' => $this->now()]);
        if (in_array($k['status'], ['active', 'paused'], true)) $this->notify('upload', $k, $c, array_filter([$this->other($k, $c->uid)]), ['extra' => self::safeName($original)]);
        return $id;
    }
    public function file(Ctx $c, int $fid): array
    {
        $f = $this->need($this->s->one('files', ['id' => $fid])); $k = $this->load((int)$f['contract_id']);
        if (!$this->canSee($c, $k)) $this->deny(); return $f;
    }
    public function removeFile(Ctx $c, int $cid, int $fid): ?string
    {
        $k = $this->load($cid); if (!$this->isOwner($c, $k) && !$c->admin) $this->deny();
        $f = $this->s->one('files', ['id' => $fid, 'contract_id' => $cid]); if (!$f) return null; $this->s->delete('files', $fid); return $f['stored_name'];
    }

    /* ---------------------------------- invoices ---------------------------------- */

    public function invoice(int $id): array { return $this->need($this->s->one('invoices', ['id' => $id])); }
    private function createInvoice(array $k, ?array $t, float $amount): int
    {
        $ex = $t ? $this->s->one('invoices', ['todo_id' => $t['id']]) : (array_values(array_filter($this->s->find('invoices', ['contract_id' => $k['id']]), fn($i) => $i['todo_id'] === null))[0] ?? null);
        if ($ex) return (int)$ex['id'];
        $id = $this->s->insert('invoices', ['contract_id' => $k['id'], 'todo_id' => $t['id'] ?? null, 'payer_id' => $k['user_id'], 'payee_id' => $k['contractor_id'], 'number' => null, 'amount' => round($amount, 2), 'currency' => $k['currency'], 'status' => 'waiting', 'created_at' => $this->now(), 'paid_at' => null]);
        $this->s->update('invoices', $id, ['number' => self::INVOICE_PREFIX . $id]); return $id;
    }
    public function invoiceLine(array $i): string { return self::symbol($i['currency']) . ' ' . number_format((float)$i['amount'], 2, '.', ' ') . ' (' . ($i['number'] ?: self::INVOICE_PREFIX . $i['id']) . ')'; }
    private function hasUnpaid(int $cid): bool { return (bool)$this->s->find('invoices', ['contract_id' => $cid, 'status' => ['waiting', 'pending']], 'id', 1); }

    /** My invoices. tab pay (I am the payer) | receive; q: contract title / step name / invoice number; sort date|contract; dir asc|desc. */
    public function invoices(Ctx $c, string $tab, string $q = '', string $sort = 'date', string $dir = 'desc'): array
    {
        $tab = $tab === 'receive' ? 'receive' : 'pay'; $dir = $dir === 'asc' ? 'asc' : 'desc'; $sort = $sort === 'contract' ? 'contract' : 'date'; $q = mb_strtolower(trim($q));
        $list = $this->s->find('invoices', [$tab === 'pay' ? 'payer_id' : 'payee_id' => $c->uid], 'id', 2000); $out = [];
        foreach ($list as $i) {
            $k = $this->contract((int)$i['contract_id']); if (!$k) continue; $t = $i['todo_id'] ? $this->s->one('todos', ['id' => $i['todo_id']]) : null;
            $i += ['contract_title' => $k['title'], 'todo_name' => $t['name'] ?? null, 'todo_pos' => $t ? (int)$t['position'] : null, 'steps' => count($this->s->find('todos', ['contract_id' => $k['id']]))];
            if ($q !== '' && !str_contains(mb_strtolower($i['contract_title'] . ' ' . ($i['todo_name'] ?? '') . ' ' . $i['number']), $q)) continue;
            $out[] = $i;
        }
        usort($out, function ($a, $b) use ($sort, $dir) { $r = $sort === 'contract' ? [mb_strtolower($a['contract_title']), -(int)$a['id']] <=> [mb_strtolower($b['contract_title']), -(int)$b['id']] : [$a['created_at'], (int)$a['id']] <=> [$b['created_at'], (int)$b['id']]; return $dir === 'asc' ? $r : -$r; });
        return $out;
    }
    public function invoiceCounts(Ctx $c): array
    {
        return ['pay' => count($this->s->find('invoices', ['payer_id' => $c->uid, 'status' => 'waiting'])), 'receive' => count($this->s->find('invoices', ['payee_id' => $c->uid, 'status' => 'pending']))];
    }
    /** Invoices block inside a contract: the payer and the payee see theirs, an administrator all. */
    public function contractInvoices(Ctx $c, int $cid): array
    {
        $out = []; $steps = array_column($this->s->find('todos', ['contract_id' => $cid]), null, 'id');
        foreach ($this->s->find('invoices', ['contract_id' => $cid]) as $i) if ($c->admin || (int)$i['payer_id'] === $c->uid || (int)$i['payee_id'] === $c->uid) $out[] = $i + ['todo_name' => $i['todo_id'] ? ($steps[$i['todo_id']]['name'] ?? null) : null];
        return $out;
    }
    public function invoicePage(Ctx $c, int $id): array
    {
        $i = $this->invoice($id); if (!$c->admin && (int)$i['payer_id'] !== $c->uid && (int)$i['payee_id'] !== $c->uid) $this->deny();
        $k = $this->load((int)$i['contract_id']); $names = $this->userInfo([(int)$i['payer_id'], (int)$i['payee_id']]); $t = $i['todo_id'] ? $this->s->one('todos', ['id' => $i['todo_id']]) : null;
        return ['invoice' => $i, 'contract' => $k, 'step' => $t, 'payer' => $names[(int)$i['payer_id']], 'payee' => $names[(int)$i['payee_id']], 'canPay' => (int)$i['payer_id'] === $c->uid && $i['status'] === 'waiting', 'canConfirm' => (int)$i['payee_id'] === $c->uid && $i['status'] === 'pending', 'canNumber' => (int)$i['payee_id'] === $c->uid && $i['status'] !== 'paid'];
    }
    /** The payer says "I paid": the invoice waits for the payee to confirm (the money moves between the two, outside the platform). */
    public function payInvoice(Ctx $c, int $id): void
    {
        $i = $this->invoice($id); if ((int)$i['payer_id'] !== $c->uid || $i['status'] !== 'waiting') $this->deny();
        $this->s->update('invoices', $id, ['status' => 'pending']); $this->notify('invoice_paid', $this->load((int)$i['contract_id']), $c, [(int)$i['payee_id']], ['url' => 'invoices?tab=receive', 'extra' => $this->invoiceLine($i)]);
    }
    public function confirmInvoice(Ctx $c, int $id): void
    {
        $i = $this->invoice($id); if ((int)$i['payee_id'] !== $c->uid || $i['status'] !== 'pending') $this->deny();
        $this->s->update('invoices', $id, ['status' => 'paid', 'paid_at' => $this->now()]); $this->notify('invoice_confirmed', $this->load((int)$i['contract_id']), $c, [(int)$i['payer_id']], ['url' => 'invoices?tab=pay', 'extra' => $this->invoiceLine($i)]);
    }
    /** The contractor (issuer) may replace the default CT-<id> number until the invoice is paid. */
    public function setNumber(Ctx $c, int $id, string $num): void
    {
        $i = $this->invoice($id); if ((int)$i['payee_id'] !== $c->uid || $i['status'] === 'paid') $this->deny();
        $num = trim($num); if ($num === '' || mb_strlen($num) > 40 || preg_match('/[\x00-\x1F<>"\']/', $num)) throw new Err('invoice_number');
        foreach ($this->s->find('invoices', ['number' => $num]) as $x) if ((int)$x['id'] !== $id) throw new Err('invoice_number_used');
        $this->s->update('invoices', $id, ['number' => $num]);
    }

    /* ---------------------------------- notifications ---------------------------------- */

    /** Tells other users about an action: a bell entry (always) and an e-mail (the callback decides, the profile may turn it off). The actor never notifies themselves. $actor null = the system. */
    public function notify(string $kind, array $k, ?Ctx $actor, array $to, array $o = []): void
    {
        $to = array_values(array_unique(array_filter(array_map('intval', $to), fn($id) => $id > 0 && (!$actor || $id !== $actor->uid)))); if (!$to) return;
        $who = $actor ? ($actor->name !== '' ? $actor->name : $this->userName($actor->uid)) : ''; $path = (string)($o['url'] ?? ('view/' . $k['id']));
        $data = ['who' => $who, 'title' => (string)$k['title'], 'step' => (string)($o['step'] ?? ''), 'extra' => (string)($o['extra'] ?? ''), 'comment' => (string)($o['comment'] ?? '')];
        foreach ($to as $uid) {
            $this->s->insert('notices', ['user_id' => $uid, 'kind' => $kind, 'contract_id' => $k['id'], 'data' => json_encode($data, JSON_UNESCAPED_UNICODE), 'url' => $path, 'created_at' => $this->now(), 'read_at' => null]);
            if ($this->mail && (int)$this->profile($uid)['notify_email'] === 1) { try { ($this->mail)($uid, $kind, $data, $path); } catch (\Throwable $e) { /* a mail failure never blocks the action */ } }
        }
    }
    public function notices(Ctx $c, int $limit = 100): array { return $this->s->find('notices', ['user_id' => $c->uid], 'id DESC', $limit); }
    public function unread(int $uid): int { return count($this->s->find('notices', ['user_id' => $uid, 'read_at' => null])); }
    public function markRead(Ctx $c): void { foreach ($this->s->find('notices', ['user_id' => $c->uid, 'read_at' => null]) as $n) $this->s->update('notices', (int)$n['id'], ['read_at' => $this->now()]); }
    /** Clear one notice ($id > 0) or all of them. */
    public function clearNotices(Ctx $c, int $id = 0): void
    {
        foreach ($this->s->find('notices', ['user_id' => $c->uid]) as $n) if ($id === 0 || (int)$n['id'] === $id) $this->s->delete('notices', (int)$n['id']);
    }

    /* ---------------------------------- profile fields (admin-defined) ---------------------------------- */

    /** Sections with their fields; $types limits to the sections of those statuses (empty = all). */
    public function structure(array $types = []): array
    {
        $secs = $this->s->find('psections', [], 'position'); $by = [];
        foreach ($this->s->find('pfields', [], 'position') as $f) $by[(int)$f['section_id']][] = $f;
        $out = []; foreach ($secs as $s) if (!$types || in_array($s['type'], $types, true)) $out[] = $s + ['fields' => $by[(int)$s['id']] ?? []];
        usort($out, fn($a, $b) => [(int)$a['position'], (int)$a['id']] <=> [(int)$b['position'], (int)$b['id']]); return $out;
    }
    public function saveSection(int $id, string $title, string $type, int $pos): int
    {
        $title = trim($title); if ($title === '' || mb_strlen($title) > 120) throw new Err('section_title');
        $row = ['title' => $title, 'type' => in_array($type, self::PARTY_TYPES, true) ? $type : 'personal', 'position' => $pos];
        if ($id) { $this->s->update('psections', $id, $row); return $id; } return $this->s->insert('psections', $row);
    }
    public function deleteSection(int $id): void
    {
        $this->s->atomic(function () use ($id) { foreach ($this->s->find('pfields', ['section_id' => $id]) as $f) $this->deleteField((int)$f['id']); $this->s->delete('psections', $id); });
    }
    public function saveField(int $id, int $section, string $label, string $type, string $options, bool $required, bool $public, int $pos): int
    {
        $label = trim($label); if ($label === '' || mb_strlen($label) > 120 || !$this->s->one('psections', ['id' => $section])) throw new Err('field_label');
        $type = in_array($type, self::FIELD_TYPES, true) ? $type : 'text';
        $row = ['section_id' => $section, 'label' => $label, 'type' => $type, 'options' => $type === 'select' ? mb_substr(trim($options), 0, 4000) : null, 'required' => $required ? 1 : 0, 'default_public' => $public ? 1 : 0, 'position' => $pos];
        if ($id) { $this->s->update('pfields', $id, $row); return $id; } return $this->s->insert('pfields', $row);
    }
    public function deleteField(int $id): void
    {
        foreach ($this->s->find('pvalues', ['field_id' => $id]) as $v) $this->s->delete('pvalues', (int)$v['id']); $this->s->delete('pfields', $id);
    }
    /** [field_id => row] of one user */
    public function values(int $uid): array { $o = []; foreach ($this->s->find('pvalues', ['user_id' => $uid]) as $r) $o[(int)$r['field_id']] = $r; return $o; }
    public static function options(array $f): array { return array_values(array_filter(array_map('trim', preg_split('/\R/', (string)$f['options'])), fn($o) => $o !== '')); }
    /** $post: [field_id => ['value' => ..., 'public' => 1]]. Only the fields of the user's statuses are written; a required one must be filled. */
    public function saveValues(int $uid, array $types, array $post): void
    {
        $have = $this->values($uid);
        foreach ($this->structure($types) as $sec) foreach ($sec['fields'] as $f) {
            $fid = (int)$f['id']; if (!isset($post[$fid]) && !isset($post[(string)$fid])) { if ($f['required'] && trim((string)($have[$fid]['value'] ?? '')) === '' && $post) throw new Err('field_required', $f['label']); continue; }
            $in = $post[$fid] ?? $post[(string)$fid]; $v = trim((string)($in['value'] ?? ''));
            if ($f['required'] && $v === '') throw new Err('field_required', $f['label']);
            if ($v !== '') {
                if ($f['type'] === 'number' && !is_numeric($v)) throw new Err('field_invalid', $f['label']);
                if ($f['type'] === 'url' && !preg_match('#^https?://#i', $v)) throw new Err('field_invalid', $f['label']);
                if ($f['type'] === 'date' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) throw new Err('field_invalid', $f['label']);
                if ($f['type'] === 'select' && !in_array($v, self::options($f), true)) throw new Err('field_invalid', $f['label']);
            }
            $row = ['value' => mb_substr($v, 0, 4000), 'is_public' => !empty($in['public']) ? 1 : 0];
            if (isset($have[$fid]) && (string)$have[$fid]['value'] === $row['value'] && (int)$have[$fid]['is_public'] === $row['is_public']) continue; // unchanged: keep its change time (the newest change wins when syncing with Bablito)
            $row['updated_at'] = gmdate('Y-m-d H:i:s', ($this->now)());
            if (isset($have[$fid])) $this->s->update('pvalues', (int)$have[$fid]['id'], $row); else $this->s->insert('pvalues', $row + ['user_id' => $uid, 'field_id' => $fid]);
        }
    }
    /** Admin export: every field of every status, for importing into another project. Excel-friendly (BOM, ";"). */
    public function fieldsCsv(): string
    {
        $ru = ['self' => 'Самозанятый', 'company' => 'Компания', 'personal' => 'Физлицо']; $names = ['text' => 'Text', 'textarea' => 'Long text', 'number' => 'Number', 'url' => 'Link (URL)', 'date' => 'Date', 'select' => 'Choice list'];
        $secs = $this->structure(); usort($secs, fn($a, $b) => [array_search($a['type'], ['self', 'company', 'personal']), (int)$a['position'], (int)$a['id']] <=> [array_search($b['type'], ['self', 'company', 'personal']), (int)$b['position'], (int)$b['id']]);
        $h = fopen('php://temp', 'w+'); fwrite($h, "\xEF\xBB\xBF");
        fputcsv($h, ['status', 'status_ru', 'section_id', 'section', 'section_position', 'field_id', 'label', 'field_type', 'field_type_name', 'options', 'required', 'public_by_default', 'field_position'], ';', '"', '');
        foreach ($secs as $s) foreach ($s['fields'] as $f) fputcsv($h, [$s['type'], $ru[$s['type']] ?? $s['type'], $s['id'], $s['title'], $s['position'], $f['id'], $f['label'], $f['type'], $names[$f['type']] ?? $f['type'], str_replace(["\r\n", "\n"], ' | ', (string)$f['options']), $f['required'], $f['default_public'], $f['position']], ';', '"', '');
        rewind($h); $csv = stream_get_contents($h); fclose($h); return (string)$csv;
    }
}
