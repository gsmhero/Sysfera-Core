<?php /** @var \Sysfera\Plugin $sf @var string $tab @var array $rows @var array $whs */
include __DIR__ . '/_nav.php'; ?>
<div class="page-h"><h1><?= e($sf->t('nav_reports')) ?></h1></div>
<div class="card"><h3><?= e($sf->t('run_report')) ?></h3><p class="muted"><?= e($sf->t('report_hint')) ?></p>
  <form method="post" class="row"><?= csrf_field() ?><select name="warehouse_id"><option value="0"><?= e($sf->t('all_whs')) ?></option><?php foreach ($whs as $w): ?><option value="<?= (int)$w['id'] ?>"><?= e($w['name']) ?></option><?php endforeach; ?></select>
    <input type="date" name="day" value="<?= e(date('Y-m-d')) ?>"><label class="chk"><input type="checkbox" name="force" value="1"> <?= e($sf->t('rerun')) ?></label><button class="btn primary"><?= e($sf->t('run')) ?></button></form></div>
<?php if ($rows): ?><div class="card flush"><table class="tbl"><thead><tr><th><?= e($sf->t('date')) ?></th><th><?= e($sf->t('warehouse')) ?></th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td><?= e($r['day']) ?></td><td><?= e($r['wname'] ?? '') ?></td><td><a href="<?= e($sf->url('/reports/' . (int)$r['warehouse_id'] . '/' . $r['day'])) ?>"><?= e($sf->t('open')) ?></a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
