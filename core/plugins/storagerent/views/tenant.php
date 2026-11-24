<?php /** @var \Sysfera\Plugin $sf @var \SysferaStoragerent\Ctx $ctx @var string $lang @var array $t @var array $contracts @var ?array $balance @var array $docs @var array $audit @var bool $canWrite @var bool $finance @var array $requests @var array $access @var array $kinds @var array $statuses @var array $vatModes */
require __DIR__ . '/_nav.php'; $id = (int)$t['id']; ?>
<p><a href="<?= e($sf->url('/tenants')) ?>">← <?= e($sf->t('wrms_tenants')) ?></a></p>
<?php if ($id && $t['restrictions'] !== ''): ?><div class="flash warn"><b><?= e($sf->t('restriction')) ?>:</b> <?= e($t['restrictions']) ?></div><?php endif; ?>
<div class="grid2">
<form method="post" action="<?= e($sf->url('/tenant/save')) ?>" class="card"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
  <h3><?= e($sf->t($id ? 'tenant_profile' : 'tenant_add')) ?> <?php if ($id): ?><span class="badge <?= e(sr_cls($t['status'])) ?>"><?= e($sf->t('st_' . $t['status'])) ?></span><?php endif; ?></h3>
  <fieldset <?= $canWrite ? '' : 'disabled' ?> style="border:0;padding:0;margin:0">
  <?= sr_sel('kind', $sf->t('f_kind'), array_combine($kinds, array_map(fn($k) => $sf->t('kind_' . $k), $kinds)), $t['kind']) ?>
  <?= sr_in('name', $sf->t('f_name'), $t['name'], 'text', 'required maxlength="190"') ?>
  <div class="sr-2"><?= sr_in('tin', $sf->t('f_tin'), $t['tin'], 'text', 'inputmode="numeric" maxlength="12"') ?><?= sr_in('contact', $sf->t('f_contact'), $t['contact'], 'text', 'maxlength="150"') ?></div>
  <div class="sr-2"><?= sr_in('phone', $sf->t('f_phone'), $t['phone'], 'tel', 'maxlength="40"') ?><?= sr_in('email', $sf->t('f_email'), $t['email'], 'email', 'maxlength="190"') ?></div>
  <?= sr_in('address', $sf->t('f_address'), $t['address'], 'text', 'maxlength="255"') ?>
  <?= sr_area('restrictions', $sf->t('f_restrictions'), $t['restrictions'], $sf->t('f_restrictions_help'), 2) ?>
  <div class="sr-3"><?= sr_sel('status', $sf->t('col_status'), array_combine($statuses, array_map(fn($s) => $sf->t('st_' . $s), $statuses)), $t['status']) ?>
    <?= sr_sel('vat_mode', $sf->t('f_vat_mode'), array_combine($vatModes, array_map(fn($s) => $sf->t('vm_' . $s), $vatModes)), $t['vat_mode']) ?><?= sr_in('vat_rate', $sf->t('f_vat_rate'), sr_num($t['vat_rate']), 'text', 'inputmode="decimal"') ?></div>
  <?= sr_in('user_id', $sf->t('f_user_id'), $t['user_id'] ?? '', 'number', 'min="1"') ?><small class="muted"><?= e($sf->t('f_user_id_help')) ?></small>
  <?= sr_area('note', $sf->t('f_note'), $t['note'], '', 2) ?>
  <?php if ($canWrite): ?><button class="btn primary"><?= e($sf->t('save')) ?></button><?php endif; ?></fieldset>
