<?php /** @var \Sysfera\Plugin $sf @var array $items @var int $me  (own posts get state badges and buttons) @var bool $admin may change every post of this site @var string $host this site's domain */
$bare = fn(string $d): string => (string)preg_replace('/^www\./', '', strtolower($d)); ?>
<div class="mb-grid">
<?php foreach ($items as $c): $here = ($c['domain'] ?? '') === '' || (!empty($host) && $bare($c['domain']) === $bare($host));
  $own = !empty($me) && $here && (!empty($admin) || (int)$c['uid'] === (int)$me); ?>
  <article class="mb-card">
    <a class="mb-cover" href="<?= e($c['url']) ?>" tabindex="-1" aria-hidden="true"><?php if ($c['cover'] !== ''): ?><img src="<?= e($c['cover']) ?>" alt="" loading="lazy" referrerpolicy="no-referrer"><?php else: ?><span><?= e(\SysferaBlog\Text::cut($c['title'], 1)) ?></span><?php endif; ?></a>
    <div class="mb-body">
      <h3><a href="<?= e($c['url']) ?>"><?= e($c['title']) ?></a></h3>
      <p><?= e($c['excerpt']) ?></p>
      <div class="mb-meta"><span><?php if ($c['author'] !== ''): ?><a href="<?= e($c['author_url']) ?>"><?= e($c['author']) ?></a> · <?php endif; ?><?= e($c['date']) ?><?php if (($c['domain'] ?? '') !== ''): ?> · <?= e($c['domain']) ?><?php endif; ?></span>
        <?php if ($c['products'] > 0): ?><span class="mb-n" title="<?= e($sf->t('products')) ?>"><?= e($sf->t('n_products', ['n' => $c['products']])) ?></span><?php endif; ?></div>
      <?php if ($own): ?><div class="mb-own">
        <?php if (($c['status'] ?? '') === 'draft'): ?><span class="badge warn"><?= e($sf->t('draft')) ?></span><?php elseif (($c['scope'] ?? '') === 'local'): ?><span class="badge info"><?= e($sf->t('scope_local_badge')) ?></span><?php else: ?><span class="badge ok"><?= e($sf->t('published')) ?></span><?php endif; ?>
        <a class="btn sm" href="<?= e($sf->url('edit?id=' . $c['id'])) ?>"><?= e($sf->t('edit')) ?></a>
        <form method="post" action="<?= e($sf->url('delete')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn sm danger" data-confirm="<?= e($sf->t('delete_confirm')) ?>"><?= e($sf->t('delete')) ?></button></form>
      </div><?php endif; ?>
    </div>
  </article>
<?php endforeach; ?>
</div>
