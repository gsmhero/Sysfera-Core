<?php /** @var \Sysfera\Plugin $sf @var array $m @var array $tasks @var array $tree @var int[] $lucky @var bool $own @var ?string $author @var array $cats @var int $edit @var string $add
 * @var array $types @var array $offered @var array $files @var array $teams @var int[] $sharedTeams @var bool $crm @var array $contactName @var array $shopLinks @var bool $starred @var bool $announceOn @var array $langs */
use Sysfera\TaskTypes;
use SysferaMissions\Mission;
$tab = $own ? 'missions' : 'library'; include __DIR__ . '/_nav.php';
$lang = \Sysfera\I18n::lang();
$byId = []; foreach ($tasks as $t) $byId[(int)$t['id']] = $t;
$done = count(array_filter($tasks, fn($t) => $t['completed'])); $pct = $tasks ? (int)round($done * 100 / count($tasks)) : 0;
$data = fn(array $t) => json_decode((string)$t['type_data'], true) ?: [];
$self = $sf->url('/m/' . (int)$m['id']);
$formTask = null; $formParent = 0; $formBranch = 'good';
if ($edit && isset($byId[$edit])) $formTask = $byId[$edit];
elseif (preg_match('/^(\d+):(good|alt|bad)$/', $add, $mm) && isset($byId[(int)$mm[1]])) { $formParent = (int)$mm[1]; $formBranch = $mm[2]; }
elseif ($add === 'root') { $formParent = 0; }
$showForm = $own && ($formTask || $add !== '');
$fd = $formTask ? $data($formTask) : [];
$val = fn(string $k, $d = '') => $formTask ? ($formTask[$k] ?? $d) : $d;
$curType = $formTask ? TaskTypes::normalize((string)$formTask['type']) : 'note';
$choices = $offered; if (!isset($choices[$curType]) && isset($types[$curType])) $choices[$curType] = $types[$curType];   // an old task keeps its type even if it was switched off
$tname = fn(string $code) => TaskTypes::label(TaskTypes::get($types, $code), $lang);
$shopLinkList = array_slice(array_values(array_filter($shopLinks, fn($x) => is_array($x) && !empty($x['url']))), 0, 200); ?>
<div class="page-h"><h1><?= e($m['title']) ?></h1>
  <div class="row"><a class="btn" href="<?= e($sf->url('/m/' . (int)$m['id'] . '/export')) ?>">⭳ JSON</a>
  <form method="post" action="<?= e($sf->url('/')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><input type="hidden" name="from" value="m"><button class="btn" name="do" value="bookmark" title="<?= e($sf->t('bookmark')) ?>"><?= $starred ? '★' : '☆' ?></button></form>
  <?php if (!$own): ?><form method="post" action="<?= e($sf->url('/library/' . (int)$m['id'])) ?>"><?= csrf_field() ?><input type="hidden" name="do" value="import"><button class="btn primary"><?= e($sf->t('import_to_mine')) ?></button></form><?php endif; ?></div></div>
<?php if ($author): ?><div class="hint"><?= e($sf->t('by_author', ['name' => $author])) ?></div><?php endif; ?>
<div class="mbar"><i style="width:<?= $pct ?>%"></i></div>
<div class="muted sm"><?= e($sf->t('progress', ['done' => $done, 'n' => count($tasks)])) ?><?= $m['status'] === 'completed' ? ' · ' . e($sf->t('st_completed')) : '' ?><?= $lucky ? ' · <span class="mlucky-t">' . e($sf->t('lucky_way')) . '</span>' : '' ?></div>

