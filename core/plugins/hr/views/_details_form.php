<?php /** The vacancy editor: the details and the free description, in the order candidates read them. Vars: $vd (details, [] for a new one), $vdText (description), $vdNew (bool) */
$vdChoice = function (string $key, string $label) use ($sf, $vd): void { ?>
  <label><?= e($sf->t($label)) ?><select name="<?= e($key) ?>"><option value=""><?= e($sf->t('not_set')) ?></option><?php foreach (\SysferaHr\Recruit::DETAIL_CHOICES[$key] as $o): ?><option value="<?= e($o) ?>" <?= ($vd[$key] ?? '') === $o ? 'selected' : '' ?>><?= e($sf->t(\SysferaHr\Recruit::DETAIL_LABELS[$key] . $o)) ?></option><?php endforeach; ?></select></label>
<?php };
$vdField = function (string $key, string $label) use ($sf, $vd): void { ?>
  <label><?= e($sf->t($label)) ?><input name="<?= e($key) ?>" maxlength="<?= (int)\SysferaHr\Recruit::DETAIL_LINES[$key] ?>" value="<?= e((string)($vd[$key] ?? '')) ?>" placeholder="<?= e($sf->t('ph_' . $key)) ?>"></label>
<?php };
$vdArea = function (string $key, string $label, int $rows) use ($sf, $vd): void { $val = $vd[$key] ?? ''; $isList = in_array($key, \SysferaHr\Recruit::DETAIL_LISTS, true); ?>
  <label class="fld"><span><?= e($sf->t($label)) ?></span><textarea name="<?= e($key) ?>" rows="<?= $rows ?>" maxlength="<?= $isList ? 15000 : (int)\SysferaHr\Recruit::DETAIL_TEXTS[$key] ?>" placeholder="<?= e($sf->t('ph_' . $key)) ?>"><?= e(is_array($val) ? implode("\n", $val) : (string)$val) ?></textarea></label>
<?php };
$vdChecks = function (string $key, string $label) use ($sf, $vd): void { ?>
  <div class="fld"><span><?= e($sf->t($label)) ?></span><input type="hidden" name="<?= e($key) ?>[]" value=""><?php foreach (\SysferaHr\Recruit::DETAIL_SETS[$key] as $o): ?><label class="hr-perm"><input type="checkbox" name="<?= e($key) ?>[]" value="<?= e($o) ?>" <?= in_array($o, (array)($vd[$key] ?? []), true) ? 'checked' : '' ?>><?= e($sf->t(\SysferaHr\Recruit::DETAIL_LABELS[$key] . $o)) ?></label><?php endforeach; ?></div>
<?php }; ?>
<div class="hr-grid hr-det"><?php $vdChoice('work_format', 'f_work_format'); $vdField('location', 'f_location'); $vdChoice('schedule', 'f_work_schedule'); $vdField('hours', 'f_hours'); $vdChoice('employment', 'f_employment'); ?></div>
<?php $vdArea('company', 'd_company', 3); ?>
<label class="fld"><span><?= e($sf->t('f_description')) ?></span><textarea name="description" rows="<?= $vdNew ? 5 : 8 ?>" <?= $vdNew ? 'required' : '' ?> maxlength="20000"><?= e($vdText) ?></textarea></label>
<?php $vdArea('responsibilities', 'd_responsibilities', 8); $vdArea('req_must', 'f_req_must', 5); $vdArea('req_nice', 'f_req_nice', 4); $vdArea('tools', 'd_tools', 4);
$vdChecks('contracts', 'f_contracts'); $vdChecks('benefits', 'f_benefits'); $vdArea('benefits_note', 'f_benefits_note', 2); $vdArea('stages', 'd_stages', 3); $vdArea('apply_info', 'd_apply_info', 3); ?>
