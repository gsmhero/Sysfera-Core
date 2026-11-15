<?php /** @var \Sysfera\Plugin $sf @var bool $hub @var array $own @var array $now @var string[] $keys @var ?array $net */
$net ??= null;
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
<?php if ($hub && $net): $st = $net['status']; ?>
<section class="card"><h3><?= e($sf->t('setup_net_h')) ?></h3>
  <p class="muted sm"><?= e($sf->t('setup_net_hint')) ?></p>
  <form method="post"><?= csrf_field() ?>
    <label class="fld"><span><?= e($sf->t('setup_tactify_secret')) ?> <?= $net['has'] ? '✔' : '' ?></span><input name="tactify_secret" type="password" autocomplete="new-password" maxlength="200" placeholder="<?= e($sf->t($net['has'] ? 'setup_secret_set' : 'setup_secret_none')) ?>"></label>
    <div class="row"><button class="btn primary" name="do" value="save"><?= e($sf->t('save')) ?></button><button class="btn" name="do" value="net_sync">↻ <?= e($sf->t('setup_net_sync')) ?></button></div></form>
  <p class="muted sm"><?= $st ? e($sf->t('setup_net_status', ['at' => $st['at'], 'a' => (int)$st['pulled'], 'b' => (int)$st['pushed'], 'c' => (int)$st['removed'], 'e' => $st['error'] ?: '—'])) : e($sf->t('setup_net_never')) ?> · <?= e($sf->t('setup_net_total', ['n' => (int)$net['total']])) ?></p>
  <?php foreach ($net['rows'] as $r): ?>
  <form method="post" class="row between sm" style="border-top:1px solid var(--line,#e5e7eb);padding:6px 0"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
    <span><?= $r['hidden'] ? '🚫 ' : '' ?><b><?= e($r['title']) ?></b> <span class="muted">· <?= e($r['source'] === 'tactify' ? 'tactify.ru' : preg_replace('/^site:/', '', (string)$r['source'])) ?> · <?= e($r['author']) ?><?= $r['reports'] ? ' · ⚑ ' . (int)$r['reports'] : '' ?></span></span>
    <span class="row"><button class="btn" name="do" value="net_hide"><?= e($sf->t($r['hidden'] ? 'net_unhide' : 'net_hide')) ?></button><button class="btn danger" name="do" value="net_delete" data-confirm="<?= e($sf->t('confirm_delete')) ?>"><?= e($sf->t('delete')) ?></button></span></form>
  <?php endforeach; ?>
</section>
<?php endif; ?>
<form class="card" method="post"><?= csrf_field() ?><h3><?= e($sf->t($hub ? 'setup_hub_h' : 'setup_local_h')) ?></h3>
  <label class="fld"><span><?= e($sf->t('setup_yandex_maps_key')) ?></span><input name="yandex_maps_key" value="<?= e($own['yandex_maps_key']) ?>" maxlength="300" autocomplete="off"></label>
  <label class="fld"><span><?= e($sf->t('setup_mapbox_key')) ?></span><input name="mapbox_key" value="<?= e($own['mapbox_key']) ?>" maxlength="300" autocomplete="off"></label>
  <label class="fld"><span><?= e($sf->t('setup_tactify_url')) ?></span><input name="tactify_url" value="<?= e($own['tactify_url']) ?>" placeholder="https://www.tactify.ru" maxlength="300"></label>
  <p class="muted sm"><?= e($sf->t('setup_note')) ?></p>
  <button class="btn primary"><?= e($sf->t('save')) ?></button></form>
</div>