<?php if ($own): ?>
<details class="card"><summary><?= e($sf->t('mission_settings')) ?></summary>
<form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="update"><input type="hidden" name="teams_form" value="1">
  <label class="fld"><span><?= e($sf->t('f_title')) ?> *</span><input name="title" required maxlength="255" value="<?= e($m['title']) ?>"></label>
  <div class="cols">
    <label class="fld"><span><?= e($sf->t('f_type')) ?></span><input name="type" maxlength="100" value="<?= e($m['type']) ?>"></label>
    <label class="fld"><span><?= e($sf->t('category')) ?></span><select name="category_id"><option value="0">—</option><?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$m['category_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($lang === 'ru' && $c['name_ru'] !== '' ? $c['name_ru'] : $c['name']) ?></option><?php endforeach; ?></select></label>
    <label class="fld"><span><?= e($sf->t('due')) ?></span><input type="date" name="due_date" value="<?= e($m['due_date'] ?? '') ?>"></label>
    <label class="fld"><span><?= e($sf->t('notify_lang')) ?></span><select name="lang"><option value=""><?= e($sf->t('lang_auto')) ?></option><?php foreach ($langs as $code => $name): ?><option value="<?= e($code) ?>" <?= $m['lang'] === $code ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?></select></label></div>
  <label class="fld"><span><?= e($sf->t('f_desc')) ?></span><textarea name="description" rows="2" maxlength="5000"><?= e($m['description'] ?? '') ?></textarea></label>
  <label class="chk"><input type="checkbox" name="shared" value="1" <?= $m['shared'] ? 'checked' : '' ?>> <?= e($sf->t('share_lib')) ?></label><small class="muted"><?= e($sf->t('share_hint')) ?></small>
  <?php if ($teams): ?><div class="fld"><span><?= e($sf->t('share_teams')) ?></span><?php foreach ($teams as $tm): ?><label class="chk"><input type="checkbox" name="teams[]" value="<?= (int)$tm['id'] ?>" <?= in_array((int)$tm['id'], $sharedTeams, true) ? 'checked' : '' ?>> 👥 <?= e($tm['name']) ?></label><?php endforeach; ?><small class="muted"><?= e($sf->t('share_teams_hint')) ?></small></div><?php endif; ?>
  <?php if ($announceOn): ?><label class="chk"><input type="checkbox" name="announce" value="1" <?= $m['announce'] ? 'checked' : '' ?>> <?= e($sf->t('announce')) ?></label><small class="muted"><?= e($sf->t('announce_hint')) ?></small><?php endif; ?>
  <div class="row"><button class="btn primary"><?= e($sf->t('save')) ?></button></div></form>
<form method="post" class="row"><?= csrf_field() ?><?php if ($m['status'] === 'completed'): ?><button class="btn" name="do" value="uncomplete"><?= e($sf->t('uncomplete')) ?></button><?php else: ?><button class="btn" name="do" value="complete"><?= e($sf->t('complete')) ?></button><?php endif; ?></form>
</details>
<?php elseif ($m['description']): ?><div class="card"><?= nl2br(e($m['description'])) ?></div><?php endif; ?>

<?php if ($showForm): ?>
<form class="card mform" method="post" id="taskform"><?= csrf_field() ?><input type="hidden" name="do" value="task_save">
  <input type="hidden" name="task_id" value="<?= $formTask ? (int)$formTask['id'] : 0 ?>"><input type="hidden" name="parent_id" value="<?= (int)$formParent ?>"><input type="hidden" name="branch" value="<?= e($formBranch) ?>">
  <h3><?= e($formTask ? $sf->t('edit_task') : $sf->t('add_task')) ?><?= $formParent ? ' — ' . e($sf->t('after', ['t' => $byId[$formParent]['title'], 'r' => $sf->t('br_' . $formBranch)])) : '' ?></h3>
  <div class="cols">
    <label class="fld"><span><?= e($sf->t('f_title')) ?> *</span><input name="title" required maxlength="255" value="<?= e($val('title')) ?>"></label>
    <label class="fld"><span><?= e($sf->t('f_type')) ?></span><select name="type" id="ttype"><?php foreach ($choices as $code => $ty): ?><option value="<?= e($code) ?>" <?= $curType === $code ? 'selected' : '' ?>><?= e(trim($ty['icon'] . ' ' . TaskTypes::label($ty, $lang))) ?></option><?php endforeach; ?></select></label>
    <label class="fld"><span><?= e($sf->t('cost')) ?></span><input name="cost" inputmode="decimal" value="<?= e($val('cost', '0')) ?>"></label></div>
  <?php foreach ($choices as $code => $ty): if (!$ty['fields']) continue; $on = $curType === $code; ?>
  <div class="cols mtype" data-t="<?= e($code) ?>" <?= $on ? '' : 'hidden' ?>>
    <?php foreach ($ty['fields'] as $f): $fn = $f['key']; $dis = $on ? '' : 'disabled'; $label = TaskTypes::fieldLabel($f, $lang);
      if ($f['kind'] === 'contact' && !$crm) continue; ?>
      <label class="fld"><span><?= e($label) ?></span>
      <?php if ($f['kind'] === 'task'): ?><select name="td[<?= e($fn) ?>]" <?= $dis ?>><option value="">—</option><?php foreach ($tasks as $o): if ($formTask && (int)$o['id'] === (int)$formTask['id']) continue; ?><option value="<?= (int)$o['id'] ?>" <?= (string)($fd[$fn] ?? '') === (string)$o['id'] ? 'selected' : '' ?>><?= e($o['title']) ?></option><?php endforeach; ?></select>
      <?php elseif ($f['kind'] === 'contact'): $cid = (int)($fd[$fn] ?? 0); ?>
        <span class="mcontact" data-find="<?= e(url('/p/crm/contacts/find')) ?>"><input type="search" class="mc-q" placeholder="<?= e($sf->t('contact_find')) ?>" autocomplete="off">
          <select name="td[<?= e($fn) ?>]" class="mc-sel" <?= $dis ?>><option value="">—</option><?php if ($cid): ?><option value="<?= $cid ?>" selected><?= e($contactName[$cid] ?? ('#' . $cid)) ?></option><?php endif; ?></select></span>
      <?php elseif ($f['kind'] === 'textarea'): ?><textarea name="td[<?= e($fn) ?>]" rows="3" <?= $dis ?>><?= e($fd[$fn] ?? '') ?></textarea>
      <?php elseif ($f['kind'] === 'shop'): ?><input type="url" name="td[<?= e($fn) ?>]" value="<?= e($fd[$fn] ?? '') ?>" placeholder="https://" list="shoplinks" <?= $dis ?>>
      <?php else: ?><input <?= in_array($f['kind'], ['email', 'url'], true) ? 'type="' . ($f['kind'] === 'email' ? 'email' : 'url') . '"' : ($f['kind'] === 'phone' ? 'type="tel"' : '') ?> name="td[<?= e($fn) ?>]" value="<?= e($fd[$fn] ?? '') ?>" <?= $dis ?>><?php endif; ?></label>
    <?php endforeach; ?></div>
  <?php endforeach; ?>
  <?php if ($shopLinkList): ?><datalist id="shoplinks"><?php foreach ($shopLinkList as $x): ?><option value="<?= e($x['url']) ?>"><?= e((string)($x['title'] ?? '')) ?></option><?php endforeach; ?></datalist><?php endif; ?>
  <label class="fld"><span><?= e($sf->t('f_desc')) ?></span><textarea name="description" rows="2"><?= e($val('description')) ?></textarea></label>
  <div class="cols">
    <label class="fld"><span><?= e($sf->t('br_good')) ?></span><input name="result_good" maxlength="255" value="<?= e($val('result_good')) ?>"></label>
    <label class="fld"><span><?= e($sf->t('br_alt')) ?></span><input name="result_alt" maxlength="255" value="<?= e($val('result_alt')) ?>"></label>
    <label class="fld"><span><?= e($sf->t('br_bad')) ?></span><input name="result_bad" maxlength="255" value="<?= e($val('result_bad')) ?>"></label></div>
  <div class="cols">
    <label class="fld"><span><?= e($sf->t('scheduled')) ?></span><input type="datetime-local" name="scheduled_at" value="<?= e($formTask && $formTask['scheduled_at'] ? str_replace(' ', 'T', substr((string)$formTask['scheduled_at'], 0, 16)) : '') ?>"></label>
    <label class="chk"><input type="checkbox" name="reminder" value="1" <?= $val('reminder') ? 'checked' : '' ?>> <?= e($sf->t('remind_me')) ?></label></div>
  <div class="row"><button class="btn primary"><?= e($sf->t('save')) ?></button><a class="btn" href="<?= e($self) ?>"><?= e($sf->t('cancel')) ?></a></div>
</form>
<script>(function(){var s=document.getElementById("ttype");if(!s)return;
function a(){document.querySelectorAll(".mtype").forEach(function(d){var on=d.getAttribute("data-t")===s.value;d.hidden=!on;d.querySelectorAll("input,select,textarea").forEach(function(i){i.disabled=!on})})}
s.addEventListener("change",a);
document.querySelectorAll(".mcontact").forEach(function(w){var q=w.querySelector(".mc-q"),sel=w.querySelector(".mc-sel"),tm=0,box=w.closest(".mtype");
 function fill(n,v){var i=box&&box.querySelector('[name="td['+n+']"]');if(i&&v)i.value=v}
 q.addEventListener("input",function(){clearTimeout(tm);tm=setTimeout(function(){if(q.value.length<2)return;
  fetch(w.getAttribute("data-find")+"?q="+encodeURIComponent(q.value),{credentials:"same-origin"}).then(function(r){return r.json()}).then(function(rows){
   var keep=sel.value;sel.innerHTML='<option value="">—</option>';rows.forEach(function(c){var o=document.createElement("option");o.value=c.id;o.textContent=c.name+(c.company?" · "+c.company:"");o.dataset.phone=c.phone||"";o.dataset.email=c.email||"";sel.appendChild(o)});
   if(rows.length)sel.value=rows[0].id;sel.dispatchEvent(new Event("change"))}).catch(function(){})},250)});
 sel.addEventListener("change",function(){var o=sel.options[sel.selectedIndex];if(!o||!o.value)return;fill("phone",o.dataset.phone);fill("to",o.dataset.email)})});
})();</script>
<?php endif; ?>

<?php
$mapLink = fn(string $place) => Mission::mapUrl($place, $lang);
$render = function (int $parent, string $branch) use (&$render, $tree, $lucky, $own, $sf, $self, $data, $m, $types, $lang, $tname, $files, $contactName, $mapLink, $edit): void {
    foreach ($tree[$parent][$branch] ?? [] as $t):
        $d = $data($t); $id = (int)$t['id']; $kids = $tree[$id] ?? []; $code = \Sysfera\TaskTypes::normalize((string)$t['type']); $ty = \Sysfera\TaskTypes::get($types, $code);
        $defs = []; foreach ($ty['fields'] as $f) $defs[$f['key']] = $f; $fl = $files[$id] ?? []; ?>
  <div class="mtask <?= $t['completed'] ? 'mtdone' : '' ?> <?= in_array($id, $lucky, true) ? 'mlucky' : '' ?>" id="t<?= $id ?>">
    <div class="row between"><div><span class="badge" style="border-color:<?= e($ty['color']) ?>"><?= e(trim($ty['icon'] . ' ' . \Sysfera\TaskTypes::label($ty, $lang))) ?></span> <b><?= e($t['title']) ?></b>
      <?php if ((float)$t['cost'] > 0): ?><small class="muted"> · <?= e(number_format((float)$t['cost'], 2, '.', ' ')) ?></small><?php endif; ?>
      <?php if ($t['scheduled_at']): ?><small class="muted"> · <?= e(substr((string)$t['scheduled_at'], 0, 16)) ?><?= $t['reminder'] ? ' ⏰' : '' ?></small><?php endif; ?></div>
      <?php if ($own): ?><form method="post" action="<?= e($self) ?>"><?= csrf_field() ?><input type="hidden" name="do" value="task_done"><input type="hidden" name="task_id" value="<?= $id ?>"><button class="btn <?= $t['completed'] ? '' : 'primary' ?>"><?= e($t['completed'] ? $sf->t('undo') : $sf->t('done_' . ($code === 'email' && !empty($d['to']) ? 'email' : ($code === 'finish_mission' ? 'finish' : 'plain')))) ?></button></form><?php endif; ?></div>
    <?php if ($t['description']): ?><div class="muted sm"><?= nl2br(e($t['description'])) ?></div><?php endif; ?>
    <?php foreach ($d as $k => $v): if ($v === '') continue; $f = $defs[$k] ?? ['kind' => 'text', 'label' => $k, 'label_ru' => '']; $label = \Sysfera\TaskTypes::fieldLabel($f, $lang); ?>
      <div class="sm"><span class="muted"><?= e($label) ?>:</span>
      <?php if ($f['kind'] === 'task'): ?><a href="#t<?= (int)$v ?>">#<?= (int)$v ?></a>
      <?php elseif ($f['kind'] === 'shop'): ?><a class="btn sm" href="<?= e($v) ?>" target="_blank" rel="noopener noreferrer">🛒 <?= e($sf->t('open_shop')) ?></a> <small class="muted"><?= e(parse_url($v, PHP_URL_HOST) ?: '') ?></small>
      <?php elseif ($f['kind'] === 'url'): ?><a href="<?= e($v) ?>" target="_blank" rel="noopener noreferrer"><?= e($v) ?></a>
      <?php elseif ($f['kind'] === 'email'): ?><a href="mailto:<?= e($v) ?>"><?= e($v) ?></a>
      <?php elseif ($f['kind'] === 'phone'): ?><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $v)) ?>"><?= e($v) ?></a>
      <?php elseif ($f['kind'] === 'contact'): ?><?= isset($contactName[(int)$v]) ? '<a href="' . e(url('/p/crm/contact/' . (int)$v)) . '">👤 ' . e($contactName[(int)$v]) . '</a>' : '<span class="muted">CRM #' . (int)$v . '</span>' ?>
      <?php else: ?><?= nl2br(e($v)) ?><?php if ($k === 'place'): ?> <a href="<?= e($mapLink($v)) ?>" target="_blank" rel="noopener noreferrer">🗺 <?= e($sf->t('on_map')) ?></a><?php endif; ?><?php endif; ?></div>
    <?php endforeach; ?>
    <?php if ($fl): ?><div class="mfiles"><?php foreach ($fl as $f): $img = in_array($f['ext'], Mission::INLINE, true); $fu = $sf->url('/file/' . (int)$f['id']); ?>
      <span class="mfile"><?php if ($img): ?><a href="<?= e($fu) ?>" target="_blank" rel="noopener"><img src="<?= e($fu) ?>" alt="<?= e($f['name']) ?>" loading="lazy"></a><?php else: ?><a href="<?= e($fu) ?>">📎 <?= e($f['name']) ?></a> <small class="muted"><?= e(number_format($f['size'] / 1024, 0)) ?> KB</small><?php endif; ?>
        <?php if ($own): ?><form method="post" action="<?= e($self) ?>" class="inl"><?= csrf_field() ?><input type="hidden" name="do" value="file_del"><input type="hidden" name="file_id" value="<?= (int)$f['id'] ?>"><button class="linkbtn bad-t" title="<?= e($sf->t('delete')) ?>" data-confirm="<?= e($sf->t('delete')) ?>?">×</button></form><?php endif; ?></span><?php endforeach; ?></div><?php endif; ?>
    <?php if ($own): ?><div class="row sm"><a href="<?= e($self . '?edit=' . $id) ?>"><?= e($sf->t('edit')) ?></a>
      <form method="post" action="<?= e($self) ?>"><?= csrf_field() ?><input type="hidden" name="do" value="task_delete"><input type="hidden" name="task_id" value="<?= $id ?>"><button class="linkbtn bad-t" data-confirm="<?= e($sf->t('confirm_task_delete')) ?>"><?= e($sf->t('delete')) ?></button></form>
      <form method="post" action="<?= e($self) ?>" enctype="multipart/form-data" class="inl"><?= csrf_field() ?><input type="hidden" name="do" value="file_add"><input type="hidden" name="task_id" value="<?= $id ?>"><label class="linkbtn">📎 <?= e($sf->t('attach')) ?><input type="file" name="file" hidden onchange="this.form.submit()"></label></form></div><?php endif; ?>
    <?php if ($code !== 'finish_mission'): foreach (Mission::BRANCHES as $br): $label = (string)$t['result_' . $br]; $kidsB = $kids[$br] ?? []; if (!$own && !$kidsB) continue; ?>
      <div class="mbranch mb-<?= $br ?>"><div class="mbh"><b><?= e($sf->t('br_' . $br)) ?></b><?= $label !== '' ? ' — ' . e($label) : '' ?>
        <?php if ($own): ?> <a class="sm" href="<?= e($self . '?add=' . $id . ':' . $br . '#taskform') ?>">+ <?= e($sf->t('add_task')) ?></a><?php endif; ?></div>
        <div class="mkids"><?php $render($id, $br); ?></div></div>
    <?php endforeach; endif; ?>
  </div>
<?php endforeach; };
if (!$tasks): ?><div class="card empty"><p><?= e($sf->t('no_tasks')) ?></p></div><?php endif;
$render(0, ''); ?>
<?php if ($own): ?><p><a class="btn" href="<?= e($self . '?add=root#taskform') ?>">+ <?= e($sf->t('add_root')) ?></a></p><?php endif; ?>
