<?php /** @var \Sysfera\Plugin $sf @var string $tab @var array $rows @var int $total @var array $pg @var array $f */
use SysferaWms\{W, Posting};
include __DIR__ . '/_nav.php';
$qs = fn(array $o = []) => '?' . http_build_query(array_filter(array_merge(['group' => $f['group'], 'type' => $f['type'], 'status' => $f['status'], 'q' => $f['q']], $o), fn($v) => $v !== '' && $v !== null));
$groups = ['' => 'grp_all', 'stock' => 'grp_stock', 'buy' => 'grp_buy', 'sell' => 'grp_sell']; ?>
<div class="page-h"><h1><?= e($sf->t('nav_docs')) ?> <small class="muted"><?= (int)$total ?></small></h1>
  <div class="menu"><button class="btn primary" type="button" data-menu>＋ <?= e($sf->t('new_doc')) ?> ▾</button>
    <div class="menu-body">
      <?php foreach (['stock' => ['grp_stock', ['receipt', 'writeoff', 'transfer', 'inventory']], 'buy' => ['grp_buy', ['purchase', 'receiving']], 'sell' => ['grp_sell', ['sale', 'shipment']]] as $g => [$gl, $types]): ?>
        <div class="menu-h"><?= e($sf->t($gl)) ?></div><?php foreach ($types as $t): ?><a href="<?= e($sf->url('/docs/new/' . $t)) ?>"><?= e($sf->t('type_' . $t)) ?><small><?= e($sf->t('hint_' . $t)) ?></small></a><?php endforeach; ?>
      <?php endforeach; ?></div></div></div>

<form class="bar" method="get">
  <div class="seg"><?php foreach ($groups as $k => $l): ?><a class="<?= $f['group'] === $k && !$f['type'] ? 'on' : '' ?>" href="<?= e($sf->url('/docs') . $qs(['group' => $k, 'type' => '', 'page' => ''])) ?>"><?= e($sf->t($l)) ?></a><?php endforeach; ?></div>
  <select name="type" onchange="this.form.submit()"><option value=""><?= e($sf->t('all_types')) ?></option><?php foreach (array_keys(Posting::TYPES) as $t): ?><option value="<?= $t ?>" <?= $f['type'] === $t ? 'selected' : '' ?>><?= e($sf->t('type_' . $t)) ?></option><?php endforeach; ?></select>
  <select name="status" onchange="this.form.submit()"><option value=""><?= e($sf->t('all_statuses')) ?></option><?php foreach (['draft', 'posted', 'cancelled'] as $s): ?><option value="<?= $s ?>" <?= $f['status'] === $s ? 'selected' : '' ?>><?= e($sf->t('status_' . $s)) ?></option><?php endforeach; ?></select>
  <input name="q" value="<?= e($f['q']) ?>" placeholder="<?= e($sf->t('search_docs')) ?>"><input type="hidden" name="group" value="<?= e($f['group']) ?>"><button class="btn"><?= e($sf->t('find')) ?></button>
</form>

<?php if (!$rows): ?><div class="card empty"><h3><?= e($sf->t('no_docs_found')) ?></h3><p><?= e($sf->t('start_hint')) ?></p></div>
<?php else: ?>
<div class="card flush"><table class="tbl">
  <thead><tr><th><?= e($sf->t('doc')) ?></th><th><?= e($sf->t('date')) ?></th><th><?= e($sf->t('warehouse')) ?></th><th><?= e($sf->t('partner')) ?></th><th class="num"><?= e($sf->t('col_total')) ?></th><th><?= e($sf->t('status')) ?></th></tr></thead>
  <tbody><?php foreach ($rows as $d): ?>
    <tr onclick="location='<?= e($sf->url('/docs/' . $d['id'])) ?>'"><td><b><?= e($d['number']) ?></b><br><small class="muted"><?= e(W::type($sf, $d['type'])) ?></small></td><td><?= e(W::date($d['moment'])) ?></td>
      <td class="muted"><?= e($d['warehouse'] ?? '—') ?></td><td><?= !empty($d['private']) ? '<span class="muted">🔒 ' . e($sf->t('private_short')) . '</span>' : e($d['contact'] ?? '—') ?></td><td class="num"><?= $d['total'] > 0 ? e(W::money($d['total'])) : '<span class="muted">—</span>' ?></td>
      <td><?= W::badge($sf, $d['status']) ?><?php if (!empty($d['progress']) && $d['status'] === 'posted'): $pr = $d['progress']; $pc = $pr['ordered'] > 0 ? min(100, (int)round($pr['done'] / $pr['ordered'] * 100)) : 0; ?><div class="bar-mini" title="<?= e(W::qty($pr['done']) . ' / ' . W::qty($pr['ordered'])) ?>"><i style="width:<?= $pc ?>%"></i></div><?php endif; ?></td></tr>
  <?php endforeach; ?></tbody></table></div>
<?php if ($pg['pages'] > 1): ?><div class="pager"><?php for ($i = 1; $i <= $pg['pages']; $i++): ?><a class="<?= $i === $pg['cur'] ? 'on' : '' ?>" href="<?= e($sf->url('/docs') . $qs(['page' => $i])) ?>"><?= $i ?></a><?php endfor; ?></div><?php endif; ?>
<?php endif; ?>
