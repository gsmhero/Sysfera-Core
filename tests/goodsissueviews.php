<?php
declare(strict_types=1);
/** Renders every Goods issue page with real sample data (engine + in-memory store); a warning or a missing translation fails. Also: en/ru have the same keys, every key used exists. Run: php tests/goodsissueviews.php */
foreach (['mb_substr', 'mb_strlen'] as $f) if (!function_exists($f)) eval("function $f(string \$s, int \$a = 0, ?int \$l = null) { return " . ($f === 'mb_strlen' ? 'strlen($s)' : '$l === null ? substr($s, $a) : substr($s, $a, $l)') . '; }');
if (!function_exists('e')) { function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); } }
if (!function_exists('csrf_field')) { function csrf_field(): string { return '<input type="hidden" name="_t" value="x">'; } }
$dir = dirname(__DIR__) . '/core/plugins/goodsissue';
foreach (['Err', 'Units', 'Machine', 'Sig', 'Code', 'Audit', 'Manifest', 'Store', 'Seed', 'Extras', 'Kpi', 'Label', 'Exchange'] as $c) require "$dir/lib/$c.php";
require __DIR__ . '/GiMemStore.php';
use SysferaGi\{Exchange, Seed, Audit};
$fail = 0; function ok(bool $c, string $n): void { global $fail; echo ($c ? '  ok   ' : '  FAIL ') . $n . "\n"; if (!$c) $fail++; }
set_error_handler(function (int $no, string $msg, string $file, int $line): bool { throw new ErrorException("$msg in " . basename($file) . ":$line"); });
$en = require "$dir/lang/en.php"; $ru = require "$dir/lang/ru.php";

