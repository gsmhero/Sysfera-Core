<?php /** @var \Sysfera\Plugin $sf @var string $tab @var array $row @var bool $agentOk @var array $whs @var \SysferaBb\Sync $sync */
include __DIR__ . '/_nav.php';
$sel = array_map('intval', (array)(json_decode((string)($row['warehouse_ids'] ?? '[]'), true) ?: []));
$sid = (int)($row['seller_id'] ?? 0); ?>
<div class="page-h"><h1><?= e($sf->t('nav_shop')) ?></h1></div>
<div class="hint"><?= e($sf->t('shop_hint')) ?></div>
<?php if (!$agentOk): ?><div class="hint warn"><?= e($sf->t('shop_need_agent')) ?></div><?php endif; ?>
<form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="do" value="save">
  <label class="fld"><span><?= e($sf->t('s_shop')) ?></span><input name="shop_name" maxlength="190" value="<?= e($row['shop_name'] ?? '') ?>" placeholder="<?= e($sf->auth()->user()['name'] ?? '') ?>"><small class="muted"><?= e($sf->t('shop_name_hint')) ?></small></label>
  <div class="fld"><span><?= e($sf->t('s_warehouse')) ?></span>
    <div class="bbwh"><?php foreach ($whs as $i => $w): ?><label class="chk"><input type="checkbox" name="warehouse_ids[]" value="<?= (int)$w['id'] ?>" <?= in_array((int)$w['id'], $sel, true) || (!$sel && $i === 0) ? 'checked' : '' ?>> <?= e($w['name']) ?></label><?php endforeach; ?></div>
    <small class="muted"><?= e($sf->t('s_warehouse_hint')) ?></small></div>
  <div class="row"><button class="btn primary"><?= e($sf->t('save')) ?></button></div>
</form>
<section class="card"><h3><?= e($sf->t('shop_status')) ?></h3>
<?php if ($sid): ?><p><?= e($sf->t('shop_connected', ['id' => $sid])) ?></p>
<?php elseif ($agentOk): ?><p class="muted"><?= e($sf->t('shop_not_yet')) ?></p>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="connect"><button class="btn primary"><?= e($sf->t('shop_connect')) ?></button></form>
<?php endif; ?></section>