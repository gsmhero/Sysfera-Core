<?php
declare(strict_types=1);
/** Task attachments: links are cleaned, document ids are limited, JSON round trip. No database. Run: php tests/attach.php */
if (!function_exists('mb_substr')) { function mb_substr(string $s, int $a = 0, ?int $l = null): string { return $l === null ? substr($s, $a) : substr($s, $a, $l); } }
require dirname(__DIR__) . '/core/app/Hooks.php'; require dirname(__DIR__) . '/core/app/Attach.php';
use Sysfera\Attach;
$fail = 0; function ok(bool $c, string $n): void { global $fail; echo ($c ? '  ok   ' : '  FAIL ') . $n . "\n"; if (!$c) $fail++; }
$l = Attach::links(['https://a.com/x', 'javascript:alert(1)', '', 'example.org/p', 'ftp://h.com/f', 'http://'], ['A', 'B', 'C', 'D', 'E', 'F']);
ok(count($l) === 2 && $l[0]['url'] === 'https://a.com/x' && $l[0]['title'] === 'A', 'only http(s) links survive');
ok($l[1]['url'] === 'https://example.org/p', 'a bare domain gets https://');
ok(count(Attach::links(array_fill(0, 30, 'https://a.com'), [])) === Attach::MAX_LINKS, 'at most 10 links');
ok(Attach::docIds(['3', '3', 'x', '0', 7]) === [3, 7], 'document ids: numbers, unique');
ok(count(Attach::docIds(range(1, 40))) === Attach::MAX_DOCS, 'at most 10 documents');
ok(Attach::encode([]) === null && Attach::decode(null) === [] && Attach::decode('[1,2]') === [1, 2] && Attach::decode('oops') === [], 'encode / decode');
ok(Attach::avail('docs') === null && Attach::items('files', [1]) === [], 'without the components nothing is offered');
Sysfera\Hooks::on('attach.docs', fn($c) => ['find' => '/x']);
ok(Attach::avail('docs') === ['find' => '/x'], 'a component can offer documents');
$_POST = ['report' => '  done  ', 'link_url' => ['https://q.com'], 'wdoc' => ['5'], 'tfile' => ['9', '9']];
ok(Attach::fromPost() === ['done', '[{"url":"https://q.com","title":""}]', '[5]', '[9]'], 'fromPost: report, links, wdocs, tfiles');
echo $fail ? "\n$fail FAILED\n" : "\nAll good\n"; exit($fail ? 1 : 0);
