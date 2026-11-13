<?php /** @var \Sysfera\Plugin $sf @var list<array> $pl @var int $pid @var string $from @var string $to @var array $sum @var list<array> $perStage @var array $byOwner @var array $reasons @var array $byMonth @var array $closedSum */
$tab = 'reports'; require __DIR__ . '/_nav.php'; $cur = $sf->setting('currency', 'RUB') ?: 'RUB'; $maxM = max(1.0, ...array_values($byMonth ?: [1.0])); $maxS = max(1, ...array_map(fn($r) => $r['n'], $perStage) ?: [1]); ?>
<div class="page-h"><h1><?= e($sf->t('reports')) ?></h1></div>
<form method="get" class="card crm-filter"><select name="pipeline" onchange="this.form.submit()"><?php foreach ($pl as $p): ?><option value="<?= (int)$p['id'] ?>"<?= (int)$p['id'] === $pid ? ' selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?></select>
  <label><?= e($sf->t('from')) ?> <input type="date" name="from" value="<?= e($from) ?>"></label><label><?= e($sf->t('to')) ?> <input type="date" name="to" value="<?= e($to) ?>"></label><button class="btn"><?= e($sf->t('apply')) ?></button></form>
<div class="crm-kpi"><div><small><?= e($sf->t('k_open')) ?></small><b><?= (int)$sum['open_n'] ?></b><span><?= e(crm_money($sum['open_sum'], $cur)) ?></span></div>
  <div><small><?= e($sf->t('k_forecast')) ?></small><b><?= e(crm_money($sum['weighted'], $cur)) ?></b></div>
  <div><small><?= e($sf->t('k_won_period')) ?></small><b><?= (int)$closedSum['won_n'] ?></b><span><?= e(crm_money($closedSum['won_sum'], $cur)) ?></span></div>
  <div><small><?= e($sf->t('k_winrate')) ?></small><b><?= $closedSum['win_rate'] === null ? '—' : e((string)$closedSum['win_rate']) . '%' ?></b><span><?= (int)$closedSum['lost_n'] ?> <?= e($sf->t('s_lost')) ?></span></div></div>
<div class="crm-two"><div>
  <section class="card"><h3><?= e($sf->t('r_funnel')) ?></h3><?php foreach ($perStage as $r): ?><div class="crm-bar"><span><?= e($r['name']) ?></span><i style="--w:<?= (int)round($r['n'] * 100 / $maxS) ?>%;--c:<?= e($r['color']) ?>"></i><b><?= (int)$r['n'] ?></b><small class="muted"><?= e(crm_money($r['sum'])) ?></small></div><?php endforeach; ?></section>
  <section class="card"><h3><?= e($sf->t('r_months')) ?></h3><?php foreach ($byMonth as $m => $v): ?><div class="crm-bar"><span><?= e($m) ?></span><i style="--w:<?= (int)round($v * 100 / $maxM) ?>%;--c:#16a34a"></i><b></b><small class="muted"><?= e(crm_money($v, $cur)) ?></small></div><?php endforeach; ?>
    <?php if (!$byMonth): ?><p class="muted"><?= e($sf->t('none')) ?></p><?php endif; ?></section>
</div><div>
  <section class="card"><h3><?= e($sf->t('r_owners')) ?></h3><table class="tbl"><thead><tr><th><?= e($sf->t('f_owner')) ?></th><th class="num"><?= e($sf->t('s_won')) ?></th><th class="num"><?= e($sf->t('f_amount')) ?></th><th class="num"><?= e($sf->t('s_lost')) ?></th></tr></thead><tbody>
    <?php foreach ($byOwner as $o => $r): ?><tr><td><?= e($o) ?></td><td class="num"><?= (int)$r['won_n'] ?></td><td class="num"><?= e(crm_money($r['won_sum'])) ?></td><td class="num"><?= (int)$r['lost_n'] ?></td></tr><?php endforeach; ?>
    <?php if (!$byOwner): ?><tr><td colspan="4" class="muted"><?= e($sf->t('none')) ?></td></tr><?php endif; ?></tbody></table></section>
  <section class="card"><h3><?= e($sf->t('r_reasons')) ?></h3><?php foreach ($reasons as $r => $n): ?><div class="crm-bar"><span><?= e($r) ?></span><i style="--w:<?= (int)round($n * 100 / max($reasons)) ?>%;--c:#dc2626"></i><b><?= (int)$n ?></b></div><?php endforeach; ?>
    <?php if (!$reasons): ?><p class="muted"><?= e($sf->t('none')) ?></p><?php endif; ?></section>
</div></div>
