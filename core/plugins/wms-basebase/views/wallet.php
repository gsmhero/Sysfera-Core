<?php /** @var \Sysfera\Plugin $sf @var string $tab @var array $w @var int $shop */
include __DIR__ . '/_nav.php';
$fmt = fn($n) => number_format((float)$n, 2, '.', ' ');
$d = $w['data'] ?? []; $err = $w['error'] ?? null; ?>
<div class="page-h"><h1><?= e($sf->t('nav_wallet')) ?></h1></div>
<?php if (!$shop): ?><div class="hint warn"><?= e($sf->t('no_shop_yet')) ?> <a href="<?= e($sf->url('/shop')) ?>"><?= e($sf->t('nav_shop')) ?></a></div>
<?php elseif ($err): ?><div class="hint warn"><?= e($sf->t('wd_fail', ['e' => preg_match('/^[a-z_]+$/', (string)$err) ? $sf->t((string)$err) : $err])) ?></div>
<?php else: ?>
<div class="cols">
  <section class="card"><h3><?= e($sf->t('wd_balance')) ?></h3><div class="bigno"><?= e($fmt($d['balance'] ?? 0)) ?> ₽</div><p class="muted sm"><?= e($sf->t('wd_hint')) ?></p></section>
  <form class="card" method="post"><?= csrf_field() ?><h3><?= e($sf->t('wd_h')) ?></h3>
    <label class="fld"><span><?= e($sf->t('wd_amount')) ?></span><input name="amount" inputmode="decimal" required placeholder="100"></label>
    <button class="btn primary" data-confirm="<?= e($sf->t('wd_confirm')) ?>"><?= e($sf->t('wd_btn')) ?></button>
    <p class="muted sm"><?= e($sf->t('wd_profile_note')) ?> <a href="<?= e($sf->url('/profile')) ?>"><?= e($sf->t('nav_profile')) ?></a></p></form>
</div>
<section class="card"><h3><?= e($sf->t('wd_history')) ?></h3>
<?php $tx = (array)($d['transactions'] ?? []); if (!$tx): ?><p class="muted"><?= e($sf->t('wd_none')) ?></p><?php else: ?>
<div class="tblwrap"><table class="tbl"><thead><tr><th><?= e($sf->t('wd_date')) ?></th><th><?= e($sf->t('wd_type')) ?></th><th class="num"><?= e($sf->t('wd_sum')) ?></th><th><?= e($sf->t('wd_status')) ?></th><th><?= e($sf->t('wd_note')) ?></th></tr></thead><tbody>
<?php foreach ($tx as $t): ?><tr><td class="nowrap"><?= e(substr(str_replace('T', ' ', (string)($t['created_at'] ?? '')), 0, 16)) ?></td><td><?= e((string)($t['type'] ?? '')) ?></td><td class="num <?= ((float)($t['amount'] ?? 0)) < 0 ? 'bad-t' : '' ?>"><?= e($fmt($t['amount'] ?? 0)) ?></td><td><?= e((string)($t['status'] ?? '')) ?></td><td><?= e((string)($t['note'] ?? '')) ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?></section>
<?php endif; ?>