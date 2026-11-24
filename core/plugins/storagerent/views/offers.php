<?php /** @var \Sysfera\Plugin $sf @var \SysferaStoragerent\Ctx $ctx @var string $lang @var array $cards @var array $mine @var array $mineStatus @var string $q @var bool $signedIn @var bool $key */
require __DIR__ . '/_nav.php'; ?>
<form class="sr-filter" method="get" action="<?= e($sf->url('/offers')) ?>"><input type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e($sf->t('offers_search_ph')) ?>"><button class="btn"><?= e($sf->t('search')) ?></button></form>
<p class="sr-count muted"><?= e($sf->t('offers_found', ['n' => count($cards)])) ?><?= $q !== '' ? ' · ' . e($q) : '' ?></p>
<?php if (!$cards): ?><div class="card empty"><?= e($sf->t('offers_none')) ?></div><?php else: ?><div class="sr-cards"><?php foreach ($cards as $c): require __DIR__ . '/_card.php'; endforeach; ?></div><?php endif; ?>
<?php if (!$signedIn): ?><div class="card"><?= e($sf->t('offers_guest_cta')) ?> <a class="btn primary" href="<?= e(url('/register')) ?>"><?= e($sf->t('register')) ?></a> <a class="btn" href="<?= e(url('/login')) ?>"><?= e($sf->t('login')) ?></a></div><?php endif; ?>
