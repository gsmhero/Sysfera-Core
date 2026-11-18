<?php
declare(strict_types=1);
namespace SysferaContactlog;

/** Builds the e-mail report of one card (subject, HTML and plain text). Pure; every string comes from the viewer's language array. */
final class Report
{
    public static function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
    public static function when(int $ts, string $tz): string
    {
        try { $z = new \DateTimeZone($tz); } catch (\Throwable) { $z = new \DateTimeZone('UTC'); }
        return (new \DateTimeImmutable('@' . $ts))->setTimezone($z)->format('Y-m-d H:i');
    }
    public static function fullName(array $c): string { return trim((string)preg_replace('/\s+/', ' ', $c['name'] . ' ' . $c['second_name'] . ' ' . $c['surname'])); }

    /** @return array{subject:string,html:string,text:string} */
    public static function build(array $c, array $L, int $now, string $tz = 'UTC'): array
    {
        $dash = fn(string $s): string => $s === '' ? '—' : $s;
        $logs = array_map(fn($l) => '[' . self::when((int)$l['at'], $tz) . '] ' . ($L['log_' . $l['action']] ?? $l['action']), $c['logs']);
        $rows = [[$L['rep_mobile'], $dash($c['mobile'])], [$L['rep_messenger'], $dash($c['messenger'])], [$L['rep_email'], $dash($c['email'])], [$L['rep_status'], $L['st_' . $c['status']] ?? $c['status']],
            [$L['rep_theme'], $dash($c['theme'])], [$L['rep_notes'], $dash($c['notes'])], [$L['rep_next'], $dash($c['next_call'])], [$L['rep_created'], self::when($c['created_at'], $tz)]];
        $name = self::fullName($c);
        $html = '<html><body style="font-family:Arial,sans-serif;color:#1e293b;"><div style="max-width:600px;margin:0 auto;padding:24px;"><h2 style="color:#3b82f6;margin-bottom:16px;">' . self::h($L['rep_title']) . '</h2>'
            . '<div style="background:#f8fafc;padding:20px;border-radius:16px;margin-bottom:20px;border:1px solid #e2e8f0;"><p style="margin:8px 0;"><strong style="font-size:1.2rem;">' . self::h($name) . '</strong></p>';
        foreach ($rows as [$k, $v]) $html .= '<p style="margin:6px 0;white-space:pre-line;"><strong>' . self::h($k) . ':</strong> ' . self::h($v) . '</p>';
        $html .= '</div><h3 style="color:#334155;margin-bottom:12px;">' . self::h($L['rep_log']) . '</h3><div style="background:#f1f5f9;padding:16px;border-radius:12px;font-family:monospace;font-size:0.85rem;line-height:1.6;">'
            . ($logs ? implode('', array_map(fn($l) => '<div>' . self::h($l) . '</div>', $logs)) : '<i style="color:#94a3b8;">' . self::h($L['no_logs']) . '</i>')
            . '</div><hr style="border:none;border-top:1px solid #e2e8f0;margin:24px 0;"><p style="font-size:0.75rem;color:#94a3b8;text-align:center;">' . self::h($L['rep_footer']) . ' · ' . self::h(self::when($now, $tz)) . '</p></div></body></html>';
        $text = $L['rep_title'] . "\n\n" . $name . "\n" . implode("\n", array_map(fn($r) => $r[0] . ': ' . $r[1], $rows)) . "\n\n" . $L['rep_log'] . ":\n" . ($logs ? implode("\n", $logs) : $L['no_logs']);
        return ['subject' => str_replace(["\r", "\n"], ' ', $L['rep_title'] . ': ' . $name), 'html' => $html, 'text' => $text];
    }
}
