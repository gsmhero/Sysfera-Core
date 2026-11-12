<?php
/** @var string $title @var string $content @var ?array $user @var array $menu @var bool $bare @var string $siteName @var array $flashes */
$icons = [
    'home' => '<path d="M3 11 12 3l9 8M5 10v10h14V10"/>', 'puzzle' => '<path d="M10 4a2 2 0 1 1 4 0v2h4v4h-2a2 2 0 1 0 0 4h2v6H6v-4a2 2 0 1 0-4 0V8h6V4z"/>',
    'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.5a3.5 3.5 0 0 1 0 7M18 14.5a6.5 6.5 0 0 1 3.5 5.5"/>',
    'cog' => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M5 5l2 2M17 17l2 2M19 5l-2 2M7 17l-2 2"/>', 'refresh' => '<path d="M20 11a8 8 0 0 0-14-4L4 9M4 4v5h5M4 13a8 8 0 0 0 14 4l2-2M20 20v-5h-5"/>',
    'dot' => '<circle cx="12" cy="12" r="3"/>',
];
$ico = fn(?string $n) => '<svg class="ico" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . ($icons[$n ?? 'dot'] ?? $icons['dot']) . '</svg>';
$langs = \Sysfera\I18n::languages(); $cur = \Sysfera\I18n::lang();
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'; $baseLen = strlen(\Sysfera\App::basePath()); $path = '/' . trim(substr($path, $baseLen), '/');
?><!doctype html>
<html lang="<?= e($cur) ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title) ?> — <?= e($siteName) ?></title>
<link rel="icon" type="image/svg+xml" href="<?= e(url("/assets/sysfera-logo.svg")) ?>">
<link rel="stylesheet" href="<?= e(url('/assets/sysfera.css')) ?>?v=<?= e(SF_VERSION) ?>">
</head><body class="<?= $bare ? 'bare' : '' ?>">
<?php if ($bare): ?>
<main class="auth"><div class="auth-box"><div class="wordmark"><?= sf_logo() ?></div><?= $content ?>
  <p class="langs"><?php foreach ($langs as $c => $n): ?><a class="<?= $c === $cur ? 'on' : '' ?>" href="<?= e(url('/lang/' . $c)) ?>"><?= e($n) ?></a><?php endforeach; ?></p></div></main>
<?php else: ?>
<div class="shell">
  <aside class="side">
    <a class="wordmark" href="<?= e(url('/')) ?>"><?= sf_logo() ?></a>
    <div class="site"><?= e($siteName) ?></div>
    <nav>
      <?php foreach ($menu as $m):
          $lab = !empty($m['slug']) ? t($m['slug'] . '.' . $m['label']) : t($m['label']);
          if ($lab === ($m['slug'] ?? '') . '.' . $m['label'] || $lab === $m['label']) $lab = $m['label'];
          $on = $m['path'] === '/' ? $path === '/' : str_starts_with($path, $m['path']); ?>
        <a href="<?= e(url($m['path'])) ?>" class="<?= $on ? 'on' : '' ?>"><?= $ico($m['icon'] ?? null) ?><span><?= e($lab) ?></span></a>
      <?php endforeach; ?>
    </nav>
    <div class="side-foot">
      <a href="<?= e(url('/profile')) ?>" class="who"><span class="av"><?= e(mb_strtoupper(mb_substr($user['name'] ?? '?', 0, 1))) ?></span><span><b><?= e($user['name'] ?? '') ?></b><small><?= e($user['role'] === 'admin' ? t('role.admin') : t('role.user')) ?></small></span></a>
      <p class="langs"><?php foreach ($langs as $c => $n): ?><a class="<?= $c === $cur ? 'on' : '' ?>" href="<?= e(url('/lang/' . $c)) ?>"><?= e(strtoupper($c)) ?></a><?php endforeach; ?></p>
      <form method="post" action="<?= e(url('/logout')) ?>"><?= csrf_field() ?><button class="link"><?= e(t('auth.logout')) ?></button></form>
      <small class="ver">Sysfera <?= e(SF_VERSION) ?></small>
    </div>
  </aside>
  <main class="main">
    <?php foreach ($flashes as [$ft, $fm]): ?><div class="flash <?= e($ft) ?>"><?= e($fm) ?></div><?php endforeach; ?>
    <?= $content ?>
  </main>
</div>
<?php endif; ?>
<script src="<?= e(url('/assets/sysfera.js')) ?>?v=<?= e(SF_VERSION) ?>"></script>
</body></html>
