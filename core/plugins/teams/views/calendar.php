<?php /** @var \Sysfera\Plugin $sf @var array $t @var array $m @var array $by @var list<array> $upcoming @var ?array $edit */
use SysferaTeams\Tm;
$tab = 'calendar'; require __DIR__ . '/_nav.php'; $tid = (int)$t['id']; $can = Tm::can($t['role'], 'event'); $me = $sf->auth()->id();
$act = fn(string $p) => e($sf->url('/t/' . $tid . $p)); $mod = Tm::can($t['role'], 'moderate');
$days = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun']; $e = $edit;
$ed = $e ? [substr((string)$e['starts'], 0, 10), substr((string)$e['starts'], 11, 5), $e['ends'] ? substr((string)$e['ends'], 0, 10) : '', $e['ends'] ? substr((string)$e['ends'], 11, 5) : ''] : [date('Y-m-d'), '', '', '']; ?>
<div class="tm-cal-h"><a class="btn sm" href="<?= $act('/calendar?m=' . $m['prev']) ?>">‹</a><b><?= e($sf->t('month_' . (int)substr($m['ym'], 5, 2))) ?> <?= e(substr($m['ym'], 0, 4)) ?></b><a class="btn sm" href="<?= $act('/calendar?m=' . $m['next']) ?>">›</a>
  <a class="btn sm" href="<?= $act('/calendar') ?>"><?= e($sf->t('today')) ?></a></div>
<div class="tm-cal"><?php foreach ($days as $d): ?><div class="tm-cal-d"><?= e($sf->t('d_' . $d)) ?></div><?php endforeach; ?>
  <?php foreach ($m['weeks'] as $week) foreach ($week as $day): ?><div class="tm-cal-c<?= $day['in'] ? '' : ' out' ?><?= $day['today'] ? ' today' : '' ?>"><span class="n"><?= (int)substr($day['date'], 8, 2) ?></span>
    <?php foreach ($by[$day['date']] ?? [] as $ev): ?><a class="tm-ev" href="<?= $act('/calendar?m=' . $m['ym'] . '&edit=' . (int)$ev['id']) ?>" title="<?= e($ev['title']) ?>"><?= substr((string)$ev['starts'], 11, 5) !== '00:00' && substr((string)$ev['starts'], 0, 10) === $day['date'] ? e(substr((string)$ev['starts'], 11, 5)) . ' ' : '' ?><?= e($ev['title']) ?></a><?php endforeach; ?></div><?php endforeach; ?></div>

<div class="tm-two" style="margin-top:14px"><?php if ($can): ?>
  <form class="card" method="post" action="<?= $act('/event/save') ?>"><?= csrf_field() ?><h3><?= e($sf->t($e ? 'edit_event' : 'add_event')) ?></h3><?php if ($e): ?><input type="hidden" name="id" value="<?= (int)$e['id'] ?>"><?php endif; ?>
    <label class="fld"><span><?= e($sf->t('f_title')) ?> *</span><input name="title" value="<?= e($e['title'] ?? '') ?>" required maxlength="190"></label>
    <div class="cols"><label class="fld"><span><?= e($sf->t('f_start')) ?></span><input type="date" name="date" value="<?= e($ed[0]) ?>" required></label><label class="fld"><span><?= e($sf->t('f_time')) ?></span><input type="time" name="time" value="<?= e($ed[1]) ?>"></label>
      <label class="fld"><span><?= e($sf->t('f_end')) ?></span><input type="date" name="date_end" value="<?= e($ed[2]) ?>"></label><label class="fld"><span><?= e($sf->t('f_time')) ?></span><input type="time" name="time_end" value="<?= e($ed[3]) ?>"></label></div>
    <label class="fld"><span><?= e($sf->t('f_descr')) ?></span><textarea name="descr" rows="2" maxlength="2000"><?= e($e['descr'] ?? '') ?></textarea></label>
    <button class="btn primary"><?= e($sf->t('save')) ?></button>
    <?php if ($e && ((int)$e['created_by'] === $me || $mod)): ?><button class="btn danger" formaction="<?= $act('/event/' . (int)$e['id'] . '/delete') ?>" formnovalidate data-confirm="<?= e($sf->t('confirm_delete')) ?>"><?= e($sf->t('delete')) ?></button><?php endif; ?></form><?php endif; ?>
  <section class="card"><h3><?= e($sf->t('upcoming')) ?></h3>
    <?php foreach ($upcoming as $ev): ?><div class="tm-up"><b><?= e(date('d.m.Y', strtotime($ev['starts']))) ?><?= substr((string)$ev['starts'], 11, 5) !== '00:00' ? ' ' . e(substr((string)$ev['starts'], 11, 5)) : '' ?></b> <?= e($ev['title']) ?><?php if ($ev['descr']): ?><div class="muted sm"><?= e($ev['descr']) ?></div><?php endif; ?></div><?php endforeach; ?>
    <?php if (!$upcoming): ?><p class="muted"><?= e($sf->t('no_events')) ?></p><?php endif; ?></section></div>
<script src="<?= e($sf->asset('teams.js')) ?>?v=<?= e($sf->manifest['version']) ?>"></script>
