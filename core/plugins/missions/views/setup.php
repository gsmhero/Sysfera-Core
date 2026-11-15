<?php /** @var \Sysfera\Plugin $sf @var bool $hub @var array $own @var array $now @var string[] $keys */
$tab = 'setup'; include __DIR__ . '/_nav.php';
$mask = fn(string $v) => $v === '' ? '—' : (strlen($v) > 8 ? substr($v, 0, 4) . '…' . substr($v, -3) : '•••'); ?>
<h1 style="margin:0 0 6px"><?= e($sf->t('setup')) ?></h1>
<p class="muted"><?= e($sf->t($hub ? 'setup_hint_hub' : 'setup_hint_site')) ?></p>
<?php if (!$hub): ?>
<section class="card"><h3><?= e($sf->t('setup_current')) ?></h3>
  <div class="row"><?php foreach ($keys as $k): ?><span class="chip"><?= e($sf->t('setup_' . $k)) ?>: <b><?= e($k === 'tactify_url' ? ($now[$k] ?: '—') : $mask((string)$now[$k])) ?></b></span><?php endforeach; ?></div>
  <p class="muted sm"><?= e($now['_from_hub'] ? $sf->t('setup_from_hub', ['min' => (int)max(0, $now['_age']) / 60 | 0]) : $sf->t('setup_no_hub')) ?></p>
  <form method="post"><?= csrf_field() ?><button class="btn" name="do" value="refresh">↻ <?= e($sf->t('setup_refresh')) ?></button></form></section>
<?php endif; ?>
<form class="card" method="post"><?= csrf_field() ?><h3><?= e($sf->t($hub ? 'setup_hub_h' : 'setup_local_h')) ?></h3>
  <label class="fld"><span><?= e($sf->t('setup_yandex_maps_key')) ?></span><input name="yandex_maps_key" value="<?= e($own['yandex_maps_key']) ?>" maxlength="300" autocomplete="off"></label>
  <label class="fld"><span><?= e($sf->t('setup_mapbox_key')) ?></span><input name="mapbox_key" value="<?= e($own['mapbox_key']) ?>" maxlength="300" autocomplete="off"></label>
  <label class="fld"><span><?= e($sf->t('setup_tactify_url')) ?></span><input name="tactify_url" value="<?= e($own['tactify_url']) ?>" placeholder="https://www.tactify.ru" maxlength="300"></label>
  <p class="muted sm"><?= e($sf->t('setup_note')) ?></p>
  <button class="btn primary"><?= e($sf->t('save')) ?></button></form>
</div>
