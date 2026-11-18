<?php /** @var \Sysfera\Plugin $sf @var array $rows @var array $cats @var string $st @var string $q @var int $cat @var bool $bm @var array $withMe @var string $cronUrl @var array $langs */
$tab = $bm ? 'bookmarks' : 'missions'; include __DIR__ . '/_nav.php';
$lang = \Sysfera\I18n::lang();
$cn = fn(array $r) => ($lang === 'ru' && ($r['cat_ru'] ?? '') !== '') ? $r['cat_ru'] : ($r['cat'] ?? '');
$cname = fn(array $c) => ($lang === 'ru' && $c['name_ru'] !== '') ? $c['name_ru'] : $c['name'];
$qs = fn(array $o) => '?' . http_build_query(array_filter(array_merge(['q' => $q, 'status' => $st, 'cat' => $cat ?: '', 'bm' => $bm ? 1 : ''], $o), fn($v) => $v !== '' && $v !== null && $v !== 0)); ?>
<div class="row" style="margin-bottom:6px">
  <button class="btn primary" type="button" onclick="document.getElementById('newm').showModal()">＋ <?= e($sf->t('new_mission')) ?></button>
  <button class="btn" type="button" onclick="document.getElementById('impm').showModal()"><?= mis_ico('upload') ?> <?= e($sf->t('import')) ?></button>
  <a class="btn" href="<?= e($sf->url('/export')) ?>"><?= mis_ico('download') ?> <?= e($sf->t('export_all')) ?></a>
  <form class="row" method="get" style="margin-left:auto"><?php if ($bm): ?><input type="hidden" name="bm" value="1"><?php endif; ?><?php if ($cat): ?><input type="hidden" name="cat" value="<?= (int)$cat ?>"><?php endif; ?>
    <input name="q" value="<?= e($q) ?>" placeholder="<?= e($sf->t('search')) ?>" style="width:200px;border-radius:999px">
    <select name="status" onchange="this.form.submit()" style="width:auto;border-radius:999px"><option value=""><?= e($sf->t('all')) ?></option><option value="active" <?= $st === 'active' ? 'selected' : '' ?>><?= e($sf->t('st_active')) ?></option><option value="completed" <?= $st === 'completed' ? 'selected' : '' ?>><?= e($sf->t('st_completed')) ?></option></select></form>
</div>
<div class="filters"><a class="<?= $cat ? '' : 'on' ?>" href="<?= e($sf->url('/') . $qs(['cat' => ''])) ?>"><?= e($sf->t('all')) ?></a>
  <?php foreach ($cats as $c): ?><a class="<?= $cat === (int)$c['id'] ? 'on' : '' ?>" href="<?= e($sf->url('/') . $qs(['cat' => (int)$c['id']])) ?>"><?= e($cname($c)) ?></a><?php endforeach; ?></div>

<?php if (!$rows): ?><div class="card muted" style="text-align:center"><?= e($sf->t('none')) ?></div><?php endif; ?>
<div class="mgrid">
<?php foreach ($rows as $r): $pct = $r['n'] ? (int)round($r['done'] * 100 / $r['n']) : 0; ?>
  <article class="card mcard <?= $r['status'] === 'completed' ? 'mdone' : '' ?>">
    <div class="row between"><div class="chips"><span class="chip id">M-<?= (int)$r['id'] ?></span>
      <?php if ($r['type'] !== ''): ?><span class="chip blue"><?= e($r['type']) ?></span><?php endif; ?>
      <?php if ($cn($r)): ?><span class="chip cat" style="background:<?= e($r['color'] ?? '#6b7280') ?>"><?= e($cn($r)) ?></span><?php endif; ?>
      <span class="chip"><?= $r['shared'] ? mis_ico('globe') . ' ' . e($sf->t('vis_public')) : mis_ico('lock') . ' ' . e($sf->t('vis_private')) ?></span><?php if ($r['shared']): ?><span class="chip"><?= mis_ico('book') ?> <?= e($sf->t('nav_library')) ?></span><?php endif; ?>
      <span class="chip <?= $r['status'] === 'completed' ? '' : 'blue' ?>"><?= $r['status'] === 'completed' ? mis_ico('check') . ' ' . e($sf->t('st_completed')) : mis_ico('play', 13) . ' ' . e($sf->t('st_active')) ?></span></div>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="linkbtn" name="do" value="bookmark" title="<?= e($sf->t('bookmark')) ?>" style="text-decoration:none;font-size:20px"><?= mis_ico('star', 18, (bool)$r['starred']) ?></button></form></div>
    <h3><a href="<?= e($sf->url('/m/' . $r['id'])) ?>"><?= e($r['title']) ?></a></h3>
    <div class="chips sm muted"><?php if ($r['due_date']): ?><span><?= mis_ico('cal', 14) ?> <?= e(date('d/m/Y', strtotime((string)$r['due_date']))) ?></span><?php endif; ?><span><?= e($sf->t('tasks_n', ['n' => (int)$r['n']])) ?></span><?php if ((float)$r['total'] > 0): ?><span class="chip cost">$<?= e(number_format((float)$r['total'], 2, '.', ' ')) ?></span><?php endif; ?></div>
    <?php if ($r['n']): ?><div class="mbar"><i style="width:<?= $pct ?>%"></i></div><?php endif; ?>
    <?php if ($r['description']): ?><p class="muted sm" style="margin:8px 0"><?= e(mb_substr((string)$r['description'], 0, 400)) ?></p><?php endif; ?>
    <form method="post" class="row between" style="margin-top:10px"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
      <div class="row"><a class="btn sm" href="<?= e($sf->url('/m/' . $r['id'])) ?>"><?= mis_ico('edit', 14) ?> <?= e($sf->t('edit')) ?></a>
        <a class="ibtn" href="<?= e($sf->url('/m/' . $r['id'] . '/export')) ?>" title="JSON"><?= mis_ico('download') ?></a>
        <button class="ibtn" name="do" value="duplicate" title="<?= e($sf->t('copy')) ?>"><?= mis_ico('copy') ?></button>
        <?php if ($r['status'] === 'completed'): ?><button class="ibtn" name="do" value="uncomplete" title="<?= e($sf->t('uncomplete')) ?>"><?= mis_ico('undo') ?></button><?php else: ?><button class="ibtn" name="do" value="complete" title="<?= e($sf->t('complete')) ?>"><?= mis_ico('check') ?></button><?php endif; ?>
        <button class="ibtn danger" name="do" value="delete" data-confirm="<?= e($sf->t('confirm_delete')) ?>" title="<?= e($sf->t('delete')) ?>"><?= mis_ico('trash', 15) ?></button></div>
      <a class="btn primary sm" href="<?= e($sf->url('/m/' . $r['id'])) ?>"><?= e($sf->t('open')) ?> ›</a></form>
  </article>
