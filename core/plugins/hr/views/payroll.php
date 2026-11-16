<?php /** @var \Sysfera\Plugin $sf @var string $from @var string $to @var ?array $doc @var string $err @var array $runs @var array $emps */
include __DIR__ . '/_nav.php'; ?>
<div class="page-h"><h1><?= e($sf->t('nav_payroll')) ?></h1></div>
<div class="hint"><?= e($sf->t('payroll_hint')) ?> <a href="<?= e(url('/api/hr/v1/schemas/payroll-export.schema.json')) ?>">JSON Schema</a></div>
<form method="get" class="row hr-noprint"><input type="date" name="from" value="<?= e($from) ?>"><input type="date" name="to" value="<?= e($to) ?>"><button class="btn primary"><?= e($sf->t('calculate')) ?></button>
  <?php if ($doc && $doc['items']): ?><a class="btn" href="<?= e($sf->url('/payroll/download?from=' . $from . '&to=' . $to)) ?>"><?= e($sf->t('export_json')) ?></a><?php endif; ?></form>
<?php if ($err !== ''): ?><div class="flash warn"><?= e($err) ?></div><?php endif; ?>
<?php if ($doc): ?><div class="card flush"><table class="tbl"><tr><th><?= e($sf->t('f_employee')) ?></th><th><?= e($sf->t('f_lines')) ?></th><th><?= e($sf->t('gross')) ?></th><th><?= e($sf->t('deductions')) ?></th><th><?= e($sf->t('net')) ?></th></tr>
  <?php if (!$doc['items']): ?><tr><td colspan="5" class="muted"><?= e($sf->t('payroll_empty')) ?></td></tr><?php endif; ?>
  <?php foreach ($doc['items'] as $it): ?><tr><td><a href="<?= e($sf->url('/employees/' . $it['employee']['uuid'])) ?>"><?= e($it['employee']['name']) ?></a> <code><?= e($it['employee']['number']) ?></code></td>
    <td><?php foreach ($it['lines'] as $l): ?><div class="muted"><?= e($pl($l)) ?>: <?= e((string)$l['qty']) ?> → <?= e($money($l['amount_kop'])) ?></div><?php endforeach; ?></td><td><?= e($money($it['totals']['gross_kop'])) ?></td><td><?= e($money($it['totals']['deductions_kop'])) ?></td><td><b><?= e($money($it['totals']['net_kop'])) ?></b></td></tr><?php endforeach; ?>
  <tr><th colspan="2"><?= e($sf->t('total')) ?> (<?= (int)$doc['totals']['employees'] ?>)</th><th><?= e($money($doc['totals']['gross_kop'])) ?></th><th><?= e($money($doc['totals']['deductions_kop'])) ?></th><th><?= e($money($doc['totals']['net_kop'])) ?></th></tr></table></div>
<p class="muted"><code><?= e($doc['export_id']) ?></code></p><?php endif; ?>
<div class="card flush"><h3 style="padding:12px 12px 0"><?= e($sf->t('payroll_runs')) ?></h3><table class="tbl"><?php if (!$runs): ?><tr><td class="muted"><?= e($sf->t('none')) ?></td></tr><?php endif; ?>
  <?php foreach ($runs as $r): ?><tr><td><code><?= e($r['export_id']) ?></code></td><td><?= e($r['period_from']) ?> – <?= e($r['period_to']) ?></td><td><?= e($r['created_at']) ?></td><td class="muted"><?= e($r['created_by']) ?></td></tr><?php endforeach; ?></table></div>
<div class="card flush"><h3 style="padding:12px 12px 0"><?= e($sf->t('payroll_config')) ?></h3><table class="tbl"><tr><th><?= e($sf->t('f_employee')) ?></th><th></th></tr>
  <?php foreach ($emps as $x): ?><tr><td><?= e($x['full_name']) ?></td><td><a class="btn sm" href="<?= e($sf->url('/employees/' . $x['uuid'])) ?>#pay"><?= e($sf->t('sec_pay')) ?></a></td></tr><?php endforeach; ?></table></div>
