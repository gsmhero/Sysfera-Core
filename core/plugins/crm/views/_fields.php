<?php /** @var array $defs field definitions @var array $vals current values (code => value) */
foreach ($defs as $fd): $n = 'f[' . $fd['code'] . ']'; $v = $vals[$fd['code']] ?? null; $req = !empty($fd['required']) ? ' required' : ''; ?>
<label class="fld"><span><?= e($fd['label']) ?><?= $req ? ' *' : '' ?></span>
<?php switch ($fd['type']):
    case 'textarea': ?><textarea name="<?= e($n) ?>" rows="3" maxlength="5000"<?= $req ?>><?= e((string)$v) ?></textarea><?php break;
    case 'number': case 'money': ?><input name="<?= e($n) ?>" inputmode="decimal" value="<?= e($v === null ? '' : (string)$v) ?>"<?= $req ?>><?php break;
    case 'date': ?><input type="date" name="<?= e($n) ?>" value="<?= e((string)$v) ?>"<?= $req ?>><?php break;
    case 'select': ?><select name="<?= e($n) ?>"<?= $req ?>><option value="">—</option><?php foreach ($fd['options'] as $o): ?><option<?= (string)$v === $o ? ' selected' : '' ?>><?= e($o) ?></option><?php endforeach; ?></select><?php break;
    case 'multiselect': ?><span class="crm-chk"><?php foreach ($fd['options'] as $o): ?><label class="chk"><input type="checkbox" name="<?= e($n) ?>[]" value="<?= e($o) ?>"<?= in_array($o, (array)$v, true) ? ' checked' : '' ?>> <?= e($o) ?></label><?php endforeach; ?></span><?php break;
    case 'checkbox': ?><label class="chk"><input type="checkbox" name="<?= e($n) ?>" value="1"<?= $v ? ' checked' : '' ?>> <?= e($sf->t('yes')) ?></label><?php break;
    case 'email': ?><input type="email" name="<?= e($n) ?>" value="<?= e((string)$v) ?>"<?= $req ?>><?php break;
    case 'url': ?><input type="url" name="<?= e($n) ?>" value="<?= e((string)$v) ?>" placeholder="https://"<?= $req ?>><?php break;
    case 'phone': ?><input type="tel" name="<?= e($n) ?>" value="<?= e((string)$v) ?>"<?= $req ?>><?php break;
    default: ?><input name="<?= e($n) ?>" value="<?= e((string)$v) ?>" maxlength="500"<?= $req ?>><?php endswitch; ?></label>
<?php endforeach; ?>
