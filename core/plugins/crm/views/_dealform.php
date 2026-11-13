<?php /** @var ?array $d deal being edited (null = new) @var int $pid @var array $stages @var array $defs @var array $users @var array $prof @var string $cur @var ?array $contact */
$d = $d ?? null; $contact = $contact ?? null; $vals = $d['extra'] ?? []; ?>
<form method="post" action="<?= e($sf->url('/deal/save')) ?>" class="crm-form"><?= csrf_field() ?>
  <?php if ($d): ?><input type="hidden" name="id" value="<?= (int)$d['id'] ?>"><?php else: ?><input type="hidden" name="pipeline_id" value="<?= (int)$pid ?>"><?php endif; ?>
  <label class="fld"><span><?= e($sf->t('f_title')) ?> *</span><input name="title" value="<?= e($d['title'] ?? '') ?>" maxlength="190" required></label>
  <div class="cols">
    <label class="fld"><span><?= e($sf->t('f_amount')) ?></span><input name="amount" inputmode="decimal" value="<?= e(isset($d['amount']) ? (string)(float)$d['amount'] : '') ?>"></label>
    <label class="fld"><span><?= e($sf->t('f_currency')) ?></span><input name="currency" value="<?= e($d['currency'] ?? $cur) ?>" maxlength="3" style="text-transform:uppercase"></label>
    <label class="fld"><span><?= e($sf->t('f_stage')) ?></span><select name="stage_id"><?php foreach ($stages as $s): ?><option value="<?= (int)$s['id'] ?>"<?= (int)($d['stage_id'] ?? 0) === (int)$s['id'] ? ' selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?></select></label>
    <label class="fld"><span><?= e($sf->t('f_close')) ?></span><input type="date" name="expected_close" value="<?= e((string)($d['expected_close'] ?? '')) ?>"></label>
  </div>
  <div class="cols">
    <div class="fld crm-pick"><span><?= e($sf->t('f_contact')) ?></span>
      <input type="hidden" name="contact_id" value="<?= (int)($d['contact_id'] ?? 0) ?: '' ?>">
      <input type="text" name="new_contact" autocomplete="off" placeholder="<?= e($sf->t('f_contact_hint')) ?>" value="<?= e($contact['name'] ?? '') ?>" data-find="<?= e($sf->url('/contacts/find')) ?>"><div class="crm-sug"></div></div>
    <?php if (!empty($prof['reassign'])): ?><label class="fld"><span><?= e($sf->t('f_owner')) ?></span><select name="owner_id"><?php foreach ($users as $u): ?><option value="<?= (int)$u['id'] ?>"<?= (int)($d['owner_id'] ?? $sf->auth()->id()) === (int)$u['id'] ? ' selected' : '' ?>><?= e($u['name']) ?></option><?php endforeach; ?></select></label><?php endif; ?>
    <label class="fld"><span><?= e($sf->t('f_tags')) ?></span><input name="tags" value="<?= e($d['tags'] ?? '') ?>" maxlength="400" placeholder="vip, b2b"></label>
  </div>
  <?php require __DIR__ . '/_fields.php'; ?>
  <button class="btn primary"><?= e($sf->t('save')) ?></button>
</form>
