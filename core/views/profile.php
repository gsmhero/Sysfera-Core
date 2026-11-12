<?php /** @var array $u */ ?>
<div class="page-h"><h1><?= e(t('nav.profile')) ?></h1></div>
<div class="cols">
  <form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="do" value="account"><h3><?= e(t('profile.account')) ?></h3>
    <label class="fld"><span><?= e(t('auth.name')) ?></span><input name="name" value="<?= e($u['name']) ?>" required></label>
    <label class="fld"><span><?= e(t('auth.email')) ?></span><input type="email" name="email" value="<?= e($u['email']) ?>" required></label>
    <label class="fld"><span><?= e(t('profile.language')) ?></span><select name="lang"><?php foreach (\Sysfera\I18n::languages() as $c => $n): ?><option value="<?= e($c) ?>" <?= ($u['lang'] ?? \Sysfera\I18n::lang()) === $c ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select></label>
    <button class="btn primary"><?= e(t('common.save')) ?></button></form>
  <form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="do" value="password"><h3><?= e(t('profile.password')) ?></h3>
    <label class="fld"><span><?= e(t('profile.current')) ?></span><input type="password" name="old" required autocomplete="current-password"></label>
    <label class="fld"><span><?= e(t('profile.new')) ?></span><input type="password" name="new" required minlength="8" autocomplete="new-password"></label>
    <button class="btn primary"><?= e(t('common.save')) ?></button></form>
</div>
