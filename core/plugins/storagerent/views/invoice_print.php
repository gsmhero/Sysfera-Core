<?php /** @var \Sysfera\Plugin $sf @var \SysferaStoragerent\Ctx $ctx @var string $lang @var array $i @var array $items @var array $tenant @var ?array $contract @var string $company @var string $company_details */
require_once __DIR__ . '/_h.php'; $tt = fn(string $k, array $v = []) => $sf->t($k, $v); $co = (string)($company ?? ''); $cd = (string)($company_details ?? ''); ?><!doctype html>
<html lang="<?= e($lang) ?>"><head><meta charset="utf-8"><title><?= e($sf->t('print_invoice_title', ['n' => $i['number']])) ?></title>
<style>body{font:14px/1.5 Arial,sans-serif;color:#000;margin:30px auto;max-width:780px;padding:0 16px}h1{font-size:20px}table{width:100%;border-collapse:collapse;margin:12px 0}td,th{border:1px solid #444;padding:5px 8px;text-align:left}.r{text-align:right}.noprint{margin-bottom:16px}@media print{.noprint{display:none}}</style></head><body>
<p class="noprint"><button onclick="window.print()"><?= e($sf->t('print_do')) ?></button></p>
<h1><?= e($sf->t('print_invoice_title', ['n' => $i['number']])) ?></h1>
<p><?= e($sf->t('col_issue_date')) ?>: <?= e(sr_d($i['issue_date'])) ?> · <?= e($sf->t('col_due_date')) ?>: <b><?= e(sr_d($i['due_date'])) ?></b></p>
<p><b><?= e($sf->t('print_landlord')) ?>:</b> <?= e($co !== '' ? $co : '—') ?><?= $cd !== '' ? '<br>' . nl2br(e($cd)) : '' ?></p>
<p><b><?= e($sf->t('print_tenant')) ?>:</b> <?= e($tenant['name'] ?? '') ?>, <?= e($sf->t('f_tin')) ?> <?= e($tenant['tin'] ?? '') ?><br><?= e($tenant['address'] ?? '') ?></p>
<p><?= e($sf->t('col_contract')) ?>: <?= e($i['contract_number']) ?><?= $i['period_start'] ? ' · ' . e(sr_d($i['period_start'])) . ' – ' . e(sr_d($i['period_end'])) : '' ?></p>
<table><thead><tr><th><?= e($sf->t('f_description')) ?></th><th class="r"><?= e($sf->t('col_qty')) ?></th><th class="r"><?= e($sf->t('f_amount')) ?></th></tr></thead><tbody>
<?php foreach ($items as $it): ?><tr><td><?= e(\SysferaStoragerent\Text::item($it, $tt)) ?></td><td class="r"><?= e(sr_num($it['qty'])) ?></td><td class="r"><?= e(sr_m($it['amount'], $lang)) ?></td></tr><?php endforeach; ?></tbody>
<tfoot><tr><td colspan="2"><?= e($sf->t('inv_net')) ?></td><td class="r"><?= e(sr_m($i['net'], $lang)) ?></td></tr><tr><td colspan="2"><?= e($sf->t('inv_vat')) ?><?= ($tenant['vat_mode'] ?? 'none') === 'none' ? ' (' . e($sf->t('inv_no_vat')) . ')' : ' ' . e(sr_num($tenant['vat_rate'] ?? 0)) . '%' ?></td><td class="r"><?= e(sr_m($i['vat'], $lang)) ?></td></tr>
<tr><td colspan="2"><b><?= e($sf->t('inv_to_pay')) ?></b></td><td class="r"><b><?= e(sr_m($i['total'], $lang)) ?></b></td></tr></tfoot></table>
</body></html>
