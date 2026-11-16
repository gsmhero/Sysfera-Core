<?php /** @var array $notes @var \Sysfera\Plugin $sf @var string $q @var string $tag @var string $sort @var array $allTags @var ?array $edit @var list<array> $users @var string $myEmail */
$qs = '?' . http_build_query(array_filter(['q' => $q, 'tag' => $tag, 'sort' => $sort !== 'new' ? $sort : ''], fn($v) => $v !== ''));
$tagList = fn(string $s): array => array_values(array_filter(explode(',', $s)));
$dt = fn(?string $d): string => $d ? date('d/m/Y H:i', strtotime($d)) : '';
$id = $edit ? (int)$edit['id'] : 0; ?>
<div class="page-h"><h1><?= e($sf->t('title')) ?></h1></div>
<form class="card nt-form" method="post" id="nt-form"><?= csrf_field() ?><input type="hidden" name="do" value="save"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="qs" value="<?= e($qs === '?' ? '' : $qs) ?>">
  <b><?= e($sf->t($edit ? 'edit' : 'new')) ?></b>
  <label class="fld"><span><?= e($sf->t('f_title')) ?></span><input name="title" maxlength="190" value="<?= e($edit['title'] ?? '') ?>"></label>
  <label class="fld"><span><?= e($sf->t('note')) ?></span><textarea name="text" rows="4" maxlength="5000"><?= e($edit['body'] ?? '') ?></textarea></label>
  <label class="fld"><span><?= e($sf->t('f_tags')) ?></span><input name="tags" list="nt-tags" maxlength="200" placeholder="<?= e($sf->t('tags_ph')) ?>" value="<?= e(implode(', ', $tagList((string)($edit['tags'] ?? '')))) ?>"></label>
  <datalist id="nt-tags"><?php foreach (array_keys($allTags) as $t): ?><option value="<?= e($t) ?>"><?php endforeach; ?></datalist>
  <div class="row"><button class="btn primary"><?= e($sf->t($edit ? 'save' : 'add')) ?></button><?php if ($edit): ?><a class="btn" href="<?= e($sf->url('/' . ($qs === '?' ? '' : $qs))) ?>"><?= e(t('common.cancel')) ?></a><?php endif; ?></div>
</form>

<form class="card nt-bar" method="get"><input type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e($sf->t('search')) ?>">
  <select name="sort"><?php foreach (['new', 'old', 'tags', 'title'] as $k): ?><option value="<?= $k ?>" <?= $sort === $k ? 'selected' : '' ?>><?= e($sf->t('sort_' . $k)) ?></option><?php endforeach; ?></select>
  <?php if ($tag !== ''): ?><input type="hidden" name="tag" value="<?= e($tag) ?>"><?php endif; ?>
  <button class="btn"><?= e($sf->t('find')) ?></button>
  <?php if ($q !== '' || $tag !== ''): ?><a class="btn" href="<?= e($sf->url('/')) ?>"><?= e($sf->t('clear')) ?></a><?php endif; ?></form>
<?php if ($allTags): ?><div class="nt-tags"><?php foreach ($allTags as $t => $c): ?><a class="nt-tag <?= $tag === (string)$t ? 'on' : '' ?>" href="<?= e($sf->url('/?' . http_build_query(['tag' => $t, 'q' => $q, 'sort' => $sort]))) ?>">#<?= e((string)$t) ?> <small><?= (int)$c ?></small></a><?php endforeach; ?></div><?php endif; ?>

