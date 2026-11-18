<?php /** One admin-defined profile field as an input. Vars: $f (field row), $val (stored value row or null) */
use SysferaContracts\Svc;
$n = 'pf[' . (int)$f['id'] . '][value]'; $req = $f['required'] ? ' required' : ''; $cur = (string)($val['value'] ?? '');
$pub = $val ? (int)$val['is_public'] : (int)$f['default_public']; ?>
<div class="ct-lbl"><span><?= e($f['label']) ?><?= $f['required'] ? ' *' : '' ?></span>
<?php switch ($f['type']) {
    case 'textarea': echo '<textarea name="' . e($n) . '" rows="3" maxlength="4000"' . $req . '>' . e($cur) . '</textarea>'; break;
    case 'number': echo '<input type="number" step="any" name="' . e($n) . '" value="' . e($cur) . '"' . $req . '>'; break;
    case 'url': echo '<input type="url" name="' . e($n) . '" maxlength="300" placeholder="https://" value="' . e($cur) . '"' . $req . '>'; break;
    case 'date': echo '<input type="date" name="' . e($n) . '" value="' . e($cur) . '"' . $req . '>'; break;
    case 'select': echo '<select name="' . e($n) . '"' . $req . '><option value="">—</option>'; foreach (Svc::options($f) as $o) echo '<option value="' . e($o) . '"' . ($o === $cur ? ' selected' : '') . '>' . e($o) . '</option>'; echo '</select>'; break;
    default: echo '<input name="' . e($n) . '" maxlength="190" value="' . e($cur) . '"' . $req . '>';
} ?>
<label class="muted"><input type="checkbox" name="pf[<?= (int)$f['id'] ?>][public]" value="1" <?= $pub ? 'checked' : '' ?>> <?= e($sf->t('show_public')) ?></label></div>
