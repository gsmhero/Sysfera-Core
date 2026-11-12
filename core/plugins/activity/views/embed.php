<?php /** @var \Sysfera\Plugin $sf @var bool $on @var bool $widget @var string $origin @var string $script */ ?>
<link rel="stylesheet" href="<?= e($sf->asset('activity.css')) ?>?v=<?= e($sf->manifest['version']) ?>">
<div class="page-h"><h1><?= e($sf->t('embed')) ?></h1><a class="btn" href="<?= e($sf->url('/')) ?>">← <?= e($sf->t('title')) ?></a></div>

<form class="card" method="post"><?= csrf_field() ?>
  <label class="chk"><input type="checkbox" name="widget" value="1" <?= $widget ? 'checked' : '' ?>> <?= e($sf->t('e_widget')) ?></label>
  <p class="muted sm"><?= e($sf->t('e_widget_hint')) ?></p>
  <label class="chk"><input type="checkbox" name="embed_on" value="1" <?= $on ? 'checked' : '' ?>> <?= e($sf->t('e_on')) ?></label>
  <p class="muted sm"><?= e($sf->t('e_on_hint')) ?></p>
  <button class="btn primary"><?= e($sf->t('save')) ?></button></form>

<section class="card"><h3><?= e($sf->t('e_code')) ?></h3>
  <?php if (!$on): ?><p class="muted"><?= e($sf->t('e_off')) ?></p><?php endif; ?>
  <div class="cols">
    <label class="fld"><span><?= e($sf->t('e_count')) ?></span><select id="oc"><?php foreach ([3, 5, 8, 10] as $n): ?><option value="<?= $n ?>" <?= $n === 5 ? 'selected' : '' ?>><?= $n ?></option><?php endforeach; ?></select></label>
    <label class="fld"><span><?= e($sf->t('e_height')) ?></span><select id="oh"><?php foreach ([120, 160, 220, 300] as $n): ?><option value="<?= $n ?>" <?= $n === 160 ? 'selected' : '' ?>><?= $n ?> px</option><?php endforeach; ?></select></label>
    <label class="fld"><span><?= e($sf->t('e_theme')) ?></span><select id="ot"><option value="light"><?= e($sf->t('e_light')) ?></option><option value="dark"><?= e($sf->t('e_dark')) ?></option></select></label>
  </div>
  <textarea id="code" rows="4" readonly style="width:100%;font-family:monospace" onclick="this.select()"></textarea>
  <p class="muted sm"><?= e($sf->t('e_paste')) ?></p>
  <?php if ($on): ?><h3><?= e($sf->t('e_preview')) ?></h3><div id="pv" style="border:1px dashed var(--line);border-radius:8px;padding:10px"></div><?php endif; ?>
</section>
<script>
(function () {
  var script = <?= json_encode($script) ?>, on = <?= $on ? 'true' : 'false' ?>;
  var c = document.getElementById('oc'), h = document.getElementById('oh'), t = document.getElementById('ot'), code = document.getElementById('code'), pv = document.getElementById('pv');
  function make() {
    var d = '<div data-sysfera-activity data-count="' + c.value + '" data-height="' + h.value + '" data-theme="' + t.value + '"></div>\n<script async src="' + script + '"><\/script>';
    code.value = d;
    if (on && pv) { pv.innerHTML = ''; var el = document.createElement('div'); el.setAttribute('data-sysfera-activity', ''); el.setAttribute('data-count', c.value); el.setAttribute('data-height', h.value); el.setAttribute('data-theme', t.value); pv.appendChild(el);
      var s = document.createElement('script'); s.src = script + '&r=' + Date.now(); pv.appendChild(s); }
  }
  [c, h, t].forEach(function (x) { x.addEventListener('change', make); }); make();
})();
</script>
