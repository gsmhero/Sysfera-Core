<?php
declare(strict_types=1);
namespace Sysfera;

/** The public page guests see at "/": what Sysfera is and which components it has. Texts live in the language files (land.c.<slug>, land.c.<slug>_d). */
final class Landing
{
    /** slug => [icon, state]. state: 'ready' (can be installed now) or 'soon' (announced). Order = order on the page. */
    public const COMPONENTS = [
        'wms'       => ['box',   'ready'],
        'basebase'  => ['link',  'ready'],
        'messenger' => ['chat',  'ready'],
        'activity'  => ['bell',  'ready'],
        'news'      => ['news',  'ready'],
        'accounting'=> ['calc',  'soon'],
        'crm'       => ['users', 'ready'],
        'teams'     => ['users', 'ready'],
        'vacancy'   => ['user',  'soon'],
    ];

    /** Landing is shown to guests unless the administrator switched it off (then guests go straight to the sign-in form). */
    public static function enabled(?string $setting): bool
    {
        return $setting !== '0';
    }

    /** @return list<array{slug:string,icon:string,state:string}> */
    public static function components(): array
    {
        $out = [];
        foreach (self::COMPONENTS as $slug => [$icon, $state]) $out[] = ['slug' => $slug, 'icon' => $icon, 'state' => $state];
        return $out;
    }

    /** Curated cards that stand for one or more plugins: those plugins get no card of their own, and a "soon" card turns "ready" once one of them exists. */
    public const COVERS = [
        'wms' => ['wms'], 'basebase' => ['wms-basebase'], 'messenger' => ['messenger'], 'activity' => ['activity'], 'news' => ['news', 'news-publisher'],
        'accounting' => ['accounting'], 'crm' => ['crm'], 'teams' => ['teams'], 'vacancy' => ['vacancy'],
    ];

    /**
     * What the start page shows: the curated cards, then one card for every other component that is installed here or offered by the
     * update server (the manifest the site already holds; guests never trigger a download). So a component added by an update appears
     * on the page by itself. Plugins named by COVERS are not repeated.
     * @param array<string,array> $plugins slug => manifest of the enabled plugins (name/description as string or {"en","ru"})
     * @param array<string,array> $remote  slug => update-server entry (name/description)
     * @return list<array{slug:string,icon:string,state:string,title?:string,desc?:string}>
     */
    public static function all(array $plugins = [], array $remote = [], string $lang = 'en'): array
    {
        $have = array_keys($plugins + $remote); $covered = []; $out = [];
        foreach (self::components() as $c) {
            $own = self::COVERS[$c['slug']] ?? [$c['slug']];
            if ($c['state'] === 'soon' && array_intersect($own, $have)) $c['state'] = 'ready';
            $out[] = $c; foreach ($own as $s) $covered[$s] = 1;
        }
        $text = function ($v) use ($lang): string { return trim(is_array($v) ? (string)($v[$lang] ?? $v['en'] ?? (reset($v) ?: '')) : (string)$v); };
        foreach ($plugins + $remote as $slug => $m) {
            if (isset($covered[$slug]) || !is_array($m)) continue;
            $title = $text($m['name'] ?? ''); if ($title === '') $title = (string)$slug;
            $d = $text($m['description'] ?? ''); $d = preg_split('/(?<=[.!?])\s/u', $d)[0] ?? $d;
            if (preg_match('/^.{171,}/us', $d) && preg_match('/^.{0,167}/us', $d, $cut)) $d = rtrim($cut[0]) . '…';
            $out[] = ['slug' => (string)$slug, 'icon' => 'box', 'state' => 'ready', 'title' => $title, 'desc' => $d];
        }
        return $out;
    }
}