<?php endforeach; ?>
</div>

<?php if ($withMe): ?><section class="card"><h3><?= e($sf->t('shared_with_me')) ?></h3>
<?php foreach ($withMe as $r): ?><div class="row between" style="padding:6px 0"><div><a href="<?= e($sf->url('/library/' . $r['id'])) ?>"><?= e($r['title']) ?></a> <small class="muted"><?= e($sf->t('by_author', ['name' => (string)$r['author']])) ?></small><?= $r['status'] === 'completed' ? ' <span class="badge ok">' . e($sf->t('st_completed')) . '</span>' : '' ?></div>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="linkbtn" name="do" value="bookmark" style="text-decoration:none;font-size:18px"><?= mis_ico('star', 18, (bool)$r['starred']) ?></button></form></div><?php endforeach; ?></section><?php endif; ?>

<?php if ($cronUrl): ?><section class="card"><h3><?= e($sf->t('cron_h')) ?></h3><p class="muted sm"><?= e($sf->t('cron_hint')) ?></p><code style="word-break:break-all">curl -s "<?= e($cronUrl) ?>"</code></section><?php endif; ?>

<dialog class="tdlg" id="newm"><form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="create">
  <header><h3><?= e($sf->t('new_mission')) ?></h3><button class="ibtn" type="button" onclick="this.closest('dialog').close()"><?= mis_ico('x') ?></button></header>
  <div class="body">
    <label class="fld"><span><?= e($sf->t('f_title')) ?> *</span><input name="title" required maxlength="255"></label>
    <div class="cols"><label class="fld"><span><?= e($sf->t('f_type')) ?> *</span><input name="type" required maxlength="100" placeholder="<?= e($sf->t('type_ph')) ?>"></label>
      <label class="fld"><span><?= e($sf->t('category')) ?></span><select name="category_id"><option value="0">— <?= e($sf->t('none_cat')) ?> —</option><?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($cname($c)) ?></option><?php endforeach; ?></select></label></div>
    <label class="fld"><span><?= e($sf->t('due')) ?></span><input type="date" name="due_date"></label>
    <label class="fld"><span><?= e($sf->t('notify_lang')) ?></span><select name="lang"><option value="">— <?= e($sf->t('lang_default')) ?> —</option><?php foreach ($langs as $code => $name): ?><option value="<?= e($code) ?>"><?= e($name) ?></option><?php endforeach; ?></select><small class="muted"><?= e($sf->t('notify_lang_hint')) ?></small></label>
    <label class="fld"><span><?= e($sf->t('f_desc')) ?></span><textarea name="description" rows="3" maxlength="5000"></textarea></label>
    <label class="fld"><span><?= e($sf->t('mission_status')) ?></span><select name="vis" onchange="document.getElementById('hidef').hidden=this.value!=='public'"><option value="private"><?= e($sf->t('vis_private')) ?></option><option value="public"><?= e($sf->t('vis_public')) ?> — <?= e($sf->t('share_lib')) ?></option></select></label>
    <div class="filenote" id="hidef" hidden><b><?= mis_ico('clip', 14) ?> <?= e($sf->t('files_note_h')) ?></b><br><?= e($sf->t('files_note')) ?><br><label class="chk"><input type="checkbox" name="hide_files" value="1" checked> <?= e($sf->t('hide_files')) ?></label></div>
  </div>
  <footer><button class="btn" type="button" onclick="this.closest('dialog').close()"><?= e($sf->t('cancel')) ?></button><button class="btn primary"><?= e($sf->t('save')) ?></button></footer></form></dialog>
<dialog class="tdlg" id="impm"><form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="do" value="import">
  <header><h3><?= e($sf->t('import_h')) ?></h3><button class="ibtn" type="button" onclick="this.closest('dialog').close()"><?= mis_ico('x') ?></button></header>
  <div class="body"><p class="muted sm"><?= e($sf->t('import_hint')) ?></p><label class="fld"><span><?= e($sf->t('file')) ?></span><input type="file" name="file" accept=".json,application/json" required></label></div>
  <footer><button class="btn" type="button" onclick="this.closest('dialog').close()"><?= e($sf->t('cancel')) ?></button><button class="btn primary"><?= e($sf->t('import')) ?></button></footer></form></dialog>
</div>
