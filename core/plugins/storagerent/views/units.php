<?php /** @var \Sysfera\Plugin $sf @var \SysferaStoragerent\Ctx $ctx @var string $lang @var array $warehouses @var array $units @var array $who @var array $plan @var bool $canWrite @var array $types @var array $features */
require __DIR__ . '/_nav.php'; $cur = (int)($_GET['edit'] ?? 0); $curU = null; foreach ($units as $u) if ((int)$u['id'] === $cur) $curU = $u; ?>
<?php if (!$warehouses): ?><div class="card empty"><?= e($sf->t('units_no_warehouse')) ?></div><?php endif; ?>
<?php foreach ($warehouses as $w): $list = $plan[(int)$w['id']] ?? []; ?>
<div class="card"><div class="row sr-between"><div><h3 style="margin:0"><?= e($w['name']) ?></h3><small class="muted"><?= e(trim($w['city'] . ', ' . $w['address'], ', ')) ?></small></div>
  <?php if ($canWrite): ?><a class="btn sm" href="<?= e($sf->url('/offers/new?wh=' . (int)$w['id'])) ?>"><?= e($sf->t('units_to_offer')) ?></a><?php endif; ?></div>
  <?php if ($list): ?>
  <div class="sr-plan" aria-label="<?= e($sf->t('units_plan')) ?>"><?php foreach ($list as $u): ?><a class="sr-cell st-<?= e($u['status']) ?>" style="grid-column:<?= (int)$u['plan_x'] + 1 ?> / span <?= max(1, (int)$u['plan_w']) ?>;grid-row:<?= (int)$u['plan_y'] + 1 ?> / span <?= max(1, (int)$u['plan_h']) ?>" href="<?= e($sf->url('/units?edit=' . (int)$u['id'])) ?>" title="<?= e($sf->t('st_' . $u['status'])) ?>"><b><?= e($u['name']) ?></b><small><?= e(sr_num($u['area'])) ?> m²</small></a><?php endforeach; ?></div>
  <p class="sr-legend"><?php foreach (['free', 'reserved', 'occupied', 'maintenance'] as $s): ?><span class="badge <?= e(sr_cls($s)) ?>"><?= e($sf->t('st_' . $s)) ?></span> <?php endforeach; ?></p>
  <div class="sr-scroll"><table><thead><tr><th><?= e($sf->t('f_name')) ?></th><th><?= e($sf->t('f_type')) ?></th><th>m²</th><th>m³</th><th><?= e($sf->t('f_pallets')) ?></th><th><?= e($sf->t('f_load')) ?></th><th><?= e($sf->t('f_height')) ?></th><th><?= e($sf->t('f_dock')) ?></th><th><?= e($sf->t('f_features')) ?></th><th><?= e($sf->t('col_status')) ?></th><th><?= e($sf->t('col_tenant')) ?></th><th></th></tr></thead><tbody>
  <?php foreach ($list as $u): ?><tr><td><b><?= e($u['name']) ?></b></td><td><?= e($sf->t('ut_' . $u['type'])) ?></td><td><?= e(sr_num($u['area'])) ?></td><td><?= e(sr_num($u['volume'])) ?></td><td><?= (int)$u['pallets'] ?></td><td><?= e(sr_num($u['load_t'])) ?></td><td><?= e(sr_num($u['height'])) ?></td><td><?= $u['dock'] ? '✓' : '' ?></td>
    <td><?= e(implode(', ', array_map(fn($f) => $sf->t('ft_' . $f), $u['feature_list']))) ?></td><td><span class="badge <?= e(sr_cls($u['status'])) ?>"><?= e($sf->t('st_' . $u['status'])) ?></span><?php if ($u['status'] === 'occupied' && $u['rented'] < 100): ?> <small class="muted"><?= e(sr_num($u['rented'])) ?>%</small><?php endif; ?></td>
    <td><?php foreach ($who[(int)$u['id']] ?? [] as $t): ?><a href="<?= e($sf->url('/contract/' . $t['contract_id'])) ?>"><?= e($t['tenant']) ?></a> <small class="muted"><?= e(sr_num($t['share'])) ?>%<?= $t['draft'] ? ' · ' . e($sf->t('st_draft')) : '' ?></small><br><?php endforeach; ?></td>
    <td><?php if ($canWrite): ?><a class="btn sm" href="<?= e($sf->url('/units?edit=' . (int)$u['id'])) ?>"><?= e($sf->t('edit')) ?></a><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div>
  <?php else: ?><p class="muted"><?= e($sf->t('units_none')) ?></p><?php endif; ?></div>
