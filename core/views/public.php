<?php
/** The frame of every public page. @var string $title @var string $content @var ?array $user @var string $siteName @var bool $open @var array $flashes @var string $description @var bool $wide @var string $nav current section @var bool $links the public sections are open to this visitor */
$langs = \Sysfera\I18n::languages(); $cur = \Sysfera\I18n::lang();
?><!doctype html>
<html lang="<?= e($cur) ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title) ?> — <?= e($siteName) ?></title>
<?php if ($description !== ''): ?><meta name="description" content="<?= e($description) ?>">
<?php endif; ?><link rel="icon" type="image/svg+xml" href="<?= e(url('/assets/sysfera-logo.svg')) ?>">
<link rel="stylesheet" href="<?= e(url('/assets/sysfera.css')) ?>?v=<?= e(SF_VERSION) ?>">
</head><body class="land">
<header class="land-top"><div class="land-w">
  <a class="wordmark" href="<?= e(url('/')) ?>"><?= sf_logo() ?></a>
  <nav><?php if ($links): foreach (['components' => 'land.nav_components', 'how' => 'land.nav_how'] as $k => $label): ?><a class="<?= $nav === $k ? 'on' : '' ?>" href="<?= e(url('/' . $k)) ?>"><?= e(t($label)) ?></a><?php endforeach; endif; ?>
    <span class="langs"><?php foreach ($langs as $c => $n): ?><a class="<?= $c === $cur ? 'on' : '' ?>" href="<?= e(url('/lang/' . $c)) ?>"><?= e(strtoupper($c)) ?></a><?php endforeach; ?></span>
    <?php if ($user): ?><a class="btn primary" href="<?= e(url('/')) ?>"><?= e(t('nav.dashboard')) ?></a>
    <?php else: ?><a class="btn" href="<?= e(url('/login')) ?>"><?= e(t('auth.login')) ?></a>
    <?php if ($open): ?><a class="btn primary" href="<?= e(url('/register')) ?>"><?= e(t('auth.register')) ?></a><?php endif; endif; ?></nav>
</div></header>
<main class="<?= $wide ? '' : 'land-w land-main' ?>">
<?php foreach ($flashes as [$ft, $fm]): ?><div class="flash <?= e($ft) ?>"><?= e($fm) ?></div><?php endforeach; ?>
<?= $content ?>
</main>
<footer class="land-foot"><div class="land-w"><span>© <?= date('Y') ?> <?= e($siteName) ?> · Sysfera <?= e(SF_VERSION) ?></span><?php if (!$user): ?><a href="<?= e(url('/login')) ?>"><?= e(t('auth.login')) ?></a><?php endif; ?></div></footer>
<script src="<?= e(url('/assets/sysfera.js')) ?>?v=<?= e(SF_VERSION) ?>"></script>
</body></html>
