<?php /** @var \Sysfera\Plugin $sf @var string $tab @var bool $manage @var array $reg @var string $week @var int $wid @var array $whs @var array $names @var array $pending @var array $approved @var array $weekly */
include __DIR__ . '/_nav.php'; $q = '?week=' . urlencode($week) . ($wid ? '&warehouse_id=' . $wid : ''); ?>
<div class="page-h"><h1><?= e($sf->t('nav_returns')) ?></h1><a class="btn" href="<?= e($sf->url('/returns/csv' . $q)) ?>"><?= e($sf->t('ret_csv')) ?></a></div>
<form method="get" class="card row"><label><?= e($sf->t('ret_week')) ?> <input type="date" name="week" value="<?= e($week) ?>"></label>
  <select name="warehouse_id"><option value="0"><?= e($sf->t('all_whs')) ?></option><?php foreach ($whs as $w): ?><option value="<?= (int)$w['id'] ?>" <?= $wid === (int)$w['id'] ? 'selected' : '' ?>><?= e($w['name']) ?></option><?php endforeach; ?></select>
  <button class="btn"><?= e($sf->t('show')) ?></button></form>

<h3><?= e($sf->t('ret_pending')) ?> (<?= count($pending) ?>)</h3>
<?php if (!$pending): ?><div class="card empty"><p class="muted"><?= e($sf->t('none')) ?></p></div><?php endif; ?>
<?php foreach ($pending as $r): $d = $r['data']; ?>
<div class="card"><p><a href="<?= e($sf->url('/orders/' . ($d['order_uuid'] ?? ''))) ?>"><b>#<?= e($d['number'] ?? '') ?></b></a> · <?= e($d['customer'] ?? '') ?> · <span class="muted"><?= e($names[(int)$r['warehouse_id']] ?? '') ?> · <?= e($d['requested_at'] ?? '') ?> · <?= e($d['requested_by'] ?? '') ?></span>
  <span class="gi-st"><?= e($sf->t('src_' . ($d['source'] ?? 'customer'))) ?></span> <span class="gi-sev gi-sev-<?= ($d['condition'] ?? 'ok') === 'damaged' ? 'high' : (($d['condition'] ?? 'ok') === 'opened' ? 'medium' : 'low') ?>"><?= e($sf->t('cond_' . ($d['condition'] ?? 'ok'))) ?></span></p>
  <p><b><?= e($sf->t('ret_reason')) ?>:</b> <?= e($d['reason'] ?? '') ?></p>
  <p class="muted"><?php foreach ($d['items'] ?? [] as $i): ?><?= e($i['name']) ?> × <?= (int)$i['qty'] ?> (<?= e($i['price_rub']) ?> ₽); <?php endforeach; ?></p>
  <?php if (!empty($d['photo_sha256'])): ?><p><a href="<?= e($sf->url('/evidence/' . $r['ref'] . '/photo')) ?>" target="_blank"><?= e($sf->t('ret_photo')) ?></a></p><?php endif; ?>
  <?php if ($manage): ?><form method="post" class="row"><?= csrf_field() ?><input type="hidden" name="ref" value="<?= e($r['ref']) ?>"><input type="hidden" name="week" value="<?= e($week) ?>">
    <label><?= e($sf->t('ret_refund')) ?> <input name="refund_rub" value="<?= e($d['refund_rub'] ?? '') ?>" inputmode="decimal" size="10"> ₽</label><input name="note" placeholder="<?= e($sf->t('ret_note')) ?>" maxlength="300">
    <button class="btn primary" name="do" value="approve"><?= e($sf->t('ret_approve')) ?></button><button class="btn" name="do" value="reject"><?= e($sf->t('ret_reject')) ?></button></form><?php endif; ?></div>
<?php endforeach; ?>

<?php if ($approved): ?><h3><?= e($sf->t('ret_to_pay')) ?> (<?= count($approved) ?>)</h3>
<div class="card flush"><table class="tbl"><tbody><?php foreach ($approved as $r): $d = $r['data']; ?><tr><td><a href="<?= e($sf->url('/orders/' . ($d['order_uuid'] ?? ''))) ?>">#<?= e($d['number'] ?? '') ?></a></td><td><?= e($d['customer'] ?? '') ?></td><td><b><?= e($d['refund_rub'] ?? '') ?> ₽</b></td><td class="muted"><?= e($d['decided_by'] ?? '') ?> <?= e($d['decided_at'] ?? '') ?></td>
  <td><?php if ($manage): ?><form method="post" class="row"><?= csrf_field() ?><input type="hidden" name="ref" value="<?= e($r['ref']) ?>"><input type="hidden" name="week" value="<?= e($week) ?>"><input name="note" placeholder="<?= e($sf->t('ret_note')) ?>" maxlength="300"><button class="btn sm" name="do" value="refunded"><?= e($sf->t('ret_paid')) ?></button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>

<h3><?= e($sf->t('ret_registry')) ?>: <?= e($reg['from']) ?> — <?= e($reg['to']) ?></h3>
<p class="muted"><?= e($sf->t('ret_count')) ?>: <b><?= (int)$reg['count'] ?></b> · <?= e($sf->t('ret_refund_sum')) ?>: <b><?= e($reg['refund_rub']) ?> ₽</b><?php foreach ($reg['by_status'] as $s => $n): ?> · <?= e($sf->t('rs_' . $s)) ?>: <?= (int)$n ?><?php endforeach; ?></p>
<div class="card flush"><table class="tbl"><thead><tr><th><?= e($sf->t('col_day')) ?></th><th><?= e($sf->t('col_order')) ?></th><th><?= e($sf->t('col_customer')) ?></th><th><?= e($sf->t('ret_reason')) ?></th><th><?= e($sf->t('ret_cond')) ?></th><th><?= e($sf->t('col_pieces')) ?></th><th><?= e($sf->t('ret_refund')) ?></th><th><?= e($sf->t('status')) ?></th><th><?= e($sf->t('ret_goods_back')) ?></th></tr></thead><tbody>
<?php foreach ($reg['rows'] as $r): ?><tr><td><?= e($r['day']) ?></td><td>#<?= e($r['number']) ?></td><td><?= e($r['customer']) ?></td><td><?= e($r['reason']) ?><br><small class="muted"><?= e($sf->t('src_' . $r['source'])) ?></small></td><td><?= e($sf->t('cond_' . $r['condition'])) ?></td><td><?= (int)$r['pieces'] ?></td><td><?= e($r['refund_rub']) ?> ₽</td><td><span class="gi-st"><?= e($sf->t('rs_' . $r['status'])) ?></span></td><td><?= $r['received'] ? '✓' : '—' ?></td></tr><?php endforeach; ?>
<?php if (!$reg['rows']): ?><tr><td colspan="9" class="muted"><?= e($sf->t('none')) ?></td></tr><?php endif; ?></tbody></table></div>
<?php if ($weekly): ?><div class="card"><h3><?= e($sf->t('ret_weekly')) ?></h3><?php foreach ($weekly as $w): [$wi, $mon] = explode(':', $w['ref']) + [1 => '']; ?><a class="btn sm" href="<?= e($sf->url('/returns?week=' . $mon . '&warehouse_id=' . (int)$wi)) ?>"><?= e($names[(int)$wi] ?? $wi) ?> · <?= e($mon) ?></a> <?php endforeach; ?></div><?php endif; ?>
