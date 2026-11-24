<?php /** @var \Sysfera\Plugin $sf @var \SysferaStoragerent\Ctx $ctx @var string $lang @var ?array $l @var array $v @var array $warehouses @var int $wh @var bool $key @var array $photos @var int $maxPhotos */
require __DIR__ . '/_nav.php'; $F = \SysferaStoragerent\Offers::FIELDS; $blocks = []; foreach ($F as $k => $def) $blocks[$def[0]][] = $k; $id = $l ? (int)$l['id'] : 0;
/** One field of the form from the FIELDS definition: label, control, hint. */
$field = function (string $k) use ($F, $v, $sf, $L): string {
    $t = $F[$k][1]; $val = $v[$k] ?? ''; $label = $sf->t('of_' . $k); $hint = isset($L['oh_' . $k]) ? $sf->t('oh_' . $k) : '';
    return match ($t) { 'b' => sr_chk($k, $label, !empty($val)) . ($hint !== '' ? '<small class="muted sr-hint">' . e($hint) . '</small>' : ''), 't' => sr_area($k, $label, $val, $hint, 3),
        'h' => sr_in($k, $label, $val, 'time', ''), 'n' => sr_in($k, $label, $val !== '' && (float)$val == 0 ? '' : $val, 'text', 'inputmode="decimal"') . ($hint !== '' ? '<small class="muted sr-hint">' . e($hint) . '</small>' : ''), default => sr_in($k, $label, $val, 'text', 'maxlength="' . (int)($F[$k][2] ?? 190) . '"') . ($hint !== '' ? '<small class="muted sr-hint">' . e($hint) . '</small>' : '') };
}; ?>
<h2><?= e($sf->t($l ? 'offer_edit' : 'offers_new')) ?></h2>
<?php if (!$l && $warehouses): ?><form method="get" action="<?= e($sf->url('/offers/new')) ?>" class="card sr-filter"><span><?= e($sf->t('offer_prefill')) ?></span><select name="wh"><option value="0">—</option><?php foreach ($warehouses as $w): ?><option value="<?= (int)$w['id'] ?>"<?= (int)$w['id'] === $wh ? ' selected' : '' ?>><?= e($w['name']) ?></option><?php endforeach; ?></select><button class="btn"><?= e($sf->t('offer_prefill_do')) ?></button></form><?php endif; ?>
<form method="post" enctype="multipart/form-data" action="<?= e($sf->url('/offers/save')) ?>" class="sr-form sr-offer" id="srOfferForm"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="warehouse_id" value="<?= (int)($v['warehouse_id'] ?? 0) ?>">
  <fieldset class="card"><legend><?= e($sf->t('blk_1')) ?></legend><p class="muted sm"><?= e($sf->t('blk_1_hint')) ?></p>
    <?= sr_in('name', $sf->t('of_name'), $v['name'] ?? '', 'text', 'required maxlength="190"') ?>
    <div class="sr-2"><?= sr_in('city', $sf->t('of_city'), $v['city'] ?? '', 'text', 'required maxlength="100"') ?><?= sr_in('street', $sf->t('of_street'), $v['street'] ?? '', 'text', 'maxlength="190"') ?></div>
    <div class="sr-2"><?= sr_in('lat', $sf->t('of_lat'), $v['lat'] ?? '', 'text', 'inputmode="decimal" id="srLat"') ?><?= sr_in('lon', $sf->t('of_lon'), $v['lon'] ?? '', 'text', 'inputmode="decimal" id="srLon"') ?></div>
    <p class="muted sm"><?= e($sf->t($key ? 'of_geo_help_key' : 'of_geo_help')) ?></p>
    <div class="sr-map" id="srMapPicker" data-key="<?= e((string)$sf->setting('yandex_key')) ?>" data-lang="<?= e($lang === 'en' ? 'en_US' : 'ru_RU') ?>" data-picker="1"><div class="sr-map-canvas" hidden></div></div>
    <div class="sr-2"><?= sr_in('area', $sf->t('of_area'), $v['area'] ?? '', 'text', 'required inputmode="decimal"') ?><?= sr_in('price_m2', $sf->t('of_price_m2'), $v['price_m2'] ?? '', 'text', 'required inputmode="decimal"') ?></div>
    <?php foreach ($blocks[1] as $k) echo $field($k); ?>
    <?= sr_sel('status', $sf->t('of_status'), ['published' => $sf->t('os_published'), 'draft' => $sf->t('os_draft'), 'archived' => $sf->t('os_archived')], $v['status'] ?? 'published') ?></fieldset>
  <?php for ($b = 2; $b <= 7; $b++): ?><fieldset class="card"><legend><?= e($sf->t('blk_' . $b)) ?></legend><?php if (isset($L['blk_' . $b . '_hint'])): ?><p class="muted sm"><?= e($sf->t('blk_' . $b . '_hint')) ?></p><?php endif; ?>
    <?php foreach ($blocks[$b] as $k) echo $field($k); ?></fieldset><?php endfor; ?>
  <fieldset class="card"><legend><?= e($sf->t('blk_8')) ?></legend><p class="muted sm"><?= e($sf->t('blk_8_hint', ['n' => $maxPhotos])) ?></p>
    <?php if ($photos): ?><div class="sr-photos"><?php foreach ($photos as $p): ?><figure><img src="<?= e(url('/p/storagerent/offers/photo/' . (int)$p['id'])) ?>" alt=""><button type="submit" form="srPhoto<?= (int)$p['id'] ?>" class="btn sm danger"><?= e($sf->t('delete')) ?></button></figure><?php endforeach; ?></div><?php endif; ?>
    <label class="fld"><span><?= e($sf->t('of_photos')) ?></span><input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple></label></fieldset>
  <fieldset class="card"><legend><?= e($sf->t('blk_9')) ?></legend><p class="muted sm"><?= e($sf->t('blk_9_hint')) ?></p>
    <?php foreach ($blocks[9] as $k) echo $field($k); ?></fieldset>
  <p><button class="btn primary"><?= e($sf->t('save')) ?></button> <a class="btn" href="<?= e($sf->url($l ? '/offers/' . $id : '/offers')) ?>"><?= e($sf->t('cancel')) ?></a></p></form>
<?php foreach ($photos as $p): ?><form id="srPhoto<?= (int)$p['id'] ?>" method="post" action="<?= e($sf->url('/offers/' . $id . '/do')) ?>"><?= csrf_field() ?><input type="hidden" name="do" value="photo_delete"><input type="hidden" name="photo_id" value="<?= (int)$p['id'] ?>"></form><?php endforeach; ?>
<script src="<?= e($sf->asset('storagerent.js')) ?>?v=<?= e($sf->manifest['version']) ?>" defer></script>
