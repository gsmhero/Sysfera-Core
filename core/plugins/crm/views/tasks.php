<?php /** @var \Sysfera\Plugin $sf @var list<array> $rows @var string $show @var array $users @var array $prof @var int $nowTs @var int $assignee */
require __DIR__ . '/_nav.php'; $backTo = '/p/crm/tasks?show=' . $show; $showLinks = true; $dealId = null; $contactId = null;
$groups = ['overdue' => [], 'today' => [], 'upcoming' => [], 'none' => [], 'done' => []];
foreach ($rows as $t) $groups[\SysferaCrm\Pipe::taskState($t['due_at'], $t['done_at'], $nowTs)][] = $t; ?>
<div class="page-h"><h1><?= e($sf->t('tasks')) ?></h1><span class="row"><?php if (!empty($prof['export'])): ?><a class="btn" href="<?= e($sf->url('/export/tasks')) ?>">CSV</a><?php endif; ?></span></div>
<div class="crm-seg" style="margin-bottom:12px"><?php foreach (array_merge(['mine'], $prof['scope'] === 'all' ? ['all'] : [], ['done']) as $k): ?><a href="<?= e($sf->url('/tasks?show=' . $k)) ?>" class="<?= $show === $k ? 'on' : '' ?>"><?= e($sf->t('tf_' . $k)) ?></a><?php endforeach; ?></div>
<?php if (!empty($prof['create'])): ?><details class="card crm-add"><summary><b>+ <?= e($sf->t('add_task')) ?></b></summary><?php require __DIR__ . '/_taskform.php'; ?></details><?php endif; ?>
<?php foreach ($groups as $g => $list): if (!$list) continue; ?><h3 class="crm-gh <?= e($g) ?>"><?= e($sf->t('g_' . $g)) ?> <small>(<?= count($list) ?>)</small></h3>
  <div class="card"><?php $rows = $list; require __DIR__ . '/_tasklist.php'; ?></div><?php endforeach; ?>
<?php if (!array_filter($groups)): ?><div class="card empty"><p class="muted"><?= e($sf->t('no_tasks')) ?></p></div><?php endif; ?>
