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
  <?php if (!empty($babOn) && $me->uid): ?><form method="post" action="<?= e($sf->url('/bablito/go')) ?>"><?= csrf_field() ?><input type="hidden" name="to" value="<?= (int)$k['remote_id'] ?>"><button class="btn primary"><?= e($sf->t('remote_open')) ?></button></form><p class="muted"><?= e($sf->t('remote_signin_help')) ?></p>
  <?php elseif (preg_match('#^https://#', $url)): ?><p><a class="btn primary" href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer"><?= e($sf->t('remote_open')) ?></a></p><?php endif; ?></div>