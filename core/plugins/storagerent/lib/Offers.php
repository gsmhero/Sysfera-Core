<?php
declare(strict_types=1);
namespace SysferaStoragerent;

/**
 * The Offers tab: listings of storage space for rent. Any signed-in Sysfera user can post; published listings are public (map, cards) and are also served as a JSON feed
 * that other Sysfera domains read, and this domain reads the feeds of the peers the administrator lists - that is how "all users of all domains" see the same offers.
 * The form is the spec's eight blocks; FIELDS lists every field of blocks 1-7 with its type so that the form, the validation and the card read one definition.
 */
final class Offers
{
    public const STATUSES = ['draft', 'published', 'archived'];
    public const MAX_PHOTOS = 12;
    public const PHOTO_MAX = 5 * 1024 * 1024;
    public const PHOTO_EXT = ['jpg', 'jpeg', 'png', 'webp'];
    /** key => [block, type]. Types: s = line, t = text, n = number (min..max), b = yes/no, h = HH:MM */
    public const FIELDS = [
        'area_useful' => [1, 'n', 0, 1000000], 'area_zones' => [1, 't', 1000], 'ceiling_height' => [1, 'n', 0, 100], 'column_step' => [1, 's', 40], 'location_note' => [1, 't', 1000],
        'floor_load' => [2, 'n', 0, 1000], 'floor_evenness' => [2, 's', 190], 'floor_cover' => [2, 's', 190],
        'power' => [3, 'b'], 'power_kw' => [3, 'n', 0, 1000000], 'water' => [3, 'b'], 'heating' => [3, 'b'], 'ventilation' => [3, 'b'],
        'fire_alarm' => [3, 'b'], 'fire_suppression' => [3, 'b'], 'video' => [3, 'b'], 'video_recorded' => [3, 'b'], 'security_alarm' => [3, 'b'], 'security_company' => [3, 'b'],
        'loading_equipment' => [3, 't', 1000], 'extra_racking' => [3, 'b'], 'extra_charging' => [3, 'b'], 'extra_accounting' => [3, 'b'], 'extra_text' => [3, 't', 1000],
        'zones' => [4, 't', 1000], 'parking' => [4, 't', 1000], 'fence' => [4, 'b'], 'lighting' => [4, 'b'], 'territory_text' => [4, 't', 1000],
        'purpose' => [5, 't', 1500], 'access_24h' => [6, 'b'], 'access_from' => [6, 'h'], 'access_to' => [6, 'h'], 'prohibitions' => [7, 't', 1500],
        // block 9: the contacts the author fills in; shown to signed-in users only, never in the public card or the feed
        'contact_person' => [9, 's', 120], 'contact_phone' => [9, 's', 60], 'contact_email' => [9, 's', 120], 'contact_hours' => [9, 's', 120], 'contact_text' => [9, 't', 1500],
    ];
    public const MAX_FAVORITES = 500;
    public const SERVICES = ['video', 'parking', 'h24', 'security'];

    public function __construct(private Store $st, private $now) {}

