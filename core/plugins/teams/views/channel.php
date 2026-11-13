<?php /** @var \Sysfera\Plugin $sf @var array $t @var array $c @var list<array> $msgs @var list<array> $pins @var int $lastRead @var list<array> $people @var int $me @var list<string> $emoji @var int $maxFile @var list<int> $chMembers */
use SysferaTeams\Tm;
$tab = 'chat'; require __DIR__ . '/_nav.php'; $base = $sf->url('/t/' . (int)$t['id'] . '/c/' . (int)$c['id']);
$cfg = ['poll' => $base . '/poll', 'send' => $base . '/send', 'msg' => $base . '/msg/', 'file' => $sf->url('/file/'), 'token' => csrf_token(), 'me' => $me, 'lastRead' => $lastRead, 'emoji' => $emoji,
    'maxFile' => $maxFile, 'canPost' => Tm::can($t['role'], 'post'), 'canReact' => Tm::can($t['role'], 'react'), 'canUpload' => Tm::can($t['role'], 'upload') && !\Sysfera\Demo::on(), 'canPin' => Tm::can($t['role'], 'moderate'),
    'people' => array_values(array_map(fn($u) => ['id' => (int)$u['id'], 'name' => $u['name']], $people)),
    'i18n' => array_combine($k = ['reply', 'edit', 'delete', 'pin', 'unpin', 'edited', 'deleted', 'attach', 'send', 'cancel', 'save', 'new_messages', 'confirm_delete', 'pinned', 'replying', 'too_big', 'sending_failed', 'download'], array_map(fn($x) => $sf->t($x), $k)),
    'msgs' => $msgs]; ?>
<main class="tm-main">
  <div class="tm-ch-h"><div><b><?= $c['is_private'] ? '🔒' : '#' ?> <?= e($c['name']) ?></b><?php if ($c['topic']): ?> <span class="muted sm">· <?= e($c['topic']) ?></span><?php endif; ?></div>
    <?php if (Tm::can($t['role'], 'channel')): ?><form method="post" action="<?= e($sf->url('/t/' . (int)$t['id'] . '/channel/' . (int)$c['id'] . '/archive')) ?>" data-confirm="<?= e($sf->t('confirm_archive')) ?>"><?= csrf_field() ?><button class="btn sm"><?= e($sf->t('archive_channel')) ?></button></form><?php endif; ?></div>
  <div class="tm-pins" id="tmpins" hidden></div>
  <div class="tm-msgs" id="tmmsgs" aria-live="polite"></div>
  <?php if (Tm::can($t['role'], 'post')): ?>
  <form class="tm-composer" id="tmform" autocomplete="off">
    <div class="tm-replybar" id="tmreply" hidden></div>
    <div class="tm-sug" id="tmsug" hidden></div>
    <textarea id="tmbody" rows="2" maxlength="<?= Tm::MAX_TEXT ?>" placeholder="<?= e($sf->t('write_ph', ['c' => $c['name']])) ?>"></textarea>
    <div class="tm-composer-f"><?php if (!\Sysfera\Demo::on() && Tm::can($t['role'], 'upload')): ?><label class="btn sm tm-attach">📎 <?= e($sf->t('attach')) ?><input type="file" id="tmfile" hidden></label><span class="muted sm" id="tmfname"></span><?php endif; ?>
      <span class="grow"></span><small class="muted" id="tmerr"></small><button class="btn primary sm" id="tmsend"><?= e($sf->t('send')) ?></button></div></form>
  <?php else: ?><p class="muted" style="text-align:center"><?= e($sf->t('read_only')) ?></p><?php endif; ?>
</main>
<script type="application/json" id="tmcfg"><?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="<?= e($sf->asset('teams.js')) ?>?v=<?= e($sf->manifest['version']) ?>"></script>
