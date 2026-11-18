<?php /** @var \Sysfera\Plugin $sf @var array $cats @var array $structure @var int $staleDays  Administrator only. */
use SysferaContracts\Svc;
include __DIR__ . '/_nav.php'; $post = fn(string $what) => e($sf->url('/admin/' . $what)); ?>
<div class="page-h"><h1><?= e($sf->t('nav_admin')) ?></h1></div>
<div class="card"><h3><?= e($sf->t('adm_settings')) ?></h3><form method="post" action="<?= $post('settings') ?>" class="row"><?= csrf_field() ?><label class="ct-lbl"><?= e($sf->t('adm_stale')) ?><input name="stale_days" value="<?= (int)$staleDays ?>" inputmode="numeric" size="5"></label><button class="btn primary"><?= e($sf->t('save')) ?></button></form><p class="muted"><?= e($sf->t('adm_stale_help')) ?></p></div>

<?php /* Bablito.ru connection */ $bab = $bab ?? ['url' => '', 'live' => false, 'key_set' => false, 'key_env' => false, 'ok' => false, 'last' => null, 'links' => 0]; $bp = fn(string $do, string $extra = '') => '<form method="post" action="' . $post('bablito') . '" class="row">' . csrf_field() . '<input type="hidden" name="do" value="' . e($do) . '">' . $extra; ?>
<div class="card"><h3><?= e($sf->t('bab_title')) ?></h3><p class="muted"><?= e($sf->t('bab_help')) ?></p>
  <form method="post" action="<?= $post('bablito') ?>" class="ct-form"><?= csrf_field() ?><input type="hidden" name="do" value="settings">
    <label class="ct-lbl"><?= e($sf->t('bab_url')) ?><input name="url" value="<?= e($bab['url']) ?>" maxlength="200" required></label>
    <label class="ct-lbl"><?= e($sf->t('bab_key')) ?><input type="password" name="key" value="" autocomplete="new-password" placeholder="<?= e($sf->t($bab['key_env'] ? 'bab_key_env' : ($bab['key_set'] ? 'bab_key_set' : 'bab_key_none'))) ?>"></label>
    <?php if ($bab['key_set'] && !$bab['key_env']): ?><label><input type="checkbox" name="clear_key" value="1"> <?= e($sf->t('bab_key_clear')) ?></label><?php endif; ?>
    <label><input type="checkbox" name="live" value="1" <?= $bab['live'] ? 'checked' : '' ?>> <?= e($sf->t('bab_live')) ?></label><p class="muted"><?= e($sf->t('bab_live_help')) ?></p>
    <button class="btn primary"><?= e($sf->t('save')) ?></button></form>
  <?php if ($bab['ok']): ?><div class="row"><?= $bp('test') ?><button class="btn"><?= e($sf->t('bab_test')) ?></button></form><?= $bp('preview') ?><button class="btn"><?= e($sf->t('bab_preview')) ?></button></form><?= $bp('sync') ?><button class="btn primary" <?= $bab['live'] ? '' : 'disabled' ?>><?= e($sf->t('bab_sync')) ?></button></form></div>
  <p class="muted"><?= e(sprintf($sf->t('bab_linked'), (int)$bab['links'])) ?></p><?php else: ?><p class="muted"><?= e($sf->t('bab_not_ready')) ?></p><?php endif; ?>
  <?php if ($l = $bab['last']): $u = $l['users'] ?? []; $s = $l['structure'] ?? []; $pr = $l['profiles'] ?? []; ?>
  <h4><?= e($sf->t(!empty($l['dry']) ? 'bab_last_dry' : 'bab_last')) ?> · <?= e((string)($l['time'] ?? '')) ?> UTC</h4>
  <ul><li><?= e(sprintf($sf->t('bab_r_struct'), (int)($s['sections_new'] ?? 0), (int)($s['sections_updated'] ?? 0), (int)($s['fields_new'] ?? 0), (int)($s['fields_updated'] ?? 0))) ?></li>
    <?php $cn = $l['contracts'] ?? null; if (is_array($cn)): ?><li><?= e(isset($cn['error']) ? $sf->t('err_' . $cn['error']) : sprintf($sf->t('bab_r_contracts'), (int)$cn['total'], (int)$cn['new'], (int)$cn['updated'], (int)$cn['removed'])) ?></li><?php endif; ?>
    <?php $pu = $l['pushed'] ?? null; if (is_array($pu)): ?><li><?= e(isset($pu['error']) ? $sf->t('err_' . $pu['error']) : sprintf($sf->t('bab_r_pushed'), (int)$pu['new'], (int)$pu['updated'], (int)$pu['removed'], (int)$pu['skipped'])) ?></li><?php endif; ?>
    <li><?= e(sprintf($sf->t('bab_r_users'), (int)($u['linked'] ?? 0), (int)($u['would_link'] ?? 0), count($u['conflicts'] ?? []))) ?></li>
    <li><?= e(sprintf($sf->t('bab_r_profiles'), (int)($pr['ok'] ?? 0), (int)($pr['pulled'] ?? 0), (int)($pr['pushed'] ?? 0), count($pr['failed'] ?? []))) ?></li></ul>
  <?php if (!empty($u['unmatched'])): ?><h4><?= e($sf->t('bab_unmatched')) ?></h4><p class="muted"><?= e($sf->t('bab_unmatched_help')) ?></p>
    <?php foreach ($u['unmatched'] as $m): ?><?= $bp('create', '<input type="hidden" name="user_id" value="' . (int)$m['id'] . '">') ?><span><?= e($m['name']) ?> &lt;<?= e($m['email']) ?>&gt;</span><button class="btn sm"><?= e($sf->t($bab['live'] ? 'bab_create' : 'bab_create_dry')) ?></button></form><?php endforeach; ?><?php endif; ?>
  <?php endif; ?></div>