<div class="nt-list"><?php foreach ($notes as $n): $parts = preg_split('/\R/', trim((string)$n['body']), 2); $title = trim((string)$n['title']) !== '' ? (string)$n['title'] : (string)$parts[0]; $text = trim((string)$n['title']) !== '' ? (string)$n['body'] : (string)($parts[1] ?? ''); ?>
  <article class="card nt-note<?= $n['from_user_id'] ? ' nt-recv' : '' ?>">
    <div class="row between"><h3><?= e($title !== '' ? $title : '—') ?></h3><small class="muted" title="<?= e($dt($n['created_at'])) ?>"><?= e($dt($n['updated_at'] ?: $n['created_at'])) ?></small></div>
    <?php if ($n['from_user_id']): ?><div class="nt-from"><?= e($sf->t('from')) ?>: <b><?= e((string)($n['author'] ?: '—')) ?></b></div><?php endif; ?>
    <?php if ($text !== ''): ?><p><?= nl2br(e($text)) ?></p><?php endif; ?>
    <?php if ($n['tags'] !== ''): ?><div class="nt-tags"><?php foreach ($tagList((string)$n['tags']) as $t): ?><a class="nt-tag" href="<?= e($sf->url('/?tag=' . rawurlencode($t))) ?>">#<?= e($t) ?></a><?php endforeach; ?></div><?php endif; ?>
    <div class="row nt-act"><a class="btn sm" href="<?= e($sf->url('/?' . http_build_query(['edit' => $n['id'], 'q' => $q, 'tag' => $tag, 'sort' => $sort !== 'new' ? $sort : '']) . '#nt-form')) ?>"><?= e($sf->t('edit')) ?></a>
      <details class="nt-share"><summary class="btn sm"><?= e($sf->t('share')) ?></summary>
        <form method="post" class="nt-sh"><?= csrf_field() ?><input type="hidden" name="do" value="send"><input type="hidden" name="id" value="<?= (int)$n['id'] ?>"><input type="hidden" name="qs" value="<?= e($qs === '?' ? '' : $qs) ?>">
          <label class="fld"><span><?= e($sf->t('to_user')) ?></span><select name="user" required><option value=""><?= e($sf->t('pick_user')) ?></option><?php foreach ($users as $u): ?><option value="<?= (int)$u['id'] ?>"><?= e($u['name']) ?></option><?php endforeach; ?></select></label>
          <button class="btn sm primary"><?= e($sf->t('send_user')) ?></button></form>
        <form method="post" class="nt-sh"><?= csrf_field() ?><input type="hidden" name="do" value="email"><input type="hidden" name="id" value="<?= (int)$n['id'] ?>"><input type="hidden" name="qs" value="<?= e($qs === '?' ? '' : $qs) ?>">
          <label class="fld"><span><?= e($sf->t('to_email')) ?></span><input type="text" name="to" placeholder="name@example.com" required></label>
          <small class="muted"><?= e($sf->t('from_you', ['e' => $myEmail])) ?></small>
          <button class="btn sm primary"><?= e($sf->t('send_email')) ?></button></form></details>
      <form method="post" data-confirm="<?= e($sf->t('confirm')) ?>"><?= csrf_field() ?><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= (int)$n['id'] ?>"><input type="hidden" name="qs" value="<?= e($qs === '?' ? '' : $qs) ?>"><button class="btn sm danger"><?= e(t('common.delete')) ?></button></form></div>
  </article>
<?php endforeach; ?>
<?php if (!$notes): ?><div class="card empty"><p class="muted"><?= e($sf->t($q !== '' || $tag !== '' ? 'none_found' : 'none')) ?></p></div><?php endif; ?></div>
<style>
.nt-form,.nt-bar{display:grid;gap:12px}.nt-bar{grid-template-columns:1fr auto auto auto;align-items:center;margin-top:14px}@media(max-width:640px){.nt-bar{grid-template-columns:1fr}}
.nt-tags{display:flex;gap:6px;flex-wrap:wrap;margin:10px 0}.nt-tag{display:inline-block;padding:2px 10px;border:1px solid var(--line);border-radius:999px;font-size:13px;color:var(--ink);text-decoration:none;background:var(--card)}.nt-tag:hover,.nt-tag.on{border-color:var(--acc);color:var(--acc)}.nt-tag small{color:var(--mut)}
.nt-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px;margin-top:6px}.nt-note{margin:0;display:flex;flex-direction:column;gap:6px}.nt-note h3{margin:0;font-size:17px}.nt-note p{margin:0;word-break:break-word}
.nt-recv{border-left:3px solid var(--acc)}.nt-from{font-size:13px;color:var(--mut)}.nt-from b{color:var(--acc)}
.nt-act{margin-top:auto;padding-top:8px;gap:8px;flex-wrap:wrap;align-items:flex-start}.nt-share{position:relative}.nt-share summary{list-style:none;cursor:pointer}.nt-share summary::-webkit-details-marker{display:none}
.nt-share[open]{flex-basis:100%}.nt-sh{display:grid;gap:8px;margin:10px 0;padding:10px;border:1px solid var(--line);border-radius:10px;background:var(--bg)}
</style>
