<?php
declare(strict_types=1);
namespace SysferaHr;

/** Append-only audit log (employee changes, role/permission grants, status history, exports). Every row carries the hash of the previous one: an edited or deleted row breaks the chain. */
final class Audit
{
    public const GENESIS = '0000000000000000000000000000000000000000000000000000000000000000';

    public static function seal(array $e, string $prev): array
    {
        $e['prev_hash'] = $prev;
        $e['hash'] = hash('sha256', $prev . '|' . self::canon($e));
        return $e;
    }
    private static function canon(array $e): string
    {
        $f = [];
        foreach (['ts', 'actor', 'action', 'entity', 'ref', 'data'] as $k) $f[$k] = isset($e[$k]) ? (string)$e[$k] : null;
        return json_encode($f, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    /** @return int|null id (or index) of the first broken row, null when the chain is intact */
    public static function verify(array $rows): ?int
    {
        $prev = self::GENESIS;
        foreach ($rows as $i => $r) {
            if (($r['prev_hash'] ?? '') !== $prev || ($r['hash'] ?? '') !== hash('sha256', $prev . '|' . self::canon($r))) return (int)($r['id'] ?? $i);
            $prev = $r['hash'];
        }
        return null;
    }
}
