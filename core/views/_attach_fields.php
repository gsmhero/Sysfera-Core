<?php /** Form part shared by the tasks of all components. @var string $report @var array $links (list of {url,title}) @var list<int> $docIds @var list<int> $fileIds */
$report = (string)($report ?? ''); $links = (array)($links ?? []); $docIds = (array)($docIds ?? []); $fileIds = (array)($fileIds ?? []);
$pickers = [['docs', 'wdoc', $docIds, 'att.docs', 'att.find_doc'], ['files', 'tfile', $fileIds, 'att.files', 'att.find_file']];
$rows = array_slice($links, 0, \Sysfera\Attach::MAX_LINKS); if (count($rows) < \Sysfera\Attach::MAX_LINKS) $rows[] = ['url' => '', 'title' => '']; ?>
<div class="att">
  <label class="fld"><span><?= e(t('att.report')) ?></span><textarea name="report" rows="3" maxlength="4000" placeholder="<?= e(t('att.report_ph')) ?>"><?= e($report) ?></textarea></label>
  <div class="fld"><span><?= e(t('att.links')) ?></span>
    <div class="att-links" data-max="<?= (int)\Sysfera\Attach::MAX_LINKS ?>"><?php foreach ($rows as $l): ?>
      <div class="att-lrow"><input type="url" name="link_url[]" placeholder="https://" maxlength="500" value="<?= e($l['url']) ?>"><input name="link_title[]" placeholder="<?= e(t('att.link_title')) ?>" maxlength="100" value="<?= e($l['title']) ?>"></div><?php endforeach; ?></div>
    <button type="button" class="btn sm att-addlink">+ <?= e(t('att.add_link')) ?></button></div>
  <?php foreach ($pickers as [$kind, $field, $ids, $label, $ph]): $av = \Sysfera\Attach::avail($kind); if (!$av) continue; $have = \Sysfera\Attach::items($kind, $ids); ?>
  <div class="fld att-docs" data-find="<?= e($av['find']) ?>" data-name="<?= e($field) ?>" data-max="<?= (int)\Sysfera\Attach::MAX_DOCS ?>"><span><?= e(t($label)) ?></span>
    <div class="att-chips"><?php foreach ($have as $d): ?><span class="att-chip"><a href="<?= e($d['url']) ?>" target="_blank" rel="noopener"><?= e($d['label']) ?></a><input type="hidden" name="<?= e($field) ?>[]" value="<?= (int)$d['id'] ?>"><button type="button" class="att-x" title="<?= e(t('att.remove')) ?>">×</button></span><?php endforeach; ?></div>
    <input type="search" class="att-q" placeholder="<?= e(t($ph)) ?>" autocomplete="off"><div class="att-res" hidden></div></div>
  <?php endforeach; ?>
</div>
<script>(function(){if(window.__attJs)return;window.__attJs=1;
document.addEventListener('click',function(e){
 var b=e.target.closest('.att-addlink');if(b){var box=b.closest('.fld').querySelector('.att-links'),rows=box.querySelectorAll('.att-lrow'),max=parseInt(box.dataset.max,10)||10;if(rows.length>=max)return;var n=rows[rows.length-1].cloneNode(true);n.querySelectorAll('input').forEach(function(i){i.value=''});box.appendChild(n);n.querySelector('input').focus();if(rows.length+1>=max)b.disabled=true;return}
 var x=e.target.closest('.att-x');if(x){x.closest('.att-chip').remove();return}
 var r=e.target.closest('.att-res button');if(r){var w=r.closest('.att-docs'),ch=w.querySelector('.att-chips');if(ch.querySelectorAll('.att-chip').length>=(parseInt(w.dataset.max,10)||10))return;if(ch.querySelector('input[value="'+r.dataset.id+'"]'))return;
  var s=document.createElement('span');s.className='att-chip';var a=document.createElement('a');a.href=r.dataset.url;a.target='_blank';a.rel='noopener';a.textContent=r.dataset.label;var h=document.createElement('input');h.type='hidden';h.name=w.dataset.name+'[]';h.value=r.dataset.id;var k=document.createElement('button');k.type='button';k.className='att-x';k.textContent='\u00d7';s.appendChild(a);s.appendChild(h);s.appendChild(k);ch.appendChild(s);w.querySelector('.att-res').hidden=true;w.querySelector('.att-q').value=''}});
var tm=0;document.addEventListener('input',function(e){var q=e.target.closest?e.target.closest('.att-q'):null;if(!q)return;var w=q.closest('.att-docs'),res=w.querySelector('.att-res');clearTimeout(tm);
 tm=setTimeout(function(){if(q.value.trim().length<1){res.hidden=true;return}fetch(w.dataset.find+'?q='+encodeURIComponent(q.value),{credentials:'same-origin'}).then(function(r){return r.json()}).then(function(rows){res.textContent='';
  rows.forEach(function(d){var b=document.createElement('button');b.type='button';b.textContent=d.label;b.dataset.id=d.id;b.dataset.label=d.label;b.dataset.url=d.url;res.appendChild(b)});res.hidden=!rows.length}).catch(function(){})},250)});
})();</script>
