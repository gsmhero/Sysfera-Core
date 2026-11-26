<?php /** @var \Sysfera\Plugin $sf @var string $msg language key */ ?>
<div class="card empty"><p><?= e($sf->t($msg)) ?></p><a class="btn" href="<?= e(url('/')) ?>"><?= e($sf->t('home')) ?></a></div>
