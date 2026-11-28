<?php /** @var \Sysfera\Plugin $sf @var array $v @var array $board @var array $versions @var array $roles @var array $teams @var array $users @var array $interviews */
include __DIR__ . '/_nav.php';
$act = fn(string $op, string $label, string $cls = 'btn sm', array $extra = []) => '<form method="post" action="' . e($sf->url('/do/' . $op)) . '" style="display:inline">' . csrf_field() . '<input type="hidden" name="return" value="/vacancies/' . e($v['uuid']) . '"><input type="hidden" name="uuid" value="' . e($v['uuid']) . '">'
    . implode('', array_map(fn($k, $x) => '<input type="hidden" name="' . e($k) . '" value="' . e($x) . '">', array_keys($extra), $extra)) . '<button class="' . $cls . '">' . e($sf->t($label)) . '</button></form>';
$byUuid = [];
foreach ($board['columns'] as $col) foreach ($col as $cd) $byUuid[$cd['uuid']] = $cd; ?>
<div class="page-h"><h1><?= e($v['title']) ?> <span class="hr-st <?= $hrst($v['wf']) ?>"><?= e($sf->t('wf_' . $v['wf'])) ?></span> <span class="hr-st"><?= e($sf->t('vs_' . $v['status'])) ?></span> <small class="muted">v<?= (int)$v['version'] ?></small></h1>
  <div class="row"><?php if ($v['status'] === 'open'): ?>
    <?php if ($v['wf'] === 'draft') echo $act('vacancies.approve', 'act_approve', 'btn sm primary'); ?>
    <?php if ($v['wf'] === 'approved') echo $act('vacancies.publish', 'act_publish', 'btn sm primary', ['audience' => 'global']); ?>
    <?= $act('vacancies.close', 'act_close') ?><?php else: ?><?php if ($v['status'] === 'closed') echo $act('vacancies.archive', 'act_archive'); endif; ?></div></div>
<p class="muted"><?php if (\SysferaHr\Recruit::salaryText($v) !== ''): ?><b><?= e(\SysferaHr\Recruit::salaryText($v)) ?></b> · <?php endif; ?><a href="<?= e($sf->url('/v/' . $v['uuid'])) ?>"><?= e($sf->t('act_preview')) ?></a></p>
<?php if ($v['status'] === 'open' && $v['wf'] !== 'published'): ?><p class="hint"><?= e($sf->t('share_hint')) ?></p><?php endif; ?>
<?php if (!empty($v['skills'])): ?><p class="hr-chips"><?php foreach ($v['skills'] as $s): ?><span class="hr-chip"><i><?= e($s) ?></i></span><?php endforeach; ?></p><?php endif; ?>
<?php if (!empty($v['post_ref'])): ?><p class="muted"><?= e($sf->t('published_as')) ?> <code><?= e($v['post_ref']) ?></code> <?= e((string)$v['published_at']) ?><?= !empty($v['external']) ? ' · ' . e($sf->t('job_boards')) . ': ' . e((string)$v['external']) : '' ?></p><?php endif; ?>

<h3><?= e($sf->t('pipeline')) ?></h3>
<div class="hr-board" id="board" data-url="<?= e($sf->url('/api/candidates.move')) ?>" data-token="<?= e(csrf_token()) ?>">
<?php foreach ($board['stages'] as $stg): ?>
  <div class="hr-col" data-stage="<?= e($stg) ?>"><h4><?= e($sf->t('stage_' . $stg)) ?> <span><?= count($board['columns'][$stg]) ?></span></h4>
    <?php foreach ($board['columns'][$stg] as $cd): ?><div class="hr-card" draggable="true" data-uuid="<?= e($cd['uuid']) ?>"><b><?= e($cd['full_name']) ?></b><small><?= e($cd['email']) ?></small>
      <?php if (!empty($cd['employee_uuid'])): ?><a href="<?= e($sf->url('/employees/' . $cd['employee_uuid'])) ?>"><?= e($sf->t('open_profile')) ?></a>
      <?php elseif (!in_array($stg, ['hired', 'rejected'], true)): ?>
      <form method="post" action="<?= e($sf->url('/do/candidates.move')) ?>" class="row"><?= csrf_field() ?><input type="hidden" name="return" value="/vacancies/<?= e($v['uuid']) ?>"><input type="hidden" name="uuid" value="<?= e($cd['uuid']) ?>">
        <select name="stage"><?php foreach (\SysferaHr\Pipeline::NEXT[$stg] as $n): if ($n === 'hired') continue; ?><option value="<?= e($n) ?>"><?= e($sf->t('stage_' . $n)) ?></option><?php endforeach; ?></select><input name="note" placeholder="<?= e($sf->t('f_note')) ?>" size="8"><button class="btn sm"><?= e($sf->t('move')) ?></button></form>
      <?php if ($stg === 'offer'): ?><form method="post" action="<?= e($sf->url('/do/candidates.hire')) ?>" class="row"><?= csrf_field() ?><input type="hidden" name="uuid" value="<?= e($cd['uuid']) ?>"><input type="date" name="hire_date" required><button class="btn sm primary"><?= e($sf->t('act_hire')) ?></button></form><?php endif; ?>
      <?php endif; ?></div><?php endforeach; ?></div>
