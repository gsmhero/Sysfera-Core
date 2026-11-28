<?php
declare(strict_types=1);
namespace SysferaBlog;

/** Pictures and videos of a post: what is stored, and how a video link is shown. */
final class Media
{
    public const MAX_IMAGES = 12, MAX_VIDEOS = 8, MAX_URL = 500;

    /** Stored gallery: a JSON list of cover-like file names. @return list<string> */
    public static function images(mixed $v): array
    {
        $v = is_string($v) ? json_decode($v, true) : $v;
        return array_values(array_filter(is_array($v) ? $v : [], fn($n) => is_string($n) && Text::coverName($n)));
    }

    /** Stored video links. @return list<string> */
    public static function videos(mixed $v): array
    {
        $v = is_string($v) ? json_decode($v, true) : $v;
        return array_values(array_filter(is_array($v) ? $v : [], fn($u) => is_string($u) && Text::httpUrl($u)));
    }

    /**
     * What the author typed, one link or pasted embed code per line, as clean links.
     * @return list<string>|null null when a line holds no usable link
     */
    public static function parseLines(string $text): ?array
    {
        $out = [];
        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') continue;
            if (stripos($line, '<iframe') !== false) {   // only the address of a pasted embed code is kept, never its HTML
                if (!preg_match('~\ssrc\s*=\s*["\']([^"\']+)["\']~i', $line, $m)) return null;
                $line = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
            if (str_starts_with($line, '//')) $line = 'https:' . $line;
            elseif (!preg_match('~^[a-z][a-z0-9+.-]*:~i', $line) && preg_match('~^[a-z0-9.-]+\.[a-z]{2,}/~i', $line)) $line = 'https://' . $line;
            if (strlen($line) > self::MAX_URL || !Text::httpUrl($line)) return null;
            $out[$line] = $line;
        }
        return array_values($out);
    }

    /**
     * How a link is shown: 'frame' (the service's own player), 'file' (a video file) or 'link' (anything else).
     * A player address is always built here from the numbers in the link.
     * @return array{type:string,src:string,service:string}
     */
    public static function video(string $url): array
    {
        $p = parse_url($url) ?: [];
        $host = (string)preg_replace('~^(www|m)\.~', '', strtolower((string)($p['host'] ?? '')));
        $path = (string)($p['path'] ?? '');
        parse_str((string)($p['query'] ?? ''), $q);
        $s = fn(string $k): string => is_string($q[$k] ?? null) ? $q[$k] : '';
        $frame = fn(string $src, string $service): array => ['type' => 'frame', 'src' => $src, 'service' => $service];

        if (in_array($host, ['youtube.com', 'youtube-nocookie.com', 'music.youtube.com', 'youtu.be'], true)) {
            $id = $host === 'youtu.be' ? trim($path, '/') : (preg_match('~^/(?:shorts|embed|live|v)/([^/]+)~', $path, $m) ? $m[1] : $s('v'));
            if (preg_match('~^[A-Za-z0-9_-]{11}$~D', $id)) {
                $t = $s('t') !== '' ? $s('t') : $s('start'); $sec = 0;
                if (preg_match('~^(?:(\d{1,2})h)?(?:(\d{1,3})m)?(?:(\d{1,6})s?)?$~D', $t, $m)) $sec = (int)($m[1] ?? 0) * 3600 + (int)($m[2] ?? 0) * 60 + (int)($m[3] ?? 0);
                return $frame('https://www.youtube-nocookie.com/embed/' . $id . ($sec ? '?start=' . $sec : ''), 'YouTube');
            }
        } elseif (in_array($host, ['vk.com', 'vk.ru', 'vkvideo.ru'], true)) {
            $oid = $id = '';
            if ($path === '/video_ext.php') { $oid = $s('oid'); $id = $s('id'); }
            elseif (preg_match('~(?:video|clip)(-?\d+)_(\d+)~', rawurldecode($path . '?' . ($p['query'] ?? '')), $m)) [, $oid, $id] = $m;
            if (preg_match('~^-?\d{1,20}$~D', $oid) && preg_match('~^\d{1,20}$~D', $id)) {
                $hash = preg_match('~^[a-f0-9]{8,40}$~D', $s('hash')) ? '&hash=' . $s('hash') : '';
                return $frame("https://vk.com/video_ext.php?oid=$oid&id=$id$hash&hd=2", 'VK Video');
            }
        } elseif ($host === 'rutube.ru') {
            if (preg_match('~^/(?:video/private|video|shorts|play/embed)/([a-f0-9]{32}|\d{1,12})/?$~D', $path, $m)) {
                $key = preg_match('~^[A-Za-z0-9_-]{1,64}$~D', $s('p')) ? '?p=' . $s('p') : '';
                return $frame('https://rutube.ru/play/embed/' . $m[1] . $key, 'Rutube');
            }
        } elseif ($host === 'vimeo.com' || $host === 'player.vimeo.com') {
            if (preg_match('~^/(?:video/)?(\d{1,12})(?:/([a-f0-9]{6,20}))?/?$~D', $path, $m)) {
                $h = $m[2] ?? (preg_match('~^[a-f0-9]{6,20}$~D', $s('h')) ? $s('h') : '');
                return $frame('https://player.vimeo.com/video/' . $m[1] . ($h !== '' ? '?h=' . $h : ''), 'Vimeo');
            }
        } elseif ($host === 'dailymotion.com' || $host === 'dai.ly') {
            if (preg_match($host === 'dai.ly' ? '~^/([A-Za-z0-9]{4,20})/?$~D' : '~^/(?:embed/)?video/([A-Za-z0-9]{4,20})~', $path, $m)) return $frame('https://www.dailymotion.com/embed/video/' . $m[1], 'Dailymotion');
        } elseif ($host === 'ok.ru') {
            if (preg_match('~^/(?:video|videoembed|live)/(\d{1,20})/?$~D', $path, $m)) return $frame('https://ok.ru/videoembed/' . $m[1], 'OK');
        }
        if (($p['scheme'] ?? '') === 'https' && preg_match('~\.(mp4|webm|ogv)$~i', $path)) return ['type' => 'file', 'src' => $url, 'service' => $host];
        return ['type' => 'link', 'src' => $url, 'service' => $host];
    }
}