final class FakeSf {
    public array $manifest = ['version' => '0.1.0'];
    public function t(string $k, array $v = []): string { global $en; $s = $en[$k] ?? throw new RuntimeException("missing key $k"); foreach ($v as $a => $b) $s = str_replace('{' . $a . '}', (string)$b, $s); return $s; }
    public function url(string $p = ''): string { return '/p/goodsissue/' . ltrim($p, '/'); }
    public function asset(string $f): string { return '/assets/p/goodsissue/' . $f; }
}
$sf = new FakeSf(); $now = strtotime('2026-11-17 10:00:00'); $store = new GiMemStore(); $codes = [];
$ex = new Exchange($store, ['secret' => 'x', 'code_ttl' => 3600, 'id_threshold_kop' => 3000000, 'overdue_days' => 3], function ($o, $c) use (&$codes) { $codes[$o['uuid']] = $c; }, fn() => $now);
$off = ['kind' => 'office', 'name' => 'head', 'warehouse' => null, 'device' => null]; $op = ['kind' => 'operator', 'name' => 'op', 'warehouse' => 1, 'device' => 'dev-1'];
[$t1] = Seed::transfers(1); $ex->createTransfer($t1, $off); $v = $ex->deliver(Seed::T1, $op);
$ex->receipt(Seed::T1, ['doc_uuid' => SysferaGi\Manifest::newUuid(), 'checksum' => $v['checksum'], 'scans' => [['barcode' => 'GI4600000001', 'qty' => 1], ['barcode' => 'GI4600000002', 'qty' => 3], ['barcode' => 'GI4600000003', 'qty' => 1], ['barcode' => 'GI4600000004', 'qty' => 1], ['barcode' => 'ZZZ-UNKNOWN', 'qty' => 1]]], $op);
$ex->count(['doc_uuid' => SysferaGi\Manifest::newUuid(), 'lines' => [['barcode' => 'GI4600000001', 'qty' => 1]]], $op);
$ex->issue(Seed::O1, ['doc_uuid' => SysferaGi\Manifest::newUuid(), 'code' => $codes[Seed::O1], 'signature' => str_repeat('x', 40), 'handover' => 'courier', 'courier' => ['service' => 'boxberry', 'name' => 'Ivan C', 'waybill' => 'BB123456']], $op);
$ex->returnAction(Seed::O1, ['doc_uuid' => SysferaGi\Manifest::newUuid(), 'kind' => 'request', 'reason' => 'does not fit', 'condition' => 'opened'], $op);
$stk = $ex->stocktakeStart(1, $off); $ex->stocktakeSubmit($stk['uuid'], ['doc_uuid' => SysferaGi\Manifest::newUuid(), 'lines' => [['barcode' => 'GI4600000004', 'qty' => 5]]], $op);
$rep = $ex->reconcile(1, date('Y-m-d', $now));
$kpi = SysferaGi\Kpi::compute([1 => 'Pickup 1'], array_values($store->orders), $store->audit, [Seed::T1 => 1, Seed::T2 => 1], $now, 30);
$labels = array_map(fn($t) => ['text' => $t, 'title' => $t, 'sub' => 'x', 'svg' => SysferaGi\Label::svg($t, 56, 2)], ['A-1001', 'SH:A12']);
$cfgV = ['id_threshold_kop' => 3000000, 'photo_threshold_kop' => 0, 'code_ttl' => 172800, 'overdue_days' => 7, 'refund_days' => 0];
$wh = ['id' => 1, 'code' => 'PVZ', 'name' => 'Pickup 1', 'active' => 1];
$t = $store->transfer(Seed::T1); $o = $store->order(Seed::O3); $o['customer_email'] = '';
$inc = array_map(fn($i) => $i + ['wname' => 'Pickup 1', 'closed_by' => '', 'closed_at' => '', 'note' => ''], array_values($store->incidents));
$render = function (string $view, array $vars) use ($sf, $dir): string { extract($vars + ['sf' => $sf]); ob_start(); include "$dir/views/$view.php"; return (string)ob_get_clean(); };
$pages = [
    'dashboard' => ['tab' => 'dashboard', 'pend' => ['returns' => 1, 'adjust' => 2, 'refunds' => 1], 'counts' => ['ready_for_issue' => 2], 'incidents' => $inc, 'transfers' => [$t + ['wname' => 'Pickup 1', 'orders' => 3]], 'whs' => [$wh], 'appUrl' => '/api/goodsissue/app', 'ops' => 1],
    'transfers' => ['tab' => 'transfers', 'rows' => [$t + ['wname' => 'Pickup 1', 'orders' => 3]], 'whs' => [$wh]],
    'transfer' => ['tab' => 'transfers', 't' => $t, 'wh' => $wh, 'orders' => $store->ordersOfTransfer(Seed::T1), 'items' => $store->items(Seed::T1), 'log' => $store->audit],
    'orders' => ['tab' => 'orders', 'rows' => [$o + ['wname' => 'Pickup 1']], 'st' => 'blocked', 'q' => 'x'],
    'order' => ['tab' => 'orders', 'act' => ['uuid' => SysferaGi\Manifest::newUuid(), 'payload' => json_encode(['evidence_sha256' => 'a', 'photo_sha256' => 'b'])], 'returns' => array_values($store->records('return')), 'o' => $o + ['shelf' => 'A12', 'handover' => 'courier', 'courier' => json_encode(['service' => 'boxberry', 'name' => 'Ivan', 'waybill' => 'BB1'])], 'items' => $store->orderItems(Seed::O3), 'code' => null, 'wh' => $wh, 'incidents' => $inc, 'log' => $store->audit],
    'incidents' => ['tab' => 'incidents', 'rows' => $inc, 'st' => 'open'],
    'reports' => ['tab' => 'reports', 'rows' => [['warehouse_id' => 1, 'day' => '2026-11-17', 'wname' => 'Pickup 1']], 'whs' => [$wh]],
    'report' => ['tab' => 'reports', 'r' => $rep, 'wh' => $wh],
    'warehouses' => ['tab' => 'warehouses', 'cfg' => $cfgV, 'wcfg' => [1 => ['id_threshold_kop' => 100000, 'code_ttl' => 7200]], 'whs' => [$wh], 'staff' => [['user_id' => 3, 'name' => 'Op', 'email' => 'o@x.y', 'role' => 'operator', 'wname' => 'Pickup 1', 'warehouse_id' => 1, 'perms' => 'receive,issue'], ['user_id' => 4, 'name' => 'Viewer', 'email' => 'v@x.y', 'role' => 'viewer', 'wname' => null, 'warehouse_id' => null, 'perms' => '']], 'users' => [['id' => 3, 'name' => 'Op', 'email' => 'o@x.y']],
        'devices' => [['id' => 1, 'name' => 'Op', 'label' => 'Phone', 'device_id' => 'abcdefghijklmnop', 'last_seen' => '', 'status' => 'pending']]],
    'audit' => ['tab' => 'audit', 'rows' => $store->audit, 'e' => '', 'a' => '', 'verify' => ['bad' => null, 'n' => 5]],
    'settings' => ['tab' => 'settings', 'rules' => [['ref' => 'SKU-TV', 'data' => ['max_days' => 5, 'refund_days' => 10]]], 'officeKey' => 'k', 'base' => '/api/goodsissue/v1/', 'appUrl' => '/api/goodsissue/app', 'https' => false, 'v' => ['id_threshold' => '30000.00', 'code_ttl_h' => '48', 'overdue_days' => '7', 'photo_threshold' => '0', 'refund_days' => '0', 'refund_auto' => '1', 'offline_hours' => '24', 'stuck_hours' => '48', 'couriers' => 'boxberry,other']],
    'returns' => ['tab' => 'returns', 'reg' => $ex->returnRegistry('2026-11-01', '2026-11-30'), 'week' => '2026-11-16', 'wid' => 0, 'whs' => [$wh], 'names' => [1 => 'Pickup 1'], 'pending' => $store->records('return', 0, 'pending'), 'approved' => $store->records('return', 0, 'pending'),
        'weekly' => [['ref' => '1:2026-11-09', 'warehouse_id' => 1, 'created_at' => 'x']]],
    'stocktake' => ['tab' => 'stocktake', 'whs' => [$wh], 'names' => [1 => 'Pickup 1'], 'rows' => $store->records('stocktake'), 'proposed' => 1],
    'stocktake_one' => ['tab' => 'stocktake', 'act' => $ex->adjustmentAct($stk['uuid']), 'names' => [1 => 'Pickup 1']],
    'kpi' => ['tab' => 'kpi', 'kp' => $kpi],
    'labels' => ['tab' => 'labels', 'kind' => 'orders', 'labels' => $labels, 'err' => '', 'transfers' => [['uuid' => Seed::T1, 'created_at' => 'x', 'wname' => 'Pickup 1']], 'tu' => Seed::T1, 'q' => []],
];
foreach ($pages as $v => $vars) { try { $h = $render($v, $vars + ['manage' => true, 'cfg' => $cfgV]); ok(strlen($h) > 80, "renders: $v"); } catch (Throwable $e) { ok(false, "renders: $v — " . $e->getMessage()); } }
$o2 = $o; $o2['status'] = 'ready_for_issue'; $o2['issued_at'] = null;
try { ok(strlen($render('order', $pages['order'] + [] + ['o' => $o2, 'code' => ['expires_at' => $now + 5, 'attempts' => 2, 'used_at' => null]])) > 80, 'renders: order ready with a code'); } catch (Throwable $e) { ok(false, 'order ready: ' . $e->getMessage()); }
try { $x = ['tab' => 'audit', 'rows' => [], 'e' => '', 'a' => '', 'verify' => ['bad' => 7, 'n' => 9]]; ok(str_contains($render('audit', $x), '7'), 'renders: broken chain message'); } catch (Throwable $e) { ok(false, 'audit broken: ' . $e->getMessage()); }

