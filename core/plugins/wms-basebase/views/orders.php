<?php /** @var \Sysfera\Plugin $sf @var string $tab @var array $rows @var \SysferaBb\Sync $sync */
use SysferaWms\W;
include __DIR__ . '/_nav.php';
$statusBadge = fn(string $s) => '<span class="badge ' . (['pending' => 'warn', 'prepare' => 'info', 'shipped' => 'ok', 'on_delivery' => 'ok', 'delivered' => 'ok', 'returned' => 'bad', 'archived' => 'mute'][$s] ?? 'mute') . '">' . e($sf->t('st_' . $s)) . '</span>'; ?>
<div class="page-h"><h1><?= e($sf->t('nav_orders')) ?></h1>
  <?php if ($sync->ready()): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="pull"><button class="btn">↻ <?= e($sf->t('pull_now')) ?></button></form><?php endif; ?></div>
<div class="hint"><?= e($sf->t('orders_hint')) ?></div>

<?php if (!$rows): ?><div class="card empty"><h3><?= e($sf->t('no_orders')) ?></h3><p><?= e($sf->t('no_orders_hint')) ?></p></div>
<?php else: ?>
<div class="card flush"><table class="tbl">
  <thead><tr><th><?= e($sf->t('basebase_order')) ?></th><th><?= e($sf->t('product')) ?></th><th><?= e($sf->t('buyer')) ?></th><th class="num"><?= e($sf->t('amount')) ?></th><th><?= e($sf->t('wms_order')) ?></th><th><?= e($sf->t('status')) ?></th><th></th></tr></thead>
  <tbody><?php foreach ($rows as $r): $c = json_decode((string)$r['customer'], true) ?: []; ?>
    <tr><td><b>#<?= (int)$r['order_id'] ?></b><br><small class="muted"><?= e($sf->t('item')) ?> <?= (int)$r['item_id'] ?> · <?= e(W::date($r['created_at'])) ?></small></td>
      <td><?= e($r['title']) ?><br><small class="muted"><?= e(W::qty($r['qty'])) ?> × <?= e(W::money($r['price'])) ?></small></td>
      <td><?= e($c['name'] ?? '—') ?><br><small class="muted"><?= e(trim(($c['phone'] ?? '') . ' ' . ($c['delivery'] ?? ''))) ?></small></td>
      <td class="num"><?= e(W::money($r['qty'] * $r['price'])) ?><br><small class="muted"><?= e($sf->t('you_get')) ?> <?= e(W::money($r['seller_receives'])) ?></small></td>
      <td><?php if ($r['doc_id']): ?><a href="<?= e(url('/p/wms/docs/' . (int)$r['doc_id'])) ?>"><b><?= e($r['number']) ?></b></a><br><?= W::badge($sf, (string)$r['doc_status']) ?><?php else: ?><span class="muted">—</span><?php endif; ?></td>
      <td><?= $statusBadge((string)$r['status']) ?><?php if ($r['error']): ?><br><small class="bad-t"><?= e($why($r['error'])) ?></small><?php endif; ?></td>
      <td class="nowrap"><?php if ($r['error'] && !str_starts_with((string)$r['error'], 'bb:')): ?><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="do" value="retry"><input type="hidden" name="item" value="<?= (int)$r['item_id'] ?>"><button class="btn"><?= e($sf->t('retry')) ?></button></form><?php endif; ?>
        <?php if ($r['doc_id'] && in_array($r['status'], ['pending', 'prepare'], true)): ?><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="do" value="shipped"><input type="hidden" name="item" value="<?= (int)$r['item_id'] ?>"><button class="btn" data-confirm="<?= e($sf->t('confirm_shipped')) ?>"><?= e($sf->t('mark_shipped')) ?></button></form><?php endif; ?></td></tr>
  <?php endforeach; ?></tbody></table></div>
<?php endif; ?>
