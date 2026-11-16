<?php /** @var \Sysfera\Plugin $sf @var array $e @var array $docs @var ?array $pay @var bool $payAllowed @var array $timeline @var array $tasks @var ?array $balance @var array $consents @var bool $consentOn @var array $roles @var array $teams @var array $schedules @var array $managers
   @var array $audit @var ?array $access @var array $users @var bool $self @var bool $editAll @var bool $pii @var bool $docsAdmin @var bool $payAdmin @var bool $offboardOk @var bool $taskAdmin @var bool $collectPassport @var array $whs */
include __DIR__ . '/_nav.php';
$ret = '/employees/' . $e['uuid']; $u = $e['uuid']; $archived = $e['status'] === 'dismissed';
$form = fn(string $op, array $hidden = []) => '<form method="post" action="' . e($sf->url('/do/' . $op)) . '">' . csrf_field() . '<input type="hidden" name="return" value="' . e($ret) . '"><input type="hidden" name="uuid" value="' . e($u) . '">' . implode('', array_map(fn($k, $v) => '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">', array_keys($hidden), $hidden));
$sel = fn(array $list, ?string $cur, string $key = 'uuid', string $label = 'name') => '<option value=""></option>' . implode('', array_map(fn($o) => '<option value="' . e($o[$key]) . '"' . ($o[$key] === $cur ? ' selected' : '') . '>' . e($o[$label]) . '</option>', $list));
$ro = fn(bool $may) => $may && !$archived ? '' : 'disabled';
$pf = $pay ?? ['type' => 'fixed', 'base_kop' => 0, 'mission_bonus_kop' => 0, 'commission_bp' => 0, 'gi' => [], 'deductions' => []]; ?>
<div class="page-h"><h1><?= e($e['full_name']) ?> <code><?= e($e['number']) ?></code> <span class="hr-st <?= $hrst($e['status']) ?>"><?= e($sf->t('es_' . $e['status'])) ?></span></h1></div>
<?php if ($archived): ?><div class="hint"><?= e($sf->t('archived_hint')) ?></div><?php endif; ?>

<div class="card"><h3><?= e($sf->t('sec_card')) ?></h3>
  <?= $form('employees.update') ?>
    <div class="hr-grid">
      <label><?= e($sf->t('f_full_name')) ?><input name="full_name" value="<?= e($e['full_name']) ?>" <?= $ro($editAll) ?>></label>
      <label><?= e($sf->t('f_email')) ?><input name="email" value="<?= e($e['email']) ?>" <?= $ro($editAll) ?>></label>
      <label><?= e($sf->t('f_phone')) ?> <small>(<?= e($sf->t('self_editable')) ?>)</small><input name="phone" value="<?= e($e['phone']) ?>" <?= $ro($editAll || $self) ?>></label>
      <label><?= e($sf->t('f_avatar')) ?> <small>(<?= e($sf->t('self_editable')) ?>)</small><input name="avatar" value="<?= e($e['avatar']) ?>" <?= $ro($editAll || $self) ?>></label>
      <label><?= e($sf->t('f_birth_date')) ?><input type="date" name="birth_date" value="<?= e((string)$e['birth_date']) ?>" <?= $ro($editAll) ?>></label>
      <label><?= e($sf->t('f_hire_date')) ?><input type="date" name="hire_date" value="<?= e($e['hire_date']) ?>" <?= $ro($editAll) ?>></label>
      <label><?= e($sf->t('f_position')) ?><select name="position_role_uuid" <?= $ro($editAll) ?>><?= $sel($roles, $e['position_role_uuid']) ?></select></label>
      <label><?= e($sf->t('f_department')) ?><input name="department" value="<?= e($e['department']) ?>" <?= $ro($editAll) ?>></label>
      <label><?= e($sf->t('f_team')) ?><select name="team_uuid" <?= $ro($editAll) ?>><?= $sel($teams, $e['team_uuid']) ?></select></label>
      <label><?= e($sf->t('f_manager')) ?><select name="manager_uuid" <?= $ro($editAll) ?>><?= $sel(array_values(array_filter($managers, fn($m) => $m['uuid'] !== $u)), $e['manager_uuid'], 'uuid', 'full_name') ?></select></label>
      <label><?= e($sf->t('f_emp_type')) ?><select name="emp_type" <?= $ro($editAll) ?>><?php foreach (\SysferaHr\Fields::EMP_TYPES as $t): ?><option value="<?= e($t) ?>" <?= $e['emp_type'] === $t ? 'selected' : '' ?>><?= e($sf->t('et_' . $t)) ?></option><?php endforeach; ?></select></label>
      <label><?= e($sf->t('f_schedule')) ?><select name="schedule_uuid" <?= $ro($editAll) ?>><?= $sel($schedules, $e['schedule_uuid']) ?></select></label>
      <label><?= e($sf->t('f_warehouse')) ?><select name="warehouse_id" <?= $ro($editAll) ?>><option value=""></option><?php foreach ($whs as $w): ?><option value="<?= (int)$w['id'] ?>" <?= (int)$e['warehouse_id'] === (int)$w['id'] ? 'selected' : '' ?>><?= e($w['name']) ?></option><?php endforeach; ?></select></label>
      <label><?= e($sf->t('f_user')) ?><select name="user_id" <?= $ro($editAll) ?>><option value=""></option><?php foreach ($users as $x): ?><option value="<?= (int)$x['id'] ?>" <?= (int)$e['user_id'] === (int)$x['id'] ? 'selected' : '' ?>><?= e($x['name']) ?> — <?= e($x['email']) ?></option><?php endforeach; ?></select></label>
      <?php if ($collectPassport && ($pii || $self)): ?><label><?= e($sf->t('f_passport')) ?> <small>(<?= e($sf->t('pii_hint')) ?>)</small><input name="passport" value="<?= e((string)$e['passport']) ?>" autocomplete="off" <?= $ro($pii) ?>></label><?php endif; ?>
    </div>
    <?php if (!$archived && ($editAll || $self)): ?><p><button class="btn primary"><?= e($sf->t('save')) ?></button></p><?php endif; ?></form></div>

