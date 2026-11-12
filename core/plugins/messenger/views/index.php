<?php /** @var \Sysfera\Plugin $sf @var array $chats @var ?array $me @var string $sid @var string $q @var ?array $found */ ?>
<link rel="stylesheet" href="<?= e($sf->asset('messenger.css')) ?>?v=<?= e($sf->manifest['version']) ?>">
<div class="page-h"><h1><?= e($sf->t('title')) ?></h1><?php if ($sf->can('messenger.admin')): ?><a class="btn" href="<?= e($sf->url('/settings')) ?>"><?= e($sf->t('settings')) ?></a><?php endif; ?></div>
<div class="hint"><?= e($sf->t('your_id')) ?>: <code class="msg-id"><?= e($sid) ?></code> — <?= e($sf->t('your_id_hint')) ?></div>

<section class="card"><form method="get" class="row"><input name="q" value="<?= e($q) ?>" placeholder="<?= e($sf->t('search_ph')) ?>" maxlength="190" style="flex:1;min-width:200px"><button class="btn primary"><?= e($sf->t('search')) ?></button></form>
<?php if ($found !== null): ?>
  <?php if (!$found): ?><p class="muted sm"><?= e($sf->t('search_none')) ?></p><?php endif; ?>
  <?php foreach ($found as $u): ?><a class="msg-row" href="<?= e($sf->url('/chat?with=' . rawurlencode($u['sid']))) ?>"><b><?= e($u['name']) ?></b><small class="muted"><?= e($u['sid']) ?></small><span class="btn sm"><?= e($sf->t('write')) ?></span></a><?php endforeach; ?>
<?php endif; ?></section>

<section class="card" style="padding:0">
<?php if (!$chats): ?><div class="empty" style="padding:22px"><h3><?= e($sf->t('no_chats')) ?></h3><p class="muted"><?= e($sf->t('no_chats_hint')) ?></p></div><?php endif; ?>
<?php foreach ($chats as $c): ?><a class="msg-row" href="<?= e($sf->url('/chat?with=' . rawurlencode($c['sid']))) ?>">
  <span class="msg-av"><?= e(mb_strtoupper(mb_substr($c['name'], 0, 1))) ?></span>
  <span class="msg-mid"><b><?= e($c['name']) ?></b><small class="muted"><?= $c['mine'] ? e($sf->t('you')) . ': ' : '' ?><?= e($c['last']) ?></small></span>
  <span class="msg-end"><small class="muted"><?= e(ago($c['at'])) ?></small><?php if ($c['unread'] > 0): ?><span class="badge info"><?= (int)$c['unread'] ?></span><?php endif; ?></span></a><?php endforeach; ?>
</section>

<form class="card" method="post" action="<?= e($sf->url('/profile')) ?>"><?= csrf_field() ?>
  <label class="chk"><input type="checkbox" name="discoverable" value="1" <?= !empty($me['discoverable']) ? 'checked' : '' ?>> <?= e($sf->t('discoverable')) ?></label>
  <p class="muted sm"><?= e($sf->t('discoverable_hint')) ?></p><button class="btn"><?= e($sf->t('save')) ?></button></form>
