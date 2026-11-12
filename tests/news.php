<?php
declare(strict_types=1);
/** Tests for Core News post cleanup (pure PHP). Run: php tests/news.php */
if (!function_exists('mb_substr')) { function mb_substr(string $s, int $start, ?int $len = null): string { return $len === null ? substr($s, $start) : substr($s, $start, $len); } }
require dirname(__DIR__) . '/core/app/News.php';

use Sysfera\News;

$fail = 0;
function ok(bool $c, string $n): void { global $fail; echo ($c ? '  ok   ' : '  FAIL ') . $n . "\n"; if (!$c) $fail++; }

$now = strtotime('2026-11-12 12:00:00');
$posts = [
    ['id' => 'a', 'date' => '2026-11-01', 'type' => 'news', 'title' => ['en' => 'Old news', 'ru' => 'Старая новость']],
    ['id' => 'b', 'date' => '2026-11-10', 'type' => 'update', 'title' => ['en' => 'Update']],
    ['id' => 'c', 'date' => '2026-10-01', 'type' => 'warning', 'title' => ['en' => 'Warning', 'ru' => 'Важно'], 'body' => ['ru' => 'Обновите ядро']],
    ['id' => 'd', 'date' => '2026-11-11', 'type' => 'news', 'title' => 'Expired', 'expires' => '2026-11-11'],
    ['id' => 'e', 'date' => '2026-11-11', 'type' => 'news', 'title' => ''],
    'garbage',
    ['id' => 'f', 'date' => '2026-11-12', 'type' => 'hack', 'title' => 'X', 'url' => 'javascript:alert(1)'],
];
$out = News::normalize($posts, 'ru', $now);
$ids = array_column($out, 'id');
ok($ids === ['c', 'f', 'b', 'a'], 'warnings first, then newest; expired, empty and invalid posts dropped');
ok($out[0]['title'] === 'Важно' && $out[0]['body'] === 'Обновите ядро', 'language is picked');
ok(News::normalize($posts, 'ru', $now)[2]['title'] === 'Update', 'falls back to English when a translation is missing');
$f = array_values(array_filter($out, fn($p) => $p['id'] === 'f'))[0];
ok($f['type'] === 'news' && $f['url'] === '', 'unknown type becomes news, non-https links are dropped');
ok(in_array('d', array_column(News::normalize($posts, 'en', strtotime('2026-11-11 20:00:00')), 'id'), true) && !in_array('d', $ids, true), 'a post is shown until the end of its expiry day');
ok(count(News::normalize(array_fill(0, 20, ['title' => 'x', 'date' => '2026-01-01']), 'en', $now)) === 6, 'at most 6 posts');

echo $fail ? "\n$fail FAILED\n" : "\nAll good\n";
exit($fail ? 1 : 0);
