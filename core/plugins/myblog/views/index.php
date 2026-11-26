<?php /** @var \Sysfera\Plugin $sf @var array $d @var string $f @var string $q @var bool $offline @var int $me @var ?array $shop @var string $state @var bool $hubMod */ ?>
<div class="page-h"><h1><?= e($sf->t('title')) ?></h1>
  <div class="row"><a class="btn" href="<?= e($sf->url('products')) ?>"><?= e($sf->t('my_products')) ?></a><a class="btn primary" href="<?= e($sf->url('edit')) ?>">+ <?= e($sf->t('new_post')) ?></a></div></div>

<?php if ($shop): ?>
<div class="card mb-shop"><span><?= e($sf->t('shop_linked', ['name' => (string)($shop['shop_name'] ?? '')])) ?><?php if ($state === 'ok'): ?> <?= e($sf->t('shop_synced')) ?><?php endif; ?>
  <?php if ($state === 'unsupported'): ?><span class="badge warn"><?= e($sf->t('sync_unsupported_badge')) ?></span><?php elseif ($state === 'error'): ?><span class="badge bad"><?= e($sf->t('sync_error_badge')) ?></span><?php endif; ?></span>
  <form method="post" action="<?= e($sf->url('sync')) ?>"><?= csrf_field() ?><button class="btn sm"><?= e($sf->t('sync_now')) ?></button></form></div>
<?php endif; ?>

<div class="mb-bar">
  <nav class="mb-tabs"><?php foreach (['all', 'site', 'mine'] as $k): ?><a class="<?= $f === $k ? 'on' : '' ?>" href="<?= e($sf->url('?f=' . $k)) ?>"><?= e($sf->t('f_' . $k)) ?></a><?php endforeach; ?></nav>
  <form method="get" action="<?= e($sf->url('/')) ?>" class="mb-search" role="search"><input type="hidden" name="f" value="<?= e($f) ?>"><input type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e($sf->t('search')) ?>" aria-label="<?= e($sf->t('search')) ?>"><button class="btn sm"><?= e($sf->t('find')) ?></button></form>
</div>
<?php if ($offline): ?><div class="flash warn"><?= e($sf->t('offline')) ?></div><?php endif; ?>

<?php if (!$d['items']): ?><div class="card empty"><p><?= e($sf->t($q !== '' ? 'none_found' : ($f === 'mine' ? 'none_mine' : 'none'))) ?></p></div>
<?php else: echo \Sysfera\View::file(__DIR__ . '/_cards.php', ['sf' => $sf, 'items' => $d['items'], 'me' => $me]); endif; ?>

<?php if ($d['pages'] > 1): ?><nav class="mb-pager"><?php if ($d['page'] > 1): ?><a class="btn sm" href="<?= e($sf->url('?f=' . $f . '&q=' . rawurlencode($q) . '&page=' . ($d['page'] - 1))) ?>">← <?= e($sf->t('newer')) ?></a><?php endif; ?>
  <span class="muted sm"><?= (int)$d['page'] ?> / <?= (int)$d['pages'] ?></span>
  <?php if ($d['page'] < $d['pages']): ?><a class="btn sm" href="<?= e($sf->url('?f=' . $f . '&q=' . rawurlencode($q) . '&page=' . ($d['page'] + 1))) ?>"><?= e($sf->t('older')) ?> →</a><?php endif; ?></nav><?php endif; ?>

<?php if ($hubMod): ?><p><a class="sm" href="<?= e($sf->url('moderate')) ?>"><?= e($sf->t('moderate')) ?></a></p><?php endif; ?>
