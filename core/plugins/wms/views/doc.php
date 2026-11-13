<?php /** @var \Sysfera\Plugin $sf @var string $tab @var ?array $doc @var string $type @var array $lines @var array $whs @var array $cells @var array $partners @var ?array $parent @var ?array $progress @var array $children @var int $defaultWh */
use SysferaWms\{W, Posting};
include __DIR__ . '/_nav.php';
$id = $doc['id'] ?? 0; $status = $doc['status'] ?? 'draft'; $draft = $status === 'draft';
$withPrice = in_array($type, ['receipt', 'purchase', 'receiving', 'sale', 'shipment'], true);
$physical = Posting::physical($type);
$withContact = in_array($type, ['purchase', 'receiving', 'sale', 'shipment'], true);
$role = in_array($type, ['purchase', 'receiving'], true) ? 'supplier' : 'customer';
usort($partners, fn($a, $b) => (in_array($role, $b['roles'], true) <=> in_array($role, $a['roles'], true)) ?: strcmp($a['name'], $b['name']));
$cur = $doc ? '/docs/' . $id : '/docs/new/' . $type;
$whId = (int)($doc['warehouse_id'] ?? $defaultWh);
$cfg = ['type' => $type, 'draft' => $draft, 'withPrice' => $withPrice, 'physical' => $physical, 'transfer' => $type === 'transfer', 'inventory' => $type === 'inventory',
    'api' => $sf->url('/api/products'), 'apiStock' => $sf->url('/api/stock'), 'cells' => $cells, 'whs' => array_column($whs, 'use_cells', 'id'),
    'lines' => array_map(fn($l) => ['product_id' => (int)$l['product_id'], 'name' => $l['name'], 'sku' => $l['sku'], 'unit' => $l['unit'], 'qty' => (float)$l['qty'], 'price' => (float)$l['price'], 'cell_id' => (int)$l['cell_id'], 'to_cell_id' => (int)$l['to_cell_id']], $lines),
    't' => ['available' => $sf->t('available_short'), 'nothing' => $sf->t('nothing_found'), 'noCell' => $sf->t('choose_cell'), 'del' => $sf->t('delete'), 'loaded' => $sf->t('stock_loaded'), 'need_wh' => $sf->t('err_warehouse')]];
?>
<script>window.WMS = <?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>

<div class="page-h">
  <h1><?= e($doc ? $doc['number'] : $sf->t('new_doc')) ?> <small class="muted"><?= e(W::type($sf, $type)) ?></small> <?= $doc ? W::badge($sf, $status) : '' ?></h1>
  <a class="btn" href="<?= e($sf->url('/docs')) ?>">← <?= e($sf->t('nav_docs')) ?></a>
</div>
<div class="hint"><?= e($sf->t('hint_' . $type)) ?></div>

<?php if ($progress && $status === 'posted'): $pc = $progress['ordered'] > 0 ? min(100, (int)round($progress['done'] / $progress['ordered'] * 100)) : 0; ?>
  <div class="card docprog"><div class="row between"><b><?= e($sf->t($type === 'purchase' ? 'received_of' : 'shipped_of', ['done' => W::qty($progress['done']), 'all' => W::qty($progress['ordered'])])) ?></b>
    <?php if ($progress['done'] + 0.0000001 < $progress['ordered']): ?><form method="post" action="<?= e($sf->url('/docs/act')) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>"><input type="hidden" name="do" value="child"><button class="btn primary"><?= e($sf->t($type === 'purchase' ? 'make_receiving' : 'make_shipment')) ?> →</button></form><?php endif; ?></div>
    <div class="bar-mini big"><i style="width:<?= $pc ?>%"></i></div></div>
<?php endif; ?>
<?php if ($parent): ?><p class="sm">↳ <?= e($sf->t('based_on')) ?> <a href="<?= e($sf->url('/docs/' . $parent['id'])) ?>"><b><?= e($parent['number']) ?></b></a> (<?= e(W::type($sf, $parent['type'])) ?>)</p><?php endif; ?>
<?php if ($children): ?><p class="sm"><?= e($sf->t('followups')) ?>: <?php foreach ($children as $c): ?><a class="chip" href="<?= e($sf->url('/docs/' . $c['id'])) ?>"><?= e($c['number']) ?> <?= W::badge($sf, $c['status']) ?></a> <?php endforeach; ?></p><?php endif; ?>

