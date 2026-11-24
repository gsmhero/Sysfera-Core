<?php /** @var \Sysfera\Plugin $sf @var \SysferaStoragerent\Ctx $ctx @var string $lang @var array $cards @var bool $signedIn */
require __DIR__ . '/_nav.php';
$mapId = 'srMapPage'; $mapKey = (string)$sf->setting('yandex_key'); $guest = !$signedIn; $regOpen = !empty($regOpen);
require __DIR__ . '/_map.php'; ?>
