<?php /** @var array $counts @var array $plugins @var array $widgets @var bool $isAdmin */ ?>
<div class="page-h"><h1><?= e(t('dash.title')) ?></h1></div>
<div class="stats">
  <div class="card stat"><small><?= e(t('dash.clients')) ?></small><b><?= (int)$counts['contacts'] ?></b></div>
  <div class="card stat"><small><?= e(t('dash.orders')) ?></small><b><?= (int)$counts['orders'] ?></b></div>
  <div class="card stat"><small><?= e(t('dash.invoices')) ?></small><b><?= (int)$counts['invoices'] ?></b></div>
  <div class="card stat"><small><?= e(t('dash.components')) ?></small><b><?= count($plugins) ?></b></div>
</div>
<?php if ($isAdmin && !empty($news['all'])): ?>
<?php $q = fn(array $o) => '?' . http_build_query(array_filter(array_merge(['np' => $news['page'], 'npp' => $news['per'], 'nf' => $news['from'], 'nt' => $news['to']], $o), fn($v) => $v !== '' && $v !== null)) . '#news'; ?>
<section class="card newsbox" id="news"><h3><?= e(t('news.title')) ?></h3>
  <form method="get" action="" class="news-f">
    <label><?= e(t('news.per')) ?> <select name="npp" onchange="this.form.submit()"><?php foreach (\Sysfera\News::PER_PAGE as $n): ?><option value="<?= $n ?>"<?= $n === $news['per'] ? ' selected' : '' ?>><?= $n ?></option><?php endforeach; ?></select></label>
    <label><?= e(t('news.from')) ?> <input type="date" name="nf" value="<?= e((string)$news['from']) ?>"></label>
    <label><?= e(t('news.to')) ?> <input type="date" name="nt" value="<?= e((string)$news['to']) ?>"></label>
    <button class="btn sm"><?= e(t('news.apply')) ?></button>
    <?php if ($news['from'] || $news['to']): ?><a class="sm" href="<?= e($q(['nf' => '', 'nt' => '', 'np' => 1])) ?>"><?= e(t('news.reset')) ?></a><?php endif; ?>
    <small class="muted"><?= e(t('news.total', ['n' => $news['total']])) ?></small>
  </form>
  <?php if (!$news['items']): ?><p class="muted"><?= e(t('news.none')) ?></p><?php endif; ?>
  <?php foreach ($news['items'] as $n): ?><article class="news-item <?= e($n['type']) ?>">
    <div class="news-h"><span class="badge <?= ['warning' => 'bad', 'update' => 'info', 'news' => 'mute'][$n['type']] ?>"><?= e(t('news.' . $n['type'])) ?></span><b><?= e($n['title']) ?></b><small class="muted"><?= e($n['date']) ?></small></div>
    <?php if ($n['body'] !== ''): ?><p><?= nl2br(e($n['body'])) ?></p><?php endif; ?>
    <?php if ($n['url'] !== ''): ?><a class="sm" href="<?= e($n['url']) ?>" target="_blank" rel="noopener noreferrer"><?= e(t('news.more')) ?> →</a><?php endif; ?>
  </article><?php endforeach; ?>
  <?php if ($news['pages'] > 1): ?><nav class="news-pg">
    <?php if ($news['page'] > 1): ?><a class="btn sm" href="<?= e($q(['np' => $news['page'] - 1])) ?>">‹</a><?php endif; ?>
    <span class="muted"><?= (int)$news['page'] ?> / <?= (int)$news['pages'] ?></span>
    <?php if ($news['page'] < $news['pages']): ?><a class="btn sm" href="<?= e($q(['np' => $news['page'] + 1])) ?>">›</a><?php endif; ?>
  </nav><?php endif; ?></section>
<?php endif; ?>
<?php foreach ($widgets as $w): ?><section class="card"><?= $w ?></section><?php endforeach; ?>
<?php if (!$plugins): ?>
  <section class="card hint"><h3><?= e(t('dash.empty_title')) ?></h3><p><?= e(t($isAdmin ? 'dash.empty_admin' : 'dash.empty_user')) ?></p>
    <?php if ($isAdmin): ?><a class="btn primary" href="<?= e(url('/plugins')) ?>"><?= e(t('nav.plugins')) ?></a><?php endif; ?></section>
<?php else: ?>
  <h2><?= e(t('dash.your_components')) ?></h2>
  <div class="tiles"><?php foreach ($plugins as $slug => $m): $first = null; ?>
    <a class="card tile" href="<?= e(url('/p/' . $slug . '/')) ?>"><h3><?= e(\Sysfera\Plugins::text($m, 'name')) ?></h3><p class="muted"><?= e(\Sysfera\Plugins::text($m, 'description')) ?></p><small class="muted">v<?= e($m['version']) ?></small></a>
  <?php endforeach; ?></div>
<?php endif; ?>
