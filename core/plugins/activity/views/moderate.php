<?php /** @var \Sysfera\Plugin $sf @var array $rows */ ?>
<div class="page-h"><h1><?= e($sf->t('moderate')) ?></h1><a class="btn" href="<?= e($sf->url('/')) ?>">← <?= e($sf->t('title')) ?></a></div>
<p class="hint"><?= e($sf->t('moderate_hint', ['n' => \SysferaMsg\Activity::HIDE_AFTER_REPORTS])) ?></p>
<?php if (!$rows): ?><div class="card empty"><h3><?= e($sf->t('mod_none')) ?></h3></div><?php endif; ?>
<?php foreach ($rows as $p): ?><article class="card">
  <div class="row between"><b><?= e($p['title']) ?></b><span><?= $p['hidden'] ? '<span class="badge bad">' . e($sf->t('hidden')) . '</span>' : '' ?> <span class="badge warn"><?= (int)$p['reports'] ?> ⚑</span></span></div>
  <p><?= nl2br(e($p['body'])) ?></p><p class="muted sm"><?= e($p['name']) ?> · <?= e($p['sid']) ?> · <?= e($p['created']) ?></p>
  <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
    <?php if ($p['hidden']): ?><button class="btn" name="do" value="show"><?= e($sf->t('show')) ?></button><?php else: ?><button class="btn" name="do" value="hide"><?= e($sf->t('hide')) ?></button><?php endif; ?>
    <button class="btn danger" name="do" value="remove" data-confirm="<?= e($sf->t('confirm_delete')) ?>"><?= e($sf->t('delete')) ?></button></form></article><?php endforeach; ?>
