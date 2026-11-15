<?php /** @var \Sysfera\Plugin $sf @var list<array> $rows @var int $nowTs @var string $backTo @var array $prof @var bool $showLinks */
$showLinks = $showLinks ?? false; ?>
<div class="crm-tasks"><?php foreach ($rows as $t): $st = \SysferaCrm\Pipe::taskState($t['due_at'], $t['done_at'], $nowTs); ?>
  <div class="crm-task <?= e($st) ?>">
    <form method="post" action="<?= e($sf->url('/task/' . (int)$t['id'] . '/toggle')) ?>"><?= csrf_field() ?><input type="hidden" name="back" value="<?= e($backTo) ?>">
      <button class="crm-chk" title="<?= e($sf->t($t['done_at'] ? 'reopen' : 'complete')) ?>"><?= $t['done_at'] ? '✓' : '' ?></button></form>
    <div class="grow"><b><?= e($t['title']) ?></b> <small class="muted">· <?php $ty = \Sysfera\TaskTypes::get(crm_task_types($sf, true), (string)$t['type'], 'todo'); echo e(trim($ty['icon'] . ' ' . \Sysfera\TaskTypes::label($ty, \Sysfera\I18n::lang()))); ?></small>
      <?php foreach ((array)(json_decode((string)($t['type_data'] ?? ''), true) ?: []) as $k => $v): $fl = null; foreach ($ty['fields'] as $ff) if ($ff['key'] === $k) $fl = $ff; if (!$fl || $v === '') continue; ?><div class="muted sm"><?= e(\Sysfera\TaskTypes::fieldLabel($fl, \Sysfera\I18n::lang())) ?>: <?php if (in_array($fl['kind'], ['url', 'shop'], true)): ?><a href="<?= e($v) ?>" target="_blank" rel="noopener noreferrer"><?= e($v) ?></a><?php elseif ($fl['kind'] === 'email'): ?><a href="mailto:<?= e($v) ?>"><?= e($v) ?></a><?php elseif ($fl['kind'] === 'phone'): ?><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $v)) ?>"><?= e($v) ?></a><?php else: ?><?= e($v) ?><?php endif; ?></div><?php endforeach; ?>
      <?php if (!empty($t['notes'])): ?><div class="muted sm"><?= e($t['notes']) ?></div><?php endif; ?>
      <?php if ($showLinks): ?><div class="muted sm"><?php if (!empty($t['deal_title'])): ?><a href="<?= e($sf->url('/deal/' . (int)$t['deal_id'])) ?>"><?= e($t['deal_title']) ?></a> <?php endif; ?><?php if (!empty($t['contact_name'])): ?><a href="<?= e($sf->url('/contact/' . (int)$t['contact_id'])) ?>"><?= e($t['contact_name']) ?></a><?php endif; ?></div><?php endif; ?></div>
    <span class="crm-due"><?= e(crm_dt($t['due_at'])) ?></span>
    <?php if (!empty($t['assignee_name'])): ?><i class="crm-av" title="<?= e($t['assignee_name']) ?>"><?= e(crm_initials($t['assignee_name'])) ?></i><?php endif; ?>
    <?php if (!empty($prof['delete'])): ?><form method="post" action="<?= e($sf->url('/task/' . (int)$t['id'] . '/delete')) ?>" data-confirm="<?= e($sf->t('confirm_delete')) ?>"><?= csrf_field() ?><input type="hidden" name="back" value="<?= e($backTo) ?>"><button class="crm-x" title="<?= e($sf->t('delete')) ?>">×</button></form><?php endif; ?>
  </div><?php endforeach; ?>
  <?php if (!$rows): ?><p class="muted" style="text-align:center;margin:18px 0"><?= e($sf->t('no_tasks')) ?></p><?php endif; ?></div>
