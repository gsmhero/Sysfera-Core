<?php /** Public page of one post. @var \Sysfera\Plugin $sf @var array $p @var array $card @var array $attached @var bool $mine */ ?>
<article class="mb-article">
  <h1><?= e($p['title']) ?></h1>
  <p class="muted sm"><?= e($sf->t('author')) ?>: <a href="<?= e($card['author_url']) ?>"><?= e($card['author']) ?></a> · <?= e($card['date']) ?>
    <?php if ($p['status'] === 'draft'): ?><span class="badge warn"><?= e($sf->t('draft_only')) ?></span><?php endif; ?></p>
  <?php if ($mine): ?><p><a class="btn sm" href="<?= e($sf->url('edit?id=' . $p['id'])) ?>"><?= e($sf->t('edit')) ?></a></p><?php endif; ?>
  <?php if ($card['cover'] !== ''): ?><img class="mb-hero" src="<?= e($card['cover']) ?>" alt="" referrerpolicy="no-referrer"><?php endif; ?>
  <div class="mb-text"><?= nl2br(e($p['body'])) ?></div>
  <?php if (!empty($p['bb_url'])): ?><p><a class="sm" href="<?= e($p['bb_url']) ?>" target="_blank" rel="noopener"><?= e($sf->t('post_on_basebase')) ?></a></p><?php endif; ?>
</article>
<?php if ($attached): ?>
<section class="mb-article"><h2><?= e($sf->t('products')) ?></h2>
  <div class="mb-shelf"><?php foreach ($attached as $x): ?>
    <a class="mb-item" href="<?= e($x['url']) ?>" target="_blank" rel="noopener"><?php if (($x['image'] ?? '') !== ''): ?><img src="<?= e($x['image']) ?>" alt="" loading="lazy" referrerpolicy="no-referrer"><?php endif; ?>
      <b><?= e($x['title']) ?></b><span class="muted sm"><?= e($x['category'] ?? '') ?></span><span class="mb-price"><?= e(number_format((float)$x['price'], 0, '.', ' ')) ?> ₽</span></a>
  <?php endforeach; ?></div>
  <?php if ($mine): ?><div class="card mb-pick wide"><p class="muted sm"><?= e($sf->t('embed_hint')) ?></p><?php foreach ($attached as $x) echo \Sysfera\View::file(__DIR__ . '/_product.php', ['sf' => $sf, 'x' => $x, 'pick' => null]); ?></div><?php endif; ?>
</section>
<?php endif; ?>
