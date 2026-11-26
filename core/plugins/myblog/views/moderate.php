<?php /** @var \Sysfera\Plugin $sf @var array $rows */ ?>
<div class="page-h"><h1><?= e($sf->t('moderate')) ?></h1><a class="btn" href="<?= e($sf->url('/')) ?>"><?= e($sf->t('back')) ?></a></div>
<p class="muted"><?= e($sf->t('moderate_hint')) ?></p>
<div class="card"><?php if (!$rows): ?><p class="muted"><?= e($sf->t('none')) ?></p><?php else: ?><table><tbody>
<?php foreach ($rows as $r): ?><tr><td><b><?= e($r['title']) ?></b><div class="muted sm"><?= e($r['excerpt']) ?></div></td><td class="sm"><?= e($r['author']) ?><br><span class="muted"><?= e($r['domain']) ?> · <?= e(substr((string)$r['created'], 0, 10)) ?></span></td>
  <td><?php if ($r['hidden']): ?><span class="badge bad"><?= e($sf->t('hidden')) ?></span><?php endif; ?><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn sm" name="do" value="<?= $r['hidden'] ? 'show' : 'hide' ?>"><?= e($sf->t($r['hidden'] ? 'show' : 'hide')) ?></button></form></td></tr>
<?php endforeach; ?></tbody></table><?php endif; ?></div>
