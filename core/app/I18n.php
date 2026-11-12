<?php
declare(strict_types=1);
namespace Sysfera;

/**
 * Translations. A language is just a file lang/<code>.php returning an array; the key '_name' is its display name.
 * To add a language: copy lang/en.php to lang/xx.php and translate it — it appears in the language lists automatically.
 * Plugins ship plugins/<slug>/lang/<code>.php; their keys are used as "slug.key".
 */
final class I18n
{
    private static string $lang = 'en';
    private static array $dirs = [];          // prefix => directory ('' = core)
    private static array $cache = [];         // "lang|prefix" => array

    public static function setLang(string $code): void { self::$lang = preg_match('/^[a-z]{2,3}(-[A-Za-z]{2})?$/', $code) ? $code : 'en'; }
    public static function lang(): string { return self::$lang; }
    public static function addDir(string $prefix, string $dir): void { self::$dirs[$prefix] = $dir; }

    /** @return array<string,string> code => name */
    public static function languages(): array
    {
        $out = [];
        foreach (glob(SF_ROOT . '/lang/*.php') ?: [] as $f) {
            $d = require $f;
            $out[basename($f, '.php')] = (string)($d['_name'] ?? basename($f, '.php'));
        }
        ksort($out);
        return $out;
    }

    public static function t(string $key, array $vars = []): string
    {
        $prefix = ''; $k = $key;
        $dot = strpos($key, '.');
        if ($dot !== false && isset(self::$dirs[substr($key, 0, $dot)])) { $prefix = substr($key, 0, $dot); $k = substr($key, $dot + 1); }
        $s = self::load(self::$lang, $prefix)[$k] ?? (self::$lang !== 'en' ? (self::load('en', $prefix)[$k] ?? null) : null) ?? $key;
        foreach ($vars as $n => $v) $s = str_replace('{' . $n . '}', (string)$v, $s);
        return $s;
    }

    private static function load(string $lang, string $prefix): array
    {
        $ck = "$lang|$prefix";
        if (isset(self::$cache[$ck])) return self::$cache[$ck];
        $dir = $prefix === '' ? SF_ROOT . '/lang' : (self::$dirs[$prefix] ?? '');
        $f = "$dir/$lang.php";
        return self::$cache[$ck] = ($dir !== '' && is_file($f)) ? (array)require $f : [];
    }
}