<?php if ($access !== null): ?><div class="card"><h3><?= e($sf->t('sec_access')) ?></h3>
  <p><?php foreach ($access['roles'] as $r): ?><span class="hr-st info"><?= e($r) ?></span> <?php endforeach; ?><?php if (!$access['roles']): ?><span class="muted"><?= e($sf->t('none')) ?></span><?php endif; ?></p>
  <p class="muted"><?= e($sf->t('gi_access')) ?>: <?= $access['warehouse'] ? e($sf->t('role_' . $access['warehouse']['role'])) . ($access['warehouse']['perms'] !== '' ? ' (' . e($access['warehouse']['perms']) . ')' : '') : e($sf->t('none')) ?></p></div><?php endif; ?>

<div class="card"><h3><?= e($sf->t('sec_consent')) ?></h3>
  <p><span class="hr-st <?= $consentOn ? 'ok' : 'warn' ?>"><?= e($sf->t($consentOn ? 'consent_on' : 'consent_off')) ?></span> <?php foreach ($consents as $k): ?><span class="muted"> · <?= e($k['at']) ?> <?= e($k['action']) ?> v<?= e($k['version']) ?></span><?php endforeach; ?></p>
  <?php if (!$archived && ($self || $c->can('hr.consent.manage'))): ?><?= $form('consent.set', ['kind' => 'personal_data', 'action' => $consentOn ? 'revoke' : 'grant']) ?><button class="btn sm"><?= e($sf->t($consentOn ? 'consent_revoke' : 'consent_grant')) ?></button></form><?php endif; ?></div>