<?php endforeach; ?>
<?php if ($canWrite): $v = $curU ?? ['id' => 0, 'warehouse_id' => (int)($warehouses[0]['id'] ?? 0), 'name' => '', 'type' => 'dry', 'area' => '', 'volume' => '', 'pallets' => '', 'load_t' => '', 'height' => '', 'dock' => 0, 'feature_list' => [], 'status_manual' => '', 'plan_x' => 0, 'plan_y' => 0, 'plan_w' => 10, 'plan_h' => 6, 'note' => '']; ?>
<div class="grid2">
<form method="post" action="<?= e($sf->url('/unit/save')) ?>" class="card"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$v['id'] ?>"><h3><?= e($sf->t($v['id'] ? 'units_edit' : 'units_add')) ?></h3>
  <?php if ($warehouses): ?><?= sr_sel('warehouse_id', $sf->t('f_warehouse'), array_column($warehouses, 'name', 'id'), $v['warehouse_id']) ?>
  <?= sr_in('name', $sf->t('f_name'), $v['name'], 'text', 'required maxlength="100"') ?>
  <?= sr_sel('type', $sf->t('f_type'), array_combine($types, array_map(fn($x) => $sf->t('ut_' . $x), $types)), $v['type']) ?>
  <div class="sr-3"><?= sr_in('area', 'm²', $v['area'], 'text', 'inputmode="decimal"') ?><?= sr_in('volume', 'm³', $v['volume'], 'text', 'inputmode="decimal"') ?><?= sr_in('pallets', $sf->t('f_pallets'), $v['pallets'], 'text', 'inputmode="numeric"') ?></div>
  <div class="sr-3"><?= sr_in('load_t', $sf->t('f_load'), $v['load_t'], 'text', 'inputmode="decimal"') ?><?= sr_in('height', $sf->t('f_height'), $v['height'], 'text', 'inputmode="decimal"') ?><?= sr_sel('status_manual', $sf->t('f_status_manual'), ['' => $sf->t('st_auto'), 'reserved' => $sf->t('st_reserved'), 'maintenance' => $sf->t('st_maintenance')], $v['status_manual']) ?></div>
  <p><?= sr_chk('dock', $sf->t('f_dock'), $v['dock']) ?></p>
  <p class="sr-chks"><?php foreach ($features as $f): ?><label class="sr-chk"><input type="checkbox" name="features[]" value="<?= e($f) ?>"<?= in_array($f, $v['feature_list'], true) ? ' checked' : '' ?>> <span><?= e($sf->t('ft_' . $f)) ?></span></label> <?php endforeach; ?></p>
  <p class="muted sm"><?= e($sf->t('units_plan_help')) ?></p>
  <div class="sr-4"><?= sr_in('plan_x', 'X', $v['plan_x'], 'number', 'min="0"') ?><?= sr_in('plan_y', 'Y', $v['plan_y'], 'number', 'min="0"') ?><?= sr_in('plan_w', $sf->t('f_plan_w'), $v['plan_w'], 'number', 'min="1"') ?><?= sr_in('plan_h', $sf->t('f_plan_h'), $v['plan_h'], 'number', 'min="1"') ?></div>
  <?= sr_area('note', $sf->t('f_note'), $v['note'], '', 2) ?>
  <button class="btn primary"><?= e($sf->t('save')) ?></button>
  <?php if ($v['id']): ?><a class="btn" href="<?= e($sf->url('/units')) ?>"><?= e($sf->t('cancel')) ?></a><?php endif; ?>
  <?php else: ?><p class="muted"><?= e($sf->t('units_no_warehouse')) ?></p><?php endif; ?>
</form>
<?php if ($v['id']): ?><form method="post" action="<?= e($sf->url('/unit/' . (int)$v['id'] . '/delete')) ?>" class="card" onsubmit="return confirm('<?= e($sf->t('confirm_delete')) ?>')"><?= csrf_field() ?><h3><?= e($sf->t('delete')) ?></h3><p class="muted sm"><?= e($sf->t('units_delete_help')) ?></p><button class="btn danger"><?= e($sf->t('delete')) ?></button></form><?php endif; ?>
<form method="post" action="<?= e($sf->url('/warehouse/save')) ?>" class="card"><?= csrf_field() ?><h3><?= e($sf->t('wh_add')) ?></h3>
  <?= sr_in('name', $sf->t('f_name'), '', 'text', 'required maxlength="150"') ?><div class="sr-2"><?= sr_in('city', $sf->t('f_city'), '', 'text', 'maxlength="100"') ?><?= sr_in('address', $sf->t('f_address'), '', 'text', 'maxlength="255"') ?></div>
  <div class="sr-2"><?= sr_in('lat', $sf->t('f_lat'), '', 'text', 'inputmode="decimal"') ?><?= sr_in('lon', $sf->t('f_lon'), '', 'text', 'inputmode="decimal"') ?></div><button class="btn"><?= e($sf->t('save')) ?></button></form>
</div>
<?php endif; ?>