<?php endforeach; ?></div>
<script>
(function(){var b=document.getElementById('board');if(!b)return;var drag=null;
b.addEventListener('dragstart',function(e){var c=e.target.closest('.hr-card');if(c){drag=c;e.dataTransfer.setData('text/plain',c.dataset.uuid);}});
b.addEventListener('dragover',function(e){var col=e.target.closest('.hr-col');if(col&&drag){e.preventDefault();col.classList.add('over');}});
b.addEventListener('dragleave',function(e){var col=e.target.closest('.hr-col');if(col)col.classList.remove('over');});
b.addEventListener('drop',function(e){var col=e.target.closest('.hr-col');if(!col||!drag)return;e.preventDefault();col.classList.remove('over');
 var f=new FormData();f.append('_t',b.dataset.token);f.append('uuid',drag.dataset.uuid);f.append('stage',col.dataset.stage);f.append('note',col.dataset.stage==='rejected'?(prompt('<?= e($sf->t('reject_reason')) ?>')||''):'');
 fetch(b.dataset.url,{method:'POST',body:f,credentials:'same-origin'}).then(function(r){return r.json().then(function(j){if(!r.ok)alert(j.error&&j.error.message||'error');location.reload();});});});})();
</script>

<div class="card"><h3><?= e($sf->t('cand_add')) ?></h3>
  <form method="post" action="<?= e($sf->url('/do/candidates.add')) ?>" class="row"><?= csrf_field() ?><input type="hidden" name="return" value="/vacancies/<?= e($v['uuid']) ?>"><input type="hidden" name="uuid" value="<?= e($v['uuid']) ?>">
    <input name="full_name" placeholder="<?= e($sf->t('f_full_name')) ?>" required><input type="email" name="email" placeholder="<?= e($sf->t('f_email')) ?>" required><input name="phone" placeholder="<?= e($sf->t('f_phone')) ?>"><input name="source" placeholder="<?= e($sf->t('f_source')) ?>"><button class="btn primary"><?= e($sf->t('add')) ?></button></form></div>

<div class="card"><h3><?= e($sf->t('interviews')) ?></h3>
  <?php $cands = array_filter($byUuid, fn($cd) => in_array($cd['stage'], ['screening', 'interview'], true)); ?>
  <form method="post" action="<?= e($sf->url('/do/interviews.schedule')) ?>" class="hr-grid"><?= csrf_field() ?><input type="hidden" name="return" value="/vacancies/<?= e($v['uuid']) ?>">
    <label><?= e($sf->t('iv_candidate')) ?><select name="uuid" required><option value=""><?= e($sf->t($cands ? 'iv_pick' : 'iv_none')) ?></option><?php foreach ($cands as $cd): ?><option value="<?= e($cd['uuid']) ?>"><?= e($cd['full_name']) ?> · <?= e($sf->t('stage_' . $cd['stage'])) ?></option><?php endforeach; ?></select></label>
    <label><?= e($sf->t('iv_interviewer')) ?><select name="interviewer_user_id" required><?php foreach ($users as $u): ?><option value="<?= (int)$u['id'] ?>"><?= e($u['name']) ?></option><?php endforeach; ?></select></label>
    <label><?= e($sf->t('iv_starts')) ?><input type="datetime-local" name="starts_at" required></label><label><?= e($sf->t('iv_ends')) ?><input type="datetime-local" name="ends_at" required></label>
    <label><?= e($sf->t('f_place')) ?><input name="place" maxlength="200"></label><label class="hr-act"><button class="btn primary"><?= e($sf->t('schedule')) ?></button></label></form>
  <?php if (!$cands): ?><p class="muted sm"><?= e($sf->t('iv_hint')) ?></p><?php endif; ?>
  <?php foreach ($interviews as $h): foreach ($h['interviews'] as $i): $fb = array_values(array_filter($h['feedback'], fn($f) => (int)$f['interviewer_user_id'] === (int)$i['interviewer_user_id'])); ?>
  <details class="card"><summary><b><?= e($h['candidate']['full_name']) ?></b> · <?= e(substr($i['starts_at'], 0, 16)) ?> – <?= e(substr($i['ends_at'], 11, 5)) ?> <span class="hr-st <?= $hrst($i['status']) ?>"><?= e($sf->t('is_' . $i['status'])) ?></span><?= $fb ? ' · ' . e($sf->t('rec_' . $fb[0]['recommend'])) . ' (' . e((string)$fb[0]['avg']) . ')' : '' ?></summary>
    <?php if ($i['status'] === 'scheduled'): ?>
    <form method="post" action="<?= e($sf->url('/do/interviews.feedback')) ?>"><?= csrf_field() ?><input type="hidden" name="return" value="/vacancies/<?= e($v['uuid']) ?>"><input type="hidden" name="uuid" value="<?= e($i['uuid']) ?>">
      <div class="hr-grid"><?php foreach (\SysferaHr\Pipeline::CRITERIA as $k): ?><label><?= e($sf->t('crit_' . $k)) ?><select name="scores[<?= e($k) ?>]"><?php for ($n = 1; $n <= 5; $n++): ?><option value="<?= $n ?>" <?= $n === 3 ? 'selected' : '' ?>><?= $n ?></option><?php endfor; ?></select></label><?php endforeach; ?>
        <label><?= e($sf->t('f_recommend')) ?><select name="recommend"><?php foreach (\SysferaHr\Pipeline::RECOMMEND as $r): ?><option value="<?= e($r) ?>"><?= e($sf->t('rec_' . $r)) ?></option><?php endforeach; ?></select></label></div>
      <label class="fld"><?= e($sf->t('f_comment')) ?><textarea name="comment" rows="3" maxlength="2000" style="width:100%"></textarea></label><button class="btn sm primary"><?= e($sf->t('save')) ?></button></form>
    <form method="post" action="<?= e($sf->url('/do/interviews.cancel')) ?>"><?= csrf_field() ?><input type="hidden" name="return" value="/vacancies/<?= e($v['uuid']) ?>"><input type="hidden" name="uuid" value="<?= e($i['uuid']) ?>"><button class="btn sm"><?= e($sf->t('cancel')) ?></button></form>
    <?php elseif ($fb): ?><p><?= e($fb[0]['comment']) ?></p><?php endif; ?></details>
  <?php endforeach; endforeach; ?></div>

