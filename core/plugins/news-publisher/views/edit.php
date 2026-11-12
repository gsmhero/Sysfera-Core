<?php /** @var \Sysfera\Plugin $sf @var array $p */ ?>
<div class="page-h"><h1><?= e($sf->t($p['id'] ? 'edit_post' : 'new_post')) ?></h1><a class="btn" href="<?= e($sf->url('/')) ?>">← <?= e($sf->t('title')) ?></a></div>
<form class="card" method="post" action="<?= e($sf->url('/save')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
  <div class="cols">
    <label class="fld"><span><?= e($sf->t('col_type')) ?></span><select name="type"><?php foreach (['news', 'update', 'warning'] as $t): ?><option value="<?= $t ?>" <?= $p['type'] === $t ? 'selected' : '' ?>><?= e($sf->t('type_' . $t)) ?></option><?php endforeach; ?></select></label>
    <label class="fld"><span><?= e($sf->t('col_date')) ?></span><input type="date" name="post_date" value="<?= e($p['post_date']) ?>" required></label>
    <label class="fld"><span><?= e($sf->t('expires')) ?></span><input type="date" name="expires" value="<?= e((string)$p['expires']) ?>"></label>
  </div>
  <p class="muted sm"><?= e($sf->t('type_hint')) ?></p>

  <div class="cols">
    <div><h3>Русский</h3>
      <label class="fld"><span><?= e($sf->t('f_title')) ?></span><input name="title_ru" value="<?= e($p['title_ru']) ?>" maxlength="160"></label>
      <label class="fld"><span><?= e($sf->t('f_body')) ?></span><textarea name="body_ru" rows="6" maxlength="1500"><?= e((string)$p['body_ru']) ?></textarea></label></div>
    <div><h3>English</h3>
      <label class="fld"><span><?= e($sf->t('f_title')) ?></span><input name="title_en" value="<?= e($p['title_en']) ?>" maxlength="160"></label>
      <label class="fld"><span><?= e($sf->t('f_body')) ?></span><textarea name="body_en" rows="6" maxlength="1500"><?= e((string)$p['body_en']) ?></textarea></label></div>
  </div>
  <p class="muted sm"><?= e($sf->t('lang_hint')) ?></p>

  <label class="fld"><span><?= e($sf->t('f_url')) ?></span><input name="url" value="<?= e((string)$p['url']) ?>" placeholder="https://" maxlength="500"></label>
  <div class="row">
    <label class="chk"><input type="checkbox" name="published" value="1" <?= $p['published'] ? 'checked' : '' ?>> <?= e($sf->t('f_published')) ?></label>
    <label class="chk"><input type="checkbox" name="pinned" value="1" <?= $p['pinned'] ? 'checked' : '' ?>> <?= e($sf->t('f_pinned')) ?></label>
  </div>
  <details style="margin:10px 0"><summary class="muted sm" style="cursor:pointer"><?= e($sf->t('advanced')) ?></summary>
    <label class="fld" style="margin-top:8px"><span><?= e($sf->t('f_slug')) ?></span><input name="slug" value="<?= e($p['slug']) ?>" maxlength="80" placeholder="<?= e($sf->t('f_slug_ph')) ?>"></label></details>
  <button class="btn primary"><?= e($sf->t('save')) ?></button>
</form>
