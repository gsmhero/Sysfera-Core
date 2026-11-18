<?php /** @var \Sysfera\Plugin $sf  The contract page: the listing view (offers, private threads) before the deal, the work view (steps, chat, invoices, reviews) after it. Vars come from Svc::page. */
use SysferaContracts\Svc;
include __DIR__ . '/_nav.php';
$k = $contract; $cid = (int)$k['id']; $cur = Svc::symbol($k['currency']); $step = $k['payment_type'] === 'step'; $act = fn(string $a) => e($sf->url('/c/' . $cid . '/' . $a)); $cat = '';
foreach ($svc->categories() as $c) if ((int)$c['id'] === (int)$k['category_id']) $cat = $lang === 'ru' && $c['name_ru'] ? $c['name_ru'] : $c['name'];
$btn = fn(string $url, string $label, string $cls = '', string $confirm = '', array $hid = []) => '<form method="post" action="' . e($url) . '" class="inline"' . ($confirm !== '' ? ' data-confirm="' . e($confirm) . '"' : '') . '>' . csrf_field() . implode('', array_map(fn($n, $v) => '<input type="hidden" name="' . e($n) . '" value="' . e((string)$v) . '">', array_keys($hid), $hid)) . '<button class="btn sm ' . $cls . '">' . e($label) . '</button></form>';
?>
<div class="page-h row between"><h1><?= e($k['title']) ?></h1><span class="row"><?php if ($isOwner || $me->admin): ?><?php if (in_array($k['status'], ['draft', 'published'], true)): ?><a class="btn" href="<?= e($sf->url('/edit/' . $cid)) ?>"><?= e($sf->t('contract_edit')) ?></a><?php endif; ?><?php endif; ?>
  <?php if ($k['status'] === 'published' && $me->uid): ?><?= $btn($sf->url('/c/' . $cid . '/watch'), $sf->t($watched ? 'unwatch' : 'watch'), '', '', ['return' => 'view/' . $cid]) ?><?php endif; ?></span></div>
<div class="ct-meta"><span class="ct-st <?= $ctst($k['status']) ?>"><?= e($sf->t('st_' . $k['status'])) ?></span><?php if ($overdue): ?><span class="ct-st bad"><?= e($sf->t('overdue')) ?></span><?php endif; ?><?php if ($cat !== ''): ?><span class="ct-chip"><?= e($cat) ?></span><?php endif; ?><?php if ($k['location'] !== ''): ?><span><?= e($k['location']) ?></span><?php endif; ?>
  <span><?= e($sf->t('author')) ?>: <?= $ctuser((int)$author['id'], $author['name']) ?> <?= $ctstars((float)$svc->profile((int)$author['id'])['rating_avg'], (int)$svc->profile((int)$author['id'])['rating_count']) ?></span>
  <?php if ($contractor): ?><span><?= e($sf->t('contractor')) ?>: <?= $ctuser((int)$contractor['id'], $contractor['name']) ?></span><?php endif; ?>
  <?php $dead = $k['agreed_deadline'] ?: $k['deadline']; if ($dead): ?><span><?= e($sf->t('deadline')) ?>: <?= e($ctd($dead)) ?></span><?php endif; ?><span><?= (int)$k['views'] ?> <?= e($sf->t('views')) ?></span></div>
<p class="ct-desc"><?= e($k['description']) ?></p>
<p class="ct-price"><?= e($ctm($k['agreed_price'] ?? $totals['price'])) ?> <small><?= e($k['currency']) ?> · <?= (int)$totals['steps'] ?> <?= e($sf->t('steps')) ?> · <?= (int)($k['agreed_days'] ?? $totals['days']) ?> <?= e($sf->t('days_short')) ?><?= $working ? ' · ' . e($sf->t('pay_' . $k['payment_type'])) : '' ?></small></p>
<?php if ($k['prepay_required']): ?><p class="ct-hint"><?= e($sf->t($k['prepaid_at'] ? 'prepaid_yes' : 'prepay_needed')) ?><?php if ($isOwner && !$k['prepaid_at'] && in_array($k['status'], ['active', 'paused'], true)): ?> <?= $btn($sf->url('/c/' . $cid . '/prepay'), $sf->t('mark_prepaid'), 'primary', $sf->t('confirm_prepaid')) ?><?php endif; ?></p><?php endif; ?>
<?php if ($files): ?><p><?php foreach ($files as $f): ?><a class="ct-chip" href="<?= e($sf->url('/file/' . $f['id'])) ?>">📎 <?= e($f['original_name']) ?></a> <?php endforeach; ?></p><?php endif; ?>

