<?php /** One of the user's Basebase products with its embed code. @var \Sysfera\Plugin $sf @var array $x @var ?bool $pick checkbox (null = none) */
$code = \SysferaBlog\Text::embedCode((string)$x['url']); ?>
<div class="mb-prod">
  <label>
    <?php if ($pick !== null): ?><input type="checkbox" name="products[]" value="<?= (int)$x['bb_id'] ?>" <?= $pick ? 'checked' : '' ?>><?php endif; ?>
    <?php if (($x['image'] ?? '') !== ''): ?><img src="<?= e($x['image']) ?>" alt="" loading="lazy" referrerpolicy="no-referrer"><?php endif; ?>
    <span><b><?= e($x['title']) ?></b><small class="muted"><?= e(number_format((float)$x['price'], 0, '.', ' ')) ?> ₽<?php if (($x['category'] ?? '') !== ''): ?> · <?= e($x['category']) ?><?php endif; ?></small></span>
  </label>
  <span class="mb-acts"><a class="btn sm" href="<?= e($x['url']) ?>" target="_blank" rel="noopener"><?= e($sf->t('on_basebase')) ?></a>
  <?php if ($code !== ''): ?><button type="button" class="btn sm" data-mb-copy="<?= e($code) ?>" data-mb-done="<?= e($sf->t('copied')) ?>"><?= e($sf->t('copy_embed')) ?></button><?php endif; ?></span>
</div>
