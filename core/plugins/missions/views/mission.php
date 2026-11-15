<?php /** @var \Sysfera\Plugin $sf @var array $m @var array $tasks @var array $tree @var int[] $lucky @var bool $own @var ?string $author @var array $cats @var int $edit @var string $add
 * @var array $types @var array $offered @var array $files @var array $teams @var int[] $sharedTeams @var bool $crm @var array $contactName @var array $shopLinks @var bool $starred @var bool $announceOn @var array $langs @var array $setup */
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
$shopLinkList = array_slice(array_values(array_filter($shopLinks, fn($x) => is_array($x) && !empty($x['url']))), 0, 200);
// navigation as on tactify.ru: the mission shows its first tasks; a task is opened on its own page (?t=) with its three result paths; ?view=tree keeps the whole tree on one page
$focus = isset($_GET['t']) && isset($byId[(int)$_GET['t']]) ? $byId[(int)$_GET['t']] : null;
$treeView = !$focus && ($_GET['view'] ?? '') === 'tree';
$q = $focus ? 't=' . (int)$focus['id'] : '';
$selfT = $self . ($q ? '?' . $q : '');   // where the buttons of this page post to
$trail = []; for ($p = $focus; $p && count($trail) < 50; $p = $p['parent_id'] ? ($byId[(int)$p['parent_id']] ?? null) : null) array_unshift($trail, $p);
$onlyId = 0; ?>
<nav class="crumbs" aria-label="breadcrumb"><a class="bc" href="<?= e($sf->url($own ? '/' : '/library')) ?>">🏠</a><span class="bs">›</span><?php if ($focus): ?><a class="bc" href="<?= e($self) ?>"><?= e($m['title']) ?></a><?php foreach ($trail as $i => $tt): ?><span class="bs">›</span><?php if ($i < count($trail) - 1): ?><a class="bc" href="<?= e($self . '?t=' . (int)$tt['id']) ?>"><?= e($tt['title']) ?></a><?php else: ?><span class="bc on"><?= e($tt['title']) ?></span><?php endif; endforeach; else: ?><span class="bc on"><?= e($m['title']) ?></span><?php endif; ?></nav>
<?php if (!$focus): ?>
<div class="card">
  <div class="row between"><div class="chips"><span class="chip id">M-<?= (int)$m['id'] ?></span><?php if ($m['type'] !== ''): ?><span class="chip blue"><?= e($m['type']) ?></span><?php endif; ?>
    <span class="chip"><?= $m['shared'] ? '🌐 ' . e($sf->t('vis_public')) : '🔒 ' . e($sf->t('vis_private')) ?></span><span class="chip <?= $m['status'] === 'completed' ? '' : 'blue' ?>"><?= $m['status'] === 'completed' ? '✓ ' . e($sf->t('st_completed')) : '▶ ' . e($sf->t('st_active')) ?></span></div>
    <div class="row"><a class="ibtn" href="<?= e($sf->url('/m/' . (int)$m['id'] . '/export')) ?>" title="JSON">⭳</a>
    <form method="post" action="<?= e($sf->url('/')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><input type="hidden" name="from" value="m"><button class="ibtn" name="do" value="bookmark" title="<?= e($sf->t('bookmark')) ?>"><?= $starred ? '★' : '☆' ?></button></form>
    <?php if (!$own): ?><form method="post" action="<?= e($sf->url('/library/' . (int)$m['id'])) ?>"><?= csrf_field() ?><input type="hidden" name="do" value="import"><button class="btn primary"><?= e($sf->t('import_to_mine')) ?></button></form><?php endif; ?></div></div>
  <h2 style="margin:10px 0 2px"><?= e($m['title']) ?></h2>
  <?php if ($author): ?><div class="muted sm"><?= e($sf->t('by_author', ['name' => $author])) ?></div><?php endif; ?>
  <?php if ($m['description'] && !$own): ?><p class="muted"><?= nl2br(e($m['description'])) ?></p><?php endif; ?>
