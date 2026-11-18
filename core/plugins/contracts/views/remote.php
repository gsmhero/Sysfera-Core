<?php /** @var \Sysfera\Plugin $sf @var array $k a copy of a contract published on Bablito.ru (see Svc::remote()) */
use SysferaContracts\Svc;
include __DIR__ . '/_nav.php'; $cur = Svc::symbol($k['currency']); $url = (string)$k['remote_url']; ?>
<div class="page-h row between"><h1><?= e($k['title']) ?></h1><span class="ct-st info"><?= e($sf->t('remote_badge')) ?></span></div>
<div class="card"><div class="ct-meta"><span><?= e($ctd($k['created_at'])) ?></span><?php if ($ctcat($k) !== ''): ?><span class="ct-chip"><?= e($ctcat($k)) ?></span><?php endif; ?><?php if ($k['location'] !== ''): ?><span><?= e($k['location']) ?></span><?php endif; ?>
    <span><?= e($sf->t('remote_author')) ?>: <?= e((string)$k['author_name']) ?></span><?php if ($k['deadline']): ?><span><?= e($sf->t('deadline')) ?>: <?= e($ctd($k['deadline'])) ?></span><?php endif; ?></div>
  <p style="white-space:pre-wrap"><?= e((string)$k['description']) ?></p>
  <?php if ($k['step_list']): ?><h3><?= e($sf->t('steps')) ?></h3><table class="tbl"><thead><tr><th>#</th><th><?= e($sf->t('step_name')) ?></th><th><?= e($sf->t('price')) ?></th><th><?= e($sf->t('days')) ?></th></tr></thead><tbody>
    <?php foreach ($k['step_list'] as $i => $t): ?><tr><td><?= $i + 1 ?></td><td><b><?= e((string)$t['name']) ?></b><?php if (trim((string)$t['description']) !== ''): ?><br><span class="muted"><?= e((string)$t['description']) ?></span><?php endif; ?></td><td><?= e($ctm($t['price'])) ?> <?= e($cur) ?></td><td><?= (int)$t['days'] ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
  <p class="ct-price"><?= e($ctm($k['total_price'])) ?> <small><?= e($k['currency']) ?> · <?= (int)$k['total_days'] ?> <?= e($sf->t('days')) ?><?= $k['prepay_required'] ? ' · ' . e($sf->t('prepay')) : '' ?></small></p>
  <p class="muted"><?= e($sf->t('remote_help')) ?></p>
  <?php if (!empty($babOn) && $me->uid): $mine = $mine ?? null; ?>
    <?php if ($mine): ?><div class="ct-offer-mine"><b><?= e($sf->t('offer_mine')) ?>:</b> <?= e($ctm($mine['price'])) ?> <?= e($cur) ?> · <?= (int)$mine['days'] ?> <?= e($sf->t('days')) ?> · <?= e($sf->t('offer_status_' . $mine['status'])) ?></div>
    <?php else: ?>
    <form method="post" action="<?= e($sf->url('/remote/' . (int)$k['remote_id'] . '/offer')) ?>" class="ct-offerform"><?= csrf_field() ?>
      <button class="btn primary" name="mode" value="accept"><?= e($sf->t('accept_contract')) ?></button>
      <details class="ct-counter"><summary class="btn"><?= e($sf->t('counteroffer')) ?></summary>
        <p class="muted"><?= e($sf->t('offer_counter_help')) ?></p>
        <?php if ($k['step_list']): ?><table class="tbl"><thead><tr><th>#</th><th><?= e($sf->t('step_name')) ?></th><th><?= e($sf->t('price')) ?></th><th><?= e($sf->t('days')) ?></th><th><?= e($sf->t('unnecessary')) ?></th></tr></thead><tbody>
          <?php foreach ($k['step_list'] as $i => $t): $n = $i + 1; ?><tr><td><?= $n ?></td><td><?= e((string)$t['name']) ?></td>
            <td><input type="text" inputmode="decimal" name="items[<?= $n ?>][price]" value="<?= e((string)(float)$t['price']) ?>" size="9"></td>
            <td><input type="number" min="0" max="3650" name="items[<?= $n ?>][days]" value="<?= (int)$t['days'] ?>" style="width:5.5em"></td>
            <td><input type="checkbox" name="items[<?= $n ?>][excluded]" value="1"></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
        <label><?= e($sf->t('payment')) ?> <select name="payment_type"><option value="finish"><?= e($sf->t('pay_finish')) ?></option><option value="step"<?= $k['payment_type'] === 'step' ? ' selected' : '' ?>><?= e($sf->t('pay_step')) ?></option></select></label>
        <label><?= e($sf->t('notes')) ?><textarea name="notes" rows="3" maxlength="4000"></textarea></label>
        <button class="btn primary" name="mode" value="counter"><?= e($sf->t('send_offer')) ?></button>
      </details></form>
    <p class="muted"><?= e($sf->t('offer_help')) ?></p>
    <?php endif; ?>
  <?php elseif (preg_match('#^https://#', $url)): ?><p><a class="btn primary" href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer"><?= e($sf->t('remote_open')) ?></a></p><?php endif; ?></div>
