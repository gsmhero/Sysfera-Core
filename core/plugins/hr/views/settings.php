<?php /** @var \Sysfera\Plugin $sf @var string $key @var string $base @var array $v @var bool $keyFile */
include __DIR__ . '/_nav.php'; ?>
<div class="page-h"><h1><?= e($sf->t('nav_settings')) ?></h1></div>
<div class="card"><h3><?= e($sf->t('set_general')) ?></h3>
  <form method="post"><?= csrf_field() ?>
    <div class="hr-grid"><label><?= e($sf->t('set_annual_days')) ?><input name="annual_days" value="<?= e($v['annual_days']) ?>" inputmode="numeric"></label><label><?= e($sf->t('set_onboarding_days')) ?><input name="onboarding_days" value="<?= e($v['onboarding_days']) ?>" inputmode="numeric"></label>
      <label><?= e($sf->t('set_consent_version')) ?><input name="consent_version" value="<?= e($v['consent_version']) ?>" maxlength="20"></label><label><?= e($sf->t('set_holidays')) ?><input name="holidays" value="<?= e($v['holidays']) ?>" placeholder="2026-12-31,2027-01-01"></label>
      <label class="hr-perm"><input type="checkbox" name="collect_passport" value="1" <?= $v['collect_passport'] === '1' ? 'checked' : '' ?>> <?= e($sf->t('set_collect_passport')) ?></label></div>
    <label class="fld"><?= e($sf->t('set_api_perms')) ?><input name="api_perms" value="<?= e($v['api_perms']) ?>" style="width:100%"></label><p class="muted"><?= e($sf->t('set_api_perms_hint')) ?></p>
    <button class="btn primary"><?= e($sf->t('save')) ?></button></form></div>
<div class="card"><h3><?= e($sf->t('set_api')) ?></h3><p class="muted"><?= e($sf->t('set_api_hint')) ?></p>
  <p><?= e($sf->t('set_base')) ?>: <code><?= e($base) ?></code> · <a href="<?= e($base) ?>openapi.yaml">openapi.yaml</a></p>
  <p><?= e($sf->t('set_key')) ?>: <code class="gi-wrap"><?= e($key) ?></code></p>
  <form method="post"><?= csrf_field() ?><button class="btn" name="do" value="newkey" onclick="return confirm('<?= e($sf->t('confirm_newkey')) ?>')"><?= e($sf->t('key_regen')) ?></button></form></div>
<div class="card"><h3><?= e($sf->t('set_dataKey')) ?></h3><p class="muted"><?= e($sf->t('set_dataKey_hint')) ?></p><p><span class="hr-st <?= $keyFile ? 'ok' : 'warn' ?>"><?= e($sf->t($keyFile ? 'key_present' : 'key_will_be_created')) ?></span></p></div>
