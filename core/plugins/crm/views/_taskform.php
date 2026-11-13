<?php /** @var \Sysfera\Plugin $sf @var array $users @var array $prof @var ?int $dealId @var ?int $contactId @var string $backTo */
$dealId = $dealId ?? null; $contactId = $contactId ?? null; ?>
<form method="post" action="<?= e($sf->url('/task/save')) ?>" class="crm-form crm-taskform"><?= csrf_field() ?>
  <input type="hidden" name="back" value="<?= e($backTo ?? '/p/crm/tasks') ?>">
  <?php if ($dealId): ?><input type="hidden" name="deal_id" value="<?= (int)$dealId ?>"><?php endif; ?>
  <?php if ($contactId): ?><input type="hidden" name="contact_id" value="<?= (int)$contactId ?>"><?php endif; ?>
  <div class="cols">
    <label class="fld"><span><?= e($sf->t('f_type')) ?></span><select name="type"><?php foreach (\SysferaCrm\Pipe::TASK_TYPES as $t): ?><option value="<?= $t ?>"><?= e($sf->t('tt_' . $t)) ?></option><?php endforeach; ?></select></label>
    <label class="fld" style="grid-column:span 2"><span><?= e($sf->t('f_title')) ?> *</span><input name="title" maxlength="190" required></label>
    <label class="fld"><span><?= e($sf->t('f_due')) ?></span><input type="date" name="due_date" value="<?= e(date('Y-m-d')) ?>"></label>
    <label class="fld"><span><?= e($sf->t('f_time')) ?></span><input type="time" name="due_time"></label>
    <?php if (!empty($prof['reassign'])): ?><label class="fld"><span><?= e($sf->t('f_assignee')) ?></span><select name="assignee_id"><?php foreach ($users as $u): ?><option value="<?= (int)$u['id'] ?>"<?= (int)$u['id'] === $sf->auth()->id() ? ' selected' : '' ?>><?= e($u['name']) ?></option><?php endforeach; ?></select></label><?php endif; ?>
  </div>
  <label class="fld"><span><?= e($sf->t('f_notes')) ?></span><input name="notes" maxlength="3000"></label>
  <button class="btn primary"><?= e($sf->t('add_task')) ?></button>
</form>
