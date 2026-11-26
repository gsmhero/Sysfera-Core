<?php /** "Write a post" and the public profile on the user's Profile page. @var \Sysfera\Plugin $sf @var int $uid @var string $about */ ?>
<form class="card mb-quick" method="post" action="<?= e($sf->url('save')) ?>" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="id" value="0"><input type="hidden" name="status" value="published">
  <h3><?= e($sf->t('quick_title')) ?></h3>
  <label class="fld"><span><?= e($sf->t('f_title')) ?></span><input name="title" required maxlength="255"></label>
  <label class="fld"><span><?= e($sf->t('f_body')) ?></span><textarea name="body" rows="5" maxlength="20000" placeholder="<?= e($sf->t('f_body_ph')) ?>"></textarea></label>
  <label class="fld"><span><?= e($sf->t('f_cover')) ?></span><input type="file" name="cover" accept="image/jpeg,image/png,image/webp,image/gif"></label>
  <label class="fld"><span><?= e($sf->t('f_scope')) ?></span><select name="scope"><option value="global"><?= e($sf->t('scope_global')) ?></option><option value="local"><?= e($sf->t('scope_local')) ?></option></select></label>
  <div class="row"><button class="btn primary"><?= e($sf->t('publish')) ?></button><a class="btn" href="<?= e($sf->url('edit')) ?>"><?= e($sf->t('with_products')) ?></a><a class="btn" href="<?= e($sf->url('?f=mine')) ?>"><?= e($sf->t('f_mine')) ?></a></div>
</form>
<form class="card" method="post" action="<?= e(url('/profile')) ?>"><?= csrf_field() ?><input type="hidden" name="do" value="x_myblog_about">
  <h3><?= e($sf->t('public_profile')) ?></h3>
  <label class="fld"><span><?= e($sf->t('about')) ?></span><textarea name="about" rows="4" maxlength="600"><?= e($about) ?></textarea></label>
  <div class="row"><button class="btn primary"><?= e($sf->t('save')) ?></button><a class="btn" href="<?= e($sf->url('u/' . $uid)) ?>"><?= e($sf->t('view_profile')) ?></a></div>
  <p class="muted sm"><?= e($sf->t('public_hint')) ?></p>
</form>