<div class="mbar"><i style="width:<?= $pct ?>%"></i></div>
<div class="muted sm"><?= e($sf->t('progress', ['done' => $done, 'n' => count($tasks)])) ?><?= $m['status'] === 'completed' ? ' · ' . e($sf->t('st_completed')) : '' ?><?= $lucky ? ' · <span class="mlucky-t">' . e($sf->t('lucky_way')) . '</span>' : '' ?></div>
</div>

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
  <label class="fld"><span><?= e($sf->t('mission_status')) ?></span><select name="vis" onchange="document.getElementById('hidef').hidden=this.value!=='public'"><option value="private">🔒 <?= e($sf->t('vis_private')) ?></option><option value="public" <?= $m['shared'] ? 'selected' : '' ?>>🌐 <?= e($sf->t('vis_public')) ?> — <?= e($sf->t('share_lib')) ?></option></select><small class="muted"><?= e($sf->t('share_hint')) ?></small></label>
  <div class="filenote" id="hidef" <?= $m['shared'] ? '' : 'hidden' ?>><b>📎 <?= e($sf->t('files_note_h')) ?></b><br><?= e($sf->t('files_note')) ?><br><label class="chk"><input type="checkbox" name="hide_files" value="1" <?= $m['hide_files'] ? 'checked' : '' ?>> <?= e($sf->t('hide_files')) ?></label></div>
  <?php if ($teams): ?><div class="fld"><span><?= e($sf->t('share_teams')) ?></span><?php foreach ($teams as $tm): ?><label class="chk"><input type="checkbox" name="teams[]" value="<?= (int)$tm['id'] ?>" <?= in_array((int)$tm['id'], $sharedTeams, true) ? 'checked' : '' ?>> 👥 <?= e($tm['name']) ?></label><?php endforeach; ?><small class="muted"><?= e($sf->t('share_teams_hint')) ?></small></div><?php endif; ?>
  <?php if ($announceOn): ?><label class="chk"><input type="checkbox" name="announce" value="1" <?= $m['announce'] ? 'checked' : '' ?>> <?= e($sf->t('announce')) ?></label><small class="muted"><?= e($sf->t('announce_hint')) ?></small><?php endif; ?>
  <div class="row"><button class="btn primary"><?= e($sf->t('save')) ?></button></div></form>
<form method="post" class="row"><?= csrf_field() ?><?php if ($m['status'] === 'completed'): ?><button class="btn" name="do" value="uncomplete"><?= e($sf->t('uncomplete')) ?></button><?php else: ?><button class="btn" name="do" value="complete"><?= e($sf->t('complete')) ?></button><?php endif; ?></form>
</details>
<?php endif; ?>
<?php endif; /* !$focus */ ?>

<?php $formHtml = ''; if ($showForm): ob_start(); ?>
<form class="card mform" method="post" id="taskform"><?= csrf_field() ?><input type="hidden" name="do" value="task_save">
  <input type="hidden" name="task_id" value="<?= $formTask ? (int)$formTask['id'] : 0 ?>"><input type="hidden" name="parent_id" value="<?= (int)$formParent ?>"><input type="hidden" name="branch" value="<?= e($formBranch) ?>">
  <h3><?= e($formTask ? $sf->t('edit_task') : $sf->t('add_task')) ?><?= $formParent ? ' — ' . e($sf->t('after', ['t' => $byId[$formParent]['title'], 'r' => $sf->t('br_' . $formBranch)])) : '' ?></h3>
  <div class="cols">
    <label class="fld"><span><?= e($sf->t('f_title')) ?> *</span><input name="title" required maxlength="255" value="<?= e($val('title')) ?>"></label>
    <label class="fld"><span><?= e($sf->t('f_type')) ?></span><select name="type" id="ttype"><?php foreach ($choices as $code => $ty): ?><option value="<?= e($code) ?>" <?= $curType === $code ? 'selected' : '' ?>><?= e(TaskTypes::label($ty, $lang)) ?></option><?php endforeach; ?></select></label>
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
  <div class="row"><button class="btn primary"><?= e($sf->t('save')) ?></button><a class="btn" href="<?= e($selfT) ?>"><?= e($sf->t('cancel')) ?></a></div>
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
<?php $formHtml = (string)ob_get_clean(); endif;
// the task form is printed under the task it belongs to (edited task / parent of the new one); a new first task goes under the list
$formAt = $formTask ? (int)$formTask['id'] : (int)$formParent; ?>

