<?php /** @var \Sysfera\Plugin $sf @var string $host @var string $url @var bool $key @var bool $hubMode @var bool|string|null $status @var bool $https */ ?>
<div class="page-h"><h1><?= e($sf->t('settings')) ?></h1><a class="btn" href="<?= e($sf->url('/')) ?>">← <?= e($sf->t('title')) ?></a></div>
<section class="card"><h3><?= e($sf->t('s_status')) ?></h3>
  <p><?= e($sf->t('s_domain')) ?>: <code><?= e($host) ?></code></p>
  <?php if ($hubMode): ?><p><span class="badge ok"><?= e($sf->t('s_is_hub')) ?></span></p>
  <?php elseif ($key): ?><p><?= $status === true ? '<span class="badge ok">' . e($sf->t('s_ok')) . '</span>' : '<span class="badge bad">' . e((string)$status) . '</span>' ?></p>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="disconnect"><button class="btn" data-confirm="<?= e($sf->t('s_disconnect_q')) ?>"><?= e($sf->t('s_disconnect')) ?></button></form>
  <?php else: ?><p><span class="badge warn"><?= e($sf->t('s_not_connected')) ?></span></p>
    <?php if (!$https): ?><p class="muted sm"><?= e($sf->t('s_need_https')) ?></p><?php endif; ?>
    <p class="muted sm"><?= e($sf->t('s_connect_hint')) ?></p>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="connect"><button class="btn primary"><?= e($sf->t('s_connect')) ?></button></form><?php endif; ?></section>
<form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="do" value="save"><h3><?= e($sf->t('s_advanced')) ?></h3>
  <label class="fld"><span><?= e($sf->t('s_hub_url')) ?></span><input name="hub_url" value="<?= e($url) ?>"></label>
  <label class="chk"><input type="checkbox" name="hub_mode" value="1" <?= $hubMode ? 'checked' : '' ?>> <?= e($sf->t('s_hub_mode')) ?></label>
  <p class="muted sm"><?= e($sf->t('s_hub_mode_hint')) ?></p><button class="btn"><?= e($sf->t('save')) ?></button></form>
