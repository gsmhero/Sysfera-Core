<?php /** @var \Sysfera\Plugin $sf @var \SysferaStoragerent\Ctx $ctx @var string $lang @var array $v */
require __DIR__ . '/_nav.php'; $host = (!empty($_SERVER['HTTP_HOST']) ? preg_replace('/[^A-Za-z0-9.\-:]/', '', (string)$_SERVER['HTTP_HOST']) : 'example.org'); ?>
<form method="post" action="<?= e($sf->url('/settings/save')) ?>" class="card sr-form"><?= csrf_field() ?>
  <h3><?= e($sf->t('set_company')) ?></h3><?= sr_in('company_name', $sf->t('set_company_name'), $v['company_name'], 'text', 'maxlength="190"') ?><?= sr_area('company_details', $sf->t('set_company_details'), $v['company_details'], $sf->t('set_company_hint'), 4) ?>
  <h3><?= e($sf->t('set_owners')) ?></h3><?= sr_chk('owners_open', $sf->t('set_owners_open'), !empty($v['owners_open'])) ?><p class="muted sm"><?= e($sf->t('set_owners_help')) ?></p>
  <h3><?= e($sf->t('set_staff')) ?></h3><?php if ($ctx->role === 'admin' && $platformAdmin): ?><?= sr_area('staff', $sf->t('set_staff_list'), $v['staff'], $sf->t('set_staff_help'), 4) ?><?php else: ?><p class="muted"><?= e($sf->t('set_staff_readonly')) ?></p><pre class="sr-pre"><?= e($v['staff']) ?></pre><?php endif; ?>
  <h3><?= e($sf->t('set_notify')) ?></h3><div class="sr-2"><?= sr_in('notify_before', $sf->t('set_before'), $v['before'], 'text', 'maxlength="40"') ?><?= sr_in('notify_after', $sf->t('set_after'), $v['after'], 'text', 'maxlength="40"') ?></div><?= sr_in('notify_expiring', $sf->t('set_expiring'), $v['expiring'], 'text', 'maxlength="40"') ?>
  <?= sr_sel('notify_lang', $sf->t('set_notify_lang'), ['ru' => 'Русский', 'en' => 'English'], $v['notify_lang']) ?><p class="muted sm"><?= e($sf->t('set_notify_help')) ?></p>
  <h3><?= e($sf->t('set_offers')) ?></h3><?= sr_in('yandex_key', $sf->t('set_yandex'), $v['yandex_key'], 'text', 'maxlength="80" autocomplete="off" spellcheck="false"') ?><p class="muted sm"><?= e($sf->t('set_yandex_help')) ?></p><p class="muted sm"><?= e($sf->t(!empty($v['yandex_hub']) ? 'set_yandex_hub_on' : 'set_yandex_hub_off')) ?></p>
  <?= sr_area('peers', $sf->t('set_peers'), $v['peers'], $sf->t('set_peers_help'), 3) ?>
  <h3><?= e($sf->t('set_cron')) ?></h3><p class="muted sm"><?= e($sf->t('set_cron_help')) ?></p><p><code>curl -X POST -H "X-Cron-Token: <?= e($v['cron_token'] !== '' ? $v['cron_token'] : '…') ?>" https://<?= e($host) ?><?= e(url('/api/storagerent/v1/cron')) ?></code></p>
  <?= sr_chk('new_token', $sf->t('set_new_token'), 0) ?>
  <p><button class="btn primary"><?= e($sf->t('save')) ?></button></p></form>
