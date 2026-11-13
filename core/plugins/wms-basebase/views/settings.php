<?php /** @var \Sysfera\Plugin $sf @var string $tab @var array $whs @var \SysferaBb\Client $client @var \SysferaBb\Sync $sync @var callable $cfg */
include __DIR__ . '/_nav.php';
$site = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'your-site') . \Sysfera\App::basePath();
$hasSecret = $cfg('secret', '') !== ''; ?>
<div class="page-h"><h1><?= e($sf->t('nav_settings')) ?></h1></div>

<div class="cols2 wide-left"><div>
<section class="card"><h3>0. AGENT_KEY</h3><p class="muted sm"><?= e($sf->t('s_key_hint')) ?></p>
  <code class="bbcode"><?= e($cfg('agent_key', '')) ?></code></section>
<form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="do" value="save">
  <h3>1. <?= e($sf->t('s_connection')) ?></h3>
  <p class="muted sm"><?= e($sf->t('s_connection_hint')) ?></p>
  <div class="cols">
    <label class="fld"><span>Agent ID</span><input name="agent_id" value="<?= e($cfg('agent_id', '')) ?>" inputmode="numeric"></label>
    <label class="fld"><span><?= e($sf->t('api_address')) ?></span><input name="api_url" value="<?= e($cfg('api_url', 'https://www.basebase.ru/api/v1')) ?>"></label>
  </div>
  <label class="fld"><span>API secret</span><input name="secret" type="password" autocomplete="off" placeholder="<?= $hasSecret ? e($sf->t('secret_saved')) : '' ?>"></label>
  <h3>2. <?= e($sf->t('s_seller')) ?></h3>
  <div class="cols">
    <label class="fld"><span>Seller ID</span><input name="seller_id" value="<?= e($cfg('seller_id', '')) ?>" inputmode="numeric"></label>
    <div class="fld"><span><?= e($sf->t('s_warehouse')) ?></span><?php $sel = array_map('intval', (array)(json_decode($cfg('warehouse_ids', '[]'), true) ?: [])) ?: ((int)$cfg('warehouse_id', '0') ? [(int)$cfg('warehouse_id', '0')] : []); ?>
      <div class="bbwh"><?php foreach ($whs as $i => $w): ?><label class="chk"><input type="checkbox" name="warehouse_ids[]" value="<?= (int)$w['id'] ?>" <?= in_array((int)$w['id'], $sel, true) || (!$sel && $i === 0) ? 'checked' : '' ?>> <?= e($w['name']) ?></label><?php endforeach; ?></div><small class="muted"><?= e($sf->t('s_warehouse_hint')) ?></small></div>
  </div>
  <div class="row"><button class="btn primary"><?= e($sf->t('save')) ?></button></div>
</form>

<?php if ($client->configured()): ?>
<form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="do" value="test"><h3><?= e($sf->t('s_test')) ?></h3><p class="muted sm"><?= e($sf->t('s_test_hint')) ?></p><button class="btn"><?= e($sf->t('s_test_btn')) ?></button></form>

<?php if (!$sync->sellerId()): ?>
<form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="do" value="create_seller"><h3><?= e($sf->t('s_create')) ?></h3><p class="muted sm"><?= e($sf->t('s_create_hint')) ?></p>
  <div class="cols">
    <label class="fld"><span>Email *</span><input name="email" type="email" required></label>
    <label class="fld"><span><?= e($sf->t('s_name')) ?> *</span><input name="name" required maxlength="190"></label>
    <label class="fld"><span><?= e($sf->t('s_shop')) ?> *</span><input name="shop_name" required maxlength="190"></label>
    <label class="fld"><span><?= e($sf->t('phone')) ?></span><input name="phone" maxlength="60"></label>
  </div><button class="btn primary"><?= e($sf->t('s_create_btn')) ?></button></form>
<?php endif; endif; ?>
</div>

<div>
  <section class="card"><h3><?= e($sf->t('s_howto')) ?></h3>
    <ol class="steps-l small"><li><?= e($sf->t('how1')) ?></li><li><?= e($sf->t('how2')) ?></li><li><?= e($sf->t('how3')) ?></li><li><?= e($sf->t('how4')) ?></li></ol></section>
  <section class="card"><h3><?= e($sf->t('s_webhook')) ?></h3>
    <p class="muted sm"><?= e($sf->t('s_webhook_hint')) ?></p>
    <code class="bbcode"><?= e($site) ?>/api/basebase/webhook</code>
    <h3 style="margin-top:14px"><?= e($sf->t('s_cron')) ?></h3>
    <p class="muted sm"><?= e($sf->t('s_cron_hint')) ?></p>
    <code class="bbcode">curl -s "<?= e($site) ?>/api/basebase/poll?token=<?= e($cfg('cron_token', '')) ?>"</code>
    <form method="post" style="margin-top:8px"><?= csrf_field() ?><input type="hidden" name="do" value="token"><button class="btn" data-confirm="<?= e($sf->t('confirm_token')) ?>"><?= e($sf->t('new_token')) ?></button></form>
  </section>
</div></div>
