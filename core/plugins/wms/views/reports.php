<?php /** @var \Sysfera\Plugin $sf @var string $tab @var array $rows @var string $from @var string $to @var ?int $wh @var array $whs */
use SysferaWms\W;
include __DIR__ . '/_nav.php';
$qs = http_build_query(['from' => $from, 'to' => $to, 'wh' => $wh ?: '', 'csv' => 1]);
$t = ['opening' => 0, 'income' => 0, 'outcome' => 0]; foreach ($rows as $r) { $t['opening'] += $r['opening']; $t['income'] += $r['income']; $t['outcome'] += $r['outcome']; } ?>
<div class="page-h"><h1><?= e($sf->t('nav_reports')) ?></h1><a class="btn" href="<?= e($sf->url('/reports') . '?' . $qs) ?>">⤓ CSV</a></div>
<div class="hint"><?= e($sf->t('turnover_hint')) ?></div>

<form class="bar" method="get">
  <label class="fld inl"><span><?= e($sf->t('from')) ?></span><input type="date" name="from" value="<?= e($from) ?>"></label>
  <label class="fld inl"><span><?= e($sf->t('to')) ?></span><input type="date" name="to" value="<?= e($to) ?>"></label>
  <select name="wh"><option value=""><?= e($sf->t('all_warehouses')) ?></option><?php foreach ($whs as $w): ?><option value="<?= (int)$w['id'] ?>" <?= (int)$wh === (int)$w['id'] ? 'selected' : '' ?>><?= e($w['name']) ?></option><?php endforeach; ?></select>
  <button class="btn primary"><?= e($sf->t('show')) ?></button>
</form>

<?php if (!$rows): ?><div class="card empty"><h3><?= e($sf->t('no_movements')) ?></h3></div>
<?php else: ?>
<div class="card flush"><table class="tbl">
  <thead><tr><th><?= e($sf->t('col_name')) ?></th><th class="num"><?= e($sf->t('col_opening')) ?></th><th class="num">+ <?= e($sf->t('col_income')) ?></th><th class="num">− <?= e($sf->t('col_outcome')) ?></th><th class="num"><?= e($sf->t('col_closing')) ?></th></tr></thead>
  <tbody><?php foreach ($rows as $r): ?>
    <tr onclick="location='<?= e($sf->url('/products/' . $r['id'])) ?>'"><td><b><?= e($r['name']) ?></b><br><small class="muted"><?= e($r['sku']) ?></small></td>
      <td class="num"><?= e(W::qty($r['opening'])) ?></td><td class="num ok-t"><?= $r['income'] ? '+' . e(W::qty($r['income'])) : '<span class="muted">—</span>' ?></td>
      <td class="num bad-t"><?= $r['outcome'] ? '−' . e(W::qty($r['outcome'])) : '<span class="muted">—</span>' ?></td><td class="num"><b><?= e(W::qty($r['opening'] + $r['income'] - $r['outcome'])) ?></b> <small class="muted"><?= e($r['unit']) ?></small></td></tr>
  <?php endforeach; ?></tbody>
  <tfoot><tr><td><b><?= e($sf->t('total')) ?></b></td><td class="num"><?= e(W::qty($t['opening'])) ?></td><td class="num"><?= e(W::qty($t['income'])) ?></td><td class="num"><?= e(W::qty($t['outcome'])) ?></td><td class="num"><?= e(W::qty($t['opening'] + $t['income'] - $t['outcome'])) ?></td></tr></tfoot></table></div>
<?php endif; ?>
