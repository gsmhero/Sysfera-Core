<?php /** @var \Sysfera\Plugin $sf @var string $tab @var array $doc */
include __DIR__ . '/_nav.php'; ?>
<div class="page-h"><h1>🔒 <?= e($sf->t('private_h')) ?></h1><a class="btn" href="<?= e($sf->url('/docs')) ?>">← <?= e($sf->t('nav_docs')) ?></a></div>
<div class="card empty"><p><?= e($sf->t('private_p')) ?></p></div>