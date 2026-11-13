<?php /** @var \Sysfera\Plugin $sf @var list<array> $pl @var list<array> $defs @var list<array> $profs @var array $assign @var list<array> $users @var list<string> $reasons @var string $cur @var int $defProf */
$tab = 'settings'; require __DIR__ . '/_nav.php';
$post = fn(string $do) => e($sf->url('/settings/' . $do));
$types = \SysferaCrm\Fields::TYPES; $ents = \SysferaCrm\Fields::ENTITIES; ?>
<div class="page-h"><h1><?= e($sf->t('settings')) ?></h1></div>
<p class="crm-anchors"><a href="#pipelines"><?= e($sf->t('s_pipelines')) ?></a> · <a href="#fields"><?= e($sf->t('s_fields')) ?></a> · <a href="#profiles"><?= e($sf->t('s_profiles')) ?></a> · <a href="#general"><?= e($sf->t('s_general')) ?></a></p>

<h2 id="pipelines"><?= e($sf->t('s_pipelines')) ?></h2><p class="muted"><?= e($sf->t('s_pipelines_hint')) ?></p>
<?php foreach ($pl as $p): ?><section class="card">
  <form method="post" action="<?= $post('pipeline') ?>" class="row"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><input type="color" name="color" value="<?= e($p['color']) ?>"><input name="name" value="<?= e($p['name']) ?>" maxlength="120" required style="flex:1"><button class="btn"><?= e($sf->t('save')) ?></button></form>
  <div class="crm-stages">
  <?php foreach ($p['stages'] as $s): ?><form method="post" action="<?= $post('stage') ?>" class="crm-srow"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
    <span><input type="color" name="color" value="<?= e($s['color']) ?>"> <input name="name" value="<?= e($s['name']) ?>" maxlength="120" required></span>
    <select name="kind"><?php foreach (['open', 'won', 'lost'] as $k): ?><option value="<?= $k ?>"<?= $s['kind'] === $k ? ' selected' : '' ?>><?= e($sf->t('s_' . $k)) ?></option><?php endforeach; ?></select>
    <span><input name="prob" type="number" min="0" max="100" value="<?= (int)$s['prob'] ?>" style="width:70px" title="<?= e($sf->t('st_prob')) ?>"> %</span>
    <input name="sort" type="number" value="<?= (int)$s['sort'] ?>" style="width:70px" title="<?= e($sf->t('st_order')) ?>">
    <span class="row"><button class="btn sm"><?= e($sf->t('save')) ?></button><button class="btn sm danger" formaction="<?= $post('stage_delete') ?>" formnovalidate<?= $s['deals'] ? ' disabled title="' . e($sf->t('e_stage_used')) . '"' : '' ?> onclick="return confirm('<?= e($sf->t('confirm_delete')) ?>')">×</button></span></form><?php endforeach; ?>
  <form method="post" action="<?= $post('stage') ?>" class="crm-srow"><?= csrf_field() ?><input type="hidden" name="pipeline_id" value="<?= (int)$p['id'] ?>">
    <span><input type="color" name="color" value="#6b7280"> <input name="name" placeholder="<?= e($sf->t('st_add')) ?>" maxlength="120" required></span>
    <select name="kind"><?php foreach (['open', 'won', 'lost'] as $k): ?><option value="<?= $k ?>"><?= e($sf->t('s_' . $k)) ?></option><?php endforeach; ?></select>
    <span><input name="prob" type="number" min="0" max="100" value="50" style="width:70px"> %</span><span></span><button class="btn sm primary">+</button></form></div>
  <form method="post" action="<?= $post('pipeline_delete') ?>" data-confirm="<?= e($sf->t('confirm_delete')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn sm danger"<?= $p['deals'] || count($pl) < 2 ? ' disabled' : '' ?>><?= e($sf->t('delete_pipeline')) ?></button></form></section><?php endforeach; ?>
<form method="post" action="<?= $post('pipeline') ?>" class="card row"><?= csrf_field() ?><input type="color" name="color" value="#4f46e5"><input name="name" placeholder="<?= e($sf->t('new_pipeline')) ?>" maxlength="120" required style="flex:1"><button class="btn primary">+ <?= e($sf->t('new_pipeline')) ?></button></form>

