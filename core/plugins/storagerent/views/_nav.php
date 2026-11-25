<?php /** @var \Sysfera\Plugin $sf @var \SysferaStoragerent\Ctx $ctx @var string $view @var string $lang */
require_once __DIR__ . '/_h.php';
$onOffers = in_array($view ?? '', ['offers', 'map', 'offer', 'offer_form', 'favorites', 'mine'], true);
$staff = $ctx->staff();
$srNav = [];   // [path, label key, visible, views that light it up]
if ($staff) {
    $srNav = [['/wrms', 'wrms_dashboard', true, ['dashboard']], ['/units', 'wrms_units', true, ['units']], ['/tenants', 'wrms_tenants', true, ['tenants', 'tenant']], ['/contracts', 'wrms_contracts', true, ['contracts', 'contract', 'contract_form']],
        ['/invoices', 'wrms_invoices', $ctx->can('finance'), ['invoices', 'invoice']], ['/payments', 'wrms_payments', $ctx->can('finance'), ['payments']], ['/utilities', 'wrms_utilities', true, ['utilities']], ['/reports', 'wrms_reports', $ctx->can('finance'), ['reports']],
        ['/notifications', 'wrms_notifications', !$ctx->owner(), ['notifications']], ['/access', 'wrms_access', true, ['access']], ['/documents', 'wrms_documents', true, ['documents']], ['/requests', 'wrms_requests', true, ['requests']], ['/settings', 'settings', $ctx->can('settings'), ['settings']]];
}
$wrmsUrl = $staff ? '/wrms' : ($ctx->role === 'tenant' ? '/portal' : '');
?>
<link rel="stylesheet" href="<?= e($sf->asset('storagerent.css')) ?>?v=<?= e($sf->manifest['version']) ?>">
<div class="sr-top">
  <h1><?= e($sf->t('menu')) ?></h1>
  <nav class="sr-tabs" aria-label="<?= e($sf->t('menu')) ?>">
    <?php if ($wrmsUrl !== ''): ?><a href="<?= e($sf->url($wrmsUrl)) ?>" class="<?= !$onOffers ? 'on' : '' ?>"><?= sr_icon('warehouse') ?><span><?= e($sf->t('tab_wrms')) ?></span></a><?php endif; ?>
    <a href="<?= e($sf->url('/offers')) ?>" class="<?= $onOffers ? 'on' : '' ?>"><?= sr_icon('tag') ?><span><?= e($sf->t('tab_offers')) ?></span></a>
  </nav>
</div>
<?php if (!$onOffers && $srNav): ?>
<nav class="sr-sub"><?php foreach ($srNav as [$path, $key, $show, $views]): if (!$show) continue; ?><a href="<?= e($sf->url($path)) ?>" class="<?= in_array($view ?? '', $views, true) ? 'on' : '' ?>"><?= e($sf->t($key)) ?></a><?php endforeach; ?></nav>
<?php endif; ?>
<?php if ($onOffers): $srView = $view ?? ''; ?>
<nav class="sr-sub sr-sub-offers" aria-label="<?= e($sf->t('tab_offers')) ?>">
  <a href="<?= e($sf->url('/offers')) ?>" class="<?= in_array($srView, ['offers', 'offer'], true) ? 'on' : '' ?>"><?= sr_icon('list') ?><span><?= e($sf->t('offers_list')) ?></span></a>
  <a href="<?= e($sf->url('/offers/map')) ?>" class="<?= $srView === 'map' ? 'on' : '' ?>"><?= sr_icon('pin') ?><span><?= e($sf->t('offers_map')) ?></span></a>
  <?php if ($ctx->uid > 0): ?><a href="<?= e($sf->url('/offers/mine')) ?>" class="<?= $srView === 'mine' ? 'on' : '' ?>"><?= sr_icon('warehouse') ?><span><?= e($sf->t('offers_mine')) ?></span></a>
  <a href="<?= e($sf->url('/offers/favorites')) ?>" class="<?= $srView === 'favorites' ? 'on' : '' ?>"><?= sr_icon('heart') ?><span><?= e($sf->t('offers_favorites')) ?></span></a>
  <a href="<?= e($sf->url('/offers/new')) ?>" class="sr-sub-add<?= $srView === 'offer_form' ? ' on' : '' ?>"><?= sr_icon('plus') ?><span><?= e($sf->t('offers_new')) ?></span></a><?php endif; ?>
</nav>
<?php endif; ?>
