<?php /** @var \Sysfera\Plugin $sf @var string $tab */
$tabs = ['overview' => ['/', 'nav_overview'], 'catalog' => ['/catalog', 'nav_catalog'], 'orders' => ['/orders', 'nav_orders'], 'shop' => ['/shop', 'nav_shop']];
if ($sf->can('wmsbb.wallet')) { $tabs['wallet'] = ['/wallet', 'nav_wallet']; $tabs['profile'] = ['/profile', 'nav_profile']; }
if ($sf->can('wmsbb.admin')) $tabs['settings'] = ['/settings', 'nav_settings'];
/** translate a stored error: a lang key of this plugin, a WMS posting key, or "stock:<name>|<have>|<need>" */
$why = function (?string $raw) use ($sf): string {
    if ($raw === null || $raw === '') return '';
    $out = [];
    foreach (explode(' | ', $raw) as $k) {
        if (str_starts_with($k, 'stock:')) { [$n, $have, $need] = array_pad(explode('|', substr($k, 6)), 3, ''); $out[] = $sf->t('err_nostock', ['name' => $n, 'have' => $have, 'need' => $need]); }
        elseif (preg_match('/^[a-z_]+$/', $k)) { $t = $sf->t($k); $out[] = $t !== $sf->slug . '.' . $k && $t !== $k ? $t : $k; }
        else $out[] = $k;
    }
    return implode('; ', $out);
};
?>
<link rel="stylesheet" href="<?= e(url('/assets/p/wms/wms.css')) ?>">
<link rel="stylesheet" href="<?= e($sf->asset('bb.css')) ?>">
<nav class="wtabs"><?php foreach ($tabs as $k => [$path, $label]): ?><a href="<?= e($sf->url($path)) ?>" class="<?= $tab === $k ? 'on' : '' ?>"><?= e($sf->t($label)) ?></a><?php endforeach; ?></nav>
