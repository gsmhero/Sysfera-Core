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
  <?= \Sysfera\Hooks::filter('wms.product.fields', '', $p) ?>
  <h3><?= e($sf->t('prices')) ?></h3>
  <div class="cols">
    <label class="fld"><span><?= e($sf->t('col_buy_price')) ?>, ₽</span><input name="buy_price" value="<?= e($v('buy_price', '0')) ?>" inputmode="decimal"><small class="muted"><?= e($sf->t('hint_buy_price')) ?></small></label>
    <label class="fld"><span><?= e($sf->t('col_sell_price')) ?>, ₽</span><input name="sell_price" value="<?= e($v('sell_price', '0')) ?>" inputmode="decimal"><small class="muted"><?= e($sf->t('hint_sell_price')) ?></small></label>
    <label class="fld"><span><?= e($sf->t('col_promo_price')) ?>, ₽</span><input name="promo_price" value="<?= e($v('promo_price', '0')) ?>" inputmode="decimal"><small class="muted"><?= e($sf->t('hint_promo_price')) ?></small></label>
    <label class="fld"><span><?= e($sf->t('col_vat')) ?></span><select name="vat"><option value="">—</option><?php foreach (['0', '10', '22'] as $r): ?><option value="<?= $r ?>" <?= $p && $p['vat'] !== null && (string)(int)$p['vat'] === $r ? 'selected' : '' ?>><?= $r ?>%</option><?php endforeach; ?></select></label>
  </div>
  <h3><?= e($sf->t('opening_stock')) ?></h3>
  <?php if (!$p): ?>
    <?php if ($whs): ?><div class="cols">
      <label class="fld"><span><?= e($sf->t('opening_qty')) ?></span><input name="opening_qty" value="0" inputmode="decimal"><small class="muted"><?= e($sf->t('hint_opening')) ?></small></label>
      <label class="fld"><span><?= e($sf->t('opening_wh')) ?></span><select name="opening_wh"><?php foreach ($whs as $w): ?><option value="<?= (int)$w['id'] ?>"><?= e($w['name']) ?></option><?php endforeach; ?></select></label>
    </div><?php else: ?><p class="muted sm"><?= e($sf->t('opening_none')) ?></p><?php endif; ?>
  <?php else: ?><p class="muted sm"><?= e($sf->t('opening_existing')) ?></p><?php endif; ?>
  <h3><?= e($sf->t('physical')) ?></h3>
  <div class="cols">
    <label class="fld"><span><?= e($sf->t('col_unit')) ?></span><input name="unit" list="units" value="<?= e($v('unit', 'шт')) ?>" maxlength="20"><datalist id="units"><?php foreach ($units as $u): ?><option value="<?= e($u) ?>"><?php endforeach; ?></datalist></label>
    <label class="fld"><span><?= e($sf->t('col_weight')) ?>, <?= e($sf->t('kg')) ?></span><input name="weight" value="<?= e($v('weight', '0')) ?>" inputmode="decimal"></label>
    <label class="fld"><span><?= e($sf->t('col_volume')) ?>, <?= e($sf->t('m3')) ?></span><input name="volume" value="<?= e($v('volume', '0')) ?>" inputmode="decimal"></label>
    <label class="fld"><span><?= e($sf->t('col_min_stock')) ?></span><input name="min_stock" value="<?= e($v('min_stock', '0')) ?>" inputmode="decimal"><small class="muted"><?= e($sf->t('min_stock_hint')) ?></small></label>
  </div>
  <label class="fld"><span><?= e($sf->t('col_short_desc')) ?></span><input name="short_desc" value="<?= e($v('short_desc')) ?>" maxlength="500"><small class="muted"><?= e($sf->t('hint_short_desc')) ?></small></label>
  <label class="fld"><span><?= e($sf->t('col_description')) ?></span><textarea name="description" rows="5" maxlength="4000"><?= e($v('description')) ?></textarea></label>
  <label class="fld"><span><?= e($sf->t('col_images')) ?></span><textarea name="images" rows="3" maxlength="4500" placeholder="https://…/photo-1.jpg"><?= e($v('images')) ?></textarea><small class="muted"><?= e($sf->t('hint_images')) ?></small></label>
  <?php $vars = json_decode((string)$v('variants'), true) ?: []; $specs = json_decode((string)$v('specs'), true) ?: []; ?>
  <h3><?= e($sf->t('options_h')) ?></h3><p class="muted sm"><?= e($sf->t('hint_options')) ?></p>
  <div data-rep="v"><div class="rep-rows"><?php foreach ($vars as $x): ?><div class="rep-row"><input name="v_name[]" value="<?= e($x['name']) ?>" placeholder="<?= e($sf->t('opt_name')) ?>"><input name="v_price[]" value="<?= e($x['price'] ?: '') ?>" inputmode="decimal" placeholder="<?= e($sf->t('opt_price')) ?>"><input name="v_stock[]" value="<?= (int)$x['stock'] ?>" inputmode="numeric" placeholder="<?= e($sf->t('opt_stock')) ?>"><button type="button" class="btn sm" data-del>×</button></div><?php endforeach; ?></div>
    <template><div class="rep-row"><input name="v_name[]" placeholder="<?= e($sf->t('opt_name')) ?>"><input name="v_price[]" inputmode="decimal" placeholder="<?= e($sf->t('opt_price')) ?>"><input name="v_stock[]" inputmode="numeric" placeholder="<?= e($sf->t('opt_stock')) ?>"><button type="button" class="btn sm" data-del>×</button></div></template>
    <button type="button" class="btn sm" data-add>+ <?= e($sf->t('opt_add')) ?></button></div>
  <h3><?= e($sf->t('specs_h')) ?></h3><p class="muted sm"><?= e($sf->t('hint_specs')) ?></p>
  <div data-rep="a"><div class="rep-rows"><?php foreach ($specs as $x): ?><div class="rep-row"><input name="a_section[]" value="<?= e($x['section']) ?>" placeholder="<?= e($sf->t('spec_section')) ?>"><input name="a_name[]" value="<?= e($x['name']) ?>" placeholder="<?= e($sf->t('spec_name')) ?>"><input name="a_value[]" value="<?= e($x['value']) ?>" placeholder="<?= e($sf->t('spec_value')) ?>"><button type="button" class="btn sm" data-del>×</button></div><?php endforeach; ?></div>
    <template><div class="rep-row"><input name="a_section[]" placeholder="<?= e($sf->t('spec_section')) ?>"><input name="a_name[]" placeholder="<?= e($sf->t('spec_name')) ?>"><input name="a_value[]" placeholder="<?= e($sf->t('spec_value')) ?>"><button type="button" class="btn sm" data-del>×</button></div></template>
    <button type="button" class="btn sm" data-add>+ <?= e($sf->t('spec_add')) ?></button></div>
  <script src="<?= e($sf->asset('wms-product.js')) ?>?v=<?= e($sf->manifest['version']) ?>" defer></script>
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
