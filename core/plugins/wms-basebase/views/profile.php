<?php /** @var \Sysfera\Plugin $sf @var string $tab @var array $p @var int $shop */
include __DIR__ . '/_nav.php';
$d = $p['data'] ?? []; $err = $p['error'] ?? null; $cur = (string)($d['payout_type'] ?? ''); $types = (array)($d['types'] ?? []); $vals = (array)($d['values'] ?? []);
if ($cur === '' || !isset($types[$cur])) $cur = (string)array_key_first($types); ?>
<div class="page-h"><h1><?= e($sf->t('nav_profile')) ?></h1></div>
<?php if (!$shop): ?><div class="hint warn"><?= e($sf->t('no_shop_yet')) ?> <a href="<?= e($sf->url('/shop')) ?>"><?= e($sf->t('nav_shop')) ?></a></div>
<?php elseif ($err): ?><div class="hint warn"><?= e($sf->t('wd_fail', ['e' => preg_match('/^[a-z_]+$/', (string)$err) ? $sf->t((string)$err) : $err])) ?></div>
<?php else: ?>
<div class="hint"><?= e($sf->t('pr_hint')) ?></div>
<?php if (!empty($d['missing_required'])): ?><div class="hint warn"><?= e($sf->t('pr_missing', ['list' => implode(', ', (array)$d['missing_required'])])) ?></div><?php endif; ?>
<form class="card" method="post" autocomplete="off"><?= csrf_field() ?>
  <label class="fld"><span><?= e($sf->t('pr_type')) ?></span><select name="payout_type" id="bbpt"><?php foreach ($types as $k => $ty): ?><option value="<?= e($k) ?>" <?= $k === $cur ? 'selected' : '' ?>><?= e($ty['label']) ?></option><?php endforeach; ?></select></label>
  <?php foreach ($types as $k => $ty): ?>
  <div class="bbpt" data-t="<?= e($k) ?>" <?= $k === $cur ? '' : 'hidden' ?>><h3><?= e($ty['section']) ?></h3><div class="cols">
    <?php foreach ((array)$ty['fields'] as $f): $v = (string)($vals[$k][(string)$f['id']] ?? ''); ?>
      <label class="fld"><span><?= e($f['label']) ?><?= !empty($f['required']) ? ' *' : '' ?></span>
        <input name="f[<?= e($k) ?>][<?= (int)$f['id'] ?>]" value="<?= e($v) ?>" maxlength="190" <?= ($f['type'] ?? '') === 'date' ? 'type="date"' : '' ?>></label>
    <?php endforeach; ?></div></div>
  <?php endforeach; ?>
  <div class="row"><button class="btn primary"><?= e($sf->t('save')) ?></button></div>
</form>
<script>(function(){var s=document.getElementById("bbpt");if(!s)return;function a(){document.querySelectorAll(".bbpt").forEach(function(d){d.hidden=d.getAttribute("data-t")!==s.value})}s.addEventListener("change",a)})();</script>
<?php endif; ?>