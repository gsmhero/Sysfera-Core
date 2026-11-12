<?php
declare(strict_types=1);
namespace SysferaBb;

/** Signed HTTP client for Basebase Partner API v1. Never throws: returns ['code' => int, 'data' => array, 'error' => ?string]. */
final class Client
{
    /** @param \Closure(string,string=):string $cfg setting reader */
    public function __construct(private \Closure $cfg) {}

    public function configured(): bool { return (int)($this->cfg)('agent_id', '0') > 0 && ($this->cfg)('secret', '') !== '' && $this->base() !== ''; }
    public function base(): string
    {
        $u = rtrim(trim(($this->cfg)('api_url', 'https://www.basebase.ru/api/v1')), '/');
        $p = parse_url($u);
        if (!$p || empty($p['host']) || !in_array($p['scheme'] ?? '', ['https', 'http'], true)) return '';
        if (($p['scheme'] ?? '') === 'http' && !in_array($p['host'], ['localhost', '127.0.0.1'], true)) return '';   // HTTPS only (contract §3)
        return $u;
    }

    public function request(string $method, string $path, ?array $body = null, int $timeout = 6): array
    {
        if (!$this->configured()) return ['code' => 0, 'data' => [], 'error' => 'not_configured'];
        $method = strtoupper($method);
        $url = $this->base() . $path;
        $uri = (string)parse_url($url, PHP_URL_PATH) . (parse_url($url, PHP_URL_QUERY) !== null ? '?' . parse_url($url, PHP_URL_QUERY) : '');
        $json = $body === null ? '' : (string)json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
        $ts = time();
        $headers = ['Content-Type: application/json', 'Accept: application/json', 'X-BB-Agent: ' . (int)($this->cfg)('agent_id', '0'), 'X-BB-Timestamp: ' . $ts,
            'X-BB-Signature: ' . Bb::sign(($this->cfg)('secret', ''), $ts, $method, $uri, $json)];

        $code = 0; $raw = ''; $err = null;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_FOLLOWLOCATION => false]);
            if ($json !== '') curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
            $raw = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            if ($raw === false) { $err = curl_error($ch) ?: 'network'; $raw = ''; }
            curl_close($ch);
        } else {
            $ctx = stream_context_create(['http' => ['method' => $method, 'header' => implode("\r\n", $headers) . "\r\n", 'content' => $json, 'timeout' => $timeout, 'ignore_errors' => true, 'follow_location' => 0]]);
            $r = @file_get_contents($url, false, $ctx);
            if ($r === false) $err = 'network'; else $raw = $r;
            foreach ($http_response_header ?? [] as $h) if (preg_match('~^HTTP/\S+ (\d{3})~', $h, $m)) $code = (int)$m[1];
        }
        $data = json_decode($raw, true); if (!is_array($data)) $data = [];
        if ($err === null && ($code < 200 || $code >= 300)) $err = (string)($data['message'] ?? $data['error'] ?? ('HTTP ' . $code));
        return ['code' => $code, 'data' => $data, 'error' => $err];
    }
}
