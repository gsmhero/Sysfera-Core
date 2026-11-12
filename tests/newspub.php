<?php
declare(strict_types=1);
/** Tests for the Core News publisher's feed format and form validation (pure PHP). Run: php tests/newspub.php */
if (!function_exists('mb_substr')) { function mb_substr(string $s, int $start, ?int $len = null): string { return $len === null ? substr($s, $start) : substr($s, $start, $len); } }
require dirname(__DIR__) . '/core/plugins/news-publisher/lib/Feed.php';

use SysferaNews\Feed;

$fail = 0;
function ok(bool $c, string $n): void { global $fail; echo ($c ? '  ok   ' : '  FAIL ') . $n . "\n"; if (!$c) $fail++; }

$row = ['slug' => 's1', 'type' => 'warning', 'pinned' => 0, 'post_date' => '2026-12-01', 'expires' => '2026-12-31', 'title_en' => '', 'title_ru' => 'Важно', 'body_en' => '', 'body_ru' => 'Текст', 'url' => 'https://x.ru/a'];
$p = Feed::post($row);
ok($p['id'] === 's1' && $p['type'] === 'warning' && $p['title'] === ['ru' => 'Важно'] && $p['body'] === ['ru' => 'Текст'] && $p['expires'] === '2026-12-31' && $p['url'] === 'https://x.ru/a', 'row becomes a feed post, empty languages are left out');
ok(!isset($p['pinned']), 'pinned only appears when set');
ok(Feed::post(['pinned' => 1, 'title_en' => 'T', 'title_ru' => '', 'slug' => 'a', 'type' => 'news', 'post_date' => '2026-01-01'])['pinned'] === true, 'pinned post');

[$v, $e] = Feed::validate(['type' => 'news', 'title_en' => 'Hello World!', 'post_date' => '2026-12-01', 'published' => '1']);
ok($e === [] && $v['slug'] === '2026-12-01-hello-world' && $v['published'] === 1 && $v['expires'] === null && $v['url'] === null, 'valid minimal post, slug generated');
[, $e] = Feed::validate(['title_en' => '', 'title_ru' => '  ', 'post_date' => '2026-12-01']);
ok($e === ['err_title'], 'a title is required');
[$v, $e] = Feed::validate(['title_en' => 'x', 'post_date' => '2026-02-30', 'expires' => 'soon', 'url' => 'http://insecure', 'slug' => 'bad slug!']);
ok(in_array('err_date', $e, true) && in_array('err_expires', $e, true) && in_array('err_url', $e, true) && in_array('err_slug', $e, true), 'bad date, expiry, url and slug are reported');
[$v] = Feed::validate(['title_en' => 'x', 'type' => 'evil', 'post_date' => '2026-01-01']);
ok($v['type'] === 'news', 'unknown type becomes news');
[$v] = Feed::validate(['title_ru' => 'Привет мир', 'post_date' => '2026-01-01']);
ok(str_starts_with($v['slug'], '2026-01-01-') && strlen($v['slug']) > 11, 'Cyrillic-only title still gets a slug');
ok(Feed::isDate('2026-02-28') && !Feed::isDate('2026-13-01') && !Feed::isDate('26-1-1'), 'date check');

echo $fail ? "\n$fail FAILED\n" : "\nAll good\n";
exit($fail ? 1 : 0);
