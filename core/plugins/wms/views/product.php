<?php /** @var \Sysfera\Plugin $sf @var string $tab @var ?array $p @var array $groups @var array $byWh @var array $byCell @var array $history @var array $units */
use SysferaWms\W;
include __DIR__ . '/_nav.php';
$v = fn(string $k, $d = '') => $p[$k] ?? $d; ?>
<div class="page-h"><h1><?= e($p ? $p['name'] : $sf->t('new_product')) ?> <?php if ($p && $p['archived']): ?><span class="badge mute"><?= e($sf->t('archived')) ?></span><?php endif; ?></h1>
  <span class="row"><?php if ($p): ?><a class="btn" target="_blank" rel="noopener" href="<?= e($sf->url('/products/' . (int)$p['id'] . '/preview')) ?>" title="<?= e($sf->t('btn_preview_hint')) ?>">👁 <?= e($sf->t('btn_preview')) ?></a><?php endif; ?>
  <a class="btn" href="<?= e($sf->url('/products')) ?>">← <?= e($sf->t('nav_products')) ?></a></span></div>

<div class="cols2 wide-left">
<?php $pt = \SysferaWms\Kinds::normal($v('ptype', 'simple')); $types = \SysferaWms\Kinds::TYPES; ?>
<form class="card" method="post" data-ptype="<?= e($pt) ?>"><?= csrf_field() ?>
  <label class="fld"><span><?= e($sf->t('ptype')) ?></span><select name="ptype" id="ptype"><?php foreach ($types as $t): ?><option value="<?= $t ?>" <?= $pt === $t ? 'selected' : '' ?>><?= e($sf->t('pt_' . $t)) ?></option><?php endforeach; ?></select>
    <?php foreach ($types as $t): ?><small class="muted" data-t="<?= $t ?>"><?= e($sf->t('pth_' . $t)) ?></small><?php endforeach; ?></label>
  <label class="fld"><span><?= e($sf->t('col_name')) ?> *</span><input name="name" value="<?= e($v('name')) ?>" required maxlength="255" <?= $p ? '' : 'autofocus' ?>></label>
  <div class="cols">
    <label class="fld"><span><?= e($sf->t('col_sku')) ?></span><input name="sku" value="<?= e($v('sku')) ?>" maxlength="80"></label>
    <label class="fld" data-t="simple bundle"><span><?= e($sf->t('col_barcode')) ?></span><input name="barcode" value="<?= e($v('barcode')) ?>" maxlength="80" placeholder="<?= e($sf->t('barcode_hint')) ?>"></label>
    <label class="fld"><span><?= e($sf->t('col_code')) ?></span><input name="code" value="<?= e($v('code')) ?>" maxlength="40" placeholder="<?= e($sf->t('auto')) ?>"></label>
    <label class="fld"><span><?= e($sf->t('col_group')) ?></span><select name="group_id"><option value="">—</option><?php foreach ($groups as $g): ?><option value="<?= (int)$g['id'] ?>" <?= (int)$v('group_id', 0) === (int)$g['id'] ? 'selected' : '' ?>><?= e($g['name']) ?></option><?php endforeach; ?></select></label>
  </div>
  <?= \Sysfera\Hooks::filter('wms.product.fields', '', $p) ?>
  <div data-t="simple variable virtual downloadable bundle">
  <h3><?= e($sf->t('prices')) ?></h3>
  <div class="cols">
    <label class="fld" data-t="simple virtual downloadable bundle"><span><?= e($sf->t('col_buy_price')) ?>, ₽</span><input name="buy_price" value="<?= e($v('buy_price', '0')) ?>" inputmode="decimal"><small class="muted"><?= e($sf->t('hint_buy_price')) ?></small></label>
    <label class="fld"><span><?= e($sf->t('col_sell_price')) ?>, ₽</span><input name="sell_price" value="<?= e($v('sell_price', '0')) ?>" inputmode="decimal"><small class="muted"><?= e($sf->t('hint_sell_price')) ?></small></label>
    <label class="fld"><span><?= e($sf->t('col_promo_price')) ?>, ₽</span><input name="promo_price" value="<?= e($v('promo_price', '0')) ?>" inputmode="decimal"><small class="muted"><?= e($sf->t('hint_promo_price')) ?></small></label>
    <label class="fld"><span><?= e($sf->t('col_vat')) ?></span><select name="vat"><option value="">—</option><?php foreach (['0', '10', '22'] as $r): ?><option value="<?= $r ?>" <?= $p && $p['vat'] !== null && (string)(int)$p['vat'] === $r ? 'selected' : '' ?>><?= $r ?>%</option><?php endforeach; ?></select></label>
  </div>
  <p class="muted sm" data-t="variable"><?= e($sf->t('hint_var_price')) ?></p><p class="muted sm" data-t="bundle"><?= e($sf->t('hint_bundle_price')) ?></p>
  </div>
  <div data-t="simple">
  <h3><?= e($sf->t('opening_stock')) ?></h3>
  <?php if (!$p): ?>
    <?php if ($whs): ?><div class="cols">
      <label class="fld"><span><?= e($sf->t('opening_qty')) ?></span><input name="opening_qty" value="0" inputmode="decimal"><small class="muted"><?= e($sf->t('hint_opening')) ?></small></label>
      <label class="fld"><span><?= e($sf->t('opening_wh')) ?></span><select name="opening_wh"><?php foreach ($whs as $w): ?><option value="<?= (int)$w['id'] ?>"><?= e($w['name']) ?></option><?php endforeach; ?></select></label>
    </div><?php else: ?><p class="muted sm"><?= e($sf->t('opening_none')) ?></p><?php endif; ?>
  <?php else: ?><p class="muted sm"><?= e($sf->t('opening_existing')) ?></p><?php endif; ?>
  </div>
  <div data-t="simple virtual downloadable bundle">
  <h3><?= e($sf->t('physical')) ?></h3>
  <div class="cols">
    <label class="fld"><span><?= e($sf->t('col_unit')) ?></span><input name="unit" list="units" value="<?= e($v('unit', 'шт')) ?>" maxlength="20"><datalist id="units"><?php foreach ($units as $u): ?><option value="<?= e($u) ?>"><?php endforeach; ?></datalist></label>
    <label class="fld" data-t="simple bundle"><span><?= e($sf->t('col_weight')) ?>, <?= e($sf->t('kg')) ?></span><input name="weight" value="<?= e($v('weight', '0')) ?>" inputmode="decimal"></label>
    <label class="fld" data-t="simple bundle"><span><?= e($sf->t('col_volume')) ?>, <?= e($sf->t('m3')) ?></span><input name="volume" value="<?= e($v('volume', '0')) ?>" inputmode="decimal"></label>
    <label class="fld" data-t="simple"><span><?= e($sf->t('col_min_stock')) ?></span><input name="min_stock" value="<?= e($v('min_stock', '0')) ?>" inputmode="decimal"><small class="muted"><?= e($sf->t('min_stock_hint')) ?></small></label>
  </div>
  </div>
  <label class="fld" data-t="downloadable"><span><?= e($sf->t('col_download')) ?></span><input name="download_url" value="<?= e($v('download_url')) ?>" maxlength="500" placeholder="https://…/file.zip"><small class="muted"><?= e($sf->t('hint_download')) ?></small></label>
  <label class="fld"><span><?= e($sf->t('col_short_desc')) ?></span><input name="short_desc" value="<?= e($v('short_desc')) ?>" maxlength="500"><small class="muted"><?= e($sf->t('hint_short_desc')) ?></small></label>
  <label class="fld"><span><?= e($sf->t('col_description')) ?></span><textarea name="description" rows="5" maxlength="4000"><?= e($v('description')) ?></textarea></label>
  <label class="fld"><span><?= e($sf->t('col_images')) ?></span><textarea name="images" rows="3" maxlength="4500" placeholder="https://…/photo-1.jpg"><?= e($v('images')) ?></textarea><small class="muted"><?= e($sf->t('hint_images')) ?></small></label>
  <?php $specs = json_decode((string)$v('specs'), true) ?: [];
  $row = fn(?array $x) => '<div class="rep-row"><input name="l_child[]" list="cands" value="' . e($x ? '#' . $x['child_id'] . ' · ' . $x['name'] . ($x['sku'] ? ' · ' . $x['sku'] : '') : '') . '" placeholder="' . e($sf->t('cmp_product')) . '" autocomplete="off">'
    . '<input class="c-label" name="l_label[]" value="' . e($x['label'] ?? '') . '" placeholder="' . e($sf->t('cmp_label_ph')) . '">'
    . '<input class="c-qty" name="l_qty[]" value="' . e($x ? W::qty($x['qty']) : '1') . '" inputmode="decimal">'
    . '<input class="c-price" name="l_price[]" value="' . e($x && (float)$x['price'] > 0 ? $x['price'] : '') . '" inputmode="decimal" placeholder="' . e($sf->t('cmp_price_ph')) . '">'
    . '<span class="c-stock muted sm">' . ($x ? e(W::qty($x['avail'])) : '') . '</span><button type="button" class="btn sm" data-del>×</button></div>'; ?>
  <div data-t="variable grouped bundle">
  <h3><?= e($sf->t('cmp_h')) ?></h3>
  <p class="muted sm" data-t="variable"><?= e($sf->t('cmp_hint_variable')) ?></p><p class="muted sm" data-t="grouped"><?= e($sf->t('cmp_hint_grouped')) ?></p><p class="muted sm" data-t="bundle"><?= e($sf->t('cmp_hint_bundle')) ?></p>
  <div data-rep="l"><div class="rep-row rep-head"><span><?= e($sf->t('cmp_product')) ?></span><span class="c-label"><?= e($sf->t('cmp_label')) ?></span><span class="c-qty"><?= e($sf->t('cmp_qty')) ?></span><span class="c-price"><?= e($sf->t('cmp_price')) ?></span><span class="c-stock"><?= e($sf->t('cmp_stock')) ?></span><span></span></div>
    <div class="rep-rows"><?php foreach ($links as $x) echo $row($x); ?></div>
    <template><?= $row(null) ?></template>
    <button type="button" class="btn sm" data-add>+ <?= e($sf->t('cmp_add')) ?></button>
    <datalist id="cands"><?php foreach ($cands as $c): ?><option value="#<?= (int)$c['id'] ?> · <?= e($c['name']) ?><?= $c['sku'] ? ' · ' . e($c['sku']) : '' ?>"><?php endforeach; ?></datalist></div>
  </div>
  <h3><?= e($sf->t('specs_h')) ?></h3><p class="muted sm"><?= e($sf->t('hint_specs')) ?></p>
  <div data-rep="a"><div class="rep-row rep-head"><span><?= e($sf->t('spec_section')) ?></span><span><?= e($sf->t('spec_name')) ?></span><span><?= e($sf->t('spec_value')) ?></span><span></span></div><div class="rep-rows"><?php foreach ($specs as $x): ?><div class="rep-row"><input name="a_section[]" value="<?= e($x['section']) ?>" placeholder="<?= e($sf->t('spec_section')) ?>"><input name="a_name[]" value="<?= e($x['name']) ?>" placeholder="<?= e($sf->t('spec_name')) ?>"><input name="a_value[]" value="<?= e($x['value']) ?>" placeholder="<?= e($sf->t('spec_value')) ?>"><button type="button" class="btn sm" data-del>×</button></div><?php endforeach; ?></div>
    <template><div class="rep-row"><input name="a_section[]" placeholder="<?= e($sf->t('spec_section')) ?>"><input name="a_name[]" placeholder="<?= e($sf->t('spec_name')) ?>"><input name="a_value[]" placeholder="<?= e($sf->t('spec_value')) ?>"><button type="button" class="btn sm" data-del>×</button></div></template>
    <button type="button" class="btn sm" data-add>+ <?= e($sf->t('spec_add')) ?></button></div>
  <script src="<?= e($sf->asset('wms-product.js')) ?>?v=<?= e($sf->manifest['version']) ?>" defer></script>
  <div class="row"><button class="btn primary"><?= e($sf->t('save')) ?></button>
    <?php if ($p && !$p['archived']): ?><button class="btn danger" name="do" value="archive" data-confirm="<?= e($sf->t('confirm_archive')) ?>"><?= e($sf->t('archive')) ?></button><?php endif; ?>
    <?php if ($p && $p['archived']): ?><button class="btn" name="do" value="restore"><?= e($sf->t('restore')) ?></button><?php endif; ?></div>
