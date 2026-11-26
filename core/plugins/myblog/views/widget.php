<?php /** Dashboard widget. @var \Sysfera\Plugin $sf @var array $items @var bool $shared  false = only this site (hub not connected) */ ?>
<div class="row between"><b><?= e($sf->t($shared ? 'w_title' : 'w_title_local')) ?></b><a class="sm" href="<?= e($sf->url('/')) ?>"><?= e($sf->t('open')) ?> →</a></div>
<?php if (!$items): ?><p class="muted sm"><?= e($sf->t('none')) ?></p>
<?php else: ?><ul class="mb-wlist"><?php foreach ($items as $c): ?>
  <li><b><?= e($c['title']) ?></b><span><?= e(\SysferaBlog\Text::excerpt($c['excerpt'], 130)) ?></span>
    <small class="muted"><?= e($c['author']) ?> · <?= e($c['date']) ?><?php if (($c['domain'] ?? '') !== ''): ?> · <?= e($c['domain']) ?><?php endif; ?></small>
    <a href="<?= e($c['url']) ?>"><?= e($sf->t('read_more')) ?></a></li>
<?php endforeach; ?></ul><?php endif; ?>
