<?php /** @var \Sysfera\Plugin $sf @var string $tab @var \SysferaHr\Ctx $c @var ?array $me */
$tabs = ['dashboard' => ['/', 'nav_overview', true], 'vacancies' => ['/vacancies', 'nav_vacancies', $c->can('hr.vacancies.manage')], 'employees' => ['/employees', 'nav_employees', $c->can('hr.employees.view')], 'teams' => ['/teams', 'nav_teams', $c->can('hr.employees.view')],
    'leave' => ['/leave', 'nav_leave', true], 'roles' => ['/roles', 'nav_roles', $c->can('hr.roles.manage')], 'access' => ['/access', 'nav_access', $c->can('hr.rbac.manage')], 'payroll' => ['/payroll', 'nav_payroll', $c->can('hr.payroll.run')],
    'audit' => ['/audit', 'nav_audit', $c->can('hr.audit.view')], 'privacy' => ['/privacy', 'nav_privacy', $c->can('hr.consent.manage') || $c->can('hr.export')], 'settings' => ['/settings', 'nav_settings', $c->root]];
?>
<link rel="stylesheet" href="<?= e($sf->asset('hr.css')) ?>?v=<?= e($sf->manifest['version']) ?>">
<nav class="wtabs hr-noprint"><?php foreach ($tabs as $k => [$path, $label, $show]): if (!$show) continue; ?><a href="<?= e($sf->url($path)) ?>" class="<?= $tab === $k ? 'on' : '' ?>"><?= e($sf->t($label)) ?></a><?php endforeach; ?>
<?php if (!empty($me)): ?><a href="<?= e($sf->url('/me')) ?>"><?= e($sf->t('nav_me')) ?></a><?php endif; ?></nav>
<?php
$hrst = function (string $s): string { return match ($s) { 'approved', 'signed', 'published', 'active', 'hired', 'open', 'done' => 'ok', 'pending_manager', 'pending_hr', 'draft', 'unsigned', 'offer', 'interview', 'screening', 'new' => 'warn', 'rejected', 'expired', 'dismissed', 'cancelled', 'closed' => 'bad', default => '' }; };
$money = fn($kop) => number_format(((int)$kop) / 100, 2, '.', ' ');
$tk = function (string $code) use ($sf): string { $t = $sf->t('task_' . $code); return $t === 'hr.task_' . $code ? $code : $t; };
$pl = function (array $l) use ($sf): string { if ($l['kind'] === 'deduction') return $l['label'] !== '' ? $l['label'] : $l['code']; return $sf->t('pl_' . $l['code']); };
