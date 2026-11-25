<?php /** @var \Sysfera\Plugin $sf @var \SysferaStoragerent\Ctx $ctx @var string $lang @var array $l @var array $card @var array $d @var bool $owner @var bool $signedIn @var bool $key */
require __DIR__ . '/_nav.php'; $F = \SysferaStoragerent\Offers::FIELDS; $blocks = []; foreach ($F as $k => $def) $blocks[$def[0]][] = $k;
$mapId = 'srMapOffer'; $mapKey = (string)($mapKey ?? $sf->setting('yandex_key')); $guest = !$signedIn; $regOpen = !empty($regOpen); ?>
<p><a href="<?= e($sf->url('/offers')) ?>">← <?= e($sf->t('offers_list')) ?></a></p>
<?php $cards = [$card]; $noFav = true; require __DIR__ . '/_map.php'; unset($noFav); ?>
<div class="card"><div class="row sr-between"><div><h2 style="margin:0"><?= e($l['name']) ?> <?php if ($l['status'] !== 'published'): ?><span class="badge warn"><?= e($sf->t('os_' . $l['status'])) ?></span><?php endif; ?></h2>
  <p class="muted"><?= e(trim($l['city'] . ($l['street'] !== '' ? ', ' . $l['street'] : ''), ', ')) ?></p></div>
  <div class="sr-actions">
    <?php if ($signedIn && $l['status'] === 'published'): $fref = 'L' . (int)$l['id']; ?><?= sr_fav($sf, $fref, !empty($favs[$fref]), $sf->url('/offers/' . (int)$l['id'])) ?>
      <?php if (empty($isAuthor)): ?><button type="button" class="btn primary sr-contact-btn" data-sr-contact="srContact"<?= empty($messenger) ? ' disabled title="' . e($sf->t('contact_off')) . '"' : '' ?>><?= sr_icon('chat') ?><span><?= e($sf->t('contact_lessor')) ?></span></button><?php endif; ?>
    <?php elseif (!$signedIn): ?><?= sr_fav($sf, '', false, '', true) ?><?php endif; ?>
    <?php if ($owner): ?><a class="btn" href="<?= e($sf->url('/offers/' . (int)$l['id'] . '/edit')) ?>"><?= e($sf->t('edit')) ?></a><?php endif; ?></div></div>
  <p><b><?= e(sr_num($l['area'])) ?> m²</b> · <b><?= e(sr_m($l['price_m2'], $lang)) ?></b> / m² · <?php foreach (['video', 'parking', 'h24', 'security'] as $k): if (!empty($card['services'][$k])): ?><span class="sr-svc-i" title="<?= e($sf->t('svc_' . $k)) ?>"><?= sr_icon($k, $sf->t('svc_' . $k)) ?></span> <?php endif; endforeach; ?></p>
  <?php if ($l['photos']): ?><div class="sr-photos" data-close="<?= e($sf->t('lb_close')) ?>" data-prev="<?= e($sf->t('lb_prev')) ?>" data-next="<?= e($sf->t('lb_next')) ?>"><?php foreach ($l['photos'] as $p): ?><img src="<?= e(url('/p/storagerent/offers/photo/' . (int)$p['id'])) ?>" alt="" loading="lazy"><?php endforeach; ?></div><?php endif; ?></div>
<?php for ($b = 1; $b <= 7; $b++): $rows = []; foreach ($blocks[$b] ?? [] as $k) { $v = $d[$k] ?? ''; $t = $F[$k][1]; if ($t === 'b') { if ($v) $rows[] = [$k, $sf->t('yes')]; } elseif ($t === 'n') { if ((float)$v > 0) $rows[] = [$k, sr_num($v)]; } elseif ((string)$v !== '' && !($b === 6 && !empty($d['access_24h']))) $rows[] = [$k, (string)$v]; }
  if ($b === 6 && !empty($d['access_24h'])) array_unshift($rows, ['access_24h', $sf->t('yes')]);
  if (!$rows) continue; ?>
