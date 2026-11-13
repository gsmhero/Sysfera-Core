<?php /** @var \Sysfera\Plugin $sf @var string $tab @var array $rows @var string $q @var string $only @var int $total @var array $pg @var array $cats @var \SysferaBb\Sync $sync */
use SysferaWms\W;
include __DIR__ . '/_nav.php'; ?>
<div class="page-h"><h1><?= e($sf->t('nav_catalog')) ?> <small class="muted"><?= (int)$total ?></small></h1>
  <?php if ($sync->ready()): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="sync_all"><button class="btn">↻ <?= e($sf->t('sync_all')) ?></button></form><?php endif; ?></div>
<div class="hint"><?= e($sf->t('catalog_hint')) ?></div>
<?php if (!$sync->ready()): ?><div class="card warnbox"><?= e($sf->t('not_connected_note')) ?> <a href="<?= e($sf->url('/settings')) ?>"><?= e($sf->t('open_settings')) ?></a></div><?php endif; ?>

<form class="bar" method="get">
  <div class="seg"><a class="<?= $only === '' ? 'on' : '' ?>" href="<?= e($sf->url('/catalog')) ?>"><?= e($sf->t('all')) ?></a><a class="<?= $only === 'pub' ? 'on' : '' ?>" href="<?= e($sf->url('/catalog?only=pub')) ?>"><?= e($sf->t('only_published')) ?></a></div>
  <input name="q" value="<?= e($q) ?>" placeholder="<?= e($sf->t('search')) ?>"><input type="hidden" name="only" value="<?= e($only) ?>"><button class="btn"><?= e($sf->t('find')) ?></button>
</form>

<?php if (!$rows): ?><div class="card empty"><h3><?= e($sf->t('no_products')) ?></h3><p><?= e($sf->t('no_products_hint')) ?></p></div>
<?php else: ?>
<datalist id="bbcats"><?php foreach ($cats as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist>
<form method="post"><?= csrf_field() ?>
<div class="card flush"><table class="tbl bbcat">
  <thead><tr><th style="width:44px"><?= e($sf->t('sell')) ?></th><th><?= e($sf->t('product')) ?></th><th><?= e($sf->t('category')) ?></th><th class="num"><?= e($sf->t('price')) ?></th><th class="num"><?= e($sf->t('on_sale')) ?></th><th><?= e($sf->t('state')) ?></th></tr></thead>
  <tbody><?php foreach ($rows as $r): $id = (int)$r['id']; ?>
    <tr>
      <td><input type="checkbox" name="row[<?= $id ?>][pub]" value="1" <?= !empty($r['published']) ? 'checked' : '' ?>></td>
      <td><b><?= e($r['name']) ?></b><br><small class="muted"><?= e($r['external_id'] ?: ($r['sku'] ?: $r['code'])) ?></small>
        </td>
      <td><input name="row[<?= $id ?>][cat]" list="bbcats" value="<?= e($r['category'] ?? '') ?>" maxlength="120" placeholder="<?= e($sf->t('category_ph')) ?>"></td>
      <td class="num"><?= $r['sell_price'] > 0 ? e(W::money($r['sell_price'])) : '<span class="bad-t">' . e($sf->t('no_price')) . '</span>' ?></td>
      <td class="num"><?= e(W::qty($r['available'])) ?> <small class="muted"><?= e($r['unit']) ?></small></td>
      <td><?php if (!empty($r['last_error'])): ?><span class="badge bad" title="<?= e($why($r['last_error'])) ?>"><?= e($sf->t('state_error')) ?></span><br><small class="bad-t"><?= e($why($r['last_error'])) ?></small>
          <?php elseif (!empty($r['published']) && $r['synced_at']): ?><span class="badge ok"><?= e($sf->t('state_live')) ?></span><br><small class="muted"><?= e(W::date($r['synced_at'])) ?></small>
          <?php elseif (!empty($r['published'])): ?><span class="badge warn"><?= e($sf->t('state_wait')) ?></span>
          <?php else: ?><span class="muted">—</span><?php endif; ?></td></tr>
  <?php endforeach; ?></tbody></table></div>
<?php if ($manage): ?><div class="actionbar"><span class="muted sm"><?= e($sf->t('save_hint')) ?></span><button class="btn primary"><?= e($sf->t('save_publish')) ?></button></div><?php else: ?><div class="hint warn"><?= e($sf->t('read_only')) ?></div><?php endif; ?>
</form>
<?php if ($pg['pages'] > 1): ?><div class="pager"><?php for ($i = 1; $i <= $pg['pages']; $i++): ?><a class="<?= $i === $pg['cur'] ? 'on' : '' ?>" href="<?= e($sf->url('/catalog') . '?' . http_build_query(['q' => $q, 'only' => $only, 'page' => $i])) ?>"><?= $i ?></a><?php endfor; ?></div><?php endif; ?>
<?php endif; ?>
