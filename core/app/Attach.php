<?php
declare(strict_types=1);
namespace Sysfera;

/**
 * What a task can carry besides its text: a report, web links and documents of the Warehouse app.
 * Components keep them as three columns (report TEXT, links TEXT json, wdocs TEXT json) and use this class to read the form and to show them.
 * Documents come from the Warehouse component through two hooks: "attach.docs" (says it is available and where to search) and "attach.docs.info" (labels + links of given ids).
 */
final class Attach
{
    public const MAX_LINKS = 10;
    public const MAX_DOCS = 10;

    /** @return list<array{url:string,title:string}> http(s) links only */
    public static function links(array $urls, array $titles): array
    {
        $out = [];
        foreach ($urls as $i => $u) {
            $u = trim((string)$u);
            if ($u === '') continue;
            if (!preg_match('~^https?://~i', $u) && preg_match('~^[\w.-]+\.[a-z]{2,}(/|$)~i', $u)) $u = 'https://' . $u;   // "example.com/x" -> https://
            $p = filter_var($u, FILTER_VALIDATE_URL) ? parse_url($u) : false;
            if (!$p || empty($p['host']) || !in_array(strtolower((string)($p['scheme'] ?? '')), ['http', 'https'], true) || strlen($u) > 500) continue;
            $out[] = ['url' => $u, 'title' => mb_substr(trim((string)($titles[$i] ?? '')), 0, 100)];
            if (count($out) >= self::MAX_LINKS) break;
        }
        return $out;
    }

    /** @return list<int> */
    public static function docIds(array $ids): array
    {
        $out = [];
        foreach ($ids as $i) { $i = (int)$i; if ($i > 0 && !in_array($i, $out, true)) $out[] = $i; if (count($out) >= self::MAX_DOCS) break; }
        return $out;
    }

    public static function encode(array $a): ?string { return $a ? json_encode(array_values($a), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null; }

    public static function decode(?string $j): array { $a = $j ? json_decode($j, true) : null; return is_array($a) ? array_values($a) : []; }

    /** ['find' => url] when the component behind $kind ("docs" = Warehouse documents, "files" = Teams files) is active for this user, else null */
    public static function avail(string $kind): ?array { $r = Hooks::filter('attach.' . $kind, null); return is_array($r) && !empty($r['find']) ? $r : null; }

    /** @return list<array{id:int,label:string,url:string}> items that still exist and that this user may see */
    public static function items(string $kind, array $ids): array
    {
        if (!$ids) return [];
        $r = Hooks::filter('attach.' . $kind . '.info', [], array_map('intval', $ids));
        return is_array($r) ? array_values($r) : [];
    }

    /** report, links, wdocs, tfiles of a task form, ready to be stored */
    public static function fromPost(): array
    {
        return [self::clean((string)($_POST['report'] ?? '')) ?: null,
            self::encode(self::links((array)($_POST['link_url'] ?? []), (array)($_POST['link_title'] ?? []))),
            self::encode(self::docIds((array)($_POST['wdoc'] ?? []))), self::encode(self::docIds((array)($_POST['tfile'] ?? [])))];
    }

    /** Missions of the library attached to a task (opt-in: only components that keep a column for them call this) @return list<int> */
    public static function missionIds(): array { return self::docIds((array)($_POST['mission'] ?? [])); }

    public static function clean(string $s, int $max = 4000): string { return trim(mb_substr($s, 0, $max)); }
}
