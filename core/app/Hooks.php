<?php
declare(strict_types=1);
namespace Sysfera;

/** Events shared by the core and all plugins. do() = notify, filter() = let listeners change a value. */
final class Hooks
{
    private static array $l = [];

    public static function on(string $name, callable $fn, int $priority = 10): void { self::$l[$name][$priority][] = $fn; }

    public static function do(string $name, mixed ...$args): void
    {
        foreach (self::sorted($name) as $fn) {
            try { $fn(...$args); } catch (\Throwable $e) { App::log("hook $name: " . $e->getMessage()); }
        }
    }

    public static function filter(string $name, mixed $value, mixed ...$args): mixed
    {
        foreach (self::sorted($name) as $fn) {
            try { $value = $fn($value, ...$args); } catch (\Throwable $e) { App::log("filter $name: " . $e->getMessage()); }
        }
        return $value;
    }

    private static function sorted(string $name): array
    {
        $b = self::$l[$name] ?? [];
        ksort($b);
        return $b ? array_merge(...array_values($b)) : [];
    }
}
