<?php /** Vacancy preview for any signed-in user. Vars: $v (Recruit::preview), $lang, $manage, $back */ ?>
<link rel="stylesheet" href="<?= e($sf->asset('hr.css')) ?>?v=<?= e($sf->manifest['version']) ?>">
<div class="page-h"><h1><?= e($v['title']) ?></h1><span class="row"><a class="btn" href="<?= e(url($back === 'activity' ? '/p/activity/' : '/')) ?>">← <?= e($sf->t($back === 'activity' ? 'pv_back_activity' : 'pv_back')) ?></a><?php if ($manage): ?><a class="btn" href="<?= e($sf->url('/vacancies/' . $v['uuid'])) ?>"><?= e($sf->t('pv_manage')) ?></a><?php endif; ?></span></div>
<div class="card hr-pv">
  <div class="hr-pv-facts">
    <?php if ($v['salary'] !== ''): ?><div><small><?= e($sf->t('f_salary')) ?></small><b><?= e($v['salary']) ?></b></div><?php endif; ?>
    <?php if ($v['role'] !== ''): ?><div><small><?= e($sf->t('f_role')) ?></small><b><?= e($lang === 'ru' ? $v['role_ru'] : $v['role']) ?></b></div><?php endif; ?>
    <?php if ($v['team'] !== ''): ?><div><small><?= e($sf->t('f_team')) ?></small><b><?= e($v['team']) ?></b></div><?php endif; ?>
    <?php foreach (\SysferaHr\Recruit::facts((array)($v['details'] ?? []), [$sf, 't']) as $fk => $fx): ?><div><small><?= e($sf->t($fk)) ?></small><b><?= e($fx) ?></b></div><?php endforeach; ?>
    <?php if ($v['published_at'] !== ''): ?><div><small><?= e($sf->t('pv_published')) ?></small><b><?= e(substr($v['published_at'], 0, 10)) ?></b></div><?php endif; ?>
    <div><small><?= e($sf->t('pv_applications')) ?></small><b><?= (int)$v['applications'] ?></b></div>
  </div>
  <?php if (!empty($v['skills'])): ?><div class="hr-pv-sk"><small><?= e($sf->t('f_skills')) ?></small><p class="hr-chips"><?php foreach ($v['skills'] as $s): ?><span class="hr-chip"><i><?= e($s) ?></i></span><?php endforeach; ?></p></div><?php endif; ?>
  <?php $vd = (array)($v['details'] ?? []); $vdText = (string)$v['description']; include __DIR__ . '/_details.php'; ?>
  <div class="row"><?php if (!$v['open']): ?><span class="hr-st bad"><?= e($sf->t('pv_closed')) ?></span>
    <?php elseif ($v['applied']): ?><span class="hr-st ok"><?= e($sf->t('applied_badge')) ?></span>
    <?php else: ?><form method="post" action="<?= e($sf->url('/apply/' . $v['uuid'])) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="return" value="preview"><button class="btn primary"><?= e($sf->t('act_apply')) ?></button></form><?php endif; ?></div>
</div>