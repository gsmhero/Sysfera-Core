<?php /** @var \Sysfera\Plugin $sf @var array $list @var array $f @var array $cats */
use SysferaContracts\Svc;
include __DIR__ . '/_nav.php'; $q = (string)($f['q'] ?? ''); $cat = (int)($f['category'] ?? 0); $pages = (int)ceil($list['total'] / max(1, $list['per'])); ?>
<div class="page-h"><h1><?= e($sf->t('nav_list')) ?></h1></div>
<form class="ct-filters" method="get" action="<?= e($sf->url('/')) ?>">
  <label><?= e($sf->t('search')) ?><input type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e($sf->t('search_ph')) ?>"></label>
  <label><?= e($sf->t('category')) ?><select name="category"><option value="0">—</option><?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $cat === (int)$c['id'] ? 'selected' : '' ?>><?= e($lang === 'ru' && $c['name_ru'] ? $c['name_ru'] : $c['name']) ?></option><?php endforeach; ?></select></label>
  <label><?= e($sf->t('sort')) ?><select name="sort"><option value="date" <?= $list['sort'] === 'date' ? 'selected' : '' ?>><?= e($sf->t('sort_date')) ?></option><option value="price" <?= $list['sort'] === 'price' ? 'selected' : '' ?>><?= e($sf->t('sort_price')) ?></option></select></label>
  <label><?= e($sf->t('price_from')) ?><input name="from" inputmode="numeric" size="8" value="<?= e((string)($f['from'] ?? '')) ?>"></label>
  <label><?= e($sf->t('price_to')) ?><input name="to" inputmode="numeric" size="8" value="<?= e((string)($f['to'] ?? '')) ?>"></label>
  <button class="btn primary"><?= e($sf->t('apply')) ?></button><?php if ($q !== '' || $cat || !empty($f['from']) || !empty($f['to'])): ?><a class="btn" href="<?= e($sf->url('/')) ?>"><?= e($sf->t('reset')) ?></a><?php endif; ?>
</form>
<?php if (!$list['rows']): ?><div class="card empty"><p><?= e($sf->t('none_found')) ?></p></div><?php endif; ?>
<?php foreach ($list['rows'] as $k) include __DIR__ . '/_card.php'; ?>
<?php if ($pages > 1): ?><div class="ct-pager"><?php for ($i = 1; $i <= $pages; $i++): ?><a class="<?= $i === $list['page'] ? 'cur' : '' ?>" href="<?= e($sf->url('/?' . http_build_query(array_filter(['q' => $q, 'category' => $cat, 'sort' => $list['sort'], 'from' => $f['from'] ?? '', 'to' => $f['to'] ?? '', 'page' => $i], fn($v) => $v !== '' && $v !== 0)))) ?>"><?= $i ?></a><?php endfor; ?></div><?php endif; ?>
