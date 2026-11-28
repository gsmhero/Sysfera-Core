<?php /** @var \Sysfera\Plugin $sf @var ?array $p @var ?array $form @var array $products @var ?array $shop @var bool $synced the last Basebase synchronisation worked */
$v = fn(string $k, string $d = ''): string => is_string($form[$k] ?? null) ? $form[$k] : (string)($p[$k] ?? $d);
$sel = \SysferaBlog\Text::ids($form !== null ? ($form['products'] ?? []) : ($p['products'] ?? []));
$cover = $p ? (!empty($p['cover']) ? $sf->url('cover/' . $p['cover']) : (string)($p['cover_url'] ?? '')) : '';
$gallery = \SysferaBlog\Media::images($p['images'] ?? null);
$videos = is_string($form['videos'] ?? null) ? $form['videos'] : implode("\n", \SysferaBlog\Media::videos($p['videos'] ?? null));
// what the server accepts in one request, checked in the browser so a too large upload does not lose the text
$bytes = function (string $k): int { $v = trim((string)ini_get($k)); return (int)$v * (['g' => 1 << 30, 'm' => 1 << 20, 'k' => 1 << 10][strtolower(substr($v, -1))] ?? 1); }; ?>
<div class="page-h"><h1><?= e($sf->t($p ? 'edit_post' : 'new_post')) ?></h1><a class="btn" href="<?= e($sf->url('?f=mine')) ?>"><?= e($sf->t('back')) ?></a></div>
<form method="post" action="<?= e($sf->url('save')) ?>" enctype="multipart/form-data" class="mb-edit" data-mb-upload data-total="<?= $bytes('post_max_size') ?>" data-file="<?= min(\SysferaBlog\Text::MAX_COVER, $bytes('upload_max_filesize') ?: \SysferaBlog\Text::MAX_COVER) ?>" data-count="<?= (int)ini_get('max_file_uploads') ?>" data-big="<?= e($sf->t('e_upload_big')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)($p['id'] ?? 0) ?>">
  <section class="card">
    <label class="fld"><span><?= e($sf->t('f_title')) ?></span><input name="title" value="<?= e($v('title')) ?>" required maxlength="255"></label>
    <label class="fld"><span><?= e($sf->t('f_body')) ?></span><textarea name="body" rows="14" maxlength="20000" placeholder="<?= e($sf->t('f_body_ph')) ?>"><?= e($v('body')) ?></textarea></label>
    <label class="fld"><span><?= e($sf->t('f_cover')) ?></span><input type="file" name="cover" accept="image/jpeg,image/png,image/webp,image/gif"></label>
    <?php if ($cover !== ''): ?><img class="mb-thumb" src="<?= e($cover) ?>" alt=""><label class="chk"><input type="checkbox" name="remove_cover" value="1"> <?= e($sf->t('remove_cover')) ?></label><?php endif; ?>
    <label class="fld"><span><?= e($sf->t('f_gallery')) ?></span><input type="file" name="gallery[]" multiple accept="image/jpeg,image/png,image/webp,image/gif"></label>
    <p class="muted sm"><?= e($sf->t('gallery_hint', ['n' => \SysferaBlog\Media::MAX_IMAGES])) ?></p>
    <?php if ($gallery): ?><div class="mb-thumbs"><?php foreach ($gallery as $g): ?><label><img src="<?= e($sf->url('cover/' . $g)) ?>" alt=""><span><input type="checkbox" name="remove_images[]" value="<?= e($g) ?>"> <?= e($sf->t('remove_image')) ?></span></label><?php endforeach; ?></div><?php endif; ?>
    <label class="fld"><span><?= e($sf->t('f_videos')) ?></span><textarea name="videos" rows="3" placeholder="https://youtu.be/…&#10;https://vkvideo.ru/video-1_2"><?= e($videos) ?></textarea></label>
    <p class="muted sm"><?= e($sf->t('videos_hint', ['n' => \SysferaBlog\Media::MAX_VIDEOS])) ?></p>
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
