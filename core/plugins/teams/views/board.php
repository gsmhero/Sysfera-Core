<?php /** @var \Sysfera\Plugin $sf @var array $t @var list<array> $cols @var array $by @var list<array> $people @var array $f @var ?array $edit @var ?array $tview @var string $today */
use SysferaTeams\Tm;
$tab = 'board'; require __DIR__ . '/_nav.php'; $tid = (int)$t['id']; $can = Tm::can($t['role'], 'task'); $adm = Tm::can($t['role'], 'channel');
$act = fn(string $p) => e($sf->url('/t/' . $tid . $p)); $prio = ['0' => 'prio_low', '1' => 'prio_mid', '2' => 'prio_high']; ?>
<form method="get" class="card tm-filter"><input name="q" value="<?= e($f['q']) ?>" placeholder="<?= e($sf->t('search')) ?>"><label class="chk"><input type="checkbox" name="mine" value="1"<?= $f['mine'] ? ' checked' : '' ?>> <?= e($sf->t('only_mine')) ?></label>
  <button class="btn sm"><?= e($sf->t('apply')) ?></button></form>

<?php if (!empty($tview)): $v = $tview; ?><section class="card tm-view"><div class="row between"><h2 style="margin:0"><?= e($v['title']) ?></h2><span class="row"><?php if ($can): ?><a class="btn primary" href="<?= $act('/board?edit=' . (int)$v['id']) ?>"><?= e(t('att.edit')) ?></a><?php endif; ?><a class="btn" href="<?= $act('/board') ?>"><?= e($sf->t('cancel')) ?></a></span></div>
  <div class="cols" style="margin-top:10px"><div class="fld"><span><?= e($sf->t('f_column')) ?></span><div><?= e((string)$v['cname']) ?></div></div><div class="fld"><span><?= e($sf->t('f_assignee')) ?></span><div><?= e($v['aname'] ?: '—') ?></div></div><div class="fld"><span><?= e($sf->t('f_due')) ?></span><div><?= e($v['due'] ? date('d/m/Y', strtotime($v['due'])) : '—') ?></div></div><div class="fld"><span><?= e($sf->t('f_prio')) ?></span><div><?= e($sf->t($prio[(string)(int)$v['prio']] ?? 'prio_mid')) ?></div></div></div>
  <?php if (!empty($v['descr'])): ?><div class="att-blk"><h4><?= e($sf->t('f_descr')) ?></h4><p><?= nl2br(e($v['descr'])) ?></p></div><?php endif; ?>
  <?php $report = (string)($v['report'] ?? ''); $links = \Sysfera\Attach::decode($v['links'] ?? null); $docIds = \Sysfera\Attach::decode($v['wdocs'] ?? null); $fileIds = \Sysfera\Attach::decode($v['tfiles'] ?? null); require dirname(__DIR__, 3) . '/views/_attach_view.php'; ?></section><?php endif; ?>

<?php if ($can && empty($tview)): $e = $edit; ?><details class="card tm-add"<?= $e ? ' open' : '' ?>><summary><b>+ <?= e($sf->t($e ? 'edit_task' : 'add_task')) ?></b></summary>
  <form method="post" action="<?= $act('/task/save') ?>"><?= csrf_field() ?><?php if ($e): ?><input type="hidden" name="id" value="<?= (int)$e['id'] ?>"><?php endif; ?>
    <div class="cols"><label class="fld" style="grid-column:span 2"><span><?= e($sf->t('f_title')) ?> *</span><input name="title" value="<?= e($e['title'] ?? '') ?>" required maxlength="190"></label>
      <label class="fld"><span><?= e($sf->t('f_column')) ?></span><select name="col_id"><?php foreach ($cols as $col): ?><option value="<?= (int)$col['id'] ?>"<?= (int)($e['col_id'] ?? 0) === (int)$col['id'] ? ' selected' : '' ?>><?= e($col['name']) ?></option><?php endforeach; ?></select></label>
      <label class="fld"><span><?= e($sf->t('f_assignee')) ?></span><select name="assignee_id"><option value="">—</option><?php foreach ($people as $u): ?><option value="<?= (int)$u['id'] ?>"<?= (int)($e['assignee_id'] ?? 0) === (int)$u['id'] ? ' selected' : '' ?>><?= e($u['name']) ?></option><?php endforeach; ?></select></label>
      <label class="fld"><span><?= e($sf->t('f_due')) ?></span><input type="date" name="due" value="<?= e((string)($e['due'] ?? '')) ?>"></label>
      <label class="fld"><span><?= e($sf->t('f_prio')) ?></span><select name="prio"><?php foreach ($prio as $v => $l): ?><option value="<?= $v ?>"<?= (int)($e['prio'] ?? 1) === (int)$v ? ' selected' : '' ?>><?= e($sf->t($l)) ?></option><?php endforeach; ?></select></label></div>
    <label class="fld"><span><?= e($sf->t('f_descr')) ?></span><textarea name="descr" rows="3" maxlength="4000"><?= e($e['descr'] ?? '') ?></textarea></label>
    <?php $report = (string)($e['report'] ?? ''); $links = \Sysfera\Attach::decode($e['links'] ?? null); $docIds = \Sysfera\Attach::decode($e['wdocs'] ?? null); $fileIds = \Sysfera\Attach::decode($e['tfiles'] ?? null); require dirname(__DIR__, 3) . '/views/_attach_fields.php'; ?>
    <button class="btn primary"><?= e($sf->t('save')) ?></button>
    <?php if ($e): ?><a class="btn" href="<?= $act('/board') ?>"><?= e($sf->t('cancel')) ?></a>
      <button class="btn danger" formaction="<?= $act('/task/' . (int)$e['id'] . '/delete') ?>" formnovalidate data-confirm="<?= e($sf->t('confirm_delete')) ?>"><?= e($sf->t('delete')) ?></button><?php endif; ?></form></details><?php endif; ?>

