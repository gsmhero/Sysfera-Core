<?php /** @var \Sysfera\Plugin $sf @var string $tab @var array $rows @var int $total @var array $pg @var array $f @var array $groups */
use SysferaWms\W;
include __DIR__ . '/_nav.php';
$qs = fn(array $o = []) => '?' . http_build_query(array_filter(array_merge(['q' => $f['q'], 'group' => $f['group_id'], 'only' => $f['only'] === 'all' ? '' : $f['only']], $o), fn($v) => $v !== '' && $v !== 0 && $v !== null)); ?>
<div class="page-h"><h1><?= e($sf->t('nav_products')) ?> <small class="muted"><?= (int)$total ?></small></h1>
  <a class="btn primary" href="<?= e($sf->url('/products/new')) ?>">＋ <?= e($sf->t('new_product')) ?></a></div>

<form class="bar" method="get">
  <input name="q" value="<?= e($f['q']) ?>" placeholder="<?= e($sf->t('search_products')) ?>" autofocus>
  <select name="group" onchange="this.form.submit()"><option value=""><?= e($sf->t('all_groups')) ?></option><?php foreach ($groups as $g): ?><option value="<?= (int)$g['id'] ?>" <?= (int)$f['group_id'] === (int)$g['id'] ? 'selected' : '' ?>><?= e($g['name']) ?> (<?= (int)$g['n'] ?>)</option><?php endforeach; ?></select>
  <select name="only" onchange="this.form.submit()"><?php foreach (['all' => 'f_all', 'in' => 'f_in', 'low' => 'f_low', 'zero' => 'f_zero'] as $k => $l): ?><option value="<?= $k ?>" <?= $f['only'] === $k ? 'selected' : '' ?>><?= e($sf->t($l)) ?></option><?php endforeach; ?></select>
  <button class="btn"><?= e($sf->t('find')) ?></button>
</form>

<?php if (!$rows): ?>
  <div class="card empty"><h3><?= e($sf->t($f['q'] !== '' || $f['group_id'] ? 'nothing_found' : 'no_products')) ?></h3>
    <?php if ($f['q'] === '' && !$f['group_id']): ?><p><?= e($sf->t('no_products_hint')) ?></p><a class="btn primary" href="<?= e($sf->url('/products/new')) ?>">＋ <?= e($sf->t('new_product')) ?></a><?php endif; ?></div>
<?php else: ?>
<div class="card flush"><table class="tbl">
  <thead><tr><th><?= e($sf->t('col_name')) ?></th><th><?= e($sf->t('col_sku')) ?></th><th><?= e($sf->t('col_group')) ?></th><th class="num"><?= e($sf->t('col_on_hand')) ?></th><th class="num"><?= e($sf->t('col_available')) ?></th><th class="num"><?= e($sf->t('col_sell_price')) ?></th></tr></thead>
  <tbody><?php foreach ($rows as $r): ?>
    <tr onclick="location='<?= e($sf->url('/products/' . $r['id'])) ?>'">
      <td><b><?= e($r['name']) ?></b><?php if ($r['barcode']): ?><br><small class="muted">▮ <?= e($r['barcode']) ?></small><?php endif; ?></td>
      <td><?= e($r['sku'] ?: $r['code']) ?></td><td class="muted"><?= e($r['group_name'] ?? '—') ?></td>
      <td class="num"><?= e(W::qty($r['on_hand'])) ?> <small class="muted"><?= e($r['unit']) ?></small></td>
      <td class="num"><span class="lvl <?= W::level((float)$r['available'], (float)$r['min_stock']) ?>"><?= e(W::qty($r['available'])) ?></span></td>
      <td class="num"><?= e(W::money($r['sell_price'])) ?></td></tr>
  <?php endforeach; ?></tbody></table></div>
<?php if ($pg['pages'] > 1): ?><div class="pager"><?php for ($i = 1; $i <= $pg['pages']; $i++): ?><a class="<?= $i === $pg['cur'] ? 'on' : '' ?>" href="<?= e($sf->url('/products') . $qs(['page' => $i])) ?>"><?= $i ?></a><?php endfor; ?></div><?php endif; ?>
<?php endif; ?>

<details class="card groups"><summary><?= e($sf->t('groups')) ?> <small class="muted">(<?= count($groups) ?>)</small></summary>
  <p class="muted sm"><?= e($sf->t('groups_hint')) ?></p>
  <div class="chips"><?php foreach ($groups as $g): ?>
    <form method="post" class="chip"><?= csrf_field() ?><input type="hidden" name="do" value="group_delete"><input type="hidden" name="id" value="<?= (int)$g['id'] ?>"><a href="<?= e($sf->url('/products?group=' . $g['id'])) ?>"><?= e($g['name']) ?></a> <small class="muted"><?= (int)$g['n'] ?></small> <button class="x" title="<?= e($sf->t('delete')) ?>" data-confirm="<?= e($sf->t('confirm_group_delete')) ?>">×</button></form>
  <?php endforeach; ?></div>
  <form method="post" class="row"><?= csrf_field() ?><input type="hidden" name="do" value="group"><input name="name" placeholder="<?= e($sf->t('group_name')) ?>" required maxlength="160"><button class="btn"><?= e($sf->t('add')) ?></button></form>
</details>
