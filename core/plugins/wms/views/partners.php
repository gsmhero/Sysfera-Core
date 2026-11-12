<?php /** @var \Sysfera\Plugin $sf @var string $tab @var array $rows @var string $role @var string $q @var array $counts */
use SysferaWms\W;
include __DIR__ . '/_nav.php'; ?>
<div class="page-h"><h1><?= e($sf->t('nav_partners')) ?> <small class="muted"><?= count($rows) ?></small></h1></div>
<div class="hint"><?= e($sf->t('partners_hint')) ?></div>

<form class="bar" method="get">
  <div class="seg"><?php foreach (['' => 'all', 'supplier' => 'role_supplier', 'customer' => 'role_customer'] as $k => $l): ?><a class="<?= $role === $k ? 'on' : '' ?>" href="<?= e($sf->url('/partners') . ($k !== '' ? '?role=' . $k : '')) ?>"><?= e($sf->t($l)) ?></a><?php endforeach; ?></div>
  <input name="q" value="<?= e($q) ?>" placeholder="<?= e($sf->t('search_partners')) ?>"><input type="hidden" name="role" value="<?= e($role) ?>"><button class="btn"><?= e($sf->t('find')) ?></button>
</form>

<?php if (!$rows): ?><div class="card empty"><h3><?= e($sf->t('no_partners')) ?></h3><p><?= e($sf->t('no_partners_hint')) ?></p></div>
<?php else: ?>
<div class="card flush"><table class="tbl">
  <thead><tr><th><?= e($sf->t('col_name')) ?></th><th><?= e($sf->t('contacts')) ?></th><th><?= e($sf->t('role')) ?></th><th class="num"><?= e($sf->t('type_purchase')) ?></th><th class="num"><?= e($sf->t('type_sale')) ?></th></tr></thead>
  <tbody><?php foreach ($rows as $r): $c = $counts[$r['id']] ?? []; ?>
    <tr><td><b><?= e($r['name']) ?></b><?php if ($r['company']): ?><br><small class="muted"><?= e($r['company']) ?></small><?php endif; ?></td>
      <td class="muted"><?= e(trim(($r['phone'] ?? '') . ' ' . ($r['email'] ?? ''))) ?: '—' ?></td>
      <td><form method="post" action="<?= e($sf->url('/partners/roles')) ?>" class="inline roles"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
        <?php foreach (['supplier', 'customer'] as $ro): ?><label class="chk sm"><input type="checkbox" name="roles[]" value="<?= $ro ?>" <?= in_array($ro, $r['roles'], true) ? 'checked' : '' ?> onchange="this.form.submit()"> <?= e($sf->t('role_' . $ro)) ?></label><?php endforeach; ?></form></td>
      <td class="num"><?= isset($c['purchase']) ? (int)$c['purchase']['n'] . ' · ' . e(W::money($c['purchase']['s'])) : '<span class="muted">—</span>' ?></td>
      <td class="num"><?= isset($c['sale']) ? (int)$c['sale']['n'] . ' · ' . e(W::money($c['sale']['s'])) : '<span class="muted">—</span>' ?></td></tr>
  <?php endforeach; ?></tbody></table></div>
<?php endif; ?>

<h2><?= e($sf->t('new_partner')) ?></h2>
<form class="card" method="post"><?= csrf_field() ?>
  <div class="cols">
    <label class="fld"><span><?= e($sf->t('col_name')) ?> *</span><input name="name" required maxlength="190"></label>
    <label class="fld"><span><?= e($sf->t('company')) ?></span><input name="company" maxlength="190"></label>
    <label class="fld"><span><?= e($sf->t('kind')) ?></span><select name="kind"><option value="person"><?= e($sf->t('kind_person')) ?></option><option value="company"><?= e($sf->t('kind_company')) ?></option></select></label>
  </div>
  <div class="cols">
    <label class="fld"><span><?= e($sf->t('phone')) ?></span><input name="phone" maxlength="60"></label>
    <label class="fld"><span>Email</span><input name="email" type="email" maxlength="190"></label>
    <label class="fld"><span><?= e($sf->t('address')) ?></span><input name="address" maxlength="255"></label>
  </div>
  <div class="row"><label class="chk"><input type="checkbox" name="roles[]" value="supplier"> <?= e($sf->t('role_supplier')) ?></label><label class="chk"><input type="checkbox" name="roles[]" value="customer" checked> <?= e($sf->t('role_customer')) ?></label></div>
  <button class="btn primary"><?= e($sf->t('add')) ?></button>
  <p class="muted sm"><?= e($sf->t('partners_shared')) ?></p>
</form>
