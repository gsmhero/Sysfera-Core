<?php
declare(strict_types=1);
namespace SysferaStoragerent;

/**
 * "Contact with Lessor": delivers a message to the author of a listing through Messenger (the client is lent by that component, see the hook messenger.client).
 * The lessor's Sysfera ID is <his user id>@<this domain>. Kept apart from the route so that it can be tested with a fake client.
 * The client needs: ready(): bool, domain(): string, call(method, op, query, body, raw, timeout, asUid): array{ok, data, error}.
 */
final class Contact
{
    public const MAX = 1500;

    /** Sends $text (and the link of the listing) to the lessor and returns his Sysfera ID, so that the caller can open the chat. Throws Err. */
    public static function send(?object $client, array $sender, int $lessorUid, string $lessorName, string $text, string $listingUrl): string
    {
        $text = trim($text); $len = function_exists('mb_strlen') ? mb_strlen($text) : (int)preg_match_all('/./su', $text);
        if ($len < 2) throw new Err('message_empty');
        if ($len > self::MAX) throw new Err('message_long');
        if ($client === null || !$client->ready()) throw new Err('messenger_off');
        // the sender is known to the hub (the same call the Messenger page makes before it sends)
        $client->call('POST', 'me', [], ['name' => (string)($sender['name'] ?? ''), 'email' => (string)($sender['email'] ?? ''), 'admin' => ($sender['role'] ?? '') === 'admin']);
        $sid = (string)$lessorUid . '@' . $client->domain(); $body = ['to' => $sid, 'text' => $text . "\n\n" . $listingUrl, 'file' => ''];
        $r = $client->call('POST', 'send', [], $body);
        if (!$r['ok'] && ($r['error'] ?? '') === 'no_user') {
            // the lessor never opened Messenger, so the hub does not know him: introduce him by name only (never listed in the directory), then try once more
            $client->call('POST', 'me', [], ['name' => $lessorName !== '' ? $lessorName : 'User ' . $lessorUid, 'email' => '', 'admin' => false], null, 8, (string)$lessorUid);
            $r = $client->call('POST', 'send', [], $body);
        }
        if (!$r['ok']) throw new Err(match ($r['error'] ?? '') { 'no_user' => 'messenger_no_user', 'blocked' => 'messenger_blocked', 'rate' => 'messenger_rate', default => 'messenger_failed' });
        return $sid;
    }
}
