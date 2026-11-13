<?php /** @var \Sysfera\Plugin $sf @var list<array> $rows @var int $total @var int $pg @var int $pages @var array $f @var array $prof */
require __DIR__ . '/_nav.php';
$q = fn(array $o) => $sf->url('/contacts?' . http_build_query(array_filter(array_merge(['q' => $f['q'], 'type' => $f['type'], 'page' => $pg], $o), fn($v) => $v !== '' && $v !== null))); ?>
<div class="page-h"><h1><?= e($sf->t('contacts')) ?> <small class="muted">(<?= (int)$total ?>)</small></h1>
  <span class="row"><?php if (!empty($prof['export'])): ?><a class="btn" href="<?= e($sf->url('/export/contacts')) ?>">CSV</a><?php endif; ?>
  <?php if (!empty($prof['create'])): ?><button class="btn primary" type="button" data-toggle="#newcontact">+ <?= e($sf->t('new_contact')) ?></button><?php endif; ?></span></div>

<?php if (!empty($prof['create'])): ?><div class="card" id="newcontact" hidden>
  <form method="post" action="<?= e($sf->url('/contact/save')) ?>" class="crm-form"><?= csrf_field() ?>
    <div class="cols"><label class="fld"><span><?= e($sf->t('f_name')) ?> *</span><input name="name" required maxlength="190"></label>
      <label class="fld"><span><?= e($sf->t('f_kind')) ?></span><select name="type"><option value="person"><?= e($sf->t('person')) ?></option><option value="company"><?= e($sf->t('company')) ?></option></select></label>
      <label class="fld"><span>E-mail</span><input type="email" name="email" maxlength="190"></label><label class="fld"><span><?= e($sf->t('f_phone')) ?></span><input name="phone" maxlength="60"></label>
      <label class="fld"><span><?= e($sf->t('f_company')) ?></span><input name="company" maxlength="190"></label></div>
    <button class="btn primary"><?= e($sf->t('save')) ?></button></form></div><?php endif; ?>

<form method="get" class="card crm-filter"><input name="q" value="<?= e($f['q']) ?>" placeholder="<?= e($sf->t('search')) ?>">
  <select name="type"><option value=""><?= e($sf->t('all_types')) ?></option><option value="person"<?= $f['type'] === 'person' ? ' selected' : '' ?>><?= e($sf->t('person')) ?></option><option value="company"<?= $f['type'] === 'company' ? ' selected' : '' ?>><?= e($sf->t('company')) ?></option></select>
  <button class="btn"><?= e($sf->t('apply')) ?></button></form>

<div class="card" style="padding:0;overflow:auto"><table class="tbl crm-tbl"><thead><tr><th><?= e($sf->t('f_name')) ?></th><th><?= e($sf->t('f_company')) ?></th><th>E-mail</th><th><?= e($sf->t('f_phone')) ?></th><th class="num"><?= e($sf->t('deals')) ?></th></tr></thead><tbody>
<?php foreach ($rows as $c): ?><tr><td><a href="<?= e($sf->url('/contact/' . (int)$c['id'])) ?>"><b><?= e($c['name']) ?></b></a></td><td><?= e($c['company'] ?: '—') ?></td><td><?= e($c['email'] ?: '—') ?></td><td><?= e($c['phone'] ?: '—') ?></td><td class="num"><?= (int)$c['deals'] ?></td></tr><?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="5" class="muted" style="text-align:center;padding:24px"><?= e($sf->t('none')) ?></td></tr><?php endif; ?></tbody></table></div>
<?php if ($pages > 1): ?><p style="text-align:center"><?php if ($pg > 1): ?><a class="btn" href="<?= e($q(['page' => $pg - 1])) ?>">‹</a><?php endif; ?> <span class="muted"><?= $pg ?> / <?= $pages ?></span> <?php if ($pg < $pages): ?><a class="btn" href="<?= e($q(['page' => $pg + 1])) ?>">›</a><?php endif; ?></p><?php endif; ?>
<script src="<?= e($sf->asset('crm.js')) ?>?v=<?= e($sf->manifest['version']) ?>"></script>
