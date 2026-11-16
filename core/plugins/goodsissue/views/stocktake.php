<?php /** @var \Sysfera\Plugin $sf @var string $tab @var bool $manage @var array $whs @var array $names @var array $rows @var int $proposed */
include __DIR__ . '/_nav.php'; ?>
<div class="page-h"><h1><?= e($sf->t('nav_stocktake')) ?></h1></div>
<div class="hint"><?= e($sf->t('stocktake_hint')) ?></div>
<?php if ($manage): ?><form method="post" class="card row"><?= csrf_field() ?><select name="warehouse_id"><?php foreach ($whs as $w): if ($w['active']): ?><option value="<?= (int)$w['id'] ?>"><?= e($w['name']) ?></option><?php endif; endforeach; ?></select>
  <button class="btn primary" name="do" value="start"><?= e($sf->t('stocktake_start')) ?></button></form><?php endif; ?>
<?php if ($proposed): ?><div class="flash warn"><?= e($sf->t('stocktake_proposed', ['n' => $proposed])) ?></div><?php endif; ?>
<div class="card flush"><table class="tbl"><thead><tr><th><?= e($sf->t('date')) ?></th><th><?= e($sf->t('warehouse')) ?></th><th><?= e($sf->t('status')) ?></th><th><?= e($sf->t('stocktake_diffs')) ?></th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): $d = $r['data']; ?><tr><td><?= e($d['started_at'] ?? $r['created_at']) ?></td><td><?= e($names[(int)$r['warehouse_id']] ?? '') ?></td><td><span class="gi-st"><?= e($sf->t('sts_' . $r['status'])) ?></span></td><td><?= isset($d['differences']) ? (int)$d['differences'] : '—' ?></td>
  <td><a class="btn sm" href="<?= e($sf->url('/stocktake/' . $r['ref'])) ?>"><?= e($sf->t('open')) ?></a></td></tr><?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="5" class="muted"><?= e($sf->t('none')) ?></td></tr><?php endif; ?></tbody></table></div>
