<?php /** @var \Sysfera\Plugin $sf @var array $t team (with role) @var string $tab @var list<array> $chans @var ?array $c current channel */
use SysferaTeams\Tm;
$cid = (int)($c['id'] ?? 0); $tid = (int)$t['id']; $role = $t['role'];
$tabs = ['chat' => ['', 'chat'], 'board' => ['/board', 'board'], 'calendar' => ['/calendar', 'calendar'], 'files' => ['/files', 'files'], 'members' => ['/members', 'members']]; ?>
<link rel="stylesheet" href="<?= e($sf->asset('teams.css')) ?>?v=<?= e($sf->manifest['version']) ?>">
<div class="tm-head" style="--c:<?= e($t['color']) ?>"><a class="muted sm" href="<?= e($sf->url('/')) ?>">← <?= e($sf->t('menu')) ?></a>
  <h1><span class="tm-dot"></span><?= e($t['name']) ?> <?php if ($t['privacy'] === 'private'): ?><small class="muted" title="<?= e($sf->t('private')) ?>">🔒</small><?php endif; ?></h1>
  <?php if ($t['descr']): ?><p class="muted" style="margin:0"><?= e($t['descr']) ?></p><?php endif; ?></div>
<nav class="tm-tabs"><?php foreach ($tabs as $k => [$path, $l]): ?><a href="<?= e($sf->url('/t/' . $tid . $path)) ?>" class="<?= $tab === $k ? 'on' : '' ?>"><?= e($sf->t($l)) ?></a><?php endforeach; ?></nav>
<?php if ($tab === 'chat' && $role !== null): ?>
<aside class="tm-side"><div class="tm-side-h"><b><?= e($sf->t('channels')) ?></b>
  <?php if (Tm::can($role, 'channel')): ?><button type="button" class="tm-plus" data-toggle="#newchan" title="<?= e($sf->t('new_channel')) ?>">+</button><?php endif; ?></div>
  <?php foreach ($chans as $ch): ?><a href="<?= e($sf->url('/t/' . $tid . '/c/' . (int)$ch['id'])) ?>" class="<?= $cid === (int)$ch['id'] ? 'on' : '' ?>"><span><?= $ch['is_private'] ? '🔒' : '#' ?> <?= e($ch['name']) ?></span>
    <?php if ($ch['unread'] && $cid !== (int)$ch['id']): ?><i class="tm-badge<?= $ch['mentions'] ? ' me' : '' ?>"><?= $ch['mentions'] ? '@' . (int)$ch['mentions'] : (int)$ch['unread'] ?></i><?php endif; ?></a><?php endforeach; ?>
  <?php if (Tm::can($role, 'channel')): ?>
  <form id="newchan" method="post" action="<?= e($sf->url('/t/' . $tid . '/channel/save')) ?>" class="tm-newchan" hidden><?= csrf_field() ?>
    <input name="name" placeholder="<?= e($sf->t('channel_name')) ?>" maxlength="60" required><input name="topic" placeholder="<?= e($sf->t('topic')) ?>" maxlength="250">
    <label class="chk"><input type="checkbox" name="private" value="1" data-priv> <?= e($sf->t('private_channel')) ?></label>
    <div class="tm-priv" hidden><?php foreach ($people ?? [] as $u): if ((int)$u['id'] === $sf->auth()->id()) continue; ?><label class="chk"><input type="checkbox" name="members[]" value="<?= (int)$u['id'] ?>"> <?= e($u['name']) ?></label><?php endforeach; ?></div>
    <button class="btn sm primary"><?= e($sf->t('create')) ?></button></form><?php endif; ?></aside>
<?php endif; ?>
