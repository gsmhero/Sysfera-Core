<?php /** @var \Sysfera\Plugin $sf @var array $c @var array $crm @var array $defs @var list<array> $deals @var array $prof @var array $tasks @var array $timeline @var int $nowTs */
require __DIR__ . '/_nav.php'; $backTo = '/p/crm/contact/' . (int)$c['id']; $vals = $crm; ?>
<div class="page-h"><div><a class="muted sm" href="<?= e($sf->url('/contacts')) ?>">← <?= e($sf->t('contacts')) ?></a><h1 style="margin:2px 0 0"><?= e($c['name']) ?></h1>
  <span class="muted"><?= e(implode(' · ', array_filter([$c['company'], $c['email'], $c['phone']]))) ?></span></div></div>
<div class="crm-two"><div>
  <section class="card"><h3><?= e($sf->t('details')) ?></h3>
  <?php if (!empty($prof['edit'])): ?>
  <form method="post" action="<?= e($sf->url('/contact/save')) ?>" class="crm-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
    <div class="cols"><label class="fld"><span><?= e($sf->t('f_name')) ?> *</span><input name="name" value="<?= e($c['name']) ?>" required maxlength="190"></label>
      <label class="fld"><span><?= e($sf->t('f_kind')) ?></span><select name="type"><option value="person"<?= $c['type'] === 'person' ? ' selected' : '' ?>><?= e($sf->t('person')) ?></option><option value="company"<?= $c['type'] === 'company' ? ' selected' : '' ?>><?= e($sf->t('company')) ?></option></select></label>
      <label class="fld"><span>E-mail</span><input type="email" name="email" value="<?= e((string)$c['email']) ?>" maxlength="190"></label>
      <label class="fld"><span><?= e($sf->t('f_phone')) ?></span><input name="phone" value="<?= e((string)$c['phone']) ?>" maxlength="60"></label>
      <label class="fld"><span><?= e($sf->t('f_company')) ?></span><input name="company" value="<?= e((string)$c['company']) ?>" maxlength="190"></label></div>
    <label class="fld"><span><?= e($sf->t('f_address')) ?></span><input name="address" value="<?= e((string)$c['address']) ?>" maxlength="1000"></label>
    <label class="fld"><span><?= e($sf->t('f_notes')) ?></span><textarea name="notes" rows="3" maxlength="5000"><?= e((string)$c['notes']) ?></textarea></label>
    <?php require __DIR__ . '/_fields.php'; ?>
    <button class="btn primary"><?= e($sf->t('save')) ?></button></form>
  <?php else: ?><dl class="crm-dl"><dt>E-mail</dt><dd><?= e($c['email'] ?: '—') ?></dd><dt><?= e($sf->t('f_phone')) ?></dt><dd><?= e($c['phone'] ?: '—') ?></dd><dt><?= e($sf->t('f_notes')) ?></dt><dd><?= nl2br(e((string)$c['notes'])) ?></dd>
    <?php foreach ($defs as $df): ?><dt><?= e($df['label']) ?></dt><dd><?= e(\SysferaCrm\Fields::show($df, $crm[$df['code']] ?? null) ?: '—') ?></dd><?php endforeach; ?></dl><?php endif; ?></section>
  <section class="card"><h3><?= e($sf->t('history')) ?></h3><?php require __DIR__ . '/_timeline.php'; ?></section>
</div><div>
  <section class="card"><h3><?= e($sf->t('deals')) ?></h3>
    <?php foreach ($deals as $d): ?><a class="crm-mini" href="<?= e($sf->url('/deal/' . (int)$d['id'])) ?>"><span><b><?= e($d['title']) ?></b><small class="muted"><span class="crm-dot" style="--c:<?= e($d['stage_color']) ?>"></span><?= e($d['stage_name']) ?></small></span><span><?= e(crm_money($d['amount'], $d['currency'])) ?></span></a><?php endforeach; ?>
    <?php if (!$deals): ?><p class="muted"><?= e($sf->t('none')) ?></p><?php endif; ?></section>
  <section class="card"><h3><?= e($sf->t('tasks')) ?></h3><?php $rows = $tasks; require __DIR__ . '/_tasklist.php'; ?>
    <?php if (!empty($prof['create'])): ?><details class="crm-add"><summary><?= e($sf->t('add_task')) ?></summary><?php $users = []; $dealId = null; $contactId = (int)$c['id']; require __DIR__ . '/_taskform.php'; ?></details><?php endif; ?></section>
  <?php if (!empty($prof['delete'])): ?><form method="post" action="<?= e($sf->url('/contact/' . (int)$c['id'] . '/delete')) ?>" data-confirm="<?= e($sf->t('confirm_delete')) ?>"><?= csrf_field() ?><button class="btn danger"><?= e($sf->t('delete')) ?></button></form><?php endif; ?>
</div></div>
<script src="<?= e($sf->asset('crm.js')) ?>?v=<?= e($sf->manifest['version']) ?>"></script>
