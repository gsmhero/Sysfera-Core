<?php /** @var \Sysfera\Plugin $sf @var string $msg */ ?>
<div class="page-h"><h1><?= e($sf->t('title')) ?></h1></div>
<section class="card empty"><h3><?= e($sf->t('n_' . $msg)) ?></h3><p class="muted"><?= e($sf->t('n_' . $msg . '_hint')) ?></p>
<?php if ($msg === 'not_connected' && $sf->can('messenger.admin')): ?><a class="btn primary" href="<?= e($sf->url('/settings')) ?>"><?= e($sf->t('settings')) ?></a><?php endif; ?></section>
