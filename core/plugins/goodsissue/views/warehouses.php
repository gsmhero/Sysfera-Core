<?php /** @var \Sysfera\Plugin $sf @var string $tab @var bool $manage @var array $whs @var array $wcfg @var array $staff @var array $users @var array $devices */
include __DIR__ . '/_nav.php';
$perms = \SysferaGi\Exchange::PERMS; $has = fn(array $s, string $p) => (($s['perms'] ?? '') === '') || in_array($p, explode(',', (string)$s['perms']), true);
$val = fn(int $id, string $k, ?int $div = null) => isset($wcfg[$id][$k]) ? ($div ? (string)intdiv((int)$wcfg[$id][$k], $div) : ($k === 'id_threshold_kop' || $k === 'photo_threshold_kop' ? \SysferaGi\Units::fmt((int)$wcfg[$id][$k]) : (string)$wcfg[$id][$k])) : ''; ?>
<div class="page-h"><h1><?= e($sf->t('nav_warehouses')) ?></h1></div>
<?php if (!$manage): ?><div class="hint"><?= e($sf->t('read_only')) ?></div><?php endif; ?>
<div class="card"><h3><?= e($sf->t('pickup_whs')) ?></h3>
  <?php foreach ($whs as $w): $id = (int)$w['id']; ?>
  <details class="gi-wh"><summary><b><?= e($w['name']) ?></b> <code><?= e($w['code']) ?></code> <span class="muted"><?= e($sf->t($w['active'] ? 'active' : 'inactive')) ?></span><?= !empty($wcfg[$id]) ? ' <span class="gi-st">' . e($sf->t('wh_overrides')) . '</span>' : '' ?></summary>
    <?php if ($manage): ?>
    <form method="post" class="row"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input name="name" value="<?= e($w['name']) ?>" maxlength="160"><button class="btn sm" name="do" value="rename_wh"><?= e($sf->t('save')) ?></button><button class="btn sm" name="do" value="toggle_wh"><?= e($sf->t($w['active'] ? 'deactivate' : 'activate')) ?></button></form>
    <form method="post" class="gi-wcfg"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><p class="muted"><?= e($sf->t('wh_overrides_h')) ?></p>
      <div class="row"><label><?= e($sf->t('set_threshold')) ?> <input name="id_threshold" value="<?= e($val($id, 'id_threshold_kop')) ?>" placeholder="<?= e($cfg['id_threshold_kop'] ? \SysferaGi\Units::fmt((int)$cfg['id_threshold_kop']) : '0') ?>" size="9" inputmode="decimal"></label>
        <label><?= e($sf->t('set_photo_threshold')) ?> <input name="photo_threshold" value="<?= e($val($id, 'photo_threshold_kop')) ?>" placeholder="<?= e(\SysferaGi\Units::fmt((int)$cfg['photo_threshold_kop'])) ?>" size="9" inputmode="decimal"></label>
        <label><?= e($sf->t('set_ttl')) ?> <input name="code_ttl_h" value="<?= e($val($id, 'code_ttl', 3600)) ?>" placeholder="<?= (int)($cfg['code_ttl'] / 3600) ?>" size="4" inputmode="numeric"></label>
        <label><?= e($sf->t('set_overdue')) ?> <input name="overdue_days" value="<?= e($val($id, 'overdue_days')) ?>" placeholder="<?= (int)$cfg['overdue_days'] ?>" size="4" inputmode="numeric"></label>
        <label><?= e($sf->t('set_refund_days')) ?> <input name="refund_days" value="<?= e($val($id, 'refund_days')) ?>" placeholder="<?= (int)$cfg['refund_days'] ?>" size="4" inputmode="numeric"></label>
        <button class="btn sm primary" name="do" value="wcfg"><?= e($sf->t('save')) ?></button></div></form><?php endif; ?>
  </details><?php endforeach; ?>
  <?php if ($manage): ?><form method="post" class="row"><?= csrf_field() ?><input name="code" placeholder="<?= e($sf->t('wh_code')) ?>" maxlength="40" required><input name="name" placeholder="<?= e($sf->t('wh_name')) ?>" maxlength="160" required><button class="btn primary" name="do" value="add_wh"><?= e($sf->t('add')) ?></button></form><?php endif; ?></div>
