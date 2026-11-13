<?php /** @var \Sysfera\Plugin $sf @var array $sum @var float $wonMonth @var list<array> $tasks @var list<array> $feed @var list<array> $funnel @var list<array> $recent @var int $nowTs */
$tab = 'dashboard'; require __DIR__ . '/_nav.php'; $cur = $sf->setting('currency', 'RUB') ?: 'RUB'; $backTo = '/p/crm/'; $showLinks = true; $prof = ['delete' => false]; ?>
<div class="page-h"><h1><?= e($sf->t('menu')) ?></h1></div>
<div class="crm-kpi"><div><small><?= e($sf->t('k_open')) ?></small><b><?= (int)$sum['open_n'] ?></b><span><?= e(crm_money($sum['open_sum'], $cur)) ?></span></div>
  <div><small><?= e($sf->t('k_forecast')) ?></small><b><?= e(crm_money($sum['weighted'], $cur)) ?></b></div>
  <div><small><?= e($sf->t('k_won_month')) ?></small><b><?= e(crm_money($wonMonth, $cur)) ?></b></div>
  <div><small><?= e($sf->t('k_winrate')) ?></small><b><?= $sum['win_rate'] === null ? '—' : e((string)$sum['win_rate']) . '%' ?></b></div></div>
<div class="crm-two"><div>
  <section class="card"><h3><?= e($sf->t('my_tasks')) ?></h3><?php $rows = $tasks; require __DIR__ . '/_tasklist.php'; ?><a href="<?= e($sf->url('/tasks')) ?>"><?= e($sf->t('all_tasks')) ?> →</a></section>
  <section class="card"><h3><?= e($sf->t('recent_activity')) ?></h3>
    <?php foreach ($feed as $r): ?><div class="crm-tl-i <?= e($r['kind']) ?>"><i></i><div><small class="muted"><?= e($r['user_name'] ?: $sf->t('system')) ?> · <?= e(crm_dt($r['created_at'])) ?><?php if ($r['deal_title']): ?> · <a href="<?= e($sf->url('/deal/' . (int)$r['deal_id'])) ?>"><?= e($r['deal_title']) ?></a><?php endif; ?></small><div><?= e(mb_strimwidth((string)$r['body'], 0, 140, '…')) ?></div></div></div><?php endforeach; ?>
    <?php if (!$feed): ?><p class="muted"><?= e($sf->t('no_history')) ?></p><?php endif; ?></section>
</div><div>
  <?php foreach ($funnel as $fu): $max = max(1, ...array_map(fn($r) => $r['n'], $fu['rows']) ?: [1]); ?>
  <section class="card"><h3><a href="<?= e($sf->url('/deals?pipeline=' . (int)$fu['p']['id'])) ?>"><?= e($fu['p']['name']) ?></a></h3>
    <?php foreach ($fu['rows'] as $r): ?><div class="crm-bar"><span><?= e($r['name']) ?></span><i style="--w:<?= (int)round($r['n'] * 100 / $max) ?>%;--c:<?= e($r['color']) ?>"></i><b><?= (int)$r['n'] ?></b><small class="muted"><?= e(crm_money($r['sum'])) ?></small></div><?php endforeach; ?></section><?php endforeach; ?>
  <section class="card"><h3><?= e($sf->t('recent_deals')) ?></h3>
    <?php foreach ($recent as $d): ?><a class="crm-mini" href="<?= e($sf->url('/deal/' . (int)$d['id'])) ?>"><span><b><?= e($d['title']) ?></b><small class="muted"><span class="crm-dot" style="--c:<?= e($d['stage_color']) ?>"></span><?= e($d['stage_name']) ?></small></span><span><?= e(crm_money($d['amount'], $d['currency'])) ?></span></a><?php endforeach; ?>
    <?php if (!$recent): ?><p class="muted"><?= e($sf->t('none')) ?></p><?php endif; ?></section>
</div></div>
