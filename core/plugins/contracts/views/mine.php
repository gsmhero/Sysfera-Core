<?php /** @var \Sysfera\Plugin $sf @var array $rows @var string $sub */
use SysferaContracts\Svc;
include __DIR__ . '/_nav.php'; $archive = $sub === 'archive'; ?>
<div class="page-h"><h1><?= e($sf->t('nav_mine')) ?></h1></div>
<nav class="ct-tabs"><?php foreach (['created', 'working', 'offers', 'archive', 'watch'] as $s): ?><a class="<?= $sub === $s ? 'on' : '' ?>" href="<?= e($sf->url('/mine?tab=' . $s)) ?>"><?= e($sf->t('mine_' . $s)) ?></a><?php endforeach; ?></nav>
<?php if (!$rows): ?><div class="card empty"><p><?= e($sf->t('none_here')) ?></p></div><?php endif; ?>
<?php foreach ($rows as $k) include __DIR__ . '/_card.php'; ?>
