<?php /** One contract card, laid out like the listing of Bablito.ru: picture block, title and meta, description, steps count and price. Vars: $k (a card from Svc), $archive (bool) */
use SysferaContracts\Svc;
$cur = Svc::symbol($k['currency']); $dead = $k['agreed_deadline'] ?: $k['deadline']; $showPrice = $k['agreed_price'] ?? $k['total_price'];
$href = e($sf->url(!empty($k['remote']) ? '/remote/' . $k['remote_id'] : '/view/' . $k['id'])); ?>
<article class="card ct-card ct-listing">
  <a class="ct-thumb" href="<?= $href ?>"><span><?= e($ctcat($k) !== '' ? $ctcat($k) : $sf->t('title')) ?></span></a>
  <div class="ct-body">
    <div class="row between"><h3><a href="<?= $href ?>"><?= e($k['title']) ?></a><?php if (!empty($k['new_offers']) && (int)$k['user_id'] === $me->uid): ?> <span class="ct-n" title="<?= e($sf->t('new_offers')) ?>"><?= (int)$k['new_offers'] ?></span><?php endif; ?></h3>
      <span class="ct-date"><?= e($ctd($k['created_at'])) ?></span></div>
    <div class="row between"><span class="ct-author"><?= !empty($k['remote']) ? e((string)$k['author_name']) : $ctuser((int)$k['user_id'], (string)$k['author_name']) . ' ' . $ctstars((float)$k['rating_avg'], (int)$k['rating_count']) ?></span>
      <span class="row"><?php if (!empty($k['remote'])): ?><span class="ct-pill info"><?= e($sf->t('remote_badge')) ?></span><?php elseif ($k['status'] !== 'published'): ?><span class="ct-pill <?= $ctst($k['status']) ?>"><?= e($sf->t('st_' . $k['status'])) ?></span><?php endif; ?><?php if ($ctcat($k) !== ''): ?><span class="ct-chip"><?= e($ctcat($k)) ?></span><?php endif; ?></span></div>
    <div class="ct-loc"><?= e((string)$k['location']) ?><?php if ($dead): ?><?= $k['location'] !== '' ? ' · ' : '' ?><?= e($sf->t('deadline')) ?>: <?= e($ctd($dead)) ?><?php endif; ?></div>
    <p class="ct-desc"><?= e(mb_substr((string)$k['description'], 0, 260)) ?></p>
    <?php if (in_array($k['status'], ['active', 'paused', 'fulfilled'], true)): $pct = $k['steps'] ? (int)round($k['accepted_steps'] / $k['steps'] * 100) : 0; ?><div class="ct-bar"><span style="width:<?= $pct ?>%"></span></div><div class="muted"><?= (int)$k['accepted_steps'] ?>/<?= (int)$k['steps'] ?></div><?php endif; ?>
    <div class="ct-foot"><div class="ct-steps"><b><?= (int)$k['steps'] ?></b><span><?= e($sf->t('steps')) ?></span></div>
      <div class="ct-pbox"><?php if ($k['prepay_required']): ?><span class="ct-pill info"><?= e($sf->t('prepay')) ?></span><?php endif; ?><div class="ct-price"><?= e($ctm($showPrice)) ?></div><div class="ct-cur"><?= e($k['currency']) ?></div></div></div>
    <div class="row between ct-small muted"><span><?php if (empty($k['remote'])): ?><?= (int)$k['views'] ?> <?= e($sf->t('views')) ?> · <?= (int)$k['offer_count'] ?> <?= e($sf->t('offers')) ?><?php endif; ?></span>
      <?php if (!empty($archive) && (int)$k['user_id'] === $me->uid): ?><form method="post" action="<?= e($sf->url('/c/' . $k['id'] . '/repeat')) ?>" class="inline" data-confirm="<?= e($sf->t('confirm_repeat')) ?>"><?= csrf_field() ?><button class="btn sm primary"><?= e($sf->t('repeat')) ?></button></form><?php endif; ?></div>
  </div>
</article>
