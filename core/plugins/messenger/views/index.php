<?php /** @var \Sysfera\Plugin $sf @var array $chats @var ?array $me @var string $sid @var string $q @var ?array $found @var ?string $searchError */ ?>
<link rel="stylesheet" href="<?= e($sf->asset('messenger.css')) ?>?v=<?= e($sf->manifest['version']) ?>">
<div class="page-h"><h1><?= e($sf->t('title')) ?></h1><?php if ($sf->can('messenger.admin')): ?><a class="btn" href="<?= e($sf->url('/settings')) ?>"><?= e($sf->t('settings')) ?></a><?php endif; ?></div>
<div class="hint"><?= e($sf->t('your_id')) ?>: <code class="msg-id"><?= e($sid) ?></code> — <?= e($sf->t('your_id_hint')) ?></div>

<?php if ($me !== null && empty($me['discoverable'])): ?><div class="flash bad"><?= e($sf->t('hidden_note')) ?> <a href="#discover"><?= e($sf->t('hidden_link')) ?></a></div><?php endif; ?>
<section class="card"><form method="get" class="row"><input name="q" value="<?= e($q) ?>" placeholder="<?= e($sf->t('search_ph')) ?>" maxlength="190" style="flex:1;min-width:200px"><button class="btn primary"><?= e($sf->t('search')) ?></button></form>
<?php if (!empty($searchError)): ?><p class="flash bad"><?= e($searchError) ?></p><?php endif; ?>
<?php if ($found !== null): ?>
  <?php if (!$found): ?><p class="muted sm"><?= e($sf->t('search_none')) ?></p><?php endif; ?>
  <?php foreach ($found as $u): ?><a class="msg-row" href="<?= e($sf->url('/chat?with=' . rawurlencode($u['sid']))) ?>"><b><?= e($u['name']) ?></b><small class="muted"><?= e($u['sid']) ?></small><span class="btn sm"><?= e($sf->t('write')) ?></span></a><?php endforeach; ?>
<?php endif; ?></section>

<section class="card" style="padding:0" id="msg-chats" data-live="<?= e($sf->url('/list')) ?>"><?= \Sysfera\View::file(__DIR__ . '/_chats.php', ['sf' => $sf, 'chats' => $chats]) ?></section>

<form class="card" id="discover" method="post" action="<?= e($sf->url('/profile')) ?>"><?= csrf_field() ?>
  <label class="chk"><input type="checkbox" name="discoverable" value="1" <?= !empty($me['discoverable']) ? 'checked' : '' ?>> <?= e($sf->t('discoverable')) ?></label>
  <p class="muted sm"><?= e($sf->t('discoverable_hint')) ?></p><button class="btn"><?= e($sf->t('save')) ?></button></form>
<script src="<?= e($sf->asset('messenger.js')) ?>?v=<?= e($sf->manifest['version']) ?>"></script>
