<?php
declare(strict_types=1);
/**
 * Manage Core News posts (news/news.json).
 *
 *   php tools/news.php add --type warning --title-ru "Обновите ядро" --title-en "Update the core" \
 *        [--body-ru "..."] [--body-en "..."] [--url https://...] [--expires 2026-12-31] [--pinned] [--date 2026-12-01] [--id my-id]
 *   php tools/news.php list
 *   php tools/news.php remove <id>
 *
 * Then commit and push — the build publishes the file as updates/news.json (see docs/NEWS.md).
 * SYSFERA_NEWS_FILE overrides the file path (used by tests).
 */
$file = getenv('SYSFERA_NEWS_FILE') ?: dirname(__DIR__) . '/news/news.json';
$cmd = $argv[1] ?? 'help';
$o = []; $args = [];   // options (--name value, --pinned) and positional arguments after the command
for ($i = 2; $i < count($argv); $i++) {
    if (str_starts_with($argv[$i], '--')) { $k = substr($argv[$i], 2); $o[$k] = ($k === 'pinned') ? true : ($argv[++$i] ?? ''); }
    else $args[] = $argv[$i];
}

function fail(string $m): never { fwrite(STDERR, "Error: $m\n"); exit(1); }
function load(string $file): array
{
    if (!is_file($file)) return [];
    $d = json_decode((string)file_get_contents($file), true);
    if (!is_array($d)) fail("$file is not valid JSON");
    return $d;
}
function save(string $file, array $posts): void
{
    @mkdir(dirname($file), 0755, true);
    $json = json_encode(array_values($posts), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    if (file_put_contents($file . '.tmp', $json) === false || !rename($file . '.tmp', $file)) fail("cannot write $file");
}
function slug(string $s): string { $s = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $s) ?? ''); return trim(substr($s, 0, 40), '-'); }

switch ($cmd) {
    case 'add':
        $type = (string)($o['type'] ?? 'news');
        if (!in_array($type, ['news', 'update', 'warning'], true)) fail('--type must be news, update or warning');
        $title = array_filter(['en' => trim((string)($o['title-en'] ?? '')), 'ru' => trim((string)($o['title-ru'] ?? ''))], fn($v) => $v !== '');
        if (!$title) fail('give a title: --title-en and/or --title-ru');
        $body = array_filter(['en' => trim((string)($o['body-en'] ?? '')), 'ru' => trim((string)($o['body-ru'] ?? ''))], fn($v) => $v !== '');
        $date = (string)($o['date'] ?? date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) fail('--date must look like 2026-12-31');
        $post = ['id' => '', 'date' => $date, 'type' => $type];
        if (isset($o['pinned'])) $post['pinned'] = true;
        if (isset($o['expires'])) { if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$o['expires']) || !strtotime((string)$o['expires'])) fail('--expires must look like 2026-12-31'); $post['expires'] = (string)$o['expires']; }
        $post['title'] = $title;
        if ($body) $post['body'] = $body;
        if (isset($o['url'])) { if (!preg_match('~^https://\S+$~i', (string)$o['url'])) fail('--url must be an https:// link'); $post['url'] = (string)$o['url']; }
        $posts = load($file);
        $post['id'] = (string)($o['id'] ?? ($date . '-' . (slug($title['en'] ?? '') ?: substr(md5(json_encode($title)), 0, 6))));
        if (!preg_match('/^[A-Za-z0-9._-]{1,80}$/', $post['id'])) fail('--id may contain only letters, digits . _ -');
        foreach ($posts as $p) if (($p['id'] ?? '') === $post['id']) fail("a post with id {$post['id']} already exists (use --id or remove it first)");
        array_unshift($posts, $post);
        save($file, $posts);
        echo "Added {$post['id']} ({$type}). Now commit and push news/news.json.\n";
        break;

    case 'list':
        foreach (load($file) as $p) {
            $t = $p['title']['en'] ?? ($p['title']['ru'] ?? (is_string($p['title'] ?? null) ? $p['title'] : '?'));
            printf("%-34s %-8s %s%s  %s\n", $p['id'] ?? '?', $p['type'] ?? 'news', $p['date'] ?? '', isset($p['expires']) ? ' → ' . $p['expires'] : '', $t);
        }
        break;

    case 'remove':
        $id = $args[0] ?? fail('usage: php tools/news.php remove <id>');
        $posts = load($file); $keep = array_values(array_filter($posts, fn($p) => ($p['id'] ?? '') !== $id));
        if (count($keep) === count($posts)) fail("no post with id $id");
        save($file, $keep);
        echo "Removed $id. Now commit and push news/news.json.\n";
        break;

    default:
        echo "Usage:\n  php tools/news.php add --type news|update|warning --title-en \"…\" --title-ru \"…\" [--body-en \"…\"] [--body-ru \"…\"] [--url https://…] [--expires YYYY-MM-DD] [--pinned] [--date YYYY-MM-DD] [--id my-id]\n  php tools/news.php list\n  php tools/news.php remove <id>\n";
}