<div class="card flush"><h3 style="padding:12px 12px 0"><?= e($sf->t('sec_docs')) ?></h3><table class="tbl"><tr><th><?= e($sf->t('f_title')) ?></th><th><?= e($sf->t('f_kind')) ?></th><th>v</th><th><?= e($sf->t('f_status')) ?></th><th></th></tr>
  <?php if (!$docs): ?><tr><td colspan="5" class="muted"><?= e($sf->t('none')) ?></td></tr><?php endif; ?>
  <?php foreach ($docs as $d): ?><tr><td><?= e($d['title']) ?><br><code class="muted"><?= e(substr($d['body_sha256'], 0, 12)) ?></code></td><td><?= e($sf->t('dk_' . $d['kind'])) ?></td><td><?= (int)$d['version'] ?><?= (int)$d['superseded'] ? ' <span class="muted">(' . e($sf->t('superseded')) . ')</span>' : '' ?></td>
    <td><span class="hr-st <?= $hrst($d['effective_status']) ?>"><?= e($sf->t('st_' . $d['effective_status'])) ?></span><?= $d['signed_at'] ? '<br><span class="muted">' . e($d['signed_at']) . ' · ' . e($sf->t('sm_' . $d['sign_method'])) . '</span>' : '' ?></td>
    <td><a class="btn sm" target="_blank" href="<?= e($sf->url('/doc/' . $d['uuid'] . '/print')) ?>"><?= e($sf->t('print')) ?></a>
      <?php if ($d['status'] === 'unsigned' && !(int)$d['superseded']): ?>
        <?php if ($self): ?><?= $form('docs.sign', ['uuid' => $d['uuid'], 'method' => 'esign', 'confirm' => '1', 'body_sha256' => $d['body_sha256']]) ?><button class="btn sm primary"><?= e($sf->t('sign_esign')) ?></button></form><?php endif; ?>
        <?php if ($docsAdmin): ?><form method="post" enctype="multipart/form-data" action="<?= e($sf->url('/do/docs.sign')) ?>"><?= csrf_field() ?><input type="hidden" name="return" value="<?= e($ret) ?>"><input type="hidden" name="uuid" value="<?= e($d['uuid']) ?>"><input type="hidden" name="method" value="scan"><input type="file" name="scan" required><button class="btn sm"><?= e($sf->t('sign_scan')) ?></button></form><?php endif; ?>
      <?php endif; ?><?php if ($docsAdmin && $d['status'] !== 'expired'): ?><?= $form('docs.expire', ['uuid' => $d['uuid']]) ?><button class="btn sm"><?= e($sf->t('act_expire')) ?></button></form><?php endif; ?></td></tr><?php endforeach; ?></table>
  <?php if ($docsAdmin && !$archived): ?><div style="padding:12px"><?= $form('docs.add') ?>
    <div class="hr-grid"><label><?= e($sf->t('f_kind')) ?><select name="kind"><?php foreach (\SysferaHr\Hr::DOC_KINDS as $k): ?><option value="<?= e($k) ?>"><?= e($sf->t('dk_' . $k)) ?></option><?php endforeach; ?></select></label><label><?= e($sf->t('f_title')) ?><input name="title" required maxlength="160"></label>
      <label><?= e($sf->t('f_new_version_of')) ?><select name="doc_key"><option value=""></option><?php foreach ($docs as $d): if ((int)$d['superseded']) continue; ?><option value="<?= e($d['doc_key']) ?>"><?= e($d['title']) ?></option><?php endforeach; ?></select></label>
      <label><?= e($sf->t('f_expires')) ?><input type="date" name="expires_at"></label></div>
    <label class="fld"><?= e($sf->t('f_text')) ?><textarea name="body" rows="5" required style="width:100%"></textarea></label><button class="btn primary"><?= e($sf->t('add')) ?></button></form></div><?php endif; ?></div>

<?php if ($payAllowed): ?><div class="card" id="pay"><h3><?= e($sf->t('sec_pay')) ?></h3><p class="muted"><?= e($sf->t('pay_hint')) ?></p>
  <?php if ($payAdmin && !$archived): ?>
  <form method="post" action="<?= e($sf->url('/do/pay.set')) ?>"><?= csrf_field() ?><input type="hidden" name="return" value="<?= e($ret) ?>"><input type="hidden" name="uuid" value="<?= e($u) ?>">
    <div class="hr-grid"><label><?= e($sf->t('f_pay_type')) ?><select name="pay[type]"><?php foreach (\SysferaHr\Payroll::TYPES as $t): ?><option value="<?= e($t) ?>" <?= $pf['type'] === $t ? 'selected' : '' ?>><?= e($sf->t('pt_' . $t)) ?></option><?php endforeach; ?></select></label>
      <label><?= e($sf->t('f_base')) ?><input name="pay[base]" value="<?= e($money($pf['base_kop'])) ?>" inputmode="decimal"></label><label><?= e($sf->t('f_mission_bonus')) ?><input name="pay[mission_bonus]" value="<?= e($money($pf['mission_bonus_kop'])) ?>" inputmode="decimal"></label>
      <label><?= e($sf->t('f_commission')) ?><input name="pay[commission_pct]" value="<?= e(rtrim(rtrim(number_format($pf['commission_bp'] / 100, 2, '.', ''), '0'), '.')) ?>" inputmode="decimal"></label>
      <?php foreach (['gi_issue' => 'per_issue_kop', 'gi_receipt' => 'per_receipt_kop', 'gi_return' => 'per_return_kop', 'gi_count' => 'per_count_kop'] as $k => $f): ?><label><?= e($sf->t('f_' . $k)) ?><input name="pay[<?= e($k) ?>]" value="<?= e($money($pf['gi'][$f] ?? 0)) ?>" inputmode="decimal"></label><?php endforeach; ?></div>
    <h4><?= e($sf->t('f_deductions')) ?></h4>
    <?php for ($i = 0; $i < 3; $i++): $d = $pf['deductions'][$i] ?? ['code' => '', 'label' => '', 'kind' => 'fixed', 'value' => 0]; ?><div class="row"><input name="pay[ded][<?= $i ?>][code]" value="<?= e($d['code']) ?>" placeholder="code" size="10"><input name="pay[ded][<?= $i ?>][label]" value="<?= e($d['label']) ?>" placeholder="<?= e($sf->t('f_label')) ?>">
      <select name="pay[ded][<?= $i ?>][kind]"><option value="fixed" <?= $d['kind'] === 'fixed' ? 'selected' : '' ?>><?= e($sf->t('ded_fixed')) ?></option><option value="percent" <?= $d['kind'] === 'percent' ? 'selected' : '' ?>><?= e($sf->t('ded_percent')) ?></option></select>
      <input name="pay[ded][<?= $i ?>][value]" value="<?= $d['value'] ? e($d['kind'] === 'percent' ? (string)($d['value'] / 100) : $money($d['value'])) : '' ?>" size="9" inputmode="decimal"></div><?php endfor; ?>
    <button class="btn primary"><?= e($sf->t('save')) ?></button></form>
  <?php elseif ($pay): ?><p><?= e($sf->t('pt_' . $pf['type'])) ?>: <b><?= e($money($pf['base_kop'])) ?></b> ₽</p><?php else: ?><p class="muted"><?= e($sf->t('none')) ?></p><?php endif; ?></div><?php endif; ?>

