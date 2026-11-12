<?php /** @var array $notes @var \Sysfera\Plugin $sf */ ?>
<div class="page-h"><h1><?= e($sf->t('title')) ?></h1></div>
<div class="hint"><?= e($sf->t('hint')) ?></div>
<form class="card" method="post"><?= csrf_field() ?>
  <label class="fld"><span><?= e($sf->t('client')) ?></span><input name="client" maxlength="190"></label>
  <label class="fld"><span><?= e($sf->t('note')) ?></span><textarea name="text" rows="3" required></textarea></label>
  <button class="btn primary"><?= e($sf->t('add')) ?></button></form>
<div class="list"><?php foreach ($notes as $n): ?>
  <form class="card item" method="post"><?= csrf_field() ?><div class="grow"><?php if ($n['client']): ?><b><?= e($n['client']) ?></b> <?php endif; ?><small class="muted"><?= e(ago($n['created_at'])) ?></small><p><?= nl2br(e($n['body'])) ?></p></div>
    <button class="btn danger" name="delete" value="<?= (int)$n['id'] ?>" data-confirm="<?= e($sf->t('confirm')) ?>"><?= e(t('common.delete')) ?></button></form>
<?php endforeach; ?></div>
