<?php /** @var \Sysfera\Plugin $sf @var array $g {next, recent, today} @var array $colors */
$cl_name = fn(array $c): string => trim($c['name'] . ' ' . $c['surname']);
$cl_act = ['call' => 'M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z',
    'email' => 'M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2zM22 6l-10 7L2 6', 'messenger' => 'M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z', 'success' => 'M22 11.1V12a10 10 0 1 1-5.9-9.1M22 4 12 14l-3-3'];
$cl_ico = fn(string $a): string => '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' . ($cl_act[$a] ?? $cl_act['call']) . '"/></svg>';
$cl_dot = fn(string $s): string => '<i class="clw-dot" style="background:' . e(preg_match('/^#[0-9a-f]{6}$/i', $colors[$s] ?? '') ? $colors[$s] : '#6b7280') . '"></i>';
?>
<link rel="stylesheet" href="<?= e($sf->asset('contactlog.css')) ?>?v=<?= e($sf->manifest['version']) ?>">
<div class="row between"><b><?= e($sf->t('w_title')) ?></b><a class="sm" href="<?= e($sf->url('/')) ?>"><?= e($sf->t('w_open')) ?> →</a></div>
<h4 class="clw-h"><?= e($sf->t('w_upcoming')) ?></h4>
<?php if (!$g['next']): ?><p class="muted sm"><?= e($sf->t('w_none_next')) ?></p><?php else: ?>
<ul class="clw-l"><?php foreach ($g['next'] as $c): $late = $c['next_call'] < $g['today']; $today = $c['next_call'] === $g['today']; ?>
  <li><?= $cl_dot($c['status']) ?><span class="clw-n"><b><?= e($cl_name($c)) ?></b><small class="muted"><?= e($sf->t('st_' . $c['status'])) ?><?= $c['theme'] !== '' ? ' · ' . e($c['theme']) : '' ?></small></span>
    <span class="clw-d<?= $late ? ' bad' : ($today ? ' warn' : '') ?>"><?= e($sf->t($late ? 'w_overdue' : ($today ? 'w_today' : 'w_on'), ['date' => $c['next_call']])) ?></span>
    <span class="clw-a"><?php if ($c['mobile'] !== ''): ?><a class="btn sm" href="tel:<?= e(preg_replace('/[^0-9+]/', '', $c['mobile'])) ?>" title="<?= e($sf->t('w_call')) ?>"><?= $cl_ico('call') ?></a><?php endif; ?><?php if ($c['email'] !== ''): ?><a class="btn sm" href="mailto:<?= e($c['email']) ?>" title="<?= e($sf->t('w_email')) ?>"><?= $cl_ico('email') ?></a><?php endif; ?></span></li>
<?php endforeach; ?></ul><?php endif; ?>
<h4 class="clw-h"><?= e($sf->t('w_recent')) ?></h4>
<?php if (!$g['recent']): ?><p class="muted sm"><?= e($sf->t('w_none_recent')) ?></p><?php else: ?>
<ul class="clw-l"><?php foreach ($g['recent'] as $c): ?>
  <li><?= $cl_dot($c['status']) ?><span class="clw-n"><b><?= e($cl_name($c)) ?></b><small class="muted"><?= e($sf->t('st_' . $c['status'])) ?></small></span>
    <span class="clw-d"><span class="clw-i"><?= $cl_ico($c['last_action']) ?></span> <?= e(date('Y-m-d H:i', (int)$c['last_at'])) ?></span></li>
<?php endforeach; ?></ul><?php endif; ?>