<?php foreach (['onboarding', 'offboarding'] as $ph): if (!$tasks[$ph]) continue; ?><div class="card"><h3><?= e($sf->t('sec_' . $ph)) ?></h3>
  <?php foreach ($tasks[$ph] as $t): ?><div class="row"><span class="hr-st <?= (int)$t['done'] ? 'ok' : ($t['due_at'] < date('Y-m-d') ? 'bad' : 'warn') ?>"><?= e($sf->t((int)$t['done'] ? 'done' : 'open')) ?></span> <b><?= e($tk($t['code'])) ?></b> <span class="muted"><?= e($sf->t('f_due')) ?> <?= e($t['due_at']) ?></span>
    <?php if ($taskAdmin): ?><?= $form('tasks.done', ['uuid' => $t['uuid'], 'done' => (int)$t['done'] ? '0' : '1']) ?><button class="btn sm"><?= e($sf->t((int)$t['done'] ? 'reopen' : 'mark_done')) ?></button></form><?php endif; ?></div><?php endforeach; ?></div><?php endforeach; ?>

<?php if ($balance): ?><div class="card"><h3><?= e($sf->t('sec_balance')) ?></h3><p><?= e($sf->t('bal_accrued')) ?>: <b><?= e((string)$balance['accrued']) ?></b> · <?= e($sf->t('bal_used')) ?>: <b><?= e((string)$balance['used']) ?></b> · <?= e($sf->t('bal_available')) ?>: <b><?= e((string)$balance['available']) ?></b></p></div><?php endif; ?>

<?php if ($timeline): ?><div class="card"><h3><?= e($sf->t('sec_timeline')) ?></h3><table class="tbl"><?php foreach ($timeline as $t): ?><tr><td><?= e(substr($t['at'], 0, 10)) ?></td><td><b><?= e($sf->t('tl_' . $t['kind'])) ?></b></td><td class="muted"><?= e($t['data']) ?></td></tr><?php endforeach; ?></table></div><?php endif; ?>

<?php if ($audit): ?><details class="card"><summary><b><?= e($sf->t('sec_changes')) ?></b></summary><table class="tbl"><tr><th><?= e($sf->t('f_when')) ?></th><th><?= e($sf->t('f_who')) ?></th><th><?= e($sf->t('f_action')) ?></th><th><?= e($sf->t('f_details')) ?></th></tr>
  <?php foreach ($audit as $a): ?><tr><td><?= e($a['ts']) ?></td><td><?= e($a['actor']) ?></td><td><code><?= e($a['action']) ?></code></td><td class="muted"><?= e((string)$a['data']) ?></td></tr><?php endforeach; ?></table></details><?php endif; ?>

<?php if ($offboardOk && !$archived): ?><div class="card"><h3><?= e($sf->t('sec_offboard')) ?></h3><p class="muted"><?= e($sf->t('offboard_hint')) ?></p>
  <?= $form('offboard') ?><div class="row"><input type="date" name="last_day" value="<?= e(date('Y-m-d')) ?>"><input name="reason" placeholder="<?= e($sf->t('f_reason')) ?>" maxlength="300"><button class="btn" onclick="return confirm('<?= e($sf->t('confirm_offboard')) ?>')"><?= e($sf->t('act_offboard')) ?></button></div></form></div><?php endif; ?>