<?php
$mapLink = fn(string $place) => Mission::mapUrl($place, $lang);
$render = function (int $parent, string $branch) use (&$render, &$onlyId, &$formHtml, $formAt, $tree, $lucky, $own, $sf, $self, $selfT, $q, $data, $m, $types, $lang, $tname, $files, $contactName, $mapLink, $setup): void {
    foreach ($tree[$parent][$branch] ?? [] as $t):
        if ($onlyId && (int)$t['id'] !== $onlyId) continue;   // the task page shows one task
        $d = $data($t); $id = (int)$t['id']; $kids = $tree[$id] ?? []; $code = \Sysfera\TaskTypes::normalize((string)$t['type']); $ty = \Sysfera\TaskTypes::get($types, $code);
        $defs = []; foreach ($ty['fields'] as $f) $defs[$f['key']] = $f; $fl = $files[$id] ?? [];
        $addr = trim((string)($d['address'] ?? '')) !== '' ? (string)$d['address'] : (string)($d['place'] ?? ''); ?>
  <div class="mtask <?= $t['completed'] ? 'mtdone' : '' ?> <?= in_array($id, $lucky, true) ? 'mlucky' : '' ?>" id="t<?= $id ?>">
    <div class="row between"><div class="chips"><span class="chip id">T-<?= $id ?></span><span class="chip type"><?= \Sysfera\TaskTypes::svg($ty['icon'], 13) ?> <?= e(\Sysfera\TaskTypes::label($ty, $lang)) ?></span>
      <?php if ((float)$t['cost'] > 0): ?><span class="chip cost">$<?= e(number_format((float)$t['cost'], 2, '.', ' ')) ?></span><?php endif; ?>
      <?php if ($t['scheduled_at']): ?><span class="chip">📅 <?= e(substr((string)$t['scheduled_at'], 0, 16)) ?><?= $t['reminder'] ? ' ⏰' : '' ?></span><?php endif; ?></div>
      <?php if ($own): ?><div class="row"><a class="ibtn" href="<?= e($self . '?edit=' . $id . ($q ? '&' . $q : '')) ?>" title="<?= e($sf->t('edit')) ?>">✎</a>
        <form method="post" action="<?= e($selfT) ?>"><?= csrf_field() ?><input type="hidden" name="do" value="task_done"><input type="hidden" name="task_id" value="<?= $id ?>"><button class="btn sm <?= $t['completed'] ? '' : 'primary' ?>"><?= e($t['completed'] ? $sf->t('undo') : $sf->t('done_' . ($code === 'email' && !empty($d['to']) ? 'email' : ($code === 'finish_mission' ? 'finish' : 'plain')))) ?></button></form>
        <form method="post" action="<?= e($selfT) ?>"><?= csrf_field() ?><input type="hidden" name="do" value="task_delete"><input type="hidden" name="task_id" value="<?= $id ?>"><button class="ibtn" title="<?= e($sf->t('delete')) ?>" data-confirm="<?= e($sf->t('confirm_task_delete')) ?>">🗑</button></form></div><?php endif; ?></div>
    <h4><?= e($t['title']) ?></h4>
    <?php if ($t['description']): ?><div class="muted sm"><?= nl2br(e($t['description'])) ?></div><?php endif; ?>
    <?php if ($d): ?><div class="tdet"><?php foreach ($d as $k => $v): if ($v === '') continue; $f = $defs[$k] ?? ['kind' => 'text', 'label' => $k, 'label_ru' => '']; $label = \Sysfera\TaskTypes::fieldLabel($f, $lang); ?>
      <div class="k"><?= e($label) ?></div><div class="v">
      <?php if ($f['kind'] === 'task'): ?><a href="#t<?= (int)$v ?>">#<?= (int)$v ?></a>
      <?php elseif ($f['kind'] === 'shop'): ?><a class="btn sm" href="<?= e($v) ?>" target="_blank" rel="noopener noreferrer">🛒 <?= e($sf->t('open_shop')) ?></a> <small class="muted"><?= e(parse_url($v, PHP_URL_HOST) ?: '') ?></small>
      <?php elseif ($f['kind'] === 'url'): ?><a href="<?= e($v) ?>" target="_blank" rel="noopener noreferrer"><?= e($v) ?></a>
      <?php elseif ($f['kind'] === 'email'): ?><a href="mailto:<?= e($v) ?>"><?= e($v) ?></a>
      <?php elseif ($f['kind'] === 'phone'): ?><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $v)) ?>"><?= e($v) ?></a>
      <?php elseif ($f['kind'] === 'contact'): ?><?= isset($contactName[(int)$v]) ? '<a href="' . e(url('/p/crm/contact/' . (int)$v)) . '">👤 ' . e($contactName[(int)$v]) . '</a>' : '<span class="muted">CRM #' . (int)$v . '</span>' ?>
      <?php else: ?><?= nl2br(e($v)) ?><?php endif; ?></div>
    <?php endforeach; ?></div><?php endif; ?>
    <?php if ($addr !== ''): ?><div class="tmap" data-address="<?= e($addr) ?>" data-fallback="<?= e($mapLink($addr)) ?>"><a class="btn sm" style="margin:10px" href="<?= e($mapLink($addr)) ?>" target="_blank" rel="noopener noreferrer">🗺 <?= e($sf->t('on_map')) ?></a></div><?php endif; ?>
    <?php if ($fl || $own): ?><div class="tdet"><div class="k">🖼 <?= e($sf->t('gallery')) ?></div>
      <?php if ($fl): ?><div class="mfiles"><?php foreach ($fl as $f): $img = in_array($f['ext'], Mission::INLINE, true); $fu = $sf->url('/file/' . (int)$f['id']); ?>
        <span class="mfile"><?php if ($img): ?><a href="<?= e($fu) ?>" target="_blank" rel="noopener" data-lb="g<?= $id ?>"><img src="<?= e($fu) ?>" alt="<?= e($f['name']) ?>" loading="lazy"></a><?php else: ?><a href="<?= e($fu) ?>">📎 <?= e($f['name']) ?></a> <small class="muted"><?= e(number_format($f['size'] / 1024, 0)) ?> KB</small><?php endif; ?>
          <?php if ($own): ?><form method="post" action="<?= e($selfT) ?>" class="inl"><?= csrf_field() ?><input type="hidden" name="do" value="file_del"><input type="hidden" name="file_id" value="<?= (int)$f['id'] ?>"><button class="linkbtn bad-t" title="<?= e($sf->t('delete')) ?>" data-confirm="<?= e($sf->t('delete')) ?>?">×</button></form><?php endif; ?></span><?php endforeach; ?></div>
      <?php else: ?><div class="muted sm"><?= e($sf->t('no_images')) ?></div><?php endif; ?>
      <?php if ($own): ?><form method="post" action="<?= e($selfT) ?>" enctype="multipart/form-data" class="inl"><?= csrf_field() ?><input type="hidden" name="do" value="file_add"><input type="hidden" name="task_id" value="<?= $id ?>"><label class="btn sm">📎 <?= e($sf->t('attach')) ?><input type="file" name="file" hidden onchange="this.form.submit()"></label></form><?php endif; ?></div><?php endif; ?>
    <?php if ($code !== 'finish_mission' && $onlyId): ?><div class="rhead"><?= e($sf->t('choose_path')) ?></div><div class="rpath"><?php foreach (Mission::BRANCHES as $br): $label = (string)$t['result_' . $br]; $kidsB = $kids[$br] ?? []; ?>
      <div class="rcard rc-<?= $br ?> <?= $kidsB ? 'has' : '' ?>"><div class="rl"><?= e($sf->t('br_' . $br)) ?></div><div class="rt"><?= e($label !== '' ? $label : $sf->t('br_' . $br)) ?></div>
        <?php foreach ($kidsB as $k): ?><a class="rgo" href="<?= e($self . '?t=' . (int)$k['id']) ?>">→ <?= e($k['title']) ?></a><?php endforeach; ?>
        <?php if (!$kidsB && $own): ?><a class="radd" href="<?= e($self . '?add=' . $id . ':' . $br . '&t=' . $id . '#taskform') ?>"><?= e($sf->t('click_to_add')) ?></a><?php elseif (!$kidsB): ?><span class="radd off">—</span><?php endif; ?></div>
    <?php endforeach; ?></div>
    <?php elseif ($code !== 'finish_mission'): ?><div class="mpath"><?php foreach (Mission::BRANCHES as $br): $label = (string)$t['result_' . $br]; $kidsB = $kids[$br] ?? []; if (!$own && !$kidsB) continue; ?>
      <div class="mbranch mb-<?= $br ?>"><div class="mbh"><b><?= e($sf->t('br_' . $br)) ?></b></div><?php if ($label !== ''): ?><div class="mbt"><?= e($label) ?></div><?php endif; ?>
        <?php if ($own): ?><a class="radd" href="<?= e($self . '?add=' . $id . ':' . $br . ($q ? '&' . $q : '') . '#taskform') ?>"><?= e($sf->t('click_to_add')) ?></a><?php endif; ?>
        <div class="mkids"><?php $render($id, $br); ?></div></div>
    <?php endforeach; ?></div><?php endif; ?>
  </div>
