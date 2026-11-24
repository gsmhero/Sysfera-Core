<?php /** @var \Sysfera\Plugin $sf @var string $lang @var string $name @var array $t @var string $from @var string $to */
require_once __DIR__ . '/_h.php'; $money = \SysferaStoragerent\Reports::MONEY; ?><!doctype html>
<html lang="<?= e($lang) ?>"><head><meta charset="utf-8"><title><?= e($sf->t('rep_' . $name)) ?></title>
<style>body{font:13px/1.5 Arial,sans-serif;color:#000;margin:24px}table{width:100%;border-collapse:collapse}td,th{border:1px solid #444;padding:4px 7px;text-align:left}.r{text-align:right}.noprint{margin-bottom:12px}@media print{.noprint{display:none}}</style></head><body>
<p class="noprint"><button onclick="window.print()"><?= e($sf->t('print_do')) ?></button></p>
<h2><?= e($sf->t('rep_' . $name)) ?></h2><p><?= e(sr_d($from)) ?> – <?= e(sr_d($to)) ?></p>
<table><thead><tr><?php foreach ($t['cols'] as $c): ?><th class="<?= in_array($c, $money, true) ? 'r' : '' ?>"><?= e($sf->t('col_' . $c)) ?></th><?php endforeach; ?></tr></thead><tbody>
<?php foreach ($t['rows'] as $r): ?><tr><?php foreach ($t['cols'] as $c): $v = $r[$c]; ?><td class="<?= in_array($c, $money, true) ? 'r' : '' ?>"><?php
  if (in_array($c, $money, true)) echo e(sr_m($v, $lang)); elseif ($c === 'due_date') echo e(sr_d((string)$v)); elseif ($c === 'status') echo e($sf->t('st_' . $v)); elseif ($c === 'bucket') echo e($sf->t('bucket_' . $v)); else echo e(is_float($v) ? sr_num($v) : (string)$v); ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody>
<?php if ($t['sum']): ?><tfoot><tr><?php foreach ($t['cols'] as $i => $c): ?><td class="<?= isset($t['sum'][$c]) ? 'r' : '' ?>"><b><?= $i === 0 ? e($sf->t('total')) : (isset($t['sum'][$c]) ? e(sr_m($t['sum'][$c], $lang)) : '') ?></b></td><?php endforeach; ?></tr></tfoot><?php endif; ?></table>
</body></html>
