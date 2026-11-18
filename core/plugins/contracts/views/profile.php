<?php /** @var \Sysfera\Plugin $sf @var array $profile @var array $types (my statuses) @var array $structure (all sections with fields) @var array $values */
use SysferaContracts\Svc;
include __DIR__ . '/_nav.php'; ?>
<div class="page-h row between"><h1><?= e($sf->t('nav_profile')) ?></h1><a class="btn" href="<?= e($sf->url('/user/' . $me->uid)) ?>"><?= e($sf->t('public_page')) ?></a></div>
<form method="post" action="<?= e($sf->url('/profile')) ?>" class="ct-form card"><?= csrf_field() ?>
  <h3><?= e($sf->t('my_status')) ?></h3><p class="muted"><?= e($sf->t('my_status_help')) ?></p>
  <p><?php foreach (Svc::PARTY_TYPES as $t): ?><label><input type="checkbox" name="types[]" value="<?= e($t) ?>" <?= in_array($t, $types, true) ? 'checked' : '' ?>> <?= e($sf->t('pt_' . $t)) ?></label> &nbsp; <?php endforeach; ?></p>
  <label class="ct-lbl"><?= e($sf->t('about')) ?><textarea name="about" rows="3" maxlength="4000"><?= e((string)$profile['about']) ?></textarea></label>
  <label><input type="checkbox" name="notify_email" value="1" <?= $profile['notify_email'] ? 'checked' : '' ?>> <?= e($sf->t('notify_email')) ?></label>
  <?php foreach ($structure as $sec): if (!in_array($sec['type'], $types, true) || !$sec['fields']) continue; ?><h3><?= e($sec['title']) ?> <small class="ct-chip"><?= e($sf->t('pt_' . $sec['type'])) ?></small></h3>
    <?php foreach ($sec['fields'] as $f): $val = $values[(int)$f['id']] ?? null; include __DIR__ . '/_field.php'; endforeach; ?><?php endforeach; ?>
  <p class="muted"><?= e($sf->t('fields_by_status')) ?></p>
  <button class="btn primary"><?= e($sf->t('save')) ?></button></form>
