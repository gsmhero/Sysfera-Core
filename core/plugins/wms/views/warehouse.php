<?php /** @var \Sysfera\Plugin $sf @var string $tab @var array $w @var array $zones @var array $cells @var array $inCell @var int $cellId @var float $qty */
use SysferaWms\W;
include __DIR__ . '/_nav.php'; $admin = $sf->can('wms.admin'); ?>
<div class="page-h"><h1><?= e($w['name']) ?> <?php if (!$w['active']): ?><span class="badge mute"><?= e($sf->t('archived')) ?></span><?php endif; ?></h1>
  <a class="btn" href="<?= e($sf->url('/warehouses')) ?>">← <?= e($sf->t('nav_warehouses')) ?></a></div>

<div class="cols2 wide-left">
<div>
<form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="do" value="save">
  <div class="cols">
    <label class="fld"><span><?= e($sf->t('col_name')) ?> *</span><input name="name" value="<?= e($w['name']) ?>" required maxlength="190" <?= $admin ? '' : 'readonly' ?>></label>
    <label class="fld"><span><?= e($sf->t('col_code')) ?></span><input name="code" value="<?= e($w['code']) ?>" maxlength="40" <?= $admin ? '' : 'readonly' ?>></label>
  </div>
  <label class="fld"><span><?= e($sf->t('address')) ?></span><input name="address" value="<?= e($w['address']) ?>" maxlength="255" <?= $admin ? '' : 'readonly' ?>></label>
  <label class="fld"><span><?= e($sf->t('comment')) ?></span><textarea name="comment" rows="2" <?= $admin ? '' : 'readonly' ?>><?= e($w['comment']) ?></textarea></label>
  <label class="chk"><input type="checkbox" name="use_cells" value="1" <?= $w['use_cells'] ? 'checked' : '' ?> <?= $admin ? '' : 'disabled' ?>> <?= e($sf->t('use_cells')) ?></label>
  <p class="muted sm"><?= e($sf->t('use_cells_hint')) ?></p>
  <label class="chk"><input type="checkbox" name="is_default" value="1" <?= $w['is_default'] ? 'checked disabled' : '' ?> <?= $admin ? '' : 'disabled' ?>> <?= e($sf->t('make_default')) ?></label>
  <?php if ($admin): ?><div class="row" style="margin-top:12px"><button class="btn primary"><?= e($sf->t('save')) ?></button>
    <?php if ($w['active'] && !$w['is_default']): ?><button class="btn danger" name="do" value="archive" data-confirm="<?= e($sf->t('confirm_archive')) ?>"><?= e($sf->t('archive')) ?></button><?php endif; ?>
    <?php if (!$w['active']): ?><button class="btn" name="do" value="restore"><?= e($sf->t('restore')) ?></button><?php endif; ?></div><?php endif; ?>
</form>

<section class="card"><h3><?= e($sf->t('zones')) ?> <small class="muted"><?= e($sf->t('zones_hint')) ?></small></h3>
  <div class="chips"><?php foreach ($zones as $z): ?>
    <form method="post" class="chip"><?= csrf_field() ?><input type="hidden" name="do" value="zone_delete"><input type="hidden" name="zone" value="<?= (int)$z['id'] ?>"><?= e($z['name']) ?><?php if ($admin): ?> <button class="x" data-confirm="<?= e($sf->t('confirm_delete')) ?>">×</button><?php endif; ?></form>
  <?php endforeach; if (!$zones): ?><span class="muted sm"><?= e($sf->t('no_zones')) ?></span><?php endif; ?></div>
  <?php if ($admin): ?><form method="post" class="row"><?= csrf_field() ?><input type="hidden" name="do" value="zone"><input name="name" placeholder="<?= e($sf->t('zone_name')) ?>" required maxlength="120"><button class="btn"><?= e($sf->t('add')) ?></button></form><?php endif; ?>
</section>

<section class="card"><h3><?= e($sf->t('cells')) ?> <small class="muted">(<?= count($cells) ?>)</small></h3>
  <?php if ($admin): ?><form method="post" class="cellform"><?= csrf_field() ?><input type="hidden" name="do" value="cell">
    <input name="name" placeholder="<?= e($sf->t('cell_names')) ?>" required maxlength="1000">
    <select name="zone"><option value=""><?= e($sf->t('no_zone')) ?></option><?php foreach ($zones as $z): ?><option value="<?= (int)$z['id'] ?>"><?= e($z['name']) ?></option><?php endforeach; ?></select>
    <input name="barcode" placeholder="<?= e($sf->t('col_barcode')) ?>" maxlength="80"><button class="btn primary"><?= e($sf->t('add')) ?></button></form>
  <p class="muted sm"><?= e($sf->t('cell_hint')) ?></p><?php endif; ?>
  <?php if ($cells): ?><table class="tbl"><thead><tr><th><?= e($sf->t('cell')) ?></th><th><?= e($sf->t('zone')) ?></th><th><?= e($sf->t('col_barcode')) ?></th><th class="num"><?= e($sf->t('col_on_hand')) ?></th><th></th></tr></thead><tbody>
    <?php foreach ($cells as $c): ?><tr class="<?= (int)$cellId === (int)$c['id'] ? 'sel' : '' ?>"><td><a href="<?= e($sf->url('/warehouses/' . $w['id'] . '?cell=' . $c['id'])) ?>"><b><?= e($c['name']) ?></b></a></td><td class="muted"><?= e($c['zone'] ?? '—') ?></td><td class="muted"><?= e($c['barcode'] ?? '') ?></td>
      <td class="num"><?= (float)$c['qty'] ? e(W::qty($c['qty'])) : '<span class="badge ok">' . e($sf->t('free')) . '</span>' ?></td>
      <td><?php if ($admin): ?><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="do" value="cell_delete"><input type="hidden" name="cell" value="<?= (int)$c['id'] ?>"><button class="x" data-confirm="<?= e($sf->t('confirm_delete')) ?>">×</button></form><?php endif; ?></td></tr>
    <?php endforeach; ?></tbody></table><?php else: ?><p class="muted"><?= e($sf->t('no_cells')) ?></p><?php endif; ?>
</section>
</div>

<div>
  <section class="card kpi"><small><?= e($sf->t('col_on_hand')) ?></small><b><?= e(W::qty($qty)) ?></b></section>
  <?php if ($cellId): ?><section class="card"><h3><?= e($sf->t('in_cell')) ?></h3>
    <?php if (!$inCell): ?><p class="muted"><?= e($sf->t('cell_empty')) ?></p><?php else: ?><table class="tbl"><tbody><?php foreach ($inCell as $r): ?>
      <tr onclick="location='<?= e($sf->url('/products/' . $r['id'])) ?>'"><td><b><?= e($r['name']) ?></b></td><td class="num"><?= e(W::qty($r['on_hand'])) ?> <?= e($r['unit']) ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></section><?php endif; ?>
  <section class="card"><a class="btn" href="<?= e($sf->url('/stock?wh=' . $w['id'])) ?>"><?= e($sf->t('open_stock')) ?> →</a></section>
</div>
</div>
