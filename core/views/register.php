<?php /** @var ?string $err @var bool $open */ ?>
<h1><?= e(t('auth.register')) ?></h1>
<?php if (!$open): ?>
  <div class="flash warn"><?= e(t('auth.reg_closed')) ?></div><p class="center"><a href="<?= e(url('/login')) ?>"><?= e(t('auth.login')) ?></a></p>
<?php else: ?>
  <?php if ($err): ?><div class="flash bad"><?= e(t($err)) ?></div><?php endif; ?>
  <form method="post"><?= csrf_field() ?>
    <label class="fld"><span><?= e(t('auth.name')) ?></span><input name="name" value="<?= e($_POST['name'] ?? '') ?>" required autofocus autocomplete="name"></label>
    <label class="fld"><span><?= e(t('auth.email')) ?></span><input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required autocomplete="username"></label>
    <label class="fld"><span><?= e(t('auth.password_min')) ?></span><input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
    <button class="btn primary block"><?= e(t('auth.register')) ?></button>
  </form>
  <p class="muted center"><?= e(t('auth.have_account')) ?> <a href="<?= e(url('/login')) ?>"><?= e(t('auth.login')) ?></a></p>
<?php endif; ?>
