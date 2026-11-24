<?php /** @var \Sysfera\Plugin $sf @var \SysferaStoragerent\Ctx $ctx @var string $lang @var array $l @var array $card @var array $d @var bool $owner @var bool $signedIn @var bool $key */
require __DIR__ . '/_nav.php'; $F = \SysferaStoragerent\Offers::FIELDS; $blocks = []; foreach ($F as $k => $def) $blocks[$def[0]][] = $k;
$mapId = 'srMapOffer'; $mapKey = (string)$sf->setting('yandex_key'); $guest = !$signedIn; $regOpen = !empty($regOpen); ?>
<p><a href="<?= e($sf->url('/offers')) ?>">← <?= e($sf->t('offers_list')) ?></a></p>
<?php $cards = [$card]; require __DIR__ . '/_map.php'; ?>
<div class="card"><div class="row sr-between"><div><h2 style="margin:0"><?= e($l['name']) ?> <?php if ($l['status'] !== 'published'): ?><span class="badge warn"><?= e($sf->t('os_' . $l['status'])) ?></span><?php endif; ?></h2>
  <p class="muted"><?= e(trim($l['city'] . ($l['street'] !== '' ? ', ' . $l['street'] : ''), ', ')) ?></p></div>
  <?php if ($owner): ?><a class="btn" href="<?= e($sf->url('/offers/' . (int)$l['id'] . '/edit')) ?>"><?= e($sf->t('edit')) ?></a><?php endif; ?></div>
  <p><b><?= e(sr_num($l['area'])) ?> m²</b> · <b><?= e(sr_m($l['price_m2'], $lang)) ?></b> / m² · <?php foreach (['video' => '📹', 'parking' => '🅿', 'h24' => '24/7', 'security' => '🛡'] as $k => $ic): if (!empty($card['services'][$k])): ?><span title="<?= e($sf->t('svc_' . $k)) ?>"><?= $ic ?></span> <?php endif; endforeach; ?></p>
  <?php if ($l['photos']): ?><div class="sr-photos"><?php foreach ($l['photos'] as $p): ?><img src="<?= e(url('/p/storagerent/offers/photo/' . (int)$p['id'])) ?>" alt="" loading="lazy"><?php endforeach; ?></div><?php endif; ?></div>
<?php for ($b = 1; $b <= 7; $b++): $rows = []; foreach ($blocks[$b] ?? [] as $k) { $v = $d[$k] ?? ''; $t = $F[$k][1]; if ($t === 'b') { if ($v) $rows[] = [$k, $sf->t('yes')]; } elseif ($t === 'n') { if ((float)$v > 0) $rows[] = [$k, sr_num($v)]; } elseif ((string)$v !== '' && !($b === 6 && !empty($d['access_24h']))) $rows[] = [$k, (string)$v]; }
  if ($b === 6 && !empty($d['access_24h'])) array_unshift($rows, ['access_24h', $sf->t('yes')]);
  if (!$rows) continue; ?>
<div class="card"><h3><?= e($sf->t('blk_' . $b)) ?></h3><dl class="sr-dl"><?php foreach ($rows as [$k, $v]): ?><dt><?= e($sf->t('of_' . $k)) ?></dt><dd><?= nl2br(e($v)) ?></dd><?php endforeach; ?></dl></div>
<?php endfor; ?>
<?php if (!$signedIn): ?><div class="card"><?= e($sf->t('offer_contact_cta')) ?> <a class="btn primary" href="<?= e(url('/register')) ?>"><?= e($sf->t('register')) ?></a></div><?php elseif (!$owner && $l['contact_name'] !== ''): ?><div class="card"><?= e($sf->t('offer_contact')) ?>: <b><?= e($l['contact_name']) ?></b></div><?php endif; ?>
<?php if ($owner): ?><div class="card sr-inline"><?php foreach (['published' => 'offer_publish', 'draft' => 'offer_unpublish', 'archived' => 'offer_archive'] as $s => $key2): if ($l['status'] === $s) continue; ?><form method="post" action="<?= e($sf->url('/offers/' . (int)$l['id'] . '/do')) ?>"><?= csrf_field() ?><input type="hidden" name="do" value="<?= e($s) ?>"><button class="btn"><?= e($sf->t($key2)) ?></button></form><?php endforeach; ?>
  <form method="post" action="<?= e($sf->url('/offers/' . (int)$l['id'] . '/do')) ?>" onsubmit="return confirm('<?= e($sf->t('confirm_delete')) ?>')"><?= csrf_field() ?><input type="hidden" name="do" value="delete"><button class="btn danger"><?= e($sf->t('delete')) ?></button></form></div><?php endif; ?>
