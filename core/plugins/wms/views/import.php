<?php /** @var \Sysfera\Plugin $sf @var string $tab @var ?array $job @var string $token @var string $step @var array $cat @var array $templates @var array $whs @var array $plans */
use SysferaWms\{ImportKit, ImportWms, SheetReader, W};
include __DIR__ . '/_nav.php';
/** [key, vars] -> translated text; a {field} variable is the field's label */
$msg = function (array $m, string $pfx) use ($sf): string {
    $vars = $m[1] ?? [];
    if (isset($vars['field'])) $vars['field'] = $sf->t('imp_f_' . $vars['field']);
    return $sf->t($pfx . $m[0], $vars);
};
$fileName = $job['name'] ?? '';
?>
<div class="page-h"><h1><?= e($sf->t('imp_title')) ?></h1><a class="btn" href="<?= e($sf->url('/products')) ?>">← <?= e($sf->t('nav_products')) ?></a></div>

<?php if ($step === 'upload' || $step === 'map'): ?>
<form method="post" enctype="multipart/form-data" class="card imp-up"><?= csrf_field() ?><input type="hidden" name="do" value="upload">
  <div class="imp-row">
    <label class="btn primary imp-pick">⇪ <?= e($sf->t('imp_upload')) ?><input type="file" name="file" accept=".xlsx,.xlsm,.csv,.txt,.tsv" hidden onchange="document.getElementById('imp-name').textContent=this.files[0]?this.files[0].name:''"></label>
    <div class="muted sm"><?= e($sf->t('imp_hint')) ?></div>
  </div>
  <div class="imp-row">
    <div class="imp-name" id="imp-name"><?= $fileName !== '' ? e($fileName) : '<span class="muted">' . e($sf->t('imp_no_file')) . '</span>' ?></div>
    <button class="btn primary"><?= e($sf->t($job ? 'imp_another' : 'imp_start')) ?></button>
  </div>
  <p class="muted sm" style="margin:0"><?= e($sf->t('imp_limits', ['mb' => SheetReader::MAX_BYTES >> 20, 'rows' => ImportKit::MAX_ROWS])) ?></p>
</form>
<?php endif; ?>

<?php if ($step === 'map' && $job):
    $t = $job['table']; $headers = $t['headers'];
    $guess = ImportKit::guess($headers, $cat);
    $tpl = ImportKit::pickTemplate($headers, $templates);
    $ticked = [];
    if ($tpl) { $guess = ImportKit::applyTemplate($guess, $headers, $tpl); foreach ($headers as $c => $h) if (isset($tpl['mapping'][ImportKit::norm($h)])) $ticked[$c] = true; }
    $choice = $job['choice'] ?: $guess;
    if ($job['choice']) $ticked = array_fill_keys($job['ticked'] ?? [], true);
    $sample = array_slice($t['rows'], 0, 1, true); $sample = $sample ? reset($sample) : [];
    $tplName = $job['tpl_name'] ?? ($tpl['name'] ?? preg_replace('/[_\s]*\d{2}\.\d{2}\.\d{4}$/u', '', pathinfo($fileName, PATHINFO_FILENAME)));
    $visible = array_filter($job['sheets'], fn($s) => !$s['hidden'] && $s['rows'] > 0);
    $mode = $job['opts']['stock'] ?? 'new'; $wh = (int)($job['opts']['wh'] ?? 0);
