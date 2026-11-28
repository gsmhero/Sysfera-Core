<?php
declare(strict_types=1);

use SysferaBlog\{BbSync, BlogError, BlogHub, DbStore, Media, Network, Posts, Profile, Text};
use SysferaMsg\{Ctx, HubError, HubServer};
use Sysfera\{Demo, Hooks, View};

/**
 * My Blog: posts of every user, public on this site and (scope "global") shared with all installations through the Messenger hub.
 * Users with a Basebase shop (wms-basebase component) attach their products and have the posts kept in step with the Basebase blog.
 */
return function (\Sysfera\Plugin $sf): void {
    $mdir = dirname($sf->dir) . '/messenger/lib/';
    foreach (['Util', 'Hub', 'Client'] as $f) require_once $mdir . "$f.php";
    foreach (['Text', 'Media', 'Store', 'Posts', 'Network', 'BbSync', 'Profile'] as $f) require_once __DIR__ . "/lib/$f.php";

    $db = $sf->db();
    $st = new DbStore($db);
    $now = fn(): int => time();
    $posts = new Posts($st, $now);
    $host = strtolower((string)preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')));
    $origin = fn(): string => 'https://' . $host . \Sysfera\App::basePath();
    $dir = SF_ROOT . '/storage/myblog';
    $feedCache = SF_ROOT . '/storage/myblog-feed.json';
    $uid = fn(): int => $sf->auth()->id();
    $admin = fn(): bool => $sf->auth()->isAdmin();
    $go = fn(string $p = '') => redirect('/p/myblog/' . ltrim($p, '/'));
    $err = fn(string $k): string => ($m = $sf->t('e_' . $k)) !== 'myblog.e_' . $k ? $m : $sf->t('e_other', ['k' => $k]);

    /* ---------- connections to other components ---------- */
    $net = function () use ($st): ?Network {
        $c = Demo::on() ? null : Hooks::filter('messenger.client', null);
        if (!$c || !$c->ready()) return null;
        return new Network($st, fn(string $m, string $op, array $q, ?array $body, string $as): array => $c->call($m, $op, $q, $body, null, 4, $as));
    };
    $shop = fn(int $u): ?array => Demo::on() ? null : Hooks::filter('basebase.shop', null, $u);
    $products = fn(int $u): array => (array)Hooks::filter('basebase.products', [], ['owner' => $u]);
    $bb = function (int $u) use ($st, $shop, $origin, $host, $now): ?BbSync {
        if (!$shop($u)) return null;
        return new BbSync($st, fn(string $m, string $suffix, ?array $body): array => Hooks::filter('basebase.request', ['code' => 0, 'data' => [], 'error' => 'not_connected'], $u, $m, $suffix, $body),
            fn(string $name): string => $origin() . '/p/myblog/cover/' . $name, BbSync::siteId($host), $now);
    };

    /** Sends everything that waits to the hub; the feed cache is dropped so the change shows at once. */
    $push = function () use ($net, $shop, $feedCache): void {
        if (!($n = $net())) return;
        if ($n->push(fn(int $u): string => (string)($shop($u)['shop_name'] ?? ''))) { @unlink($feedCache); @unlink($feedCache . '.try'); }
    };

    /** Basebase synchronisation of one user, at most every 5 minutes unless forced. @return ?array result, null when skipped */
    $sync = function (int $u, bool $force = false) use ($bb, $sf): ?array {
        if (!$force && time() - (int)$sf->setting("sync_$u", '0') < 300) return null;
        if (!($s = $bb($u))) return null;
        $sf->setSetting("sync_$u", (string)time());
        try { $r = $s->run($u); } catch (\Throwable $e) { \Sysfera\App::log('myblog sync ' . $e); $r = ['status' => 'error', 'error' => 'server_error', 'pushed' => 0, 'pulled' => 0, 'removed' => 0]; }
        $sf->setSetting("sync_state_$u", $r['status']);
        return $r;
    };

    /** The shared feed's first page, cached for a minute (it is shown on the dashboard and to guests). */
    $latest = function () use ($net, $feedCache): ?array {
        $age = is_file($feedCache) ? time() - (int)filemtime($feedCache) : PHP_INT_MAX;
        $cached = is_file($feedCache) ? json_decode((string)@file_get_contents($feedCache), true) : null;
        if ($age >= 60 && !(is_file($feedCache . '.try') && time() - (int)filemtime($feedCache . '.try') < 30)) {
            @file_put_contents($feedCache . '.try', (string)time());
            if (($n = $net()) && ($r = $n->feed(1)) !== null) { $cached = $r; @file_put_contents($feedCache, json_encode($r, JSON_UNESCAPED_UNICODE)); }
        }
        return is_array($cached) ? $cached : null;
    };

    $localFeed = function (string $q, int $page, ?int $owner = null) use ($st): array {
        [$rows, $total] = $st->publicPosts($owner, $q, BlogHub::PER_PAGE, ($page - 1) * BlogHub::PER_PAGE);
        return ['items' => array_map(fn($p) => Posts::card($p, fn(string $x) => url($x)), $rows), 'total' => $total, 'page' => $page, 'pages' => max(1, (int)ceil($total / BlogHub::PER_PAGE))];
    };

    $css = fn(): string => '<link rel="stylesheet" href="' . e($sf->asset('myblog.css')) . '?v=' . e($sf->manifest['version']) . '">';
    $js = fn(): string => '<script src="' . e($sf->asset('myblog.js')) . '?v=' . e($sf->manifest['version']) . '" defer></script>';
    $page = function (string $view, array $vars, ?string $title = null) use ($sf, $css, $js): void {
        View::page($title ?? $sf->t('title'), $css() . View::file($sf->dir . "/views/$view.php", $vars + ['sf' => $sf]) . $js());
    };
    $publicPage = function (string $view, array $vars, string $title, array $opt = []) use ($sf, $css, $js): void {
        View::publicPage($title, $css() . View::file($sf->dir . "/views/$view.php", $vars + ['sf' => $sf]) . $js(), $opt);
    };
    $notFound = function () use ($publicPage, $sf): void { http_response_code(404); $publicPage('notice', ['msg' => 'e_no_post'], $sf->t('title')); };

    /** Stores one uploaded picture and returns its name; null when nothing was uploaded. @throws BlogError */
    $store = function (mixed $f) use ($dir): ?string {
        if (!is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
        $tmp = (string)($f['tmp_name'] ?? '');
        if (($f['error'] ?? 1) !== UPLOAD_ERR_OK || !is_uploaded_file($tmp) || (int)$f['size'] > Text::MAX_COVER) throw new BlogError('bad_cover');
        $ext = Text::imageExt((string)file_get_contents($tmp, false, null, 0, 16));
        if ($ext === '' || !@getimagesize($tmp)) throw new BlogError('bad_cover');
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        if (!@move_uploaded_file($tmp, "$dir/$name")) throw new BlogError('bad_cover');
        return $name;
    };
    $drop = function (array $names) use ($dir): void { foreach ($names as $n) if (is_string($n) && Text::coverName($n)) @unlink("$dir/$n"); };
    /** Stores the gallery pictures of the form. All or nothing. @return list<string> @throws BlogError */
    $storeMany = function () use ($store, $drop): array {
        $f = $_FILES['gallery'] ?? null; $names = [];
        if (!is_array($f) || !is_array($f['error'] ?? null)) return $names;
        try {
            foreach (array_keys($f['error']) as $i) {
                $one = ['error' => $f['error'][$i], 'tmp_name' => $f['tmp_name'][$i] ?? '', 'size' => $f['size'][$i] ?? 0];
                if (($n = $store($one)) !== null) $names[] = $n;
                if (count($names) > Media::MAX_IMAGES) throw new BlogError('many_images');
            }
        } catch (BlogError $e) { $drop($names); throw $e; }
        return $names;
    };

    $sf->menu('menu', '/', ['order' => 5, 'icon' => 'note']);

    /* ---------- hub side: operations other installations call ---------- */
    $bh = new BlogHub($st, $now);
    $hubRun = function (callable $fn): array {
        try { return $fn(); } catch (BlogError $e) { throw new HubError($e->key, $e->key === 'post_rate' ? 429 : 400); }
    };
    HubServer::register('blog.publish', fn(Ctx $c) => $hubRun(fn() => $bh->publish($c->domain, (int)$c->uid, $c->in)));
    HubServer::register('blog.remove', fn(Ctx $c) => $bh->remove($c->domain, (int)$c->uid, (int)($c->in['post_id'] ?? 0)));
    HubServer::register('blog.feed', fn(Ctx $c) => $bh->feed($c->q, fn(string $d): string => $d === $host ? $origin() : 'https://' . $d), true);   // the installation itself (user 0) reads it for widgets and guests

    /* ---------- the My Blog page ---------- */
    $sf->route('GET', '/', function () use ($sf, $st, $uid, $net, $push, $sync, $localFeed, $latest, $shop, $page, $host) {
        $me = $uid();
        $push(); $sync($me); $push();
        $f = in_array($_GET['f'] ?? '', ['all', 'site', 'mine'], true) ? $_GET['f'] : ($net() ? 'all' : 'site');
        $q = Text::line((string)(is_string($_GET['q'] ?? null) ? $_GET['q'] : ''), 100);
        $pg = max(1, (int)($_GET['page'] ?? 1));
        $offline = false; $d = null;
        if ($f === 'mine') {
            $items = [];
            foreach ($st->ownerPosts($me) as $p) if (!$p['deleted'] && ($q === '' || stripos($p['title'] . ' ' . $p['body'], $q) !== false)) $items[] = Posts::card($p, fn(string $x) => url($x));
            $d = ['items' => $items, 'total' => count($items), 'page' => 1, 'pages' => 1];
        } elseif ($f === 'all') {
            $n = $net();
            $d = $n ? ($pg === 1 && $q === '' ? $latest() : $n->feed($pg, BlogHub::PER_PAGE, $q)) : null;
            if ($d === null) $offline = true;
        }
        if ($d === null) $d = $localFeed($q, $pg);
        $page('index', ['d' => $d, 'f' => $f, 'q' => $q, 'offline' => $offline, 'me' => $me, 'admin' => $sf->auth()->isAdmin(), 'host' => $host, 'shop' => $shop($me), 'state' => $sf->setting("sync_state_$me", ''), 'hubMod' => $sf->auth()->isAdmin() && Hooks::filter('messenger.hub', null) !== null]);
    });

    $sf->route('GET', '/edit', function () use ($sf, $posts, $uid, $admin, $products, $shop, $err, $go, $page) {
        $id = (int)($_GET['id'] ?? 0); $p = null;
        if ($id) { try { $p = $posts->editable($uid(), $admin(), $id); } catch (BlogError $e) { flash($err($e->key), 'bad'); $go('?f=mine'); } }
        $owner = $p ? (int)$p['owner_id'] : $uid();
        sf_session(); $form = $_SESSION['mb_form'] ?? null; unset($_SESSION['mb_form']);
        if (is_array($form) && (int)$form['id'] !== $id) $form = null;
        $page('edit', ['p' => $p, 'form' => $form, 'products' => $products($owner), 'shop' => $shop($owner), 'synced' => $sf->setting("sync_state_$owner", '') === 'ok', 'siteLang' => \Sysfera\I18n::lang()], $sf->t($p ? 'edit_post' : 'new_post'));
    });

    $sf->route('GET', '/products', function () use ($sf, $uid, $products, $shop, $page) {
        $page('products', ['products' => $products($uid()), 'shop' => $shop($uid())], $sf->t('my_products'));
    });

    $sf->route('POST', '/save', function () use ($posts, $uid, $admin, $products, $store, $storeMany, $drop, $push, $sync, $sf, $st, $err, $go) {
        $id = (int)($_POST['id'] ?? 0); $new = null; $added = [];
        try {
            $old = $id ? $posts->editable($uid(), $admin(), $id) : null;
            $owner = $old ? (int)$old['owner_id'] : $uid();
            $new = $store($_FILES['cover'] ?? null);
            $added = $storeMany();
            $own = array_map(fn($p) => (int)$p['bb_id'], $products($owner));
            $saved = $posts->save($uid(), $admin(), $id, $_POST, $own, $new, $added);
        } catch (BlogError $e) {
            $drop(array_merge($added, [$new]));
            sf_session(); $_SESSION['mb_form'] = ['id' => $id] + array_intersect_key($_POST, array_flip(['title', 'body', 'status', 'scope', 'products', 'videos']));
            flash($err($e->key), 'bad'); $go('edit' . ($id ? '?id=' . $id : ''));
        }
        $p = $st->post($saved);
        if ($old) {   // pictures the post no longer shows
            if (!empty($old['cover']) && ($p['cover'] ?? null) !== $old['cover']) $drop([$old['cover']]);
            $drop(array_diff(Media::images($old['images'] ?? null), Media::images($p['images'] ?? null)));
        }
        $r = $sync((int)$p['owner_id'], true); $push();
        $sf->log('post.save', ['id' => $saved]);
        flash($sf->t($p['status'] === 'draft' ? 'saved_draft' : 'saved'));
        if ($r && $r['status'] === 'error') flash($sf->t('sync_error', ['e' => (string)$r['error']]), 'warn');
        $go('post/' . $saved);
    });

    $sf->route('POST', '/delete', function () use ($posts, $uid, $admin, $push, $sync, $sf, $drop, $err, $go) {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $p = $posts->editable($uid(), $admin(), $id);
            $posts->delete($uid(), $admin(), $id);
        } catch (BlogError $e) { flash($err($e->key), 'bad'); $go('?f=mine'); }
        $drop(array_merge(Media::images($p['images'] ?? null), [$p['cover'] ?? null]));
        $sync((int)$p['owner_id'], true); $push();
        $sf->log('post.delete', ['id' => $id]); flash($sf->t('deleted'));
        $go('?f=mine');
    });

    $sf->route('POST', '/sync', function () use ($sf, $uid, $sync, $push, $go) {
        $r = $sync($uid(), true); $push();
        if (!$r) flash($sf->t('no_shop'), 'warn');
        elseif ($r['status'] === 'ok') flash($sf->t('sync_ok', ['a' => $r['pushed'], 'b' => $r['pulled'], 'c' => $r['removed']]));
        elseif ($r['status'] === 'unsupported') flash($sf->t('sync_unsupported'), 'warn');
        else flash($sf->t('sync_error', ['e' => (string)$r['error']]), 'bad');
        $go('?f=mine');
    });

    /* ---------- public pages ---------- */
    $sf->route('GET', '/post/{id}', function (array $a) use ($sf, $st, $uid, $admin, $products, $shop, $notFound, $publicPage) {
        $p = $st->post((int)$a['id']);
        if (!Posts::readable($p, $uid(), $admin())) { $notFound(); return; }
        $own = []; foreach ($products((int)$p['owner_id']) as $x) $own[(int)$x['bb_id']] = $x;
        $attached = []; foreach (Text::ids($p['products']) as $i) if (isset($own[$i])) $attached[] = $own[$i];
        $card = Posts::card($p, fn(string $x) => url($x));
        $s = $shop((int)$p['owner_id']);
        if ($s && ($s['shop_name'] ?? '') !== '') $card['author'] = $s['shop_name'];
        $mine = $uid() && ((int)$p['owner_id'] === $uid() || $admin());
        $publicPage('post', ['p' => $p, 'card' => $card, 'attached' => $attached, 'mine' => $mine], (string)$p['title'], ['description' => Text::excerpt((string)$p['body'], 160)]);
    }, ['auth' => false]);

    /** The shop's sets for a public profile, kept for 10 minutes so guests do not make the site call Basebase on every visit. */
    $sets = function (int $u) use ($bb, $dir): ?array {
        $file = "$dir/sets-$u.json";
        if (is_file($file) && time() - (int)filemtime($file) < 600) return json_decode((string)file_get_contents($file), true);
        $r = ($s = $bb($u)) ? $s->sets() : null;
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        @file_put_contents($file, json_encode($r, JSON_UNESCAPED_UNICODE));
        return $r;
    };

    // the author's public profile: Posts, Products (with a filter sidebar), Sets
    $sf->route('GET', '/u/{id}', function (array $a) use ($sf, $st, $uid, $admin, $products, $shop, $sets, $localFeed, $notFound, $publicPage) {
        $u = (int)$a['id']; $name = $st->userName($u); $own = $uid() > 0 && $uid() === $u;
        $tab = in_array($_GET['tab'] ?? '', Profile::TABS, true) ? $_GET['tab'] : 'posts';
        $pg = max(1, min(10000, (int)($_GET['page'] ?? 1)));
        $all = $name === null ? [] : $products($u);
        $d = $name === null ? ['items' => [], 'total' => 0, 'pages' => 1] : $localFeed('', $tab === 'posts' ? $pg : 1, $u);
        if (!Profile::visible($name, $d['total'], count($all), $own || $admin())) { $notFound(); return; }
        $f = Profile::filters($_GET); $hit = Profile::filter($all, $f);
        $pages = $tab === 'posts' ? $d['pages'] : ($tab === 'products' ? max(1, (int)ceil(count($hit) / Profile::PER_PAGE)) : 1);
        $pg = min($pg, $pages);
        $shopName = (string)($shop($u)['shop_name'] ?? '');
        $about = $sf->setting("about_$u", '');
        $publicPage('profile', ['uid' => $u, 'name' => $name, 'shopName' => $shopName, 'about' => $about, 'tab' => $tab, 'postCount' => $d['total'], 'productCount' => count($all), 'd' => $d,
            'items' => array_slice($hit, ($pg - 1) * Profile::PER_PAGE, Profile::PER_PAGE), 'f' => $f, 'cats' => Profile::categories($all), 'page' => $pg, 'pages' => $pages,
            'sets' => $tab === 'sets' ? $sets($u) : null, 'own' => $own, 'me' => $uid(), 'admin' => $admin()], $shopName !== '' ? $shopName : (string)$name, ['description' => Text::excerpt($about, 160)]);
    }, ['auth' => false]);

    $sf->route('GET', '/cover/{file}', function (array $a) use ($dir) {
        $n = (string)$a['file']; $f = "$dir/$n";
        if (!Text::coverName($n) || !is_file($f)) { http_response_code(404); return; }
        $types = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif'];
        header('Content-Type: ' . $types[pathinfo($n, PATHINFO_EXTENSION)]); header('X-Content-Type-Options: nosniff'); header('Cache-Control: public, max-age=604800'); header('Content-Length: ' . filesize($f)); readfile($f);
    }, ['auth' => false]);

    /* ---------- the public start page: posts of this site or of all sites, and the search ---------- */
    $sf->on('landing.feed', function ($v, array $o) use ($sf, $st, $net, $latest, $localFeed, $products, $css) {
        $q = Text::line((string)($o['q'] ?? ''), 100); $pg = max(1, (int)($o['page'] ?? 1));
        $n = $net(); $scope = $n && ($o['scope'] ?? '') === 'global' ? 'global' : 'local';
        $d = null;
        if ($scope === 'global') $d = $pg === 1 && $q === '' ? $latest() : $n->feed($pg, BlogHub::PER_PAGE, $q);
        $offline = $scope === 'global' && $d === null;
        $d ??= $localFeed($q, $pg);
        $people = []; $found = [];
        if ($q !== '' && $pg === 1) {   // products, people and shops are those of this site
            $people = Profile::people((array)Hooks::filter('basebase.shops', [], $q), $st->publicAuthors($q, 24));
            $found = (array)Hooks::filter('basebase.products', [], ['q' => $q, 'limit' => 24]);
        }
        return ['html' => $css() . View::file($sf->dir . '/views/start.php', ['sf' => $sf, 'd' => $d, 'q' => $q, 'scope' => $scope, 'offline' => $offline, 'people' => $people, 'products' => $found]), 'global' => $n !== null];
    });

    /* ---------- dashboard widget and profile card ---------- */
    $sf->on('dashboard.widgets', function (array $w) use ($sf, $latest, $localFeed, $css) {
        $d = Demo::on() ? null : $latest();
        $shared = $d !== null;
        $w['myblog'] = $css() . View::file($sf->dir . '/views/widget.php', ['sf' => $sf, 'items' => array_slice(($d ?? $localFeed('', 1))['items'], 0, 6), 'shared' => $shared]);
        return $w;
    });

    $sf->on('profile.cards', function (array $cards, array $u) use ($sf, $css) {
        $cards[] = $css() . View::file($sf->dir . '/views/profile_card.php', ['sf' => $sf, 'uid' => (int)$u['id'], 'about' => $sf->setting('about_' . (int)$u['id'], '')]);
        return $cards;
    });

    // "About me" on the core Profile page: shown on the public profile
    $sf->on('profile.save', function (string $do, array $u) use ($sf) {
        if ($do !== 'x_myblog_about') return;
        $sf->setSetting('about_' . (int)$u['id'], Text::cut(trim(str_replace("\r\n", "\n", is_string($_POST['about'] ?? null) ? $_POST['about'] : '')), Profile::ABOUT_MAX));
        flash($sf->t('about_saved'));
    });

    /* ---------- moderation of the shared feed (hub site, administrators) ---------- */
    $sf->route('*', '/moderate', function () use ($sf, $st, $go, $page, $feedCache) {
        if (Hooks::filter('messenger.hub', null) === null) $go();
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            $st->hubHide((int)($_POST['id'] ?? 0), ($_POST['do'] ?? '') === 'hide');
            @unlink($feedCache); $sf->log('post.moderate', ['id' => (int)($_POST['id'] ?? 0), 'do' => (string)($_POST['do'] ?? '')]);
            flash($sf->t('moderated')); $go('moderate');
        }
        $page('moderate', ['rows' => $st->hubAll(200)], $sf->t('moderate'));
    }, ['admin' => true]);

    /* ---------- a user leaves: his posts leave the shared feed ---------- */
    $sf->on('user.deleted', function (int $u) use ($st, $push, $drop, $dir, $db) {
        $db->q('DELETE FROM {p}settings WHERE k IN (?,?,?)', ["plugin.myblog.about_$u", "plugin.myblog.sync_$u", "plugin.myblog.sync_state_$u"]);
        @unlink("$dir/sets-$u.json");
        foreach ($st->ownerPosts($u) as $p) {
            $drop(array_merge(Media::images($p['images'] ?? null), [$p['cover'] ?? null]));
            $st->update((int)$p['id'], ['deleted' => 1, 'hub_dirty' => 1, 'bb_dirty' => 0, 'cover' => null, 'images' => null, 'updated_at' => date('Y-m-d H:i:s')]);   // the Basebase shop is the user's own and stays
        }
        $push();
    });

    $sf->on('demo.reset', function () use ($db) { foreach (['myblog_posts', 'myblog_hub'] as $t) $db->q("DELETE FROM {p}$t"); });
};
