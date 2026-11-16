<?php /** @var \Sysfera\Plugin $sf @var string $tab @var bool $manage @var string $officeKey @var string $base @var string $appUrl @var bool $https @var array $v @var array $rules */
include __DIR__ . '/_nav.php'; ?>
<div class="page-h"><h1><?= e($sf->t('nav_settings')) ?></h1></div>
<?php if (!$https): ?><div class="flash warn"><?= e($sf->t('https_warn')) ?></div><?php endif; ?>
<?php if (!$manage): ?><div class="hint"><?= e($sf->t('read_only')) ?></div><?php endif; ?>
<form method="post" class="card"><?= csrf_field() ?><fieldset <?= $manage ? '' : 'disabled' ?> style="border:0;padding:0;margin:0">
  <label class="fld"><span><?= e($sf->t('set_threshold')) ?></span><input name="id_threshold" value="<?= e($v['id_threshold']) ?>" inputmode="decimal"><small class="muted"><?= e($sf->t('set_threshold_h')) ?></small></label>
  <label class="fld"><span><?= e($sf->t('set_photo_threshold')) ?></span><input name="photo_threshold" value="<?= e($v['photo_threshold']) ?>" inputmode="decimal"><small class="muted"><?= e($sf->t('set_photo_threshold_h')) ?></small></label>
  <label class="fld"><span><?= e($sf->t('set_ttl')) ?></span><input name="code_ttl_h" type="number" min="1" max="720" value="<?= e($v['code_ttl_h']) ?>"><small class="muted"><?= e($sf->t('set_ttl_h')) ?></small></label>
  <label class="fld"><span><?= e($sf->t('set_overdue')) ?></span><input name="overdue_days" type="number" min="1" max="365" value="<?= e($v['overdue_days']) ?>"><small class="muted"><?= e($sf->t('set_overdue_h')) ?></small></label>
  <label class="fld"><span><?= e($sf->t('set_refund_days')) ?></span><input name="refund_days" type="number" min="0" max="365" value="<?= e($v['refund_days']) ?>"><small class="muted"><?= e($sf->t('set_refund_days_h')) ?></small></label>
  <label class="row"><input type="checkbox" name="refund_auto" value="1" <?= $v['refund_auto'] === '1' ? 'checked' : '' ?> style="width:auto"> <?= e($sf->t('set_refund_auto')) ?></label>
  <label class="fld"><span><?= e($sf->t('set_offline')) ?></span><input name="offline_hours" type="number" min="1" max="720" value="<?= e($v['offline_hours']) ?>"><small class="muted"><?= e($sf->t('set_offline_h')) ?></small></label>
  <label class="fld"><span><?= e($sf->t('set_stuck')) ?></span><input name="stuck_hours" type="number" min="1" max="720" value="<?= e($v['stuck_hours']) ?>"><small class="muted"><?= e($sf->t('set_stuck_h')) ?></small></label>
  <label class="fld"><span><?= e($sf->t('set_couriers')) ?></span><input name="couriers" value="<?= e($v['couriers']) ?>"><small class="muted"><?= e($sf->t('set_couriers_h')) ?></small></label>
  <?php if ($manage): ?><button class="btn primary"><?= e($sf->t('save')) ?></button><?php endif; ?></fieldset></form>
<div class="card"><h3><?= e($sf->t('rules_h')) ?></h3><p class="muted"><?= e($sf->t('rules_hint')) ?></p>
  <?php foreach ($rules as $r): ?><form method="post" class="row"><?= csrf_field() ?><input type="hidden" name="sku" value="<?= e($r['ref']) ?>"><b><code><?= e($r['ref']) ?></code></b> <?= e($sf->t('rule_max')) ?>: <b><?= (int)$r['data']['max_days'] ?></b> · <?= e($sf->t('rule_refund')) ?>: <b><?= (int)$r['data']['refund_days'] ?></b><?php if ($manage): ?> <button class="btn sm" name="do" value="rule_del"><?= e($sf->t('remove')) ?></button><?php endif; ?></form><?php endforeach; ?>
  <?php if (!$rules): ?><p class="muted"><?= e($sf->t('none')) ?></p><?php endif; ?>
  <?php if ($manage): ?><form method="post" class="row"><?= csrf_field() ?><input name="sku" placeholder="SKU" maxlength="64" required><label><?= e($sf->t('rule_max')) ?> <input name="max_days" type="number" min="0" max="365" value="0" style="width:80px"></label><label><?= e($sf->t('rule_refund')) ?> <input name="refund_days" type="number" min="0" max="365" value="0" style="width:80px"></label><button class="btn primary" name="do" value="rule_set"><?= e($sf->t('add')) ?></button></form><?php endif; ?></div>
<div class="card"><h3><?= e($sf->t('integration')) ?></h3>
  <p><b><?= e($sf->t('app_url')) ?>:</b> <code class="gi-wrap"><?= e((preg_match('~^https?://~', $appUrl) ? '' : (($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? ''))) . $appUrl) ?></code></p>
  <p><b><?= e($sf->t('api_base')) ?>:</b> <code class="gi-wrap"><?= e((preg_match('~^https?://~', $base) ? '' : (($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? ''))) . $base) ?></code> · <a href="<?= e($base . 'openapi.yaml') ?>">openapi.yaml</a></p>
  <p><b><?= e($sf->t('office_key')) ?>:</b> <code class="gi-wrap"><?= $manage ? e($officeKey) : '••••••••' ?></code></p><p class="muted"><?= e($sf->t('office_key_h')) ?></p>
  <?php if ($manage): ?><form method="post" onsubmit="return confirm('<?= e($sf->t('key_confirm')) ?>')"><?= csrf_field() ?><button class="btn" name="do" value="newkey"><?= e($sf->t('key_regen')) ?></button></form><?php endif; ?></div>