<div class="card"><h3><?= e($sf->t('blk_' . $b)) ?></h3><dl class="sr-dl"><?php foreach ($rows as [$k, $v]): ?><dt><?= e($sf->t('of_' . $k)) ?></dt><dd><?= nl2br(e($v)) ?></dd><?php endforeach; ?></dl></div>
<?php endfor; ?>
<?php if (!$signedIn): ?><div class="card"><?= e($sf->t('offer_contact_cta')) ?><?= !empty($hasContacts) ? ' ' . e($sf->t('contacts_locked')) : '' ?> <a class="btn primary" href="<?= e(url('/register')) ?>"><?= e($sf->t('register')) ?></a></div>
<?php elseif ($l['contact_name'] !== '' || !empty($hasContacts)): $tel = preg_replace('/[^\d+]/', '', (string)($d['contact_phone'] ?? '')); ?>
<div class="card sr-contacts"><h3><?= e($sf->t('blk_9')) ?></h3><dl class="sr-dl">
  <?php if ($l['contact_name'] !== ''): ?><dt><?= e($sf->t('of_lessor')) ?></dt><dd><?= e($l['contact_name']) ?></dd><?php endif; ?>
  <?php if (($d['contact_person'] ?? '') !== ''): ?><dt><?= e($sf->t('of_contact_person')) ?></dt><dd><?= e($d['contact_person']) ?></dd><?php endif; ?>
  <?php if (($d['contact_phone'] ?? '') !== ''): ?><dt><?= e($sf->t('of_contact_phone')) ?></dt><dd><?= $tel !== '' ? '<a href="tel:' . e($tel) . '">' . e($d['contact_phone']) . '</a>' : e($d['contact_phone']) ?></dd><?php endif; ?>
  <?php if (($d['contact_email'] ?? '') !== ''): ?><dt><?= e($sf->t('of_contact_email')) ?></dt><dd><a href="mailto:<?= e($d['contact_email']) ?>"><?= e($d['contact_email']) ?></a></dd><?php endif; ?>
  <?php if (($d['contact_hours'] ?? '') !== ''): ?><dt><?= e($sf->t('of_contact_hours')) ?></dt><dd><?= e($d['contact_hours']) ?></dd><?php endif; ?>
  <?php if (($d['contact_text'] ?? '') !== ''): ?><dt><?= e($sf->t('of_contact_text')) ?></dt><dd><?= nl2br(e($d['contact_text'])) ?></dd><?php endif; ?>
</dl></div>
<?php endif; ?>
<?php if ($signedIn && empty($isAuthor) && $l['status'] === 'published' && !empty($messenger)): ?>
<dialog id="srContact" class="sr-dialog" aria-labelledby="srContactT"><form method="post" action="<?= e($sf->url('/offers/' . (int)$l['id'] . '/contact')) ?>"><?= csrf_field() ?>
  <h3 id="srContactT"><?= e($sf->t('contact_lessor')) ?></h3><p class="muted sm"><?= e($sf->t('contact_hint')) ?></p>
  <textarea name="text" rows="6" required minlength="2" maxlength="1500"><?= e($sf->t('contact_default', ['name' => $l['name']])) ?></textarea>
  <div class="sr-dialog-act"><button class="btn primary"><?= e($sf->t('contact_send')) ?></button> <button type="button" class="btn" data-sr-close><?= e($sf->t('cancel')) ?></button></div></form></dialog>
<?php endif; ?>
<?php if ($owner): ?><div class="card sr-inline"><?php foreach (['published' => 'offer_publish', 'draft' => 'offer_unpublish', 'archived' => 'offer_archive'] as $s => $key2): if ($l['status'] === $s) continue; ?><form method="post" action="<?= e($sf->url('/offers/' . (int)$l['id'] . '/do')) ?>"><?= csrf_field() ?><input type="hidden" name="do" value="<?= e($s) ?>"><button class="btn"><?= e($sf->t($key2)) ?></button></form><?php endforeach; ?>
  <form method="post" action="<?= e($sf->url('/offers/' . (int)$l['id'] . '/do')) ?>" onsubmit="return confirm('<?= e($sf->t('confirm_delete')) ?>')"><?= csrf_field() ?><input type="hidden" name="do" value="delete"><button class="btn danger"><?= e($sf->t('delete')) ?></button></form></div><?php endif; ?>
<script src="<?= e($sf->asset('storagerent.js')) ?>?v=<?= e($sf->manifest['version']) ?>" defer></script>
