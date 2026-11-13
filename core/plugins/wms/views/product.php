<?php /** @var \Sysfera\Plugin $sf @var string $tab @var ?array $p @var array $groups @var array $byWh @var array $byCell @var array $history @var array $units */
use SysferaWms\W;
include __DIR__ . '/_nav.php';
$v = fn(string $k, $d = '') => $p[$k] ?? $d; ?>
<div class="page-h"><h1><?= e($p ? $p['name'] : $sf->t('new_product')) ?> <?php if ($p && $p['archived']): ?><span class="badge mute"><?= e($sf->t('archived')) ?></span><?php endif; ?></h1>
  <a class="btn" href="<?= e($sf->url('/products')) ?>">← <?= e($sf->t('nav_products')) ?></a></div>

<div class="cols2 wide-left">
<form class="card" method="post"><?= csrf_field() ?>
  <label class="fld"><span><?= e($sf->t('col_name')) ?> *</span><input name="name" value="<?= e($v('name')) ?>" required maxlength="255" <?= $p ? '' : 'autofocus' ?>></label>
  <div class="cols">
    <label class="fld"><span><?= e($sf->t('col_sku')) ?></span><input name="sku" value="<?= e($v('sku')) ?>" maxlength="80"></label>
    <label class="fld"><span><?= e($sf->t('col_barcode')) ?></span><input name="barcode" value="<?= e($v('barcode')) ?>" maxlength="80" placeholder="<?= e($sf->t('barcode_hint')) ?>"></label>
    <label class="fld"><span><?= e($sf->t('col_code')) ?></span><input name="code" value="<?= e($v('code')) ?>" maxlength="40" placeholder="<?= e($sf->t('auto')) ?>"></label>
    <label class="fld"><span><?= e($sf->t('col_group')) ?></span><select name="group_id"><option value="">—</option><?php foreach ($groups as $g): ?><option value="<?= (int)$g['id'] ?>" <?= (int)$v('group_id', 0) === (int)$g['id'] ? 'selected' : '' ?>><?= e($g['name']) ?></option><?php endforeach; ?></select></label>
  </div>
  <h3><?= e($sf->t('prices')) ?></h3>
  <div class="cols">
    <label class="fld"><span><?= e($sf->t('col_buy_price')) ?>, ₽</span><input name="buy_price" value="<?= e($v('buy_price', '0')) ?>" inputmode="decimal"><small class="muted"><?= e($sf->t('hint_buy_price')) ?></small></label>
    <label class="fld"><span><?= e($sf->t('col_sell_price')) ?>, ₽</span><input name="sell_price" value="<?= e($v('sell_price', '0')) ?>" inputmode="decimal"><small class="muted"><?= e($sf->t('hint_sell_price')) ?></small></label>
    <label class="fld"><span><?= e($sf->t('col_promo_price')) ?>, ₽</span><input name="promo_price" value="<?= e($v('promo_price', '0')) ?>" inputmode="decimal"><small class="muted"><?= e($sf->t('hint_promo_price')) ?></small></label>
    <label class="fld"><span><?= e($sf->t('col_vat')) ?>, %</span><select name="vat"><option value="">—</option><?php foreach (['0', '10', '22'] as $r): ?><option value="<?= $r ?>" <?= $p && $p['vat'] !== null && (string)(int)$p['vat'] === $r ? 'selected' : '' ?>><?= $r ?>%</option><?php endforeach; ?></select></label>
  </div>
  <h3><?= e($sf->t('physical')) ?></h3>
  <div class="cols">
    <label class="fld"><span><?= e($sf->t('col_unit')) ?></span><input name="unit" list="units" value="<?= e($v('unit', 'шт')) ?>" maxlength="20"><datalist id="units"><?php foreach ($units as $u): ?><option value="<?= e($u) ?>"><?php endforeach; ?></datalist></label>
    <label class="fld"><span><?= e($sf->t('col_weight')) ?>, <?= e($sf->t('kg')) ?></span><input name="weight" value="<?= e($v('weight', '0')) ?>" inputmode="decimal"></label>
    <label class="fld"><span><?= e($sf->t('col_volume')) ?>, <?= e($sf->t('m3')) ?></span><input name="volume" value="<?= e($v('volume', '0')) ?>" inputmode="decimal"></label>
    <label class="fld"><span><?= e($sf->t('col_min_stock')) ?></span><input name="min_stock" value="<?= e($v('min_stock', '0')) ?>" inputmode="decimal"><small class="muted"><?= e($sf->t('min_stock_hint')) ?></small></label>
  </div>
  <label class="fld"><span><?= e($sf->t('col_description')) ?></span><textarea name="description" rows="3" maxlength="4000"><?= e($v('description')) ?></textarea></label>
  <div class="row"><button class="btn primary"><?= e($sf->t('save')) ?></button>
    <?php if ($p && !$p['archived']): ?><button class="btn danger" name="do" value="archive" data-confirm="<?= e($sf->t('confirm_archive')) ?>"><?= e($sf->t('archive')) ?></button><?php endif; ?>
    <?php if ($p && $p['archived']): ?><button class="btn" name="do" value="restore"><?= e($sf->t('restore')) ?></button><?php endif; ?></div>