<form method="post" action="<?= e($sf->url('/docs/save')) ?>" id="docform" class="docform"><?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)$id ?>"><input type="hidden" name="type" value="<?= e($type) ?>"><input type="hidden" name="parent_id" value="<?= (int)($doc['parent_id'] ?? 0) ?>">
  <fieldset class="card" <?= $draft ? '' : 'disabled' ?>>
    <div class="cols">
      <label class="fld"><span><?= e($sf->t('date')) ?></span><input type="datetime-local" name="moment" value="<?= e(W::input($doc['moment'] ?? null)) ?>" required></label>
      <label class="fld"><span><?= e($sf->t($type === 'transfer' ? 'from_warehouse' : 'warehouse')) ?> *</span>
        <select name="warehouse_id" id="wh" required><?php foreach ($whs as $w): ?><option value="<?= (int)$w['id'] ?>" <?= $whId === (int)$w['id'] ? 'selected' : '' ?>><?= e($w['name']) ?></option><?php endforeach; ?></select></label>
      <?php if ($type === 'transfer'): ?><label class="fld"><span><?= e($sf->t('to_warehouse')) ?> *</span>
        <select name="to_warehouse_id" id="wh2" required><?php foreach ($whs as $w): ?><option value="<?= (int)$w['id'] ?>" <?= (int)($doc['to_warehouse_id'] ?? 0) === (int)$w['id'] ? 'selected' : '' ?>><?= e($w['name']) ?></option><?php endforeach; ?></select></label><?php endif; ?>
      <?php if ($withContact): ?><label class="fld"><span><?= e($sf->t($role)) ?><?= Posting::needsContact($type) ? ' *' : '' ?></span>
        <select name="contact_id" <?= Posting::needsContact($type) ? 'required' : '' ?>><option value=""><?= $partners ? '—' : e($sf->t('no_partners_yet')) ?></option>
          <?php foreach ($partners as $p): ?><option value="<?= (int)$p['id'] ?>" <?= (int)($doc['contact_id'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?><?= $p['company'] ? ' · ' . e($p['company']) : '' ?></option><?php endforeach; ?></select></label><?php endif; ?>
    </div>
    <label class="fld"><span><?= e($sf->t('comment')) ?></span><input name="comment" value="<?= e($doc['comment'] ?? '') ?>" maxlength="2000"></label>
  </fieldset>

  <section class="card flush lines" id="lines">
    <table class="tbl"><thead><tr><th class="idx">#</th><th><?= e($sf->t('col_product')) ?></th>
      <?php if ($physical): ?><th class="cellcol"><?= e($sf->t($type === 'transfer' ? 'from_cell' : 'cell')) ?></th><?php endif; ?>
      <?php if ($type === 'transfer'): ?><th class="cellcol"><?= e($sf->t('to_cell')) ?></th><?php endif; ?>
      <th class="num"><?= e($sf->t($type === 'inventory' ? 'counted' : 'col_qty')) ?></th>
      <?php if ($type === 'inventory' && $status === 'posted'): ?><th class="num"><?= e($sf->t('system_qty')) ?></th><th class="num"><?= e($sf->t('difference')) ?></th><?php endif; ?>
      <?php if ($withPrice): ?><th class="num"><?= e($sf->t('col_price')) ?></th><th class="num"><?= e($sf->t('col_sum')) ?></th><?php endif; ?>
      <?php if ($draft): ?><th></th><?php endif; ?></tr></thead>
      <?php if ($draft): ?><tbody id="rows"></tbody>
      <?php else: ?><tbody><?php foreach ($lines as $i => $l): ?><tr>
        <td class="idx"><?= $i + 1 ?></td><td><b><?= e($l['name']) ?></b><br><small class="muted"><?= e($l['sku']) ?></small></td>
        <?php if ($physical): ?><td class="muted"><?= e($l['cell'] ?? '—') ?></td><?php endif; ?><?php if ($type === 'transfer'): ?><td class="muted"><?= e($l['to_cell'] ?? '—') ?></td><?php endif; ?>
        <td class="num"><?= e(W::qty($l['qty'])) ?> <small class="muted"><?= e($l['unit']) ?></small></td>
        <?php if ($type === 'inventory' && $status === 'posted'): $df = (float)$l['qty'] - (float)$l['sys_qty']; ?><td class="num"><?= e(W::qty($l['sys_qty'])) ?></td><td class="num"><span class="<?= $df > 0 ? 'ok-t' : ($df < 0 ? 'bad-t' : 'muted') ?>"><?= $df > 0 ? '+' : '' ?><?= e(W::qty($df)) ?></span></td><?php endif; ?>
        <?php if ($withPrice): ?><td class="num"><?= e(W::money($l['price'])) ?></td><td class="num"><?= e(W::money($l['qty'] * $l['price'])) ?></td><?php endif; ?></tr><?php endforeach; ?></tbody><?php endif; ?>
      <?php if ($withPrice): ?><tfoot><tr><td colspan="<?= 2 + ($physical ? 1 : 0) + 1 ?>"></td><td class="num"><b><?= e($sf->t('col_total')) ?></b></td><td></td><td class="num"><b id="total"><?= e(W::money($doc['total'] ?? 0)) ?></b></td><?= $draft ? '<td></td>' : '' ?></tr></tfoot><?php endif; ?>
    </table>
    <?php if ($draft): ?>
    <div class="addline"><div class="search"><input type="text" id="psearch" autocomplete="off" placeholder="<?= e($sf->t('scan_hint')) ?>"><div class="results" id="presults"></div></div>
      <?php if ($type === 'inventory'): ?><button type="button" class="btn" id="loadstock"><?= e($sf->t('load_stock')) ?></button><?php endif; ?>
      <span class="muted sm"><?= e($sf->t('scan_hint')) ?></span></div>
    <?php endif; ?>
  </section>

  <div class="actionbar">
    <?php if ($draft): ?>
      <button class="btn primary" name="do" value="post"><?= e($sf->t('post')) ?></button>
      <button class="btn" name="do" value="save" formnovalidate><?= e($sf->t('save_draft')) ?></button>
      <span class="muted sm"><?= e($sf->t('post_hint')) ?></span>
    <?php endif; ?>
  </div>
</form>

<?php if ($doc): ?>
<div class="actionbar secondary">
  <?php foreach ([['copy', $status !== 'draft' || true, 'copy', ''], ['unpost', $status === 'posted', 'unpost_btn', 'confirm_unpost'], ['cancel', $status === 'posted' && in_array($type, ['purchase', 'sale'], true), 'cancel_btn', 'confirm_cancel'], ['delete', $status !== 'posted', 'delete', 'confirm_delete']] as [$do, $show, $label, $conf]): if (!$show) continue; ?>
    <form method="post" action="<?= e($sf->url('/docs/act')) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>"><input type="hidden" name="do" value="<?= $do ?>">
      <button class="btn <?= $do === 'delete' || $do === 'cancel' ? 'danger' : '' ?>" <?= $conf ? 'data-confirm="' . e($sf->t($conf)) . '"' : '' ?>><?= e($sf->t($label)) ?></button></form>
  <?php endforeach; ?>
  <button class="btn" type="button" onclick="window.print()">🖶 <?= e($sf->t('print')) ?></button>
</div>
<?php endif; ?>

<?php if ($draft && $withContact): ?>
<details class="card quick"><summary>＋ <?= e($sf->t('new_partner')) ?></summary>
  <form method="post" action="<?= e($sf->url('/partners')) ?>" class="cols"><?= csrf_field() ?><input type="hidden" name="back" value="<?= e($cur) ?>"><input type="hidden" name="roles[]" value="<?= e($role) ?>">
    <label class="fld"><span><?= e($sf->t('col_name')) ?> *</span><input name="name" required maxlength="190"></label>
    <label class="fld"><span><?= e($sf->t('phone')) ?></span><input name="phone" maxlength="60"></label>
    <label class="fld"><span>Email</span><input type="email" name="email" maxlength="190"></label>
    <div><button class="btn"><?= e($sf->t('add')) ?></button> <small class="muted"><?= e($sf->t('quick_partner_hint')) ?></small></div></form></details>
<?php endif; ?>
<script src="<?= e($sf->asset('wms.js')) ?>?v=<?= e($sf->manifest['version']) ?>" defer></script>
