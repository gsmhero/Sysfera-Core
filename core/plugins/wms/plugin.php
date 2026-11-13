<?php
declare(strict_types=1);

use SysferaWms\{Posting, Stock, Docs, W};

/** Sysfera WMS — warehouse management component. */
return function (\Sysfera\Plugin $sf): void {
    foreach (['Posting', 'Stock', 'Docs', 'W'] as $c) require_once __DIR__ . "/lib/$c.php";

    $db = $sf->db();
    $stock = new Stock($db);
    $docs = new Docs($db, $stock, fn(string $k, string $d = '') => $sf->setting($k, $d));
    $uid = fn() => $sf->auth()->id() ?: null;
    $num = fn($v) => round((float)str_replace([',', "\u{202F}", ' '], ['.', '', ''], (string)$v), 3);
    $self = fn(string $p = '') => '/p/wms/' . ltrim($p, '/');

    /** a first warehouse exists from the very first visit */
    $ensure = function () use ($db, $sf): void {
        static $done = false; if ($done) return; $done = true;
        if (!(int)$db->val('SELECT COUNT(*) FROM {p}wms_warehouses')) $db->q('INSERT INTO {p}wms_warehouses (name, code, is_default) VALUES (?,?,1)', [$sf->t('main_warehouse'), 'MAIN']);
    };
    $route = function (string $method, string $path, callable $fn, string $cap = 'wms.use') use ($sf, $ensure): void {
        $sf->route($method, $path, function (array $p) use ($fn, $ensure) { $ensure(); return $fn($p); }, ['cap' => $cap]);
    };
    $page = fn(string $view, array $vars, string $title, string $tab) => $sf->render($view, $vars + ['tab' => $tab, 'stockApi' => $stock], $title);
    $warehouses = fn(bool $all = false) => $db->rows('SELECT * FROM {p}wms_warehouses' . ($all ? '' : ' WHERE active = 1') . ' ORDER BY is_default DESC, name');
    $partners = fn(?string $role = null) => array_values(array_filter(
        array_map(function ($r) { $r['roles'] = (json_decode((string)$r['custom'], true)['roles'] ?? []); return $r; }, $db->rows('SELECT id, name, company, phone, email, custom FROM {p}contacts ORDER BY name LIMIT 1000')),
        fn($r) => $role === null || in_array($role, $r['roles'], true)));

    $sf->menu('menu', '/', ['cap' => 'wms.use', 'order' => 20, 'icon' => 'box']);

    /* ================= dashboard ================= */
    $route('GET', '/', function () use ($sf, $stock, $docs, $db, $page) {
        $low = $stock->summary(['only' => 'low'], 8);
        $recent = $docs->list([], 8);
        $open = ['purchase' => 0, 'sale' => 0];
        foreach ($db->rows("SELECT d.type, d.id FROM {p}wms_docs d WHERE d.type IN ('purchase','sale') AND d.status = 'posted'") as $o) { $pr = $docs->progress((int)$o['id']); if ($pr['done'] + 0.0000001 < $pr['ordered']) $open[$o['type']]++; }
        $page('dashboard', ['totals' => $stock->totals(), 'low' => $low, 'recent' => $recent, 'open' => $open, 'drafts' => $docs->count(['status' => 'draft'])], $sf->t('nav_dashboard'), 'dashboard');
    });

    /* ================= products ================= */
    $route('GET', '/products', function () use ($sf, $stock, $db, $page) {
        $f = ['q' => trim((string)($_GET['q'] ?? '')), 'group_id' => (int)($_GET['group'] ?? 0), 'only' => (string)($_GET['only'] ?? 'all')];
        $per = 30; $total = $stock->summaryCount($f); $pg = W::page($total, $per, (int)($_GET['page'] ?? 1));
        $page('products', ['rows' => $stock->summary($f, $per, ($pg['cur'] - 1) * $per), 'total' => $total, 'pg' => $pg, 'f' => $f,
            'groups' => $db->rows('SELECT g.*, (SELECT COUNT(*) FROM {p}wms_products p WHERE p.group_id = g.id AND p.archived = 0) AS n FROM {p}wms_groups g ORDER BY g.name')], $sf->t('nav_products'), 'products');
    });
    $route('POST', '/products', function () use ($sf, $db) {
        $do = (string)($_POST['do'] ?? '');
        if ($do === 'group' && trim((string)($_POST['name'] ?? '')) !== '') { $db->q('INSERT INTO {p}wms_groups (name) VALUES (?)', [mb_substr(trim((string)$_POST['name']), 0, 160)]); flash($sf->t('group_added')); }
        elseif ($do === 'group_delete') { $g = (int)($_POST['id'] ?? 0); $db->q('UPDATE {p}wms_products SET group_id = NULL WHERE group_id = ?', [$g]); $db->q('DELETE FROM {p}wms_groups WHERE id = ?', [$g]); flash($sf->t('group_deleted')); }
        redirect(wms_path('/products'));
    });

    $productForm = function (?int $id) use ($sf, $db, $stock, $page, $warehouses): void {
        $p = $id ? $db->row('SELECT * FROM {p}wms_products WHERE id = ?', [$id]) : null;
        if ($id && !$p) { http_response_code(404); echo e($sf->t('not_found')); return; }
        $page('product', ['p' => $p, 'groups' => $db->rows('SELECT * FROM {p}wms_groups ORDER BY name'),
            'whs' => array_values(array_filter($warehouses(), fn($w) => !$w['use_cells'])), 'byWh' => $id ? $stock->byWarehouse($id) : [], 'byCell' => $id ? $stock->byCell($id) : [], 'history' => $id ? $stock->history($id, 20) : [],
            'units' => ['шт', 'кг', 'г', 'л', 'м', 'упак', 'компл', 'пара']], $p ? $p['name'] : $sf->t('new_product'), 'products');
    };
    $route('GET', '/products/new', fn() => $productForm(null));
    $route('GET', '/products/{id}', fn(array $a) => $productForm((int)$a['id']));
    $saveProduct = function (?int $id) use ($sf, $db, $num, $docs): void {
        $isEdit = $id !== null; $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') { flash($sf->t('err_name'), 'bad'); redirect(wms_path($id ? "/products/$id" : '/products/new')); }
        if (($_POST['do'] ?? '') === 'archive' && $id) { $db->q('UPDATE {p}wms_products SET archived = 1 WHERE id = ?', [$id]); flash($sf->t('product_archived')); redirect(wms_path('/products')); }
        if (($_POST['do'] ?? '') === 'restore' && $id) { $db->q('UPDATE {p}wms_products SET archived = 0 WHERE id = ?', [$id]); flash($sf->t('product_restored')); redirect(wms_path("/products/$id")); }
        $code = trim((string)($_POST['code'] ?? ''));
        if ($code === '') { $k = 'wms.counter.product'; $db->q('INSERT IGNORE INTO {p}settings (k, v) VALUES (?, ?)', [$k, '0']); $db->q('UPDATE {p}settings SET v = LAST_INSERT_ID(CAST(v AS UNSIGNED) + 1) WHERE k = ?', [$k]); $code = str_pad((string)$db->val('SELECT LAST_INSERT_ID()'), 5, '0', STR_PAD_LEFT); }
        $vat = trim((string)($_POST['vat'] ?? ''));
        $v = [mb_substr($name, 0, 255), ((int)($_POST['group_id'] ?? 0)) ?: null, mb_substr(trim((string)($_POST['sku'] ?? '')), 0, 80) ?: null, mb_substr($code, 0, 40),
            mb_substr(trim((string)($_POST['barcode'] ?? '')), 0, 80) ?: null, mb_substr(trim((string)($_POST['unit'] ?? 'шт')), 0, 20) ?: 'шт',
            $num($_POST['weight'] ?? 0), $num($_POST['volume'] ?? 0), $vat === '' ? null : $num($vat), $num($_POST['buy_price'] ?? 0), $num($_POST['sell_price'] ?? 0), $num($_POST['promo_price'] ?? 0), $num($_POST['min_stock'] ?? 0),
            mb_substr(trim((string)($_POST['description'] ?? '')), 0, 4000) ?: null];
        if ($id) { $db->q('UPDATE {p}wms_products SET name=?, group_id=?, sku=?, code=?, barcode=?, unit=?, weight=?, volume=?, vat=?, buy_price=?, sell_price=?, promo_price=?, min_stock=?, description=? WHERE id = ?', [...$v, $id]); }
        else { $db->q('INSERT INTO {p}wms_products (name, group_id, sku, code, barcode, unit, weight, volume, vat, buy_price, sell_price, promo_price, min_stock, description) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)', $v); $id = $db->insertId(); }
        $sf->log('product.saved', ['id' => $id, 'name' => $name]);
        $open = !$isEdit ? $num($_POST['opening_qty'] ?? 0) : 0.0;   // opening stock of a NEW product = a posted receipt, so it shows in documents and history
        if ($open > 0) {
            $wh = $db->row('SELECT id, use_cells FROM {p}wms_warehouses WHERE id = ? AND active = 1', [(int)($_POST['opening_wh'] ?? 0)]);
            if (!$wh || $wh['use_cells']) { flash($sf->t('err_opening'), 'bad'); redirect(wms_path("/products/$id")); }
            try {
                $d = $docs->save(['type' => 'receipt', 'warehouse_id' => (int)$wh['id'], 'moment' => date('Y-m-d H:i:s'), 'comment' => $sf->t('opening_comment')], [['product_id' => $id, 'qty' => $open, 'price' => $v[9]]]);
                $bad = $docs->post($d);
                if ($bad) { flash($sf->t('err_opening'), 'bad'); redirect(wms_path("/products/$id")); }
            } catch (\Throwable $e) { flash($sf->t('err_opening'), 'bad'); redirect(wms_path("/products/$id")); }
        }
        flash($sf->t('saved')); redirect(wms_path("/products/$id"));
    };
    $route('POST', '/products/new', fn() => $saveProduct(null));
    $route('POST', '/products/{id}', fn(array $a) => $saveProduct((int)$a['id']));

    /* ================= stock ================= */
    $route('GET', '/stock', function () use ($sf, $stock, $db, $page, $warehouses) {
        $f = ['q' => trim((string)($_GET['q'] ?? '')), 'warehouse_id' => (int)($_GET['wh'] ?? 0), 'only' => (string)($_GET['only'] ?? 'in'), 'group_id' => (int)($_GET['group'] ?? 0)];
        if (!empty($_GET['csv'])) {
            W::csv('stock-' . date('Ymd') . '.csv', [$sf->t('col_name'), $sf->t('col_sku'), $sf->t('col_unit'), $sf->t('col_on_hand'), $sf->t('col_reserve'), $sf->t('col_expected'), $sf->t('col_available'), $sf->t('col_buy_price'), $sf->t('col_value')],
                array_map(fn($r) => [$r['name'], $r['sku'], $r['unit'], $r['on_hand'], $r['reserve'], $r['expected'], $r['available'], $r['buy_price'], round($r['on_hand'] * $r['buy_price'], 2)], $stock->summary($f, 500)));
        }
        $per = 40; $total = $stock->summaryCount($f); $pg = W::page($total, $per, (int)($_GET['page'] ?? 1));
        $page('stock', ['rows' => $stock->summary($f, $per, ($pg['cur'] - 1) * $per), 'total' => $total, 'pg' => $pg, 'f' => $f, 'whs' => $warehouses(),
            'groups' => $db->rows('SELECT * FROM {p}wms_groups ORDER BY name')], $sf->t('nav_stock'), 'stock');
    });

    /* ================= warehouses ================= */
    $route('GET', '/warehouses', function () use ($sf, $db, $page) {
        $page('warehouses', ['rows' => $db->rows('SELECT w.*, (SELECT COUNT(*) FROM {p}wms_cells c WHERE c.warehouse_id = w.id AND c.active = 1) cells,
            (SELECT COALESCE(SUM(m.on_hand),0) FROM {p}wms_moves m WHERE m.warehouse_id = w.id) qty FROM {p}wms_warehouses w ORDER BY w.active DESC, w.is_default DESC, w.name')], $sf->t('nav_warehouses'), 'warehouses');
    });
    $route('POST', '/warehouses', function () use ($sf, $db) {
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') { flash($sf->t('err_name'), 'bad'); redirect(wms_path('/warehouses')); }
        $db->q('INSERT INTO {p}wms_warehouses (name, code, address, use_cells) VALUES (?,?,?,?)', [mb_substr($name, 0, 190), mb_substr(trim((string)($_POST['code'] ?? '')), 0, 40) ?: null, mb_substr(trim((string)($_POST['address'] ?? '')), 0, 255) ?: null, !empty($_POST['use_cells']) ? 1 : 0]);
        $id = $db->insertId(); flash($sf->t('warehouse_added')); redirect(wms_path("/warehouses/$id"));
    }, 'wms.admin');
    $route('GET', '/warehouses/{id}', function (array $a) use ($sf, $db, $stock, $page) {
        $w = $db->row('SELECT * FROM {p}wms_warehouses WHERE id = ?', [(int)$a['id']]);
        if (!$w) { http_response_code(404); echo e($sf->t('not_found')); return; }
        $cells = $db->rows('SELECT c.*, z.name AS zone, (SELECT COALESCE(SUM(m.on_hand),0) FROM {p}wms_moves m WHERE m.cell_id = c.id) AS qty FROM {p}wms_cells c LEFT JOIN {p}wms_zones z ON z.id = c.zone_id WHERE c.warehouse_id = ? AND c.active = 1 ORDER BY z.name, c.name', [$w['id']]);
        $cellId = (int)($_GET['cell'] ?? 0);
        $page('warehouse', ['w' => $w, 'zones' => $db->rows('SELECT * FROM {p}wms_zones WHERE warehouse_id = ? ORDER BY name', [$w['id']]), 'cells' => $cells,
            'inCell' => $cellId ? $stock->inCell($cellId) : [], 'cellId' => $cellId, 'qty' => (float)$db->val('SELECT COALESCE(SUM(on_hand),0) FROM {p}wms_moves WHERE warehouse_id = ?', [$w['id']])], $w['name'], 'warehouses');
    });
    $route('POST', '/warehouses/{id}', function (array $a) use ($sf, $db) {
        $id = (int)$a['id']; $do = (string)($_POST['do'] ?? '');
        $w = $db->row('SELECT * FROM {p}wms_warehouses WHERE id = ?', [$id]); if (!$w) redirect(wms_path('/warehouses'));
        if ($do === 'save') {
            $name = trim((string)($_POST['name'] ?? '')); if ($name === '') { flash($sf->t('err_name'), 'bad'); redirect(wms_path("/warehouses/$id")); }
            $db->q('UPDATE {p}wms_warehouses SET name=?, code=?, address=?, comment=?, use_cells=? WHERE id = ?', [mb_substr($name, 0, 190), mb_substr(trim((string)($_POST['code'] ?? '')), 0, 40) ?: null, mb_substr(trim((string)($_POST['address'] ?? '')), 0, 255) ?: null, mb_substr(trim((string)($_POST['comment'] ?? '')), 0, 2000) ?: null, !empty($_POST['use_cells']) ? 1 : 0, $id]);
            if (!empty($_POST['is_default'])) { $db->q('UPDATE {p}wms_warehouses SET is_default = 0'); $db->q('UPDATE {p}wms_warehouses SET is_default = 1 WHERE id = ?', [$id]); }
            flash($sf->t('saved'));
        } elseif ($do === 'archive') {
            $qty = (float)$db->val('SELECT COALESCE(SUM(on_hand),0) FROM {p}wms_moves WHERE warehouse_id = ?', [$id]);
            if ($qty != 0.0) flash($sf->t('err_wh_not_empty'), 'bad'); elseif ($w['is_default']) flash($sf->t('err_wh_default'), 'bad');
            else { $db->q('UPDATE {p}wms_warehouses SET active = 0 WHERE id = ?', [$id]); flash($sf->t('warehouse_archived')); redirect(wms_path('/warehouses')); }
        } elseif ($do === 'restore') { $db->q('UPDATE {p}wms_warehouses SET active = 1 WHERE id = ?', [$id]); flash($sf->t('saved'));
        } elseif ($do === 'zone' && trim((string)($_POST['name'] ?? '')) !== '') { $db->q('INSERT INTO {p}wms_zones (warehouse_id, name) VALUES (?,?)', [$id, mb_substr(trim((string)$_POST['name']), 0, 120)]); flash($sf->t('saved'));
        } elseif ($do === 'zone_delete') { $z = (int)($_POST['zone'] ?? 0); $db->q('UPDATE {p}wms_cells SET zone_id = NULL WHERE zone_id = ? AND warehouse_id = ?', [$z, $id]); $db->q('DELETE FROM {p}wms_zones WHERE id = ? AND warehouse_id = ?', [$z, $id]); flash($sf->t('saved'));
        } elseif ($do === 'cell' && trim((string)($_POST['name'] ?? '')) !== '') {
            $names = array_filter(array_map('trim', preg_split('/[\r\n,;]+/', (string)$_POST['name']) ?: []));   // several cells at once: "A-01, A-02, A-03"
            foreach (array_slice($names, 0, 200) as $n) $db->q('INSERT INTO {p}wms_cells (warehouse_id, zone_id, name, barcode) VALUES (?,?,?,?)', [$id, ((int)($_POST['zone'] ?? 0)) ?: null, mb_substr($n, 0, 120), count($names) === 1 ? (mb_substr(trim((string)($_POST['barcode'] ?? '')), 0, 80) ?: null) : null]);
            flash($sf->t('cells_added', ['n' => count($names)]));
        } elseif ($do === 'cell_delete') {
            $c = (int)($_POST['cell'] ?? 0);
            if ((float)$db->val('SELECT COALESCE(SUM(on_hand),0) FROM {p}wms_moves WHERE cell_id = ?', [$c]) != 0.0) flash($sf->t('err_cell_not_empty'), 'bad');
            else { $db->q('UPDATE {p}wms_cells SET active = 0 WHERE id = ? AND warehouse_id = ?', [$c, $id]); flash($sf->t('saved')); }
        }
        redirect(wms_path("/warehouses/$id"));
    }, 'wms.admin');

    /* ================= documents ================= */
    $route('GET', '/docs', function () use ($sf, $docs, $page) {
        $f = ['group' => (string)($_GET['group'] ?? ''), 'type' => (string)($_GET['type'] ?? ''), 'status' => (string)($_GET['status'] ?? ''), 'q' => trim((string)($_GET['q'] ?? ''))];
        $per = 30; $total = $docs->count($f); $pg = W::page($total, $per, (int)($_GET['page'] ?? 1));
        $rows = $docs->list($f, $per, ($pg['cur'] - 1) * $per);
        foreach ($rows as &$r) if (in_array($r['type'], ['purchase', 'sale'], true)) $r['progress'] = $docs->progress((int)$r['id']);
        unset($r);
        $page('docs', ['rows' => $rows, 'total' => $total, 'pg' => $pg, 'f' => $f], $sf->t('nav_docs'), 'docs');
    });

    $editor = function (?array $doc, string $type) use ($sf, $docs, $db, $warehouses, $partners, $page): void {
        $cells = $db->rows('SELECT c.id, c.warehouse_id, c.name, z.name AS zone FROM {p}wms_cells c LEFT JOIN {p}wms_zones z ON z.id = c.zone_id WHERE c.active = 1 ORDER BY z.name, c.name');
        $parent = $doc && $doc['parent_id'] ? $docs->find((int)$doc['parent_id']) : null;
        $page('doc', ['doc' => $doc, 'type' => $type, 'lines' => $doc ? $docs->lines((int)$doc['id']) : [], 'whs' => $warehouses(), 'cells' => $cells,
            'partners' => $partners(), 'parent' => $parent, 'progress' => $doc && in_array($type, ['purchase', 'sale'], true) ? $docs->progress((int)$doc['id']) : null,
            'children' => $doc ? $db->rows('SELECT id, type, number, status, total FROM {p}wms_docs WHERE parent_id = ? ORDER BY id', [(int)$doc['id']]) : [],
            'defaultWh' => (int)($db->val('SELECT id FROM {p}wms_warehouses WHERE is_default = 1 AND active = 1 LIMIT 1') ?: 0)],
            $doc ? $doc['number'] : $sf->t('new_doc') . ': ' . W::type($sf, $type), 'docs');
    };
    $route('GET', '/docs/new/{type}', function (array $a) use ($sf, $editor) {
        if (!Posting::known($a['type'])) { http_response_code(404); echo e($sf->t('not_found')); return; }
        $editor(null, $a['type']);
    });
    $route('GET', '/docs/{id}', function (array $a) use ($sf, $docs, $editor) {
        $d = $docs->find((int)$a['id']);
        if (!$d) { http_response_code(404); echo e($sf->t('not_found')); return; }
        $editor($d, $d['type']);
    });
    $route('POST', '/docs/save', function () use ($sf, $docs, $uid) {
        $id = (int)($_POST['id'] ?? 0) ?: null; $type = (string)($_POST['type'] ?? '');
        $lines = array_values((array)($_POST['lines'] ?? []));
        try {
            $new = $id === null;
            $id = $docs->save(['type' => $type, 'moment' => $_POST['moment'] ?? '', 'warehouse_id' => $_POST['warehouse_id'] ?? 0, 'to_warehouse_id' => $_POST['to_warehouse_id'] ?? 0,
                'contact_id' => $_POST['contact_id'] ?? 0, 'parent_id' => $_POST['parent_id'] ?? 0, 'comment' => $_POST['comment'] ?? ''], $lines, $id, $uid());
            $d = $docs->find($id);
            if (($_POST['do'] ?? '') === 'post') {
                $problems = $docs->post($id);
                if ($problems) { foreach ($problems as $pr) flash(wms_msg($sf, $pr), 'bad'); }
                else { $sf->log('doc.posted', ['id' => $id, 'number' => $d['number'], 'type' => $type]); flash($sf->t('posted_ok', ['n' => $d['number']])); }
            } else flash($sf->t('saved'));
        } catch (\Throwable $e) { flash($sf->t($e->getMessage() === 'not_draft' ? 'err_state' : 'err_generic'), 'bad'); \Sysfera\App::log('wms save: ' . $e->getMessage()); if (!$id) redirect(wms_path('/docs')); }
        redirect(wms_path("/docs/$id"));
    });
    $route('POST', '/docs/act', function () use ($sf, $docs, $uid) {
        $id = (int)($_POST['id'] ?? 0); $do = (string)($_POST['do'] ?? ''); $d = $docs->find($id);
        if (!$d) redirect(wms_path('/docs'));
        try {
            if ($do === 'post') { $pr = $docs->post($id); if ($pr) foreach ($pr as $x) flash(wms_msg($sf, $x), 'bad'); else { $sf->log('doc.posted', ['id' => $id, 'number' => $d['number'], 'type' => $d['type']]); flash($sf->t('posted_ok', ['n' => $d['number']])); } }
            elseif ($do === 'unpost' || $do === 'cancel') { $pr = $docs->unpost($id, $do === 'cancel'); if ($pr) foreach ($pr as $x) flash(wms_msg($sf, $x), 'bad'); else { $sf->log('doc.' . $do, ['id' => $id, 'number' => $d['number']]); flash($sf->t($do === 'cancel' ? 'cancelled_ok' : 'unposted_ok')); } }
            elseif ($do === 'delete') { if ($docs->delete($id)) { flash($sf->t('deleted')); redirect(wms_path('/docs')); } else flash($sf->t('err_state'), 'bad'); }
            elseif ($do === 'child') { $cid = $docs->child($id, $uid()); flash($sf->t('child_created')); redirect(wms_path("/docs/$cid")); }
            elseif ($do === 'copy') {
                $cid = $docs->save(['type' => $d['type'], 'moment' => date('Y-m-d H:i:s'), 'warehouse_id' => $d['warehouse_id'], 'to_warehouse_id' => $d['to_warehouse_id'], 'contact_id' => $d['contact_id'], 'comment' => $d['comment']], $docs->lines($id), null, $uid());
                flash($sf->t('copied')); redirect(wms_path("/docs/$cid"));
            }
        } catch (\Throwable $e) { flash($sf->t('err_generic'), 'bad'); \Sysfera\App::log('wms act: ' . $e->getMessage()); }
        redirect(wms_path("/docs/$id"));
    });

    /* ================= partners (shared contacts) ================= */
    $route('GET', '/partners', function () use ($sf, $db, $page, $partners) {
        $role = (string)($_GET['role'] ?? ''); $q = trim((string)($_GET['q'] ?? ''));
        $rows = array_values(array_filter($partners(in_array($role, ['supplier', 'customer'], true) ? $role : null), fn($r) => $q === '' || mb_stripos($r['name'] . ' ' . $r['company'] . ' ' . $r['phone'] . ' ' . $r['email'], $q) !== false));
        $counts = [];
        foreach ($db->rows("SELECT contact_id, type, COUNT(*) n, SUM(CASE WHEN status='posted' THEN total ELSE 0 END) s FROM {p}wms_docs WHERE contact_id IS NOT NULL AND type IN ('purchase','sale') GROUP BY contact_id, type") as $c) $counts[$c['contact_id']][$c['type']] = $c;
        $page('partners', ['rows' => $rows, 'role' => $role, 'q' => $q, 'counts' => $counts], $sf->t('nav_partners'), 'partners');
    });
    $route('POST', '/partners', function () use ($sf, $db, $uid) {
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') { flash($sf->t('err_name'), 'bad'); redirect(wms_path('/partners')); }
        $roles = array_values(array_intersect((array)($_POST['roles'] ?? []), ['supplier', 'customer']));
        $id = $sf->contacts()->create(['owner_id' => $uid(), 'type' => ($_POST['kind'] ?? '') === 'company' ? 'company' : 'person', 'name' => mb_substr($name, 0, 190), 'email' => trim((string)($_POST['email'] ?? '')) ?: null,
            'phone' => trim((string)($_POST['phone'] ?? '')) ?: null, 'company' => trim((string)($_POST['company'] ?? '')) ?: null, 'address' => trim((string)($_POST['address'] ?? '')) ?: null, 'source' => 'wms', 'custom' => ['roles' => $roles]]);
        flash($sf->t('partner_added')); redirect(wms_path(str_starts_with((string)($_POST['back'] ?? ''), '/docs/') ? (string)$_POST['back'] : '/partners'));
    });
    $route('POST', '/partners/roles', function () use ($sf, $db) {
        $id = (int)($_POST['id'] ?? 0); $c = $sf->contacts()->find($id);
        if ($c) { $custom = (array)($c['custom'] ?? []); $custom['roles'] = array_values(array_intersect((array)($_POST['roles'] ?? []), ['supplier', 'customer'])); $sf->contacts()->update($id, ['custom' => $custom]); flash($sf->t('saved')); }
        redirect(wms_path('/partners'));
    });

    /* ================= reports ================= */
    $route('GET', '/reports', function () use ($sf, $stock, $page, $warehouses) {
        $from = (string)($_GET['from'] ?? date('Y-m-01')); $to = (string)($_GET['to'] ?? date('Y-m-d'));
        $wh = (int)($_GET['wh'] ?? 0) ?: null;
        $rows = $stock->turnover($from . ' 00:00:00', $to . ' 23:59:59', $wh);
        if (!empty($_GET['csv'])) W::csv("turnover-$from-$to.csv", [$sf->t('col_name'), $sf->t('col_sku'), $sf->t('col_unit'), $sf->t('col_opening'), $sf->t('col_income'), $sf->t('col_outcome'), $sf->t('col_closing')],
            array_map(fn($r) => [$r['name'], $r['sku'], $r['unit'], $r['opening'], $r['income'], $r['outcome'], $r['opening'] + $r['income'] - $r['outcome']], $rows));
        $page('reports', ['rows' => $rows, 'from' => $from, 'to' => $to, 'wh' => $wh, 'whs' => $warehouses()], $sf->t('nav_reports'), 'reports');
    });

    /* ================= audit ================= */
    $route('GET', '/audit', function () use ($sf, $db, $page) {
        $per = 40; $total = (int)$db->val("SELECT COUNT(*) FROM {p}events WHERE plugin = 'wms'"); $pg = W::page($total, $per, (int)($_GET['page'] ?? 1));
        $rows = $db->rows("SELECT e.*, u.name AS user FROM {p}events e LEFT JOIN {p}users u ON u.id = e.user_id WHERE e.plugin = 'wms' ORDER BY e.id DESC LIMIT $per OFFSET " . (($pg['cur'] - 1) * $per));
        $page('audit', ['rows' => $rows, 'pg' => $pg, 'total' => $total], $sf->t('nav_audit'), 'audit');
    });

    /* ================= settings ================= */
    $route('GET', '/settings', fn() => $page('settings', ['forbid' => $sf->setting('forbid_negative', '1') === '1'], $sf->t('nav_settings'), 'settings'), 'wms.admin');
    $route('POST', '/settings', function () use ($sf) {
        $sf->setSetting('forbid_negative', !empty($_POST['forbid_negative']) ? '1' : '0');
        flash($sf->t('saved')); redirect(wms_path('/settings'));
    }, 'wms.admin');

    /* ================= JSON for the editor ================= */
    $route('GET', '/api/products', function () use ($db, $stock) {
        $q = trim((string)($_GET['q'] ?? '')); $wh = (int)($_GET['wh'] ?? 0);
        header('Content-Type: application/json; charset=utf-8');
        if ($q === '') { echo '[]'; return; }
        $like = '%' . $q . '%';
        $rows = $db->rows('SELECT id, name, sku, code, barcode, unit, buy_price, sell_price FROM {p}wms_products WHERE archived = 0 AND (name LIKE ? OR sku LIKE ? OR code LIKE ? OR barcode = ?) ORDER BY (barcode = ?) DESC, name LIMIT 12', [$like, $like, $like, $q, $q]);
        foreach ($rows as &$r) { $r['available'] = $wh ? $stock->available((int)$r['id'], $wh) : null; $r['on_hand'] = $wh ? $stock->onHand((int)$r['id'], $wh) : null; }
        echo json_encode($rows, JSON_UNESCAPED_UNICODE);
    });
    $route('GET', '/api/stock', function () use ($stock) {   // current stock of a warehouse, used by "load stock" in an inventory
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array_map(fn($r) => ['id' => (int)$r['id'], 'name' => $r['name'], 'sku' => $r['sku'], 'unit' => $r['unit'], 'barcode' => $r['barcode'], 'on_hand' => (float)$r['on_hand'], 'price' => (float)$r['buy_price']],
            $stock->summary(['warehouse_id' => (int)($_GET['wh'] ?? 0), 'only' => 'in'], 500)), JSON_UNESCAPED_UNICODE);
    });

    /* message for a posting problem: plain keys or "stock:<name>|<have>|<need>" */
    if (!function_exists('wms_path')) {
        /** site-relative path of a WMS page (for redirect()) */
        function wms_path(string $p): string { return '/p/wms/' . ltrim($p, '/'); }
    }
    if (!function_exists('wms_msg')) {
        function wms_msg(\Sysfera\Plugin $sf, string $k): string
        {
            if (str_starts_with($k, 'stock:')) { [$n, $have, $need] = array_pad(explode('|', substr($k, 6)), 3, ''); return $sf->t('err_stock', ['name' => $n, 'have' => $have, 'need' => $need]); }
            return $sf->t($k);
        }
    }

    /* demo mode: wipe the warehouse and load a small believable example */
    $sf->on('demo.reset', function () use ($sf, $db, $docs) {
        foreach (['wms_moves', 'wms_doc_lines', 'wms_docs', 'wms_products', 'wms_groups', 'wms_cells', 'wms_zones', 'wms_warehouses'] as $t) $db->q("DELETE FROM {p}$t");
        $db->q("DELETE FROM {p}settings WHERE k LIKE 'plugin.wms.%'");
        $ru = \Sysfera\I18n::lang() === 'ru';
        $db->q('INSERT INTO {p}wms_warehouses (name, code, is_default, address) VALUES (?,?,1,?)', [$sf->t('main_warehouse'), 'MAIN', $ru ? 'Москва, ул. Складская, 1' : 'Warehouse St. 1']);
        $wh = $db->insertId();
        $db->q('INSERT INTO {p}wms_groups (name) VALUES (?)', [$ru ? 'Кухня' : 'Kitchen']); $grp = $db->insertId();
        $items = [   // name, sku, buy, sell, min, qty received
            [$ru ? 'Кружка керамическая 350 мл' : 'Ceramic mug 350 ml', 'MUG-350', 180, 450, 10, 40],
            [$ru ? 'Тарелка десертная' : 'Dessert plate', 'PLT-D20', 120, 320, 10, 60],
            [$ru ? 'Чайник стальной 1,5 л' : 'Steel kettle 1.5 l', 'KTL-15', 900, 2100, 5, 4],
            [$ru ? 'Набор ножей, 5 предметов' : 'Knife set, 5 pcs', 'KNF-5', 1500, 3900, 3, 12],
            [$ru ? 'Доска разделочная' : 'Cutting board', 'BRD-01', 250, 690, 8, 0],
        ];
        $ids = []; $lines = [];
        foreach ($items as $n => [$name, $sku, $buy, $sell, $min, $qty]) {
            $db->q('INSERT INTO {p}wms_products (group_id, name, sku, code, unit, buy_price, sell_price, min_stock, source) VALUES (?,?,?,?,?,?,?,?,?)', [$grp, $name, $sku, str_pad((string)($n + 1), 5, '0', STR_PAD_LEFT), $ru ? 'шт' : 'pcs', $buy, $sell, $min, 'demo']);
            $ids[] = $db->insertId();
            if ($qty > 0) $lines[] = ['product_id' => $ids[$n], 'qty' => $qty, 'price' => $buy];
        }
        $db->q("INSERT INTO {p}settings (k, v) VALUES ('plugin.wms.counter.product', '5') ON DUPLICATE KEY UPDATE v = '5'");
        $supplier = $sf->contacts()->create(['type' => 'company', 'name' => $ru ? 'ООО «Керамика»' : 'Ceramics Ltd', 'source' => 'demo', 'custom' => ['roles' => ['supplier']]]);
        $buyer = $sf->contacts()->create(['type' => 'person', 'name' => $ru ? 'Иван Петров' : 'John Smith', 'phone' => '+7 900 000-00-00', 'source' => 'demo', 'custom' => ['roles' => ['customer']]]);
        $r = $docs->save(['type' => 'receipt', 'warehouse_id' => $wh, 'contact_id' => $supplier, 'moment' => date('Y-m-d H:i:s', time() - 86400), 'comment' => $ru ? 'Первоначальный приход' : 'Initial receipt'], $lines);
        $docs->post($r);
        $so = $docs->save(['type' => 'sale', 'warehouse_id' => $wh, 'contact_id' => $buyer, 'moment' => date('Y-m-d H:i:s')], [['product_id' => $ids[2], 'qty' => 2, 'price' => 2100], ['product_id' => $ids[0], 'qty' => 6, 'price' => 450]]);
        $docs->post($so);
    });

    /* services other components (e.g. wms-basebase) may use; they get them through Hooks::filter('wms.services', []) */
    $sf->on('wms.services', fn(array $s) => $s + ['docs' => $docs, 'stock' => $stock, 'db' => $db]);

    $sf->on('dashboard.widgets', function (array $w) use ($sf, $stock) {
        if (!$sf->can('wms.use')) return $w;
        $t = $stock->totals();
        $w[] = '<b>' . e($sf->t('title')) . '</b>: ' . e(W::qty($t['qty'])) . ' · ' . e(W::money($t['value'])) . ($t['low'] ? ' · <span class="bad-t">' . e($sf->t('low_n', ['n' => $t['low']])) . '</span>' : '') . ' — <a href="' . e($sf->url('/')) . '">' . e($sf->t('open')) . '</a>';
        return $w;
    });
};
