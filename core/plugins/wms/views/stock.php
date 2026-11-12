<?php /** @var \Sysfera\Plugin $sf @var string $tab @var array $rows @var int $total @var array $pg @var array $f @var array $whs @var array $groups */
use SysferaWms\W;
include __DIR__ . '/_nav.php';
$qs = fn(array $o = []) => '?' . http_build_query(array_filter(array_merge(['q' => $f['q'], 'wh' => $f['warehouse_id'], 'only' => $f['only'], 'group' => $f['group_id']], $o), fn($v) => $v !== '' && $v !== 0 && $v !== null));
$sumHand = 0; $sumValue = 0; foreach ($rows as $r) { $sumHand += $r['on_hand']; $sumValue += $r['on_hand'] * $r['buy_price']; } ?>
<div class="page-h"><h1><?= e($sf->t('nav_stock')) ?> <small class="muted"><?= (int)$total ?></small></h1>
  <a class="btn" href="<?= e($sf->url('/stock') . $qs(['csv' => 1])) ?>">⬇ <?= e($sf->t('export_csv')) ?></a></div>

<form class="bar" method="get">
  <input name="q" value="<?= e($f['q']) ?>" placeholder="<?= e($sf->t('search_products')) ?>">
  <select name="wh" onchange="this.form.submit()"><option value=""><?= e($sf->t('all_warehouses')) ?></option><?php foreach ($whs as $w): ?><option value="<?= (int)$w['id'] ?>" <?= (int)$f['warehouse_id'] === (int)$w['id'] ? 'selected' : '' ?>><?= e($w['name']) ?></option><?php endforeach; ?></select>
  <select name="group" onchange="this.form.submit()"><option value=""><?= e($sf->t('all_groups')) ?></option><?php foreach ($groups as $g): ?><option value="<?= (int)$g['id'] ?>" <?= (int)$f['group_id'] === (int)$g['id'] ? 'selected' : '' ?>><?= e($g['name']) ?></option><?php endforeach; ?></select>
  <div class="seg"><?php foreach (['in' => 'f_in', 'low' => 'f_low', 'zero' => 'f_zero', 'all' => 'f_all'] as $k => $l): ?><a class="<?= $f['only'] === $k ? 'on' : '' ?>" href="<?= e($sf->url('/stock') . $qs(['only' => $k, 'page' => ''])) ?>"><?= e($sf->t($l)) ?></a><?php endforeach; ?></div>
  <button class="btn"><?= e($sf->t('find')) ?></button>
</form>

<?php if (!$rows): ?><div class="card empty"><h3><?= e($sf->t('nothing_found')) ?></h3><p><?= e($sf->t('stock_empty_hint')) ?></p><a class="btn primary" href="<?= e($sf->url('/docs/new/receipt')) ?>">＋ <?= e($sf->t('type_receipt')) ?></a></div>
<?php else: ?>
<div class="card flush"><table class="tbl">
  <thead><tr><th><?= e($sf->t('col_name')) ?></th><th><?= e($sf->t('col_sku')) ?></th><th class="num"><?= e($sf->t('col_on_hand')) ?></th><th class="num"><?= e($sf->t('col_reserve')) ?></th><th class="num"><?= e($sf->t('col_expected')) ?></th><th class="num"><?= e($sf->t('col_available')) ?></th><th class="num"><?= e($sf->t('col_value')) ?></th></tr></thead>
  <tbody><?php foreach ($rows as $r): ?>
    <tr onclick="location='<?= e($sf->url('/products/' . $r['id'])) ?>'"><td><b><?= e($r['name']) ?></b></td><td class="muted"><?= e($r['sku'] ?: $r['code']) ?></td>
      <td class="num"><?= e(W::qty($r['on_hand'])) ?> <small class="muted"><?= e($r['unit']) ?></small></td>
      <td class="num <?= (float)$r['reserve'] ? '' : 'muted' ?>"><?= e(W::qty($r['reserve'])) ?></td><td class="num <?= (float)$r['expected'] ? '' : 'muted' ?>"><?= e(W::qty($r['expected'])) ?></td>
      <td class="num"><span class="lvl <?= W::level((float)$r['available'], (float)$r['min_stock']) ?>"><?= e(W::qty($r['available'])) ?></span></td>
      <td class="num"><?= e(W::money($r['on_hand'] * $r['buy_price'])) ?></td></tr>
  <?php endforeach; ?></tbody>
  <tfoot><tr><td colspan="2"><?= e($sf->t('on_page')) ?></td><td class="num"><?= e(W::qty($sumHand)) ?></td><td colspan="3"></td><td class="num"><?= e(W::money($sumValue)) ?></td></tr></tfoot></table></div>
<?php if ($pg['pages'] > 1): ?><div class="pager"><?php for ($i = 1; $i <= $pg['pages']; $i++): ?><a class="<?= $i === $pg['cur'] ? 'on' : '' ?>" href="<?= e($sf->url('/stock') . $qs(['page' => $i])) ?>"><?= $i ?></a><?php endfor; ?></div><?php endif; ?>
<?php endif; ?>
