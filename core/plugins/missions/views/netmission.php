<?php /** @var \Sysfera\Plugin $sf @var array $m @var array $types @var int $id */
$tab = 'library'; include __DIR__ . '/_nav.php'; $lang = \Sysfera\I18n::lang();
$from = $m['source'] === 'tactify' ? 'tactify.ru' : (string)preg_replace('/^site:/', '', (string)$m['source']);
$cn = ($lang === 'ru' && ($m['category_ru'] ?? '') !== '') ? $m['category_ru'] : ($m['category'] ?? '');
$tasks = (array)($m['content']['tasks'] ?? []); ?>
<div class="page-h"><h1><?= e($m['title']) ?></h1></div>
<p class="muted sm"><a href="<?= e($sf->url('/library?src=net')) ?>">← <?= e($sf->t('lib_network')) ?></a> · <?php if ($cn): ?><span class="chip cat"><?= e($cn) ?></span> <?php endif; ?><?= e($m['type']) ?> · <?= e($sf->t('by_author', ['name' => $m['author'] !== '' ? $m['author'] : '—'])) ?> · <?= e($sf->t('net_from', ['site' => $from])) ?></p>
<?php if ($m['description']): ?><div class="card"><?= nl2br(e($m['description'])) ?></div><?php endif; ?>
<section class="card"><h3><?= e($sf->t('tasks_n', ['n' => count($tasks)])) ?></h3>
  <ol class="sm" style="margin:0;padding-left:20px"><?php foreach ($tasks as $t): $ty = \Sysfera\TaskTypes::get($types, (string)($t['type'] ?? 'note')); ?>
    <li style="margin:4px 0;margin-left:<?= $t['parent'] !== null ? 8 : 0 ?>px"><?= \Sysfera\TaskTypes::svg($ty['icon'], 13) ?> <b><?= e($t['title'] ?? '') ?></b> <span class="muted">· <?= e(\Sysfera\TaskTypes::label($ty, $lang)) ?><?= !empty($t['branch']) ? ' · ' . e($sf->t('br_' . $t['branch'])) : '' ?></span></li>
  <?php endforeach; ?></ol></section>
<div class="row">
  <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="import"><button class="btn primary"><?= e($sf->t('import_to_mine')) ?></button></form>
  <form method="post" data-confirm="<?= e($sf->t('net_report')) ?>?"><?= csrf_field() ?><input type="hidden" name="do" value="report"><button class="btn"><?= e($sf->t('net_report')) ?></button></form>
</div>
</div>
