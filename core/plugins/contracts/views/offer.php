<?php /** @var \Sysfera\Plugin $sf  An offer: its steps, the private discussion, accept / reject (author) or edit / withdraw (offerer). Vars from Svc::offerPage. */
use SysferaContracts\Svc;
include __DIR__ . '/_nav.php';
$k = $contract; $oid = (int)$offer['id']; $cid = (int)$k['id']; $pending = $offer['status'] === 'pending';
$btn = fn(string $url, string $label, string $cls = '', string $confirm = '') => '<form method="post" action="' . e($url) . '" class="inline"' . ($confirm !== '' ? ' data-confirm="' . e($confirm) . '"' : '') . '>' . csrf_field() . '<button class="btn sm ' . $cls . '">' . e($label) . '</button></form>';
$mine = (int)$offer['user_id'] === $me->uid; ?>
<div class="page-h row between"><h1><?= e($sf->t('offer')) ?>: <a href="<?= e($sf->url('/view/' . $cid)) ?>"><?= e($k['title']) ?></a></h1><span class="ct-st <?= $pending ? 'warn' : 'ok' ?>"><?= e($sf->t($pending ? 'offer_pending' : 'offer_accepted')) ?></span></div>
<div class="ct-meta"><span><?= e($sf->t('from')) ?>: <?= $ctuser((int)$offerer['id'], $offerer['name']) ?> <?= $ctstars((float)$svc->profile((int)$offerer['id'])['rating_avg'], (int)$svc->profile((int)$offerer['id'])['rating_count']) ?></span><span><?= e($sf->t('pay_' . $offer['payment_type'])) ?></span><span><?= e($sf->t('as_' . $offer['offerer_type'])) ?></span><span><?= e($ctdt($offer['created_at'])) ?></span></div>
<p class="ct-price"><?= e($ctm($offer['price'])) ?> <small><?= e($k['currency']) ?> · <?= (int)$offer['days'] ?> <?= e($sf->t('days_short')) ?></small></p>
<?php if ($offer['notes']): ?><p class="ct-desc"><?= e($offer['notes']) ?></p><?php endif; ?>
<div class="ct-two"><div>
<?php if ($edit && $mine && $pending && $k['status'] === 'published'): $rows = Svc::offerRows($svc->steps($cid), $draft) ; if (!$draft) $rows = $items; $action = $sf->url('/o/' . $oid . '/edit'); $asIs = false; $pre = $draft ? ['notes' => $draft['notes'] ?? '', 'payment_type' => $draft['payment_type'] ?? 'finish', 'offerer_type' => $draft['offerer_type'] ?? ''] : ['notes' => $offer['notes'], 'payment_type' => $offer['payment_type'], 'offerer_type' => $offer['offerer_type']]; include __DIR__ . '/_offerform.php'; ?>
<?php else: ?>
  <?php foreach ($items as $i => $it): ?><div class="ct-step <?= $it['excluded'] ? 'out' : '' ?>"><div class="row between"><h4><?= $i + 1 ?>. <?= e($it['name']) ?><?= $it['type'] === 'extra' ? ' <span class="ct-chip">' . e($sf->t('own_step')) . '</span>' : '' ?><?= $it['excluded'] ? ' <span class="ct-chip">' . e($sf->t('unnecessary')) . '</span>' : '' ?></h4>
    <b><?= e($ctm($it['o_price'])) ?> <?= e($k['currency']) ?> · <?= (int)$it['o_days'] ?> <?= e($sf->t('days_short')) ?></b></div><?php if ($it['description']): ?><p class="ct-desc"><?= e($it['description']) ?></p><?php endif; ?>
    <?php if ($it['price'] !== null && ((float)$it['price'] !== (float)$it['o_price'] || (int)$it['days'] !== (int)$it['o_days'])): ?><small class="muted"><?= e($sf->t('author_wanted')) ?>: <?= e($ctm($it['price'])) ?> / <?= (int)$it['days'] ?></small><?php endif; ?></div><?php endforeach; ?>
<?php endif; ?>
</div><div>
<div class="card row"><?php if ($isOwner && $pending && $k['status'] === 'published'): ?><?= $btn($sf->url('/o/' . $oid . '/accept'), $sf->t('accept_offer'), 'primary', $sf->t('confirm_accept')) ?><?= $btn($sf->url('/o/' . $oid . '/reject'), $sf->t('reject_offer'), 'danger', $sf->t('confirm_reject')) ?><?php endif; ?>
  <?php if ($mine && $pending && $k['status'] === 'published'): ?><a class="btn sm" href="<?= e($sf->url('/offer/' . $oid . '?edit=1')) ?>"><?= e($sf->t('edit_offer')) ?></a><?= $btn($sf->url('/o/' . $oid . '/delete'), $sf->t('withdraw_offer'), 'danger', $sf->t('confirm_withdraw')) ?><?php endif; ?>
  <?php if (!$pending): ?><a class="btn sm primary" href="<?= e($sf->url('/view/' . $cid)) ?>"><?= e($sf->t('open_contract')) ?></a><?php endif; ?></div>
<div class="card"><h3><?= e($sf->t('offer_chat')) ?></h3><?php $list = $comments; $names = $svc->userInfo(array_merge(array_column($comments, 'user_id'), [$me->uid])); $post = $sf->url('/o/' . $oid . '/comment'); $hidden = []; $anchor = 'comments'; include __DIR__ . '/_comments.php'; ?></div>
</div></div>
