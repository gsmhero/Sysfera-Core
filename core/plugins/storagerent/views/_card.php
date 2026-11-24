<?php /** The storage card: header (photo or a map stub), address (city, street), name, total area, price per m², service icons. @var \Sysfera\Plugin $sf @var array $c @var string $lang */
require_once __DIR__ . '/_h.php';
$href = $c['url'] ?? $sf->url('/offers/' . (int)$c['id']); $addr = trim($c['city'] . ($c['street'] !== '' ? ', ' . $c['street'] : ''), ', ');
$icons = ['video' => 'svc_video', 'parking' => 'svc_parking', 'h24' => 'svc_h24', 'security' => 'svc_security']; ?>
<article class="sr-card">
  <a class="sr-card-head" href="<?= e($href) ?>"<?= !empty($c['origin']) ? ' rel="noopener" target="_blank"' : '' ?>>
    <?php if ($c['photo'] !== ''): ?><img src="<?= e($c['photo']) ?>" alt="" loading="lazy"><?php else: ?><span class="sr-card-ph" aria-hidden="true"><?= sr_icon('warehouse') ?></span><?php endif; ?>
  </a>
  <div class="sr-card-body">
    <div class="sr-addr"><?= e($addr) ?></div>
    <h3><a href="<?= e($href) ?>"<?= !empty($c['origin']) ? ' rel="noopener" target="_blank"' : '' ?>><?= e($c['name']) ?></a></h3>
    <div class="sr-facts"><span><?= e(sr_num($c['area'])) ?> m²</span><span><b><?= e(sr_m($c['price_m2'], $lang)) ?></b> / m²</span></div>
    <div class="sr-svc"><?php foreach ($icons as $k => $key): if (!empty($c['services'][$k])): ?><span title="<?= e($sf->t($key)) ?>"><?= sr_icon($k, $sf->t($key)) ?></span><?php endif; endforeach; ?></div>
    <?php if (isset($ctx) && $ctx->uid >= 0 && !isset($noFav)): $srRef = \SysferaStoragerent\Offers::ref($c); $srBack = (string)($_SERVER['REQUEST_URI'] ?? ''); if (!str_starts_with($srBack, '/p/storagerent/') || str_contains($srBack, '//')) $srBack = $sf->url('/offers'); ?><div class="sr-card-act"><?= sr_fav($sf, $srRef, !empty($favs[$srRef]), $srBack, $ctx->uid <= 0) ?></div><?php endif; ?>
    <?php if (!empty($c['origin'])): ?><div class="muted sm"><?= e($sf->t('offer_from_site', ['site' => parse_url((string)$c['origin'], PHP_URL_HOST) ?: ''])) ?></div><?php endif; ?>
  </div>
</article>
