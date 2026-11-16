<?php
declare(strict_types=1);
namespace SysferaGi;

/** Append-only audit log. Every row carries the hash of the previous one, so an edited or deleted row breaks the chain and verify() finds it. */
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
        foreach (['ts', 'client_ts', 'actor', 'action', 'entity', 'entity_uuid', 'device_id', 'code_hash', 'data'] as $k) $f[$k] = isset($e[$k]) ? (string)$e[$k] : null;
        return json_encode($f, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    /** @return int|null id (or index) of the first broken row, null when the chain is intact */
    public static function verify(array $rows): ?int { return self::verifyFrom($rows, self::GENESIS)[0]; }

    /** the same for a slice of the log: @return array{0: ?int, 1: string} first broken id and the hash to continue with */
    public static function verifyFrom(array $rows, string $prev): array
    {
        foreach ($rows as $i => $r) {
            if (($r['prev_hash'] ?? '') !== $prev || ($r['hash'] ?? '') !== hash('sha256', $prev . '|' . self::canon($r))) return [(int)($r['id'] ?? $i), $prev];
            $prev = $r['hash'];
        }
        return [null, $prev];
    }
}
