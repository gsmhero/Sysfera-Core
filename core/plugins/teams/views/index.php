<?php /** @var \Sysfera\Plugin $sf @var list<array> $mine @var list<array> $open @var bool $canCreate */ ?>
<link rel="stylesheet" href="<?= e($sf->asset('teams.css')) ?>?v=<?= e($sf->manifest['version']) ?>">
<div class="page-h"><h1><?= e($sf->t('title')) ?></h1><?php if ($canCreate): ?><button class="btn primary" type="button" data-toggle="#newteam">+ <?= e($sf->t('new_team')) ?></button><?php endif; ?></div>
<?php if ($canCreate): ?><form id="newteam" class="card" method="post" action="<?= e($sf->url('/team/save')) ?>" hidden><?= csrf_field() ?>
  <div class="cols"><label class="fld"><span><?= e($sf->t('f_name')) ?> *</span><input name="name" required maxlength="120"></label>
    <label class="fld"><span><?= e($sf->t('f_privacy')) ?></span><select name="privacy"><option value="private"><?= e($sf->t('private')) ?></option><option value="open"><?= e($sf->t('open_team')) ?></option></select></label>
    <label class="fld"><span><?= e($sf->t('f_color')) ?></span><input type="color" name="color" value="#4f46e5"></label></div>
  <label class="fld"><span><?= e($sf->t('f_descr')) ?></span><input name="descr" maxlength="500"></label>
  <button class="btn primary"><?= e($sf->t('create')) ?></button></form><?php endif; ?>

<h2><?= e($sf->t('my_teams')) ?></h2>
<div class="tm-grid"><?php foreach ($mine as $x): ?><a class="card tm-card" href="<?= e($sf->url('/t/' . (int)$x['id'])) ?>" style="--c:<?= e($x['color']) ?>">
  <b><?= e($x['name']) ?> <?= $x['privacy'] === 'private' ? '🔒' : '' ?></b><span class="muted sm"><?= e($x['descr'] ?: '') ?></span>
  <span class="tm-card-f"><small class="muted"><?= e($sf->t('n_members', ['n' => (int)$x['n']])) ?> · <?= e($sf->t('role_' . $x['role'])) ?></small><?php if ($x['unread']): ?><i class="tm-badge"><?= (int)$x['unread'] ?></i><?php endif; ?></span></a><?php endforeach; ?>
  <?php if (!$mine): ?><p class="muted"><?= e($sf->t('no_teams')) ?></p><?php endif; ?></div>

<?php if ($open): ?><h2><?= e($sf->t('discover')) ?></h2>
<div class="tm-grid"><?php foreach ($open as $x): ?><div class="card tm-card" style="--c:<?= e($x['color']) ?>"><b><?= e($x['name']) ?></b><span class="muted sm"><?= e($x['descr'] ?: '') ?></span>
  <span class="tm-card-f"><small class="muted"><?= e($sf->t('n_members', ['n' => (int)$x['n']])) ?></small>
  <form method="post" action="<?= e($sf->url('/team/' . (int)$x['id'] . '/join')) ?>"><?= csrf_field() ?><button class="btn sm primary"><?= e($sf->t('join')) ?></button></form></span></div><?php endforeach; ?></div><?php endif; ?>
<script src="<?= e($sf->asset('teams.js')) ?>?v=<?= e($sf->manifest['version']) ?>"></script>
