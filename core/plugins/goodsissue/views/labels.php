<?php /** @var \Sysfera\Plugin $sf @var string $tab @var string $kind @var array $labels @var string $err @var array $transfers @var string $tu @var array $q */
include __DIR__ . '/_nav.php'; ?>
<div class="page-h gi-noprint"><h1><?= e($sf->t('nav_labels')) ?></h1><?php if ($labels): ?><button class="btn primary" onclick="window.print()"><?= e($sf->t('print')) ?> (<?= count($labels) ?>)</button><?php endif; ?></div>
<?php if ($err): ?><div class="flash bad"><?= e($err) ?></div><?php endif; ?>
<div class="hint gi-noprint"><?= e($sf->t('lbl_hint')) ?></div>
<form method="get" class="card gi-noprint"><div class="row"><?php foreach (['orders', 'items', 'shelves'] as $k): ?><label><input type="radio" name="kind" value="<?= $k ?>" <?= $kind === $k ? 'checked' : '' ?> style="width:auto"> <?= e($sf->t('lbl_k_' . $k)) ?></label> <?php endforeach; ?></div>
  <div class="row" style="margin-top:8px"><select name="transfer"><option value=""><?= e($sf->t('lbl_pick_transfer')) ?></option><?php foreach ($transfers as $t): ?><option value="<?= e($t['uuid']) ?>" <?= $tu === $t['uuid'] ? 'selected' : '' ?>><?= e(substr($t['uuid'], 0, 8)) ?> · <?= e((string)$t['wname']) ?> · <?= e($t['created_at']) ?></option><?php endforeach; ?></select></div>
  <div class="row" style="margin-top:8px"><span class="muted"><?= e($sf->t('lbl_shelves')) ?></span><input name="prefix" value="<?= e((string)($q['prefix'] ?? 'A')) ?>" size="6" maxlength="12"><input name="from" type="number" min="0" value="<?= e((string)($q['from'] ?? 1)) ?>" style="width:80px"><input name="to" type="number" min="0" value="<?= e((string)($q['to'] ?? 12)) ?>" style="width:80px">
    <input name="codes" value="<?= e((string)($q['codes'] ?? '')) ?>" placeholder="<?= e($sf->t('lbl_codes')) ?>"></div>
  <p><button class="btn primary"><?= e($sf->t('lbl_make')) ?></button></p></form>
<style>.gi-labels{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:8px}.gi-label{border:1px dashed #999;padding:8px;background:#fff;color:#000;break-inside:avoid;text-align:center;overflow:hidden}.gi-label svg{max-width:100%;height:auto}.gi-label b{display:block;font-size:14px}.gi-label small{display:block;color:#444;font-size:11px}
@media print{.gi-noprint,.wtabs,header,nav,aside{display:none!important}.gi-labels{gap:4px}body{background:#fff}}</style>
<div class="gi-labels"><?php foreach ($labels as $l): ?><div class="gi-label"><?= $l['svg'] ?><b><?= e($l['title']) ?></b><small><?= e($l['sub']) ?></small><small><?= e($l['text']) ?></small></div><?php endforeach; ?></div>
