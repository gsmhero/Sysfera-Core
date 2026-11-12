<?php
declare(strict_types=1);

use SysferaNews\Feed;

/** Core News publisher: admin panel for the posts shown in every installation's dashboard, plus the public feed GET /api/news. */
return function (\Sysfera\Plugin $sf): void {
    require_once __DIR__ . '/lib/Feed.php';
    $db = $sf->db();
    $uid = fn() => $sf->auth()->id() ?: null;
    $go = fn(string $p = '') => redirect('/p/news-publisher/' . ltrim($p, '/'));
    $live = fn(): array => $db->rows('SELECT * FROM {p}news_posts WHERE published = 1 AND (expires IS NULL OR expires >= CURDATE()) ORDER BY pinned DESC, post_date DESC, id DESC LIMIT 200');

    /* After every core update on this site: write the "Update" post by itself (once per version, from the release notes in updates/manifest.json). */
    try {
        $app = \Sysfera\App::get();
        if ($app->setting('news_pub_version', '') !== SF_VERSION) {
            $mf = json_decode((string)@file_get_contents(dirname(SF_ROOT) . '/updates/manifest.json'), true);
            $notes = is_array($mf) && ($mf['core']['version'] ?? '') === SF_VERSION && is_array($mf['core']['notes'] ?? null) ? $mf['core']['notes'] : [];
            $v = Feed::updatePost(SF_VERSION, $notes, date('Y-m-d'));
            $cols = array_keys($v);
            $db->q('INSERT IGNORE INTO {p}news_posts (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')', array_values($v));
            $app->setSetting('news_pub_version', SF_VERSION);
        }
    } catch (\Throwable $e) { /* table not migrated yet: try again on the next request */ }

    $sf->menu('menu', '/', ['cap' => 'news.admin', 'order' => 15, 'icon' => 'refresh']);

    /* ---------- admin pages ---------- */
    $sf->route('GET', '/', function () use ($sf, $db, $live) {
        $d = fn(string $k) => Feed::isDate((string)($_GET[$k] ?? '')) ? (string)$_GET[$k] : '';
        $from = $d('from'); $to = $d('to');
        $per = in_array((int)($_GET['per'] ?? 5), [5, 20, 50], true) ? (int)($_GET['per'] ?? 5) : 5;
        $where = ' WHERE 1=1' . ($from !== '' ? ' AND post_date >= ?' : '') . ($to !== '' ? ' AND post_date <= ?' : '');
        $args = array_values(array_filter([$from, $to], fn($x) => $x !== ''));
        $total = (int)$db->val('SELECT COUNT(*) FROM {p}news_posts' . $where, $args);
        $pages = max(1, (int)ceil($total / $per)); $page = min(max(1, (int)($_GET['page'] ?? 1)), $pages);
        $rows = $db->rows('SELECT * FROM {p}news_posts' . $where . ' ORDER BY post_date DESC, id DESC LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per), $args);
        $sf->render('index', ['rows' => $rows, 'total' => $total, 'pages' => $pages, 'page' => $page, 'per' => $per, 'from' => $from, 'to' => $to,'liveCount' => count($live()), 'feed' => 'https://' . ($_SERVER['HTTP_HOST'] ?? '') . url('/api/news')], $sf->t('title'));
    }, ['cap' => 'news.admin']);

    $sf->route('GET', '/edit', function () use ($sf, $db) {
        $id = (int)($_GET['id'] ?? 0);
        $r = $id ? $db->row('SELECT * FROM {p}news_posts WHERE id = ?', [$id]) : null;
        if ($id && !$r) { flash($sf->t('not_found'), 'bad'); redirect('/p/news-publisher/'); }
        $r ??= ['id' => 0, 'slug' => '', 'type' => 'news', 'pinned' => 0, 'published' => 1, 'post_date' => date('Y-m-d'), 'expires' => '', 'title_en' => '', 'title_ru' => '', 'body_en' => '', 'body_ru' => '', 'url' => ''];
        if (!empty($_SESSION['news_form'])) { $r = array_merge($r, $_SESSION['news_form']); unset($_SESSION['news_form']); }   // keep what was typed after a validation error
        $sf->render('edit', ['p' => $r], $sf->t($id ? 'edit_post' : 'new_post'));
    }, ['cap' => 'news.admin']);

    $sf->route('POST', '/save', function () use ($sf, $db, $uid, $go) {
        $id = (int)($_POST['id'] ?? 0);
        [$v, $errors] = Feed::validate($_POST);
        $dup = $db->val('SELECT id FROM {p}news_posts WHERE slug = ? AND id <> ?', [$v['slug'], $id]);
        if ($dup) $errors[] = 'err_slug_taken';
        if ($errors) {
            foreach ($errors as $k) flash($sf->t($k), 'bad');
            $_SESSION['news_form'] = $_POST + ['id' => $id];
            redirect('/p/news-publisher/edit' . ($id ? '?id=' . $id : ''));
        }
        $cols = ['slug', 'type', 'pinned', 'published', 'post_date', 'expires', 'title_en', 'title_ru', 'body_en', 'body_ru', 'url'];
        $vals = array_map(fn($c) => $v[$c], $cols);
        if ($id) { $db->q('UPDATE {p}news_posts SET ' . implode(' = ?, ', $cols) . ' = ? WHERE id = ?', [...$vals, $id]); }
        else { $db->q('INSERT INTO {p}news_posts (' . implode(',', $cols) . ', created_by) VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ',?)', [...$vals, $uid()]); $id = $db->insertId(); }
        $sf->log('news.saved', ['id' => $id, 'slug' => $v['slug']]);
        flash($sf->t($v['published'] ? 'saved_live' : 'saved_draft'));
        $go();
    }, ['cap' => 'news.admin']);

    $sf->route('POST', '/action', function () use ($sf, $db, $go) {
        $id = (int)($_POST['id'] ?? 0); $do = (string)($_POST['do'] ?? '');
        if ($do === 'toggle') { $db->q('UPDATE {p}news_posts SET published = 1 - published WHERE id = ?', [$id]); flash($sf->t('saved')); }
        elseif ($do === 'delete') { $db->q('DELETE FROM {p}news_posts WHERE id = ?', [$id]); $sf->log('news.deleted', ['id' => $id]); flash($sf->t('deleted')); }
        $go();
    }, ['cap' => 'news.admin']);

    /* ---------- public feed read by every installation ---------- */
    $sf->api('GET', 'news', function () use ($live) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: public, max-age=300');
        echo json_encode(array_map([Feed::class, 'post'], $live()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    });

    // this site shows its own posts without fetching its own feed over HTTP
    \Sysfera\Hooks::on('news.local', fn($v) => array_map([Feed::class, 'post'], $live()));

    // demo visitors get every capability — never this one
    \Sysfera\Hooks::on('demo.caps', fn(array $caps) => array_values(array_diff($caps, ['news.admin'])));
};
