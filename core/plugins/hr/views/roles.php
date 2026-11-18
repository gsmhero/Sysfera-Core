<?php /** @var \Sysfera\Plugin $sf @var array $roles @var array $catalog */
include __DIR__ . '/_nav.php'; ?>
<div class="page-h"><h1><?= e($sf->t('nav_roles')) ?></h1></div>
<div class="hint"><?= e($sf->t('roles_hint')) ?></div>
<div class="card"><h3><?= e($sf->t('role_new')) ?></h3>
  <form method="post" action="<?= e($sf->url('/do/roles.save')) ?>"><?= csrf_field() ?><input type="hidden" name="return" value="/roles">
    <div class="hr-grid"><label><?= e($sf->t('f_code')) ?><input name="code" required maxlength="40" pattern="[a-z][a-z0-9_]{1,39}"></label><label><?= e($sf->t('f_name')) ?><input name="name" required maxlength="120"></label><label><?= e($sf->t('f_name_ru')) ?><input name="name_ru" maxlength="120"></label><label><?= e($sf->t('f_descr')) ?><input name="descr" maxlength="1000"></label></div>
    <p><?php foreach ($catalog as $p): ?><label class="hr-perm"><input type="checkbox" name="perms[]" value="<?= e($p) ?>"> <?= e($sf->t('perm_' . str_replace('.', '_', $p))) ?></label><?php endforeach; ?></p>
    <button class="btn primary"><?= e($sf->t('add')) ?></button></form></div>
<?php foreach ($roles as $r): $n = count($r['perm_list']); ?>
<details class="card hr-acc">
  <summary><span class="hr-acc-t"><b><?= e($r['name']) ?></b> <code><?= e($r['code']) ?></code></span>
    <span class="hr-acc-m"><?php if ((int)$r['builtin']): ?><span class="hr-st info"><?= e($sf->t('builtin')) ?></span><?php endif; ?><span class="hr-st <?= (int)$r['active'] ? 'ok' : 'bad' ?>"><?= e($sf->t((int)$r['active'] ? 'active' : 'inactive')) ?></span><span class="muted sm"><?= e($sf->t('perm_count', ['n' => in_array('*', $r['perm_list'], true) ? count($catalog) : $n])) ?></span></span></summary>
  <div class="hr-acc-b">
  <form method="post" action="<?= e($sf->url('/do/roles.save')) ?>"><?= csrf_field() ?><input type="hidden" name="return" value="/roles"><input type="hidden" name="uuid" value="<?= e($r['uuid']) ?>">
    <div class="hr-grid"><label><?= e($sf->t('f_name')) ?><input name="name" value="<?= e($r['name']) ?>" maxlength="120" <?= (int)$r['builtin'] ? 'readonly' : '' ?>></label><label><?= e($sf->t('f_name_ru')) ?><input name="name_ru" value="<?= e($r['name_ru']) ?>" maxlength="120"></label><label><?= e($sf->t('f_descr')) ?><input name="descr" value="<?= e($r['descr']) ?>" maxlength="1000"></label></div>
    <p><?php foreach ($catalog as $pm): ?><label class="hr-perm"><input type="checkbox" name="perms[]" value="<?= e($pm) ?>" <?= \SysferaHr\Rbac::matches($r['perm_list'], $pm) ? 'checked' : '' ?>> <?= e($sf->t('perm_' . str_replace('.', '_', $pm))) ?></label><?php endforeach; ?></p>
    <button class="btn sm primary"><?= e($sf->t('save')) ?></button></form>
  <form method="post" action="<?= e($sf->url('/do/roles.active')) ?>" class="row"><?= csrf_field() ?><input type="hidden" name="return" value="/roles"><input type="hidden" name="uuid" value="<?= e($r['uuid']) ?>"><input type="hidden" name="active" value="<?= (int)$r['active'] ? '0' : '1' ?>"><button class="btn sm"><?= e($sf->t((int)$r['active'] ? 'deactivate' : 'activate')) ?></button></form>
  </div>
</details>
<?php endforeach; ?>
