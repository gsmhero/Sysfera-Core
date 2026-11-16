<?php /** @var \Sysfera\Plugin $sf @var array $missing @var array $exports @var string $version @var bool $collect @var array $cols */
include __DIR__ . '/_nav.php'; ?>
<div class="page-h"><h1><?= e($sf->t('nav_privacy')) ?></h1></div>
<div class="hint"><?= e($sf->t('privacy_hint')) ?></div>
<div class="card"><h3><?= e($sf->t('privacy_state')) ?></h3><p><?= e($sf->t('consent_version')) ?>: <b>v<?= e($version) ?></b> · <?= e($sf->t('passport_collect')) ?>: <b><?= e($sf->t($collect ? 'yes' : 'no')) ?></b></p>
  <ul class="muted"><li><?= e($sf->t('pr_min')) ?></li><li><?= e($sf->t('pr_enc')) ?></li><li><?= e($sf->t('pr_export')) ?></li><li><?= e($sf->t('pr_log')) ?></li></ul></div>
<div class="card flush"><h3 style="padding:12px 12px 0"><?= e($sf->t('consent_missing')) ?></h3><table class="tbl"><?php if (!$missing): ?><tr><td class="muted"><?= e($sf->t('none')) ?></td></tr><?php endif; ?>
  <?php foreach ($missing as $m): ?><tr><td><a href="<?= e($sf->url('/employees/' . $m['employee_uuid'])) ?>"><?= e($m['full_name']) ?></a></td></tr><?php endforeach; ?></table></div>
<?php if ($c->can('hr.export')): ?><div class="card"><h3><?= e($sf->t('export_h')) ?></h3><p class="muted"><?= e($sf->t('export_cols')) ?>: <?= e(implode(', ', $cols)) ?></p><a class="btn primary" href="<?= e($sf->url('/export.csv')) ?>"><?= e($sf->t('export_csv')) ?></a></div><?php endif; ?>
<div class="card flush"><h3 style="padding:12px 12px 0"><?= e($sf->t('export_log')) ?></h3><table class="tbl"><?php if (!$exports): ?><tr><td class="muted"><?= e($sf->t('none')) ?></td></tr><?php endif; ?>
  <?php foreach ($exports as $x): ?><tr><td><?= e($x['ts']) ?></td><td><?= e($x['actor']) ?></td><td class="muted"><?= e((string)$x['data']) ?></td></tr><?php endforeach; ?></table></div>
