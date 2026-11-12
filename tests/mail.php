<?php
declare(strict_types=1);
/** Tests for the message builder of the core mailer (pure PHP, no network). Run: php tests/mail.php */
namespace {
    if (!function_exists('mb_substr')) { function mb_substr(string $s, int $start, ?int $len = null): string { return $len === null ? substr($s, $start) : substr($s, $start, $len); } }
    require dirname(__DIR__) . '/core/app/Mailer.php';

    use Sysfera\Mailer;

    $fail = 0;
    function ok(bool $c, string $n): void { global $fail; echo ($c ? '  ok   ' : '  FAIL ') . $n . "\n"; if (!$c) $fail++; }
    function decodeWords(string $h): string { preg_match_all('/=\?UTF-8\?B\?([^?]+)\?=/', $h, $m); return implode('', array_map('base64_decode', $m[1])); }

    ok(Mailer::header('Hello') === 'Hello', 'ASCII header stays as is');
    $long = 'Новый заказ Basebase № 1055: Кружка керамическая 350 мл, 2 шт., доставка CDEK в Москву';
    $enc = Mailer::header($long);
    ok(decodeWords($enc) === $long, 'non-ASCII subject round-trips through encoded words');
    ok(max(array_map('strlen', explode("\r\n ", $enc))) <= 75, 'every encoded word fits the 75 character limit');
    ok(!preg_match('/\n(?! )|\r(?!\n)/', $enc), 'folded lines start with a space');
    ok(!str_contains(Mailer::header("Hi\r\nBcc: x@y.z"), "\n"), 'newlines cannot inject headers');

    $m = Mailer::build('noreply@sysfera.ru', 'Sysfera', ['a@b.ru', "evil@x.ru\r\nBcc: z@z.ru"], 'Тема', "Строка 1\nСтрока 2", null, null, 'Mon, 01 Jan 2026 10:00:00 +0000', 'abc');
    [$head, $body] = explode("\r\n\r\n", $m, 2);
    ok(str_contains($head, "To: a@b.ru, evil@x.ruBcc: z@z.ru") && !str_contains($head, "\nBcc:"), 'recipients are sanitised');
    ok(str_contains($head, 'Message-ID: <abc@sysfera.ru>') && str_contains($head, 'MIME-Version: 1.0') && str_contains($head, 'From: Sysfera <noreply@sysfera.ru>'), 'standard headers');
    ok(base64_decode($body) === "Строка 1\r\nСтрока 2", 'plain body is base64 UTF-8 with CRLF line breaks');
    ok(str_contains($head, 'Content-Type: text/plain; charset=UTF-8'), 'plain content type');

    $h = Mailer::build('n@s.ru', '', ['a@b.ru'], 'S', 'text', '<b>html</b>', 'BND');
    ok(str_contains($h, 'multipart/alternative; boundary="BND"') && substr_count($h, '--BND') === 3 && str_contains($h, '--BND--'), 'multipart message with text and html parts');
    ok(str_contains($h, 'From: n@s.ru'), 'sender without a name');

    ok(Mailer::dotStuff("a\r\n.b\r\n..c") === "a\r\n..b\r\n...c", 'dot stuffing');

    echo $fail ? "\n$fail FAILED\n" : "\nAll good\n";
    exit($fail ? 1 : 0);
}
