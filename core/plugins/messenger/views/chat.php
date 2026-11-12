<?php /** @var \Sysfera\Plugin $sf @var array $peer @var array $msgs @var bool $blocked @var string $sid */
$last = $msgs ? (int)end($msgs)['id'] : 0;
$render = function (array $m) use ($sf): string {
    $h = '<div class="msg ' . ($m['mine'] ? 'mine' : 'theirs') . '" data-id="' . (int)$m['id'] . '">';
    if ($m['text'] !== '') $h .= '<p>' . nl2br(e($m['text'])) . '</p>';
    if ($m['file']) {
        $u = $sf->url('/file?id=' . $m['file']['id']);
        if ($m['file']['image']) $h .= '<a href="' . e($u) . '"><img class="msg-img" loading="lazy" src="' . e($u . '&inline=1') . '" alt="' . e($m['file']['name']) . '"></a>';
        else $h .= '<a class="msg-file" href="' . e($u) . '">📎 ' . e($m['file']['name']) . ' <small>' . e(human_size((int)$m['file']['size'])) . '</small></a>';
    }
    return $h . '<small class="muted">' . e($m['at']) . '</small></div>';
}; ?>
<link rel="stylesheet" href="<?= e($sf->asset('messenger.css')) ?>?v=<?= e($sf->manifest['version']) ?>">
<div class="page-h"><h1><?= e($peer['name']) ?> <small class="muted"><?= e($peer['sid']) ?></small></h1><a class="btn" href="<?= e($sf->url('/')) ?>">← <?= e($sf->t('title')) ?></a></div>
<section class="card"><div id="msgs" class="msgs" data-poll="<?= e($sf->url('/poll?with=' . rawurlencode($peer['sid']))) ?>" data-file="<?= e($sf->url('/file?id=')) ?>" data-last="<?= $last ?>">
  <?php foreach ($msgs as $m) echo $render($m); ?>
  <?php if (!$msgs): ?><p class="muted" id="msg-empty"><?= e($sf->t('chat_empty')) ?></p><?php endif; ?></div>
  <form method="post" action="<?= e($sf->url('/send')) ?>" enctype="multipart/form-data" class="msg-form"><?= csrf_field() ?><input type="hidden" name="with" value="<?= e($peer['sid']) ?>">
    <textarea name="text" rows="2" maxlength="2000" placeholder="<?= e($sf->t('type_ph')) ?>"></textarea>
    <div class="row"><input type="file" name="file" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.txt,.csv,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.odt,.ods,.zip"><small class="muted"><?= e($sf->t('file_hint')) ?></small><button class="btn primary" style="margin-left:auto"><?= e($sf->t('send')) ?></button></div></form></section>
<form method="post" action="<?= e($sf->url('/block')) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="sid" value="<?= e($peer['sid']) ?>"><input type="hidden" name="on" value="<?= $blocked ? '' : '1' ?>">
  <button class="btn sm<?= $blocked ? '' : ' danger' ?>"><?= e($sf->t($blocked ? 'unblock' : 'block')) ?></button></form>
<script src="<?= e($sf->asset('messenger.js')) ?>?v=<?= e($sf->manifest['version']) ?>"></script>
