<?php /** @var array $counts @var array $plugins @var array $widgets @var bool $isAdmin */ ?>
<div class="page-h"><h1><?= e(t('dash.title')) ?></h1></div>
<div class="stats">
  <div class="card stat"><small><?= e(t('dash.clients')) ?></small><b><?= (int)$counts['contacts'] ?></b></div>
  <div class="card stat"><small><?= e(t('dash.orders')) ?></small><b><?= (int)$counts['orders'] ?></b></div>
  <div class="card stat"><small><?= e(t('dash.invoices')) ?></small><b><?= (int)$counts['invoices'] ?></b></div>
  <div class="card stat"><small><?= e(t('dash.components')) ?></small><b><?= count($plugins) ?></b></div>
</div>
<?php foreach ($widgets as $w): ?><section class="card"><?= $w ?></section><?php endforeach; ?>
<?php if (!$plugins): ?>
  <section class="card hint"><h3><?= e(t('dash.empty_title')) ?></h3><p><?= e(t($isAdmin ? 'dash.empty_admin' : 'dash.empty_user')) ?></p>
    <?php if ($isAdmin): ?><a class="btn primary" href="<?= e(url('/plugins')) ?>"><?= e(t('nav.plugins')) ?></a><?php endif; ?></section>
<?php else: ?>
  <h2><?= e(t('dash.your_components')) ?></h2>
  <div class="tiles"><?php foreach ($plugins as $slug => $m): $first = null; ?>
    <a class="card tile" href="<?= e(url('/p/' . $slug . '/')) ?>"><h3><?= e(\Sysfera\Plugins::text($m, 'name')) ?></h3><p class="muted"><?= e(\Sysfera\Plugins::text($m, 'description')) ?></p><small class="muted">v<?= e($m['version']) ?></small></a>
  <?php endforeach; ?></div>
<?php endif; ?>
