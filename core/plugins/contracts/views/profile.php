<?php /** @var \Sysfera\Plugin $sf @var array $profile @var array $types (my statuses) @var array $structure (all sections with fields) @var array $values */
use SysferaContracts\Svc;
?>
<link rel="stylesheet" href="<?= e($sf->asset('contracts.css')) ?>?v=<?= e($sf->manifest['version']) ?>">
<div class="page-h row between" id="contracts"><h2><?= e($sf->t('title')) ?></h2><a class="btn" href="<?= e($sf->url('/user/' . $me->uid)) ?>"><?= e($sf->t('public_page')) ?></a></div>
<form method="post" action="<?= e(url('/profile')) ?>" class="ct-form card" id="ct-pform"><?= csrf_field() ?><input type="hidden" name="do" value="x_contracts">
  <nav class="ct-ptabs" role="tablist"><button type="button" class="on" data-tab="general"><?= e($sf->t('pf_general')) ?></button><?php foreach (Svc::PARTY_TYPES as $t): ?><button type="button" data-tab="<?= e($t) ?>"><?= e($sf->t('pt_' . $t)) ?><?= in_array($t, $types, true) ? '' : ' <small>(' . e($sf->t('pf_off')) . ')</small>' ?></button><?php endforeach; ?></nav>
  <section data-panel="general"><p class="muted"><?= e($sf->t('my_status_help')) ?></p>
    <label class="ct-lbl"><?= e($sf->t('about')) ?><textarea name="about" rows="3" maxlength="4000"><?= e((string)$profile['about']) ?></textarea></label>
    <label><input type="checkbox" name="notify_email" value="1" <?= $profile['notify_email'] ? 'checked' : '' ?>> <?= e($sf->t('notify_email')) ?></label></section>
  <?php foreach (Svc::PARTY_TYPES as $t): $on = in_array($t, $types, true); ?><section data-panel="<?= e($t) ?>" data-type="<?= e($t) ?>">
    <label class="ct-act"><input type="checkbox" name="types[]" value="<?= e($t) ?>" <?= $on ? 'checked' : '' ?>> <b><?= e($sf->t('as_' . $t)) ?></b> <span class="muted">- <?= e($sf->t('pf_act_help')) ?></span></label>
    <div class="ct-pfields"><?php $any = false; foreach ($structure as $sec): if ($sec['type'] !== $t || !$sec['fields']) continue; $any = true; ?><h3><?= e($sec['title']) ?></h3>
      <?php foreach ($sec['fields'] as $f): $val = $values[(int)$f['id']] ?? null; include __DIR__ . '/_field.php'; endforeach; ?><?php endforeach; ?>
      <?php if (!$any): ?><p class="muted"><?= e($sf->t('pf_none')) ?></p><?php endif; ?></div></section><?php endforeach; ?>
  <p class="muted"><?= e($sf->t('fields_by_status')) ?></p>
  <button class="btn primary"><?= e($sf->t('save')) ?></button></form>
<script>(function(){var f=document.getElementById('ct-pform');if(!f)return;f.classList.add('ct-js');
function show(n){f.querySelectorAll('[data-tab]').forEach(function(b){b.classList.toggle('on',b.dataset.tab===n)});f.querySelectorAll('[data-panel]').forEach(function(p){p.hidden=p.dataset.panel!==n});}
function sync(p){var on=p.querySelector('input[name="types[]"]').checked;p.querySelectorAll('.ct-pfields input,.ct-pfields select,.ct-pfields textarea').forEach(function(i){i.disabled=!on});p.classList.toggle('ct-off',!on);var b=f.querySelector('[data-tab="'+p.dataset.panel+'"] small');if(b)b.hidden=on;}
f.querySelectorAll('[data-tab]').forEach(function(b){b.addEventListener('click',function(){show(b.dataset.tab)})});
f.querySelectorAll('[data-type]').forEach(function(p){sync(p);p.querySelector('input[name="types[]"]').addEventListener('change',function(){sync(p)})});
f.addEventListener('invalid',function(e){var p=e.target.closest('[data-panel]');if(p&&p.hidden)show(p.dataset.panel)},true);
var h=(location.hash||'').replace('#ct-','');if(h&&f.querySelector('[data-tab="'+h+'"]'))show(h);else show('general');})();</script>
<?php $bab = $bablito ?? ['on' => false, 'link' => null]; if ($bab['on']): ?>
<div class="card"><h3><?= e($sf->t('bab_title')) ?></h3>
<?php if ($bab['link']): ?><p><?= e(sprintf($sf->t('bab_you_linked'), (string)$bab['link']['remote_email'])) ?> <?= $bab['link']['synced_at'] ? e(sprintf($sf->t('bab_synced_at'), (string)$bab['link']['synced_at'])) : '' ?></p>
  <form method="post" action="<?= e($sf->url('/bablito/sync')) ?>"><?= csrf_field() ?><button class="btn"><?= e($sf->t('bab_sync_me')) ?></button></form>
<?php else: ?><p class="muted"><?= e($sf->t('bab_create_help')) ?></p>
  <form method="post" action="<?= e($sf->url('/bablito/create')) ?>"><?= csrf_field() ?><button class="btn primary"><?= e($sf->t('bab_create_me')) ?></button></form><?php endif; ?></div>
<?php endif; ?>
