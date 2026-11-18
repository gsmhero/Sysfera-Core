<?php /** One contract card. Vars: $k (a card from Svc), $archive (bool) */
use SysferaContracts\Svc;
$cur = Svc::symbol($k['currency']); $dead = $k['agreed_deadline'] ?: $k['deadline']; $showPrice = $k['agreed_price'] ?? $k['total_price']; ?>
<article class="card ct-card">
  <div class="row between"><h3><a href="<?= e($sf->url(!empty($k['remote']) ? '/remote/' . $k['remote_id'] : '/view/' . $k['id'])) ?>"><?= e($k['title']) ?></a><?php if (!empty($k['new_offers']) && (int)$k['user_id'] === $me->uid): ?> <span class="ct-n" title="<?= e($sf->t('new_offers')) ?>"><?= (int)$k['new_offers'] ?></span><?php endif; ?></h3>
    <?php if (!empty($k['remote'])): ?><span class="ct-st info"><?= e($sf->t('remote_badge')) ?></span><?php else: ?><span class="ct-st <?= $ctst($k['status']) ?>"><?= e($sf->t('st_' . $k['status'])) ?></span><?php endif; ?></div>
  <div class="ct-meta"><span><?= e($ctd($k['created_at'])) ?></span><?php if ($ctcat($k) !== ''): ?><span class="ct-chip"><?= e($ctcat($k)) ?></span><?php endif; ?><?php if ($k['location'] !== ''): ?><span><?= e($k['location']) ?></span><?php endif; ?>
    <span><?= !empty($k['remote']) ? e((string)$k['author_name']) : $ctuser((int)$k['user_id'], (string)$k['author_name']) . ' ' . $ctstars((float)$k['rating_avg'], (int)$k['rating_count']) ?></span><?php if ($dead): ?><span><?= e($sf->t('deadline')) ?>: <?= e($ctd($dead)) ?></span><?php endif; ?></div>
  <p class="ct-desc"><?= e(mb_substr((string)$k['description'], 0, 240)) ?></p>
  <?php if (in_array($k['status'], ['active', 'paused', 'fulfilled'], true)): $pct = $k['steps'] ? (int)round($k['accepted_steps'] / $k['steps'] * 100) : 0; ?><div class="ct-bar"><span style="width:<?= $pct ?>%"></span></div><div class="muted"><?= (int)$k['accepted_steps'] ?>/<?= (int)$k['steps'] ?></div><?php endif; ?>
  <div class="ct-foot"><span class="ct-price"><?= e($ctm($showPrice)) ?> <small><?= e($k['currency']) ?> · <?= (int)$k['steps'] ?> <?= e($sf->t('steps')) ?><?= $k['prepay_required'] ? ' · ' . e($sf->t('prepay')) : '' ?></small></span>
    <?php if (empty($k['remote'])): ?><span class="muted"><?= (int)$k['views'] ?> <?= e($sf->t('views')) ?> · <?= (int)$k['offer_count'] ?> <?= e($sf->t('offers')) ?></span><?php endif; ?>
    <?php if (!empty($archive) && (int)$k['user_id'] === $me->uid): ?><form method="post" action="<?= e($sf->url('/c/' . $k['id'] . '/repeat')) ?>" class="inline" data-confirm="<?= e($sf->t('confirm_repeat')) ?>"><?= csrf_field() ?><button class="btn sm primary"><?= e($sf->t('repeat')) ?></button></form><?php endif; ?></div>
</article>
