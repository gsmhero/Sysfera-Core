<?php
/** An author's public profile, laid out like a shop page on Basebase: header, tabs Posts / Products / Sets, filter sidebar on Products.
 * @var \Sysfera\Plugin $sf @var int $uid @var string $name @var string $shopName @var string $about @var string $tab @var int $postCount @var int $productCount
 * @var array $d posts page @var array $items filtered products on this page @var array $f filter @var array $cats @var int $page @var int $pages @var ?array $sets @var bool $own */
$here = fn(array $q = []): string => $sf->url('u/' . $uid) . ($q ? '?' . http_build_query($q) : '');
$display = $shopName !== '' ? $shopName : $name;
$keep = array_filter(['tab' => 'products', 'q' => $f['q'], 'min' => $f['min'], 'max' => $f['max'], 'cat' => $f['cat']], fn($v) => $v !== ''); ?>
<section class="mb-who">
  <span class="mb-av" aria-hidden="true"><?= e(function_exists('mb_strtoupper') ? mb_strtoupper(\SysferaBlog\Text::cut($display, 1)) : strtoupper(\SysferaBlog\Text::cut($display, 1))) ?></span>
  <div class="mb-who-t">
    <h1><?= e($display) ?></h1>
    <p class="mb-chips"><?php if ($shopName !== '' && $shopName !== $name): ?><span><?= e($name) ?></span><?php endif; ?><span><?= e($sf->t('n_products', ['n' => $productCount])) ?></span><a href="<?= e($here()) ?>"><?= e($sf->t('n_posts', ['n' => $postCount])) ?></a></p>
    <?php if ($about !== ''): ?><p class="mb-about"><?= nl2br(e($about)) ?></p><?php endif; ?>
  </div>
  <?php if ($own): ?><a class="btn" href="<?= e(url('/profile')) ?>"><?= e($sf->t('edit_profile')) ?></a><?php endif; ?>
</section>

<nav class="mb-tabs mb-ptabs"><?php foreach (\SysferaBlog\Profile::TABS as $k): ?><a class="<?= $tab === $k ? 'on' : '' ?>" href="<?= e($here($k === 'posts' ? [] : ['tab' => $k])) ?>"><?= e($sf->t('tab_' . $k)) ?></a><?php endforeach; ?></nav>

<?php if ($tab === 'posts'): ?>
  <?php if (!$d['items']): ?><div class="card empty"><p><?= e($sf->t('none')) ?></p></div>
  <?php else: echo \Sysfera\View::file(__DIR__ . '/_cards.php', ['sf' => $sf, 'items' => $d['items'], 'me' => 0]); endif; ?>
<?php elseif ($tab === 'products'): ?>
<div class="mb-cols">
  <aside>
    <form class="card" method="get" action="<?= e($here()) ?>"><input type="hidden" name="tab" value="products"><?php if ($f['cat'] !== ''): ?><input type="hidden" name="cat" value="<?= e($f['cat']) ?>"><?php endif; ?>
      <label class="fld"><span><?= e($sf->t('flt_search')) ?></span><input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="<?= e($sf->t('flt_search_ph')) ?>"></label>
      <label class="fld"><span><?= e($sf->t('flt_price')) ?></span><input name="min" inputmode="decimal" value="<?= e($f['min']) ?>" placeholder="<?= e($sf->t('flt_from')) ?>" aria-label="<?= e($sf->t('flt_price') . ', ' . $sf->t('flt_from')) ?>"></label>
      <label class="fld"><input name="max" inputmode="decimal" value="<?= e($f['max']) ?>" placeholder="<?= e($sf->t('flt_to')) ?>" aria-label="<?= e($sf->t('flt_price') . ', ' . $sf->t('flt_to')) ?>"></label>
      <button class="btn primary mb-wide"><?= e($sf->t('apply')) ?></button></form>
    <?php if ($cats): ?><nav class="card mb-cats"><small class="muted"><?= e($sf->t('categories')) ?></small>
      <a class="<?= $f['cat'] === '' ? 'on' : '' ?>" href="<?= e($here(array_diff_key($keep, ['cat' => 1]))) ?>"><?= e($sf->t('all')) ?></a>
      <?php foreach ($cats as $c => $n): ?><a class="<?= $f['cat'] === (string)$c ? 'on' : '' ?>" href="<?= e($here(['cat' => (string)$c] + $keep)) ?>"><?= e($c) ?> <span class="muted"><?= (int)$n ?></span></a><?php endforeach; ?></nav><?php endif; ?>
  </aside>
  <div>
    <?php if (!$items): ?><div class="card empty"><p><?= e($sf->t($productCount ? 'none_found' : 'no_products_public')) ?></p></div>
    <?php else: ?><div class="mb-shelf"><?php foreach ($items as $x): $code = $own ? \SysferaBlog\Text::embedCode((string)$x['url']) : ''; ?>
      <div class="mb-tile"><a class="mb-item" href="<?= e($x['url']) ?>" target="_blank" rel="noopener"><?php if ($x['image'] !== ''): ?><img src="<?= e($x['image']) ?>" alt="" loading="lazy" referrerpolicy="no-referrer"><?php endif; ?>
        <b><?= e($x['title']) ?></b><span class="muted sm"><?= e($x['category']) ?></span><span class="mb-price"><?= e(number_format((float)$x['price'], 0, '.', ' ')) ?> ₽</span></a>
        <?php if ($code !== ''): ?><button type="button" class="btn sm" data-mb-copy="<?= e($code) ?>" data-mb-done="<?= e($sf->t('copied')) ?>"><?= e($sf->t('copy_embed')) ?></button><?php endif; ?></div>
    <?php endforeach; ?></div><?php endif; ?>
  </div>
</div>
<?php else: ?>
  <?php if (!$sets): ?><div class="card empty"><p><?= e($sf->t('no_sets')) ?></p></div>
  <?php else: ?><div class="mb-shelf"><?php foreach ($sets as $s): ?><a class="mb-item" href="<?= e($s['url']) ?>" target="_blank" rel="noopener"><span class="badge info"><?= e($sf->t('n_items', ['n' => $s['items']])) ?></span><b><?= e($s['title']) ?></b><span class="mb-price"><?= e(number_format((float)$s['sum'], 0, '.', ' ')) ?> ₽</span></a><?php endforeach; ?></div><?php endif; ?>
<?php endif; ?>

<?php if ($pages > 1): $q = $tab === 'products' ? $keep : []; ?><nav class="mb-pager"><?php if ($page > 1): ?><a class="btn sm" href="<?= e($here($q + ['page' => $page - 1])) ?>">←</a><?php endif; ?><span class="muted sm"><?= (int)$page ?> / <?= (int)$pages ?></span><?php if ($page < $pages): ?><a class="btn sm" href="<?= e($here($q + ['page' => $page + 1])) ?>">→</a><?php endif; ?></nav><?php endif; ?>
