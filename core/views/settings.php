<?php /** @var array $caps @var array $langs */
$app = \Sysfera\App::get(); $defaults = $app->defaultCaps(); ?>
<div class="page-h"><h1><?= e(t('nav.settings')) ?></h1></div>
<form class="card" method="post"><?= csrf_field() ?>
  <label class="fld"><span><?= e(t('settings.site_name')) ?></span><input name="site_name" value="<?= e($app->setting('site_name', 'Sysfera')) ?>" maxlength="80"></label>
  <div class="cols">
    <label class="fld"><span><?= e(t('settings.default_lang')) ?></span><select name="default_lang"><?php foreach ($langs as $c => $n): ?><option value="<?= e($c) ?>" <?= $app->setting('default_lang', 'en') === $c ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select></label>
    <label class="fld"><span><?= e(t('settings.timezone')) ?></span><select name="timezone"><?php foreach (timezone_identifiers_list() as $tz): ?><option <?= $app->setting('timezone', 'UTC') === $tz ? 'selected' : '' ?>><?= e($tz) ?></option><?php endforeach; ?></select></label>
  </div>
  <label class="chk"><input type="checkbox" name="landing" value="1" <?= $app->setting('landing', '1') !== '0' ? 'checked' : '' ?>> <?= e(t('settings.landing_on')) ?></label>
  <p class="muted sm"><?= e(t('settings.landing_hint')) ?></p>
  <h3><?= e(t('settings.registration')) ?></h3>
  <p class="muted sm"><?= e(t('settings.registration_hint')) ?></p>
  <label class="chk"><input type="checkbox" name="registration" value="1" <?= $app->setting('registration', '0') === '1' ? 'checked' : '' ?>> <?= e(t('settings.registration_open')) ?></label>
  <?php $grantAll = $app->setting('grant_all_new', '1') !== '0'; ?>
  <label class="chk"><input type="checkbox" name="grant_all_new" value="1" id="grant-all-new" <?= $grantAll ? 'checked' : '' ?>> <?= e(t('settings.grant_all_new')) ?></label>
  <?php if ($caps): ?><div class="caps" id="default-caps"><span class="muted sm"><?= e(t('settings.default_caps')) ?></span><?php foreach ($caps as $c => $label): ?><label class="chk"><input type="checkbox" name="default_caps[]" value="<?= e($c) ?>" <?= $grantAll || in_array($c, $defaults, true) ? 'checked' : '' ?> <?= $grantAll ? 'disabled' : '' ?>> <?= e($label) ?></label><?php endforeach; ?></div>
  <script>(function(){var g=document.getElementById("grant-all-new"),b=document.querySelectorAll("#default-caps input");if(!g)return;var saved=[].map.call(b,function(i){return i.checked});
    function ap(){b.forEach(function(i,n){if(g.checked){i.checked=true;i.disabled=true}else{i.disabled=false}})}g.addEventListener("change",ap);ap()})();</script><?php endif; ?>
  <h3><?= e(t('settings.updates')) ?></h3>
  <label class="chk"><input type="checkbox" name="auto_install" value="1" <?= $app->setting('auto_install', '0') === '1' ? 'checked' : '' ?>> <?= e(t('settings.auto_install')) ?></label>
  <p class="muted sm"><?= e(t('settings.auto_install_hint')) ?></p>
  <label class="fld"><span><?= e(t('settings.update_url')) ?></span><input name="update_url" value="<?= e($app->setting('update_url', '')) ?>" placeholder="<?= e(\Sysfera\Updater::DEFAULT_URL) ?>"></label>
  <p class="muted sm"><?= e(t('settings.update_hint')) ?></p>
  <label class="chk"><input type="checkbox" name="news_enabled" value="1" <?= $app->setting('news_enabled', '1') === '1' ? 'checked' : '' ?>> <?= e(t('settings.news_on')) ?></label>
  <p class="muted sm"><?= e(t('settings.news_hint')) ?></p>
  <h3><?= e(t('settings.demo')) ?></h3>
  <p class="muted sm"><?= e(t('settings.demo_hint')) ?></p>
  <label class="chk"><input type="checkbox" name="demo_mode" value="1" <?= \Sysfera\Demo::on() ? 'checked' : '' ?>> <?= e(t('settings.demo_on')) ?></label>
  <label class="fld inline"><span><?= e(t('settings.demo_hours')) ?></span><select name="demo_hours"><?php foreach (\Sysfera\Demo::HOURS as $h): ?><option <?= \Sysfera\Demo::hours() === $h ? 'selected' : '' ?>><?= $h ?></option><?php endforeach; ?></select></label>
  <div style="margin-top:14px"><button class="btn primary"><?= e(t('common.save')) ?></button></div>
