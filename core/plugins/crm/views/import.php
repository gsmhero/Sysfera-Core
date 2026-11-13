<?php /** @var \Sysfera\Plugin $sf @var ?array $res @var list<array> $pipelines */
$tab = 'import'; $prof = ['import' => true]; require __DIR__ . '/_nav.php'; ?>
<div class="page-h"><h1><?= e($sf->t('import')) ?></h1></div>
<?php if ($res): ?><div class="flash ok"><?= e($sf->t('imp_done', ['n' => $res['ok'], 'm' => $res['skip']])) ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="card crm-form"><?= csrf_field() ?>
  <p class="muted"><?= e($sf->t('imp_hint')) ?></p>
  <div class="cols"><label class="fld"><span><?= e($sf->t('imp_what')) ?></span><select name="kind" id="impkind"><option value="contacts"><?= e($sf->t('contacts')) ?></option><option value="deals"><?= e($sf->t('deals')) ?></option></select></label>
    <label class="fld" id="imppipe" hidden><span><?= e($sf->t('f_pipeline')) ?></span><select name="pipeline_id"><?php foreach ($pipelines as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?></select></label></div>
  <label class="fld"><span>CSV (UTF-8, ≤ 2 MB, ≤ 2000)</span><input type="file" name="csv" accept=".csv,text/csv,text/plain" required></label>
  <button class="btn primary"><?= e($sf->t('import')) ?></button></form>
<script>(function(){var k=document.getElementById('impkind'),p=document.getElementById('imppipe');function u(){p.hidden=k.value!=='deals'}k.onchange=u;u()})();</script>
