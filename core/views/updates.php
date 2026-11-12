<?php /** @var ?array $m @var ?array $core @var array $plugins @var array $backups @var int $checked @var bool $zip */
$lang = \Sysfera\I18n::lang(); ?>
<div class="page-h"><h1><?= e(t('nav.updates')) ?></h1></div>
<div class="hint"><?= e(t('upd.hint')) ?></div>
<?php if (!$zip): ?><div class="flash warn"><?= e(t('upd.need_zip')) ?></div><?php endif; ?>
<section class="card">
  <div class="row between"><div><h3><?= e(t('upd.core')) ?></h3><p class="muted">Sysfera <b><?= e(SF_VERSION) ?></b> · <?= e(t('upd.last_check')) ?>: <?= $checked ? e(date('Y-m-d H:i', $checked)) : '—' ?></p></div>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="check"><button class="btn"><?= e(t('upd.check')) ?></button></form></div>
  <?php if ($m === null): ?><p class="sm bad-t"><?= e(t('upd.unreachable')) ?></p>
  <?php elseif ($core): ?>
    <div class="flash ok"><?= e(t('upd.available', ['v' => $core['version']])) ?>
      <?php $notes = $core['notes'] ?? ''; $notes = is_array($notes) ? ($notes[$lang] ?? $notes['en'] ?? '') : $notes; if ($notes): ?><br><small><?= e($notes) ?></small><?php endif; ?></div>
    <form method="post" data-confirm="<?= e(t('upd.confirm')) ?>"><?= csrf_field() ?><input type="hidden" name="do" value="core"><button class="btn primary" <?= $zip ? '' : 'disabled' ?>><?= e(t('upd.update_to', ['v' => $core['version']])) ?></button></form>
  <?php else: ?><p class="sm ok-t"><?= e(t('upd.up_to_date')) ?></p><?php endif; ?>
</section>
<?php if ($plugins): ?><h2><?= e(t('upd.plugins')) ?></h2><div class="list">
  <?php foreach ($plugins as $slug => $p): ?><form method="post" class="card item" data-confirm="<?= e(t('upd.confirm')) ?>"><?= csrf_field() ?><input type="hidden" name="do" value="plugin"><input type="hidden" name="slug" value="<?= e($slug) ?>">
    <div class="grow"><b><?= e(\Sysfera\Plugins::text($p, 'name') ?: $slug) ?></b> <small class="muted">→ v<?= e($p['version']) ?></small></div><button class="btn primary"><?= e(t('upd.update')) ?></button></form><?php endforeach; ?></div><?php endif; ?>
<h2><?= e(t('upd.backups')) ?></h2>
<div class="hint"><?= e(t('upd.backups_hint')) ?></div>
<?php if (!$backups): ?><div class="card empty"><p><?= e(t('upd.no_backups')) ?></p></div><?php endif; ?>
<div class="list"><?php foreach ($backups as $b): ?><div class="card item"><div class="grow"><code>storage/backups/<?= e($b['name']) ?></code></div><small class="muted"><?= e(human_size($b['size'])) ?></small></div><?php endforeach; ?></div>
