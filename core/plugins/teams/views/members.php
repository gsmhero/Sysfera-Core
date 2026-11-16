<?php /** @var \Sysfera\Plugin $sf @var array $t @var list<array> $mem @var list<array> $others */
use SysferaTeams\Tm;
$tab = 'members'; require __DIR__ . '/_nav.php'; $tid = (int)$t['id']; $me = $sf->auth()->id(); $act = fn(string $p) => e($sf->url('/t/' . $tid . $p)); ?>
<div class="tm-two"><div>
<section class="card"><h3><?= e($sf->t('members')) ?> (<?= count($mem) ?>)</h3>
  <?php foreach ($mem as $u): $canRole = Tm::can($t['role'], 'roles') && (int)$u['id'] !== $me; $canRm = Tm::can($t['role'], 'members') && $u['role'] !== 'owner' && (int)$u['id'] !== $me && ($u['role'] === 'member' || Tm::can($t['role'], 'roles')); ?>
  <div class="tm-mem"><i class="tm-av"><?= e(mb_strtoupper(mb_substr($u['name'], 0, 1))) ?></i><span class="grow"><b><?= e($u['name']) ?></b><small class="muted"> <?= e($u['email']) ?></small></span>
    <?php if ($canRole): ?><form method="post" action="<?= $act('/members/role') ?>" class="row"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
      <select name="role"><?php foreach (Tm::ROLES as $r): ?><option value="<?= $r ?>"<?= $u['role'] === $r ? ' selected' : '' ?>><?= e($sf->t('role_' . $r)) ?></option><?php endforeach; ?></select><button class="btn sm"><?= e($sf->t('save')) ?></button></form>
    <?php else: ?><span class="muted sm"><?= e($sf->t('role_' . $u['role'])) ?></span><?php endif; ?>
    <?php if ($canRm): ?><form method="post" action="<?= $act('/members/remove') ?>" data-confirm="<?= e($sf->t('confirm_remove')) ?>"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>"><button class="btn sm danger" title="<?= e($sf->t('remove')) ?>"><?= e($sf->t('remove')) ?></button></form><?php endif; ?></div>
  <?php endforeach; ?>
  <?php if (in_array($me, array_map('intval', array_column($mem, 'id')), true)): ?>
  <form method="post" action="<?= $act('/leave') ?>" data-confirm="<?= e($sf->t('confirm_leave')) ?>" style="margin-top:12px"><?= csrf_field() ?><button class="btn danger sm"><?= e($sf->t('leave')) ?></button></form><?php endif; ?></section>

<?php if (Tm::can($t['role'], 'members')): $invites = $invites ?? []; $canRoles = Tm::can($t['role'], 'roles');
  $roleSel = fn() => $canRoles ? '<select name="role" title="' . e($sf->t('inv_role')) . '"><option value="member">' . e($sf->t('role_member')) . '</option><option value="admin">' . e($sf->t('role_admin')) . '</option></select>' : ''; ?>
<section class="card tm-inv"><h3><?= e($sf->t('inv_h')) ?></h3>
  <?php if ($others): ?><form method="post" action="<?= $act('/members/add') ?>" class="tm-invrow"><?= csrf_field() ?><label class="fld grow"><span><?= e($sf->t('inv_existing')) ?></span><select name="user_id"><?php foreach ($others as $u): ?><option value="<?= (int)$u['id'] ?>"><?= e($u['name']) ?> — <?= e($u['email']) ?></option><?php endforeach; ?></select></label><?= $roleSel() ?><button class="btn primary"><?= e($sf->t('add_member')) ?></button></form><?php endif; ?>
  <form method="post" action="<?= $act('/members/invite') ?>" class="tm-invrow"><?= csrf_field() ?><label class="fld grow"><span><?= e($sf->t('inv_by_mail')) ?></span><input name="emails" placeholder="name@example.com, other@example.com" required></label><?= $roleSel() ?><button class="btn primary"><?= e($sf->t('inv_send')) ?></button></form>
  <form method="post" action="<?= $act('/members/link') ?>" class="tm-invrow"><?= csrf_field() ?><span class="muted sm grow"><?= e($sf->t('inv_link_hint')) ?></span><?= $roleSel() ?><button class="btn"><?= e($sf->t('inv_make_link')) ?></button></form>
</section>
<?php if ($invites): ?><section class="card"><h3><?= e($sf->t('inv_pending')) ?> (<?= count($invites) ?>)</h3>
  <?php foreach ($invites as $iv): $url = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $sf->url('/invite/' . $iv['token']); ?>
  <div class="tm-invp"><div class="row between"><b><?= e($iv['email'] ?: $sf->t('inv_a_link')) ?></b><span class="muted sm"><?= e($sf->t('role_' . $iv['role'])) ?> · <?= e($sf->t('inv_until', ['d' => date('d/m/Y', strtotime((string)$iv['expires_at']))])) ?></span></div>
    <div class="row"><input readonly value="<?= e($url) ?>" onclick="this.select()" style="flex:1"><form method="post" action="<?= $act('/members/revoke') ?>" data-confirm="<?= e($sf->t('inv_confirm_revoke')) ?>"><?= csrf_field() ?><input type="hidden" name="invite" value="<?= (int)$iv['id'] ?>"><button class="btn sm danger"><?= e($sf->t('inv_revoke')) ?></button></form></div></div>
  <?php endforeach; ?></section><?php endif; ?>
<?php endif; ?></div><div>
<?php if (Tm::can($t['role'], 'edit_team')): ?><form method="post" action="<?= $act('/settings') ?>" class="card"><?= csrf_field() ?><h3><?= e($sf->t('team_settings')) ?></h3>
  <label class="fld"><span><?= e($sf->t('f_name')) ?></span><input name="name" value="<?= e($t['name']) ?>" required maxlength="120"></label>
  <label class="fld"><span><?= e($sf->t('f_descr')) ?></span><input name="descr" value="<?= e((string)$t['descr']) ?>" maxlength="500"></label>
  <div class="cols"><label class="fld"><span><?= e($sf->t('f_privacy')) ?></span><select name="privacy"><option value="private"<?= $t['privacy'] === 'private' ? ' selected' : '' ?>><?= e($sf->t('private')) ?></option><option value="open"<?= $t['privacy'] === 'open' ? ' selected' : '' ?>><?= e($sf->t('open_team')) ?></option></select></label>
    <label class="fld"><span><?= e($sf->t('f_color')) ?></span><input type="color" name="color" value="<?= e($t['color']) ?>"></label></div>
  <button class="btn primary"><?= e($sf->t('save')) ?></button>
  <?php if (Tm::can($t['role'], 'delete_team')): ?><button class="btn danger" name="archive" value="1" formnovalidate data-confirm="<?= e($sf->t('confirm_archive_team')) ?>"><?= e($sf->t('archive_team')) ?></button><?php endif; ?></form><?php endif; ?>
<section class="card"><h3><?= e($sf->t('roles_h')) ?></h3><p class="muted sm"><b><?= e($sf->t('role_owner')) ?></b> — <?= e($sf->t('role_owner_d')) ?></p><p class="muted sm"><b><?= e($sf->t('role_admin')) ?></b> — <?= e($sf->t('role_admin_d')) ?></p><p class="muted sm"><b><?= e($sf->t('role_member')) ?></b> — <?= e($sf->t('role_member_d')) ?></p></section>
</div></div>
