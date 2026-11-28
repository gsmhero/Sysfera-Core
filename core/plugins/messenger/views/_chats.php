<?php /** The list of conversations; the page asks for it again to stay up to date. @var \Sysfera\Plugin $sf @var array $chats */ ?>
<?php if (!$chats): ?><div class="empty" style="padding:22px"><h3><?= e($sf->t('no_chats')) ?></h3><p class="muted"><?= e($sf->t('no_chats_hint')) ?></p></div><?php endif; ?>
<?php foreach ($chats as $c): ?><a class="msg-row" href="<?= e($sf->url('/chat?with=' . rawurlencode($c['sid']))) ?>">
  <span class="msg-av"><?= e(mb_strtoupper(mb_substr($c['name'], 0, 1))) ?></span>
  <span class="msg-mid"><b><?= e($c['name']) ?></b><small class="muted"><?= $c['mine'] ? e($sf->t('you')) . ': ' : '' ?><?= e($c['last']) ?></small></span>
  <span class="msg-end"><small class="muted"><?= e(ago($c['at'])) ?></small><?php if ($c['unread'] > 0): ?><span class="badge info"><?= (int)$c['unread'] ?></span><?php endif; ?></span></a><?php endforeach; ?>