</form>
<form class="card" method="post" data-confirm="<?= e(t('settings.grant_confirm')) ?>"><?= csrf_field() ?><input type="hidden" name="do" value="grant_all">
  <h3><?= e(t('settings.grant_h')) ?></h3><p class="muted sm"><?= e(t('settings.grant_hint')) ?></p><button class="btn"><?= e(t('settings.grant_btn')) ?></button>
</form>
<?php if (\Sysfera\Demo::on()): ?>
<form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="do" value="demo_reset">
  <h3><?= e(t('settings.demo_reset')) ?></h3><p class="muted sm"><?= e(t('settings.demo_reset_hint')) ?></p>
  <div class="row"><input name="confirm" placeholder="RESET" style="max-width:160px" autocomplete="off"><button class="btn danger"><?= e(t('settings.demo_reset_btn')) ?></button></div>
</form>
<?php endif; ?>

<form class="card" method="post"><?= csrf_field() ?>
  <h3><?= e(t('mail.title')) ?></h3><p class="muted sm"><?= e(t('mail.hint')) ?></p>
  <div class="cols">
    <label class="fld"><span><?= e(t('mail.host')) ?></span><input name="smtp_host" value="<?= e($app->setting('smtp_host')) ?>" placeholder="smtp.example.com"></label>
    <label class="fld"><span><?= e(t('mail.port')) ?></span><input name="smtp_port" value="<?= e($app->setting('smtp_port', '587')) ?>" inputmode="numeric"></label>
    <label class="fld"><span><?= e(t('mail.secure')) ?></span><select name="smtp_secure"><?php foreach (['auto' => t('mail.secure_auto'), 'ssl' => 'SSL/TLS (465)', 'tls' => 'STARTTLS (587)', 'none' => t('mail.secure_none')] as $k => $l): ?><option value="<?= $k ?>" <?= $app->setting('smtp_secure', 'auto') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
  </div>
  <div class="cols">
    <label class="fld"><span><?= e(t('mail.user')) ?></span><input name="smtp_user" value="<?= e($app->setting('smtp_user')) ?>" autocomplete="off"></label>
    <label class="fld"><span><?= e(t('mail.pass')) ?></span><input name="smtp_pass" type="password" autocomplete="new-password" placeholder="<?= $app->setting('smtp_pass') !== '' ? e(t('mail.pass_saved')) : '' ?>"></label>
  </div>
  <div class="cols">
    <label class="fld"><span><?= e(t('mail.from')) ?></span><input name="smtp_from" type="email" value="<?= e($app->setting('smtp_from')) ?>" placeholder="noreply@example.com"></label>
    <label class="fld"><span><?= e(t('mail.from_name')) ?></span><input name="smtp_from_name" value="<?= e($app->setting('smtp_from_name')) ?>" maxlength="80"></label>
  </div>
  <label class="fld"><span><?= e(t('mail.notify')) ?></span><input name="notify_email" value="<?= e($app->setting('notify_email')) ?>" placeholder="<?= e(t('mail.notify_ph')) ?>"></label>
  <div class="row"><button class="btn primary" name="do" value="mail_save"><?= e(t('common.save')) ?></button><button class="btn" name="do" value="mail_test"><?= e(t('mail.test_btn')) ?></button></div>
</form>

<?php $logFile = SF_ROOT . '/storage/error.log'; $log = '';
if (is_file($logFile)) { $sz = (int)filesize($logFile); $fh = @fopen($logFile, 'rb'); if ($fh) { if ($sz > 12000) fseek($fh, -12000, SEEK_END); $log = (string)stream_get_contents($fh); fclose($fh); } } ?>
<form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="do" value="log_clear">
  <h3><?= e(t('log.title')) ?></h3><p class="muted sm"><?= e(t('log.hint')) ?></p>
  <?php if ($log === ''): ?><p class="muted"><?= e(t('log.empty')) ?></p>
  <?php else: ?><pre class="logbox"><?= e(implode("\n", array_slice(explode("\n", trim($log)), -60))) ?></pre><button class="btn danger"><?= e(t('log.clear')) ?></button><?php endif; ?>
</form>
