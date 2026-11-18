<?php
declare(strict_types=1);
namespace SysferaContracts;

/**
 * Client of the Bablito.ru machine API (signed requests). signature = hex(HMAC-SHA256(key, ts "\n" METHOD "\n" request-uri "\n" sha256hex(body))),
 * headers X-Sysfera-Timestamp / X-Sysfera-Signature. The key is never logged or shown. $http(method, url, headers[], body): [status, body] does the transfer.
 */
final class Bablito
{
    public function __construct(private string $base, private string $key, private $http, private $now) {}

    public static function sig(string $key, string $ts, string $method, string $uri, string $body): string
    {
        return hash_hmac('sha256', $ts . "\n" . strtoupper($method) . "\n" . $uri . "\n" . hash('sha256', $body), $key);
    }
    public static function validBase(string $url): bool
    {
        $p = parse_url($url); return is_array($p) && ($p['scheme'] ?? '') === 'https' && !empty($p['host']) && empty($p['user']) && empty($p['query']) && empty($p['fragment']);
    }

    /** @return array decoded JSON; throws Err('bablito_*') */
    public function call(string $method, string $path, ?array $body = null): array
    {
        if (!self::validBase($this->base) || strlen($this->key) < 32) throw new Err('bablito_not_configured');
        $raw = $body === null ? '' : (string)json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $uri = (string)parse_url($this->base, PHP_URL_PATH); $uri = rtrim($uri, '/') . $path; $ts = (string)($this->now)();
        $headers = ['X-Sysfera-Timestamp: ' . $ts, 'X-Sysfera-Signature: ' . self::sig($this->key, $ts, $method, $uri, $raw), 'Accept: application/json'] + ($raw !== '' ? [3 => 'Content-Type: application/json'] : []);
        try { [$status, $resp] = ($this->http)($method, rtrim($this->base, '/') . $path, array_values($headers), $raw); }
        catch (\Throwable $e) { throw new Err('bablito_unreachable', $e->getMessage()); }
        $j = json_decode((string)$resp, true);
        if ($status === 401) throw new Err('bablito_bad_key', '', 502);
        if ($status === 404 && !is_array($j)) throw new Err('bablito_disabled', '', 502);
        if (!is_array($j) || $status >= 500) throw new Err('bablito_bad_answer', 'HTTP ' . $status, 502);
        if ($status >= 400 && $status !== 409) throw new Err('bablito_refused', (string)($j['detail'] ?? $j['error'] ?? 'HTTP ' . $status), 502);
        return $j + ['_status' => $status];
    }
    public function ping(): array { return $this->call('GET', '/api/sysfera/ping'); }
    public function fields(): array { return $this->call('GET', '/api/sysfera/fields')['sections'] ?? []; }
    public function user(string $email): ?array { return $this->call('GET', '/api/sysfera/users?email=' . rawurlencode(mb_strtolower(trim($email))))['user'] ?? null; }
    public function account(array $u, bool $link, bool $dry): array { return $this->call('POST', '/api/sysfera/users', ['email' => mb_strtolower(trim((string)$u['email'])), 'name' => $u['name'], 'types' => $u['types'], 'lang' => $u['lang'], 'sysfera_id' => $u['id'], 'link' => $link, 'dry_run' => $dry]); }
    public function base(): string { return rtrim($this->base, '/'); }
    /** One page (up to 100) of the contracts published on Bablito, ascending id, after the given id. */
    public function contracts(int $after): array { return $this->call('GET', '/api/sysfera/contracts?after=' . max(0, $after) . '&limit=100'); }
    /** Sends the full list of the published Sysfera contracts; Bablito shows them as read-only copies and drops the ones that are missing. */
    public function pushContracts(array $items, bool $dry): array { return $this->call('POST', '/api/sysfera/contracts', ['contracts' => $items, 'dry_run' => $dry]); }
    /** Address that signs the Bablito user in (valid 2 minutes, works once) and goes on to $path (a path on Bablito). */
    public function loginUrl(int $remoteId, string $path): string
    {
        if (!self::validBase($this->base) || strlen($this->key) < 32) throw new Err('bablito_not_configured');
        if (!preg_match('#^/(?!/)[A-Za-z0-9/_\-.?=&%]{0,200}$#', $path)) $path = '/contracts';
        $exp = ($this->now)() + 120; $nonce = bin2hex(random_bytes(16));
        $sig = hash_hmac('sha256', "login\n$remoteId\n$exp\n$nonce\n$path", $this->key);
        return rtrim($this->base, '/') . '/sysfera-login?' . http_build_query(['u' => $remoteId, 'e' => $exp, 'n' => $nonce, 'r' => $path, 's' => $sig]);
    }
    /** Makes an offer as the linked Bablito user (mode accept = the author's own prices; counter = per-step price/days by step number). */
    public function offer(int $remoteUser, int $contract, array $body): array { return $this->call('POST', '/api/sysfera/offers', ['user_id' => $remoteUser, 'contract_id' => $contract] + $body); }
    /** The offer of this user on this contract, or null. */
    public function myOffer(int $remoteUser, int $contract): ?array { return $this->call('GET', '/api/sysfera/offers?user_id=' . $remoteUser . '&contract_id=' . $contract)['offer'] ?? null; }
    public function profile(int $remoteId): array { return $this->call('GET', '/api/sysfera/profile/' . $remoteId); }
    public function pushProfile(int $remoteId, array $types, array $values, bool $dry): array { return $this->call('POST', '/api/sysfera/profile/' . $remoteId, ['types' => $types, 'values' => $values, 'dry_run' => $dry]); }
}
