<?php /** @var \Sysfera\Plugin $sf @var array $rows @var int $liveCount @var string $feed */
$badge = ['warning' => 'bad', 'update' => 'info', 'news' => 'mute']; ?>
<div class="page-h"><h1><?= e($sf->t('title')) ?> <small class="muted"><?= count($rows) ?></small></h1><a class="btn primary" href="<?= e($sf->url('/edit')) ?>">＋ <?= e($sf->t('new_post')) ?></a></div>
<div class="hint"><?= e($sf->t('hint', ['n' => $liveCount])) ?></div>

<?php if (!$rows): ?><div class="card empty"><h3><?= e($sf->t('none')) ?></h3><p><?= e($sf->t('none_hint')) ?></p></div>
<?php else: ?>
<div class="card" style="padding:0;overflow-x:auto"><table>
  <thead><tr><th><?= e($sf->t('col_post')) ?></th><th><?= e($sf->t('col_type')) ?></th><th><?= e($sf->t('col_date')) ?></th><th><?= e($sf->t('col_status')) ?></th><th></th></tr></thead>
  <tbody><?php foreach ($rows as $r):
    $expired = $r['expires'] && $r['expires'] < date('Y-m-d');
    $title = $r['title_ru'] !== '' ? $r['title_ru'] : $r['title_en']; ?>
    <tr><td><a href="<?= e($sf->url('/edit?id=' . (int)$r['id'])) ?>"><b><?= e($title) ?></b></a><?php if ($r['pinned']): ?> <small class="muted">📌</small><?php endif; ?><br>
        <small class="muted"><?= $r['title_en'] !== '' && $r['title_ru'] !== '' ? e($r['title_en']) : ($r['title_en'] === '' ? 'EN: —' : 'RU: —') ?></small></td>
      <td><span class="badge <?= $badge[$r['type']] ?>"><?= e($sf->t('type_' . $r['type'])) ?></span></td>
      <td><?= e($r['post_date']) ?><?php if ($r['expires']): ?><br><small class="muted">→ <?= e($r['expires']) ?></small><?php endif; ?></td>
      <td><?php if (!$r['published']): ?><span class="badge mute"><?= e($sf->t('st_draft')) ?></span><?php elseif ($expired): ?><span class="badge warn"><?= e($sf->t('st_expired')) ?></span><?php else: ?><span class="badge ok"><?= e($sf->t('st_live')) ?></span><?php endif; ?></td>
      <td style="white-space:nowrap">
        <form method="post" action="<?= e($sf->url('/action')) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <button class="btn" name="do" value="toggle"><?= e($sf->t($r['published'] ? 'unpublish' : 'publish')) ?></button>
          <button class="btn danger" name="do" value="delete" data-confirm="<?= e($sf->t('confirm_delete')) ?>"><?= e($sf->t('delete')) ?></button></form></td></tr>
  <?php endforeach; ?></tbody></table></div>
<?php endif; ?>

<section class="card"><h3><?= e($sf->t('feed')) ?></h3><p class="muted sm"><?= e($sf->t('feed_hint')) ?></p>
  <code style="display:block;padding:9px 11px;border:1px solid var(--line);border-radius:8px;background:var(--bg);word-break:break-all;user-select:all"><?= e($feed) ?></code></section>
