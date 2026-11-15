<?php /** @var array<string,array> $types @var string[] $kinds @var string[] $apps */
$lang = \Sysfera\I18n::lang();
$tk = fn(string $p, string $k): string => t('tasktypes.' . $p . $k);
$fieldRows = function (array $fields) use ($kinds, $tk): void {
    $fields = array_pad($fields, max(count($fields) + 1, 3), ['key' => '', 'kind' => 'text', 'label' => '', 'label_ru' => '']);
    foreach ($fields as $f): ?>
    <div class="cols" style="grid-template-columns:1fr 1fr 1.4fr 1.4fr">
      <input name="f_key[]" placeholder="<?= e(t('tasktypes.f_key')) ?>" maxlength="24" value="<?= e($f['key']) ?>">
      <select name="f_kind[]"><?php foreach ($kinds as $k): ?><option value="<?= e($k) ?>" <?= $f['kind'] === $k ? 'selected' : '' ?>><?= e($tk('kind_', $k)) ?></option><?php endforeach; ?></select>
      <input name="f_label[]" placeholder="<?= e(t('tasktypes.f_label')) ?>" maxlength="60" value="<?= e($f['key'] !== '' ? $f['label'] : '') ?>">
      <input name="f_label_ru[]" placeholder="<?= e(t('tasktypes.f_label_ru')) ?>" maxlength="60" value="<?= e($f['label_ru']) ?>">
    </div>
<?php endforeach; };
$form = function (array $x, bool $new) use ($apps, $fieldRows, $tk): void { $have = explode(',', $x['apps']); ?>
  <div class="cols">
    <label class="fld"><span><?= e(t('tasktypes.code')) ?></span><input name="code" required maxlength="20" pattern="[a-z0-9_]+" value="<?= e($x['code']) ?>" <?= $new ? '' : 'readonly' ?>></label>
    <label class="fld"><span><?= e(t('tasktypes.name')) ?></span><input name="name" required maxlength="80" value="<?= e($x['name']) ?>"></label>
    <label class="fld"><span><?= e(t('tasktypes.name_ru')) ?></span><input name="name_ru" maxlength="80" value="<?= e($x['name_ru']) ?>"></label>
    <label class="fld"><span><?= e(t('tasktypes.color')) ?></span><input type="color" name="color" value="<?= e($x['color']) ?>"></label>
    <label class="fld"><span><?= e(t('tasktypes.sort')) ?></span><input type="number" name="sort" value="<?= (int)$x['sort'] ?>"></label>
  </div>
  <div class="row" style="flex-wrap:wrap;gap:4px"><span class="muted sm"><?= e(t('tasktypes.icon')) ?>:</span>
    <?php $cur = \Sysfera\TaskTypes::iconName((string)$x['icon']); foreach (array_keys(\Sysfera\TaskTypes::ICONS) as $n): ?>
    <label title="<?= e($n) ?>" style="cursor:pointer;display:inline-flex"><input type="radio" name="icon" value="<?= e($n) ?>" <?= $cur === $n ? 'checked' : '' ?> style="position:absolute;opacity:0;width:0;height:0" onchange="this.closest('form').querySelectorAll('.icopick').forEach(function(s){s.style.borderColor='transparent'});this.nextElementSibling.style.borderColor='currentColor'"><span class="icopick" style="padding:5px;border:1.5px solid <?= $cur === $n ? 'currentColor' : 'transparent' ?>;border-radius:8px;line-height:0"><?= \Sysfera\TaskTypes::svg($n, 20) ?></span></label>
    <?php endforeach; ?></div>
  <div class="row"><span class="muted sm"><?= e(t('tasktypes.apps')) ?>:</span>
    <?php foreach ($apps as $a): ?><label class="chk"><input type="checkbox" name="apps[]" value="<?= e($a) ?>" <?= in_array($a, $have, true) ? 'checked' : '' ?>> <?= e($tk('app_', $a)) ?></label><?php endforeach; ?>
    <label class="chk"><input type="checkbox" name="active" value="1" <?= $x['active'] ? 'checked' : '' ?>> <?= e(t('tasktypes.active')) ?></label></div>
  <div class="muted sm"><?= e(t('tasktypes.fields')) ?> <span class="tt-count"></span></div>
  <div class="col tt-fields" data-max="<?= (int)\Sysfera\TaskTypes::MAX_FIELDS ?>"><?php $fieldRows($x['fields']); ?></div>
  <div><button type="button" class="btn tt-addfield">+ <?= e(t('tasktypes.add_field')) ?></button></div><?php
};
?>
<div class="page-h"><h1><?= e(t('nav.tasktypes')) ?></h1></div>
<div class="hint"><?= e(t('tasktypes.hint')) ?></div>
<?php foreach ($types as $x): ?>
<form method="post" class="card col"><?= csrf_field() ?>
  <div class="row between"><div><span style="color:<?= e($x['color']) ?>"><?= \Sysfera\TaskTypes::svg($x['icon'], 18) ?></span> <b><?= e($lang === 'ru' && $x['name_ru'] !== '' ? $x['name_ru'] : $x['name']) ?></b> <small class="muted"><?= e($x['code']) ?></small><?= $x['active'] ? '' : ' <span class="badge warn">off</span>' ?></div></div>
  <?php $form($x, false); ?>
  <div class="row"><button class="btn primary" name="do" value="save"><?= e(t('common.save')) ?></button>
    <?php if (!$x['builtin']): ?><button class="btn danger" name="do" value="delete" data-confirm="<?= e(t('common.delete')) ?>?"><?= e(t('common.delete')) ?></button><?php endif; ?></div>
</form>
<?php endforeach; ?>
<h2><?= e(t('tasktypes.add')) ?></h2>
<form method="post" class="card col"><?= csrf_field() ?>
  <?php $form(['code' => '', 'name' => '', 'name_ru' => '', 'icon' => 'tag', 'color' => '#6b7280', 'apps' => 'missions,crm', 'fields' => [], 'sort' => 500, 'active' => 1], true); ?>
  <div class="row"><button class="btn primary" name="do" value="add"><?= e(t('tasktypes.add')) ?></button></div>
</form>
<script>
(function () {   // "+ Add field": one more empty row (up to the maximum); empty rows are ignored when saving
  document.addEventListener('click', function (e) {
    var b = e.target.closest('.tt-addfield'); if (!b) return;
    var box = b.closest('form').querySelector('.tt-fields'), rows = box.querySelectorAll('.cols'), max = parseInt(box.dataset.max, 10) || 8;
    if (rows.length >= max) return;
    var n = rows[rows.length - 1].cloneNode(true);
    n.querySelectorAll('input').forEach(function (i) { i.value = ''; });
    n.querySelectorAll('select').forEach(function (s) { s.selectedIndex = 0; });
    box.appendChild(n); n.querySelector('input').focus();
    if (rows.length + 1 >= max) b.disabled = true;
  });
  document.querySelectorAll('.tt-fields').forEach(function (box) { var b = box.closest('form').querySelector('.tt-addfield'); if (b && box.querySelectorAll('.cols').length >= (parseInt(box.dataset.max, 10) || 8)) b.disabled = true; });
})();
</script>
<form method="post" class="row"><?= csrf_field() ?><button class="btn" name="do" value="restore" data-confirm="<?= e(t('tasktypes.confirm_restore')) ?>"><?= e(t('tasktypes.restore')) ?></button></form>
