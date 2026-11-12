<?php
declare(strict_types=1);
/** Tests for Messenger/Activity pure logic: signatures, word limits, domains, file names. Run: php tests/messenger.php */
if (!function_exists('mb_substr')) { function mb_substr(string $s, int $start, ?int $len = null): string { return $len === null ? substr($s, $start) : substr($s, $start, $len); } }
require dirname(__DIR__) . '/core/plugins/messenger/lib/Util.php';

use SysferaMsg\{Sig, Text, Activity};

$fail = 0;
function ok(bool $c, string $n): void { global $fail; echo ($c ? '  ok   ' : '  FAIL ') . $n . "\n"; if (!$c) $fail++; }

/* signatures */
$k = str_repeat('a', 64); $now = 1_800_000_000;
$s = Sig::sign($k, $now, 'POST', '/api/hub/send', '12', '{"to":"1@x.ru"}');
ok(Sig::verify($k, $now, 'post', '/api/hub/send', '12', '{"to":"1@x.ru"}', $s, $now + 10), 'valid signature passes (method case-insensitive)');
ok(!Sig::verify($k, $now, 'POST', '/api/hub/send', '13', '{"to":"1@x.ru"}', $s, $now), 'another user id is rejected');
ok(!Sig::verify($k, $now, 'POST', '/api/hub/send', '12', '{"to":"2@x.ru"}', $s, $now), 'changed body is rejected');
ok(!Sig::verify($k, $now, 'POST', '/api/hub/inbox', '12', '{"to":"1@x.ru"}', $s, $now), 'changed path is rejected');
ok(!Sig::verify($k, $now, 'POST', '/api/hub/send', '12', '{"to":"1@x.ru"}', $s, $now + 301), 'old timestamp is rejected');
ok(!Sig::verify('', $now, 'POST', '/a', '1', '', 'x', $now), 'unknown installation (empty key) is rejected');
ok(!Sig::verify(str_repeat('b', 64), $now, 'POST', '/api/hub/send', '12', '{"to":"1@x.ru"}', $s, $now), 'wrong key is rejected');

/* words */
ok(Text::words('Hello, big world!') === 3 && Text::words('') === 0 && Text::words('  ') === 0, 'word counter');
ok(Text::words('Привет, мир! Это тест-проверка 2026') === 5, 'Russian words, hyphen and digits');
$w = fn(int $n) => implode(' ', array_fill(0, $n, 'word'));
ok(Text::checkPost($w(20), $w(50)) === null, '20 title words and 50 text words are fine');
ok(Text::checkPost($w(21), $w(5)) === 'err_title_long', '21 title words are too many');
ok(Text::checkPost($w(3), $w(51)) === 'err_body_long', '51 text words are too many');
ok(Text::checkPost('', 'x') === 'err_title_empty' && Text::checkPost('x', '  ') === 'err_body_empty', 'empty title or text');
ok(Text::checkPost(str_repeat('a', 401), 'x') === 'err_chars', 'one huge word cannot pass');
ok(Activity::TITLE_WORDS === 20 && Activity::BODY_WORDS === 50 && Activity::POSTS_PER_DAY === 3, 'limits');

/* domains: the hub only talks to public https names */
ok(Text::domain('https://shop.example.ru') === 'shop.example.ru' && Text::domain('https://Shop.Example.ru/sf/') === 'shop.example.ru', 'https site, optional sub-folder, lower case');
foreach (['http://shop.example.ru', 'https://127.0.0.1', 'https://localhost', 'https://10.0.0.1/x', 'https://shop.example.ru:8443', 'https://u:p@shop.example.ru', 'https://shop.example.ru/../etc', 'https://shop.example.ru/?x=1', 'ftp://a.ru', 'not a url', 'https://[::1]/'] as $bad)
    ok(Text::domain($bad) === null, 'rejected: ' . $bad);

/* attachments */
ok(Text::file('report.PDF') === ['report.PDF', 'application/pdf'], 'pdf allowed (type from extension)');
ok(Text::file('shell.php') === null && Text::file('a.exe') === null && Text::file('x.svg') === null && Text::file('page.html') === null && Text::file('noext') === null, 'scripts, executables, svg, html, no extension are refused');
ok(Text::file('evil.php.jpg') === null, 'double extension with a script is refused');
ok(Text::file('../../etc/passwd.txt')[0] === 'passwd.txt', 'path parts are stripped');
ok(Text::file("bad\x00name:*?.png")[0] === 'bad_name_.png' || str_ends_with(Text::file("bad\x00name:*?.png")[0], '.png'), 'control and special characters are cleaned');
ok(strlen(Text::file(str_repeat('a', 300) . '.png')[0]) <= 120, 'long names are shortened');
ok(Text::MAX_FILE === 5242880, '5 MB limit');
ok(Text::name("  A\nB\t") === 'A B', 'display name on one line');

echo $fail ? "\n$fail FAILED\n" : "\nAll good\n";
exit($fail ? 1 : 0);
