<?php /** @var array $counts @var array $plugins @var array $widgets @var bool $isAdmin */ ?>
<div class="page-h"><h1><?= e(t('dash.title')) ?></h1></div>
<div class="stats">
  <?php if (!empty($widgets['wms'])): ?><div class="card wdg stat-w"><?= $widgets['wms'] ?></div><?php endif; ?>
  <div class="card stat"><small><?= e(t('dash.orders')) ?></small><b><?= (int)$counts['orders'] ?></b></div>
  <div class="card stat"><small><?= e(t('dash.invoices')) ?></small><b><?= (int)$counts['invoices'] ?></b></div>
  <div class="card stat"><small><?= e(t('dash.clients')) ?></small><b><?= (int)$counts['contacts'] ?></b></div>
</div>
<?php $side = array_filter([$widgets['activity'] ?? '', $widgets['notes'] ?? '']); $main = array_diff_key($widgets, ['wms' => 1, 'activity' => 1, 'notes' => 1]); ?>
<?php if ($main || $side): ?><div class="dash-cols">
  <div class="dash-main"><?php foreach ($main as $w): ?><section class="card wdg"><?= $w ?></section><?php endforeach; ?></div>
  <?php if ($side): ?><div class="dash-side"><?php foreach ($side as $w): ?><section class="card wdg"><?= $w ?></section><?php endforeach; ?></div><?php endif; ?>
</div><?php endif; ?>
<?php if (!$plugins): ?>
  <section class="card hint"><h3><?= e(t('dash.empty_title')) ?></h3><p><?= e(t($isAdmin ? 'dash.empty_admin' : 'dash.empty_user')) ?></p>
    <?php if ($isAdmin): ?><a class="btn primary" href="<?= e(url('/plugins')) ?>"><?= e(t('nav.plugins')) ?></a><?php endif; ?></section>
<?php endif; ?>
