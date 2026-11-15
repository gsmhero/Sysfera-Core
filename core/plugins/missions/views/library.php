<?php /** @var \Sysfera\Plugin $sf @var array $rows @var array $cats @var string $q @var int $cat @var ?string $src @var ?array $net */
$src ??= ''; $net ??= null;
$tab = 'library'; include __DIR__ . '/_nav.php'; $lang = \Sysfera\I18n::lang();
$srcs = ['' => 'lib_this_site', 'net' => 'lib_network', 'tactify' => 'lib_tactify', 'sites' => 'lib_sites']; ?>
<div class="page-h"><h1><?= e($sf->t('library')) ?></h1></div>
<div class="hint"><?= e($sf->t($net === null ? 'library_hint' : 'library_net_hint')) ?></div>
<div class="seg"><?php foreach ($srcs as $k => $lbl): ?><a class="<?= $src === $k ? 'on' : '' ?>" href="<?= e($sf->url('/library' . ($k !== '' ? '?src=' . $k : ''))) ?>"><?= e($sf->t($lbl)) ?></a><?php endforeach; ?></div>
<form class="card" method="get"><input type="hidden" name="src" value="<?= e($src) ?>"><div class="row">
  <label class="fld inline"><span><?= e($sf->t('search')) ?></span><input name="q" value="<?= e($q) ?>"></label>
  <label class="fld inline"><span><?= e($sf->t('category')) ?></span><select name="cat"><option value="0"><?= e($sf->t('all')) ?></option><?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $cat === (int)$c['id'] ? 'selected' : '' ?>><?= e($lang === 'ru' && $c['name_ru'] !== '' ? $c['name_ru'] : $c['name']) ?></option><?php endforeach; ?></select></label>
  <button class="btn"><?= e($sf->t('apply')) ?></button></div></form>
<?php if ($net !== null): ?>
  <?php if ($net['err']): ?><div class="card empty"><p><?= e($sf->t($net['err'] === 'not_connected' ? 'net_not_connected' : 'net_unavailable')) ?></p></div>
  <?php elseif (!$net['rows']): ?><div class="card empty"><p><?= e($sf->t('library_none')) ?></p></div><?php endif; ?>
  <div class="mgrid">
  <?php foreach ($net['rows'] as $r): $cn = ($lang === 'ru' && ($r['category_ru'] ?? '') !== '') ? $r['category_ru'] : ($r['category'] ?? ''); $from = $r['source'] === 'tactify' ? 'tactify.ru' : (string)preg_replace('/^site:/', '', (string)$r['source']); ?>
    <article class="card mcard"><h3><a href="<?= e($sf->url('/library/net/' . (int)$r['id'])) ?>"><?= e($r['title']) ?></a></h3>
      <div class="muted sm"><?php if ($cn): ?><span class="chip cat"><?= e($cn) ?></span> <?php endif; ?><?= e($r['type']) ?> · <?= e($sf->t('by_author', ['name' => $r['author'] !== '' ? $r['author'] : '—'])) ?> · <?= e($sf->t('tasks_n', ['n' => (int)$r['tasks_n']])) ?> · <?= e($sf->t('net_from', ['site' => $from])) ?></div>
      <?php if ($r['description']): ?><p class="sm"><?= e(mb_substr((string)$r['description'], 0, 200)) ?></p><?php endif; ?>
      <div class="row"><a class="btn" href="<?= e($sf->url('/library/net/' . (int)$r['id'])) ?>"><?= e($sf->t('preview')) ?></a></div></article>
  <?php endforeach; ?>
  </div>
  <?php $pages = (int)ceil($net['total'] / 24); if ($pages > 1): ?><div class="row"><?php for ($i = 1; $i <= min($pages, 40); $i++): ?><a class="chip <?= $i === $net['page'] ? 'on' : '' ?>" href="<?= e($sf->url('/library?src=' . $src . '&q=' . urlencode($q) . '&cat=' . $cat . '&page=' . $i)) ?>"><?= $i ?></a><?php endfor; ?></div><?php endif; ?>
<?php else: ?>
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
<?php endif; ?>
</div>
