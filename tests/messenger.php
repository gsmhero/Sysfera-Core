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

/* finding people */
ok(Text::ids('12', 'a.ru') === ['12@a.ru'], 'a bare number is a user of the own site');
ok(Text::ids(' 7@Site.RU ', 'a.ru') === ['7@site.ru', '7@www.site.ru'] && Text::ids('7@www.site.ru', 'a.ru') === ['7@www.site.ru', '7@site.ru'], 'ID: case, spaces, with and without www');
ok(Text::ids('7@https://site.ru/', 'a.ru')[0] === '7@site.ru' && Text::ids('ivan@site.ru', 'a.ru') === [] && Text::ids('Ivan', 'a.ru') === [] && Text::ids('0', 'a.ru') === [], 'what is and is not an ID');
$ro = Text::roster([['id' => '3', 'name' => 'Анна', 'email' => ' Anna@Mail.RU ', 'role' => 'admin']]);
ok($ro === [['id' => 3, 'name' => 'Анна', 'email_hash' => hash('sha256', 'anna@mail.ru'), 'admin' => true]], 'the directory carries a hash, not the address');

eval('namespace Sysfera; class Db {
    public array $users = [];
    public function q(string $sql, array $a = []): void {
        if (str_contains($sql, "(sid, domain, uid, name, email_hash, is_admin, discoverable)")) { $old = $this->users[$a[0]] ?? null; $this->users[$a[0]] = ["sid" => $a[0], "name" => $a[3], "email_hash" => $a[4] ?? $old["email_hash"] ?? null, "discoverable" => $old["discoverable"] ?? 1]; }
        elseif (str_contains($sql, "(sid, domain, uid, name, last_seen)")) $this->users[$a[0]] ??= ["sid" => $a[0], "name" => $a[3], "email_hash" => null, "discoverable" => 1];
    }
    public function rows(string $sql, array $a = []): array {
        $me = array_pop($a);
        if (str_contains($sql, "sid IN")) $f = fn($u) => in_array($u["sid"], $a, true);
        elseif (str_contains($sql, "email_hash = ?")) $f = fn($u) => $u["email_hash"] === $a[0] && $u["discoverable"];
        else $f = fn($u) => $u["discoverable"] && str_starts_with($u["name"], rtrim($a[0], "%"));
        return array_values(array_filter($this->users, fn($u) => $u["sid"] !== $me && $f($u)));
    }
}');
require dirname(__DIR__) . '/core/plugins/messenger/lib/Hub.php';
$db = new Sysfera\Db(); $hub = new SysferaMsg\HubServer($db, sys_get_temp_dir());
$call = fn(string $op, string $uid, array $q = [], array $in = [], string $dom = 'b.ru') => $hub->dispatch($op, new SysferaMsg\Ctx($dom, $uid, $q, $in));
$found = fn(string $q, string $dom = 'a.ru') => array_column($call('search', '1', ['q' => $q], [], $dom)['users'], 'sid');
$people = Text::roster([['id' => 5, 'name' => 'Boris', 'email' => '123@mail.ru', 'role' => 'user'], ['id' => 6, 'name' => 'Vera', 'email' => 'vera@mail.ru', 'role' => 'user']]);
ok($call('roster', '0', [], ['users' => [...$people, ['id' => 'x'], 'junk']])['saved'] === 2, 'a site registers its users; broken rows are skipped');
ok($found('5@b.ru') === ['5@b.ru'] && $found('5@www.b.ru') === ['5@b.ru'] && $found('5', 'b.ru') === ['5@b.ru'], 'found by ID without ever opening Messenger');
ok($found('vera@mail.ru') === ['6@b.ru'] && $found('123@mail.ru') === ['5@b.ru'] && $found('Ver') === ['6@b.ru'], 'found by e-mail (also a numeric one) and by name');
$db->users['6@b.ru']['discoverable'] = 0; $call('roster', '0', [], ['users' => $people]);
ok($found('vera@mail.ru') === [] && $found('Ver') === [] && $found('6@b.ru') === ['6@b.ru'], 'a hidden person stays hidden after the next sync; the exact ID still works');
$bad = 0; foreach ([fn() => $call('roster', '5', [], ['users' => []]), fn() => $call('roster', '0'), fn() => $call('search', '0', ['q' => 'x'])] as $fn) { try { $fn(); } catch (SysferaMsg\HubError) { $bad++; } }
ok($bad === 3, 'only the installation itself sends the list, and it cannot search');

/* a deploy replaces files without "Update": the new migrations still run */
$mig = (string)file_get_contents(dirname(__DIR__) . '/core/plugins/messenger/migrations/004_findable.sql');
ok(str_contains($mig, 'UPDATE {p}msg_users SET discoverable = 1;'), 'old accounts become findable once');

echo $fail ? "\n$fail FAILED\n" : "\nAll good\n";
exit($fail ? 1 : 0);
