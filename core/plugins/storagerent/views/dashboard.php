<?php /** @var \Sysfera\Plugin $sf @var \SysferaStoragerent\Ctx $ctx @var string $lang @var array $d @var array $buckets @var int $new_requests @var bool $finance */
require __DIR__ . '/_nav.php'; ?>
<div class="stats sr-stats">
  <div class="card stat"><small><?= e($sf->t('dash_occupancy')) ?></small><b><?= e(sr_num($d['occupancy'])) ?>%</b><small class="muted"><?= e($sf->t('dash_units', ['free' => $d['units']['free'], 'occupied' => $d['units']['occupied'], 'reserved' => $d['units']['reserved'], 'maint' => $d['units']['maintenance']])) ?></small></div>
  <?php if ($finance): ?>
  <div class="card stat"><small><?= e($sf->t('dash_invoiced')) ?></small><b><?= e(sr_m($d['invoiced'], $lang)) ?></b><small class="muted"><?= e($sf->t('dash_received')) ?>: <?= e(sr_m($d['received'], $lang)) ?></small></div>
  <div class="card stat"><small><?= e($sf->t('dash_arrears')) ?></small><b class="<?= $d['arrears'] > 0 ? 'bad-t' : '' ?>"><?= e(sr_m($d['arrears'], $lang)) ?></b><small class="muted"><?= e($sf->t('dash_arrears_n', ['n' => $d['arrears_invoices']])) ?></small></div>
  <?php endif; ?>
  <div class="card stat"><small><?= e($sf->t('dash_expiring')) ?></small><b><?= count($d['expiring']) ?></b><small class="muted"><?= e($sf->t('dash_expiring_help')) ?></small></div>
  <div class="card stat"><small><?= e($sf->t('wrms_requests')) ?></small><b><?= (int)$new_requests ?></b><small class="muted"><a href="<?= e($sf->url('/requests')) ?>"><?= e($sf->t('dash_new_requests')) ?></a></small></div>
</div>
<?php if ($finance): ?>
<div class="card"><h3><?= e($sf->t('dash_aging')) ?></h3>
  <div class="sr-aging"><?php foreach (['1-30', '31-60', '60+'] as $b): ?><div><small class="muted"><?= e($sf->t('bk_' . $b)) ?></small><b><?= e(sr_m($buckets[$b] ?? 0, $lang)) ?></b></div><?php endforeach; ?></div>
  <p><a class="btn sm" href="<?= e($sf->url('/reports?r=arrears')) ?>"><?= e($sf->t('rep_arrears')) ?></a></p></div>
<?php endif; ?>
<div class="card"><h3><?= e($sf->t('dash_expiring_title')) ?></h3>
<?php if (!$d['expiring']): ?><p class="muted"><?= e($sf->t('none')) ?></p><?php else: ?>
  <table><thead><tr><th><?= e($sf->t('col_contract')) ?></th><th><?= e($sf->t('col_tenant')) ?></th><th><?= e($sf->t('col_end_date')) ?></th><th><?= e($sf->t('col_days_left')) ?></th></tr></thead><tbody>
  <?php foreach ($d['expiring'] as $k): ?><tr><td><a href="<?= e($sf->url('/contract/' . $k['id'])) ?>"><?= e($k['number']) ?></a></td><td><?= e($k['tenant']['name'] ?? '') ?></td><td><?= e(sr_d($k['end_date'])) ?></td><td><span class="badge warn"><?= (int)$k['days_left'] ?></span></td></tr><?php endforeach; ?>
  </tbody></table><?php endif; ?></div>
