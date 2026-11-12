<?php
declare(strict_types=1);
namespace Sysfera;

/**
 * Outgoing email over SMTP (no external libraries). Settings live in the admin Settings page:
 * smtp_host, smtp_port, smtp_secure (auto|ssl|tls|none), smtp_user, smtp_pass, smtp_from, smtp_from_name, notify_email.
 * send() never throws: it returns null on success or an error text.
 */
final class Mailer
{
    private static function s(string $k, string $d = ''): string { return App::get()->setting($k, $d); }

    public static function configured(): bool { return self::s('smtp_host') !== '' && filter_var(self::s('smtp_from'), FILTER_VALIDATE_EMAIL) !== false; }

    /** Recipients of system notifications: the address from Settings, else every active administrator. */
    public static function adminRecipients(): array
    {
        $one = trim(self::s('notify_email'));
        if ($one !== '') return array_values(array_filter(array_map('trim', preg_split('/[,;\s]+/', $one)), fn($a) => filter_var($a, FILTER_VALIDATE_EMAIL)));
        return array_column(App::get()->db()->rows("SELECT email FROM {p}users WHERE role = 'admin' AND status = 'active'"), 'email');
    }

    /** Tell the administrators something happened. Silent when mail is not configured. */
    public static function notify(string $subject, string $text): ?string
    {
        if (!self::configured()) return 'not_configured';
        $to = self::adminRecipients();
        return $to ? self::send($to, $subject, $text) : 'no_recipient';
    }

    /** @param string|string[] $to */
    public static function send(string|array $to, string $subject, string $text, ?string $html = null): ?string
    {
        if (!self::configured()) return 'not_configured';
        $to = array_values(array_filter((array)$to, fn($a) => filter_var(trim((string)$a), FILTER_VALIDATE_EMAIL)));
        if (!$to) return 'no_recipient';
        $from = self::s('smtp_from'); $fromName = self::s('smtp_from_name', self::s('site_name', 'Sysfera'));
        $host = self::s('smtp_host'); $port = (int)self::s('smtp_port', '587') ?: 587;
        $secure = self::s('smtp_secure', 'auto'); if ($secure === 'auto') $secure = $port === 465 ? 'ssl' : ($port === 25 ? 'none' : 'tls');
        $msg = self::build($from, $fromName, $to, $subject, $text, $html);
        $err = self::deliver($host, $port, $secure, self::s('smtp_user'), self::s('smtp_pass'), $from, $to, $msg, $_SERVER['HTTP_HOST'] ?? 'localhost');
        Hooks::do('mail.sent', $to, $subject, $err);
        return $err;
    }

    /* ---------------- message building (pure, unit-tested) ---------------- */

    /** RFC 2047 encoded-word for non-ASCII header text, split on character boundaries. */
    public static function header(string $s): string
    {
        $s = trim(str_replace(["\r", "\n"], ' ', $s));
        if ($s === '' || preg_match('/^[\x20-\x7e]*$/', $s)) return $s;
        $parts = []; $cur = '';
        foreach (preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $ch) {
            if (strlen($cur) + strlen($ch) > 42) { $parts[] = $cur; $cur = ''; }
            $cur .= $ch;
        }
        if ($cur !== '') $parts[] = $cur;
        return implode("\r\n ", array_map(fn($p) => '=?UTF-8?B?' . base64_encode($p) . '?=', $parts));
    }

    public static function build(string $from, string $fromName, array $to, string $subject, string $text, ?string $html = null, ?string $boundary = null, ?string $date = null, ?string $id = null): string
    {
        $clean = fn(string $a) => str_replace(["\r", "\n", '<', '>', ','], '', trim($a));
        $fromHost = substr(strrchr($from, '@') ?: '@localhost', 1);
        $h = [
            'Date: ' . ($date ?? date('r')),
            'From: ' . ($fromName !== '' ? self::header($fromName) . ' <' . $clean($from) . '>' : $clean($from)),
            'To: ' . implode(', ', array_map($clean, $to)),
            'Subject: ' . self::header($subject),
            'Message-ID: <' . ($id ?? bin2hex(random_bytes(12))) . '@' . $clean($fromHost) . '>',
            'MIME-Version: 1.0',
        ];
        $norm = fn(string $s) => str_replace("\n", "\r\n", str_replace(["\r\n", "\r"], "\n", $s));
        if ($html === null) {
            $h[] = 'Content-Type: text/plain; charset=UTF-8'; $h[] = 'Content-Transfer-Encoding: base64';
            return implode("\r\n", $h) . "\r\n\r\n" . chunk_split(base64_encode($norm($text)), 76, "\r\n");
        }
        $bd = $boundary ?? ('sf' . bin2hex(random_bytes(8)));
        $h[] = 'Content-Type: multipart/alternative; boundary="' . $bd . '"';
        $body = "--$bd\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($norm($text)), 76, "\r\n")
            . "--$bd\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($norm($html)), 76, "\r\n") . "--$bd--\r\n";
        return implode("\r\n", $h) . "\r\n\r\n" . $body;
    }

    /** Dot-stuffing for the SMTP DATA section. */
    public static function dotStuff(string $msg): string { return preg_replace('/^\./m', '..', $msg); }

    /* ---------------- SMTP dialogue ---------------- */

    private static function deliver(string $host, int $port, string $secure, string $user, string $pass, string $from, array $to, string $msg, string $ehlo): ?string
    {
        $ehlo = preg_replace('/[^A-Za-z0-9.\-]/', '', explode(':', $ehlo)[0]) ?: 'localhost';
        $fp = @stream_socket_client(($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port, $en, $es, 12, STREAM_CLIENT_CONNECT);
        if (!$fp) return "connect: $es ($en)";
        stream_set_timeout($fp, 15);
        $read = function () use ($fp): array {
            $code = 0; $text = '';
            while (($l = fgets($fp, 1024)) !== false) { $text .= $l; $code = (int)substr($l, 0, 3); if (strlen($l) < 4 || $l[3] !== '-') break; }
            return [$code, trim($text)];
        };
        $cmd = function (string $c, array $ok) use ($fp, $read): ?string {
            if ($c !== '') fwrite($fp, $c . "\r\n");
            [$code, $text] = $read();
            return in_array($code, $ok, true) ? null : "SMTP $code: " . mb_substr($text, 0, 200);
        };
        try {
            if ($e = $cmd('', [220])) return $e;
            if ($e = $cmd("EHLO $ehlo", [250])) return $e;
            if ($secure === 'tls') {
                if ($e = $cmd('STARTTLS', [220])) return $e;
                if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) return 'TLS handshake failed';
                if ($e = $cmd("EHLO $ehlo", [250])) return $e;
            }
            if ($user !== '') {
                if ($e = $cmd('AUTH LOGIN', [334])) return $e;
                if ($e = $cmd(base64_encode($user), [334])) return $e;
                if ($e = $cmd(base64_encode($pass), [235])) return $e;
            }
            if ($e = $cmd('MAIL FROM:<' . $from . '>', [250])) return $e;
            foreach ($to as $r) if ($e = $cmd('RCPT TO:<' . $r . '>', [250, 251])) return $e;
            if ($e = $cmd('DATA', [354])) return $e;
            fwrite($fp, self::dotStuff($msg) . "\r\n.\r\n");
            [$code, $text] = $read();
            if ($code !== 250) return "SMTP $code: " . mb_substr($text, 0, 200);
            @fwrite($fp, "QUIT\r\n");
            return null;
        } finally { @fclose($fp); }
    }
}
