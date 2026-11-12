<?php /** @var array $users @var array $caps @var int $me */ ?>
<div class="page-h"><h1><?= e(t('nav.users')) ?></h1></div>
<div class="hint"><?= e(t('users.hint')) ?></div>
<div class="list">
<?php foreach ($users as $x): $have = json_decode((string)$x['caps'], true) ?: []; $self = (int)$x['id'] === $me; ?>
  <form method="post" class="card item col"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$x['id'] ?>">
    <div class="row between"><div><b><?= e($x['name']) ?></b> <small class="muted"><?= e($x['email']) ?></small> <?php if ($x['status'] === 'blocked'): ?><span class="badge bad"><?= e(t('users.blocked')) ?></span><?php endif; ?><div class="muted sm"><?= e(t('users.last_login')) ?>: <?= e(ago($x['last_login'])) ?></div></div>
      <?php if ($self): ?><span class="badge ok"><?= e(t('users.you')) ?></span><?php endif; ?></div>
    <?php if (!$self): ?>
    <div class="row">
      <label class="fld inline"><span><?= e(t('users.role')) ?></span><select name="role"><option value="user" <?= $x['role'] === 'user' ? 'selected' : '' ?>><?= e(t('role.user')) ?></option><option value="admin" <?= $x['role'] === 'admin' ? 'selected' : '' ?>><?= e(t('role.admin')) ?></option></select></label>
      <label class="fld inline"><span><?= e(t('users.status')) ?></span><select name="status"><option value="active"><?= e(t('users.active')) ?></option><option value="blocked" <?= $x['status'] === 'blocked' ? 'selected' : '' ?>><?= e(t('users.blocked')) ?></option></select></label>
    </div>
    <?php if ($caps): ?><div class="caps"><span class="muted sm"><?= e(t('users.caps')) ?></span><?php foreach ($caps as $c => $label): ?><label class="chk"><input type="checkbox" name="caps[]" value="<?= e($c) ?>" <?= in_array($c, $have, true) ? 'checked' : '' ?>> <?= e($label) ?></label><?php endforeach; ?></div><?php endif; ?>
    <div class="row"><button class="btn primary" name="do" value="save"><?= e(t('common.save')) ?></button><button class="btn danger" name="do" value="delete" data-confirm="<?= e(t('users.confirm_delete')) ?>"><?= e(t('common.delete')) ?></button></div>
    <?php endif; ?>
  </form>
<?php endforeach; ?>
</div>
<h2><?= e(t('users.add')) ?></h2>
<form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="do" value="create">
  <div class="cols">
    <label class="fld"><span><?= e(t('auth.name')) ?></span><input name="name" required></label>
    <label class="fld"><span><?= e(t('auth.email')) ?></span><input type="email" name="email" required></label>
    <label class="fld"><span><?= e(t('auth.password_min')) ?></span><input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
    <label class="fld"><span><?= e(t('users.role')) ?></span><select name="role"><option value="user"><?= e(t('role.user')) ?></option><option value="admin"><?= e(t('role.admin')) ?></option></select></label>
  </div><button class="btn primary"><?= e(t('users.add')) ?></button></form>
