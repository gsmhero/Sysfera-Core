<?php /** @var \Sysfera\Plugin $sf @var string $tab @var array $rows */
use SysferaWms\W;
include __DIR__ . '/_nav.php'; $admin = $sf->can('wms.admin'); ?>
<div class="page-h"><h1><?= e($sf->t('nav_warehouses')) ?></h1></div>
<div class="hint"><?= e($sf->t('wh_hint')) ?></div>
<div class="wgrid"><?php foreach ($rows as $w): ?>
  <a class="card wcard <?= $w['active'] ? '' : 'off' ?>" href="<?= e($sf->url('/warehouses/' . $w['id'])) ?>">
    <div class="row between"><b><?= e($w['name']) ?></b><?php if ($w['is_default']): ?><span class="badge info"><?= e($sf->t('default')) ?></span><?php elseif (!$w['active']): ?><span class="badge mute"><?= e($sf->t('archived')) ?></span><?php endif; ?></div>
    <p class="muted sm"><?= e($w['address'] ?: '—') ?></p>
    <div class="row between"><span><b><?= e(W::qty($w['qty'])) ?></b> <small class="muted"><?= e($sf->t('units_short')) ?></small></span>
      <small class="muted"><?= $w['use_cells'] ? '▦ ' . e($sf->t('cells_n', ['n' => (int)$w['cells']])) : e($sf->t('no_cells_mode')) ?></small></div>
  </a>
<?php endforeach; ?></div>

<?php if ($admin): ?>
<h2><?= e($sf->t('new_warehouse')) ?></h2>
<form class="card" method="post"><?= csrf_field() ?>
  <div class="cols">
    <label class="fld"><span><?= e($sf->t('col_name')) ?> *</span><input name="name" required maxlength="190"></label>
    <label class="fld"><span><?= e($sf->t('col_code')) ?></span><input name="code" maxlength="40"></label>
    <label class="fld"><span><?= e($sf->t('address')) ?></span><input name="address" maxlength="255"></label>
  </div>
  <label class="chk"><input type="checkbox" name="use_cells" value="1"> <?= e($sf->t('use_cells')) ?></label>
  <p class="muted sm"><?= e($sf->t('use_cells_hint')) ?></p>
  <button class="btn primary"><?= e($sf->t('add')) ?></button></form>
<?php endif; ?>
