<?php
declare(strict_types=1);
/**
 * Start from zero: removes every user except administrators and all their business data
 * (orders, products, warehouses of users, stock, documents, Basebase shops). Settings, installed components and administrators stay.
 *
 *   php core/tools/wipe-data.php          shows what would be deleted (nothing changes)
 *   php core/tools/wipe-data.php --yes    really deletes. MAKE A DATABASE BACKUP FIRST: this cannot be undone.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
use Sysfera\{App, Db};

$go = in_array('--yes', $argv, true);
$cfg = require App::configFile();
$db = new Db($cfg['db']);
$has = fn(string $t): bool => (bool)$db->val("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?", [$db->sql('{p}' . $t)]);
$admins = array_map('intval', array_column($db->rows("SELECT id FROM {p}users WHERE role = 'admin'"), 'id'));
if (!$admins) { fwrite(STDERR, "No administrator found: refusing to continue.\n"); exit(1); }
$in = implode(',', $admins);

$jobs = [   // table => where (null = everything)
    'orders' => null, 'order_lines' => null, 'invoices' => null, 'invoice_lines' => null,
    'wmsbb_items' => null, 'wmsbb_products' => null, 'wmsbb_events' => null, 'wmsbb_sellers' => null,
    'wms_moves' => null, 'wms_doc_lines' => null, 'wms_docs' => null, 'wms_product_links' => null, 'wms_products' => null, 'wms_groups' => null,
    'wms_cells' => "warehouse_id IN (SELECT id FROM {p}wms_warehouses WHERE owner_id IS NULL OR owner_id NOT IN ($in))",
    'wms_zones' => "warehouse_id IN (SELECT id FROM {p}wms_warehouses WHERE owner_id IS NULL OR owner_id NOT IN ($in))",
    'wms_warehouses' => "owner_id IS NULL OR owner_id NOT IN ($in)",
    'contacts' => "source IN ('basebase','demo')",
    'msg_users' => "user_id NOT IN ($in)", 'teams_members' => "user_id NOT IN ($in)",
    'users' => "role <> 'admin'",
];
echo $go ? "DELETING:\n" : "Dry run (add --yes to delete). Administrators kept: " . implode(', ', $admins) . "\n";
$db->pdo()->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach ($jobs as $t => $w) {
    if (!$has($t)) continue;
    try {
        $n = (int)$db->val("SELECT COUNT(*) FROM {p}$t" . ($w ? " WHERE $w" : ''));
        if ($go && $n) $db->q("DELETE FROM {p}$t" . ($w ? " WHERE $w" : ''));
        printf("  %-18s %d\n", $t, $n);
    } catch (Throwable $e) { printf("  %-18s skipped (%s)\n", $t, $e->getMessage()); }
}
if ($go) {
    $db->q("DELETE FROM {p}settings WHERE k LIKE '%wms.counter.%'");   // numbering starts again from 1
    foreach ($admins as $a) { /* each administrator gets a fresh default warehouse on the next visit */ }
}
$db->pdo()->exec('SET FOREIGN_KEY_CHECKS = 1');
echo $go ? "Done. Sign in as an administrator and start adding data.\n" : "Nothing was changed.\n";
