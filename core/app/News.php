<?php
declare(strict_types=1);
namespace Sysfera;

/**
 * "Core News": posts published by the Sysfera team (news, updates, warnings), fetched from <update server>/news.json.
 * One small JSON file, cached for an hour; when the server is unreachable the last copy is shown. Switch off in Settings.
 * Post format: {"id","date":"YYYY-MM-DD","type":"news|update|warning","pinned":bool,"expires":"YYYY-MM-DD","title":{"en","ru"},"body":{"en","ru"},"url":"https://…"}
 */
final class News
{
    private const TTL = 3600, RETRY = 600, MAX = 6;

    public static function enabled(): bool { return App::get()->setting('news_enabled', '1') === '1'; }

    public static function url(): string
    {
        $m = App::get()->updater()->url();
        return preg_replace('~/[^/]*$~', '/news.json', $m) ?: '';
    }

    /** Posts ready to show (current language, newest first, warnings and pinned posts on top). */
    public static function items(string $lang, ?int $now = null): array
    {
        if (!self::enabled()) return [];
        return self::normalize(self::load(), $lang, $now ?? time());
    }

    /** Pure: clean up raw posts. */
    public static function normalize(array $raw, string $lang, int $now): array
    {
        $pick = function ($v) use ($lang): string {
            if (is_string($v)) return trim($v);
            return is_array($v) ? trim((string)($v[$lang] ?? $v['en'] ?? reset($v) ?: '')) : '';
        };
        $out = [];
        foreach ($raw as $p) {
            if (!is_array($p)) continue;
            $title = $pick($p['title'] ?? ''); if ($title === '') continue;
            $exp = isset($p['expires']) ? strtotime((string)$p['expires'] . ' 23:59:59') : false;
            if ($exp !== false && $exp < $now) continue;
            $type = in_array($p['type'] ?? '', ['news', 'update', 'warning'], true) ? $p['type'] : 'news';
            $url = (string)($p['url'] ?? ''); if (!preg_match('~^https://~i', $url)) $url = '';
            $out[] = ['id' => (string)($p['id'] ?? md5($title)), 'date' => (string)($p['date'] ?? ''), 'type' => $type, 'pinned' => !empty($p['pinned']) || $type === 'warning',
                'title' => mb_substr($title, 0, 160), 'body' => mb_substr($pick($p['body'] ?? ''), 0, 1500), 'url' => $url];
        }
        usort($out, fn($a, $b) => [$b['pinned'], $b['date']] <=> [$a['pinned'], $a['date']]);
        return array_slice($out, 0, self::MAX);
    }

    /** Cached fetch. Never throws, never blocks longer than a few seconds. */
    private static function load(): array
    {
        $file = SF_ROOT . '/storage/news.json';
        $age = is_file($file) ? time() - (int)filemtime($file) : PHP_INT_MAX;
        $cached = is_file($file) ? (json_decode((string)@file_get_contents($file), true) ?: []) : [];
        if ($age < self::TTL) return $cached;
        if (is_file($file . '.try') && time() - (int)filemtime($file . '.try') < self::RETRY) return $cached;   // do not hammer a server that is down
        @file_put_contents($file . '.try', (string)time());
        $url = self::url();
        if (!preg_match('~^https://~', $url)) return $cached;
        $ctx = stream_context_create(['http' => ['timeout' => 3, 'follow_location' => 1, 'max_redirects' => 2, 'user_agent' => 'Sysfera/' . SF_VERSION, 'ignore_errors' => true], 'ssl' => ['verify_peer' => true]]);
        $body = @file_get_contents($url, false, $ctx, 0, 200000);
        $data = $body !== false ? json_decode($body, true) : null;
        $ok = is_array($data) && isset($http_response_header[0]) && str_contains($http_response_header[0], ' 200');
        if (!$ok) return $cached;
        @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE));
        return $data;
    }
}
