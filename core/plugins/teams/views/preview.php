<?php /** @var \Sysfera\Plugin $sf @var array $t @var int $n */ $tab = 'preview'; $chans = []; require __DIR__ . '/_nav.php'; ?>
<div class="card empty"><h3><?= e($sf->t('open_team')) ?> · <?= e($sf->t('n_members', ['n' => $n])) ?></h3><p class="muted"><?= e($sf->t('join_hint')) ?></p>
  <form method="post" action="<?= e($sf->url('/team/' . (int)$t['id'] . '/join')) ?>"><?= csrf_field() ?><button class="btn primary"><?= e($sf->t('join')) ?></button></form></div>