<div class="tm-board" data-move="<?= $act('/task/move') ?>" data-token="<?= e(csrf_token()) ?>" data-can="<?= $can ? '1' : '0' ?>">
<?php foreach ($cols as $col): $list = $by[(int)$col['id']] ?? []; ?>
  <section class="tm-col" data-col="<?= (int)$col['id'] ?>"><header><b><?= e($col['name']) ?><?= $col['is_done'] ? ' ✓' : '' ?></b><span class="muted"><?= count($list) ?></span></header>
    <div class="tm-cards"><?php foreach ($list as $x): $late = $x['due'] && !$x['done_at'] && $x['due'] < $today; ?>
      <a class="tm-task prio<?= (int)$x['prio'] ?>" href="<?= $act('/board?view=' . (int)$x['id']) ?>" draggable="<?= $can ? 'true' : 'false' ?>" data-id="<?= (int)$x['id'] ?>">
        <b class="tm-tt"><?= e($x['title']) ?></b>
        <?php if (trim((string)($x['descr'] ?? '')) !== ''): ?><span class="tm-td"><?= e(mb_substr(trim((string)preg_replace('/\s+/u', ' ', $x['descr'])), 0, 90)) ?></span><?php endif; ?>
        <?php $att = count(\Sysfera\Attach::decode($x['links'] ?? null)) + count(\Sysfera\Attach::decode($x['wdocs'] ?? null)) + count(\Sysfera\Attach::decode($x['tfiles'] ?? null)); ?>
        <span class="tm-task-f"><span class="tm-tmeta">
          <?php if ($x['due']): ?><small class="<?= $late ? 'late' : 'muted' ?>"><svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg> <?= e(date('d.m', strtotime((string)$x['due']))) ?></small><?php endif; ?>
          <?php if ($att): ?><small class="muted" title="<?= e($sf->t('attachments')) ?>"><svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><path d="M21 11l-8.5 8.5a5 5 0 0 1-7-7L14 4a3.5 3.5 0 0 1 5 5l-8.5 8.5a2 2 0 0 1-3-3L15 7"/></svg> <?= $att ?></small><?php endif; ?></span>
          <?php if ($x['aname']): ?><span class="tm-tas"><small class="muted"><?= e($sf->t('assigned_to')) ?></small><i class="tm-av" title="<?= e($x['aname']) ?>"><?= e(mb_strtoupper(mb_substr($x['aname'], 0, 1))) ?></i></span><?php endif; ?></span></a><?php endforeach; ?></div></section>
<?php endforeach; ?></div>

<?php if ($adm): ?><details class="card tm-add"><summary><?= e($sf->t('manage_columns')) ?></summary>
  <?php foreach ($cols as $col): ?><form method="post" action="<?= $act('/col/save') ?>" class="tm-crow"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$col['id'] ?>">
    <input name="name" value="<?= e($col['name']) ?>" maxlength="60" required><input name="sort" type="number" value="<?= (int)$col['sort'] ?>" style="width:80px" title="<?= e($sf->t('st_order')) ?>">
    <label class="chk"><input type="checkbox" name="is_done" value="1"<?= $col['is_done'] ? ' checked' : '' ?>> <?= e($sf->t('col_done')) ?></label><button class="btn sm"><?= e($sf->t('save')) ?></button>
    <button class="btn sm danger" formaction="<?= $act('/col/' . (int)$col['id'] . '/delete') ?>" formnovalidate data-confirm="<?= e($sf->t('confirm_delete')) ?>">×</button></form><?php endforeach; ?>
  <form method="post" action="<?= $act('/col/save') ?>" class="tm-crow"><?= csrf_field() ?><input name="name" placeholder="<?= e($sf->t('new_column')) ?>" maxlength="60" required><label class="chk"><input type="checkbox" name="is_done" value="1"> <?= e($sf->t('col_done')) ?></label><button class="btn sm primary">+</button></form></details><?php endif; ?>
<script src="<?= e($sf->asset('teams.js')) ?>?v=<?= e($sf->manifest['version']) ?>"></script>
