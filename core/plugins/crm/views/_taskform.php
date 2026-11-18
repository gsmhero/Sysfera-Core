<?php /** @var \Sysfera\Plugin $sf @var array $users @var array $prof @var ?int $dealId @var ?int $contactId @var string $backTo */
$dealId = $dealId ?? null; $contactId = $contactId ?? null; $lang = \Sysfera\I18n::lang(); $first = (string)array_key_first(crm_task_types($sf));
$task = $task ?? null; $tv = []; if ($task) { $tv = (array)(json_decode((string)($task['type_data'] ?? ''), true) ?: []); if (isset(crm_task_types($sf)[(string)$task['type']])) $first = (string)$task['type']; } ?>
<form method="post" action="<?= e($sf->url($task ? '/task/' . (int)$task['id'] . '/save' : '/task/save')) ?>" class="crm-form crm-taskform"><?= csrf_field() ?>
  <input type="hidden" name="back" value="<?= e($backTo ?? '/p/crm/tasks') ?>">
  <?php if ($dealId): ?><input type="hidden" name="deal_id" value="<?= (int)$dealId ?>"><?php endif; ?>
  <?php if ($contactId): ?><input type="hidden" name="contact_id" value="<?= (int)$contactId ?>"><?php endif; ?>
  <div class="cols">
    <label class="fld"><span><?= e($sf->t('f_type')) ?></span><select name="type" class="crm-ttype"><?php foreach (crm_task_types($sf) as $code => $ty): ?><option value="<?= e($code) ?>"<?= $task && $code === $first ? ' selected' : '' ?>><?= e(\Sysfera\TaskTypes::label($ty, $lang)) ?></option><?php endforeach; ?></select></label>
    <label class="fld" style="grid-column:span 2"><span><?= e($sf->t('f_title')) ?> *</span><input name="title" maxlength="190" required value="<?= e($task['title'] ?? '') ?>"></label>
    <label class="fld"><span><?= e($sf->t('f_due')) ?></span><input type="date" name="due_date" value="<?= e($task ? substr((string)$task['due_at'], 0, 10) : date('Y-m-d')) ?>"></label>
    <label class="fld"><span><?= e($sf->t('f_time')) ?></span><input type="time" name="due_time" value="<?= e($task && $task['due_at'] && substr((string)$task['due_at'], 11, 5) !== '00:00' ? substr((string)$task['due_at'], 11, 5) : '') ?>"></label>
    <?php if (!empty($prof['reassign'])): ?><label class="fld"><span><?= e($sf->t('f_assignee')) ?></span><select name="assignee_id"><?php foreach ($users as $u): ?><option value="<?= (int)$u['id'] ?>"<?= (int)$u['id'] === ($task ? (int)$task['assignee_id'] : $sf->auth()->id()) ? ' selected' : '' ?>><?= e($u['name']) ?></option><?php endforeach; ?></select></label><?php endif; ?>
  </div>
  <?php foreach (crm_task_types($sf) as $code => $ty): $fl = array_values(array_filter($ty['fields'], fn($f) => $f['kind'] !== 'task' && !($f['kind'] === 'contact' && $contactId))); if (!$fl) continue; ?>
  <div class="cols crm-tfields" data-t="<?= e($code) ?>" <?= $code === $first ? '' : 'hidden' ?>>
    <?php foreach ($fl as $f): $dis = $code === $first ? '' : 'disabled'; $k = $f['key']; $label = \Sysfera\TaskTypes::fieldLabel($f, $lang); ?>
    <label class="fld"><span><?= e($label) ?></span>
      <?php if ($f['kind'] === 'contact'): ?><span class="crm-contactpick" data-find="<?= e($sf->url('/contacts/find')) ?>"><input type="search" class="cp-q" placeholder="<?= e($sf->t('contacts')) ?>…" autocomplete="off"><select name="td[<?= e($k) ?>]" class="cp-sel" <?= $dis ?>><option value="">—</option><?php if (!empty($curContact)): ?><option value="<?= (int)$curContact['id'] ?>" selected><?= e($curContact['name'] . ((string)$curContact['company'] !== '' ? ' · ' . $curContact['company'] : '')) ?></option><?php endif; ?></select></span>
      <?php elseif ($f['kind'] === 'textarea'): ?><textarea name="td[<?= e($k) ?>]" rows="2" <?= $dis ?>><?= e((string)($tv[$k] ?? '')) ?></textarea>
      <?php else: ?><input <?= $f['kind'] === 'email' ? 'type="email"' : ($f['kind'] === 'phone' ? 'type="tel"' : (in_array($f['kind'], ['url', 'shop'], true) ? 'type="url" placeholder="https://"' : '')) ?> name="td[<?= e($k) ?>]" <?= $dis ?> value="<?= e((string)($tv[$k] ?? '')) ?>"><?php endif; ?></label>
    <?php endforeach; ?></div>
  <?php endforeach; ?>
  <?php $report = (string)($task['report'] ?? ''); $links = \Sysfera\Attach::decode($task['links'] ?? null); $docIds = \Sysfera\Attach::decode($task['wdocs'] ?? null); $fileIds = \Sysfera\Attach::decode($task['tfiles'] ?? null); $missionIds = \Sysfera\Attach::decode($task['tmissions'] ?? null); require dirname(__DIR__, 3) . '/views/_attach_fields.php'; ?>
  <label class="fld"><span><?= e($sf->t('f_notes')) ?></span><input name="notes" maxlength="3000" value="<?= e($task['notes'] ?? '') ?>"></label>
  <button class="btn primary"><?= e($sf->t($task ? 'save' : 'add_task')) ?></button>
</form>
<script>(function(){var f=document.currentScript.previousElementSibling;if(!f||f.getAttribute("data-init"))return;f.setAttribute("data-init","1");var s=f.querySelector(".crm-ttype");if(!s)return;
function a(){f.querySelectorAll(".crm-tfields").forEach(function(d){var on=d.getAttribute("data-t")===s.value;d.hidden=!on;d.querySelectorAll("input,select,textarea").forEach(function(i){i.disabled=!on})})}
s.addEventListener("change",a);
f.querySelectorAll(".crm-contactpick").forEach(function(w){var q=w.querySelector(".cp-q"),sel=w.querySelector(".cp-sel"),tm=0,box=w.closest(".crm-tfields");
 function fill(n,v){var i=box&&box.querySelector('[name="td['+n+']"]');if(i&&v)i.value=v}
 q.addEventListener("input",function(){clearTimeout(tm);tm=setTimeout(function(){if(q.value.length<2)return;
  fetch(w.getAttribute("data-find")+"?q="+encodeURIComponent(q.value),{credentials:"same-origin"}).then(function(r){return r.json()}).then(function(rows){
   sel.innerHTML='<option value="">—</option>';rows.forEach(function(c){var o=document.createElement("option");o.value=c.id;o.textContent=c.name+(c.company?" · "+c.company:"");o.dataset.phone=c.phone||"";o.dataset.email=c.email||"";sel.appendChild(o)});
   if(rows.length)sel.value=rows[0].id;sel.dispatchEvent(new Event("change"))}).catch(function(){})},250)});
 sel.addEventListener("change",function(){var o=sel.options[sel.selectedIndex];if(!o||!o.value)return;fill("phone",o.dataset.phone);fill("to",o.dataset.email)})});
})();</script>
