<?php /** @var array $caps @var array $langs */
$app = \Sysfera\App::get(); $defaults = json_decode($app->setting('default_caps', '[]'), true) ?: []; ?>
<div class="page-h"><h1><?= e(t('nav.settings')) ?></h1></div>
<form class="card" method="post"><?= csrf_field() ?>
  <label class="fld"><span><?= e(t('settings.site_name')) ?></span><input name="site_name" value="<?= e($app->setting('site_name', 'Sysfera')) ?>" maxlength="80"></label>
  <div class="cols">
    <label class="fld"><span><?= e(t('settings.default_lang')) ?></span><select name="default_lang"><?php foreach ($langs as $c => $n): ?><option value="<?= e($c) ?>" <?= $app->setting('default_lang', 'en') === $c ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select></label>
    <label class="fld"><span><?= e(t('settings.timezone')) ?></span><select name="timezone"><?php foreach (timezone_identifiers_list() as $tz): ?><option <?= $app->setting('timezone', 'UTC') === $tz ? 'selected' : '' ?>><?= e($tz) ?></option><?php endforeach; ?></select></label>
  </div>
  <h3><?= e(t('settings.registration')) ?></h3>
  <p class="muted sm"><?= e(t('settings.registration_hint')) ?></p>
  <label class="chk"><input type="checkbox" name="registration" value="1" <?= $app->setting('registration', '0') === '1' ? 'checked' : '' ?>> <?= e(t('settings.registration_open')) ?></label>
  <?php if ($caps): ?><div class="caps"><span class="muted sm"><?= e(t('settings.default_caps')) ?></span><?php foreach ($caps as $c => $label): ?><label class="chk"><input type="checkbox" name="default_caps[]" value="<?= e($c) ?>" <?= in_array($c, $defaults, true) ? 'checked' : '' ?>> <?= e($label) ?></label><?php endforeach; ?></div><?php endif; ?>
  <h3><?= e(t('settings.updates')) ?></h3>
  <label class="fld"><span><?= e(t('settings.update_url')) ?></span><input name="update_url" value="<?= e($app->setting('update_url', '')) ?>" placeholder="<?= e(\Sysfera\Updater::DEFAULT_URL) ?>"></label>
  <p class="muted sm"><?= e(t('settings.update_hint')) ?></p>
  <h3><?= e(t('settings.demo')) ?></h3>
  <p class="muted sm"><?= e(t('settings.demo_hint')) ?></p>
  <label class="chk"><input type="checkbox" name="demo_mode" value="1" <?= \Sysfera\Demo::on() ? 'checked' : '' ?>> <?= e(t('settings.demo_on')) ?></label>
  <label class="fld inline"><span><?= e(t('settings.demo_hours')) ?></span><select name="demo_hours"><?php foreach (\Sysfera\Demo::HOURS as $h): ?><option <?= \Sysfera\Demo::hours() === $h ? 'selected' : '' ?>><?= $h ?></option><?php endforeach; ?></select></label>
  <div style="margin-top:14px"><button class="btn primary"><?= e(t('common.save')) ?></button></div>
</form>
<?php if (\Sysfera\Demo::on()): ?>
<form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="do" value="demo_reset">
  <h3><?= e(t('settings.demo_reset')) ?></h3><p class="muted sm"><?= e(t('settings.demo_reset_hint')) ?></p>
  <div class="row"><input name="confirm" placeholder="RESET" style="max-width:160px" autocomplete="off"><button class="btn danger"><?= e(t('settings.demo_reset_btn')) ?></button></div>
</form>
<?php endif; ?>
