<?php /** @var \Sysfera\Plugin $sf @var string $tab @var array $t @var ?array $wh @var array $orders @var array $items @var array $log */
include __DIR__ . '/_nav.php'; ?>
<div class="page-h"><h1><?= e($sf->t('transfer')) ?> <code><?= e(substr($t['uuid'], 0, 8)) ?></code> <span class="gi-st gi-<?= e($t['status']) ?>"><?= e($sf->t('tst_' . $t['status'])) ?></span></h1><a class="btn" href="<?= e($sf->url('/transfers')) ?>">← <?= e($sf->t('nav_transfers')) ?></a></div>
<div class="card"><p><b><?= e($sf->t('warehouse')) ?>:</b> <?= e($wh['name'] ?? '?') ?> · <b>UUID:</b> <code><?= e($t['uuid']) ?></code></p>
  <p><b><?= e($sf->t('checksum')) ?>:</b> <code class="gi-wrap"><?= e($t['checksum']) ?></code></p>
  <p class="muted"><?= e($sf->t('created')) ?> <?= e($t['created_at']) ?> · <?= e($t['created_by']) ?><?= $t['delivered_at'] ? ' · ' . e($sf->t('delivered')) . ' ' . e($t['delivered_at']) : '' ?><?= $t['confirmed_at'] ? ' · ' . e($sf->t('confirmed')) . ' ' . e($t['confirmed_at']) : '' ?></p></div>
<?php foreach ($orders as $o): $its = array_filter($items, fn($i) => $i['order_uuid'] === $o['uuid']); ?>
<div class="card flush"><div class="gi-oh"><a href="<?= e($sf->url('/orders/' . $o['uuid'])) ?>"><b>#<?= e($o['number']) ?></b></a> <?= e($o['customer_name']) ?> <span class="gi-st gi-<?= e($o['status']) ?>"><?= e($sf->t('st_' . $o['status'])) ?></span><span class="gi-sum"><?= e($o['total_rub']) ?> ₽</span></div>
<table class="tbl"><thead><tr><th><?= e($sf->t('col_barcode')) ?></th><th><?= e($sf->t('col_name')) ?></th><th><?= e($sf->t('col_qty')) ?></th><th><?= e($sf->t('col_received')) ?></th><th><?= e($sf->t('col_weight')) ?></th><th><?= e($sf->t('col_price')) ?></th><th><?= e($sf->t('status')) ?></th></tr></thead><tbody>
<?php foreach ($its as $i): ?><tr><td><code><?= e($i['barcode']) ?></code></td><td><?= e($i['name']) ?> <small class="muted"><?= e($i['sku']) ?></small></td><td><?= (int)$i['qty'] ?></td><td><?= (int)$i['received_qty'] ?></td><td><?= (int)$i['weight_g'] ?> g</td><td><?= e($i['price_rub']) ?> ₽</td><td><span class="gi-is gi-is-<?= e($i['state']) ?>"><?= e($sf->t('is_' . $i['state'])) ?></span></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php endforeach; ?>
<div class="card flush"><table class="tbl"><thead><tr><th><?= e($sf->t('date')) ?></th><th><?= e($sf->t('actor')) ?></th><th><?= e($sf->t('event')) ?></th></tr></thead><tbody>
<?php foreach ($log as $l): ?><tr><td class="muted"><?= e($l['ts']) ?></td><td><?= e($l['actor']) ?></td><td><?= e($l['action']) ?></td></tr><?php endforeach; ?></tbody></table></div>