    private static function numberOf(mixed $v, float $min, float $max): float
    {
        $s = str_replace([',', ' '], ['.', ''], trim((string)$v)); if ($s === '') return 0.0;
        if (!is_numeric($s) || (float)$s < $min || (float)$s > $max) throw new Err('number_invalid'); return (float)$s;
    }
    /** Cleans the data of blocks 1-7 from a form. Unknown keys are dropped. */
    public static function cleanData(array $in): array
    {
        $out = [];
        foreach (self::FIELDS as $k => $def) {
            $v = $in[$k] ?? null;
            switch ($def[1]) {
                case 'b': $out[$k] = !empty($v) && $v !== '0' ? 1 : 0; break;
                case 'n': $out[$k] = self::numberOf($v ?? '', (float)$def[2], (float)$def[3]); break;
                case 's': case 't': $out[$k] = Wrms::clean($v ?? '', (int)$def[2]); break;
                case 'h': $s = trim((string)($v ?? '')); if ($s !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $s)) throw new Err('time_invalid'); $out[$k] = $s; break;
            }
        }
        if ($out['contact_email'] !== '' && !filter_var($out['contact_email'], FILTER_VALIDATE_EMAIL)) throw new Err('email_invalid');
        if ($out['access_24h']) { $out['access_from'] = ''; $out['access_to'] = ''; }
        elseif ($out['access_from'] === '' && $out['access_to'] !== '' || $out['access_from'] !== '' && $out['access_to'] === '') throw new Err('time_invalid');
        return $out;
    }
    /** Service icons of the card from the form data. */
    public static function services(array $d): array
    {
        return ['video' => !empty($d['video']), 'parking' => trim((string)($d['parking'] ?? '')) !== '', 'h24' => !empty($d['access_24h']), 'security' => !empty($d['security_alarm']) || !empty($d['security_company'])];
    }

    /** Creates or updates a listing. $isAdmin lets a Sysfera administrator edit anyone's listing. */
    public function save(int $uid, bool $isAdmin, int $id, array $in, string $contactName = ''): array
    {
        if ($uid <= 0) throw new Err('forbidden', '', 403);
        $name = Wrms::clean($in['name'] ?? '', 190); if ($name === '') throw new Err('name_required');
        $city = Wrms::clean($in['city'] ?? '', 100); if ($city === '') throw new Err('city_required');
        $area = self::numberOf($in['area'] ?? '', 0, 1000000); if ($area <= 0) throw new Err('area_required');
        $price = Billing::kop($in['price_m2'] ?? '0') ?? throw new Err('amount_invalid'); if ($price < 0) throw new Err('amount_invalid');
        [$lat, $lon] = Wrms::latlon($in['lat'] ?? '', $in['lon'] ?? '');
        $status = (string)($in['status'] ?? 'published'); if (!in_array($status, self::STATUSES, true)) throw new Err('status_invalid');
        $data = self::cleanData($in); $svc = self::services($data); $n = ($this->now)();
        $row = ['name' => $name, 'city' => $city, 'street' => Wrms::clean($in['street'] ?? '', 190), 'lat' => $lat, 'lon' => $lon, 'area' => $area, 'price_m2' => $price, 'status' => $status,
            'svc_video' => (int)$svc['video'], 'svc_parking' => (int)$svc['parking'], 'svc_24h' => (int)$svc['h24'], 'svc_security' => (int)$svc['security'], 'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'warehouse_id' => ($w = (int)($in['warehouse_id'] ?? 0)) > 0 ? $w : null, 'updated_at' => $n];
        if ($id > 0) {
            $old = $this->st->one('listings', ['id' => $id]) ?? throw new Err('not_found', '', 404);
            if ((int)$old['user_id'] !== $uid && !$isAdmin) throw new Err('forbidden', '', 403);
            $this->st->update('listings', $id, $row);
        } else $id = $this->st->insert('listings', $row + ['user_id' => $uid, 'contact_name' => Wrms::clean($contactName, 120), 'created_at' => $n]);
        return $this->get($id);
    }
    public function setStatus(int $uid, bool $isAdmin, int $id, string $status): void
    {
        if (!in_array($status, self::STATUSES, true)) throw new Err('status_invalid'); $l = $this->st->one('listings', ['id' => $id]) ?? throw new Err('not_found', '', 404);
        if ((int)$l['user_id'] !== $uid && !$isAdmin) throw new Err('forbidden', '', 403); $this->st->update('listings', $id, ['status' => $status, 'updated_at' => ($this->now)()]);
    }
    public function delete(int $uid, bool $isAdmin, int $id): array
    {
        $l = $this->st->one('listings', ['id' => $id]) ?? throw new Err('not_found', '', 404); if ((int)$l['user_id'] !== $uid && !$isAdmin) throw new Err('forbidden', '', 403);
        $photos = $this->photos($id); foreach ($photos as $p) $this->st->delete('listing_photos', (int)$p['id']); $this->st->delete('listings', $id); return $photos;
    }
    public function get(int $id): ?array
    {
        $l = $this->st->one('listings', ['id' => $id]); if (!$l) return null;
        return $l + ['d' => json_decode((string)$l['data'], true) ?: [], 'photos' => $this->photos($id)];
    }
    public function photos(int $id): array { return $this->st->find('listing_photos', ['listing_id' => $id], 'sort'); }
    public function addPhoto(int $uid, bool $isAdmin, int $listingId, string $stored, string $mime, int $size): int
    {
        $l = $this->st->one('listings', ['id' => $listingId]) ?? throw new Err('not_found', '', 404); if ((int)$l['user_id'] !== $uid && !$isAdmin) throw new Err('forbidden', '', 403);
        $have = $this->photos($listingId); if (count($have) >= self::MAX_PHOTOS) throw new Err('photos_max');
        return $this->st->insert('listing_photos', ['listing_id' => $listingId, 'file_key' => $stored, 'mime' => $mime, 'size' => $size, 'sort' => count($have), 'created_at' => ($this->now)()]);
    }
    public function removePhoto(int $uid, bool $isAdmin, int $photoId): ?array
    {
        $p = $this->st->one('listing_photos', ['id' => $photoId]) ?? throw new Err('not_found', '', 404); $l = $this->st->one('listings', ['id' => (int)$p['listing_id']]);
        if (!$l || ((int)$l['user_id'] !== $uid && !$isAdmin)) throw new Err('forbidden', '', 403); $this->st->delete('listing_photos', $photoId); return $p;
    }
    public static function checkPhoto(string $name, int $size): string
    {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION)); if (!in_array($ext, self::PHOTO_EXT, true)) throw new Err('file_type');
        if ($size <= 0) throw new Err('file_empty'); if ($size > self::PHOTO_MAX) throw new Err('file_big'); return $ext;
    }

    /** Listings for lists and the map. $uid > 0 + $mine = only that user's (any status); otherwise published only. $q searches name, city, street. */
    public function listings(string $q = '', ?int $mineOf = null): array
    {
        $rows = $this->st->find('listings', $mineOf ? ['user_id' => $mineOf] : ['status' => 'published'], 'id DESC', 2000); $q = trim($q);
        if ($q !== '') $rows = array_values(array_filter($rows, fn($l) => stripos($l['name'] . ' ' . $l['city'] . ' ' . $l['street'], $q) !== false));
        return $rows;
    }
    /** The public card: only what a stranger may see (no user ids, no contact details). $photoUrl: callable(array photoRow, int listingId):string. */
    public function card(array $l, callable $photoUrl, string $origin = ''): array
    {
        $ph = $this->photos((int)$l['id']);
        return ['id' => (int)$l['id'], 'origin' => $origin, 'name' => (string)$l['name'], 'city' => (string)$l['city'], 'street' => (string)$l['street'],
            'lat' => $l['lat'] !== null && $l['lat'] !== '' ? (float)$l['lat'] : null, 'lon' => $l['lon'] !== null && $l['lon'] !== '' ? (float)$l['lon'] : null,
            'area' => (float)$l['area'], 'price_m2' => (int)$l['price_m2'], 'services' => ['video' => (bool)$l['svc_video'], 'parking' => (bool)$l['svc_parking'], 'h24' => (bool)$l['svc_24h'], 'security' => (bool)$l['svc_security']],
            'photo' => $ph ? $photoUrl($ph[0], (int)$l['id']) : '', 'updated_at' => (int)$l['updated_at']];
    }
    public function cards(array $listings, callable $photoUrl): array { return array_map(fn($l) => $this->card($l, $photoUrl), $listings); }

    /** True when the listing has anything in the Contacts block. */
    public static function hasContacts(array $d): bool { foreach (['contact_person', 'contact_phone', 'contact_email', 'contact_hours', 'contact_text'] as $k) if (trim((string)($d[$k] ?? '')) !== '') return true; return false; }

    /* ================================================================== favorites */
    /** The key of a card in the favorites: L<id> for this domain, R<hash of the origin>-<id> for a listing of another domain. */
    public static function ref(array $card): string
    {
        return empty($card['origin']) ? 'L' . (int)$card['id'] : 'R' . substr(sha1((string)$card['origin']), 0, 12) . '-' . (int)$card['id'];
    }
    /** [ref => true] of the user, for the hearts in the lists. */
    public function favoriteRefs(int $uid): array
    {
        if ($uid <= 0) return []; $out = []; foreach ($this->st->find('favorites', ['user_id' => $uid], 'id DESC', self::MAX_FAVORITES + 1) as $r) $out[(string)$r['ref']] = true; return $out;
    }
    /** Adds or removes a favorite. Returns true when the listing is now a favorite. $remote: the cards of other domains (to find a "R..." ref). */
    public function toggleFavorite(int $uid, string $ref, callable $photoUrl, array $remote = []): bool
    {
        if ($uid <= 0) throw new Err('forbidden', '', 403);
        if (!preg_match('/^(L\d{1,10}|R[0-9a-f]{12}-\d{1,10})$/', $ref)) throw new Err('not_found', '', 404);
        $have = $this->st->one('favorites', ['user_id' => $uid, 'ref' => $ref]);
        if ($have) { $this->st->delete('favorites', (int)$have['id']); return false; }
        if (count($this->st->find('favorites', ['user_id' => $uid], 'id', self::MAX_FAVORITES + 1)) >= self::MAX_FAVORITES) throw new Err('favorites_max');
        $card = null;
        if ($ref[0] === 'L') { $l = $this->get((int)substr($ref, 1)); if ($l && $l['status'] === 'published') $card = $this->card($l, $photoUrl); }
        else foreach ($remote as $c) if (self::ref($c) === $ref) { $card = $c; break; }
        if ($card === null) throw new Err('not_found', '', 404);
        $this->st->insert('favorites', ['user_id' => $uid, 'ref' => $ref, 'snapshot' => json_encode($card, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'created_at' => ($this->now)()]);
        return true;
    }
    /** The favorites of a user, newest first: [['ref', 'card', 'gone' => the local listing was removed or unpublished]]. A local card is read live; a remote one is the saved snapshot. */
    public function favorites(int $uid, callable $photoUrl): array
    {
        $out = [];
        foreach ($uid > 0 ? $this->st->find('favorites', ['user_id' => $uid], 'id DESC', self::MAX_FAVORITES) : [] as $r) {
            $snap = json_decode((string)$r['snapshot'], true); if (!is_array($snap)) continue; $ref = (string)$r['ref']; $gone = false;
            if ($ref[0] === 'L') { $l = $this->get((int)substr($ref, 1)); if ($l && $l['status'] === 'published') $snap = $this->card($l, $photoUrl); else $gone = true; }
            $out[] = ['ref' => $ref, 'card' => $snap + ['origin' => '', 'url' => null, 'photo' => '', 'services' => [], 'city' => '', 'street' => '', 'name' => '', 'area' => 0, 'price_m2' => 0], 'gone' => $gone];
        }
        return $out;
    }

    /** Form defaults from the user's WRMS data: a warehouse and (optionally) some of its units. */
    public function prefill(array $warehouse, array $units): array
    {
        $area = 0.0; $h = 0.0; $load = 0.0; $feat = [];
        foreach ($units as $u) { $area += (float)$u['area']; $h = max($h, (float)$u['height']); $load = $load === 0.0 ? (float)$u['load_t'] : min($load, (float)$u['load_t'] ?: $load); foreach (explode(',', (string)($u['features'] ?? '')) as $f) if ($f !== '') $feat[$f] = 1; }
        $d = ['name' => (string)$warehouse['name'], 'city' => (string)$warehouse['city'], 'street' => (string)$warehouse['address'], 'lat' => (string)($warehouse['lat'] ?? ''), 'lon' => (string)($warehouse['lon'] ?? ''), 'warehouse_id' => (int)$warehouse['id'],
            'area' => $area > 0 ? rtrim(rtrim(number_format($area, 2, '.', ''), '0'), '.') : '', 'ceiling_height' => $h > 0 ? (string)$h : '', 'floor_load' => $load > 0 ? (string)$load : ''];
        foreach (['power' => 'power', 'heating' => 'heating', 'camera' => 'video', 'fire' => 'fire_alarm', 'racking' => 'extra_racking'] as $from => $to) if (isset($feat[$from])) $d[$to] = 1;
        return $d;
    }

    /* ================================================================== feeds between domains */
    /** The JSON of the public feed: published listings of this domain. */
    public function feed(callable $photoUrl, string $site): array
    {
        $cards = $this->cards($this->listings(), $photoUrl); $url = fn($c) => $c;
        return ['v' => 1, 'site' => $site, 'generated' => ($this->now)(), 'listings' => array_map($url, $cards)];
    }
    /** Cleans a feed received from another domain: it is untrusted. Returns at most 300 cards; links only http(s); strings cut; numbers checked. */
    public static function parseFeed(string $json, string $origin): array
    {
        $j = json_decode($json, true); if (!is_array($j) || (int)($j['v'] ?? 0) !== 1 || !is_array($j['listings'] ?? null)) return [];
        $out = []; $link = fn($u) => is_string($u) && preg_match('~^https?://[^\s<>"\']{1,500}$~i', $u) ? $u : '';
        foreach (array_slice($j['listings'], 0, 300) as $c) {
            if (!is_array($c) || !is_numeric($c['id'] ?? null)) continue;
            $name = Wrms::clean($c['name'] ?? '', 190); if ($name === '') continue;
            $lat = is_numeric($c['lat'] ?? null) && abs((float)$c['lat']) <= 90 ? (float)$c['lat'] : null; $lon = is_numeric($c['lon'] ?? null) && abs((float)$c['lon']) <= 180 ? (float)$c['lon'] : null;
            if ($lat === null || $lon === null) { $lat = $lon = null; }
            $s = is_array($c['services'] ?? null) ? $c['services'] : [];
            $out[] = ['id' => (int)$c['id'], 'origin' => $origin, 'name' => $name, 'city' => Wrms::clean($c['city'] ?? '', 100), 'street' => Wrms::clean($c['street'] ?? '', 190), 'lat' => $lat, 'lon' => $lon,
                'area' => is_numeric($c['area'] ?? null) ? max(0.0, min(1e7, (float)$c['area'])) : 0.0, 'price_m2' => is_numeric($c['price_m2'] ?? null) ? max(0, min(10 ** 12, (int)$c['price_m2'])) : 0,
                'services' => ['video' => !empty($s['video']), 'parking' => !empty($s['parking']), 'h24' => !empty($s['h24']), 'security' => !empty($s['security'])],
                'photo' => $link($c['photo'] ?? ''), 'url' => $link($c['url'] ?? ''), 'updated_at' => (int)($c['updated_at'] ?? 0)];
        }
        return $out;
    }
    /** Cards of the peer domains (cached for $ttl seconds). $fetch(url): ?string body or null on failure. Peers are https URLs of feeds the administrator listed. */
    public function remote(array $peers, callable $fetch, int $ttl = 900): array
    {
        $out = []; $n = ($this->now)();
        foreach (array_slice($peers, 0, 10) as $url) {
            if (!preg_match('~^https://[^\s<>"\']{4,250}$~i', $url)) continue; $h = sha1($url); $row = $this->st->one('remote_cache', ['url_hash' => $h]);
            if (!$row || $n - (int)$row['fetched_at'] > $ttl) {
                $body = $fetch($url);
                if ($body !== null) { if ($row) $this->st->update('remote_cache', (int)$row['id'], ['fetched_at' => $n, 'body' => $body]); else $this->st->insert('remote_cache', ['url_hash' => $h, 'url' => $url, 'fetched_at' => $n, 'body' => $body]); $row = ['body' => $body]; }
                elseif ($row) $this->st->update('remote_cache', (int)$row['id'], ['fetched_at' => $n - $ttl + 120]);   // keep the old copy, try again in two minutes
            }
            if ($row && ($row['body'] ?? null) !== null) $out = array_merge($out, self::parseFeed((string)$row['body'], (string)(parse_url($url, PHP_URL_HOST) ?: $url)));
        }
        return $out;
    }
}
