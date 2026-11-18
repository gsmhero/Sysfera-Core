<?php /** The discussion under a task. @var \Sysfera\Plugin $sf @var array $task @var array $comments (rows with user_name and files) @var string $listBack @var array $prof */
$comments = (array)($comments ?? []); $meId = (int)$sf->auth()->id(); $imgExt = \SysferaCrm\Discuss::IMAGES; $tid = (int)$task['id']; ?>
<section class="card crm-disc" id="discuss">
  <h3><?= e($sf->t('discuss')) ?> <?php if ($comments): ?><small class="muted">(<?= count($comments) ?>)</small><?php endif; ?></h3>
  <?php if (!$comments): ?><p class="muted"><?= e($sf->t('discuss_empty')) ?></p><?php endif; ?>
  <?php foreach ($comments as $c): $mine = (int)$c['user_id'] === $meId; ?>
    <div class="crm-cm<?= $mine ? ' mine' : '' ?>" id="c<?= (int)$c['id'] ?>">
      <div class="crm-cm-h"><b><?= e((string)($c['user_name'] ?? '') !== '' ? $c['user_name'] : '—') ?></b><span class="muted"><?= e(crm_dt($c['created_at'])) ?></span>
        <?php if ($mine || !empty($prof['delete'])): ?><form method="post" action="<?= e($sf->url('/task/' . $tid . '/comment/' . (int)$c['id'] . '/delete')) ?>" class="inline" onsubmit="return confirm(<?= e(json_encode($sf->t('comment_confirm'), JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>)"><?= csrf_field() ?><input type="hidden" name="back" value="<?= e($listBack) ?>"><button class="crm-cm-x" title="<?= e($sf->t('delete')) ?>" aria-label="<?= e($sf->t('delete')) ?>">×</button></form><?php endif; ?></div>
      <?php if ((string)$c['body'] !== ''): ?><div class="crm-cm-b"><?= nl2br(e((string)$c['body'])) ?></div><?php endif; ?>
      <?php if (!empty($c['files'])): ?><div class="crm-cm-f"><?php foreach ($c['files'] as $f): $u = $sf->url('/task/' . $tid . '/file/' . (int)$f['id']); $isImg = isset($imgExt[strtolower(pathinfo((string)$f['stored_name'], PATHINFO_EXTENSION))]); ?>
        <?php if ($isImg): ?><a href="<?= e($u) ?>" target="_blank" rel="noopener" class="crm-cm-img"><img src="<?= e($u) ?>" alt="<?= e($f['original_name']) ?>" loading="lazy"></a>
        <?php else: ?><a class="crm-cm-file" href="<?= e($u) ?>"><?= e($f['original_name']) ?> <small class="muted"><?= e(human_size((int)$f['size'])) ?></small></a><?php endif; ?>
      <?php endforeach; ?></div><?php endif; ?>
    </div>
  <?php endforeach; ?>
  <form method="post" action="<?= e($sf->url('/task/' . $tid . '/comment')) ?>" enctype="multipart/form-data" class="crm-cm-form"><?= csrf_field() ?><input type="hidden" name="back" value="<?= e($listBack) ?>">
    <textarea name="body" rows="3" maxlength="<?= (int)\SysferaCrm\Discuss::MAX_BODY ?>" placeholder="<?= e($sf->t('comment_ph')) ?>"></textarea>
    <div class="row between"><label class="crm-cm-pick"><input type="file" name="files[]" multiple accept=".<?= e(implode(',.', \SysferaCrm\Discuss::EXT)) ?>"><small class="muted"><?= e($sf->t('file_hint', ['n' => \SysferaCrm\Discuss::MAX_FILES, 'mb' => (int)(\SysferaCrm\Discuss::MAX_BYTES / 1_000_000)])) ?></small></label>
      <button class="btn primary"><?= e($sf->t('comment_send')) ?></button></div>
  </form>
</section>