<?php /** @var ?string $err */ ?>
<h1><?= e(t('auth.login')) ?></h1>
<?php if ($err): ?><div class="flash bad"><?= e(t($err)) ?></div><?php endif; ?>
<form method="post"><?= csrf_field() ?>
  <label class="fld"><span><?= e(t('auth.email')) ?></span><input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required autofocus autocomplete="username"></label>
  <label class="fld"><span><?= e(t('auth.password')) ?></span><input type="password" name="password" required autocomplete="current-password"></label>
  <button class="btn primary block"><?= e(t('auth.login')) ?></button>
</form>
<?php if (\Sysfera\App::get()->setting('registration', '0') === '1'): ?><p class="muted center" style="margin:16px 0 8px"><?= e(t('auth.no_account')) ?></p><a class="btn block" href="<?= e(url('/register')) ?>"><?= e(t('auth.register')) ?></a><?php endif; ?>
<?php if (\Sysfera\Landing::enabled(\Sysfera\App::get()->setting('landing', '1'))): ?><a class="btn block" style="margin-top:10px" href="<?= e(url('/')) ?>">← <?= e(t('auth.to_site')) ?></a><?php endif; ?>
