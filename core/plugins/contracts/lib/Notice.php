<?php
declare(strict_types=1);
namespace SysferaContracts;

/** The text of a notification (bell and e-mail) in one language: "<who> <phrase>", then the contract, step, details and comment. $L is the plugin's language array. */
final class Notice
{
    public const KINDS = ['offer_new', 'offer_edit', 'offer_withdrawn', 'offer_comment', 'thread_comment', 'offer_accepted', 'offer_rejected', 'offer_lost', 'work_comment', 'todo_comment', 'todo_done', 'todo_undone', 'todo_accepted', 'todo_unaccepted', 'contract_fulfilled', 'paused', 'resumed', 'fired', 'prepaid', 'upload', 'invoice_paid', 'invoice_confirmed', 'review', 'stale'];

    public static function headline(array $L, string $kind, array $d): string
    {
        $what = $L['nt_' . $kind] ?? $kind; $who = trim((string)($d['who'] ?? ''));
        return $who !== '' ? $who . ' ' . $what : ucfirst($what);
    }
    public static function lines(array $L, string $kind, array $d): array
    {
        $o = [self::headline($L, $kind, $d) . '.', '', ($L['nt_l_contract'] ?? 'Contract') . ': ' . ($d['title'] ?? '')];
        if (($d['step'] ?? '') !== '') $o[] = ($L['nt_l_step'] ?? 'Step') . ': ' . $d['step'];
        if (($d['extra'] ?? '') !== '') $o[] = $d['extra'];
        if (($d['comment'] ?? '') !== '') $o[] = ($L['nt_l_comment'] ?? 'Comment') . ': ' . $d['comment'];
        return $o;
    }
    /** Absolute link to a plugin path ("view/3#comments"). */
    public static function link(string $base, string $path): string { return rtrim($base, '/') . ($path === '' ? '' : '/' . ltrim($path, '/')); }
}