</form>

<?php if ($p): ?>
<div>
  <section class="card"><h3><?= e($sf->t('stock_by_wh')) ?></h3>
    <table class="tbl"><thead><tr><th><?= e($sf->t('warehouse')) ?></th><th class="num"><?= e($sf->t('col_on_hand')) ?></th><th class="num"><?= e($sf->t('col_reserve')) ?></th><th class="num"><?= e($sf->t('col_expected')) ?></th></tr></thead>
    <tbody><?php foreach ($byWh as $r): ?><tr><td><?= e($r['name']) ?></td><td class="num"><?= e(W::qty($r['on_hand'])) ?></td><td class="num"><?= e(W::qty($r['reserve'])) ?></td><td class="num"><?= e(W::qty($r['expected'])) ?></td></tr><?php endforeach; ?></tbody></table>
    <?php if ($byCell): ?><h3><?= e($sf->t('stock_by_cell')) ?></h3><table class="tbl"><tbody><?php foreach ($byCell as $r): ?><tr><td><?= e($r['warehouse']) ?> · <b><?= e($r['cell'] ?? $sf->t('no_cell')) ?></b></td><td class="num"><?= e(W::qty($r['on_hand'])) ?> <?= e($p['unit']) ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
  </section>
  <section class="card"><h3><?= e($sf->t('history')) ?></h3>
    <?php if (!$history): ?><p class="muted"><?= e($sf->t('no_history')) ?></p><?php else: ?>
    <table class="tbl"><tbody><?php foreach ($history as $h): $d = (float)$h['on_hand']; ?>
      <tr onclick="location='<?= e($sf->url('/docs/' . $h['doc_id'])) ?>'"><td><small class="muted"><?= e(W::date($h['moment'])) ?></small><br><?= e(W::type($sf, $h['type'])) ?> <b><?= e($h['number']) ?></b></td>
      <td class="num"><?php if ($d != 0.0): ?><span class="<?= $d > 0 ? 'ok-t' : 'bad-t' ?>"><?= $d > 0 ? '+' : '' ?><?= e(W::qty($d)) ?></span><?php else: ?><span class="muted"><?= (float)$h['reserve'] != 0.0 ? e($sf->t('col_reserve')) . ' ' . e(W::qty($h['reserve'])) : e($sf->t('col_expected')) . ' ' . e(W::qty($h['expected'])) ?></span><?php endif; ?></td></tr>
    <?php endforeach; ?></tbody></table><?php endif; ?>
  </section>
</div>
<?php endif; ?>
</div>