<?php if ($formHtml !== '' && $formAt === $id) { echo $formHtml; $formHtml = ''; } ?>
<?php endforeach; };
if (!$tasks): ?><div class="card muted" style="text-align:center"><?= e($sf->t('no_tasks')) ?></div><?php endif;
if ($focus):
    $onlyId = (int)$focus['id']; $render((int)($focus['parent_id'] ?? 0), $focus['parent_id'] ? (string)$focus['branch'] : '');
    $backTo = $focus['parent_id'] ? $self . '?t=' . (int)$focus['parent_id'] : $self; ?>
<?= $formHtml ?>
<p><a class="btn" href="<?= e($backTo) ?>">‹ <?= e($sf->t('back')) ?></a></p>
<?php elseif ($treeView): $render(0, ''); echo $formHtml; ?>
<p class="row"><?php if ($own): ?><a class="btn primary" href="<?= e($self . '?add=root#taskform') ?>">＋ <?= e($sf->t('add_root')) ?></a><?php endif; ?><a class="btn" href="<?= e($self) ?>"><?= e($sf->t('view_list')) ?></a></p>
<?php else: foreach ($tree[0][''] ?? [] as $t): $ty = \Sysfera\TaskTypes::get($types, \Sysfera\TaskTypes::normalize((string)$t['type'])); ?>
<a class="tcard <?= $t['completed'] ? 'mtdone' : '' ?> <?= in_array((int)$t['id'], $lucky, true) ? 'mlucky' : '' ?>" href="<?= e($self . '?t=' . (int)$t['id']) ?>">
  <span class="chips"><span class="chip id">T-<?= (int)$t['id'] ?></span><span class="chip type"><?= \Sysfera\TaskTypes::svg($ty['icon'], 13) ?> <?= e(\Sysfera\TaskTypes::label($ty, $lang)) ?></span>
  <?php if ((float)$t['cost'] > 0): ?><span class="chip cost">$<?= e(number_format((float)$t['cost'], 2, '.', ' ')) ?></span><?php endif; ?><?php if ($t['completed']): ?><span class="chip">✓</span><?php endif; ?></span>
  <span class="tt"><?= e($t['title']) ?></span>
  <?php if ($t['description']): ?><span class="td"><?= e(mb_substr((string)$t['description'], 0, 220)) ?></span><?php endif; ?></a>
