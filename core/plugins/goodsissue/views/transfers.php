<?php /** @var \Sysfera\Plugin $sf @var string $tab @var array $rows @var array $whs */
include __DIR__ . '/_nav.php'; ?>
<div class="page-h"><h1><?= e($sf->t('nav_transfers')) ?></h1></div>
<div class="card">
  <h3><?= e($sf->t('new_transfer')) ?></h3>
  <?php if (!$whs): ?><p class="muted"><?= e($sf->t('need_wh')) ?> <a href="<?= e($sf->url('/warehouses')) ?>"><?= e($sf->t('nav_warehouses')) ?></a></p><?php else: ?>
  <form method="post"><?= csrf_field() ?>
    <label class="fld"><span><?= e($sf->t('warehouse')) ?></span><select name="warehouse_id"><?php foreach ($whs as $w): if (!$w['active']) continue; ?><option value="<?= (int)$w['id'] ?>"><?= e($w['name']) ?> (<?= e($w['code']) ?>)</option><?php endforeach; ?></select></label>
    <label class="fld"><span><?= e($sf->t('lines')) ?></span><textarea name="lines" rows="6" class="gi-mono" placeholder="A-2001;Ivan Petrov;+79990000001;ivan@example.com;SKU-1;Kettle;1;1200;;1500.00"></textarea></label>
    <p class="muted"><?= e($sf->t('lines_hint')) ?></p>
    <div class="row"><button class="btn primary" name="do" value="create"><?= e($sf->t('create_transfer')) ?></button><button class="btn" name="do" value="demo" formnovalidate><?= e($sf->t('load_demo')) ?></button></div>
  </form><?php endif; ?>
</div>
<?php if ($rows): ?><div class="card flush"><table class="tbl"><thead><tr><th>UUID</th><th><?= e($sf->t('warehouse')) ?></th><th><?= e($sf->t('status')) ?></th><th><?= e($sf->t('orders_n')) ?></th><th><?= e($sf->t('date')) ?></th></tr></thead><tbody>
<?php foreach ($rows as $t): ?><tr><td><a href="<?= e($sf->url('/transfers/' . $t['uuid'])) ?>"><code><?= e(substr($t['uuid'], 0, 8)) ?></code></a></td><td><?= e($t['wname'] ?? '') ?></td><td><span class="gi-st gi-<?= e($t['status']) ?>"><?= e($sf->t('tst_' . $t['status'])) ?></span></td><td><?= (int)$t['orders'] ?></td><td class="muted"><?= e($t['created_at']) ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?>
