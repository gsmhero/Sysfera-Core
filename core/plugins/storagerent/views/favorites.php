<?php /** The Favorites tab of the Offers. @var \Sysfera\Plugin $sf @var \SysferaStoragerent\Ctx $ctx @var string $lang @var array $items [['ref', 'card', 'gone']] @var array $favs */
require __DIR__ . '/_nav.php'; ?>
<h2 class="sr-h"><?= e($sf->t('offers_favorites')) ?> <span class="muted">(<?= count($items) ?>)</span></h2>
<?php if (!$items): ?><div class="card empty"><?= e($sf->t('fav_none')) ?> <a class="btn" href="<?= e($sf->url('/offers')) ?>"><?= e($sf->t('offers_list')) ?></a></div>
<?php else: ?><div class="sr-cards"><?php foreach ($items as $it): $c = $it['card'];
  if ($it['gone']): ?><article class="sr-card sr-gone"><div class="sr-card-body"><h3><?= e($c['name']) ?></h3><p class="muted sm"><?= e($sf->t('fav_gone')) ?></p><div class="sr-card-act"><?= sr_fav($sf, $it['ref'], true, $sf->url('/offers/favorites')) ?></div></div></article>
  <?php else: require __DIR__ . '/_card.php'; endif; endforeach; ?></div><?php endif; ?>