<?php /** Posts (and search results) on the public start page. @var \Sysfera\Plugin $sf @var array $d posts page @var string $q @var string $scope @var bool $offline @var array $people @var array $products */
$to = fn(int $n): string => url('/') . '?' . http_build_query(array_filter(['scope' => $scope, 'q' => $q, 'page' => $n], fn($v) => $v !== '')); ?>
<?php if ($people): ?><h2 class="mb-found"><?= e($sf->t('s_people')) ?></h2>
<div class="mb-people"><?php foreach ($people as $x): ?><a class="mb-item" href="<?= e($sf->url('u/' . $x['uid'])) ?>"><b><?= e($x['name']) ?></b><span class="muted sm"><?= e(implode(' · ', array_filter([$x['products'] ? $sf->t('n_products', ['n' => $x['products']]) : '', $x['posts'] ? $sf->t('n_posts', ['n' => $x['posts']]) : '']))) ?></span></a><?php endforeach; ?></div><?php endif; ?>
<?php if ($products): ?><h2 class="mb-found"><?= e($sf->t('products')) ?></h2>
<div class="mb-shelf"><?php foreach ($products as $x): ?><div class="mb-tile"><a class="mb-item" href="<?= e($x['url']) ?>" target="_blank" rel="noopener"><?php if ($x['image'] !== ''): ?><img src="<?= e($x['image']) ?>" alt="" loading="lazy" referrerpolicy="no-referrer"><?php endif; ?>
  <b><?= e($x['title']) ?></b><span class="muted sm"><?= e($x['category']) ?></span><span class="mb-price"><?= e(number_format((float)$x['price'], 0, '.', ' ')) ?> ₽</span></a>
  <a class="btn sm" href="<?= e($sf->url('u/' . (int)$x['owner_id']) . '?tab=products') ?>"><?= e($sf->t('seller')) ?></a></div><?php endforeach; ?></div><?php endif; ?>
<?php if ($q !== '' && ($people || $products)): ?><h2 class="mb-found"><?= e($sf->t('tab_posts')) ?></h2><?php endif; ?>
<?php if ($offline): ?><p class="muted sm"><?= e($sf->t('offline_public')) ?></p><?php endif; ?>
<?php if ($d['items']): echo \Sysfera\View::file(__DIR__ . '/_cards.php', ['sf' => $sf, 'items' => $d['items'], 'me' => 0]);
else: ?><div class="card empty"><p><?= e($sf->t($q === '' ? 'none' : 'none_found')) ?></p></div><?php endif; ?>
<?php if ($d['pages'] > 1): ?><nav class="mb-pager"><?php if ($d['page'] > 1): ?><a class="btn sm" href="<?= e($to($d['page'] - 1)) ?>"><?= e($sf->t('newer')) ?></a><?php endif; ?><span class="muted sm"><?= (int)$d['page'] ?> / <?= (int)$d['pages'] ?></span><?php if ($d['page'] < $d['pages']): ?><a class="btn sm" href="<?= e($to($d['page'] + 1)) ?>"><?= e($sf->t('older')) ?></a><?php endif; ?></nav><?php endif; ?>
