<?php
declare(strict_types=1);
namespace SysferaMsg;

/**
 * An installation's connection to the hub. On the hub site itself calls go straight to the HubServer (no HTTP round trip);
 * everywhere else they are signed HTTPS requests to <hub>/api/hub/<op>. Never throws:
 * returns ['ok' => bool, 'data' => array, 'error' => ?string(key), 'status' => int].
 */
final class Client
{
    /** @param \Closure(string,string=):string $cfg setting reader  @param \Closure():?HubServer $local the hub when this site is one */
    public function __construct(private \Closure $cfg, private \Closure $local, private \Closure $uid, private string $host) {}

    public function isHub(): bool { return ($this->local)() !== null; }
    public function ready(): bool { return $this->isHub() || (($this->cfg)('hub_key', '') !== '' && $this->base() !== ''); }
    public function domain(): string { return $this->host; }
    public function me(): string { return (string)($this->uid)() . '@' . $this->host; }

    public function base(): string
    {
        $u = rtrim(trim(($this->cfg)('hub_url', 'https://core.sysfera.ru')), '/');
        $p = parse_url($u);
        if (!$p || empty($p['host']) || !in_array($p['scheme'] ?? '', ['https', 'http'], true)) return '';
        if (($p['scheme'] ?? '') === 'http' && !in_array($p['host'], ['localhost', '127.0.0.1'], true)) return '';
        return $u;
    }

    /** @param array|null $body JSON body  @param string|null $raw raw bytes (uploads) */
    public function call(string $method, string $op, array $query = [], ?array $body = null, ?string $raw = null, int $timeout = 8, ?string $asUid = null): array
    {
        $uid = $asUid ?? (string)($this->uid)();   // $asUid '0' = the site itself (no signed-in user)
        if (!$this->ready()) return $this->fail('not_connected', 0);
        if (!preg_match('/^\d{1,10}$/', $uid) || ($uid === '0' && $asUid !== '0')) return $this->fail('bad_user', 0);

        if (($hub = ($this->local)()) !== null) {
            try {
                $r = $hub->dispatch($op, new Ctx($this->host, $uid, array_map('strval', $query), $body ?? [], $raw ?? ''));
                return ['ok' => true, 'data' => $r, 'error' => null, 'status' => 200];
            } catch (HubError $e) { return $this->fail($e->key, $e->status); }
        }

        $payload = $raw ?? ($body === null ? '' : (string)json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $url = $this->base() . '/api/hub/' . $op . ($query ? '?' . http_build_query($query) : '');
        $uri = (string)parse_url($url, PHP_URL_PATH) . (parse_url($url, PHP_URL_QUERY) !== null ? '?' . parse_url($url, PHP_URL_QUERY) : '');
        $ts = time();
        $headers = ['Content-Type: ' . ($raw !== null ? 'application/octet-stream' : 'application/json'), 'Accept: application/json',
            'X-SF-Domain: ' . $this->host, 'X-SF-User: ' . $uid, 'X-SF-Timestamp: ' . $ts, 'X-SF-Signature: ' . Sig::sign(($this->cfg)('hub_key', ''), $ts, $method, $uri, $uid, $payload)];
        [$code, $res, $err] = self::http(strtoupper($method), $url, $headers, $payload, $timeout);
        $data = json_decode($res, true); if (!is_array($data)) $data = [];
        if ($err !== null) return $this->fail('network', 0);
        if ($code < 200 || $code >= 300) return $this->fail((string)($data['error'] ?? 'http_' . $code), $code);
        return ['ok' => true, 'data' => $data, 'error' => null, 'status' => $code];
    }

    /** Registers this site with the hub (proves it owns the domain, receives the shared key). */
    public function connect(string $base, string $challenge): array
    {
        $b = $this->base(); if ($b === '') return $this->fail('bad_hub', 0);
        $payload = (string)json_encode(['base' => $base, 'challenge' => $challenge]);
        [$code, $res, $err] = self::http('POST', $b . '/api/hub/register', ['Content-Type: application/json', 'Accept: application/json'], $payload, 15);
        $data = json_decode($res, true); if (!is_array($data)) $data = [];
        if ($err !== null) return $this->fail('network', 0);
        if ($code < 200 || $code >= 300 || empty($data['key'])) return $this->fail((string)($data['error'] ?? 'http_' . $code), $code);
        return ['ok' => true, 'data' => $data, 'error' => null, 'status' => $code];
    }

    private function fail(string $key, int $status): array { return ['ok' => false, 'data' => [], 'error' => $key, 'status' => $status]; }

    /** @return array{0:int,1:string,2:?string} code, body, transport error */
    public static function http(string $method, string $url, array $headers, string $payload, int $timeout, ?string $resolve = null): array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_FOLLOWLOCATION => false, CURLOPT_MAXFILESIZE => 12000000]);
            if ($payload !== '') curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            if ($resolve) curl_setopt($ch, CURLOPT_RESOLVE, [$resolve]);
            $r = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE); $err = $r === false ? (curl_error($ch) ?: 'network') : null;
            curl_close($ch);
            return [$code, $r === false ? '' : (string)$r, $err];
        }
        $ctx = stream_context_create(['http' => ['method' => $method, 'header' => implode("\r\n", $headers) . "\r\n", 'content' => $payload, 'timeout' => $timeout, 'ignore_errors' => true, 'follow_location' => 0]]);
        $r = @file_get_contents($url, false, $ctx); $code = 0;
        foreach ($http_response_header ?? [] as $h) if (preg_match('~^HTTP/\S+ (\d{3})~', $h, $m)) $code = (int)$m[1];
        return [$code, $r === false ? '' : $r, $r === false ? 'network' : null];
    }
}
