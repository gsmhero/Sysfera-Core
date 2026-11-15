<?php /** @var \Sysfera\Plugin $sf @var array $cats */
$tab = 'categories'; include __DIR__ . '/_nav.php'; ?>
<div class="page-h"><h1><?= e($sf->t('categories')) ?></h1></div>
<div class="hint"><?= e($sf->t('categories_hint')) ?></div>
<div class="list">
<?php foreach ($cats as $c): ?>
  <form class="card item" method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><div class="row">
    <label class="fld inline"><span>EN</span><input name="name" value="<?= e($c['name']) ?>" required maxlength="100"></label>
    <label class="fld inline"><span>RU</span><input name="name_ru" value="<?= e($c['name_ru']) ?>" maxlength="100"></label>
    <label class="fld inline"><span><?= e($sf->t('color')) ?></span><input type="color" name="color" value="<?= e($c['color']) ?>"></label>
    <label class="fld inline"><span>№</span><input name="sort_order" value="<?= (int)$c['sort_order'] ?>" size="3"></label>
    <button class="btn primary" name="do" value="save"><?= e($sf->t('save')) ?></button>
    <button class="btn danger" name="do" value="delete" data-confirm="<?= e($sf->t('confirm_delete_cat')) ?>">✕</button></div></form>
<?php endforeach; ?>
</div>
<form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="do" value="add"><h3><?= e($sf->t('add_category')) ?></h3><div class="row">
  <label class="fld inline"><span>EN *</span><input name="name" required maxlength="100"></label>
  <label class="fld inline"><span>RU</span><input name="name_ru" maxlength="100"></label>
  <label class="fld inline"><span><?= e($sf->t('color')) ?></span><input type="color" name="color" value="#6b7280"></label>
  <label class="fld inline"><span>№</span><input name="sort_order" value="0" size="3"></label>
  <button class="btn primary"><?= e($sf->t('add')) ?></button></div></form>
