<?php
declare(strict_types=1);
namespace SysferaWms;

/**
 * Whose data is being handled. Everyone (the administrator too) works only with their own products and documents, like a seller in an ordinary multi-vendor shop.
 * Outside a web session (cron, webhook) nothing is scoped unless a component asks for one owner with Scope::as().
 */
final class Scope
{
    public static ?\Closure $resolver = null;
    private static int|false $override = false;

    /** Owner id the current work is limited to, or null for "everything" (system context). */
    public static function id(): ?int
    {
        if (self::$override !== false) return self::$override ?: null;
        $id = self::$resolver ? (int)(self::$resolver)() : 0;
        return $id > 0 ? $id : null;
    }

    /** Run $fn on behalf of one owner (0 = everybody). */
    public static function as(int $owner, \Closure $fn): mixed
    {
        $prev = self::$override; self::$override = $owner;
        try { return $fn(); } finally { self::$override = $prev; }
    }

    /** May the current owner touch this row (it has an owner_id)? */
    public static function owns(?array $row): bool
    {
        if ($row === null) return false;
        $me = self::id();
        return $me === null || (int)($row['owner_id'] ?? 0) === $me;
    }
}
