<?php /** Dashboard widget. Vars: $list (published open vacancies: uuid, title, applications, new, applied), $total, $manage (may handle candidates) */ ?>
<link rel="stylesheet" href="<?= e($sf->asset('hr.css')) ?>?v=<?= e($sf->manifest['version']) ?>">
<div class="row between"><b><?= e($sf->t('w_title')) ?></b><a class="sm" href="<?= e($sf->url($manage ? '/vacancies' : '/')) ?>"><?= e($sf->t('w_open')) ?> →</a></div>
<h4 class="hrw-h"><?= e($sf->t('w_vacancies')) ?> <span class="muted"><?= (int)$total ?></span></h4>
<?php if (!$list): ?><p class="muted sm"><?= e($sf->t('w_none')) ?></p><?php else: ?>
<ul class="hrw-l"><?php foreach ($list as $v): ?>
  <li><span class="hrw-t"><a href="<?= e($sf->url('/v/' . $v['uuid'])) ?>"><?= e($v['title']) ?></a>
    <small class="muted"><?php if (!empty($v['salary'])): ?><span class="hrw-s"><?= e($v['salary']) ?></span> · <?php endif; ?><?= e($sf->t('w_applications', ['n' => (int)$v['applications']])) ?><?php if ($manage && (int)$v['new'] > 0): ?> · <span class="hrw-new"><?= e($sf->t('w_new', ['n' => (int)$v['new']])) ?></span><?php endif; ?></small></span>
    <span class="hrw-a"><a class="btn sm" href="<?= e($sf->url('/v/' . $v['uuid'])) ?>"><?= e($sf->t('act_preview')) ?></a>
    <?php if ($v['applied']): ?><span class="hr-st ok"><?= e($sf->t('applied_badge')) ?></span>
    <?php else: ?><form method="post" action="<?= e($sf->url('/apply/' . $v['uuid'])) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="return" value="dashboard"><button class="btn sm primary"><?= e($sf->t('act_apply')) ?></button></form><?php endif; ?></span></li>
<?php endforeach; ?></ul><?php endif; ?>