<div class="card"><h3><?= e($sf->t('vac_edit')) ?></h3><?php if ($v['status'] === 'open'): ?>
  <form method="post" action="<?= e($sf->url('/do/vacancies.save')) ?>"><?= csrf_field() ?><input type="hidden" name="return" value="/vacancies/<?= e($v['uuid']) ?>"><input type="hidden" name="uuid" value="<?= e($v['uuid']) ?>">
    <div class="hr-grid"><label><?= e($sf->t('f_title')) ?><input name="title" value="<?= e($v['title']) ?>" required maxlength="160"></label>
      <label><?= e($sf->t('f_role')) ?><select name="role_uuid"><?php foreach ($roles as $r): ?><option value="<?= e($r['uuid']) ?>" <?= $r['uuid'] === $v['role_uuid'] ? 'selected' : '' ?>><?= e($r['name']) ?></option><?php endforeach; ?></select></label>
      <label><?= e($sf->t('f_team')) ?><select name="team_uuid"><option value=""><?= e($sf->t('team_none')) ?></option><?php foreach ($teams as $t): ?><option value="<?= e($t['uuid']) ?>" <?= ($v['team_uuid'] ?? null) === $t['uuid'] ? 'selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach; ?></select></label><label><?= e($sf->t('f_salary_from')) ?><input name="salary_from" inputmode="numeric" pattern="[0-9 ]*" maxlength="12" value="<?= e((string)($v['salary_from'] ?? '')) ?>"></label><label><?= e($sf->t('f_salary_to')) ?><input name="salary_to" inputmode="numeric" pattern="[0-9 ]*" maxlength="12" value="<?= e((string)($v['salary_to'] ?? '')) ?>"></label><label><?= e($sf->t('f_currency')) ?><select name="salary_cur"><?php foreach (\SysferaHr\Recruit::CURRENCIES as $k => $sym): ?><option value="<?= e($k) ?>" <?= ($v['salary_cur'] ?? 'RUB') === $k ? 'selected' : '' ?>><?= e($k . ' ' . $sym) ?></option><?php endforeach; ?></select></label></div>
    <?php $skillList = (array)($v['skills'] ?? []); include __DIR__ . '/_skills.php'; ?>
    <?php $vd = (array)($v['details'] ?? []); $vdText = (string)$v['description']; $vdNew = false; include __DIR__ . '/_details_form.php'; ?><p class="muted"><?= e($sf->t('version_hint')) ?></p>
    <button class="btn primary"><?= e($sf->t('save')) ?></button></form><?php endif; ?>
  <h3><?= e($sf->t('versions')) ?></h3><?php foreach ($versions as $ver): ?><details><summary>v<?= (int)$ver['version'] ?> · <?= e($ver['created_at']) ?> · <?= e($ver['created_by']) ?> · <code><?= e(substr($ver['sha256'], 0, 10)) ?></code></summary><?php if (empty($ver['details'])): ?><div class="hr-pre"><?= e($ver['description']) ?></div><?php else: ?><div class="hr-pre hr-ver"><?php $vd = $ver['details']; $vdText = (string)$ver['description']; foreach (\SysferaHr\Recruit::facts($vd, [$sf, 't']) as $fk => $fx): ?><div><b><?= e($sf->t($fk)) ?>:</b> <?= e($fx) ?></div><?php endforeach; include __DIR__ . '/_details.php'; ?></div><?php endif; ?></details><?php endforeach; ?></div>
