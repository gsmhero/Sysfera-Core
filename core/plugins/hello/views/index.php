<?php /** @var array $notes @var \Sysfera\Plugin $sf @var string $q @var string $tag @var string $sort @var int $prio @var array<int,int> $counts @var array $allTags @var list<string> $themes @var int $uid @var ?array $edit @var list<array> $users @var string $myEmail */
$qs = '?' . http_build_query(array_filter(['q' => $q, 'tag' => $tag, 'sort' => $sort !== 'new' ? $sort : '', 'priority' => $prio ?: ''], fn($v) => $v !== ''));
$qsField = e($qs === '?' ? '' : $qs);
$tagList = fn(string $s): array => array_values(array_filter(explode(',', $s)));
$dt = fn(?string $d): string => $d ? date('d/m/Y H:i', strtotime($d)) : '';
$id = $edit ? (int)$edit['id'] : 0;
$level = fn($p): int => in_array((int)$p, [1, 2, 3], true) ? (int)$p : 2;
$editPrio = $level($edit['priority'] ?? 2);
/* group by theme: alphabetical, "No theme" last; the notes keep the order of the selected sort */
$groups = [];
foreach ($notes as $n) { $th = trim((string)($n['theme'] ?? '')); $k = mb_strtolower($th); $groups[$k] ??= ['name' => $th, 'notes' => []]; $groups[$k]['notes'][] = $n; }
uksort($groups, fn($a, $b) => $a === '' ? ($b === '' ? 0 : 1) : ($b === '' ? -1 : strcmp((string)$a, (string)$b)));
$ico = ['card' => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="8" height="8" rx="1.5"/><rect x="13" y="3" width="8" height="8" rx="1.5"/><rect x="3" y="13" width="8" height="8" rx="1.5"/><rect x="13" y="13" width="8" height="8" rx="1.5"/></svg>',
        'list' => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M3 5h18M3 9.5h18M3 14h18M3 18.5h18"/></svg>']; ?>
<div class="page-h"><h1><?= e($sf->t('title')) ?></h1></div>
<form class="card nt-bar" method="get"><input type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e($sf->t('search')) ?>">
  <select name="sort"><?php foreach (['new', 'old', 'tags', 'title'] as $k): ?><option value="<?= $k ?>" <?= $sort === $k ? 'selected' : '' ?>><?= e($sf->t('sort_' . $k)) ?></option><?php endforeach; ?></select>
  <select name="priority" id="nt-pfilter" aria-label="<?= e($sf->t('f_priority')) ?>"><option value=""><?= e($sf->t('prio_all')) ?></option><?php foreach ([3, 2, 1] as $p): ?><option value="<?= $p ?>" <?= $prio === $p ? 'selected' : '' ?>><?= e($sf->t('f_priority') . ': ' . $sf->t('prio_' . $p)) ?> (<?= (int)($counts[$p] ?? 0) ?>)</option><?php endforeach; ?></select>
  <?php if ($tag !== ''): ?><input type="hidden" name="tag" value="<?= e($tag) ?>"><?php endif; ?>
  <button class="btn"><?= e($sf->t('find')) ?></button>
  <?php if ($q !== '' || $tag !== '' || $prio): ?><a class="btn" href="<?= e($sf->url('/')) ?>"><?= e($sf->t('clear')) ?></a><?php endif; ?></form>

<div class="nt-sub">
  <div class="nt-views" role="group" aria-label="<?= e($sf->t('view')) ?>">
    <button type="button" class="nt-vb on" data-view="card" title="<?= e($sf->t('view_card')) ?>" aria-label="<?= e($sf->t('view_card')) ?>" aria-pressed="true"><?= $ico['card'] ?></button>
    <button type="button" class="nt-vb" data-view="list" title="<?= e($sf->t('view_list')) ?>" aria-label="<?= e($sf->t('view_list')) ?>" aria-pressed="false"><?= $ico['list'] ?></button>
  </div>
  <?php if ($allTags): ?><div class="nt-tags nt-filter"><?php foreach ($allTags as $t => $c): ?><a class="nt-tag <?= $tag === (string)$t ? 'on' : '' ?>" href="<?= e($sf->url('/?' . http_build_query(['tag' => $tag === (string)$t ? '' : $t, 'q' => $q, 'sort' => $sort, 'priority' => $prio ?: '']))) ?>">#<?= e((string)$t) ?> <small><?= (int)$c ?></small></a><?php endforeach; ?></div><?php endif; ?>
</div>

<div class="nt-wrap" id="nt-root" data-uid="<?= (int)$uid ?>">
<script>try{if(localStorage.getItem('sysfera.notes.view.<?= (int)$uid ?>')==='list')document.getElementById('nt-root').classList.add('nt-as-list')}catch(x){}</script>
<aside class="nt-side"><form class="card nt-form" method="post" id="nt-form"><?= csrf_field() ?><input type="hidden" name="do" value="save"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="qs" value="<?= $qsField ?>">
  <b><?= e($sf->t($edit ? 'edit_note' : 'new')) ?></b>
  <label class="fld"><span><?= e($sf->t('f_title')) ?></span><input name="title" maxlength="190" value="<?= e($edit['title'] ?? '') ?>"></label>
  <label class="fld"><span><?= e($sf->t('f_theme')) ?></span><input name="theme" list="nt-themes" maxlength="80" autocomplete="off" placeholder="<?= e($sf->t('no_theme')) ?>" value="<?= e($edit['theme'] ?? '') ?>"></label>
  <datalist id="nt-themes"><?php foreach ($themes as $th): ?><option value="<?= e($th) ?>"><?php endforeach; ?></datalist>
  <label class="fld"><span><?= e($sf->t('f_priority')) ?></span><select name="priority"><?php foreach ([3, 2, 1] as $p): ?><option value="<?= $p ?>" <?= $editPrio === $p ? 'selected' : '' ?>><?= e($sf->t('prio_' . $p)) ?></option><?php endforeach; ?></select></label>
  <label class="fld"><span><?= e($sf->t('note')) ?></span><textarea name="text" rows="4" maxlength="5000"><?= e($edit['body'] ?? '') ?></textarea></label>
  <label class="fld"><span><?= e($sf->t('f_tags')) ?></span><input name="tags" list="nt-tags" maxlength="200" placeholder="<?= e($sf->t('tags_ph')) ?>" value="<?= e(implode(', ', $tagList((string)($edit['tags'] ?? '')))) ?>"></label>
  <datalist id="nt-tags"><?php foreach (array_keys($allTags) as $t): ?><option value="<?= e((string)$t) ?>"><?php endforeach; ?></datalist>
  <div class="row"><button class="btn primary"><?= e($sf->t($edit ? 'save' : 'add')) ?></button><?php if ($edit): ?><a class="btn" href="<?= e($sf->url('/' . ($qs === '?' ? '' : $qs))) ?>"><?= e(t('common.cancel')) ?></a><?php endif; ?></div>
</form></aside>

<div class="nt-groups">
<?php foreach ($groups as $k => $g): ?>
<details class="nt-group" open data-key="<?= e((string)$k) ?>"><summary><span class="nt-gname<?= $g['name'] === '' ? ' none' : '' ?>"><?= e($g['name'] !== '' ? $g['name'] : $sf->t('no_theme')) ?></span><small class="nt-gcount"><?= count($g['notes']) ?></small></summary>
<div class="nt-list"><?php foreach ($g['notes'] as $n): $parts = preg_split('/\R/', trim((string)$n['body']), 2); $title = trim((string)$n['title']) !== '' ? (string)$n['title'] : (string)$parts[0]; $text = trim((string)$n['title']) !== '' ? (string)$n['body'] : (string)($parts[1] ?? ''); $np = $level($n['priority'] ?? 2); ?>
  <article class="card nt-note<?= $n['from_user_id'] ? ' nt-recv' : '' ?>">
    <div class="nt-head"><h3><?= e($title !== '' ? $title : '—') ?></h3><small class="muted nt-date" title="<?= e($dt($n['created_at'])) ?>"><?= e($dt($n['updated_at'] ?: $n['created_at'])) ?></small></div>
    <?php if ($g['name'] !== ''): ?><div class="nt-theme"><?= e($g['name']) ?></div><?php endif; ?>
    <?php if ($n['from_user_id']): ?><div class="nt-from"><?= e($sf->t('from')) ?>: <b><?= e((string)($n['author'] ?: '—')) ?></b></div><?php endif; ?>
    <?php if ($text !== ''): ?><p><?= nl2br(e($text)) ?></p><?php endif; ?>
    <?php if ($n['tags'] !== ''): ?><div class="nt-tags"><?php foreach ($tagList((string)$n['tags']) as $t): ?><a class="nt-tag" href="<?= e($sf->url('/?tag=' . rawurlencode($t))) ?>">#<?= e($t) ?></a><?php endforeach; ?></div><?php endif; ?>
    <div class="row nt-act"><a class="btn sm" href="<?= e($sf->url('/?' . http_build_query(['edit' => $n['id'], 'q' => $q, 'tag' => $tag, 'sort' => $sort !== 'new' ? $sort : '', 'priority' => $prio ?: '']) . '#nt-form')) ?>"><?= e($sf->t('edit')) ?></a>
      <form method="post" class="nt-prio nt-p<?= $np ?>"><?= csrf_field() ?><input type="hidden" name="do" value="priority"><input type="hidden" name="id" value="<?= (int)$n['id'] ?>"><input type="hidden" name="qs" value="<?= $qsField ?>">
        <select name="priority" title="<?= e($sf->t('f_priority')) ?>" aria-label="<?= e($sf->t('f_priority')) ?>"><?php foreach ([3, 2, 1] as $p): ?><option value="<?= $p ?>" <?= $np === $p ? 'selected' : '' ?>><?= e($sf->t('prio_' . $p)) ?></option><?php endforeach; ?></select><noscript><button class="btn sm"><?= e($sf->t('save')) ?></button></noscript></form>
      <details class="nt-share"><summary class="btn sm"><?= e($sf->t('share')) ?></summary>
        <div class="nt-shp"><form method="post" class="nt-sh"><?= csrf_field() ?><input type="hidden" name="do" value="send"><input type="hidden" name="id" value="<?= (int)$n['id'] ?>"><input type="hidden" name="qs" value="<?= $qsField ?>">
          <label class="fld"><span><?= e($sf->t('to_user')) ?></span><select name="user" required><option value=""><?= e($sf->t('pick_user')) ?></option><?php foreach ($users as $u): ?><option value="<?= (int)$u['id'] ?>"><?= e($u['name']) ?></option><?php endforeach; ?></select></label>
          <button class="btn sm primary"><?= e($sf->t('send_user')) ?></button></form>
        <form method="post" class="nt-sh"><?= csrf_field() ?><input type="hidden" name="do" value="email"><input type="hidden" name="id" value="<?= (int)$n['id'] ?>"><input type="hidden" name="qs" value="<?= $qsField ?>">
          <label class="fld"><span><?= e($sf->t('to_email')) ?></span><input type="text" name="to" placeholder="name@example.com" required></label>
          <small class="muted"><?= e($sf->t('from_you', ['e' => $myEmail])) ?></small>
          <button class="btn sm primary"><?= e($sf->t('send_email')) ?></button></form></div></details>
      <form method="post" data-confirm="<?= e($sf->t('confirm')) ?>"><?= csrf_field() ?><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= (int)$n['id'] ?>"><input type="hidden" name="qs" value="<?= $qsField ?>"><button class="btn sm danger"><?= e(t('common.delete')) ?></button></form></div>
  </article>
<?php endforeach; ?></div></details>
<?php endforeach; ?>
<?php if (!$notes): ?><div class="card empty"><p class="muted"><?= e($sf->t($q !== '' || $tag !== '' || $prio ? 'none_found' : 'none')) ?></p></div><?php endif; ?>
</div></div>
<script>
(function(){var root=document.getElementById('nt-root');if(!root)return;var uid=root.getAttribute('data-uid'),vk='sysfera.notes.view.'+uid,ck='sysfera.notes.closed.'+uid;
function get(k){try{return localStorage.getItem(k)}catch(x){return null}}function put(k,v){try{localStorage.setItem(k,v)}catch(x){}}
var btns=document.querySelectorAll('.nt-vb');
function show(v){root.classList.toggle('nt-as-list',v==='list');btns.forEach(function(b){var on=b.getAttribute('data-view')===v;b.classList.toggle('on',on);b.setAttribute('aria-pressed',on?'true':'false')})}
show(get(vk)==='list'?'list':'card');
btns.forEach(function(b){b.addEventListener('click',function(){var v=b.getAttribute('data-view');put(vk,v);show(v)})});
var pf=document.getElementById('nt-pfilter');if(pf)pf.addEventListener('change',function(){pf.form.submit()});
document.querySelectorAll('.nt-prio select').forEach(function(s){s.addEventListener('change',function(){s.form.submit()})});
var closed=[];try{closed=JSON.parse(get(ck)||'[]')||[]}catch(x){closed=[]}
document.querySelectorAll('.nt-group').forEach(function(d){var k=d.getAttribute('data-key');if(closed.indexOf(k)>-1)d.open=false;
d.addEventListener('toggle',function(){var i=closed.indexOf(k);if(d.open&&i>-1)closed.splice(i,1);if(!d.open&&i<0)closed.push(k);put(ck,JSON.stringify(closed.slice(-100)))})});
})();
</script>
<style>
.nt-form,.nt-bar{display:grid;gap:12px}.nt-bar{grid-template-columns:1fr auto auto auto auto;align-items:center}@media(max-width:760px){.nt-bar{grid-template-columns:1fr}}
.nt-prio{display:flex;gap:6px;align-items:center;margin:0}.nt-prio select{width:auto;padding:3px 8px;font-size:13px}.nt-p3 select{border-color:var(--bad);background:var(--badbg);color:var(--bad);font-weight:600}.nt-p1 select{color:var(--mut)}
.nt-sub{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin:12px 0 4px}
.nt-views{display:flex;gap:8px}.nt-vb{display:inline-flex;align-items:center;justify-content:center;width:44px;height:40px;padding:0;border:2px solid var(--line);border-radius:8px;background:var(--card);color:var(--ink);cursor:pointer}.nt-vb:hover{border-color:var(--acc)}.nt-vb.on{border-color:var(--ink)}
.nt-tags{display:flex;gap:6px;flex-wrap:wrap;margin:10px 0}.nt-filter{margin:0;justify-content:flex-end}.nt-tag{display:inline-block;padding:2px 10px;border:1px solid var(--line);border-radius:999px;font-size:13px;color:var(--ink);text-decoration:none;background:var(--card)}.nt-tag:hover,.nt-tag.on{border-color:var(--acc);color:var(--acc)}.nt-tag small{color:var(--mut)}
.nt-wrap{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;align-items:start;margin-top:10px}
.nt-groups{grid-column:1/3;grid-row:1;min-width:0}.nt-side{grid-column:3;grid-row:1;position:sticky;top:12px}.nt-side .nt-form{margin:0}
@media(max-width:1000px){.nt-wrap{grid-template-columns:minmax(0,1fr)}.nt-groups,.nt-side{grid-column:1;position:static}.nt-groups{grid-row:2}}
.nt-group{margin:0 0 18px}.nt-group>summary{display:flex;align-items:center;gap:8px;cursor:pointer;list-style:none;font-weight:700;font-size:24px;padding:4px 0 10px}.nt-group>summary::-webkit-details-marker{display:none}
.nt-group>summary::before{content:"\25BE";color:var(--mut);transition:transform .15s}.nt-group:not([open])>summary::before{transform:rotate(-90deg)}
.nt-gname.none{color:var(--mut);font-weight:600}.nt-gcount{font-weight:400;color:var(--mut);border:1px solid var(--line);border-radius:999px;padding:0 8px}
.nt-list{column-count:2;column-gap:14px}.nt-note{margin:0 0 14px;break-inside:avoid;display:flex;flex-direction:column;gap:6px}
@media(max-width:640px){.nt-list{column-count:1}}
.nt-head{display:flex;justify-content:space-between;align-items:baseline;gap:4px 10px;flex-wrap:wrap}.nt-head h3{margin:0;font-size:17px;min-width:0;word-break:break-word}.nt-date{white-space:nowrap}
.nt-note p{margin:0;word-break:break-word}.nt-theme{display:none;font-size:13px;color:var(--mut)}
.nt-recv{border-left:3px solid var(--acc)}.nt-from{font-size:13px;color:var(--mut)}.nt-from b{color:var(--acc)}
.nt-act{margin-top:auto;padding-top:8px;gap:8px;flex-wrap:wrap;align-items:flex-start}.nt-share{position:relative}.nt-share summary{list-style:none;cursor:pointer}.nt-share summary::-webkit-details-marker{display:none}
.nt-share[open]{flex-basis:100%}.nt-sh{display:grid;gap:8px;margin:10px 0;padding:10px;border:1px solid var(--line);border-radius:10px;background:var(--bg)}
/* list view: one compact row per note */
.nt-as-list .nt-list{column-count:1;column-gap:0}.nt-as-list .nt-note{flex-direction:row;flex-wrap:wrap;align-items:center;gap:6px 14px;padding:8px 14px;margin:0 0 6px}
.nt-as-list .nt-head{flex:1 1 220px;order:1;flex-wrap:nowrap}.nt-as-list .nt-head h3{font-size:15px}.nt-as-list .nt-theme{display:block;order:2;flex:0 0 140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.nt-as-list .nt-note p,.nt-as-list .nt-from{display:none}.nt-as-list .nt-tags{order:3;margin:0;flex:0 1 200px}.nt-as-list .nt-act{order:5;margin:0 0 0 auto;padding:0;position:relative}
.nt-as-list .nt-share[open]{flex-basis:auto}.nt-as-list .nt-shp{position:absolute;right:0;top:100%;z-index:5;width:300px;padding:6px;border:1px solid var(--line);border-radius:10px;background:var(--card);box-shadow:0 6px 20px rgba(0,0,0,.12)}
@media(max-width:640px){.nt-as-list .nt-theme{flex-basis:100%}.nt-as-list .nt-shp{width:min(300px,80vw)}}
</style>
