<?php /** @var bool $open registration is open @var string $siteName */
$icons = [
    'box' => '<path d="M21 8l-9-5-9 5v8l9 5 9-5V8z"/><path d="M3 8l9 5 9-5M12 13v8"/>', 'link' => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
    'chat' => '<path d="M21 12a8 8 0 0 1-11.6 7.1L3 21l1.9-5.4A8 8 0 1 1 21 12z"/>', 'bell' => '<path d="M6 16V11a6 6 0 0 1 12 0v5l2 2H4l2-2zM10 21h4"/>',
    'news' => '<path d="M4 5h13v14H6a2 2 0 0 1-2-2V5zM17 9h3v8a2 2 0 0 1-2 2M8 9h5M8 13h5"/>', 'calc' => '<rect x="5" y="3" width="14" height="18" rx="2"/><path d="M8 7h8M8 12h2M12 12h2M16 12h0M8 16h2M12 16h2M16 16h0"/>',
    'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.5a3.5 3.5 0 0 1 0 7M18 14.5a6.5 6.5 0 0 1 3.5 5.5"/>', 'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
];
$ico = fn(string $n) => '<svg class="ico" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">' . ($icons[$n] ?? '') . '</svg>';
$langs = \Sysfera\I18n::languages(); $cur = \Sysfera\I18n::lang();
$all = \Sysfera\Landing::components(); ?><!doctype html>
<html lang="<?= e($cur) ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($siteName) ?> — <?= e(t('land.tagline')) ?></title>
<meta name="description" content="<?= e(t('land.lead')) ?>">
<link rel="icon" type="image/svg+xml" href="<?= e(url('/assets/sysfera-logo.svg')) ?>">
<link rel="stylesheet" href="<?= e(url('/assets/sysfera.css')) ?>?v=<?= e(SF_VERSION) ?>">
</head><body class="land">
<header class="land-top"><div class="land-w">
  <a class="wordmark" href="<?= e(url('/')) ?>"><?= sf_logo() ?></a>
  <nav><a href="#components"><?= e(t('land.nav_components')) ?></a><a href="#how"><?= e(t('land.nav_how')) ?></a>
    <span class="langs"><?php foreach ($langs as $c => $n): ?><a class="<?= $c === $cur ? 'on' : '' ?>" href="<?= e(url('/lang/' . $c)) ?>"><?= e(strtoupper($c)) ?></a><?php endforeach; ?></span>
    <a class="btn" href="<?= e(url('/login')) ?>"><?= e(t('auth.login')) ?></a>
    <?php if ($open): ?><a class="btn primary" href="<?= e(url('/register')) ?>"><?= e(t('auth.register')) ?></a><?php endif; ?></nav>
</div></header>

<section class="land-hero"><div class="land-w">
  <h1><?= e(t('land.h1')) ?></h1>
  <p class="land-lead"><?= e(t('land.lead')) ?></p>
  <p class="land-cta"><a class="btn primary big" href="<?= e(url($open ? '/register' : '/login')) ?>"><?= e(t($open ? 'land.cta_register' : 'land.cta_login')) ?></a>
    <?php if ($open): ?><a class="btn big" href="<?= e(url('/login')) ?>"><?= e(t('auth.login')) ?></a><?php endif; ?></p>
  <ul class="land-pts"><li><?= e(t('land.pt1')) ?></li><li><?= e(t('land.pt2')) ?></li><li><?= e(t('land.pt3')) ?></li></ul>
</div></section>

<section id="components" class="land-sec"><div class="land-w">
  <h2><?= e(t('land.comp_h')) ?></h2><p class="muted"><?= e(t('land.comp_p')) ?></p>
  <div class="land-grid">
  <?php foreach ($all as $c): ?><article class="land-card <?= $c['state'] === 'soon' ? 'soon' : '' ?>">
    <div class="land-ic"><?= $ico($c['icon']) ?></div>
    <h3><?= e(t('land.c.' . $c['slug'])) ?> <?php if ($c['state'] === 'soon'): ?><span class="badge"><?= e(t('land.soon')) ?></span><?php endif; ?></h3>
    <p><?= e(t('land.c.' . $c['slug'] . '_d')) ?></p></article><?php endforeach; ?>
  </div>
</div></section>

<section id="how" class="land-sec alt"><div class="land-w">
  <h2><?= e(t('land.how_h')) ?></h2>
  <ol class="land-steps"><li><b><?= e(t('land.s1')) ?></b><span><?= e(t('land.s1d')) ?></span></li><li><b><?= e(t('land.s2')) ?></b><span><?= e(t('land.s2d')) ?></span></li><li><b><?= e(t('land.s3')) ?></b><span><?= e(t('land.s3d')) ?></span></li></ol>
  <p class="land-cta"><a class="btn primary big" href="<?= e(url($open ? '/register' : '/login')) ?>"><?= e(t($open ? 'land.cta_register' : 'land.cta_login')) ?></a></p>
</div></section>

<footer class="land-foot"><div class="land-w"><span>© <?= date('Y') ?> <?= e($siteName) ?> · Sysfera <?= e(SF_VERSION) ?></span><a href="<?= e(url('/login')) ?>"><?= e(t('auth.login')) ?></a></div></footer>
</body></html>
