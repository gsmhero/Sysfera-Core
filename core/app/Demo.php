<?php
declare(strict_types=1);
namespace Sysfera;

/**
 * Demo mode (for a public showcase install such as core.sysfera.ru).
 * While on: registration is open, new users get every capability, a banner explains the rules,
 * and every N hours all visitor data is wiped. The administrator account and settings stay.
 * Components take part through the hooks "demo.reset" (wipe + reseed their data) and the filter "demo.caps" (remove capabilities visitors must not get).
 */
final class Demo
{
    public const HOURS = [1, 3, 6, 12, 24];

    public static function on(): bool { return App::get()->setting('demo_mode', '0') === '1'; }
    public static function hours(): int { $h = (int)App::get()->setting('demo_hours', '6'); return in_array($h, self::HOURS, true) ? $h : 6; }
    public static function nextReset(): int { return (int)App::get()->setting('demo_last_reset', '0') + self::hours() * 3600; }

    /** Capabilities a demo visitor receives. */
    public static function caps(App $app): array
    {
        return array_values(Hooks::filter('demo.caps', array_keys($app->plugins()->capabilities())));
    }

    /** Lazy scheduler: called on every request, does nothing until the interval has passed. */
    public static function tick(App $app): void
    {
        if (!self::on() || time() < self::nextReset()) return;
        $app->setSetting('demo_last_reset', (string)time());   // claim first, so concurrent requests do not reset twice
        self::reset($app);
    }

    /** Wipe visitor data. Keeps administrators, settings and installed components. */
    public static function reset(App $app): void
    {
        $db = $app->db();
        foreach (['invoice_lines', 'invoices', 'order_lines', 'orders', 'contacts'] as $t) $db->q("DELETE FROM {p}$t");
        $db->q("DELETE FROM {p}users WHERE role <> 'admin'");
        $db->q("DELETE FROM {p}events");
        $app->setSetting('demo_last_reset', (string)time());
        Hooks::do('demo.reset', $db);
        $db->q('INSERT INTO {p}events (plugin, type, data) VALUES (NULL, ?, ?)', ['demo.reset', json_encode(['at' => date('c')])]);
    }
}
