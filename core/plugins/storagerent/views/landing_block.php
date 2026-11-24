<?php /** Public block of the landing page: the map of offers for guests. @var \Sysfera\Plugin $sf @var array $L @var string $lang @var array $cards @var string $key @var bool $open */
$t = fn(string $k) => $L[$k] ?? $k;
if (!$cards) return; ?>
<section class="sr-landing" style="max-width:1100px;margin:32px auto;padding:0 16px">
  <link rel="stylesheet" href="<?= e($sf->asset('storagerent.css')) ?>?v=<?= e($sf->manifest['version']) ?>">
  <h2><?= e($t('landing_title')) ?></h2><p class="muted"><?= e($t('landing_sub')) ?></p>
  <?php
  $sfView = new class($sf, $L) { public array $manifest; public function __construct(private $sf, private array $L) { $this->manifest = $sf->manifest; }
      public function t(string $k, array $v = []): string { $s = $this->L[$k] ?? $k; foreach ($v as $a => $b) $s = str_replace('{' . $a . '}', (string)$b, $s); return $s; }
      public function url(string $p): string { return $this->sf->url($p); } public function asset(string $f): string { return $this->sf->asset($f); } public function setting(string $k, string $d = ''): string { return $this->sf->setting($k, $d); } };
  $sf2 = $sf; $sf = $sfView; $mapId = 'srMapLanding'; $mapKey = $key; $guest = true; $regOpen = $open; require __DIR__ . '/_map.php'; $sf = $sf2; ?>
</section>
