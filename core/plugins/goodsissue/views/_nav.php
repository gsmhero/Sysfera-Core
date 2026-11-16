<?php /** @var \Sysfera\Plugin $sf @var string $tab */
$tabs = ['dashboard' => ['/', 'nav_overview'], 'transfers' => ['/transfers', 'nav_transfers'], 'orders' => ['/orders', 'nav_orders'], 'incidents' => ['/incidents', 'nav_incidents'], 'reports' => ['/reports', 'nav_reports'],
    'warehouses' => ['/warehouses', 'nav_warehouses'], 'audit' => ['/audit', 'nav_audit'], 'settings' => ['/settings', 'nav_settings']];
$stc = fn(string $s) => 'gi-st gi-' . $s;
?>
<link rel="stylesheet" href="<?= e($sf->asset('gi.css')) ?>?v=<?= e($sf->manifest['version']) ?>">
<nav class="wtabs"><?php foreach ($tabs as $k => [$path, $label]): ?><a href="<?= e($sf->url($path)) ?>" class="<?= $tab === $k ? 'on' : '' ?>"><?= e($sf->t($label)) ?></a><?php endforeach; ?></nav>