<?php endforeach; echo $formHtml; ?>
<p class="row"><?php if ($own): ?><a class="btn primary" href="<?= e($self . '?add=root#taskform') ?>">＋ <?= e($sf->t('add_root')) ?></a><?php endif; ?><?php if ($tasks): ?><a class="btn" href="<?= e($self . '?view=tree') ?>"><?= e($sf->t('view_tree')) ?></a><?php endif; ?></p>
<?php endif; ?>
<script>(function(){   // lightbox for task images: arrows / swipe / Esc
var box,img,cnt,list=[],i=0;
function build(){box=document.createElement("div");box.className="lbx";box.hidden=true;
 box.innerHTML='<button type="button" class="lb-x" aria-label="Close">×</button><button type="button" class="lb-p" aria-label="Previous">‹</button><img alt=""><button type="button" class="lb-n" aria-label="Next">›</button><div class="lb-c"></div>';
 document.body.appendChild(box);img=box.querySelector("img");cnt=box.querySelector(".lb-c");
 box.querySelector(".lb-x").onclick=close;box.querySelector(".lb-p").onclick=function(){go(-1)};box.querySelector(".lb-n").onclick=function(){go(1)};
 box.addEventListener("click",function(e){if(e.target===box)close()});
 var x0=null;box.addEventListener("touchstart",function(e){x0=e.touches[0].clientX},{passive:true});
 box.addEventListener("touchend",function(e){if(x0===null)return;var d=e.changedTouches[0].clientX-x0;x0=null;if(Math.abs(d)>40)go(d<0?1:-1)});}
function show(){var a=list[i];img.src=a.href;img.alt=(a.querySelector("img")||{}).alt||"";cnt.textContent=(i+1)+" / "+list.length;
 box.classList.toggle("one",list.length<2);}
function go(d){if(list.length<2)return;i=(i+d+list.length)%list.length;show()}
function close(){box.hidden=true;img.removeAttribute("src");document.documentElement.classList.remove("lb-open")}
document.addEventListener("click",function(e){var a=e.target.closest("a[data-lb]");if(!a||e.ctrlKey||e.metaKey||e.shiftKey)return;e.preventDefault();
 if(!box)build();list=[].slice.call(document.querySelectorAll('a[data-lb="'+a.getAttribute("data-lb")+'"]'));i=list.indexOf(a);box.hidden=false;document.documentElement.classList.add("lb-open");show();});
document.addEventListener("keydown",function(e){if(!box||box.hidden)return;if(e.key==="Escape")close();else if(e.key==="ArrowLeft")go(-1);else if(e.key==="ArrowRight")go(1);});
})();</script>
<?php $mapKeyY = (string)($setup['yandex_maps_key'] ?? ''); $mapKeyM = (string)($setup['mapbox_key'] ?? ''); if (($mapKeyY !== '' || $mapKeyM !== '')): ?>
<script>(function(){var els=document.querySelectorAll(".tmap[data-address]");if(!els.length)return;var ru=<?= json_encode($lang === 'ru') ?>;
function yandex(){els.forEach(function(el){ymaps.ready(function(){var a=el.getAttribute("data-address");el.innerHTML="";var map=new ymaps.Map(el,{center:[55.7558,37.6173],zoom:13,controls:["zoomControl"]});
 ymaps.geocode(a).then(function(r){var o=r.geoObjects.get(0);if(o){var c=o.geometry.getCoordinates();map.setCenter(c);map.geoObjects.add(new ymaps.Placemark(c))}})})})}
function mapbox(){var l=document.createElement("link");l.rel="stylesheet";l.href="https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.css";document.head.appendChild(l);var s=document.createElement("script");s.src="https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.js";
 s.onload=function(){mapboxgl.accessToken=<?= json_encode($mapKeyM) ?>;els.forEach(function(el){var a=el.getAttribute("data-address");fetch("https://api.mapbox.com/geocoding/v5/mapbox.places/"+encodeURIComponent(a)+".json?access_token="+encodeURIComponent(mapboxgl.accessToken)).then(function(r){return r.json()}).then(function(j){var f=j.features&&j.features[0];if(!f)return;el.innerHTML="";var m=new mapboxgl.Map({container:el,style:"mapbox://styles/mapbox/light-v11",center:f.center,zoom:13});new mapboxgl.Marker().setLngLat(f.center).addTo(m)})})};document.head.appendChild(s)}
<?php if ($mapKeyY !== ''): ?>var y=document.createElement("script");y.src="https://api-maps.yandex.ru/2.1/?apikey="+encodeURIComponent(<?= json_encode($mapKeyY) ?>)+"&lang="+(ru?"ru_RU":"en_US");y.onload=yandex;<?php if ($mapKeyM !== ''): ?>y.onerror=mapbox;<?php endif; ?>document.head.appendChild(y);
<?php else: ?>mapbox();<?php endif; ?>
})();</script>
<?php endif; ?>
</div>
