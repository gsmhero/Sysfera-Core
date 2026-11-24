<?php /** The My listings tab of the Offers. @var \Sysfera\Plugin $sf @var \SysferaStoragerent\Ctx $ctx @var string $lang @var array $cards @var array $mineStatus */
require __DIR__ . '/_nav.php'; ?>
<h2 class="sr-h"><?= e($sf->t('offers_mine')) ?> <span class="muted">(<?= count($cards) ?>)</span></h2>
<?php if (!$cards): ?><div class="card empty"><?= e($sf->t('mine_none')) ?> <a class="btn primary" href="<?= e($sf->url('/offers/new')) ?>">+ <?= e($sf->t('offers_new')) ?></a></div>
<?php else: ?><div class="sr-cards"><?php foreach ($cards as $c): ?><div><?php $noFav = true; require __DIR__ . '/_card.php'; unset($noFav); ?><p class="sm"><span class="badge <?= e(sr_cls((string)($mineStatus[$c['id']] ?? ''))) ?>"><?= e($sf->t('os_' . ($mineStatus[$c['id']] ?? 'draft'))) ?></span> <a href="<?= e($sf->url('/offers/' . $c['id'] . '/edit')) ?>"><?= e($sf->t('edit')) ?></a></p></div><?php endforeach; ?></div><?php endif; ?>
