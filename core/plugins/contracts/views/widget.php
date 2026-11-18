<?php /** Dashboard widget. Vars: $g {new: cards from Svc::listing, st: counts of the user's contracts} */
use SysferaContracts\Svc;
$st = $g['st']; $cell = function (string $label, int $n, string $path, string $cls = '') use ($sf): string {
    return '<a class="ctw-c' . ($cls !== '' && $n > 0 ? ' ' . $cls : '') . '" href="' . e($sf->url($path)) . '"><b>' . $n . '</b><small>' . e($label) . '</small></a>'; };
?>
<link rel="stylesheet" href="<?= e($sf->asset('contracts.css')) ?>?v=<?= e($sf->manifest['version']) ?>">
<div class="row between"><b><?= e($sf->t('title')) ?></b><a class="sm" href="<?= e($sf->url('/')) ?>"><?= e($sf->t('w_open')) ?> →</a></div>
<h4 class="ctw-h"><?= e($sf->t('w_new')) ?></h4>
<?php if (!$g['new']): ?><p class="muted sm"><?= e($sf->t('w_none')) ?></p><?php else: ?>
<ul class="ctw-l"><?php foreach ($g['new'] as $k): $href = $sf->url(!empty($k['remote']) ? '/remote/' . $k['remote_id'] : '/view/' . $k['id']); ?>
  <li><span class="ctw-t"><a href="<?= e($href) ?>"><?= e($k['title']) ?></a><small class="muted"><?= e((string)($k['category_ru'] !== '' && ($sf->t('w_lang') === 'ru') ? $k['category_ru'] : $k['category'])) ?><?= !empty($k['remote']) ? ' · ' . e($sf->t('remote_badge')) : '' ?> · <?= e(substr((string)$k['created_at'], 0, 10)) ?></small></span>
    <span class="ctw-p"><?= e(Svc::symbol($k['currency'])) ?> <?= e(number_format((float)$k['total_price'], 0, '.', ' ')) ?></span></li>
<?php endforeach; ?></ul><?php endif; ?>
<h4 class="ctw-h"><?= e($sf->t('nav_mine')) ?></h4>
<div class="ctw-g">
  <?= $cell($sf->t('w_created'), (int)$st['created'], '/mine?tab=created') ?>
  <?= $cell($sf->t('w_working'), (int)$st['working'], '/mine?tab=working') ?>
  <?= $cell($sf->t('w_offers'), (int)$st['offers'], '/mine?tab=offers') ?>
  <?= $cell($sf->t('w_archive'), (int)$st['archive'], '/mine?tab=archive') ?>
</div>
<?php if ((int)$st['new_offers'] > 0 || (int)$st['unpaid'] > 0): ?><p class="sm ctw-note"><?php if ((int)$st['new_offers'] > 0): ?><a href="<?= e($sf->url('/mine?tab=created')) ?>"><?= e($sf->t('w_new_offers', ['n' => (int)$st['new_offers']])) ?></a><?php endif; ?>
  <?php if ((int)$st['unpaid'] > 0): ?><a href="<?= e($sf->url('/invoices')) ?>"><?= e($sf->t('w_unpaid', ['n' => (int)$st['unpaid']])) ?></a><?php endif; ?></p><?php endif; ?>