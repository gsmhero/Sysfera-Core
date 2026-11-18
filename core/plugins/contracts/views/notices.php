<?php /** @var \Sysfera\Plugin $sf @var array $list the notices of the user (Svc::notices); $L = the language array of the viewer */
use SysferaContracts\Notice;
include __DIR__ . '/_nav.php'; ?>
<div class="page-h row between"><h1><?= e($sf->t('nav_notices')) ?></h1><?php if ($list): ?><form method="post" action="<?= e($sf->url('/notices/clear')) ?>" data-confirm="<?= e($sf->t('confirm_clear_all')) ?>"><?= csrf_field() ?><button class="btn"><?= e($sf->t('clear_all')) ?></button></form><?php endif; ?></div>
<?php if (!$list): ?><div class="card empty"><p><?= e($sf->t('no_notices')) ?></p></div><?php endif; ?>
<?php foreach ($list as $n): $d = json_decode((string)$n['data'], true) ?: []; ?>
  <div class="ct-notice"><a class="item <?= $n['read_at'] === null ? 'unread' : '' ?>" href="<?= e($sf->url((string)$n['url'])) ?>"><?= e(Notice::headline($L, $n['kind'], $d)) ?><small><?= e($d['title'] ?? '') ?><?= !empty($d['step']) ? ' · ' . e($d['step']) : '' ?><?= !empty($d['extra']) ? ' · ' . e($d['extra']) : '' ?> · <?= e($ctdt($n['created_at'])) ?></small></a>
    <form method="post" action="<?= e($sf->url('/notices/clear')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$n['id'] ?>"><button class="btn sm" title="<?= e($sf->t('clear')) ?>">×</button></form></div>
<?php endforeach; ?>
