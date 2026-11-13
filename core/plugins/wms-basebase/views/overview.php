<?php /** @var \Sysfera\Plugin $sf @var string $tab @var array $stats @var array $attention @var array $badProducts @var \SysferaBb\Client $client @var \SysferaBb\Sync $sync @var callable $cfg */
include __DIR__ . '/_nav.php';
$ready = $sync->ready(); ?>
<div class="page-h"><h1><?= e($sf->t('menu')) ?></h1>
  <?php if ($ready): ?><form method="post" action="<?= e($sf->url('/orders')) ?>"><?= csrf_field() ?><input type="hidden" name="do" value="pull"><button class="btn">↻ <?= e($sf->t('pull_now')) ?></button></form><?php endif; ?></div>

<?php if (!$ready): ?>
  <div class="card bb-setup"><h3><?= e($sf->t('setup_title')) ?></h3>
    <ol class="steps-l">
      <li class="<?= $client->configured() ? 'done' : '' ?>"><?= e($sf->t('step_connect')) ?></li>
      <li class="<?= $sync->sellerId() ? 'done' : '' ?>"><?= e($sf->t('step_seller')) ?></li>
      <li><?= e($sf->t('step_publish')) ?></li>
    </ol>
    <?php if ($sf->can('wmsbb.admin')): ?><a class="btn primary" href="<?= e($sf->url('/settings')) ?>"><?= e($sf->t('open_settings')) ?> →</a><?php else: ?><p class="muted"><?= e($sf->t('ask_admin')) ?></p><?php endif; ?>
  </div>
<?php else: ?>
<div class="kpis">
  <div class="kpi"><small><?= e($sf->t('kpi_published')) ?></small><b><?= (int)$stats['published'] ?></b><span><a href="<?= e($sf->url('/catalog')) ?>"><?= e($sf->t('manage')) ?> →</a></span></div>
  <div class="kpi <?= $stats['errors'] ? 'warn' : '' ?>"><small><?= e($sf->t('kpi_errors')) ?></small><b><?= (int)$stats['errors'] ?></b><span><?= e($sf->t('kpi_errors_hint')) ?></span></div>
  <?php if ($see): ?><div class="kpi"><small><?= e($sf->t('kpi_open')) ?></small><b><?= (int)$stats['open'] ?></b><span><?= e($sf->t('kpi_open_hint')) ?></span></div>
  <div class="kpi"><small><?= e($sf->t('kpi_orders')) ?></small><b><?= (int)$stats['orders'] ?></b><span><a href="<?= e($sf->url('/orders')) ?>"><?= e($sf->t('nav_orders')) ?> →</a></span></div><?php endif; ?>
</div>
<p class="muted sm"><?= e($sf->t('last_poll')) ?>: <?= (int)$cfg('last_poll', '0') ? e(date('d.m.Y H:i', (int)$cfg('last_poll'))) : '—' ?> · <?= e($sf->t('flow_hint')) ?></p>

<?php if ($attention || $badProducts): ?>
<h2><?= e($sf->t('attention')) ?></h2>
<div class="card flush"><table class="tbl"><tbody>
  <?php foreach ($attention as $a): ?><tr onclick="location='<?= e($sf->url('/orders')) ?>'"><td><b>#<?= (int)$a['order_id'] ?></b> <small class="muted"><?= e($a['title']) ?> × <?= e(\SysferaWms\W::qty($a['qty'])) ?></small></td><td class="bad-t"><?= e($why($a['error'])) ?></td></tr><?php endforeach; ?>
  <?php foreach ($badProducts as $b): ?><tr onclick="location='<?= e($sf->url('/catalog')) ?>'"><td><b><?= e($b['name']) ?></b></td><td class="bad-t"><?= e($why($b['last_error'])) ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
<?php endif; ?>
