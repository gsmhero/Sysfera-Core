<?php /** @var \Sysfera\Plugin $sf @var array $t @var list<array> $mem @var list<array> $others */
use SysferaTeams\Tm;
$tab = 'members'; require __DIR__ . '/_nav.php'; $tid = (int)$t['id']; $me = $sf->auth()->id(); $act = fn(string $p) => e($sf->url('/t/' . $tid . $p)); ?>
<div class="tm-two"><div>
<section class="card"><h3><?= e($sf->t('members')) ?> (<?= count($mem) ?>)</h3>
  <?php foreach ($mem as $u): ?><div class="tm-mem"><i class="tm-av"><?= e(mb_strtoupper(mb_substr($u['name'], 0, 1))) ?></i><span class="grow"><b><?= e($u['name']) ?></b><small class="muted"> <?= e($u['email']) ?></small></span>
    <?php if (Tm::can($t['role'], 'roles') && (int)$u['id'] !== $me): ?><form method="post" action="<?= $act('/members/role') ?>" class="row"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
      <select name="role" onchange="this.form.submit()"><?php foreach (Tm::ROLES as $r): ?><option value="<?= $r ?>"<?= $u['role'] === $r ? ' selected' : '' ?>><?= e($sf->t('role_' . $r)) ?></option><?php endforeach; ?></select></form>
    <?php else: ?><span class="muted sm"><?= e($sf->t('role_' . $u['role'])) ?></span><?php endif; ?>
    <?php if (Tm::can($t['role'], 'members') && $u['role'] !== 'owner' && (int)$u['id'] !== $me && ($u['role'] === 'member' || Tm::can($t['role'], 'roles'))): ?><form method="post" action="<?= $act('/members/remove') ?>" data-confirm="<?= e($sf->t('confirm_remove')) ?>"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>"><button class="tm-rm" title="<?= e($sf->t('remove')) ?>">×</button></form><?php endif; ?></div><?php endforeach; ?>
  <?php if (in_array($me, array_map('intval', array_column($mem, 'id')), true)): ?>
  <form method="post" action="<?= $act('/leave') ?>" data-confirm="<?= e($sf->t('confirm_leave')) ?>" style="margin-top:12px"><?= csrf_field() ?><button class="btn danger sm"><?= e($sf->t('leave')) ?></button></form><?php endif; ?></section>
<?php if (Tm::can($t['role'], 'members') && $others): ?><form method="post" action="<?= $act('/members/add') ?>" class="card row"><?= csrf_field() ?>
  <select name="user_id" style="flex:1"><?php foreach ($others as $u): ?><option value="<?= (int)$u['id'] ?>"><?= e($u['name']) ?> — <?= e($u['email']) ?></option><?php endforeach; ?></select><button class="btn primary"><?= e($sf->t('add_member')) ?></button></form><?php endif; ?>
</div><div>
<?php if (Tm::can($t['role'], 'edit_team')): ?><form method="post" action="<?= $act('/settings') ?>" class="card"><?= csrf_field() ?><h3><?= e($sf->t('team_settings')) ?></h3>
  <label class="fld"><span><?= e($sf->t('f_name')) ?></span><input name="name" value="<?= e($t['name']) ?>" required maxlength="120"></label>
  <label class="fld"><span><?= e($sf->t('f_descr')) ?></span><input name="descr" value="<?= e((string)$t['descr']) ?>" maxlength="500"></label>
  <div class="cols"><label class="fld"><span><?= e($sf->t('f_privacy')) ?></span><select name="privacy"><option value="private"<?= $t['privacy'] === 'private' ? ' selected' : '' ?>><?= e($sf->t('private')) ?></option><option value="open"<?= $t['privacy'] === 'open' ? ' selected' : '' ?>><?= e($sf->t('open_team')) ?></option></select></label>
    <label class="fld"><span><?= e($sf->t('f_color')) ?></span><input type="color" name="color" value="<?= e($t['color']) ?>"></label></div>
  <button class="btn primary"><?= e($sf->t('save')) ?></button>
  <?php if (Tm::can($t['role'], 'delete_team')): ?><button class="btn danger" name="archive" value="1" formnovalidate data-confirm="<?= e($sf->t('confirm_archive_team')) ?>"><?= e($sf->t('archive_team')) ?></button><?php endif; ?></form><?php endif; ?>
<section class="card"><h3><?= e($sf->t('roles_h')) ?></h3><p class="muted sm"><b><?= e($sf->t('role_owner')) ?></b> — <?= e($sf->t('role_owner_d')) ?></p><p class="muted sm"><b><?= e($sf->t('role_admin')) ?></b> — <?= e($sf->t('role_admin_d')) ?></p><p class="muted sm"><b><?= e($sf->t('role_member')) ?></b> — <?= e($sf->t('role_member_d')) ?></p></section>
</div></div>
