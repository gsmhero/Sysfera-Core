<?php
declare(strict_types=1);
namespace SysferaNews;

/** Pure helpers: turn table rows into the public feed format and validate the edit form. */
final class Feed
{
    /** Row of news_posts → post in news.json format (see docs/NEWS.md). */
    public static function post(array $r): array
    {
        $title = array_filter(['en' => trim((string)$r['title_en']), 'ru' => trim((string)$r['title_ru'])], fn($v) => $v !== '');
        $body = array_filter(['en' => trim((string)($r['body_en'] ?? '')), 'ru' => trim((string)($r['body_ru'] ?? ''))], fn($v) => $v !== '');
        $p = ['id' => (string)$r['slug'], 'date' => (string)$r['post_date'], 'type' => (string)$r['type']];
        if (!empty($r['pinned'])) $p['pinned'] = true;
        if (!empty($r['expires'])) $p['expires'] = (string)$r['expires'];
        $p['title'] = $title;
        if ($body) $p['body'] = $body;
        if (!empty($r['url'])) $p['url'] = (string)$r['url'];
        return $p;
    }

    /** @return array{0:array,1:string[]} cleaned values and error keys */
    public static function validate(array $in): array
    {
        $e = [];
        $type = in_array($in['type'] ?? '', ['news', 'update', 'warning'], true) ? $in['type'] : 'news';
        $te = trim((string)($in['title_en'] ?? '')); $tr = trim((string)($in['title_ru'] ?? ''));
        if ($te === '' && $tr === '') $e[] = 'err_title';
        $date = (string)($in['post_date'] ?? ''); if (!self::isDate($date)) { $e[] = 'err_date'; $date = date('Y-m-d'); }
        $exp = trim((string)($in['expires'] ?? '')); if ($exp !== '' && !self::isDate($exp)) { $e[] = 'err_expires'; $exp = ''; }
        $url = trim((string)($in['url'] ?? '')); if ($url !== '' && !preg_match('~^https://\S+$~i', $url)) { $e[] = 'err_url'; $url = ''; }
        $slug = trim((string)($in['slug'] ?? ''));
        if ($slug === '') $slug = self::slug($date, $te !== '' ? $te : $tr);
        elseif (!preg_match('/^[A-Za-z0-9._-]{1,80}$/', $slug)) { $e[] = 'err_slug'; }
        return [[
            'type' => $type, 'pinned' => !empty($in['pinned']) ? 1 : 0, 'published' => !empty($in['published']) ? 1 : 0, 'post_date' => $date, 'expires' => $exp !== '' ? $exp : null,
            'title_en' => mb_substr($te, 0, 160), 'title_ru' => mb_substr($tr, 0, 160), 'body_en' => mb_substr(trim((string)($in['body_en'] ?? '')), 0, 1500), 'body_ru' => mb_substr(trim((string)($in['body_ru'] ?? '')), 0, 1500),
            'url' => $url !== '' ? $url : null, 'slug' => $slug,
        ], $e];
    }

    /**
     * Pure: the "Update" post written automatically after a core update. $notes = ['en'=>…,'ru'=>…] release notes (may be empty).
     * The slug is fixed per version, so it can never be created twice.
     */
    public static function updatePost(string $version, array $notes, string $date): array
    {
        $en = trim((string)($notes['en'] ?? '')); $ru = trim((string)($notes['ru'] ?? ''));
        return [
            'slug' => 'core-' . $version, 'type' => 'update', 'pinned' => 0, 'published' => 1, 'post_date' => $date, 'expires' => null,
            'title_en' => 'Sysfera Core ' . $version . ' released', 'title_ru' => 'Вышла Sysfera Core ' . $version,
            'body_en' => mb_substr($en !== '' ? $en : 'Core was updated to version ' . $version . '. Open Updates to install it.', 0, 1500),
            'body_ru' => mb_substr($ru !== '' ? $ru : 'Вышла новая версия ядра ' . $version . '. Установите её на странице «Обновления».', 0, 1500),
            'url' => null,
        ];
    }

    public static function isDate(string $s): bool { return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) && checkdate((int)substr($s, 5, 2), (int)substr($s, 8, 2), (int)substr($s, 0, 4)); }

    public static function slug(string $date, string $title): string
    {
        $t = strtolower(trim((string)preg_replace('/[^A-Za-z0-9]+/', '-', $title), '-'));
        return $date . '-' . (substr($t, 0, 40) ?: substr(md5($title), 0, 6));
    }
}