<?php if ($working): /* ===================== work view ===================== */ ?>
<div class="row ct-noprint"><?php if ($isOwner && in_array($k['status'], ['active', 'paused'], true)): ?><?= $btn($sf->url('/c/' . $cid . '/pause'), $sf->t($k['status'] === 'paused' ? 'resume' : 'pause')) ?> <?= $btn($sf->url('/c/' . $cid . '/fire'), $sf->t('fire'), 'danger', $sf->t('confirm_fire')) ?><?php endif; ?>
  <?php if ($isOwner && in_array($k['status'], ['fulfilled', 'terminated'], true)): ?><?= $btn($sf->url('/c/' . $cid . '/repeat'), $sf->t('repeat'), 'primary', $sf->t('confirm_repeat')) ?><?php endif; ?>
  <?php if (($isOwner || $isContractor) && in_array($k['status'], ['active', 'paused'], true)): ?><form method="post" action="<?= $act('upload') ?>" enctype="multipart/form-data" class="inline"><?= csrf_field() ?><input type="file" name="upload[]" multiple required><button class="btn sm"><?= e($sf->t('upload')) ?></button></form><?php endif; ?>
  <?php if (!in_array($k['status'], ['active', 'paused'], true) && ($isOwner || $me->admin)): ?><?= $btn($sf->url('/c/' . $cid . '/delete'), $sf->t('delete'), 'danger', $sf->t('confirm_delete')) ?><?php endif; ?></div>

<div class="ct-two"><div>
<h2><?= e($sf->t('steps')) ?></h2>
<?php foreach ($steps as $i => $t): $tid = (int)$t['id']; $price = $t['agreed_price'] ?? $t['price']; $acc = $t['accepted_at'] !== null; $done = $t['done_at'] !== null; ?>
  <div class="ct-step <?= $acc ? 'acc' : ($done ? 'done' : '') ?>" id="todo-<?= $tid ?>"><div class="row between"><h4><?= $i + 1 ?>. <?= e($t['name']) ?></h4><span class="ct-st <?= $acc ? 'ok' : ($done ? 'info' : '') ?>"><?= e($sf->t($acc ? 'step_accepted' : ($done ? 'step_done' : 'step_todo'))) ?></span></div>
    <?php if ($t['description']): ?><p class="ct-desc"><?= e($t['description']) ?></p><?php endif; ?>
    <div class="ct-meta"><b><?= e($ctm($price)) ?> <?= e($k['currency']) ?></b><span><?= (int)($t['agreed_days'] ?? $t['days']) ?> <?= e($sf->t('days_short')) ?></span><?php if ($done): ?><span><?= e($sf->t('done_at')) ?> <?= e($ctdt($t['done_at'])) ?></span><?php endif; ?></div>
    <div class="row ct-noprint"><?php if ($isContractor && $k['status'] === 'active' && !$acc): ?><?= $btn($sf->url('/t/' . $tid . '/done'), $sf->t($done ? 'undo_done' : 'mark_done'), $done ? '' : 'primary') ?><?php endif; ?>
      <?php if ($isOwner && $k['status'] === 'active' && $done): ?><?= $btn($sf->url('/t/' . $tid . '/accept'), $sf->t($acc ? 'unaccept' : 'accept_step'), $acc ? '' : 'primary') ?><?php if (!$acc && $step): ?><?= $btn($sf->url('/t/' . $tid . '/accept'), $sf->t('accept_and_pay'), '', '', ['pay' => 'now']) ?><?php endif; ?><?php endif; ?></div>
    <?php if ($isOwner || $isContractor || $me->admin): ?><details <?= !empty($stepComments[$tid]) ? 'open' : '' ?>><summary class="muted"><?= e($sf->t('step_comments')) ?> (<?= count($stepComments[$tid] ?? []) ?>)</summary><?php $list = $stepComments[$tid] ?? []; $names = $userNames; $post = $sf->url('/t/' . $tid . '/comment'); $hidden = []; $anchor = 'todo-c-' . $tid; include __DIR__ . '/_comments.php'; ?></details><?php endif; ?></div>
