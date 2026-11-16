<?php /** @var \Sysfera\Plugin $sf @var array $users @var array $roles @var array $catalog @var array $orphans */
include __DIR__ . '/_nav.php'; ?>
<div class="page-h"><h1><?= e($sf->t('nav_access')) ?></h1></div>
<div class="hint"><?= e($sf->t('access_hint')) ?></div>
<?php if ($orphans): ?><div class="card"><h3><?= e($sf->t('orphans_h')) ?></h3>
  <?php foreach ($orphans as $o): ?><p><code>#<?= (int)$o['user_id'] ?></code> <span class="hr-st bad"><?= e($sf->t('orphan_' . $o['why'])) ?></span></p><?php endforeach; ?>
  <form method="post" action="<?= e($sf->url('/do/rbac.revoke_orphans')) ?>"><?= csrf_field() ?><input type="hidden" name="return" value="/access"><button class="btn primary"><?= e($sf->t('orphans_revoke')) ?></button></form></div><?php endif; ?>
<div class="card flush"><table class="tbl"><tr><th><?= e($sf->t('f_user')) ?></th><th><?= e($sf->t('f_roles')) ?></th><th><?= e($sf->t('gi_access')) ?></th><th></th></tr>
  <?php foreach ($users as $u): $has = $u['roles']; ?><tr><td><b><?= e($u['name']) ?></b><br><span class="muted"><?= e($u['email']) ?></span></td>
    <td><?php foreach ($roles as $r): if (!in_array($r['code'], $has, true)) continue; ?>
      <form method="post" action="<?= e($sf->url('/do/rbac.revoke')) ?>" style="display:inline"><?= csrf_field() ?><input type="hidden" name="return" value="/access"><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>"><input type="hidden" name="role_uuid" value="<?= e($r['uuid']) ?>"><button class="hr-st info" title="<?= e($sf->t('revoke')) ?>"><?= e($r['name']) ?> ×</button></form><?php endforeach; ?>
      <?php if (!$has): ?><span class="muted"><?= e($sf->t('none')) ?></span><?php endif; ?></td>
    <td><?= $u['warehouse'] ? e($sf->t('role_' . $u['warehouse']['role'])) . ($u['warehouse']['perms'] !== '' ? ' <span class="muted">(' . e($u['warehouse']['perms']) . ')</span>' : '') : '<span class="muted">—</span>' ?></td>
    <td><form method="post" action="<?= e($sf->url('/do/rbac.grant')) ?>" class="row"><?= csrf_field() ?><input type="hidden" name="return" value="/access"><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>"><select name="role_uuid"><?php foreach ($roles as $r): if (!(int)$r['active'] || in_array($r['code'], $has, true)) continue; ?><option value="<?= e($r['uuid']) ?>"><?= e($r['name']) ?></option><?php endforeach; ?></select><button class="btn sm primary"><?= e($sf->t('grant')) ?></button></form></td></tr><?php endforeach; ?></table></div>
<div class="card flush"><h3 style="padding:12px 12px 0"><?= e($sf->t('matrix')) ?></h3><table class="tbl"><tr><th><?= e($sf->t('f_permission')) ?></th><?php foreach ($roles as $r): if (!(int)$r['active']) continue; ?><th><?= e($r['name']) ?></th><?php endforeach; ?></tr>
  <?php foreach ($catalog as $p): ?><tr><td><code><?= e($p) ?></code><br><span class="muted"><?= e($sf->t('perm_' . str_replace('.', '_', $p))) ?></span></td><?php foreach ($roles as $r): if (!(int)$r['active']) continue; ?><td><?= \SysferaHr\Rbac::matches($r['perm_list'], $p) ? '✓' : '' ?></td><?php endforeach; ?></tr><?php endforeach; ?></table></div>
