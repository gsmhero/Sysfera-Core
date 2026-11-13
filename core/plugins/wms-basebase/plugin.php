<?php
declare(strict_types=1);

use SysferaBb\{Bb, Client, Sync};

/** Sysfera WMS → Basebase connector. Talks to Basebase Partner API v1 (docs/BASEBASE-SYSFERA-API-v1.md). Requires the "wms" component. */
return function (\Sysfera\Plugin $sf): void {
    foreach (['Bb', 'Client', 'Sync'] as $c) require_once __DIR__ . "/lib/$c.php";

    $db = $sf->db();
    $cfg = fn(string $k, string $d = ''): string => $sf->setting($k, $d);
    $set = fn(string $k, string $v) => $sf->setSetting($k, $v);
    $client = new Client($cfg);

    /** the Sync service, or null while the WMS component is missing/disabled */
    $sync = function () use ($sf, $db, $client, $cfg, $set): ?Sync {
        static $s = null;
        if ($s) return $s;
        $svc = \Sysfera\Hooks::filter('wms.services', []);
        if (empty($svc['docs']) || empty($svc['stock'])) return null;
        $note = function (string $t, array $d) use ($sf): void {
            $sf->log($t, $d);
            $link = 'https://' . ($_SERVER['HTTP_HOST'] ?? '') . url('/p/wms-basebase/orders');
            if ($t === 'order.received') $sf->notify($sf->t('mail_order_subject', ['n' => $d['number'] ?? '']), $sf->t('mail_order_text', ['order' => $d['order'] ?? '', 'n' => $d['number'] ?? '', 'url' => $link]));
            elseif ($t === 'order.failed') {
                $why = array_map(function (string $k) use ($sf): string {
                    if (str_starts_with($k, 'stock:')) { [$n, $have, $need] = array_pad(explode('|', substr($k, 6)), 3, ''); return $sf->t('err_nostock', ['name' => $n, 'have' => $have, 'need' => $need]); }
                    return preg_match('/^[a-z_]+$/', $k) ? $sf->t($k) : $k;
                }, explode(' | ', (string)($d['error'] ?? '')));
                $sf->notify($sf->t('mail_fail_subject', ['order' => $d['order'] ?? '']), $sf->t('mail_fail_text', ['order' => $d['order'] ?? '', 'why' => implode('; ', $why), 'url' => $link]));
            }
        };
        return $s = new Sync($db, $svc, $client, $cfg, $set, $sf->contacts(), $note);
    };
    $page = fn(string $view, array $vars, string $title, string $tab) => $sf->render($view, $vars + ['tab' => $tab, 'client' => $client, 'cfg' => $cfg, 'sync' => $sync()], $title);
    $route = function (string $method, string $path, callable $fn, string $cap = 'wmsbb.use') use ($sf, $sync): void {
        $sf->route($method, $path, function (array $p) use ($fn, $sync) {
            if (!$sync()) { flash(t('plugins.need_plugin', ['name' => 'wms']), 'bad'); redirect('/plugins'); }
            return $fn($p);
        }, ['cap' => $cap]);
    };
    $go = fn(string $p = '') => redirect('/p/wms-basebase/' . ltrim($p, '/'));

    $sf->menu('menu', '/', ['cap' => 'wmsbb.use', 'order' => 25, 'icon' => 'refresh']);

    /* ================= overview ================= */
    $route('GET', '/', function () use ($sf, $db, $page, $sync, $cfg, $set) {
        $s = $sync();
        if ($s->ready() && time() - (int)$cfg('last_poll', '0') > 300) { $set('last_poll', (string)time()); $s->pull(); }   // lazy polling fallback
        $stats = [
            'published' => (int)$db->val('SELECT COUNT(*) FROM {p}wmsbb_products WHERE published = 1'),
            'errors' => (int)$db->val('SELECT COUNT(*) FROM {p}wmsbb_products WHERE published = 1 AND last_error IS NOT NULL'),
            'orders' => (int)$db->val('SELECT COUNT(*) FROM {p}wmsbb_items'),
            'open' => (int)$db->val("SELECT COUNT(*) FROM {p}wmsbb_items WHERE status IN ('pending','prepare')"),
        ];
        $attention = $db->rows('SELECT * FROM {p}wmsbb_items WHERE error IS NOT NULL ORDER BY item_id DESC LIMIT 10');
        $page('overview', ['stats' => $stats, 'attention' => $attention, 'badProducts' => $db->rows('SELECT m.product_id, p.name, m.last_error FROM {p}wmsbb_products m JOIN {p}wms_products p ON p.id = m.product_id WHERE m.published = 1 AND m.last_error IS NOT NULL LIMIT 10')], $sf->t('menu'), 'overview');
    });

    /* ================= catalog ================= */
    $route('GET', '/catalog', function () use ($sf, $db, $page, $sync, $cfg) {
        $q = trim((string)($_GET['q'] ?? '')); $only = (string)($_GET['only'] ?? '');
        $w = ' WHERE p.archived = 0'; $a = [];
        if ($q !== '') { $w .= ' AND (p.name LIKE ? OR p.sku LIKE ? OR p.code LIKE ?)'; $l = '%' . $q . '%'; array_push($a, $l, $l, $l); }
        if ($only === 'pub') $w .= ' AND m.published = 1';
        $per = 40; $total = (int)$db->val("SELECT COUNT(*) FROM {p}wms_products p LEFT JOIN {p}wmsbb_products m ON m.product_id = p.id$w", $a);
        $pages = max(1, (int)ceil($total / $per)); $cur = min(max(1, (int)($_GET['page'] ?? 1)), $pages);
        $rows = $db->rows("SELECT p.id, p.name, p.sku, p.code, p.unit, p.sell_price, m.external_id, m.published, m.category, m.images, p.images AS wms_images, m.synced_at, m.last_error, m.last_stock, m.dirty
            FROM {p}wms_products p LEFT JOIN {p}wmsbb_products m ON m.product_id = p.id$w ORDER BY m.published DESC, p.name LIMIT $per OFFSET " . (($cur - 1) * $per), $a);
        $s = $sync();
        foreach ($rows as &$r) $r['available'] = $s->available((int)$r['id']);
        unset($r);
        $page('catalog', ['rows' => $rows, 'q' => $q, 'only' => $only, 'total' => $total, 'pg' => ['pages' => $pages, 'cur' => $cur], 'cats' => json_decode($cfg('categories', '[]'), true) ?: []], $sf->t('nav_catalog'), 'catalog');
    });
    $route('POST', '/catalog', function () use ($sf, $db, $sync, $go) {
        $s = $sync(); $errors = 0; $done = 0;
        if (($_POST['do'] ?? '') === 'sync_all') {
            $r = $s->pushAll(); flash($sf->t('synced', ['ok' => $r['ok'], 'bad' => $r['failed']]), $r['failed'] ? 'bad' : 'ok'); $go('catalog');
        }
        foreach ((array)($_POST['row'] ?? []) as $pid => $f) {
            $pid = (int)$pid; $p = $db->row('SELECT * FROM {p}wms_products WHERE id = ?', [$pid]); if (!$p) continue;
            $pub = !empty($f['pub']) ? 1 : 0; $cat = mb_substr(trim((string)($f['cat'] ?? '')), 0, 120); $img = mb_substr(trim(str_replace("", '', (string)($f['images'] ?? ''))), 0, 4000);
            if ($img === trim(str_replace("", '', (string)($p['images'] ?? '')))) $img = '';   // same as the product card: keep following it
            $cur = $db->row('SELECT * FROM {p}wmsbb_products WHERE product_id = ?', [$pid]);
            if (!$cur && !$pub) continue;                       // nothing to remember
            if (!$cur) {
                $ext = Bb::externalId($p);
                if ($db->val('SELECT 1 FROM {p}wmsbb_products WHERE external_id = ?', [$ext])) $ext .= '-' . $pid;
                $db->q('INSERT INTO {p}wmsbb_products (product_id, external_id, published, category, images, dirty) VALUES (?,?,?,?,?,1)', [$pid, $ext, $pub, $cat ?: null, $img ?: null]);
            } else {
                if ((int)$cur['published'] === $pub && (string)$cur['category'] === $cat && (string)$cur['images'] === $img) continue;   // unchanged
                $db->q('UPDATE {p}wmsbb_products SET published = ?, category = ?, images = ?, dirty = 1 WHERE product_id = ?', [$pub, $cat ?: null, $img ?: null, $pid]);
            }
            $s->pushProduct($pid) === null ? $done++ : $errors++;
        }
        flash($sf->t('synced', ['ok' => $done, 'bad' => $errors]), $errors ? 'bad' : 'ok'); $go('catalog');
    });

    /* ================= orders ================= */
    $route('GET', '/orders', function () use ($sf, $db, $page) {
        $rows = $db->rows('SELECT i.*, d.number, d.status AS doc_status FROM {p}wmsbb_items i LEFT JOIN {p}wms_docs d ON d.id = i.doc_id ORDER BY i.item_id DESC LIMIT 100');
        $page('orders', ['rows' => $rows], $sf->t('nav_orders'), 'orders');
    });
    $route('POST', '/orders', function () use ($sf, $sync, $go) {
        $s = $sync(); $do = (string)($_POST['do'] ?? ''); $id = (int)($_POST['item'] ?? 0);
        if ($do === 'pull') { $n = $s->pull(); flash($sf->t('pulled', ['n' => $n])); }
        elseif ($do === 'retry') { $e = $s->retry($id); flash($e ? $sf->t('retry_failed') : $sf->t('retry_ok'), $e ? 'bad' : 'ok'); }
        elseif ($do === 'shipped') { $e = $s->tell($id, 'shipped'); flash($e ? $e : $sf->t('saved'), $e ? 'bad' : 'ok'); }
        $go('orders');
    });

    /* ================= settings ================= */
    $route('GET', '/settings', function () use ($sf, $db, $page, $cfg, $set) {
        if ($cfg('cron_token') === '') $set('cron_token', bin2hex(random_bytes(16)));
        if ($cfg('agent_key') === '') $set('agent_key', bin2hex(random_bytes(16)));   // AGENT_KEY of the contract (§2): identifies this install when the agent is linked in Basebase
        $page('settings', ['whs' => $db->rows('SELECT id, name FROM {p}wms_warehouses WHERE active = 1 ORDER BY is_default DESC, name')], $sf->t('nav_settings'), 'settings');
    }, 'wmsbb.admin');
    $route('POST', '/settings', function () use ($sf, $sync, $cfg, $set, $go) {
        $s = $sync(); $do = (string)($_POST['do'] ?? 'save');
        if ($do === 'save') {
            $set('agent_id', (string)max(0, (int)($_POST['agent_id'] ?? 0)));
            $url = trim((string)($_POST['api_url'] ?? '')); $set('api_url', $url !== '' ? $url : 'https://www.basebase.ru/api/v1');
            if (trim((string)($_POST['secret'] ?? '')) !== '') $set('secret', trim((string)$_POST['secret']));
            $set('seller_id', (string)max(0, (int)($_POST['seller_id'] ?? 0)));
            $ws = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['warehouse_ids'] ?? [])))));
            $set('warehouse_ids', json_encode($ws)); $set('warehouse_id', (string)($ws[0] ?? 0));
            flash($sf->t('saved'));
        } elseif ($do === 'test') {
            $r = $s->ping();
            if ($r['error'] === null) { $s->refreshCategories(); flash($sf->t('ping_ok', ['fee' => (string)($r['data']['agency_fee'] ?? '?'), 'pc' => (string)($r['data']['platform_commission'] ?? '?')])); }
            else flash($sf->t('ping_fail', ['e' => $r['error']]), 'bad');
        } elseif ($do === 'create_seller') {
            $r = (new Client($cfg))->request('POST', '/sellers', ['email' => trim((string)($_POST['email'] ?? '')), 'name' => trim((string)($_POST['name'] ?? '')), 'shop_name' => trim((string)($_POST['shop_name'] ?? '')),
                'phone' => trim((string)($_POST['phone'] ?? '')), 'external_id' => 'sysfera-' . substr(md5((string)($_SERVER['HTTP_HOST'] ?? 'sysfera')), 0, 10)], 10);
            if ($r['error'] === null && !empty($r['data']['seller_id'])) { $set('seller_id', (string)(int)$r['data']['seller_id']); flash($sf->t('seller_created', ['id' => (int)$r['data']['seller_id']])); }
            else flash($sf->t('ping_fail', ['e' => $r['error'] ?? '?']), 'bad');
        } elseif ($do === 'token') { $set('cron_token', bin2hex(random_bytes(16))); flash($sf->t('saved')); }
        $go('settings');
    }, 'wmsbb.admin');

    /* ================= machine endpoints (called by Basebase / by cron) ================= */
    $json = function (array $d, int $code = 200): void { http_response_code($code); header('Content-Type: application/json; charset=utf-8'); echo json_encode($d, JSON_UNESCAPED_UNICODE); };

    $sf->api('POST', 'basebase/webhook', function () use ($cfg, $sync, $json) {
        $raw = (string)file_get_contents('php://input');
        $ok = (int)($_SERVER['HTTP_X_BB_AGENT'] ?? 0) === (int)$cfg('agent_id', '0')
            && Bb::verify($cfg('secret', ''), (int)($_SERVER['HTTP_X_BB_TIMESTAMP'] ?? 0), 'POST', '/api/basebase/webhook', $raw, (string)($_SERVER['HTTP_X_BB_SIGNATURE'] ?? ''));
        if (!$ok) { $json(['error' => 'unauthorized', 'message' => 'Bad agent, timestamp or signature.'], 401); return; }
        $ev = json_decode($raw, true);
        if (!is_array($ev)) { $json(['error' => 'bad_json', 'message' => 'JSON object expected.'], 400); return; }
        $s = $sync();
        if (!$s) { $json(['error' => 'unavailable', 'message' => 'Warehouse component is not enabled.'], 503); return; }   // Basebase will retry
        try { $handled = $s->handleEvent($ev); }
        catch (\Throwable $e) { @file_put_contents(SF_ROOT . '/storage/error.log', date('c') . ' basebase webhook ' . $e . "\n", FILE_APPEND); $json(['error' => 'server_error', 'message' => 'Could not process the event.'], 500); return; }
        $handled ? $json(['ok' => true]) : $json(['error' => 'bad_event', 'message' => 'event_id and event are required.'], 400);
    });

    // for the hosting's cron:  curl -s "https://<site>/api/basebase/poll?token=<token from Settings>"
    $sf->api('GET', 'basebase/poll', function () use ($cfg, $sync, $set, $json) {
        $t = $cfg('cron_token', '');
        if ($t === '' || !hash_equals($t, (string)($_GET['token'] ?? ''))) { $json(['error' => 'unauthorized'], 401); return; }
        $s = $sync(); if (!$s || !$s->ready()) { $json(['error' => 'not_ready'], 503); return; }
        $set('last_poll', (string)time());
        $new = $s->pull(); $push = $s->pushAll(true);
        $json(['ok' => true, 'new_items' => $new, 'products_ok' => $push['ok'], 'products_failed' => $push['failed']]);
    });

    /* ================= demo mode ================= */
    // visitors of a public demo must not push demo data to a real Basebase account; the owner's connection settings survive a reset
    $sf->on('demo.reset', function () use ($db) { foreach (['wmsbb_products', 'wmsbb_items', 'wmsbb_events'] as $t) $db->q("DELETE FROM {p}$t"); });
    \Sysfera\Hooks::on('demo.caps', fn(array $caps) => array_values(array_diff($caps, ['wmsbb.use', 'wmsbb.admin'])));

    /* ================= fields on the WMS product card ================= */
    $sf->on('wms.product.fields', function (string $html, ?array $p) use ($sf, $db, $cfg): string {
        if (!$sf->can('wmsbb.use')) return $html;
        $m = $p ? $db->row('SELECT published, category FROM {p}wmsbb_products WHERE product_id = ?', [(int)$p['id']]) : null;
        $cats = json_decode($cfg('categories', '[]'), true) ?: [];
        ob_start(); ?>
  <h3><?= e($sf->t('pf_h')) ?></h3>
  <input type="hidden" name="bb_form" value="1">
  <div class="cols">
    <label class="fld"><span><?= e($sf->t('pf_cat')) ?></span><input name="bb_cat" list="bbcats-pf" value="<?= e($m['category'] ?? '') ?>" maxlength="120" placeholder="<?= e($sf->t('category_ph')) ?>" autocomplete="off"><datalist id="bbcats-pf"><?php foreach ($cats as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist><small class="muted"><?= e($sf->t($cats ? 'pf_cat_hint' : 'pf_cat_hint0')) ?></small></label>
    <label class="fld"><span><?= e($sf->t('pf_pub')) ?></span><label class="chk"><input type="checkbox" name="bb_pub" value="1" <?= !empty($m['published']) ? 'checked' : '' ?>> <?= e($sf->t('pf_pub_l')) ?></label><small class="muted"><?= e($sf->t('pf_pub_hint')) ?></small></label>
  </div>
<?php
        return $html . ob_get_clean();
    });
    $sf->on('wms.product.saved', function (int $pid, array $post) use ($sf, $db, $sync): void {
        if (empty($post['bb_form']) || !$sf->can('wmsbb.use')) return;
        $p = $db->row('SELECT * FROM {p}wms_products WHERE id = ?', [$pid]); if (!$p) return;
        $pub = !empty($post['bb_pub']) ? 1 : 0; $cat = mb_substr(trim((string)($post['bb_cat'] ?? '')), 0, 120);
        $cur = $db->row('SELECT * FROM {p}wmsbb_products WHERE product_id = ?', [$pid]);
        if (!$cur && !$pub) return;
        if (!$cur) {
            $ext = Bb::externalId($p);
            if ($db->val('SELECT 1 FROM {p}wmsbb_products WHERE external_id = ?', [$ext])) $ext .= '-' . $pid;
            $db->q('INSERT INTO {p}wmsbb_products (product_id, external_id, published, category, dirty) VALUES (?,?,?,?,1)', [$pid, $ext, $pub, $cat ?: null]);
        } else $db->q('UPDATE {p}wmsbb_products SET published = ?, category = ?, dirty = 1 WHERE product_id = ?', [$pub, $cat ?: null, $pid]);
        $s = $sync();
        if ($s && $s->ready()) { $err = $s->pushProduct($pid); if ($err !== null) flash($sf->t('pf_push_fail', ['e' => preg_match('/^[a-z_]+$/', $err) ? $sf->t($err) : $err]), 'bad'); }
    });

    /* ================= react to the warehouse ================= */
    $sf->on('wms.doc_changed', function (int $id, string $action) use ($sync, $client) {
        if (!$client->configured()) return;
        $s = $sync(); if ($s && $s->ready()) $s->onDocChanged($id, $action);
    });
};
