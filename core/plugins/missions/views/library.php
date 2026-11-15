<?php /** @var \Sysfera\Plugin $sf @var array $rows @var array $cats @var string $q @var int $cat */
$tab = 'library'; include __DIR__ . '/_nav.php'; $lang = \Sysfera\I18n::lang(); ?>
<div class="page-h"><h1><?= e($sf->t('library')) ?></h1></div>
<div class="hint"><?= e($sf->t('library_hint')) ?></div>
<form class="card" method="get"><div class="row">
  <label class="fld inline"><span><?= e($sf->t('search')) ?></span><input name="q" value="<?= e($q) ?>"></label>
  <label class="fld inline"><span><?= e($sf->t('category')) ?></span><select name="cat"><option value="0"><?= e($sf->t('all')) ?></option><?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $cat === (int)$c['id'] ? 'selected' : '' ?>><?= e($lang === 'ru' && $c['name_ru'] !== '' ? $c['name_ru'] : $c['name']) ?></option><?php endforeach; ?></select></label>
  <button class="btn"><?= e($sf->t('apply')) ?></button></div></form>
<?php if (!$rows): ?><div class="card empty"><p><?= e($sf->t('library_none')) ?></p></div><?php endif; ?>
<div class="mgrid">
<?php foreach ($rows as $r): $cn = ($lang === 'ru' && ($r['cat_ru'] ?? '') !== '') ? $r['cat_ru'] : ($r['cat'] ?? ''); ?>
  <article class="card mcard"><h3><a href="<?= e($sf->url('/library/' . $r['id'])) ?>"><?= e($r['title']) ?></a></h3>
    <div class="muted sm"><?php if ($cn): ?><span class="chip cat" style="background:<?= e($r['color'] ?? '#6b7280') ?>"><?= e($cn) ?></span> <?php endif; ?><?= e($r['type']) ?> · <?= e($sf->t('by_author', ['name' => $r['author'] ?? '—'])) ?> · <?= e($sf->t('tasks_n', ['n' => (int)$r['n']])) ?></div>
    <?php if ($r['description']): ?><p class="sm"><?= e(mb_substr((string)$r['description'], 0, 200)) ?></p><?php endif; ?>
    <div class="row"><a class="btn" href="<?= e($sf->url('/library/' . $r['id'])) ?>"><?= e($sf->t('preview')) ?></a>
      <form method="post" action="<?= e($sf->url('/library/' . $r['id'])) ?>"><?= csrf_field() ?><input type="hidden" name="do" value="import"><button class="btn primary"><?= e($sf->t('import_to_mine')) ?></button></form></div></article>
<?php endforeach; ?>
</div>
</div>
