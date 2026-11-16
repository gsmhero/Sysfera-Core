<?php /** @var \Sysfera\Plugin $sf @var array $roles @var array $catalog */
include __DIR__ . '/_nav.php'; ?>
<div class="page-h"><h1><?= e($sf->t('nav_roles')) ?></h1></div>
<div class="hint"><?= e($sf->t('roles_hint')) ?></div>
<div class="card"><h3><?= e($sf->t('role_new')) ?></h3>
  <form method="post" action="<?= e($sf->url('/do/roles.save')) ?>"><?= csrf_field() ?><input type="hidden" name="return" value="/roles">
    <div class="hr-grid"><label><?= e($sf->t('f_code')) ?><input name="code" required maxlength="40" pattern="[a-z][a-z0-9_]{1,39}"></label><label><?= e($sf->t('f_name')) ?><input name="name" required maxlength="120"></label><label><?= e($sf->t('f_name_ru')) ?><input name="name_ru" maxlength="120"></label><label><?= e($sf->t('f_descr')) ?><input name="descr" maxlength="1000"></label></div>
    <p><?php foreach ($catalog as $p): ?><label class="hr-perm"><input type="checkbox" name="perms[]" value="<?= e($p) ?>"> <?= e($sf->t('perm_' . str_replace('.', '_', $p))) ?></label><?php endforeach; ?></p>
    <button class="btn primary"><?= e($sf->t('add')) ?></button></form></div>
<?php foreach ($roles as $r): ?>
<div class="card"><h3><?= e($r['name']) ?> <code><?= e($r['code']) ?></code> <?php if ((int)$r['builtin']): ?><span class="hr-st info"><?= e($sf->t('builtin')) ?></span><?php endif; ?> <span class="hr-st <?= (int)$r['active'] ? 'ok' : 'bad' ?>"><?= e($sf->t((int)$r['active'] ? 'active' : 'inactive')) ?></span></h3>
  <form method="post" action="<?= e($sf->url('/do/roles.save')) ?>"><?= csrf_field() ?><input type="hidden" name="return" value="/roles"><input type="hidden" name="uuid" value="<?= e($r['uuid']) ?>">
    <div class="hr-grid"><label><?= e($sf->t('f_name')) ?><input name="name" value="<?= e($r['name']) ?>" maxlength="120" <?= (int)$r['builtin'] ? 'readonly' : '' ?>></label><label><?= e($sf->t('f_name_ru')) ?><input name="name_ru" value="<?= e($r['name_ru']) ?>" maxlength="120"></label><label><?= e($sf->t('f_descr')) ?><input name="descr" value="<?= e($r['descr']) ?>" maxlength="1000"></label></div>
    <p><?php foreach ($catalog as $p): ?><label class="hr-perm"><input type="checkbox" name="perms[]" value="<?= e($p) ?>" <?= \SysferaHr\Rbac::matches($r['perm_list'], $p) ? 'checked' : '' ?>> <?= e($sf->t('perm_' . str_replace('.', '_', $p))) ?></label><?php endforeach; ?></p>
    <button class="btn sm primary"><?= e($sf->t('save')) ?></button></form>
  <form method="post" action="<?= e($sf->url('/do/roles.active')) ?>" class="row"><?= csrf_field() ?><input type="hidden" name="return" value="/roles"><input type="hidden" name="uuid" value="<?= e($r['uuid']) ?>"><input type="hidden" name="active" value="<?= (int)$r['active'] ? '0' : '1' ?>"><button class="btn sm"><?= e($sf->t((int)$r['active'] ? 'deactivate' : 'activate')) ?></button></form>
</div>
<?php endforeach; ?>
