<?php /** @var \Sysfera\Plugin $sf @var string $tab @var bool $forbid */
include __DIR__ . '/_nav.php'; ?>
<div class="page-h"><h1><?= e($sf->t('nav_settings')) ?></h1></div>
<form class="card" method="post" style="max-width:640px"><?= csrf_field() ?>
  <label class="chk"><input type="checkbox" name="forbid_negative" value="1" <?= $forbid ? 'checked' : '' ?>> <b><?= e($sf->t('forbid_negative')) ?></b></label>
  <p class="muted sm"><?= e($sf->t('forbid_negative_hint')) ?></p>
  <p class="muted sm"><?= e($sf->t('currency_note')) ?></p>
  <button class="btn primary"><?= e($sf->t('save')) ?></button>
</form>
