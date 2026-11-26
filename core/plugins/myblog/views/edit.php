<?php /** @var \Sysfera\Plugin $sf @var ?array $p @var ?array $form @var array $products @var ?array $shop @var bool $synced the last Basebase synchronisation worked */
$v = fn(string $k, string $d = ''): string => is_string($form[$k] ?? null) ? $form[$k] : (string)($p[$k] ?? $d);
$sel = \SysferaBlog\Text::ids($form !== null ? ($form['products'] ?? []) : ($p['products'] ?? []));
$cover = $p ? (!empty($p['cover']) ? $sf->url('cover/' . $p['cover']) : (string)($p['cover_url'] ?? '')) : ''; ?>
<div class="page-h"><h1><?= e($sf->t($p ? 'edit_post' : 'new_post')) ?></h1><a class="btn" href="<?= e($sf->url('?f=mine')) ?>"><?= e($sf->t('back')) ?></a></div>
<form method="post" action="<?= e($sf->url('save')) ?>" enctype="multipart/form-data" class="mb-edit"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)($p['id'] ?? 0) ?>">
  <section class="card">
    <label class="fld"><span><?= e($sf->t('f_title')) ?></span><input name="title" value="<?= e($v('title')) ?>" required maxlength="255"></label>
    <label class="fld"><span><?= e($sf->t('f_body')) ?></span><textarea name="body" rows="14" maxlength="20000" placeholder="<?= e($sf->t('f_body_ph')) ?>"><?= e($v('body')) ?></textarea></label>
    <label class="fld"><span><?= e($sf->t('f_cover')) ?></span><input type="file" name="cover" accept="image/jpeg,image/png,image/webp,image/gif"></label>
    <?php if ($cover !== ''): ?><img class="mb-thumb" src="<?= e($cover) ?>" alt=""><label class="chk"><input type="checkbox" name="remove_cover" value="1"> <?= e($sf->t('remove_cover')) ?></label><?php endif; ?>
  </section>
  <div>
    <section class="card"><h3><?= e($sf->t('publishing')) ?></h3>
      <label class="fld"><span><?= e($sf->t('f_status')) ?></span><select name="status"><option value="published" <?= $v('status', 'published') === 'published' ? 'selected' : '' ?>><?= e($sf->t('published')) ?></option><option value="draft" <?= $v('status') === 'draft' ? 'selected' : '' ?>><?= e($sf->t('draft_only')) ?></option></select></label>
      <label class="fld"><span><?= e($sf->t('f_scope')) ?></span><select name="scope"><option value="global" <?= $v('scope', 'global') === 'global' ? 'selected' : '' ?>><?= e($sf->t('scope_global')) ?></option><option value="local" <?= $v('scope') === 'local' ? 'selected' : '' ?>><?= e($sf->t('scope_local')) ?></option></select></label>
      <?php if ($shop && $synced): ?><p class="muted sm"><?= e($sf->t('goes_to_basebase')) ?></p><?php endif; ?>
      <button class="btn primary"><?= e($sf->t($p ? 'save' : 'publish')) ?></button></section>
    <section class="card"><h3><?= e($sf->t('attach')) ?></h3>
      <?php foreach (array_diff($sel, array_map(fn($x) => (int)$x['bb_id'], $products)) as $i): ?><input type="hidden" name="products[]" value="<?= (int)$i ?>"><?php endforeach; /* attached on Basebase, not in this site's list: kept */ ?>
      <?php if (!$products): ?><p class="muted sm"><?= e($sf->t($shop ? 'no_products' : 'no_shop_products')) ?></p>
      <?php else: ?><p class="muted sm"><?= e($sf->t('attach_hint')) ?></p><div class="mb-pick"><?php foreach ($products as $x) echo \Sysfera\View::file(__DIR__ . '/_product.php', ['sf' => $sf, 'x' => $x, 'pick' => in_array((int)$x['bb_id'], $sel, true)]); ?></div><?php endif; ?>
    </section>
  </div>
</form>
