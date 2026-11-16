<?php /** @var \Sysfera\Plugin $sf @var array $i */ ?>
<div class="card" style="max-width:520px;margin:30px auto;text-align:center">
  <h2 style="margin:0 0 6px"><span style="display:inline-block;width:14px;height:14px;border-radius:4px;background:<?= e($i['tcolor']) ?>;margin-right:8px"></span><?= e($i['tname']) ?></h2>
  <?php if (!empty($i['tdescr'])): ?><p class="muted"><?= e($i['tdescr']) ?></p><?php endif; ?>
  <p><?= e($sf->t('inv_text', ['n' => (string)($i['by_name'] ?? '—'), 'r' => $sf->t('role_' . $i['role'])])) ?></p>
  <form method="post" action="<?= e($sf->url('/invite/' . $i['token'] . '/accept')) ?>"><?= csrf_field() ?><button class="btn primary"><?= e($sf->t('inv_join')) ?></button> <a class="btn" href="<?= e($sf->url('/')) ?>"><?= e($sf->t('cancel')) ?></a></form>
</div>
