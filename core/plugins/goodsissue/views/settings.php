<?php /** @var \Sysfera\Plugin $sf @var string $tab @var string $officeKey @var string $base @var string $appUrl @var bool $https @var array $v */
include __DIR__ . '/_nav.php'; ?>
<div class="page-h"><h1><?= e($sf->t('nav_settings')) ?></h1></div>
<?php if (!$https): ?><div class="flash warn"><?= e($sf->t('https_warn')) ?></div><?php endif; ?>
<form method="post" class="card"><?= csrf_field() ?>
  <label class="fld"><span><?= e($sf->t('set_threshold')) ?></span><input name="id_threshold" value="<?= e($v['id_threshold']) ?>" inputmode="decimal"><small class="muted"><?= e($sf->t('set_threshold_h')) ?></small></label>
  <label class="fld"><span><?= e($sf->t('set_ttl')) ?></span><input name="code_ttl_h" type="number" min="1" max="720" value="<?= e($v['code_ttl_h']) ?>"><small class="muted"><?= e($sf->t('set_ttl_h')) ?></small></label>
  <label class="fld"><span><?= e($sf->t('set_overdue')) ?></span><input name="overdue_days" type="number" min="1" max="60" value="<?= e($v['overdue_days']) ?>"><small class="muted"><?= e($sf->t('set_overdue_h')) ?></small></label>
  <button class="btn primary"><?= e($sf->t('save')) ?></button></form>
<div class="card"><h3><?= e($sf->t('integration')) ?></h3>
  <p><b><?= e($sf->t('app_url')) ?>:</b> <code class="gi-wrap"><?= e((preg_match('~^https?://~', $appUrl) ? '' : (($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? ''))) . $appUrl) ?></code></p>
  <p><b><?= e($sf->t('api_base')) ?>:</b> <code class="gi-wrap"><?= e((preg_match('~^https?://~', $base) ? '' : (($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? ''))) . $base) ?></code> · <a href="<?= e($base . 'openapi.yaml') ?>">openapi.yaml</a></p>
  <p><b><?= e($sf->t('office_key')) ?>:</b> <code class="gi-wrap"><?= e($officeKey) ?></code></p><p class="muted"><?= e($sf->t('office_key_h')) ?></p>
  <form method="post" onsubmit="return confirm('<?= e($sf->t('key_confirm')) ?>')"><?= csrf_field() ?><button class="btn" name="do" value="newkey"><?= e($sf->t('key_regen')) ?></button></form></div>