<?php endforeach; ?>
</div><div>
<?php if ($invoices): ?><div class="card"><h3><?= e($sf->t('invoices_block')) ?></h3><div class="ct-wrap"><table class="ct-tbl"><tr><th><?= e($sf->t('number')) ?></th><th><?= e($sf->t('step')) ?></th><th class="r"><?= e($sf->t('amount')) ?></th><th><?= e($sf->t('status')) ?></th><th></th></tr>
  <?php foreach ($invoices as $i): ?><tr><td><a href="<?= e($sf->url('/invoice/' . $i['id'])) ?>"><?= e($i['number']) ?></a></td><td><?= e($i['todo_name'] ?? $sf->t('whole_contract')) ?></td><td class="r"><?= e($ctm($i['amount'])) ?> <?= e($i['currency']) ?></td><td><span class="ct-st <?= $ctst($i['status']) ?>"><?= e($sf->t('inv_' . $i['status'])) ?></span></td>
    <td><?php if ((int)$i['payer_id'] === $me->uid && $i['status'] === 'waiting'): ?><?= $btn($sf->url('/i/' . $i['id'] . '/pay'), $sf->t('i_paid'), 'primary', $sf->t('confirm_paid'), ['back' => 'view/' . $cid]) ?><?php elseif ((int)$i['payee_id'] === $me->uid && $i['status'] === 'pending'): ?><?= $btn($sf->url('/i/' . $i['id'] . '/confirm'), $sf->t('confirm_payment'), 'primary', '', ['back' => 'view/' . $cid]) ?><?php endif; ?></td></tr><?php endforeach; ?></table></div></div><?php endif; ?>
<?php if ($isOwner || $isContractor || $me->admin): ?><div class="card"><h3><?= e($sf->t('work_chat')) ?></h3><?php $list = $comments; $names = $userNames; $post = $sf->url('/c/' . $cid . '/comment'); $hidden = []; $anchor = 'comments'; include __DIR__ . '/_comments.php'; ?></div><?php endif; ?>
<?php if ($k['status'] === 'fulfilled' && ($isOwner || $isContractor) && !$myReview): $target = $isOwner ? (int)$k['contractor_id'] : (int)$k['user_id']; ?>
  <div class="card"><h3><?= e($sf->t('leave_review')) ?></h3><form method="post" action="<?= e($sf->url('/user/' . $target . '/review')) ?>" class="ct-form"><?= csrf_field() ?><input type="hidden" name="contract_id" value="<?= $cid ?>">
    <label class="ct-lbl"><?= e($sf->t('rating')) ?><select name="rating"><?php for ($r = 5; $r >= 1; $r--): ?><option value="<?= $r ?>"><?= str_repeat('★', $r) ?></option><?php endfor; ?></select></label><textarea name="body" rows="2" placeholder="<?= e($sf->t('review_ph')) ?>"></textarea><button class="btn primary"><?= e($sf->t('send')) ?></button></form></div><?php endif; ?>
<?php foreach ($reviews as $r): ?><div class="ct-msg"><small><b><?= e(str_repeat('★', (int)$r['rating'])) ?></b> · <?= e($svc->userName((int)$r['reviewer_id'])) ?></small><p><?= e((string)$r['body']) ?></p></div><?php endforeach; ?>
</div></div>

