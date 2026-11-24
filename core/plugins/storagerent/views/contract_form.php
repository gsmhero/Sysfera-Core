<?php /** @var \Sysfera\Plugin $sf @var \SysferaStoragerent\Ctx $ctx @var string $lang @var array $k @var array $tenants @var array $units @var bool $tariff @var array $rateTypes @var array $penaltyTypes @var array $utilMethods @var array $warehouses */
require __DIR__ . '/_nav.php'; $id = (int)$k['id']; $lines = $k['lines'] ?: [['unit_id' => 0, 'share_pct' => 100]];
$pv = $k['penalty_type'] === 'fixed' ? \SysferaStoragerent\Billing::dec((int)$k['penalty_value']) : sr_num($k['penalty_value']); ?>
<p><a href="<?= e($sf->url($id ? '/contract/' . $id : '/contracts')) ?>">← <?= e($sf->t('wrms_contracts')) ?></a></p>
<form method="post" action="<?= e($sf->url('/contract/save')) ?>" class="card sr-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
  <h3><?= e($sf->t($id ? 'contract_edit' : 'contract_new')) ?> <?= $id ? e($k['number']) : '' ?></h3>
  <?php if ($id && $k['status'] === 'active'): ?><p class="hint"><?= e($sf->t('contract_edit_active_help')) ?></p><?php endif; ?>
  <?= sr_sel('tenant_id', $sf->t('col_tenant'), [0 => '—'] + array_column($tenants, 'name', 'id'), $k['tenant_id'], 'required') ?>
  <div class="sr-2"><?= sr_in('start_date', $sf->t('f_start'), $k['start_date'], 'date', 'required') ?><?= sr_in('end_date', $sf->t('f_end'), $k['end_date'], 'date', 'required') ?></div>
  <h4><?= e($sf->t('col_units')) ?></h4>
  <div id="srLines"><?php foreach ($lines as $i => $l): ?><div class="sr-line"><select name="unit_id[]"><option value="0">—</option><?php foreach ($units as $u): ?><option value="<?= (int)$u['id'] ?>"<?= (int)$u['id'] === (int)$l['unit_id'] ? ' selected' : '' ?>><?= e(($warehouses[(int)$u['warehouse_id']] ?? '') . ' / ' . $u['name'] . ' (' . sr_num($u['area']) . ' m², ' . $sf->t('st_' . $u['status']) . ')') ?></option><?php endforeach; ?></select>
    <input name="unit_share[]" value="<?= e(sr_num($l['share_pct'])) ?>" inputmode="decimal" aria-label="<?= e($sf->t('f_share')) ?>"> <span>%</span></div><?php endforeach; ?></div>
  <p><button type="button" class="btn sm" id="srAddLine">+ <?= e($sf->t('contract_add_unit')) ?></button> <small class="muted"><?= e($sf->t('f_share_help')) ?></small></p>
  <h4><?= e($sf->t('f_rate')) ?></h4>
  <div class="sr-3"><?= sr_sel('rate_type', $sf->t('f_rate_type'), array_combine($rateTypes, array_map(fn($r) => $sf->t('rt_' . $r), $rateTypes)), $k['rate_type']) ?><?= sr_in('rate', $sf->t('f_rate_value'), \SysferaStoragerent\Billing::dec((int)$k['rate']), 'text', 'inputmode="decimal" required') ?>
    <?= sr_sel('billing_months', $sf->t('f_billing'), [1 => $sf->t('billing_every', ['n' => 1]), 2 => $sf->t('billing_every', ['n' => 2]), 3 => $sf->t('billing_every', ['n' => 3]), 6 => $sf->t('billing_every', ['n' => 6]), 12 => $sf->t('billing_every', ['n' => 12])], $k['billing_months']) ?></div>
  <p class="muted sm"><?= e($sf->t('f_rate_help')) ?></p>
  <div class="sr-3"><?= sr_in('due_day', $sf->t('f_due_day'), $k['due_day'], 'number', 'min="1" max="31"') ?><?= sr_in('grace_days', $sf->t('f_grace'), $k['grace_days'], 'number', 'min="0"') ?><?= sr_in('deposit', $sf->t('f_deposit'), \SysferaStoragerent\Billing::dec((int)$k['deposit']), 'text', 'inputmode="decimal"') ?></div>
  <div class="sr-3"><?= sr_sel('penalty_type', $sf->t('f_penalty'), array_combine($penaltyTypes, array_map(fn($r) => $sf->t('pt_' . $r), $penaltyTypes)), $k['penalty_type']) ?><?= sr_in('penalty_value', $sf->t('f_penalty_value'), $pv, 'text', 'inputmode="decimal"') ?><?= sr_in('penalty_cap_pct', $sf->t('f_penalty_cap'), $k['penalty_cap_pct'], 'number', 'min="0"') ?></div>
  <p class="muted sm"><?= e($sf->t('f_penalty_help')) ?></p>
  <div class="sr-3"><?= sr_sel('util_method', $sf->t('f_utilities'), array_combine($utilMethods, array_map(fn($r) => $sf->t('um_' . $r), $utilMethods)), $k['util_method']) ?><?= sr_in('util_fixed', $sf->t('f_util_fixed'), \SysferaStoragerent\Billing::dec((int)$k['util_fixed']), 'text', 'inputmode="decimal"') ?>
    <?= sr_in('notice_days', $sf->t('f_notice'), $k['notice_days'], 'number', 'min="0"') ?></div>
  <h4><?= e($sf->t('f_access')) ?></h4>
  <div class="sr-3"><p><?= sr_chk('access_24h', $sf->t('access_24h'), $k['access_24h']) ?></p><?= sr_in('access_from', $sf->t('f_from'), $k['access_from'], 'time') ?><?= sr_in('access_to', $sf->t('f_to'), $k['access_to'], 'time') ?></div>
  <div class="sr-3"><?= sr_in('flag_days', $sf->t('f_flag_days'), $k['flag_days'], 'number', 'min="0"') ?><p><?= sr_chk('prorate', $sf->t('f_prorate'), $k['prorate']) ?></p></div>
  <?= sr_area('note', $sf->t('f_note'), $k['note'], '', 3) ?>
  <button class="btn primary"><?= e($sf->t('save')) ?></button> <a class="btn" href="<?= e($sf->url($id ? '/contract/' . $id : '/contracts')) ?>"><?= e($sf->t('cancel')) ?></a>
</form>
<script src="<?= e($sf->asset('storagerent.js')) ?>?v=<?= e($sf->manifest['version']) ?>" defer></script>