</form>
<?php if ($id): ?><div>
  <?php if ($balance): ?><div class="card"><h3><?= e($sf->t('tenant_balance')) ?></h3><table><tbody>
    <tr><td><?= e($sf->t('bal_outstanding')) ?></td><td class="r"><?= e(sr_m($balance['outstanding'], $lang)) ?></td></tr><tr><td><?= e($sf->t('bal_penalty')) ?></td><td class="r"><?= e(sr_m($balance['penalty_unbilled'], $lang)) ?></td></tr>
    <tr><td><?= e($sf->t('bal_credit')) ?></td><td class="r"><?= e(sr_m($balance['credit'], $lang)) ?></td></tr><tr><td><b><?= e($sf->t('col_debt')) ?></b></td><td class="r <?= $balance['debt'] > 0 ? 'bad-t' : '' ?>"><b><?= e(sr_m($balance['debt'], $lang)) ?></b></td></tr></tbody></table>
    <p><a class="btn sm" href="<?= e($sf->url('/invoices?tenant=' . $id)) ?>"><?= e($sf->t('wrms_invoices')) ?></a> <a class="btn sm" href="<?= e($sf->url('/payments?tenant=' . $id)) ?>"><?= e($sf->t('wrms_payments')) ?></a></p></div><?php endif; ?>
  <div class="card"><div class="row sr-between"><h3 style="margin:0"><?= e($sf->t('wrms_contracts')) ?></h3><?php if ($canWrite): ?><a class="btn sm" href="<?= e($sf->url('/contract/0/edit?tenant=' . $id)) ?>">+ <?= e($sf->t('contract_new')) ?></a><?php endif; ?></div>
    <?php if (!$contracts): ?><p class="muted"><?= e($sf->t('none')) ?></p><?php else: ?><table><tbody><?php foreach ($contracts as $k): ?><tr><td><a href="<?= e($sf->url('/contract/' . $k['id'])) ?>"><?= e($k['number']) ?></a></td><td><?= e(sr_d($k['start_date'])) ?> – <?= e(sr_d($k['end_date'])) ?></td><td><span class="badge <?= e(sr_cls($k['state'])) ?>"><?= e($sf->t('st_' . $k['state'])) ?></span></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></div>
</div><?php endif; ?>
</div>
<?php if ($id): ?>
<div class="card"><h3><?= e($sf->t('wrms_documents')) ?></h3>
  <?php if ($docs): ?><table><tbody><?php foreach ($docs as $d): ?><tr><td><a href="<?= e($sf->url('/doc/' . $d['id'])) ?>"><?= e($d['title']) ?></a> <small class="muted"><?= e($d['file_name']) ?></small></td><td><?= e(sr_dt((int)$d['created_at'])) ?></td><td class="r"><?php if ($canWrite): ?>
    <form method="post" action="<?= e($sf->url('/doc/' . $d['id'] . '/delete')) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="back" value="<?= e('/p/storagerent/tenant/' . $id) ?>"><button class="btn sm danger"><?= e($sf->t('delete')) ?></button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table><?php else: ?><p class="muted"><?= e($sf->t('none')) ?></p><?php endif; ?>
  <?php if ($canWrite): ?><form method="post" enctype="multipart/form-data" action="<?= e($sf->url('/doc/upload')) ?>" class="sr-inline"><?= csrf_field() ?><input type="hidden" name="owner_type" value="tenant"><input type="hidden" name="owner_id" value="<?= $id ?>"><input type="hidden" name="back" value="<?= e('/p/storagerent/tenant/' . $id) ?>">
    <input name="title" placeholder="<?= e($sf->t('doc_title')) ?>" maxlength="190"><input type="file" name="file" required><button class="btn"><?= e($sf->t('upload')) ?></button></form><?php endif; ?></div>
<?php if ($requests): ?><div class="card"><h3><?= e($sf->t('wrms_requests')) ?></h3><table><tbody><?php foreach ($requests as $r): ?><tr><td><?= e($r['title']) ?></td><td><span class="badge <?= e(sr_cls($r['status'])) ?>"><?= e($sf->t('rq_' . $r['status'])) ?></span></td><td><?= e(sr_dt((int)$r['created_at'])) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
<?php if ($audit): ?><div class="card"><h3><?= e($sf->t('audit_log')) ?></h3><div class="sr-scroll"><table><thead><tr><th><?= e($sf->t('col_date')) ?></th><th><?= e($sf->t('col_user')) ?></th><th><?= e($sf->t('col_action')) ?></th><th><?= e($sf->t('col_changes')) ?></th></tr></thead><tbody>
  <?php foreach ($audit as $a): $diff = json_decode((string)$a['diff'], true) ?: []; ?><tr><td><?= e(sr_dt((int)$a['created_at'])) ?></td><td>#<?= (int)$a['user_id'] ?></td><td><?= e($a['action']) ?></td><td class="sm"><?php foreach ($diff as $f => $ch): ?><div><b><?= e($f) ?></b>: <?= e(is_array($ch) ? ($ch[0] . ' → ' . $ch[1]) : (string)$ch) ?></div><?php endforeach; ?></td></tr><?php endforeach; ?></tbody></table></div></div><?php endif; ?>
<?php endif; ?>
