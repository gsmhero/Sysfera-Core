<?php /** @var \Sysfera\Plugin $sf @var string $tab @var array $rows @var string $st @var string $q */
include __DIR__ . '/_nav.php'; ?>
<div class="page-h"><h1><?= e($sf->t('nav_orders')) ?></h1></div>
<form class="row" method="get"><select name="status" onchange="this.form.submit()"><option value=""><?= e($sf->t('all')) ?></option><?php foreach (\SysferaGi\Machine::STATUSES as $s): ?><option value="<?= e($s) ?>"<?= $st === $s ? ' selected' : '' ?>><?= e($sf->t('st_' . $s)) ?></option><?php endforeach; ?></select>
  <input type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e($sf->t('search_orders')) ?>"><button class="btn"><?= e($sf->t('find')) ?></button></form>
<div class="card flush"><table class="tbl"><thead><tr><th>#</th><th><?= e($sf->t('customer')) ?></th><th><?= e($sf->t('warehouse')) ?></th><th><?= e($sf->t('total')) ?></th><th><?= e($sf->t('status')) ?></th><th><?= e($sf->t('updated')) ?></th></tr></thead><tbody>
<?php foreach ($rows as $o): ?><tr><td><a href="<?= e($sf->url('/orders/' . $o['uuid'])) ?>"><b><?= e($o['number']) ?></b></a></td><td><?= e($o['customer_name']) ?> <small class="muted"><?= e($o['customer_phone']) ?></small></td><td><?= e($o['wname'] ?? '') ?></td><td><?= e($o['total_rub']) ?> ₽</td><td><span class="gi-st gi-<?= e($o['status']) ?>"><?= e($sf->t('st_' . $o['status'])) ?></span></td><td class="muted"><?= e($o['updated_at']) ?></td></tr><?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="6" class="muted"><?= e($sf->t('none')) ?></td></tr><?php endif; ?></tbody></table></div>
