<?php
declare(strict_types=1);
namespace SysferaGi;

/** The status of an order. created -> transferred -> received -> ready_for_issue -> issued | return_requested -> returned | blocked */
final class Machine
{
    public const STATUSES = ['created', 'transferred', 'received', 'ready_for_issue', 'issued', 'return_requested', 'returned', 'blocked'];

    /** from => [to => who may do it]  (system = the exchange layer itself, office = head office, operator = remote warehouse app) */
    private const T = [
        'created'          => ['transferred' => ['system']],
        'transferred'      => ['received' => ['operator'], 'blocked' => ['operator']],
        'received'         => ['ready_for_issue' => ['operator', 'system'], 'blocked' => ['operator']],
        'ready_for_issue'  => ['issued' => ['operator'], 'blocked' => ['office', 'system'], 'return_requested' => ['system']],   // system: unclaimed too long -> refund
        'blocked'          => ['ready_for_issue' => ['office'], 'return_requested' => ['office']],
        'issued'           => ['return_requested' => ['operator', 'office']],
        'return_requested' => ['returned' => ['operator'], 'issued' => ['office'], 'ready_for_issue' => ['office']],   // office: the return was rejected, the order goes back
        'returned'         => [],
    ];

    public static function can(string $from, string $to, string $actor): bool { return in_array($actor, self::T[$from][$to] ?? [], true); }

    public static function go(string $from, string $to, string $actor): string
    {
        if (!isset(self::T[$from][$to])) throw new Err('bad_transition', "$from -> $to is not a legal step", 409);
        if (!self::can($from, $to, $actor)) throw new Err('forbidden_transition', "$actor may not do $from -> $to", 403);
        return $to;
    }

    public static function next(string $from): array { return array_keys(self::T[$from] ?? []); }
    /** goods that are physically in the pickup warehouse */
    public static function inWarehouse(string $s): bool { return in_array($s, ['received', 'ready_for_issue', 'blocked', 'returned'], true); }
}
