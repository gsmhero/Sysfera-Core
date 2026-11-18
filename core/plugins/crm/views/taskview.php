<?php /** @var \Sysfera\Plugin $sf @var array $task @var ?array $curContact @var ?array $deal @var string $assignee @var string $listBack @var array $prof @var int $nowTs */
require __DIR__ . '/_nav.php'; $lang = \Sysfera\I18n::lang(); $ty = \Sysfera\TaskTypes::get(crm_task_types($sf, true), (string)$task['type'], 'todo');
$st = \SysferaCrm\Pipe::taskState($task['due_at'], $task['done_at'], $nowTs); $me = (int)$sf->auth()->id();
$data = (array)(json_decode((string)($task['type_data'] ?? ''), true) ?: []); $self = '/p/crm/task/' . (int)$task['id'] . '?back=' . rawurlencode($listBack); ?>
<div class="page-h"><div><a class="btn sm" href="<?= e(url($listBack)) ?>">← <?= e($sf->t('tasks')) ?></a><h1 style="margin:2px 0 0"><?= e($task['title']) ?></h1></div>
  <span class="row"><?php if (!empty($prof['edit'])): ?>
    <form method="post" action="<?= e($sf->url('/task/' . (int)$task['id'] . '/toggle')) ?>"><?= csrf_field() ?><input type="hidden" name="back" value="<?= e($self) ?>"><button class="btn"><?= e($sf->t($task['done_at'] ? 'reopen' : 'complete')) ?></button></form>
    <a class="btn primary" href="<?= e($sf->url('/task/' . (int)$task['id'] . '/edit?back=' . rawurlencode($listBack))) ?>"><?= e(t('att.edit')) ?></a><?php endif; ?></span></div>
<div class="card crm-tview">
  <div class="cols">
    <div class="fld"><span><?= e($sf->t('f_type')) ?></span><div><?= \Sysfera\TaskTypes::svg($ty['icon'], 15) ?> <?= e(\Sysfera\TaskTypes::label($ty, $lang)) ?></div></div>
    <div class="fld"><span><?= e($sf->t('f_status')) ?></span><div><?= e($sf->t($task['done_at'] ? 'tst_done' : 'tst_open')) ?><?php if ($st === 'overdue'): ?> · <b style="color:var(--bad)"><?= e($sf->t('g_overdue')) ?></b><?php endif; ?></div></div>
    <div class="fld"><span><?= e($sf->t('f_due')) ?></span><div><?= e($task['due_at'] ? crm_dt($task['due_at']) : '—') ?></div></div>
    <div class="fld"><span><?= e($sf->t('f_assignee')) ?></span><div><?= e($assignee !== '' ? $assignee : '—') ?></div></div>
    <?php if ($curContact): ?><div class="fld"><span><?= e($sf->t('f_contact')) ?></span><div><a href="<?= e($sf->url('/contact/' . (int)$curContact['id'])) ?>"><?= e($curContact['name']) ?></a></div></div><?php endif; ?>
    <?php if ($deal): ?><div class="fld"><span><?= e($sf->t('f_deal')) ?></span><div><a href="<?= e($sf->url('/deal/' . (int)$deal['id'])) ?>"><?= e($deal['title']) ?></a></div></div><?php endif; ?>
    <?php foreach ($data as $k => $v): $fl = null; foreach ($ty['fields'] as $ff) if ($ff['key'] === $k) $fl = $ff; if (!$fl || $v === '' || !is_scalar($v)) continue; ?>
      <div class="fld"><span><?= e(\Sysfera\TaskTypes::fieldLabel($fl, $lang)) ?></span><div><?php if (in_array($fl['kind'], ['url', 'shop'], true)): ?><a href="<?= e((string)$v) ?>" target="_blank" rel="noopener noreferrer"><?= e((string)$v) ?></a><?php elseif ($fl['kind'] === 'email'): ?><a href="mailto:<?= e((string)$v) ?>"><?= e((string)$v) ?></a><?php elseif ($fl['kind'] === 'phone'): ?><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', (string)$v)) ?>"><?= e((string)$v) ?></a><?php else: ?><?= nl2br(e((string)$v)) ?><?php endif; ?></div></div>
    <?php endforeach; ?>
  </div>
  <?php if (!empty($task['notes'])): ?><div class="att-blk"><h4><?= e($sf->t('f_notes')) ?></h4><p><?= nl2br(e($task['notes'])) ?></p></div><?php endif; ?>
  <?php $report = (string)($task['report'] ?? ''); $links = \Sysfera\Attach::decode($task['links'] ?? null); $docIds = \Sysfera\Attach::decode($task['wdocs'] ?? null); $fileIds = \Sysfera\Attach::decode($task['tfiles'] ?? null); $missionIds = \Sysfera\Attach::decode($task['tmissions'] ?? null); require dirname(__DIR__, 3) . '/views/_attach_view.php'; ?>
</div>
<?php require __DIR__ . '/_taskcomments.php'; ?>
