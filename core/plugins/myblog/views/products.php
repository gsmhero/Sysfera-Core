<?php /** @var \Sysfera\Plugin $sf @var array $products @var ?array $shop */ ?>
<div class="page-h"><h1><?= e($sf->t('my_products')) ?></h1><a class="btn" href="<?= e($sf->url('/')) ?>"><?= e($sf->t('back')) ?></a></div>
<?php if (!$products): ?><div class="card empty"><p><?= e($sf->t($shop ? 'no_products' : 'no_shop_products')) ?></p></div>
<?php else: ?><p class="muted"><?= e($sf->t('embed_hint')) ?></p>
<div class="card mb-pick wide"><?php foreach ($products as $x) echo \Sysfera\View::file(__DIR__ . '/_product.php', ['sf' => $sf, 'x' => $x, 'pick' => null]); ?></div><?php endif; ?>
