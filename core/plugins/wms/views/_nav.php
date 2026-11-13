<?php /** @var \Sysfera\Plugin $sf @var string $tab */
$tabs = ['dashboard' => ['/', 'nav_dashboard'], 'products' => ['/products', 'nav_products'], 'stock' => ['/stock', 'nav_stock'], 'docs' => ['/docs', 'nav_docs'],
    'partners' => ['/partners', 'nav_partners'], 'warehouses' => ['/warehouses', 'nav_warehouses'], 'reports' => ['/reports', 'nav_reports'], 'audit' => ['/audit', 'nav_audit']];
if ($sf->can('wms.admin')) $tabs['settings'] = ['/settings', 'nav_settings'];
?>
<link rel="stylesheet" href="<?= e($sf->asset('wms.css')) ?>?v=<?= e($sf->manifest['version']) ?>">
<script src="<?= e($sf->asset('wms-menu.js')) ?>?v=<?= e($sf->manifest['version']) ?>" defer></script>
<nav class="wtabs"><?php foreach ($tabs as $k => [$path, $label]): ?><a href="<?= e($sf->url($path)) ?>" class="<?= $tab === $k ? 'on' : '' ?>"><?= e($sf->t($label)) ?></a><?php endforeach; ?></nav>
