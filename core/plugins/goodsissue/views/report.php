<?php /** @var \Sysfera\Plugin $sf @var string $tab @var array $r @var ?array $wh */
include __DIR__ . '/_nav.php'; ?>
<div class="page-h"><h1><?= e($sf->t('report_of', ['w' => $wh['name'] ?? '?', 'd' => $r['day']])) ?></h1><a class="btn" href="<?= e($sf->url('/reports')) ?>">← <?= e($sf->t('nav_reports')) ?></a></div>
<div class="gi-tiles"><div class="card gi-tile"><b><?= (int)$r['expected_pieces'] ?></b><span><?= e($sf->t('expected')) ?></span></div>
  <div class="card gi-tile"><b><?= $r['counted'] ? (int)$r['actual_pieces'] : '—' ?></b><span><?= e($sf->t('actual')) ?></span></div>
  <div class="card gi-tile"><b><?= count($r['issued']) ?></b><span><?= e($sf->t('issued_today')) ?></span></div>
  <div class="card gi-tile"><b><?= count($r['not_issued']) ?></b><span><?= e($sf->t('not_issued')) ?></span></div>
  <div class="card gi-tile"><b><?= count($r['overdue']) ?></b><span><?= e($sf->t('overdue')) ?></span></div></div>
<?php if (!$r['counted']): ?><div class="flash warn"><?= e($sf->t('no_count')) ?></div><?php elseif ($r['ok']): ?><div class="flash ok"><?= e($sf->t('all_match')) ?></div><?php endif; ?>
<div class="card flush"><table class="tbl"><thead><tr><th><?= e($sf->t('col_barcode')) ?></th><th><?= e($sf->t('col_name')) ?></th><th><?= e($sf->t('expected')) ?></th><th><?= e($sf->t('actual')) ?></th><th><?= e($sf->t('diff')) ?></th></tr></thead><tbody>
<?php foreach ($r['lines'] as $l): ?><tr class="<?= $l['diff'] ? 'gi-bad' : '' ?>"><td><code><?= e($l['barcode']) ?></code></td><td><?= e($l['name']) ?></td><td><?= (int)$l['expected'] ?></td><td><?= $l['actual'] === null ? '—' : (int)$l['actual'] ?></td><td><b><?= $l['diff'] === null ? '' : ($l['diff'] > 0 ? '+' : '') . (int)$l['diff'] ?></b></td></tr><?php endforeach; ?>
<?php if (!$r['lines']): ?><tr><td colspan="5" class="muted"><?= e($sf->t('none')) ?></td></tr><?php endif; ?></tbody></table></div>
<?php foreach (['not_issued', 'overdue'] as $k): if (!$r[$k]) continue; ?><div class="card"><h3><?= e($sf->t($k)) ?></h3><?php foreach ($r[$k] as $o): ?><p><a href="<?= e($sf->url('/orders/' . $o['uuid'])) ?>">#<?= e($o['number']) ?></a> <span class="muted"><?= e($o['total_rub']) ?> ₽ · <?= e((string)($o['ready_at'] ?? '')) ?></span></p><?php endforeach; ?></div><?php endforeach; ?>
<p class="muted"><?= e($sf->t('incidents_made', ['n' => (int)$r['incidents']])) ?> · <?= e($r['generated_at']) ?></p>
