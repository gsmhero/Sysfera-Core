<?php /** @var array $available @var array $installed @var array $remote @var array $updates @var callable $problems */
use Sysfera\Plugins;
$form = fn(string $do, string $slug, string $label, string $cls = '', string $extra = '') => '<form method="post" class="inline" ' . $extra . '>' . csrf_field() . '<input type="hidden" name="do" value="' . $do . '"><input type="hidden" name="slug" value="' . e($slug) . '"><button class="btn ' . $cls . '">' . e($label) . '</button></form>';
$notInstalled = array_diff_key($available, $installed);
$remoteNew = array_diff_key($remote, $available);
?>
<div class="page-h"><h1><?= e(t('nav.plugins')) ?></h1></div>
<div class="hint"><?= e(t('plugins.hint')) ?></div>

<h2><?= e(t('plugins.installed')) ?></h2>
<?php if (!$installed): ?><div class="card empty"><p><?= e(t('plugins.none_installed')) ?></p></div><?php endif; ?>
<div class="list"><?php foreach ($installed as $slug => $row): $m = $available[$slug] ?? null; ?>
  <div class="card item">
    <div class="grow"><b><?= e($m ? Plugins::text($m, 'name') : $slug) ?></b> <span class="badge <?= $row['enabled'] ? 'ok' : 'mute' ?>"><?= e(t($row['enabled'] ? 'plugins.enabled' : 'plugins.disabled')) ?></span> <small class="muted">v<?= e($row['version']) ?></small>
      <?php if (isset($updates[$slug])): ?><span class="badge warn"><?= e(t('plugins.update_to', ['v' => $updates[$slug]['version']])) ?></span><?php endif; ?>
      <p class="muted sm"><?= e($m ? Plugins::text($m, 'description') : t('plugins.files_missing')) ?></p></div>
    <div class="actions">
      <?php if (isset($updates[$slug])) echo '<form method="post" class="inline" action="' . e(url('/updates')) . '">' . csrf_field() . '<input type="hidden" name="do" value="plugin"><input type="hidden" name="slug" value="' . e($slug) . '"><button class="btn primary">' . e(t('upd.update')) . '</button></form>'; ?>
      <?php if ($m && $row['enabled']) echo $form('disable', $slug, t('plugins.disable'), '', 'data-confirm="' . e(t('plugins.confirm_disable')) . '"'); ?>
      <?php if ($m && !$row['enabled']) echo $form('install', $slug, t('plugins.enable'), 'primary'); ?>
      <form method="post" class="inline" data-confirm="<?= e(t('plugins.confirm_uninstall')) ?>"><?= csrf_field() ?><input type="hidden" name="do" value="uninstall"><input type="hidden" name="slug" value="<?= e($slug) ?>"><button class="btn danger"><?= e(t('plugins.uninstall')) ?></button></form>
    </div>
  </div>
<?php endforeach; ?></div>

<h2><?= e(t('plugins.available')) ?></h2>
<div class="hint"><?= e(t('plugins.hint_available')) ?></div>
<?php if (!$notInstalled && !$remoteNew): ?><div class="card empty"><p><?= e(t('plugins.none_available')) ?></p></div><?php endif; ?>
<div class="list">
  <?php foreach ($notInstalled as $slug => $m): $pr = $problems($m); ?>
  <div class="card item"><div class="grow"><b><?= e(Plugins::text($m, 'name')) ?></b> <small class="muted">v<?= e($m['version']) ?></small><p class="muted sm"><?= e(Plugins::text($m, 'description')) ?></p>
    <?php foreach ($pr as $p): ?><p class="sm bad-t"><?= e($p) ?></p><?php endforeach; ?></div>
    <div class="actions"><?php if (!$pr) echo $form('install', $slug, t('plugins.install'), 'primary'); ?></div></div>
  <?php endforeach; ?>
  <?php foreach ($remoteNew as $slug => $m): ?>
  <div class="card item"><div class="grow"><b><?= e(Plugins::text($m, 'name')) ?></b> <small class="muted">v<?= e($m['version']) ?></small> <span class="badge info"><?= e(t('plugins.on_server')) ?></span><p class="muted sm"><?= e(Plugins::text($m, 'description')) ?></p></div>
    <div class="actions"><?= $form('download', $slug, t('plugins.download'), 'primary') ?></div></div>
  <?php endforeach; ?>
</div>

<h2><?= e(t('plugins.upload')) ?></h2>
<form class="card" method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="do" value="upload">
  <p class="muted sm"><?= e(t('plugins.hint_upload')) ?></p>
  <div class="row"><input type="file" name="zip" accept=".zip" required><button class="btn"><?= e(t('plugins.upload_btn')) ?></button></div></form>