<div class="card"><h3><?= e($sf->t('staff')) ?></h3><p class="muted"><?= e($sf->t('staff_hint')) ?></p>
  <?php foreach ($staff as $s): ?><form method="post" class="row"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$s['user_id'] ?>"><input type="hidden" name="role" value="<?= e($s['role']) ?>"><input type="hidden" name="warehouse_id" value="<?= (int)($s['warehouse_id'] ?? 0) ?>"><input type="hidden" name="perms_set" value="1">
    <b><?= e($s['name']) ?></b> <span class="muted"><?= e($s['email']) ?></span> <span class="gi-st"><?= e($sf->t('role_' . $s['role'])) ?></span> <?= e($s['wname'] ?? '') ?>
    <?php if ($s['role'] === 'operator'): foreach ($perms as $p): ?><label class="gi-perm"><input type="checkbox" name="perms[]" value="<?= $p ?>" <?= $has($s, $p) ? 'checked' : '' ?> <?= $manage ? '' : 'disabled' ?>> <?= e($sf->t('perm_' . $p)) ?></label><?php endforeach; endif; ?>
    <?php if ($manage): ?><?php if ($s['role'] === 'operator'): ?><button class="btn sm" name="do" value="add_staff"><?= e($sf->t('save')) ?></button><?php endif; ?><button class="btn sm" name="do" value="del_staff" onclick="return confirm('<?= e($sf->t('confirm_remove')) ?>')"><?= e($sf->t('remove')) ?></button><?php endif; ?></form><?php endforeach; ?>
  <?php if ($manage): ?><form method="post" class="row"><?= csrf_field() ?><input type="hidden" name="perms_set" value="1"><select name="user_id"><?php foreach ($users as $u): ?><option value="<?= (int)$u['id'] ?>"><?= e($u['name']) ?> — <?= e($u['email']) ?></option><?php endforeach; ?></select>
    <select name="role"><option value="operator"><?= e($sf->t('role_operator')) ?></option><option value="office"><?= e($sf->t('role_office')) ?></option><option value="viewer"><?= e($sf->t('role_viewer')) ?></option></select>
    <select name="warehouse_id"><?php foreach ($whs as $w): ?><option value="<?= (int)$w['id'] ?>"><?= e($w['name']) ?></option><?php endforeach; ?></select>
    <?php foreach ($perms as $p): ?><label class="gi-perm"><input type="checkbox" name="perms[]" value="<?= $p ?>" checked> <?= e($sf->t('perm_' . $p)) ?></label><?php endforeach; ?>
    <button class="btn primary" name="do" value="add_staff"><?= e($sf->t('add')) ?></button></form><?php endif; ?></div>
<div class="card"><h3><?= e($sf->t('devices')) ?></h3><p class="muted"><?= e($sf->t('devices_hint')) ?></p>
  <?php if (!$devices): ?><p class="muted"><?= e($sf->t('none')) ?></p><?php endif; ?>
  <?php foreach ($devices as $d): ?><form method="post" class="row"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$d['id'] ?>"><b><?= e($d['name']) ?></b> <span class="muted"><?= e($d['label']) ?> · <code><?= e(substr($d['device_id'], 0, 10)) ?></code> · <?= e((string)$d['last_seen']) ?></span> <span class="gi-st gi-dev-<?= e($d['status']) ?>"><?= e($sf->t('dev_' . $d['status'])) ?></span>
    <?php if ($manage): ?><?php if ($d['status'] !== 'approved'): ?><button class="btn sm primary" name="do" value="dev_approve"><?= e($sf->t('approve')) ?></button><?php endif; ?><?php if ($d['status'] !== 'revoked'): ?><button class="btn sm" name="do" value="dev_revoke"><?= e($sf->t('revoke')) ?></button><?php endif; ?><?php endif; ?></form><?php endforeach; ?></div>
