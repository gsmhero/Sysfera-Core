<?php /** @var \Sysfera\Plugin $sf @var string $tab @var \SysferaContracts\Ctx $me @var int $unread @var string $lang */
use SysferaContracts\Svc;
$tabs = ['list' => ['/', 'nav_list', true, 0], 'mine' => ['/mine', 'nav_mine', true, 0], 'invoices' => ['/invoices', 'nav_invoices', true, 0], 'notices' => ['/notices', 'nav_notices', true, $unread]];
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
<?php $babOn = $babOn ?? false; $isAdm = !empty($platformAdmin); $goForm = fn(string $label, array $hid = [], string $cls = '') => '<form method="post" action="' . e($sf->url('/bablito/go')) . '" class="inline">' . csrf_field() . implode('', array_map(fn($n, $v) => '<input type="hidden" name="' . e($n) . '" value="' . e((string)$v) . '">', array_keys($hid), $hid)) . '<button class="linkbtn ' . $cls . '">' . e($label) . '</button></form>'; ?>
<nav class="ct-tabs"><?php foreach ($tabs as $tabKey => [$tabPath, $tabLabel, $tabShow, $tabN]): if (!$tabShow) continue; ?><a href="<?= e($sf->url($tabPath)) ?>" class="<?= $tab === $tabKey ? 'on' : '' ?>"><?= e($sf->t($tabLabel)) ?><?= $tabN ? ' <b class="ct-n">' . (int)$tabN . '</b>' : '' ?></a><?php endforeach; ?>
<span class="ct-nav-r"><a class="btn sm primary ct-new" href="<?= e($sf->url('/new')) ?>">+ <?= e($sf->t('contract_new')) ?></a>
  <a class="ct-ico" href="<?= e($sf->url('/notices')) ?>" title="<?= e($sf->t('nav_notices')) ?>" aria-label="<?= e($sf->t('nav_notices')) ?>"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 10-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 01-3.4 0"/></svg><?php if ($unread > 0): ?><span class="ct-bell-n"><?= $unread > 99 ? '99+' : (int)$unread ?></span><?php endif; ?></a>
  <a class="ct-ico" href="<?= e($sf->url('/mine?tab=watch')) ?>" title="<?= e($sf->t('mine_watch')) ?>" aria-label="<?= e($sf->t('mine_watch')) ?>"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></a>
  <details class="ct-menu"><summary class="ct-ico" aria-label="Menu"><svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><rect x="3" y="5" width="18" height="2.4"/><rect x="3" y="10.8" width="18" height="2.4"/><rect x="3" y="16.6" width="18" height="2.4"/></svg></summary>
    <div class="ct-menu-pop"><a href="<?= e($sf->url('/')) ?>"><?= e($sf->t('nav_list')) ?></a><a href="<?= e($sf->url('/new')) ?>"><?= e($sf->t('contract_new')) ?></a><a href="<?= e($sf->url('/mine')) ?>"><?= e($sf->t('nav_mine')) ?></a><a href="<?= e($sf->url('/invoices')) ?>"><?= e($sf->t('nav_invoices')) ?></a><a href="<?= e($sf->url('/mine?tab=watch')) ?>"><?= e($sf->t('mine_watch')) ?></a>
      <a href="<?= e(url('/profile')) ?>"><?= e($sf->t('nav_profile')) ?></a>
      <?php if ($babOn): ?><?= $goForm($sf->t('open_bablito')) ?><?php endif; ?>
      <?php if ($isAdm): ?><a href="<?= e($sf->url('/admin')) ?>"><?= e($sf->t('nav_admin')) ?></a><?php endif; ?></div></details></span></nav>
