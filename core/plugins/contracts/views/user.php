<?php /** @var \Sysfera\Plugin $sf @var array $user @var array $profile @var array $reviews @var array $names @var array $public @var int $uid @var array $canReview ([[contract id, title]]) */
include __DIR__ . '/_nav.php'; ?>
<div class="page-h"><h1><?= e($user['name']) ?></h1></div>
<div class="card"><p><?= $ctstars((float)$profile['rating_avg'], (int)$profile['rating_count']) ?> · <?= e(implode(', ', array_map(fn($t) => $sf->t('pt_' . $t), explode(',', (string)$profile['user_types'])))) ?></p>
  <?php if (trim((string)$profile['about']) !== ''): ?><p class="ct-desc"><?= e($profile['about']) ?></p><?php endif; ?>
  <?php if ($public): ?><table class="ct-tbl"><?php foreach ($public as $p): ?><tr><th><?= e($p['label']) ?></th><td><?= e($p['value']) ?></td></tr><?php endforeach; ?></table><?php endif; ?>
  <?php if ($me->uid === $uid): ?><p><a class="btn" href="<?= e($sf->url('/profile')) ?>"><?= e($sf->t('nav_profile')) ?></a></p><?php endif; ?></div>
<?php if ($canReview): ?><div class="card"><h3><?= e($sf->t('leave_review')) ?></h3><form method="post" action="<?= e($sf->url('/user/' . $uid . '/review')) ?>" class="ct-form"><?= csrf_field() ?>
  <div class="row"><label class="ct-lbl"><?= e($sf->t('contract')) ?><select name="contract_id"><?php foreach ($canReview as [$id, $title]): ?><option value="<?= (int)$id ?>"><?= e($title) ?></option><?php endforeach; ?></select></label>
  <label class="ct-lbl"><?= e($sf->t('rating')) ?><select name="rating"><?php for ($r = 5; $r >= 1; $r--): ?><option value="<?= $r ?>"><?= str_repeat('★', $r) ?></option><?php endfor; ?></select></label></div>
  <textarea name="body" rows="2" placeholder="<?= e($sf->t('review_ph')) ?>"></textarea><button class="btn primary"><?= e($sf->t('send')) ?></button></form></div><?php endif; ?>
<h2><?= e($sf->t('reviews')) ?> (<?= count($reviews) ?>)</h2>
<?php if (!$reviews): ?><p class="muted"><?= e($sf->t('no_reviews')) ?></p><?php endif; ?>
<?php foreach ($reviews as $r): ?><div class="ct-msg"><small><span class="ct-stars"><?= e(str_repeat('★', (int)$r['rating'])) ?></span> · <?= e($names[(int)$r['reviewer_id']]['name'] ?? '') ?> · <a href="<?= e($sf->url('/view/' . $r['contract_id'])) ?>"><?= e($sf->t('contract')) ?> #<?= (int)$r['contract_id'] ?></a> · <?= e($ctd($r['created_at'])) ?></small><?php if ((string)$r['body'] !== ''): ?><p><?= e($r['body']) ?></p><?php endif; ?></div><?php endforeach; ?>
