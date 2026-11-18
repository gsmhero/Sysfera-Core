<?php /** @var \Sysfera\Plugin $sf @var array $task @var ?array $curContact @var ?array $deal @var string $assignee @var string $listBack @var array $prof @var int $nowTs */
require __DIR__ . '/_nav.php'; $lang = \Sysfera\I18n::lang(); $ty = \Sysfera\TaskTypes::get(crm_task_types($sf, true), (string)$task['type'], 'todo');
$st = \SysferaCrm\Pipe::taskState($task['due_at'], $task['done_at'], $nowTs); $me = (int)$sf->auth()->id();
$data = (array)(json_decode((string)($task['type_data'] ?? ''), true) ?: []); $self = '/p/crm/task/' . (int)$task['id'] . '?back=' . rawurlencode($listBack); ?>
<?php
if (!function_exists('crm_ti')) {
    /** small one-colour line icons for the task page */
    function crm_ti(string $n, int $px = 16): string {
        static $d = ['cal' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>', 'user' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
            'brief' => '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>', 'phone' => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/>',
            'mail' => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/>', 'link' => '<path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/>',
            'text' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h6"/>', 'done' => '<circle cx="12" cy="12" r="10"/><path d="m8 12 3 3 5-6"/>',
            'users' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/>'];
        return '<svg class="crm-ti" viewBox="0 0 24 24" width="' . $px . '" height="' . $px . '" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($d[$n] ?? '') . '</svg>';
    }
}
$tc = preg_match('/^#[0-9a-fA-F]{6}$/', (string)($ty['color'] ?? '')) ? $ty['color'] : '#4f46e5'; $over = $st === 'overdue';
$initials = $assignee !== '' ? mb_strtoupper(mb_substr($assignee, 0, 1) . (preg_match('/\s(\S)/u', $assignee, $mm) ? $mm[1] : '')) : '?'; ?>
<div class="crm-th" style="--tc:<?= e($tc) ?>">
  <a class="btn sm" href="<?= e(url($listBack)) ?>">← <?= e($sf->t('tasks')) ?></a>
  <div class="crm-th-row">
    <span class="crm-th-ic"><?= \Sysfera\TaskTypes::svg($ty['icon'], 26) ?></span>
    <div class="crm-th-t">
      <div class="crm-th-chips"><span class="crm-chip"><?= e(\Sysfera\TaskTypes::label($ty, $lang)) ?></span>
        <span class="badge <?= $task['done_at'] ? 'ok' : 'info' ?>"><?= e($sf->t($task['done_at'] ? 'tst_done' : 'tst_open')) ?></span><?php if ($over): ?><span class="badge bad"><?= e($sf->t('g_overdue')) ?></span><?php endif; ?></div>
      <h1><?= e($task['title']) ?></h1>
    </div>
    <span class="row crm-th-act"><?php if (!empty($prof['edit'])): ?>
      <form method="post" action="<?= e($sf->url('/task/' . (int)$task['id'] . '/toggle')) ?>"><?= csrf_field() ?><input type="hidden" name="back" value="<?= e($self) ?>"><button class="btn"><?= e($sf->t($task['done_at'] ? 'reopen' : 'complete')) ?></button></form>
      <a class="btn primary" href="<?= e($sf->url('/task/' . (int)$task['id'] . '/edit?back=' . rawurlencode($listBack))) ?>"><?= e(t('att.edit')) ?></a><?php endif; ?></span>
  </div>
  <div class="crm-facts">
    <div class="crm-fact<?= $over ? ' bad' : '' ?>"><i><?= crm_ti('cal') ?></i><div><small><?= e($sf->t('f_due')) ?></small><b><?= e($task['due_at'] ? crm_dt($task['due_at']) : '—') ?></b></div></div>
    <div class="crm-fact"><i class="av"><?= e($initials) ?></i><div><small><?= e($sf->t('f_assignee')) ?></small><b><?= e($assignee !== '' ? $assignee : '—') ?></b></div></div>
    <?php if ($curContact): ?><div class="crm-fact"><i><?= crm_ti('user') ?></i><div><small><?= e($sf->t('f_contact')) ?></small><b><a href="<?= e($sf->url('/contact/' . (int)$curContact['id'])) ?>"><?= e($curContact['name']) ?></a></b></div></div><?php endif; ?>
    <?php if ($deal): ?><div class="crm-fact"><i><?= crm_ti('brief') ?></i><div><small><?= e($sf->t('f_deal')) ?></small><b><a href="<?= e($sf->url('/deal/' . (int)$deal['id'])) ?>"><?= e($deal['title']) ?></a></b></div></div><?php endif; ?>
    <?php foreach ($data as $k => $v): $fl = null; foreach ($ty['fields'] as $ff) if ($ff['key'] === $k) $fl = $ff; if (!$fl || $v === '' || !is_scalar($v)) continue;
      $ik = $fl['kind'] === 'phone' ? 'phone' : ($fl['kind'] === 'email' ? 'mail' : (in_array($fl['kind'], ['url', 'shop'], true) ? 'link' : 'text')); ?>
      <div class="crm-fact"><i><?= crm_ti($ik) ?></i><div><small><?= e(\Sysfera\TaskTypes::fieldLabel($fl, $lang)) ?></small><b><?php if (in_array($fl['kind'], ['url', 'shop'], true)): ?><a href="<?= e((string)$v) ?>" target="_blank" rel="noopener noreferrer"><?= e((string)$v) ?></a><?php elseif ($fl['kind'] === 'email'): ?><a href="mailto:<?= e((string)$v) ?>"><?= e((string)$v) ?></a><?php elseif ($fl['kind'] === 'phone'): ?><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', (string)$v)) ?>"><?= e((string)$v) ?></a><?php else: ?><?= nl2br(e((string)$v)) ?><?php endif; ?></b></div></div>
    <?php endforeach; ?>
  </div>
</div>
<?php $hasBody = !empty($task['notes']) || (string)($task['report'] ?? '') !== '' || \Sysfera\Attach::decode($task['links'] ?? null) || \Sysfera\Attach::decode($task['wdocs'] ?? null) || \Sysfera\Attach::decode($task['tfiles'] ?? null) || \Sysfera\Attach::decode($task['tmissions'] ?? null); if ($hasBody): ?><div class="card crm-tbody"><?php endif; ?>
  <?php if (!empty($task['notes'])): ?><div class="att-blk crm-notes"><h4><?= crm_ti('text', 15) ?> <?= e($sf->t('f_notes')) ?></h4><p><?= nl2br(e($task['notes'])) ?></p></div><?php endif; ?>
  <?php $report = (string)($task['report'] ?? ''); $links = \Sysfera\Attach::decode($task['links'] ?? null); $docIds = \Sysfera\Attach::decode($task['wdocs'] ?? null); $fileIds = \Sysfera\Attach::decode($task['tfiles'] ?? null); $missionIds = \Sysfera\Attach::decode($task['tmissions'] ?? null); require dirname(__DIR__, 3) . '/views/_attach_view.php'; ?>
<?php if ($hasBody): ?></div><?php endif; ?>
<?php require __DIR__ . '/_taskcomments.php'; ?>
