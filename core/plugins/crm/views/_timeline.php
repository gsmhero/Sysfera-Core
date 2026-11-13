<?php /** @var \Sysfera\Plugin $sf @var list<array> $timeline */ ?>
<div class="crm-tl"><?php foreach ($timeline as $r): ?>
  <div class="crm-tl-i <?= e($r['kind']) ?>"><i></i><div><small class="muted"><?= e($r['user_name'] ?: $sf->t('system')) ?> · <?= e(crm_dt($r['created_at'])) ?> · <?= e($sf->t('tl_' . $r['kind'])) ?></small>
    <div><?= nl2br(e((string)$r['body'])) ?></div></div></div><?php endforeach; ?>
  <?php if (!$timeline): ?><p class="muted"><?= e($sf->t('no_history')) ?></p><?php endif; ?></div>
