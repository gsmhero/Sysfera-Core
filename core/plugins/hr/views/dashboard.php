<?php /** @var \Sysfera\Plugin $sf @var array $vac @var array $alerts @var array $pending @var array $orphans @var array $missing @var array $emps @var bool $isHr @var array $myLeave */
include __DIR__ . '/_nav.php'; ?>
<div class="page-h"><h1><?= e($sf->t('menu')) ?></h1></div>
<?php if ($isHr || $vac || $alerts || $pending): ?>
<div class="hr-tiles">
  <a class="card hr-tile" href="<?= e($sf->url('/vacancies')) ?>"><b><?= count($vac) ?></b><span><?= e($sf->t('tile_vacancies')) ?></span></a>
  <a class="card hr-tile" href="<?= e($sf->url('/employees')) ?>"><b><?= count($emps) ?></b><span><?= e($sf->t('tile_employees')) ?></span></a>
  <a class="card hr-tile" href="<?= e($sf->url('/leave')) ?>"><b><?= count($pending) ?></b><span><?= e($sf->t('tile_leave')) ?></span></a>
  <a class="card hr-tile" href="<?= e($sf->url('/employees')) ?>"><b><?= count($alerts) ?></b><span><?= e($sf->t('tile_onboarding')) ?></span></a>
  <a class="card hr-tile" href="<?= e($sf->url('/access')) ?>"><b><?= count($orphans) ?></b><span><?= e($sf->t('tile_orphans')) ?></span></a>
  <a class="card hr-tile" href="<?= e($sf->url('/privacy')) ?>"><b><?= count($missing) ?></b><span><?= e($sf->t('tile_consents')) ?></span></a>
</div>
<?php endif; ?>
<?php if ($alerts): ?><div class="card flush"><h3 style="padding:12px 12px 0"><?= e($sf->t('alerts_h')) ?></h3><table class="tbl"><tr><th><?= e($sf->t('f_employee')) ?></th><th><?= e($sf->t('f_item')) ?></th><th><?= e($sf->t('f_due')) ?></th></tr>
  <?php foreach ($alerts as $a): ?><tr class="hr-bad"><td><a href="<?= e($sf->url('/employees/' . $a['employee_uuid'])) ?>"><?= e($a['employee']) ?></a></td><td><?= e($tk($a['code'])) ?></td><td><?= e($a['due_at']) ?></td></tr><?php endforeach; ?></table></div><?php endif; ?>
<?php if ($orphans): ?><div class="hint"><?= e($sf->t('orphans_hint', ['n' => count($orphans)])) ?> <a href="<?= e($sf->url('/access')) ?>"><?= e($sf->t('nav_access')) ?></a></div><?php endif; ?>
<?php if ($vac): ?><div class="card flush"><h3 style="padding:12px 12px 0"><?= e($sf->t('tile_vacancies')) ?></h3><table class="tbl"><tr><th><?= e($sf->t('f_title')) ?></th><th><?= e($sf->t('f_wf')) ?></th><th><?= e($sf->t('f_candidates')) ?></th></tr>
  <?php foreach ($vac as $v): ?><tr><td><a href="<?= e($sf->url('/vacancies/' . $v['uuid'])) ?>"><?= e($v['title']) ?></a></td><td><span class="hr-st <?= $hrst($v['wf']) ?>"><?= e($sf->t('wf_' . $v['wf'])) ?></span></td><td><?= (int)$v['candidates'] ?></td></tr><?php endforeach; ?></table></div><?php endif; ?>
<?php if (!$isHr): ?><div class="card"><h3><?= e($sf->t('my_leave')) ?></h3>
  <?php if (!$myLeave): ?><p class="muted"><?= e($sf->t('none')) ?></p><?php endif; ?>
  <?php foreach ($myLeave as $l): ?><p><?= e($sf->t('lk_' . $l['kind'])) ?> <?= e($l['starts_on']) ?> – <?= e($l['ends_on']) ?> <span class="hr-st <?= $hrst($l['status']) ?>"><?= e($sf->t('ls_' . $l['status'])) ?></span></p><?php endforeach; ?>
  <a class="btn" href="<?= e($sf->url('/leave')) ?>"><?= e($sf->t('nav_leave')) ?></a> <a class="btn" href="<?= e($sf->url('/me')) ?>"><?= e($sf->t('nav_me')) ?></a></div><?php endif; ?>
