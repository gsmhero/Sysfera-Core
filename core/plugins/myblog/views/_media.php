<?php /** The gallery and the videos under a post's text. @var \Sysfera\Plugin $sf @var list<string> $images file names @var list<string> $videos links @var string $title */ ?>
<?php if ($images): $n = count($images); ?>
<div class="mb-gal" data-mb-gal data-prev="<?= e($sf->t('gal_prev')) ?>" data-next="<?= e($sf->t('gal_next')) ?>" data-close="<?= e($sf->t('gal_close')) ?>">
  <div class="mb-track" tabindex="0"><?php foreach ($images as $i => $name): $src = $sf->url('cover/' . $name); ?>
    <a class="mb-slide" href="<?= e($src) ?>" data-mb-zoom><img src="<?= e($src) ?>" alt="<?= e($title . ' — ' . ($i + 1) . ' / ' . $n) ?>"<?= $i ? ' loading="lazy"' : '' ?>></a><?php endforeach; ?>
  </div>
  <?php if ($n > 1): ?><button type="button" class="mb-arrow prev" data-mb-prev aria-label="<?= e($sf->t('gal_prev')) ?>">‹</button><button type="button" class="mb-arrow next" data-mb-next aria-label="<?= e($sf->t('gal_next')) ?>">›</button>
  <span class="mb-count" data-mb-count>1 / <?= $n ?></span><?php endif; ?>
</div>
<?php endif; ?>
<?php foreach ($videos as $url): $v = \SysferaBlog\Media::video($url); ?>
  <?php if ($v['type'] === 'frame'): ?><div class="mb-video"><iframe src="<?= e($v['src']) ?>" title="<?= e($v['service']) ?>" loading="lazy" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></div>
  <?php elseif ($v['type'] === 'file'): ?><div class="mb-video"><video src="<?= e($v['src']) ?>" controls preload="metadata"></video></div>
  <?php else: ?><p class="mb-vlink"><a class="btn" href="<?= e($v['src']) ?>" target="_blank" rel="noopener nofollow">▶ <?= e($sf->t('open_video', ['s' => $v['service']])) ?></a></p><?php endif; ?>
<?php endforeach; ?>
