<?php /** @var \Sysfera\Plugin $sf @var string $tab @var array $totals @var array $low @var array $recent @var array $open @var int $drafts */
use SysferaWms\W;
include __DIR__ . '/_nav.php'; ?>
<div class="page-h"><h1><?= e($sf->t('title')) ?></h1>
  <div class="actions">
    <a class="btn" href="<?= e($sf->url('/docs/new/receipt')) ?>">＋ <?= e($sf->t('type_receipt')) ?></a>
    <a class="btn" href="<?= e($sf->url('/docs/new/sale')) ?>">＋ <?= e($sf->t('type_sale')) ?></a>
    <a class="btn primary" href="<?= e($sf->url('/products/new')) ?>">＋ <?= e($sf->t('new_product')) ?></a>
  </div></div>

<div class="kpis">
  <div class="card kpi"><small><?= e($sf->t('kpi_value')) ?></small><b><?= e(W::money($totals['value'])) ?></b><span class="muted sm"><?= e($sf->t('kpi_value_hint')) ?></span></div>
  <div class="card kpi"><small><?= e($sf->t('kpi_products')) ?></small><b><?= (int)$totals['products'] ?></b><span class="muted sm"><?= e($sf->t('kpi_units', ['n' => W::qty($totals['qty'])])) ?></span></div>
  <div class="card kpi"><small><?= e($sf->t('col_reserve')) ?></small><b><?= e(W::qty($totals['reserve'])) ?></b><span class="muted sm"><?= e($sf->t('kpi_reserve_hint')) ?></span></div>
  <div class="card kpi"><small><?= e($sf->t('col_expected')) ?></small><b><?= e(W::qty($totals['expected'])) ?></b><span class="muted sm"><?= e($sf->t('kpi_expected_hint')) ?></span></div>
  <a class="card kpi <?= $totals['low'] ? 'warn' : '' ?>" href="<?= e($sf->url('/stock?only=low')) ?>"><small><?= e($sf->t('kpi_low')) ?></small><b><?= (int)$totals['low'] ?></b><span class="muted sm"><?= e($sf->t('kpi_low_hint')) ?></span></a>
</div>

<?php if ($open['purchase'] || $open['sale'] || $drafts): ?>
<div class="todo">
  <?php if ($open['sale']): ?><a class="chip" href="<?= e($sf->url('/docs?type=sale&status=posted')) ?>">🛒 <?= e($sf->t('todo_ship', ['n' => $open['sale']])) ?></a><?php endif; ?>
  <?php if ($open['purchase']): ?><a class="chip" href="<?= e($sf->url('/docs?type=purchase&status=posted')) ?>">📥 <?= e($sf->t('todo_receive', ['n' => $open['purchase']])) ?></a><?php endif; ?>
  <?php if ($drafts): ?><a class="chip" href="<?= e($sf->url('/docs?status=draft')) ?>">✎ <?= e($sf->t('todo_drafts', ['n' => $drafts])) ?></a><?php endif; ?>
</div>
<?php endif; ?>

<div class="cols2">
  <section class="card">
    <div class="row between"><h3><?= e($sf->t('recent_docs')) ?></h3><a class="sm" href="<?= e($sf->url('/docs')) ?>"><?= e($sf->t('all')) ?> →</a></div>
    <?php if (!$recent): ?><p class="muted"><?= e($sf->t('no_docs_yet')) ?></p>
      <p class="sm"><?= e($sf->t('start_hint')) ?></p>
    <?php else: ?><table class="tbl"><tbody><?php foreach ($recent as $d): ?>
      <tr onclick="location='<?= e($sf->url('/docs/' . $d['id'])) ?>'"><td><b><?= e($d['number']) ?></b><br><small class="muted"><?= e(W::type($sf, $d['type'])) ?></small></td><td><?= e($d['contact'] ?? $d['warehouse'] ?? '') ?></td><td class="num"><?= e(W::money($d['total'])) ?></td><td><?= W::badge($sf, $d['status']) ?></td></tr>
    <?php endforeach; ?></tbody></table><?php endif; ?>
  </section>
  <section class="card">
    <div class="row between"><h3><?= e($sf->t('low_stock')) ?></h3><a class="sm" href="<?= e($sf->url('/stock?only=low')) ?>"><?= e($sf->t('all')) ?> →</a></div>
    <?php if (!$low): ?><p class="muted"><?= e($sf->t('low_none')) ?></p><p class="sm"><?= e($sf->t('low_hint')) ?></p>
    <?php else: ?><table class="tbl"><tbody><?php foreach ($low as $r): ?>
      <tr onclick="location='<?= e($sf->url('/products/' . $r['id'])) ?>'"><td><b><?= e($r['name']) ?></b><br><small class="muted"><?= e($r['sku']) ?></small></td><td class="num"><span class="lvl <?= W::level((float)$r['available'], (float)$r['min_stock']) ?>"><?= e(W::qty($r['available'])) ?></span> <small class="muted">/ <?= e(W::qty($r['min_stock'])) ?> <?= e($r['unit']) ?></small></td></tr>
    <?php endforeach; ?></tbody></table><?php endif; ?>
  </section>
</div>
