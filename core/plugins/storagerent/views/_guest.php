<?php /** @var \Sysfera\Plugin $sf @var string $content @var string $title @var bool $open */
$langs = \Sysfera\I18n::languages(); $cur = \Sysfera\I18n::lang(); $siteName = \Sysfera\App::get()->setting('site_name', 'Sysfera'); ?><!doctype html>
<html lang="<?= e($cur) ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title) ?> — <?= e($siteName) ?></title>
<link rel="icon" type="image/svg+xml" href="<?= e(url('/assets/sysfera-logo.svg')) ?>">
<link rel="stylesheet" href="<?= e(url('/assets/sysfera.css')) ?>?v=<?= e(SF_VERSION) ?>">
<?= function_exists('sf_accent') ? sf_accent() : '' ?>
</head><body class="land">
<header class="land-top"><div class="land-w">
  <a class="wordmark" href="<?= e(url('/')) ?>"><?= sf_logo() ?></a>
  <nav><span class="langs"><?php foreach ($langs as $c => $n): ?><a class="<?= $c === $cur ? 'on' : '' ?>" href="<?= e(url('/lang/' . $c)) ?>"><?= e(strtoupper($c)) ?></a><?php endforeach; ?></span>
    <a class="btn" href="<?= e(url('/login')) ?>"><?= e(t('auth.login')) ?></a><?php if ($open): ?><a class="btn primary" href="<?= e(url('/register')) ?>"><?= e(t('auth.register')) ?></a><?php endif; ?></nav>
</div></header>
<main class="land-w" style="padding:20px 0 40px"><?= $content ?></main>
</body></html>
