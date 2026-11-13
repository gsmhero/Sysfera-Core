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
}
