<?php /** @var \Sysfera\Plugin $sf @var string $tab */
if (!function_exists('crm_money')) {
    function crm_money($v, string $cur = ''): string { return number_format((float)$v, 2, '.', ' ') . ($cur !== '' ? ' ' . $cur : ''); }
    function crm_date(?string $d): string { return $d ? date('d.m.Y', strtotime($d)) : ''; }
    function crm_dt(?string $d): string { return $d ? (substr($d, 11, 5) === '00:00' ? date('d.m.Y', strtotime($d)) : date('d.m.Y H:i', strtotime($d))) : ''; }
    function crm_initials(?string $n): string { return mb_strtoupper(mb_substr(trim((string)$n) ?: '?', 0, 1)); }
}
$tabs = ['dashboard' => ['/', 'menu'], 'deals' => ['/deals', 'deals'], 'contacts' => ['/contacts', 'contacts'], 'tasks' => ['/tasks', 'tasks'], 'reports' => ['/reports', 'reports']];
?><link rel="stylesheet" href="<?= e($sf->asset('crm.css')) ?>?v=<?= e($sf->manifest['version']) ?>">
<nav class="crm-tabs"><?php foreach ($tabs as $k => [$p, $l]): ?><a href="<?= e($sf->url($p)) ?>" class="<?= ($tab ?? '') === $k ? 'on' : '' ?>"><?= e($sf->t($l)) ?></a><?php endforeach; ?>
  <span class="grow"></span>
  <?php if (!empty($prof['import'])): ?><a href="<?= e($sf->url('/import')) ?>" class="<?= ($tab ?? '') === 'import' ? 'on' : '' ?>"><?= e($sf->t('import')) ?></a><?php endif; ?>
  <?php if (!empty($prof['settings']) || $sf->auth()->isAdmin()): ?><a href="<?= e($sf->url('/settings')) ?>" class="<?= ($tab ?? '') === 'settings' ? 'on' : '' ?>"><?= e($sf->t('settings')) ?></a><?php endif; ?></nav>