</form>

<?php if ($p && $pt === 'simple'): ?>
<div>
  <section class="card"><h3><?= e($sf->t('stock_by_wh')) ?></h3>
    <table class="tbl"><thead><tr><th><?= e($sf->t('warehouse')) ?></th><th class="num"><?= e($sf->t('col_on_hand')) ?></th><th class="num"><?= e($sf->t('col_reserve')) ?></th><th class="num"><?= e($sf->t('col_expected')) ?></th></tr></thead>
    <tbody><?php foreach ($byWh as $r): ?><tr><td><?= e($r['name']) ?></td><td class="num"><?= e(W::qty($r['on_hand'])) ?></td><td class="num"><?= e(W::qty($r['reserve'])) ?></td><td class="num"><?= e(W::qty($r['expected'])) ?></td></tr><?php endforeach; ?></tbody></table>
    <?php if ($byCell): ?><h3><?= e($sf->t('stock_by_cell')) ?></h3><table class="tbl"><tbody><?php foreach ($byCell as $r): ?><tr><td><?= e($r['warehouse']) ?> · <b><?= e($r['cell'] ?? $sf->t('no_cell')) ?></b></td><td class="num"><?= e(W::qty($r['on_hand'])) ?> <?= e($p['unit']) ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
  </section>
  <section class="card"><h3><?= e($sf->t('history')) ?></h3>
    <?php if (!$history): ?><p class="muted"><?= e($sf->t('no_history')) ?></p><?php else: ?>
    <table class="tbl"><tbody><?php foreach ($history as $h): $d = (float)$h['on_hand']; ?>
      <tr <?= empty($h['private']) ? 'onclick="location=\'' . e($sf->url('/docs/' . $h['doc_id'])) . '\'"' : '' ?>><td><small class="muted"><?= e(W::date($h['moment'])) ?></small><br><?php if (!empty($h['private'])): ?>🔒 <?= e($sf->t('private_short')) ?><?php else: ?><?= e(W::type($sf, $h['type'])) ?> <b><?= e($h['number']) ?></b><?php endif; ?></td>
      <td class="num"><?php if ($d != 0.0): ?><span class="<?= $d > 0 ? 'ok-t' : 'bad-t' ?>"><?= $d > 0 ? '+' : '' ?><?= e(W::qty($d)) ?></span><?php else: ?><span class="muted"><?= (float)$h['reserve'] != 0.0 ? e($sf->t('col_reserve')) . ' ' . e(W::qty($h['reserve'])) : e($sf->t('col_expected')) . ' ' . e(W::qty($h['expected'])) ?></span><?php endif; ?></td></tr>
    <?php endforeach; ?></tbody></table><?php endif; ?>
  </section>
</div>
<?php endif; ?>
</div>
