<?php /** @var \Sysfera\Plugin $sf @var \SysferaStoragerent\Ctx $ctx @var string $lang @var array $k @var array $versions @var string $company @var string $company_details */
require_once __DIR__ . '/_h.php'; $t = $k['tenant']; $co = (string)($company ?? ''); $cd = (string)($company_details ?? ''); ?><!doctype html>
<html lang="<?= e($lang) ?>"><head><meta charset="utf-8"><title><?= e($sf->t('print_contract_title', ['n' => $k['number']])) ?></title>
<style>body{font:14px/1.5 "Times New Roman",serif;color:#000;margin:30px auto;max-width:780px;padding:0 16px}h1{font-size:20px;text-align:center}h2{font-size:15px;margin:18px 0 6px}table{width:100%;border-collapse:collapse}td,th{border:1px solid #444;padding:5px 8px;text-align:left}.sign{display:flex;gap:40px;margin-top:40px}.sign div{flex:1;border-top:1px solid #000;padding-top:4px}.warn{border:2px solid #000;padding:8px 12px;margin:10px 0}.noprint{margin-bottom:16px}@media print{.noprint{display:none}}</style></head><body>
<p class="noprint"><button onclick="window.print()"><?= e($sf->t('print_do')) ?></button></p>
<h1><?= e($sf->t('print_contract_title', ['n' => $k['number']])) ?></h1>
<p><?= e($sf->t('print_between')) ?></p>
<p><b><?= e($sf->t('print_landlord')) ?>:</b> <?= e($co !== '' ? $co : '—') ?><?= $cd !== '' ? '<br>' . nl2br(e($cd)) : '' ?></p>
<p><b><?= e($sf->t('print_tenant')) ?>:</b> <?= e($t['name'] ?? '') ?>, <?= e($sf->t('f_tin')) ?> <?= e($t['tin'] ?? '') ?><br><?= e($t['address'] ?? '') ?><br><?= e($t['contact'] ?? '') ?> <?= e($t['phone'] ?? '') ?> <?= e($t['email'] ?? '') ?></p>
<?php if (($t['restrictions'] ?? '') !== ''): ?><div class="warn"><b><?= e($sf->t('restriction')) ?>:</b> <?= e($t['restrictions']) ?></div><?php endif; ?>
<h2>1. <?= e($sf->t('print_subject')) ?></h2>
<table><thead><tr><th><?= e($sf->t('f_name')) ?></th><th>m²</th><th><?= e($sf->t('f_share')) ?></th></tr></thead><tbody><?php foreach ($k['lines'] as $l): ?><tr><td><?= e($l['unit']['name'] ?? '') ?></td><td><?= e(sr_num($l['unit']['area'] ?? 0)) ?></td><td><?= e(sr_num($l['share_pct'])) ?>%</td></tr><?php endforeach; ?></tbody></table>
<h2>2. <?= e($sf->t('print_term')) ?></h2><p><?= e(sr_d($k['start_date'])) ?> – <?= e(sr_d($k['end_date'])) ?>. <?= e($sf->t('f_notice')) ?>: <?= e($sf->t('days_n', ['n' => $k['notice_days']])) ?>.</p>
<h2>3. <?= e($sf->t('print_payment')) ?></h2>
<p><?= e($sf->t('f_rate')) ?>: <?= e(sr_m($k['rate'], $lang)) ?> / <?= e($sf->t('rt_' . $k['rate_type'])) ?> (<?= e(sr_m($k['monthly'], $lang)) ?> <?= e($sf->t('per_month')) ?>). <?= e($sf->t('billing_every', ['n' => $k['billing_months']])) ?>; <?= e($sf->t('f_due_day')) ?>: <?= (int)$k['due_day'] ?>; <?= e($sf->t('f_grace')) ?>: <?= (int)$k['grace_days'] ?>.</p>
<p><?= e($sf->t('f_penalty')) ?>: <?= $k['penalty_type'] === 'none' ? e($sf->t('pt_none')) : e($k['penalty_type'] === 'percent' ? sr_num($k['penalty_value']) . ' % ' . $sf->t('per_day') : sr_m((int)$k['penalty_value'], $lang) . ' ' . $sf->t('per_day')) ?>. <?= e($sf->t('f_deposit')) ?>: <?= e(sr_m($k['deposit'], $lang)) ?>. <?= e($sf->t('f_utilities')) ?>: <?= e($sf->t('um_' . $k['util_method'])) ?><?= $k['util_method'] === 'fixed' ? ' (' . e(sr_m($k['util_fixed'], $lang)) . ')' : '' ?>.</p>
<h2>4. <?= e($sf->t('print_access')) ?></h2><p><?= $k['access_24h'] ? e($sf->t('access_24h')) : e($k['access_from'] . ' – ' . $k['access_to']) ?></p>
<?php if ($k['note'] !== ''): ?><h2>5. <?= e($sf->t('f_note')) ?></h2><p><?= nl2br(e($k['note'])) ?></p><?php endif; ?>
<div class="sign"><div><?= e($sf->t('print_landlord')) ?></div><div><?= e($sf->t('print_tenant')) ?></div></div>
<p style="margin-top:24px;font-size:12px"><?= e($sf->t('print_version', ['v' => count($versions), 'date' => date('d.m.Y')])) ?></p>
</body></html>
