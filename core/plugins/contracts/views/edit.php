<?php /** @var \Sysfera\Plugin $sf @var ?array $k @var array $steps @var bool $locked @var array $files @var array $cats @var array $myTypes @var ?array $form */
use SysferaContracts\Svc;
include __DIR__ . '/_nav.php';
$v = fn(string $n, $d = '') => $form[$n] ?? $k[$n] ?? $d;
$rows = $form && !$locked ? array_values(array_filter((array)($form['todos'] ?? []), 'is_array')) : array_map(fn($t) => ['id' => $t['id'], 'name' => $t['name'], 'description' => $t['description'], 'price' => $t['price'], 'days' => $t['days']], $steps);
if (!$rows) $rows = [['id' => 0, 'name' => '', 'description' => '', 'price' => '', 'days' => 1]];
$stepRow = function (string $i, array $r) use ($sf, $ctm, $locked): string { ob_start(); ?>
  <div class="ct-step" data-row><input type="hidden" name="todos[<?= e($i) ?>][id]" value="<?= (int)($r['id'] ?? 0) ?>">
    <div class="row"><label class="ct-lbl" style="flex:3 1 220px"><?= e($sf->t('step_name')) ?><input name="todos[<?= e($i) ?>][name]" value="<?= e($r['name'] ?? '') ?>" maxlength="190" <?= $locked ? 'readonly' : '' ?>></label>
      <label class="ct-lbl"><?= e($sf->t('price')) ?><input name="todos[<?= e($i) ?>][price]" value="<?= e($r['price'] === '' ? '' : $ctm($r['price'])) ?>" inputmode="decimal" <?= $locked ? 'readonly' : '' ?>></label>
      <label class="ct-lbl"><?= e($sf->t('days')) ?><input name="todos[<?= e($i) ?>][days]" value="<?= e((string)($r['days'] ?? 1)) ?>" inputmode="numeric" <?= $locked ? 'readonly' : '' ?>></label>
      <?php if (!$locked): ?><span><button class="btn sm" data-up title="<?= e($sf->t('up')) ?>">↑</button> <button class="btn sm" data-down title="<?= e($sf->t('down')) ?>">↓</button> <button class="btn sm danger" data-remove title="<?= e($sf->t('remove')) ?>">×</button></span><?php endif; ?></div>
    <label class="ct-lbl"><?= e($sf->t('step_descr')) ?><textarea name="todos[<?= e($i) ?>][description]" rows="2" <?= $locked ? 'readonly' : '' ?>><?= e($r['description'] ?? '') ?></textarea></label></div>
<?php return (string)ob_get_clean(); }; ?>
<div class="page-h"><h1><?= e($sf->t($k ? 'contract_edit' : 'contract_new')) ?></h1></div>
<?php if ($locked): ?><p class="ct-hint"><?= e($sf->t('steps_locked')) ?></p><?php endif; ?>
<form method="post" action="<?= e($sf->url('/save')) ?>" enctype="multipart/form-data" class="ct-form card"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)($k['id'] ?? 0) ?>">
  <label class="ct-lbl"><?= e($sf->t('f_title')) ?><input name="title" value="<?= e($v('title')) ?>" maxlength="190" required></label>
  <div class="row"><label class="ct-lbl"><?= e($sf->t('category')) ?><select name="category_id"><option value="0">—</option><?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$v('category_id', 0) === (int)$c['id'] ? 'selected' : '' ?>><?= e($lang === 'ru' && $c['name_ru'] ? $c['name_ru'] : $c['name']) ?></option><?php endforeach; ?></select></label>
    <label class="ct-lbl"><?= e($sf->t('location')) ?><input name="location" value="<?= e($v('location')) ?>" maxlength="190"></label>
    <label class="ct-lbl"><?= e($sf->t('deadline')) ?><input type="date" name="deadline" value="<?= e((string)$v('deadline')) ?>"></label>
    <label class="ct-lbl"><?= e($sf->t('currency')) ?><select name="currency"><?php foreach (Svc::CURRENCIES as $code => $sym): ?><option value="<?= e($code) ?>" <?= $v('currency', 'RUB') === $code ? 'selected' : '' ?>><?= e($sym . ' ' . $code) ?></option><?php endforeach; ?></select></label>
    <?php if (count($myTypes) > 1): ?><label class="ct-lbl"><?= e($sf->t('act_as')) ?><select name="author_type"><?php foreach ($myTypes as $t): ?><option value="<?= e($t) ?>" <?= $v('author_type') === $t ? 'selected' : '' ?>><?= e($sf->t('as_' . $t)) ?></option><?php endforeach; ?></select></label><?php endif; ?></div>
  <label class="ct-lbl"><?= e($sf->t('f_descr')) ?><textarea name="description" rows="5"><?= e($v('description')) ?></textarea></label>
  <h3><?= e($sf->t('steps')) ?></h3>
  <div data-rows id="ct-steps"><div data-list class="ct-rows"><?php foreach ($rows as $i => $r) echo $stepRow((string)$i, $r); ?></div><?php if (!$locked): ?><template><?= $stepRow('__', ['id' => 0, 'name' => '', 'description' => '', 'price' => '', 'days' => 1]) ?></template><p><button class="btn" data-add="#ct-steps"><?= e($sf->t('add_step')) ?></button></p><?php endif; ?></div>
  <label><input type="checkbox" name="prepay_required" value="1" <?= $v('prepay_required', 0) ? 'checked' : '' ?>> <?= e($sf->t('prepay_required')) ?></label>
  <h3><?= e($sf->t('files')) ?></h3>
  <?php foreach ($files as $f): ?><p><label><input type="checkbox" name="delete_file[]" value="<?= (int)$f['id'] ?>"> <?= e($sf->t('delete')) ?></label> <a href="<?= e($sf->url('/file/' . $f['id'])) ?>"><?= e($f['original_name']) ?></a></p><?php endforeach; ?>
  <div class="row"><label class="ct-lbl"><?= e($sf->t('images')) ?><input type="file" name="images[]" accept="image/*" multiple></label><label class="ct-lbl"><?= e($sf->t('files')) ?><input type="file" name="files[]" multiple></label></div>
  <div class="row"><label><input type="checkbox" name="publish" value="1" <?= ($form ? !empty($form['publish']) : ($k['status'] ?? '') === 'published') ? 'checked' : '' ?>> <?= e($sf->t('publish')) ?></label>
    <button class="btn primary"><?= e($sf->t('save')) ?></button><a class="btn" href="<?= e($sf->url($k ? '/view/' . $k['id'] : '/mine')) ?>"><?= e($sf->t('cancel')) ?></a></div>
</form>
<?php if ($k && !in_array($k['status'], ['active', 'paused'], true)): ?><form method="post" action="<?= e($sf->url('/c/' . $k['id'] . '/delete')) ?>" data-confirm="<?= e($sf->t('confirm_delete')) ?>"><?= csrf_field() ?><button class="btn danger"><?= e($sf->t('delete')) ?></button></form><?php endif; ?>
