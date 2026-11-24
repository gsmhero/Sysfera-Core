<?php /** @var \Sysfera\Plugin $sf @var \SysferaStoragerent\Ctx $ctx @var string $lang @var array $rows @var string $status @var int $tenantId @var string $q @var int $sum @var bool $canWrite @var array $tenants @var array $contracts @var array $statuses */
require __DIR__ . '/_nav.php'; ?>
<form class="sr-filter" method="get" action="<?= e($sf->url('/invoices')) ?>"><input type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e($sf->t('search_ph')) ?>">
  <select name="status"><option value=""><?= e($sf->t('all_statuses')) ?></option><?php foreach ($statuses as $s): ?><option value="<?= e($s) ?>"<?= $s === $status ? ' selected' : '' ?>><?= e($sf->t('st_' . $s)) ?></option><?php endforeach; ?></select>
  <select name="tenant"><option value="0"><?= e($sf->t('all_tenants')) ?></option><?php foreach ($tenants as $id => $n): ?><option value="<?= (int)$id ?>"<?= (int)$id === $tenantId ? ' selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select>
  <button class="btn"><?= e($sf->t('search')) ?></button></form>
<?php if (!$rows): ?><div class="card empty"><?= e($sf->t('none')) ?></div><?php else: ?>
<div class="card sr-scroll"><table><thead><tr><th><?= e($sf->t('col_invoice')) ?></th><th><?= e($sf->t('col_tenant')) ?></th><th><?= e($sf->t('col_contract')) ?></th><th><?= e($sf->t('col_period')) ?></th><th><?= e($sf->t('col_due_date')) ?></th><th class="r"><?= e($sf->t('col_total')) ?></th><th class="r"><?= e($sf->t('col_outstanding')) ?></th><th><?= e($sf->t('col_status')) ?></th></tr></thead><tbody>
<?php foreach ($rows as $i): ?><tr><td><a href="<?= e($sf->url('/invoice/' . $i['id'])) ?>"><b><?= e($i['number']) ?></b></a></td><td><?= e($i['tenant_name']) ?></td><td><a href="<?= e($sf->url('/contract/' . $i['contract_id'])) ?>"><?= e($i['contract_number']) ?></a></td>
  <td><?= e($i['period_start'] ? sr_d($i['period_start']) . ' – ' . sr_d($i['period_end']) : $sf->t('inv_manual')) ?></td><td><?= e(sr_d($i['due_date'])) ?></td><td class="r"><?= e(sr_m($i['total'], $lang)) ?></td><td class="r <?= $i['late'] ? 'bad-t' : '' ?>"><?= e(sr_m($i['outstanding'], $lang)) ?></td>
  <td><span class="badge <?= e(sr_cls($i['status'])) ?>"><?= e($sf->t('st_' . $i['status'])) ?></span><?php if ($i['late'] && $i['status'] !== 'overdue'): ?> <span class="badge bad"><?= e($sf->t('st_late')) ?></span><?php endif; ?></td></tr><?php endforeach; ?></tbody>
  <tfoot><tr><td colspan="6"><b><?= e($sf->t('total')) ?></b></td><td class="r"><b><?= e(sr_m($sum, $lang)) ?></b></td><td></td></tr></tfoot></table></div><?php endif; ?>
<?php if ($canWrite && $contracts): ?>
<form method="post" action="<?= e($sf->url('/invoice/new')) ?>" class="card sr-form"><?= csrf_field() ?><h3><?= e($sf->t('inv_new')) ?></h3><p class="muted sm"><?= e($sf->t('inv_new_help')) ?></p>
  <div class="sr-2"><?= sr_sel('contract_id', $sf->t('col_contract'), array_column(array_map(fn($k) => ['id' => $k['id'], 'n' => $k['number'] . ' — ' . ($k['tenant']['name'] ?? '')], $contracts), 'n', 'id'), '') ?><?= sr_in('due', $sf->t('col_due_date'), '', 'date', 'required') ?></div>
  <div id="srInvLines"><div class="sr-line"><input name="desc[]" placeholder="<?= e($sf->t('f_description')) ?>" maxlength="255"><input name="amount[]" placeholder="<?= e($sf->t('f_amount')) ?>" inputmode="decimal"><label class="sr-chk"><input type="checkbox" name="vatable[0]" value="1"> <span><?= e($sf->t('f_vatable')) ?></span></label></div></div>
  <?= sr_in('reason', $sf->t('f_reason'), '', 'text', 'required maxlength="255"') ?>
  <p><?= sr_chk('draft', $sf->t('inv_draft'), 0) ?></p><button class="btn primary"><?= e($sf->t('inv_create')) ?></button></form>
<?php endif; ?>