/* translations: same keys in en and ru; every key the code uses exists */
ok(array_diff(array_keys($en), array_keys($ru)) === [] && array_diff(array_keys($ru), array_keys($en)) === [], 'en and ru have the same keys: ' . implode(',', array_merge(array_diff(array_keys($en), array_keys($ru)), array_diff(array_keys($ru), array_keys($en)))));
$used = [];
foreach (array_merge(glob("$dir/views/*.php"), ["$dir/plugin.php"]) as $f) { preg_match_all('/->t\(\s*\'([a-z_0-9]+)\'/', (string)file_get_contents($f), $m); $used = array_merge($used, $m[1]); }
preg_match_all('/\bT\(\s*\'(a_[a-z_0-9]+)\'/', (string)file_get_contents("$dir/assets/app.js"), $m); $js = array_unique($m[1]);
$dyn = [];
foreach (['created', 'transferred', 'received', 'ready_for_issue', 'issued', 'return_requested', 'returned', 'blocked'] as $s) { $dyn[] = 'st_' . $s; $dyn[] = 'a_st_' . $s; }
foreach (['created', 'transferred', 'confirmed', 'disputed'] as $s) $dyn[] = 'tst_' . $s;
foreach (['expected', 'ok', 'missing', 'short', 'extra', 'damaged', 'weight', 'accepted'] as $s) $dyn[] = 'is_' . $s;
foreach (['receipt_discrepancy', 'unknown_package', 'code_locked', 'return_request', 'return_damaged', 'stock_diff', 'no_count', 'overdue'] as $s) $dyn[] = 'inc_' . $s;
foreach (['pending', 'approved', 'revoked'] as $s) $dyn[] = 'dev_' . $s;
foreach (['operator', 'office'] as $s) $dyn[] = 'role_' . $s;
$dyn[] = 'a_damaged'; $dyn[] = 'a_wrong_weight';
foreach (['pending', 'approved', 'rejected', 'refunded'] as $s) $dyn[] = 'rs_' . $s;
foreach (['customer', 'unclaimed'] as $s) $dyn[] = 'src_' . $s;
foreach (['ok', 'opened', 'damaged'] as $s) $dyn[] = 'cond_' . $s;
foreach (['open', 'submitted', 'closed'] as $s) $dyn[] = 'sts_' . $s;
foreach (['proposed', 'approved', 'rejected'] as $s) $dyn[] = 'adjs_' . $s;
foreach (['repeated_code', 'code_reissued', 'manual_override', 'refused_burst', 'clock_ahead'] as $s) $dyn[] = 'sus_' . $s;
foreach (['receive', 'issue', 'return', 'count', 'shelf'] as $s) $dyn[] = 'perm_' . $s;
foreach (['orders', 'items', 'shelves'] as $s) $dyn[] = 'lbl_k_' . $s;
foreach (['offline', 'stuck_shipment', 'refund_due', 'stocktake_diff'] as $s) $dyn[] = 'inc_' . $s;
$dyn[] = 'role_viewer';
foreach (['forbidden', 'photo_required', 'bad_photo', 'bad_courier', 'bad_shelf', 'reason_required', 'bad_return_item'] as $s) $dyn[] = 'a_err_' . $s;
foreach (['code_wrong', 'code_expired', 'code_used', 'code_locked', 'code_missing', 'id_check_required', 'order_blocked', 'not_ready', 'already_issued', 'evidence_required', 'device_pending', 'device_revoked', 'invalid_credentials', 'online_only', 'too_many_attempts', 'unauthorized', 'stale_request', 'bad_signature', 'bad_server_signature', 'manifest_changed', 'not_delivered', 'network', 'forbidden_transition'] as $s) $dyn[] = 'a_err_' . $s;
$missing = array_values(array_filter(array_unique(array_merge($used, $js, $dyn)), fn($k) => !str_ends_with($k, '_') && !isset($en[$k])));
ok($missing === [], 'every translation key used exists in the language files' . ($missing ? ': ' . implode(',', $missing) : ''));
/* every error code the engine can throw has an app text */
preg_match_all("/new Err\('([a-z_]+)'/", (string)file_get_contents("$dir/lib/Exchange.php"), $m);
$noText = array_values(array_filter(array_unique($m[1]), fn($c) => !isset($en['a_err_' . $c])));
echo '  note: engine error codes without a dedicated app text (the server message is shown): ' . implode(', ', $noText) . "\n";
ok(is_file("$dir/openapi.yaml") && str_contains((string)file_get_contents("$dir/openapi.yaml"), '/orders/{uuid}/issue'), 'openapi.yaml is shipped');
echo $fail ? "\n$fail FAILED\n" : "\nAll good\n"; exit($fail ? 1 : 0);
