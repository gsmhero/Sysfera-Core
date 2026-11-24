<?php /** The public map of offers (Yandex Maps JS API loaded by assets/storagerent.js with the key from settings) and, without a key or without JavaScript, a plain list.
 * @var \Sysfera\Plugin $sf @var string $lang @var array $cards @var string $mapId @var string $mapKey @var bool $guest @var bool $regOpen */
require_once __DIR__ . '/_h.php';
$pts = []; foreach ($cards as $c) if ($c['lat'] !== null && $c['lon'] !== null) $pts[] = ['name' => $c['name'], 'addr' => trim($c['city'] . ($c['street'] !== '' ? ', ' . $c['street'] : ''), ', '), 'lat' => $c['lat'], 'lon' => $c['lon'], 'area' => sr_num($c['area']), 'price' => sr_m($c['price_m2'], $lang) . ' / m²',
    'url' => $c['url'] ?? $sf->url('/offers/' . (int)$c['id']), 'ext' => !empty($c['origin'])];
$labels = ['open' => $sf->t('map_open'), 'register' => $sf->t($regOpen ? 'map_register' : 'map_login'), 'cta' => $sf->t('map_cta'), 'area' => $sf->t('map_area')];
$cta = $guest ? ($regOpen ? url('/register') : url('/login')) : ''; ?>
<div class="sr-map" id="<?= e($mapId) ?>" data-key="<?= e($mapKey) ?>" data-lang="<?= e($lang === 'en' ? 'en_US' : 'ru_RU') ?>" data-points="<?= e(json_encode($pts, JSON_UNESCAPED_UNICODE)) ?>" data-labels="<?= e(json_encode($labels, JSON_UNESCAPED_UNICODE)) ?>" data-cta="<?= e($cta) ?>">
  <div class="sr-map-canvas" hidden></div>
  <div class="sr-map-fallback"><?php if ($mapKey === ''): ?><p class="muted sm"><?= e($sf->t('map_nokey')) ?></p><?php endif; ?>
    <div class="sr-cards"><?php foreach ($cards as $c): require __DIR__ . '/_card.php'; endforeach; ?></div></div>
</div>
<script src="<?= e($sf->asset('storagerent.js')) ?>?v=<?= e($sf->manifest['version']) ?>" defer></script>