<?php else: /* ===================== listing view (published / draft) ===================== */ ?>
<div class="ct-two"><div>
<h2><?= e($sf->t('steps')) ?></h2>
<?php foreach ($steps as $i => $t): ?><div class="ct-step"><div class="row between"><h4><?= $i + 1 ?>. <?= e($t['name']) ?></h4><b><?= e($ctm($t['price'])) ?> <?= e($k['currency']) ?> · <?= (int)$t['days'] ?> <?= e($sf->t('days_short')) ?></b></div><?php if ($t['description']): ?><p class="ct-desc"><?= e($t['description']) ?></p><?php endif; ?></div><?php endforeach; ?>

<?php if ($authorSide): ?><h2><?= e($sf->t('offers')) ?> (<?= count($offers) ?>)</h2>
  <?php if (!$offers): ?><p class="muted"><?= e($sf->t('no_offers')) ?></p><?php endif; ?>
  <?php foreach ($offers as $o): ?><div class="card ct-card"><div class="row between"><h3><a href="<?= e($sf->url('/offer/' . $o['id'])) ?>"><?= e($o['name']) ?></a> <?= $o['seen_at'] === null ? '<span class="ct-n">' . e($sf->t('new')) . '</span>' : '' ?></h3><span class="ct-price"><?= e($ctm($o['price'])) ?> <small><?= e($k['currency']) ?> · <?= (int)$o['days'] ?> <?= e($sf->t('days_short')) ?></small></span></div>
    <div class="ct-meta"><?= $ctstars((float)$o['rating_avg'], (int)$o['rating_count']) ?><span><?= e($sf->t('pay_' . $o['payment_type'])) ?></span><span><?= e($sf->t('as_' . $o['offerer_type'])) ?></span><span><?= e($ctdt($o['created_at'])) ?></span></div>
    <?php if ($isOwner && $k['status'] === 'published'): ?><div class="row"><?= $btn($sf->url('/o/' . $o['id'] . '/accept'), $sf->t('accept_offer'), 'primary', $sf->t('confirm_accept')) ?><?= $btn($sf->url('/o/' . $o['id'] . '/reject'), $sf->t('reject_offer'), 'danger', $sf->t('confirm_reject')) ?></div><?php endif; ?></div><?php endforeach; ?>
<?php endif; ?>
</div><div>
<?php if ($canOffer): $rows = Svc::offerRows($steps, $draft); $action = $sf->url('/c/' . $cid . '/offer'); $asIs = true; $pre = ['notes' => $draft['notes'] ?? '', 'payment_type' => $draft['payment_type'] ?? 'finish', 'offerer_type' => $draft['offerer_type'] ?? ''];
  include __DIR__ . '/_offerform.php'; ?>
<?php elseif ($myOffer): ?><div class="card"><p><?= e($sf->t('your_offer_sent')) ?> <a class="btn sm" href="<?= e($sf->url('/offer/' . $myOffer['id'])) ?>"><?= e($sf->t('open')) ?></a></p></div>
<?php elseif (!$me->uid): ?><div class="card"><p><?= e($sf->t('login_to_offer')) ?></p></div><?php endif; ?>

<?php if ($k['status'] === 'published' && ($authorSide ? $threads : $me->uid)): ?><div class="card"><h3><?= e($sf->t('private_chat')) ?></h3><p class="muted"><?= e($sf->t($authorSide ? 'threads_help_author' : 'threads_help')) ?></p>
  <?php if ($authorSide): ?><p><?php foreach ($threads as $t): ?><a class="ct-chip <?= (int)$t['uid'] === (int)$thread ? 'on' : '' ?>" href="<?= e($sf->url('/view/' . $cid . '?thread=' . $t['uid'])) ?>"><?= e($t['name']) ?></a> <?php endforeach; ?></p><?php endif; ?>
  <?php if ($thread || !$authorSide): $list = $comments; $names = $userNames + $svc->userInfo(array_filter([$thread])); $post = $sf->url('/c/' . $cid . '/comment'); $hidden = $authorSide ? ['thread' => $thread] : []; $anchor = 'comments'; include __DIR__ . '/_comments.php'; endif; ?></div><?php endif; ?>
</div></div>
<?php endif; ?>
