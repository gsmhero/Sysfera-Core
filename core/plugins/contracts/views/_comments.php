<?php /** Comment list + form. Vars: $list, $names (id => user), $post (form action), $hidden (extra hidden fields name => value), $cid (anchor id) */ ?>
<div id="<?= e($anchor ?? 'comments') ?>">
<?php foreach ($list as $m): $who = $names[(int)$m['user_id']]['name'] ?? ('#' . $m['user_id']); ?>
  <div class="ct-msg <?= (int)$m['user_id'] === $me->uid ? 'mine' : '' ?>"><small><b><?= e($who) ?></b> · <?= e($ctdt($m['created_at'])) ?></small><p><?= e($m['body']) ?></p></div>
<?php endforeach; ?>
<?php if (!$list): ?><p class="muted"><?= e($sf->t('no_comments')) ?></p><?php endif; ?>
<form method="post" action="<?= e($post) ?>" class="ct-form"><?= csrf_field() ?><?php foreach (($hidden ?? []) as $n => $val): ?><input type="hidden" name="<?= e($n) ?>" value="<?= e((string)$val) ?>"><?php endforeach; ?>
  <textarea name="body" rows="2" maxlength="4000" required placeholder="<?= e($sf->t('write_comment')) ?>"></textarea><button class="btn sm primary"><?= e($sf->t('send')) ?></button></form>
</div>
