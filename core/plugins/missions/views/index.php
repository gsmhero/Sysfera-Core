<?php /** @var \Sysfera\Plugin $sf @var array $rows @var array $cats @var string $st @var string $q @var int $cat @var bool $bm @var array $withMe @var string $cronUrl */
$tab = 'missions'; include __DIR__ . '/_nav.php';
$lang = \Sysfera\I18n::lang();
$cn = fn(array $r) => ($lang === 'ru' && ($r['cat_ru'] ?? '') !== '') ? $r['cat_ru'] : ($r['cat'] ?? ''); ?>
<div class="page-h"><h1><?= e($sf->t('title')) ?></h1></div>

<form class="card" method="get"><div class="row">
  <label class="fld inline"><span><?= e($sf->t('search')) ?></span><input name="q" value="<?= e($q) ?>"></label>
  <label class="fld inline"><span><?= e($sf->t('status')) ?></span><select name="status"><option value=""><?= e($sf->t('all')) ?></option><option value="active" <?= $st === 'active' ? 'selected' : '' ?>><?= e($sf->t('st_active')) ?></option><option value="completed" <?= $st === 'completed' ? 'selected' : '' ?>><?= e($sf->t('st_completed')) ?></option></select></label>
  <label class="fld inline"><span><?= e($sf->t('category')) ?></span><select name="cat"><option value="0"><?= e($sf->t('all')) ?></option><?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $cat === (int)$c['id'] ? 'selected' : '' ?>><?= e($lang === 'ru' && $c['name_ru'] !== '' ? $c['name_ru'] : $c['name']) ?></option><?php endforeach; ?></select></label>
  <label class="chk"><input type="checkbox" name="bm" value="1" <?= $bm ? 'checked' : '' ?>> ★ <?= e($sf->t('bookmarks')) ?></label>
  <button class="btn"><?= e($sf->t('apply')) ?></button></div></form>

<?php if (!$rows): ?><div class="card empty"><p><?= e($sf->t('none')) ?></p></div><?php endif; ?>
<div class="mgrid">
<?php foreach ($rows as $r): $pct = $r['n'] ? (int)round($r['done'] * 100 / $r['n']) : 0; ?>
  <article class="card mcard <?= $r['status'] === 'completed' ? 'mdone' : '' ?>">
    <div class="row between"><h3><a href="<?= e($sf->url('/m/' . $r['id'])) ?>"><?= e($r['title']) ?></a></h3>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="linkbtn" name="do" value="bookmark" title="<?= e($sf->t('bookmark')) ?>" style="text-decoration:none;font-size:18px"><?= $r['starred'] ? '★' : '☆' ?></button></form>
      <?php if ($r['status'] === 'completed'): ?><span class="badge ok"><?= e($sf->t('st_completed')) ?></span><?php endif; ?></div>
    <div class="muted sm"><?php if ($cn($r)): ?><span class="mcat" style="background:<?= e($r['color'] ?? '#6b7280') ?>"><?= e($cn($r)) ?></span> <?php endif; ?><?= e($r['type']) ?><?= $r['due_date'] ? ' · ' . e($sf->t('due')) . ' ' . e($r['due_date']) : '' ?><?= $r['shared'] ? ' · ' . e($sf->t('shared')) : '' ?></div>
    <div class="mbar"><i style="width:<?= $pct ?>%"></i></div>
    <div class="muted sm"><?= e($sf->t('progress', ['done' => (int)$r['done'], 'n' => (int)$r['n']])) ?></div>
    <form method="post" class="row"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
      <a class="btn" href="<?= e($sf->url('/m/' . $r['id'])) ?>"><?= e($sf->t('open')) ?></a>
      <?php if ($r['status'] === 'completed'): ?><button class="btn" name="do" value="uncomplete"><?= e($sf->t('uncomplete')) ?></button><?php else: ?><button class="btn" name="do" value="complete"><?= e($sf->t('complete')) ?></button><?php endif; ?>
      <button class="btn danger" name="do" value="delete" data-confirm="<?= e($sf->t('confirm_delete')) ?>">✕</button></div></form>
  </article>
<?php endforeach; ?>
</div>

<div class="cols">
<form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="do" value="create"><h3><?= e($sf->t('new_mission')) ?></h3>
  <label class="fld"><span><?= e($sf->t('f_title')) ?> *</span><input name="title" required maxlength="255"></label>
  <div class="cols">
    <label class="fld"><span><?= e($sf->t('f_type')) ?></span><input name="type" maxlength="100"></label>
    <label class="fld"><span><?= e($sf->t('category')) ?></span><select name="category_id"><option value="0">—</option><?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($lang === 'ru' && $c['name_ru'] !== '' ? $c['name_ru'] : $c['name']) ?></option><?php endforeach; ?></select></label>
    <label class="fld"><span><?= e($sf->t('due')) ?></span><input type="date" name="due_date"></label></div>
  <label class="fld"><span><?= e($sf->t('f_desc')) ?></span><textarea name="description" rows="2" maxlength="5000"></textarea></label>
  <button class="btn primary"><?= e($sf->t('create')) ?></button></form>
<form class="card" method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="do" value="import"><h3><?= e($sf->t('import_h')) ?></h3>
  <p class="muted sm"><?= e($sf->t('import_hint')) ?></p>
  <label class="fld"><span><?= e($sf->t('file')) ?></span><input type="file" name="file" accept=".json,application/json" required></label>
  <button class="btn"><?= e($sf->t('import')) ?></button></form>
</div>

<?php if ($withMe): ?><section class="card"><h3><?= e($sf->t('shared_with_me')) ?></h3><div class="list">
<?php foreach ($withMe as $r): ?><div class="row between"><div><a href="<?= e($sf->url('/library/' . $r['id'])) ?>"><?= e($r['title']) ?></a> <small class="muted"><?= e($sf->t('by_author', ['name' => (string)$r['author']])) ?></small><?= $r['status'] === 'completed' ? ' <span class="badge ok">' . e($sf->t('st_completed')) . '</span>' : '' ?></div>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="linkbtn" name="do" value="bookmark" style="text-decoration:none;font-size:18px"><?= $r['starred'] ? '★' : '☆' ?></button></form></div><?php endforeach; ?></div></section><?php endif; ?>

<?php if ($cronUrl): ?><section class="card"><h3><?= e($sf->t('cron_h')) ?></h3><p class="muted sm"><?= e($sf->t('cron_hint')) ?></p><code class="bbcode">curl -s "<?= e($cronUrl) ?>"</code></section><?php endif; ?>
