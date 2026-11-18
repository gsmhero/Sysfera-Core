<?php /** @var \Sysfera\Plugin $sf @var array $rows */
$tab = 'notes'; include __DIR__ . '/_nav.php'; ?>
<div class="page-h"><h1><?= e($sf->t('nav_notes')) ?></h1>
  <form method="post" class="row"><?= csrf_field() ?><button class="btn" name="do" value="read_all"><?= e($sf->t('read_all')) ?></button><button class="btn" name="do" value="clear" data-confirm="<?= e($sf->t('confirm_clear')) ?>"><?= e($sf->t('clear_read')) ?></button></form></div>
<?php if (!$rows): ?><div class="card empty"><p><?= e($sf->t('notes_none')) ?></p></div><?php endif; ?>
<div class="list">
<?php foreach ($rows as $n): ?>
  <div class="card row between" style="<?= $n['read_at'] ? 'opacity:.6' : '' ?>">
    <div><?= $n['kind'] === 'team' ? mis_ico('users') : mis_ico('clock') ?> <?= $n['mission_id'] ? '<a href="' . e($sf->url('/library/' . (int)$n['mission_id'])) . '">' . e($n['body']) . '</a>' : e($n['body']) ?> <small class="muted"><?= e(substr((string)$n['created_at'], 0, 16)) ?></small></div>
    <?php if (!$n['read_at']): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$n['id'] ?>"><button class="btn" name="do" value="read"><?= mis_ico('check') ?></button></form><?php endif; ?>
  </div>
<?php endforeach; ?></div>
</div>
