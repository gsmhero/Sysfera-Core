<?php
declare(strict_types=1);

use SysferaMsg\{Activity, Client, Ctx, HubError, HubServer, Text};

/**
 * Sysfera Activity: a public feed where every registered user of every Sysfera installation can post (title up to 20 words,
 * text up to 50 words) and write to the author through Sysfera Messenger. Posts live on the hub (core.sysfera.ru).
 */
return function (\Sysfera\Plugin $sf): void {
    $mdir = dirname($sf->dir) . '/messenger/lib/';
    foreach (['Util', 'Hub', 'Client'] as $f) require_once $mdir . "$f.php";
    $db = $sf->db();
    $client = fn(): ?Client => \Sysfera\Hooks::filter('messenger.client', null);
    $go = fn(string $p = '') => redirect('/p/activity/' . ltrim($p, '/'));
    $date = fn(string $v) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && checkdate((int)substr($v, 5, 2), (int)substr($v, 8, 2), (int)substr($v, 0, 4)) ? $v : '';
    $err = fn(?string $k) => ($k && ($m = $sf->t('e_' . $k)) !== 'activity.e_' . $k) ? $m : $sf->t('e_other', ['k' => (string)$k]);

    $sf->menu('menu', '/', ['order' => 13, 'icon' => 'bell']);
    \Sysfera\Hooks::on('demo.caps', fn(array $caps) => array_values(array_diff($caps, ['activity.admin'])));

    /* ---------- hub side: operations other installations can call ---------- */
    HubServer::register('act.post', function (Ctx $c, \Sysfera\Db $db) {
        $title = trim((string)($c->in['title'] ?? '')); $body = trim((string)($c->in['body'] ?? ''));
        if ($e = Text::checkPost($title, $body)) throw new HubError($e, 400);
        if ((int)$db->val('SELECT COUNT(*) FROM {p}act_posts WHERE sid = ? AND created > (NOW() - INTERVAL 1 DAY)', [$c->sid()]) >= Activity::POSTS_PER_DAY) throw new HubError('post_rate', 429);
        $db->q('INSERT INTO {p}act_posts (sid, title, body) VALUES (?,?,?)', [$c->sid(), $title, $body]);
        return ['id' => $db->insertId()];
    });

    HubServer::register('act.feed', function (Ctx $c, \Sysfera\Db $db) use ($date) {
        $per = in_array((int)($c->q['per'] ?? 5), [5, 20, 50], true) ? (int)$c->q['per'] : 5;
        $from = $date((string)($c->q['from'] ?? '')); $to = $date((string)($c->q['to'] ?? ''));
        $where = 'p.hidden = 0' . ($from !== '' ? ' AND p.created >= ?' : '') . ($to !== '' ? ' AND p.created < ? + INTERVAL 1 DAY' : '');
        $args = array_values(array_filter([$from, $to], fn($x) => $x !== ''));
        $total = (int)$db->val("SELECT COUNT(*) FROM {p}act_posts p WHERE $where", $args);
        $pages = max(1, (int)ceil($total / $per)); $page = min(max(1, (int)($c->q['page'] ?? 1)), $pages);
        $rows = $db->rows("SELECT p.id, p.sid, p.title, p.body, p.created, COALESCE(u.name, p.sid) AS name,
                (SELECT COUNT(*) FROM {p}act_reports r WHERE r.post_id = p.id AND r.sid = ?) AS reported
            FROM {p}act_posts p LEFT JOIN {p}msg_users u ON u.sid = p.sid WHERE $where ORDER BY p.id DESC LIMIT $per OFFSET " . (($page - 1) * $per), [$c->sid(), ...$args]);
        return ['items' => array_map(fn($r) => ['id' => (int)$r['id'], 'sid' => $r['sid'], 'name' => $r['name'], 'title' => $r['title'], 'body' => $r['body'], 'at' => $r['created'],
            'mine' => $r['sid'] === $c->sid(), 'reported' => (int)$r['reported'] > 0], $rows), 'total' => $total, 'pages' => $pages, 'page' => $page, 'per' => $per];
    });

    HubServer::register('act.delete', function (Ctx $c, \Sysfera\Db $db) {
        $db->q('DELETE FROM {p}act_posts WHERE id = ? AND sid = ?', [(int)($c->in['id'] ?? 0), $c->sid()]);
        return ['ok' => true];
    });

    HubServer::register('act.report', function (Ctx $c, \Sysfera\Db $db) {
        $id = (int)($c->in['id'] ?? 0);
        $p = $db->row('SELECT sid FROM {p}act_posts WHERE id = ?', [$id]);
        if (!$p) throw new HubError('no_post', 404);
        if ($p['sid'] === $c->sid()) throw new HubError('own_post', 400);
        $db->q('INSERT IGNORE INTO {p}act_reports (post_id, sid) VALUES (?,?)', [$id, $c->sid()]);
        $n = (int)$db->val('SELECT COUNT(*) FROM {p}act_reports WHERE post_id = ?', [$id]);
        $db->q('UPDATE {p}act_posts SET reports = ?, hidden = IF(? >= ?, 1, hidden) WHERE id = ?', [$n, $n, Activity::HIDE_AFTER_REPORTS, $id]);
        return ['ok' => true];
    });

    /* ---------- pages ---------- */
    $ready = function () use ($sf, $client): ?Client {
        if (\Sysfera\Demo::on()) { $sf->render('notice', ['msg' => 'demo'], $sf->t('title')); return null; }
        $c = $client();
        if (!$c || !$c->ready()) { $sf->render('notice', ['msg' => 'not_connected'], $sf->t('title')); return null; }
        $me = $sf->auth()->user() ?? [];
        sf_session();
        if (!isset($_SESSION['act_me']) || time() - $_SESSION['act_me'] > 600) { $c->call('POST', 'me', [], ['name' => (string)($me['name'] ?? ''), 'email' => (string)($me['email'] ?? '')]); $_SESSION['act_me'] = time(); }
        return $c;
    };

    $sf->route('GET', '/', function () use ($sf, $ready, $date, $err) {
        if (!($c = $ready())) return;
        $per = in_array((int)($_GET['per'] ?? 5), [5, 20, 50], true) ? (int)$_GET['per'] : 5;
        $from = $date((string)($_GET['from'] ?? '')); $to = $date((string)($_GET['to'] ?? ''));
        $r = $c->call('GET', 'act.feed', ['per' => $per, 'page' => (int)($_GET['page'] ?? 1), 'from' => $from, 'to' => $to]);
        if (!$r['ok']) flash($err($r['error']), 'bad');
        $d = $r['data'] + ['items' => [], 'total' => 0, 'pages' => 1, 'page' => 1, 'per' => $per];
        $sf->render('index', ['d' => $d, 'from' => $from, 'to' => $to, 'sid' => $c->me(), 'chatUrl' => url('/p/messenger/chat?with='), 'mod' => $sf->can('activity.admin') && \Sysfera\Hooks::filter('messenger.hub', null) !== null], $sf->t('title'));
    });

    $sf->route('POST', '/post', function () use ($sf, $ready, $go, $err) {
        if (!($c = $ready())) return;
        $r = $c->call('POST', 'act.post', [], ['title' => (string)($_POST['title'] ?? ''), 'body' => (string)($_POST['body'] ?? '')]);
        if ($r['ok']) flash($sf->t('posted'));
        else { flash($err($r['error']), 'bad'); $_SESSION['act_form'] = ['title' => (string)($_POST['title'] ?? ''), 'body' => (string)($_POST['body'] ?? '')]; }
        $go();
    });

    $sf->route('POST', '/action', function () use ($sf, $ready, $go, $err) {
        if (!($c = $ready())) return;
        $do = (string)($_POST['do'] ?? ''); $id = (int)($_POST['id'] ?? 0);
        if (!in_array($do, ['delete', 'report'], true)) $go();
        $r = $c->call('POST', 'act.' . $do, [], ['id' => $id]);
        flash($r['ok'] ? $sf->t($do === 'delete' ? 'deleted' : 'reported') : $err($r['error']), $r['ok'] ? 'ok' : 'bad');
        $go();
    });

    /* ---------- moderation (hub site only) ---------- */
    $sf->route('*', '/moderate', function () use ($sf, $db, $go) {
        if (\Sysfera\Hooks::filter('messenger.hub', null) === null) { $go(); }
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            $id = (int)($_POST['id'] ?? 0); $do = (string)($_POST['do'] ?? '');
            if ($do === 'hide') $db->q('UPDATE {p}act_posts SET hidden = 1 WHERE id = ?', [$id]);
            elseif ($do === 'show') $db->q('UPDATE {p}act_posts SET hidden = 0 WHERE id = ?', [$id]);
            elseif ($do === 'remove') { $db->q('DELETE FROM {p}act_posts WHERE id = ?', [$id]); $db->q('DELETE FROM {p}act_reports WHERE post_id = ?', [$id]); }
            $sf->log('activity.' . $do, ['id' => $id]); flash($sf->t('saved')); $go('moderate');
        }
        $rows = $db->rows('SELECT p.*, COALESCE(u.name, p.sid) AS name FROM {p}act_posts p LEFT JOIN {p}msg_users u ON u.sid = p.sid WHERE p.hidden = 1 OR p.reports > 0 ORDER BY p.hidden DESC, p.reports DESC, p.id DESC LIMIT 100');
        $sf->render('moderate', ['rows' => $rows], $sf->t('moderate'));
    }, ['cap' => 'activity.admin']);
};
