<?php /** @var \Sysfera\Plugin $sf @var string $tab @var array $counts @var array $incidents @var array $transfers @var array $whs @var string $appUrl @var int $ops */
include __DIR__ . '/_nav.php'; ?>
<div class="page-h"><h1><?= e($sf->t('menu')) ?></h1><a class="btn" href="<?= e($appUrl) ?>" target="_blank"><?= e($sf->t('open_app')) ?></a></div>
<?php if (!$whs || !$ops): ?><div class="hint"><b><?= e($sf->t('setup_h')) ?></b> <?= e($sf->t('setup_t')) ?> <a href="<?= e($sf->url('/warehouses')) ?>"><?= e($sf->t('nav_warehouses')) ?></a></div><?php endif; ?>
<div class="gi-tiles"><?php foreach (['transferred', 'ready_for_issue', 'blocked', 'issued', 'return_requested', 'returned'] as $s): ?>
  <a class="card gi-tile" href="<?= e($sf->url('/orders?status=' . $s)) ?>"><b><?= (int)($counts[$s] ?? 0) ?></b><span class="<?= e($stc($s)) ?>"><?= e($sf->t('st_' . $s)) ?></span></a><?php endforeach; ?></div>
<?php if (!empty($pend['returns']) || !empty($pend['adjust']) || !empty($pend['refunds'])): ?><div class="flash warn"><?php if (!empty($pend['returns'])): ?><a href="<?= e($sf->url('/returns')) ?>"><?= e($sf->t('pend_returns', ['n' => $pend['returns']])) ?></a> <?php endif; ?>
  <?php if (!empty($pend['refunds'])): ?><a href="<?= e($sf->url('/returns')) ?>"><?= e($sf->t('pend_refunds', ['n' => $pend['refunds']])) ?></a> <?php endif; ?>
  <?php if (!empty($pend['adjust'])): ?><a href="<?= e($sf->url('/stocktake')) ?>"><?= e($sf->t('pend_adjust', ['n' => $pend['adjust']])) ?></a><?php endif; ?></div><?php endif; ?>
<div class="cols">
  <div class="card"><h3><?= e($sf->t('open_incidents')) ?></h3>
    <?php if (!$incidents): ?><p class="muted"><?= e($sf->t('none')) ?></p><?php endif; ?>
    <?php foreach ($incidents as $i): ?><p><span class="gi-sev gi-sev-<?= e($i['severity']) ?>"><?= e($sf->t('inc_' . $i['kind'])) ?></span> <span class="muted"><?= e($i['wname'] ?? '') ?> · <?= e($i['created_at']) ?></span></p><?php endforeach; ?>
    <a href="<?= e($sf->url('/incidents')) ?>"><?= e($sf->t('all_incidents')) ?></a></div>
  <div class="card"><h3><?= e($sf->t('nav_transfers')) ?></h3>
    <?php if (!$transfers): ?><p class="muted"><?= e($sf->t('none')) ?></p><?php endif; ?>
    <?php foreach ($transfers as $t): ?><p><a href="<?= e($sf->url('/transfers/' . $t['uuid'])) ?>"><code><?= e(substr($t['uuid'], 0, 8)) ?></code></a> <span class="<?= e($stc($t['status'])) ?>"><?= e($sf->t('tst_' . $t['status'])) ?></span> <span class="muted"><?= e($t['wname'] ?? '') ?> · <?= (int)$t['orders'] ?> <?= e($sf->t('orders_n')) ?></span></p><?php endforeach; ?>
    <a href="<?= e($sf->url('/transfers')) ?>"><?= e($sf->t('nav_transfers')) ?></a></div>
</div>