<h2 id="fields"><?= e($sf->t('s_fields')) ?></h2><p class="muted"><?= e($sf->t('s_fields_hint')) ?></p>
<?php foreach ($ents as $en): ?><section class="card"><h3><?= e($sf->t('ent_' . $en)) ?></h3>
  <?php foreach ($defs as $d): if ($d['entity'] !== $en) continue; ?><form method="post" action="<?= $post('field') ?>" class="crm-fdef"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$d['id'] ?>"><input type="hidden" name="entity" value="<?= e($en) ?>">
    <input name="label" value="<?= e($d['label']) ?>" maxlength="120" required>
    <select name="type"><?php foreach ($types as $t): ?><option value="<?= $t ?>"<?= $d['type'] === $t ? ' selected' : '' ?>><?= e($sf->t('ft_' . $t)) ?></option><?php endforeach; ?></select>
    <?php if ($en === 'deal'): ?><select name="pipeline_id"><option value=""><?= e($sf->t('all_pipelines')) ?></option><?php foreach ($pl as $p): ?><option value="<?= (int)$p['id'] ?>"<?= (int)$d['pipeline_id'] === (int)$p['id'] ? ' selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?></select><?php endif; ?>
    <input name="options" value="<?= e(implode(', ', $d['options'])) ?>" placeholder="<?= e($sf->t('f_options')) ?>">
    <label class="chk"><input type="checkbox" name="required" value="1"<?= $d['required'] ? ' checked' : '' ?>> <?= e($sf->t('f_required')) ?></label>
    <button class="btn sm"><?= e($sf->t('save')) ?></button><button class="btn sm danger" formaction="<?= $post('field_delete') ?>" formnovalidate onclick="return confirm('<?= e($sf->t('confirm_delete')) ?>')">×</button></form><?php endforeach; ?>
  <form method="post" action="<?= $post('field') ?>" class="crm-fdef"><?= csrf_field() ?><input type="hidden" name="entity" value="<?= e($en) ?>">
    <input name="label" placeholder="<?= e($sf->t('f_new')) ?>" maxlength="120" required>
    <select name="type"><?php foreach ($types as $t): ?><option value="<?= $t ?>"><?= e($sf->t('ft_' . $t)) ?></option><?php endforeach; ?></select>
    <?php if ($en === 'deal'): ?><select name="pipeline_id"><option value=""><?= e($sf->t('all_pipelines')) ?></option><?php foreach ($pl as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?></select><?php endif; ?>
    <input name="options" placeholder="<?= e($sf->t('f_options')) ?>"><label class="chk"><input type="checkbox" name="required" value="1"> <?= e($sf->t('f_required')) ?></label><button class="btn sm primary">+</button></form></section><?php endforeach; ?>

<h2 id="profiles"><?= e($sf->t('s_profiles')) ?></h2><p class="muted"><?= e($sf->t('s_profiles_hint')) ?></p>
<?php foreach ($profs as $pr): ?><form method="post" action="<?= $post('profile') ?>" class="card crm-prof"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$pr['id'] ?>">
  <input name="name" value="<?= e($pr['name']) ?>" maxlength="120" required>
  <select name="scope"><option value="own"<?= $pr['p']['scope'] === 'own' ? ' selected' : '' ?>><?= e($sf->t('scope_own')) ?></option><option value="all"<?= $pr['p']['scope'] === 'all' ? ' selected' : '' ?>><?= e($sf->t('scope_all')) ?></option></select>
  <?php foreach (\SysferaCrm\Pipe::PERMS as $k): ?><label class="chk"><input type="checkbox" name="perm[<?= $k ?>]" value="1"<?= $pr['p'][$k] ? ' checked' : '' ?>> <?= e($sf->t('perm_' . $k)) ?></label><?php endforeach; ?>
  <button class="btn sm"><?= e($sf->t('save')) ?></button><button class="btn sm danger" formaction="<?= $post('profile_delete') ?>" formnovalidate onclick="return confirm('<?= e($sf->t('confirm_delete')) ?>')">×</button></form><?php endforeach; ?>
<form method="post" action="<?= $post('profile') ?>" class="card crm-prof"><?= csrf_field() ?><input name="name" placeholder="<?= e($sf->t('new_profile')) ?>" maxlength="120" required>
  <select name="scope"><option value="own"><?= e($sf->t('scope_own')) ?></option><option value="all"><?= e($sf->t('scope_all')) ?></option></select>
  <?php foreach (\SysferaCrm\Pipe::PERMS as $k): ?><label class="chk"><input type="checkbox" name="perm[<?= $k ?>]" value="1"> <?= e($sf->t('perm_' . $k)) ?></label><?php endforeach; ?><button class="btn sm primary">+</button></form>

<form method="post" action="<?= $post('assign') ?>" class="card"><?= csrf_field() ?><h3><?= e($sf->t('assign_h')) ?></h3>
  <label class="fld"><span><?= e($sf->t('default_profile')) ?></span><select name="default_profile"><?php foreach ($profs as $pr): ?><option value="<?= (int)$pr['id'] ?>"<?= (int)$pr['id'] === $defProf ? ' selected' : '' ?>><?= e($pr['name']) ?></option><?php endforeach; ?></select></label>
  <table class="tbl"><tbody><?php foreach ($users as $u): ?><tr><td><b><?= e($u['name']) ?></b> <small class="muted"><?= e($u['email']) ?></small></td>
    <td><?php if ($u['role'] === 'admin'): ?><span class="muted"><?= e($sf->t('admin_full')) ?></span><?php else: ?><select name="u[<?= (int)$u['id'] ?>]"><?php foreach ($profs as $pr): ?><option value="<?= (int)$pr['id'] ?>"<?= (int)($assign[(int)$u['id']] ?? $defProf) === (int)$pr['id'] ? ' selected' : '' ?>><?= e($pr['name']) ?></option><?php endforeach; ?></select><?php endif; ?></td></tr><?php endforeach; ?></tbody></table>
  <button class="btn primary"><?= e($sf->t('save')) ?></button></form>

<h2 id="general"><?= e($sf->t('s_general')) ?></h2>
<form method="post" action="<?= $post('general') ?>" class="card crm-form"><?= csrf_field() ?>
  <label class="fld"><span><?= e($sf->t('f_currency')) ?></span><input name="currency" value="<?= e($cur) ?>" maxlength="3" style="max-width:100px;text-transform:uppercase"></label>
  <label class="fld"><span><?= e($sf->t('lost_reasons')) ?></span><textarea name="reasons" rows="5"><?= e(implode("\n", $reasons)) ?></textarea></label>
  <button class="btn primary"><?= e($sf->t('save')) ?></button></form>
