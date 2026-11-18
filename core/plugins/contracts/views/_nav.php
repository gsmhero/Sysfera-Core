<?php /** @var \Sysfera\Plugin $sf @var string $tab @var \SysferaContracts\Ctx $me @var int $unread @var string $lang */
use SysferaContracts\Svc;
$tabs = ['list' => ['/', 'nav_list', true, 0], 'mine' => ['/mine', 'nav_mine', true, 0], 'invoices' => ['/invoices', 'nav_invoices', true, 0], 'notices' => ['/notices', 'nav_notices', true, $unread], 'profile' => ['/profile', 'nav_profile', true, 0], 'admin' => ['/admin', 'nav_admin', $me->admin, 0]];
$ctm = fn($n): string => ($s = rtrim(rtrim(number_format((float)$n, 2, '.', ' '), '0'), '.')) === '' ? '0' : $s;
$ctd = fn(?string $d): string => $d ? date('d.m.Y', strtotime($d)) : '';
$ctdt = fn(?string $d): string => $d ? date('d.m.Y H:i', strtotime($d)) : '';
$ctst = fn(string $s): string => match ($s) { 'published', 'fulfilled', 'paid' => 'ok', 'active', 'pending' => 'info', 'paused', 'waiting', 'draft' => 'warn', 'terminated' => 'bad', default => '' };
$ctcat = fn(array $k): string => $lang === 'ru' ? (string)($k['category_ru'] ?? '') : (string)($k['category'] ?? '');
$ctuser = fn(int $id, string $name): string => '<a href="' . e($sf->url('/user/' . $id)) . '">' . e($name) . '</a>';
$ctstars = function (float $avg, int $n): string { $f = (int)round($avg); return '<span class="ct-stars" title="' . e(number_format($avg, 1)) . '">' . str_repeat('★', $f) . str_repeat('☆', 5 - $f) . '</span> <span class="muted">(' . $n . ')</span>'; };
?>
<link rel="stylesheet" href="<?= e($sf->asset('contracts.css')) ?>?v=<?= e($sf->manifest['version']) ?>">
<script src="<?= e($sf->asset('contracts.js')) ?>?v=<?= e($sf->manifest['version']) ?>" defer></script>
<nav class="ct-tabs"><?php foreach ($tabs as $tabKey => [$tabPath, $tabLabel, $tabShow, $tabN]): if (!$tabShow) continue; ?><a href="<?= e($tabKey === 'profile' ? url('/profile') : $sf->url($tabPath)) ?>" class="<?= $tab === $tabKey ? 'on' : '' ?>"><?= e($sf->t($tabLabel)) ?><?= $tabN ? ' <b class="ct-n">' . (int)$tabN . '</b>' : '' ?></a><?php endforeach; ?>
<a class="btn sm primary ct-new" href="<?= e($sf->url('/new')) ?>">+ <?= e($sf->t('contract_new')) ?></a></nav>
