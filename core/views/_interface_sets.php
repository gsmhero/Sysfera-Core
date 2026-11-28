<?php /** Profile: the user's interface sets. @var array $sets @var ?array $edit @var array $components */
$act = e(url('/interface')); ?>
<h2 id="interface-sets"><?= e(t('ui.h')) ?></h2>
<div class="card">
  <p class="muted sm"><?= e(t('ui.hint')) ?></p>
  <div class="ui-sets">
    <form class="ui-add" method="post" action="<?= $act ?>"><?= csrf_field() ?><input type="hidden" name="do" value="create">
      <label class="fld"><span><?= e(t('ui.name')) ?></span><input name="name" required maxlength="<?= \Sysfera\InterfaceSets::MAX_NAME ?>"></label>
      <button class="btn primary"><?= e(t('ui.add')) ?></button></form>
    <?php foreach (array_reverse($sets) as $s): ?>
      <?php if ($edit && $edit['id'] === $s['id']): ?>
      <form class="ui-edit" method="post" action="<?= $act ?>"><?= csrf_field() ?><input type="hidden" name="do" value="save"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
        <label class="fld"><span><?= e(t('ui.name')) ?></span><input name="name" value="<?= e($s['name']) ?>" required maxlength="<?= \Sysfera\InterfaceSets::MAX_NAME ?>"></label>
        <p><b><?= e(t('ui.show')) ?></b></p>
        <?php if (!$components): ?><p class="muted sm"><?= e(t('ui.none')) ?></p><?php endif; ?>
        <div class="ui-checks"><?php foreach ($components as $slug => $name): ?><label class="chk"><input type="checkbox" name="items[]" value="<?= e($slug) ?>"<?= in_array($slug, $s['items'], true) ? ' checked' : '' ?>> <?= e($name) ?></label><?php endforeach; ?></div>
        <p class="muted sm"><?= e(t('ui.always')) ?></p>
        <div class="row"><button class="btn primary"><?= e(t('common.save')) ?></button><a class="btn" href="<?= e(url('/profile')) ?>#interface-sets"><?= e(t('ui.cancel')) ?></a></div></form>
      <?php else: ?>
      <div class="ui-row"><b><?= e($s['name']) ?><?php if ($s['active']): ?> <span class="badge ok"><?= e(t('ui.in_use')) ?></span><?php endif; ?></b>
        <a class="btn primary" href="<?= e(url('/profile')) ?>?set=<?= (int)$s['id'] ?>#interface-sets"><?= e(t('ui.edit')) ?></a>
        <form method="post" action="<?= $act ?>" class="inline" data-confirm="<?= e(t('ui.delete_confirm')) ?>"><?= csrf_field() ?><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn danger"><?= e(t('ui.delete')) ?></button></form></div>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
</div>
