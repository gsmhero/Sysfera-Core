<?php /** @var \Sysfera\Plugin $sf @var array $items */ ?>
<link rel="stylesheet" href="<?= e($sf->asset('activity.css')) ?>?v=<?= e($sf->manifest['version']) ?>">
<div class="row between"><b><?= e($sf->t('title')) ?></b><a class="sm" href="<?= e($sf->url('/')) ?>"><?= e($sf->t('open_feed')) ?> →</a></div>
<?php if (!$items): ?><p class="muted sm"><?= e($sf->t('w_none')) ?></p>
<?php else: $list = function () use ($items): string { $h = ''; foreach ($items as $p) $h .= '<div class="act-tk-i"><b>' . e($p['title']) . '</b> <span>' . e($p['body']) . '</span> <small class="muted">— ' . e($p['name']) . ', ' . e($p['at']) . '</small></div>'; return $h; }; ?>
<div class="act-tk" style="--n:<?= count($items) ?>"><div class="act-tk-in"><?= $list() ?><?php if (count($items) > 1) echo $list(); ?></div></div>
<?php endif; ?>
