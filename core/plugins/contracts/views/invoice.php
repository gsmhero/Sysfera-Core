<?php /** @var \Sysfera\Plugin $sf @var array $invoice @var array $contract @var ?array $step @var array $payer @var array $payee @var bool $canPay @var bool $canConfirm @var bool $canNumber */
include __DIR__ . '/_nav.php'; $i = $invoice; ?>
<div class="page-h row between"><h1><?= e($sf->t('invoice')) ?> <?= e($i['number']) ?></h1><span class="row ct-noprint"><button class="btn" onclick="window.print()"><?= e($sf->t('print')) ?></button><a class="btn" href="<?= e($sf->url('/invoices')) ?>"><?= e($sf->t('nav_invoices')) ?></a></span></div>
<div class="card ct-print">
  <table class="ct-tbl"><tr><th><?= e($sf->t('number')) ?></th><td><?= e($i['number']) ?></td></tr><tr><th><?= e($sf->t('date')) ?></th><td><?= e($ctd($i['created_at'])) ?></td></tr>
    <tr><th><?= e($sf->t('from')) ?></th><td><?= e($payee['name']) ?> <small class="muted">(<?= e($sf->t('contractor')) ?>)</small></td></tr><tr><th><?= e($sf->t('to')) ?></th><td><?= e($payer['name']) ?> <small class="muted">(<?= e($sf->t('author')) ?>)</small></td></tr>
    <tr><th><?= e($sf->t('contract')) ?></th><td><a href="<?= e($sf->url('/view/' . $contract['id'])) ?>"><?= e($contract['title']) ?></a></td></tr>
    <tr><th><?= e($sf->t('step')) ?></th><td><?= e($step ? ($step['position'] + 1) . '. ' . $step['name'] : $sf->t('whole_contract')) ?></td></tr>
    <tr><th><?= e($sf->t('amount')) ?></th><td><b><?= e($ctm($i['amount'])) ?> <?= e($i['currency']) ?></b></td></tr>
    <tr><th><?= e($sf->t('status')) ?></th><td><span class="ct-st <?= $ctst($i['status']) ?>"><?= e($sf->t('inv_' . $i['status'])) ?></span><?= $i['paid_at'] ? ' ' . e($ctdt($i['paid_at'])) : '' ?></td></tr></table>
  <p class="muted"><?= e($sf->t('inv_note')) ?></p></div>
<div class="row ct-noprint"><?php if ($canPay): ?><form method="post" action="<?= e($sf->url('/i/' . $i['id'] . '/pay')) ?>" data-confirm="<?= e($sf->t('confirm_paid')) ?>"><?= csrf_field() ?><input type="hidden" name="back" value="invoice/<?= (int)$i['id'] ?>"><button class="btn primary"><?= e($sf->t('i_paid')) ?></button></form><?php endif; ?>
  <?php if ($canConfirm): ?><form method="post" action="<?= e($sf->url('/i/' . $i['id'] . '/confirm')) ?>"><?= csrf_field() ?><input type="hidden" name="back" value="invoice/<?= (int)$i['id'] ?>"><button class="btn primary"><?= e($sf->t('confirm_payment')) ?></button></form><?php endif; ?>
  <?php if ($canNumber): ?><form method="post" action="<?= e($sf->url('/i/' . $i['id'] . '/number')) ?>" class="row"><?= csrf_field() ?><input name="number" value="<?= e($i['number']) ?>" maxlength="40" required><button class="btn"><?= e($sf->t('save_number')) ?></button></form><?php endif; ?></div>