?>
<div class="card">
  <h3><?= e($sf->t('imp_converter')) ?></h3>
  <p class="muted sm"><?= e($sf->t('imp_found', ['n' => count($t['rows']), 'sheet' => $t['sheet'], 'row' => $t['header_row'] + 1])) ?>
    <?php if ($tpl): ?><span class="badge ok"><?= e($sf->t('imp_tpl_applied', ['name' => $tpl['name']])) ?></span><?php else: ?><?= e($sf->t('imp_auto')) ?><?php endif; ?></p>
  <form method="post" class="imp-reparse"><?= csrf_field() ?><input type="hidden" name="do" value="reparse"><input type="hidden" name="t" value="<?= e($token) ?>">
    <?php if (count($visible) > 1): ?><label><?= e($sf->t('imp_sheet')) ?> <select name="sheet"><?php foreach ($job['sheets'] as $i => $s): if ($s['hidden'] || $s['rows'] < 1) continue; ?><option value="<?= $i ?>" <?= $i === $t['sheetIndex'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?></select></label>
    <?php else: ?><input type="hidden" name="sheet" value="<?= (int)$t['sheetIndex'] ?>"><?php endif; ?>
    <label><?= e($sf->t('imp_header_row')) ?> <input type="number" name="header_row" min="1" max="50" value="<?= $t['header_row'] + 1 ?>" style="width:72px"></label>
    <button class="btn sm"><?= e($sf->t('imp_reread')) ?></button>
  </form>

  <form method="post" id="imp-map"><?= csrf_field() ?><input type="hidden" name="do" value="preview"><input type="hidden" name="t" value="<?= e($token) ?>">
    <div class="imp-scroll"><table class="tbl">
      <thead><tr><th><?= e($sf->t('imp_col_uploaded')) ?></th><th><?= e($sf->t('imp_col_example')) ?></th><th><?= e($sf->t('imp_col_match')) ?></th><th style="text-align:center"><?= e($sf->t('imp_col_tpl')) ?></th><th><?= e($sf->t('imp_col_custom')) ?></th></tr></thead>
      <tbody><?php foreach ($headers as $c => $h): $ch = $choice[$c] ?? ['field' => 'as_is', 'name' => '']; ?>
        <tr><td><b><?= e(ImportKit::label($h) ?: $sf->t('imp_column', ['n' => $c + 1])) ?></b></td>
          <td class="muted sm imp-ex"><?= e(ImportKit::cut((string)($sample[$c] ?? ''), 60)) ?></td>
          <td><select name="field[<?= $c ?>]"><?php foreach (ImportWms::CHOICES as $k): ?><option value="<?= $k ?>" <?= $ch['field'] === $k ? 'selected' : '' ?>><?= e($sf->t('imp_f_' . $k)) ?></option><?php endforeach; ?></select></td>
          <td style="text-align:center"><input type="checkbox" name="tpl[<?= $c ?>]" value="1" <?= !empty($ticked[$c]) ? 'checked' : '' ?>></td>
          <td><input type="text" name="name[<?= $c ?>]" value="<?= e($ch['name']) ?>" maxlength="120"></td></tr>
      <?php endforeach; ?></tbody></table></div>
    <p class="muted sm"><?= $sf->t('imp_help') ?></p>
    <div class="cols">
      <label class="fld"><span><?= e($sf->t('imp_stock_mode')) ?></span><select name="stock">
        <?php foreach (['new' => 'imp_sm_new', 'set' => 'imp_sm_set', 'none' => 'imp_sm_none'] as $k => $l): ?><option value="<?= $k ?>" <?= $mode === $k ? 'selected' : '' ?>><?= e($sf->t($l)) ?></option><?php endforeach; ?></select></label>
      <label class="fld"><span><?= e($sf->t('imp_wh')) ?></span><select name="wh"><?php foreach ($whs as $w): ?><option value="<?= (int)$w['id'] ?>" <?= $wh === (int)$w['id'] ? 'selected' : '' ?>><?= e($w['name']) ?></option><?php endforeach; ?></select></label>
      <label class="fld"><span><?= e($sf->t('imp_tpl_name')) ?></span><input type="text" name="tpl_name" value="<?= e($tplName) ?>" maxlength="190"></label>
    </div>
    <div class="imp-actions">
      <button class="btn" type="submit" form="imp-cancel"><?= e($sf->t('imp_cancel')) ?></button>
      <button class="btn primary"><?= e($sf->t('imp_finish')) ?></button>
    </div>
  </form>
  <form method="post" id="imp-cancel"><?= csrf_field() ?><input type="hidden" name="do" value="cancel"><input type="hidden" name="t" value="<?= e($token) ?>"></form>
</div>
<?php endif; ?>

<?php if ($step === 'preview' && $job):
    $n = ['create' => 0, 'update' => 0, 'error' => 0, 'warn' => 0]; $units = 0.0; $stocked = 0; $opts = $job['opts'];
    foreach ($plans as $p) {
        $n[$p['plan']['action']]++; if ($p['plan']['warnings']) $n['warn']++;
        $q = $p['plan']['d']['qty'] ?? null;
        if ($q !== null && $p['plan']['action'] !== 'error' && ($p['plan']['action'] === 'create' ? $q > 0 : $opts['stock'] === 'set')) { $stocked++; if ($p['plan']['action'] === 'create') $units += $q; }
    }
    $ok = $n['create'] + $n['update'];
    $bad = array_values(array_filter($plans, fn($p) => $p['plan']['action'] === 'error'));
    $warn = []; foreach ($plans as $p) foreach ($p['plan']['warnings'] as $w) $warn[] = [$p['row']['line'], $w];
?>
<div class="card">
  <h3><?= e($sf->t('imp_check')) ?></h3>
  <div class="imp-stats">
    <div><b><?= $n['create'] ?></b><span><?= e($sf->t('imp_s_new')) ?></span></div>
    <div><b><?= $n['update'] ?></b><span><?= e($sf->t('imp_s_upd')) ?></span></div>
    <div class="<?= $n['error'] ? 'bad' : '' ?>"><b><?= $n['error'] ?></b><span><?= e($sf->t('imp_s_err')) ?></span></div>
    <div><b><?= $n['warn'] ?></b><span><?= e($sf->t('imp_s_warn')) ?></span></div>
    <?php if ($opts['stock'] !== 'none'): ?><div><b><?= e(W::qty($units)) ?></b><span><?= e($sf->t('imp_s_stock', ['n' => $stocked])) ?></span></div><?php endif; ?>
  </div>
  <?php if ($ok): ?><div class="imp-scroll"><table class="tbl"><thead><tr><th><?= e($sf->t('imp_row_h')) ?></th><th><?= e($sf->t('imp_action')) ?></th><th><?= e($sf->t('col_sku')) ?></th><th><?= e($sf->t('col_name')) ?></th><th class="num"><?= e($sf->t('col_sell_price')) ?></th><th class="num"><?= e($sf->t('imp_qty')) ?></th></tr></thead><tbody>
    <?php $shown = 0; foreach ($plans as $p): if ($p['plan']['action'] === 'error') continue; if (++$shown > 12) break; $d = $p['plan']['d']; $ex = $p['ex']; ?>
      <tr><td><?= (int)$p['row']['line'] ?></td><td><?= $p['plan']['action'] === 'create' ? '<span class="badge ok">' . e($sf->t('imp_new')) . '</span>' : '<span class="badge mute">' . e($sf->t('imp_update')) . '</span>' ?></td>
        <td><?= e($d['sku'] ?? ($ex['sku'] ?? '—')) ?></td><td><?= e(ImportKit::cut($d['name'] ?? ($ex['name'] ?? ''), 70)) ?></td>
        <td class="num"><?= isset($d['sell']) ? e(W::money($d['sell'])) : ($ex ? '<span class="muted">' . e(W::money($ex['sell_price'])) . '</span>' : '—') ?></td>
        <td class="num"><?= isset($d['qty']) ? e(W::qty($d['qty'])) : '—' ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
    <?php if ($ok > 12): ?><p class="muted sm"><?= e($sf->t('imp_first', ['n' => 12, 'total' => $ok])) ?></p><?php endif; ?>
  <?php endif; ?>
  <?php if ($bad): ?><h3 style="margin-top:18px"><?= e($sf->t('imp_errors_h')) ?></h3>
    <ul class="imp-errs"><?php foreach (array_slice($bad, 0, 40) as $p): ?><li><b><?= e($sf->t('imp_row', ['n' => $p['row']['line']])) ?>:</b> <?= e(implode(' ', array_map(fn($m) => $msg($m, 'imp_e_'), $p['plan']['errors']))) ?></li><?php endforeach; ?></ul>
    <?php if (count($bad) > 40): ?><p class="muted sm"><?= e($sf->t('imp_more', ['n' => count($bad) - 40])) ?></p><?php endif; ?><?php endif; ?>
  <?php if ($warn): ?><h3 style="margin-top:18px"><?= e($sf->t('imp_notes_h')) ?></h3>
    <ul class="imp-errs warn"><?php foreach (array_slice($warn, 0, 15) as [$ln, $w]): ?><li><b><?= e($sf->t('imp_row', ['n' => $ln])) ?>:</b> <?= e($msg($w, 'imp_w_')) ?></li><?php endforeach; ?></ul>
    <?php if (count($warn) > 15): ?><p class="muted sm"><?= e($sf->t('imp_more', ['n' => count($warn) - 15])) ?></p><?php endif; ?><?php endif; ?>
  <div class="imp-actions">
    <a class="btn" href="<?= e($sf->url('/import?t=' . $token)) ?>"><?= e($sf->t('imp_back')) ?></a>
    <?php if ($ok): ?><form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="do" value="run"><input type="hidden" name="t" value="<?= e($token) ?>"><button class="btn primary"><?= e($sf->t('imp_go', ['n' => $ok])) ?></button></form><?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php if ($step === 'done' && $job): $rep = $job['report']; ?>
<div class="card">
  <h3><?= e($sf->t('imp_done')) ?></h3>
  <div class="imp-stats">
    <div><b><?= (int)$rep['created'] ?></b><span><?= e($sf->t('imp_created')) ?></span></div><div><b><?= (int)$rep['updated'] ?></b><span><?= e($sf->t('imp_updated')) ?></span></div>
    <div class="<?= $rep['failed'] ? 'bad' : '' ?>"><b><?= (int)$rep['failed'] ?></b><span><?= e($sf->t('imp_s_err')) ?></span></div>
    <?php if ((float)$rep['units'] > 0): ?><div><b><?= e(W::qty($rep['units'])) ?></b><span><?= e($sf->t('imp_received')) ?></span></div><?php endif; ?>
  </div>
  <?php if (!empty($rep['template'])): ?><p class="muted sm"><?= e($sf->t('imp_tpl_saved', ['name' => $rep['template']])) ?></p><?php endif; ?>
  <?php foreach ($rep['docs'] as $d): ?><p><?= $d['ok'] && $d['id'] ? '<a href="' . e($sf->url('/docs/' . (int)$d['id'])) . '">' . e($d['number']) . '</a> · ' . e($sf->t('imp_doc_posted', ['n' => $d['lines']])) : '<span class="bad-t">' . e($sf->t('imp_doc_failed', ['type' => $sf->t('type_' . $d['type'])])) . '</span>' . ($d['id'] ? ' <a href="' . e($sf->url('/docs/' . (int)$d['id'])) . '">' . e($d['number']) . '</a>' : '') ?></p><?php endforeach; ?>
  <?php if ($rep['errors']): ?><h3 style="margin-top:18px"><?= e($sf->t('imp_errors_h')) ?></h3><ul class="imp-errs"><?php foreach (array_slice($rep['errors'], 0, 100) as [$ln, $ms]): ?><li><b><?= e($sf->t('imp_row', ['n' => $ln])) ?>:</b> <?= e(implode(' ', array_map(fn($m) => $msg($m, 'imp_e_'), $ms))) ?></li><?php endforeach; ?></ul><?php endif; ?>
  <?php if ($rep['warnings']): ?><h3 style="margin-top:18px"><?= e($sf->t('imp_notes_h')) ?></h3><ul class="imp-errs warn"><?php foreach (array_slice($rep['warnings'], 0, 60) as [$ln, $w]): ?><li><b><?= e($sf->t('imp_row', ['n' => $ln])) ?>:</b> <?= e($msg($w, 'imp_w_')) ?></li><?php endforeach; ?></ul><?php endif; ?>
  <div class="imp-actions"><a class="btn" href="<?= e($sf->url('/import')) ?>"><?= e($sf->t('imp_another_file')) ?></a><a class="btn primary" href="<?= e($sf->url('/products')) ?>"><?= e($sf->t('imp_goto_products')) ?></a></div>
</div>
<?php endif; ?>
