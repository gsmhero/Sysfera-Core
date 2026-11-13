<?php /** @var \Sysfera\Plugin $sf @var array $pipelines @var int $pid @var array $stages @var array $deals @var array $board @var array $f @var string $view @var array $users @var array $prof @var array $sum @var array $defs @var string $cur @var string $sortBy */
require __DIR__ . '/_nav.php';
$q = fn(array $o) => $sf->url('/deals?' . http_build_query(array_filter(array_merge(['pipeline' => $pid, 'view' => $view, 'q' => $f['q'], 'owner' => $f['owner'], 'tag' => $f['tag'], 'status' => $f['status']], $o), fn($v) => $v !== '' && $v !== null)));
if ($view === 'list' && in_array($sortBy, ['amount', 'title', 'close'], true)) usort($deals, fn($a, $b) => $sortBy === 'amount' ? (float)$b['amount'] <=> (float)$a['amount'] : ($sortBy === 'title' ? strcasecmp($a['title'], $b['title']) : strcmp((string)($a['expected_close'] ?: '9999'), (string)($b['expected_close'] ?: '9999'))));
$nowTs = time(); ?>
<div class="page-h"><h1><?= e($sf->t('deals')) ?></h1>
  <span class="row"><?php if (!empty($prof['export'])): ?><a class="btn" href="<?= e($sf->url('/export/deals')) ?>">CSV</a><?php endif; ?>
  <?php if (!empty($prof['create'])): ?><button class="btn primary" type="button" data-toggle="#newdeal">+ <?= e($sf->t('new_deal')) ?></button><?php endif; ?></span></div>

<div class="crm-pipes"><?php foreach ($pipelines as $p): ?><a href="<?= e($sf->url('/deals?pipeline=' . (int)$p['id'] . '&view=' . $view)) ?>" class="<?= (int)$p['id'] === $pid ? 'on' : '' ?>" style="--c:<?= e($p['color']) ?>"><?= e($p['name']) ?></a><?php endforeach; ?></div>

<?php if (!empty($prof['create'])): ?><div class="card" id="newdeal" hidden><?php $d = null; $contact = null; require __DIR__ . '/_dealform.php'; ?></div><?php endif; ?>

<div class="crm-kpi"><div><small><?= e($sf->t('k_open')) ?></small><b><?= (int)$sum['open_n'] ?></b><span><?= e(crm_money($sum['open_sum'], $cur)) ?></span></div>
  <div><small><?= e($sf->t('k_forecast')) ?></small><b><?= e(crm_money($sum['weighted'], $cur)) ?></b></div>
  <div><small><?= e($sf->t('k_won')) ?></small><b><?= (int)$sum['won_n'] ?></b><span><?= e(crm_money($sum['won_sum'], $cur)) ?></span></div>
  <div><small><?= e($sf->t('k_winrate')) ?></small><b><?= $sum['win_rate'] === null ? '—' : e((string)$sum['win_rate']) . '%' ?></b></div></div>

<form method="get" class="card crm-filter"><input type="hidden" name="pipeline" value="<?= (int)$pid ?>"><input type="hidden" name="view" value="<?= e($view) ?>">
  <input name="q" value="<?= e($f['q']) ?>" placeholder="<?= e($sf->t('search')) ?>">
  <select name="owner"><option value=""><?= e($sf->t('all_owners')) ?></option><?php foreach ($users as $u): ?><option value="<?= (int)$u['id'] ?>"<?= (int)$f['owner'] === (int)$u['id'] ? ' selected' : '' ?>><?= e($u['name']) ?></option><?php endforeach; ?></select>
  <select name="status"><option value=""><?= e($sf->t('all_status')) ?></option><?php foreach (['open', 'won', 'lost'] as $k): ?><option value="<?= $k ?>"<?= $f['status'] === $k ? ' selected' : '' ?>><?= e($sf->t('s_' . $k)) ?></option><?php endforeach; ?></select>
  <input name="tag" value="<?= e($f['tag']) ?>" placeholder="<?= e($sf->t('f_tags')) ?>" style="max-width:140px">
  <button class="btn"><?= e($sf->t('apply')) ?></button>
  <span class="grow"></span>
  <span class="crm-seg"><a href="<?= e($q(['view' => 'board'])) ?>" class="<?= $view === 'board' ? 'on' : '' ?>"><?= e($sf->t('v_board')) ?></a><a href="<?= e($q(['view' => 'list'])) ?>" class="<?= $view === 'list' ? 'on' : '' ?>"><?= e($sf->t('v_list')) ?></a></span></form>

<?php if ($view === 'board'): ?>
<div class="crm-board" data-move="<?= e($sf->url('/deal/move')) ?>" data-token="<?= e(csrf_token()) ?>" data-can="<?= !empty($prof['edit']) ? '1' : '0' ?>" data-lost-prompt="<?= e($sf->t('lost_prompt')) ?>">
  <?php foreach ($stages as $s): $col = $board[(int)$s['id']] ?? []; $tot = array_sum(array_map(fn($x) => (float)$x['amount'], $col)); ?>
  <section class="crm-col" data-stage="<?= (int)$s['id'] ?>" data-kind="<?= e($s['kind']) ?>">
    <header style="--c:<?= e($s['color']) ?>"><b><?= e($s['name']) ?></b><span><?= count($col) ?> · <?= e(crm_money($tot)) ?></span></header>
    <div class="crm-cards">
    <?php foreach ($col as $d): $st = $d['next_due'] ? \SysferaCrm\Pipe::taskState($d['next_due'], null, $nowTs) : 'none'; ?>
      <a class="crm-card" href="<?= e($sf->url('/deal/' . (int)$d['id'])) ?>" draggable="<?= !empty($prof['edit']) ? 'true' : 'false' ?>" data-id="<?= (int)$d['id'] ?>">
        <b><?= e($d['title']) ?></b>
        <?php if ($d['contact_name']): ?><small class="muted"><?= e($d['contact_name']) ?><?= $d['contact_company'] ? ' · ' . e($d['contact_company']) : '' ?></small><?php endif; ?>
        <span class="crm-card-f"><span><?= e(crm_money($d['amount'], $d['currency'])) ?></span><?php if ($d['owner_name']): ?><i class="crm-av" title="<?= e($d['owner_name']) ?>"><?= e(crm_initials($d['owner_name'])) ?></i><?php endif; ?></span>
        <?php if ($d['open_tasks']): ?><small class="crm-t <?= e($st) ?>">● <?= (int)$d['open_tasks'] ?> · <?= e(crm_dt($d['next_due'])) ?></small><?php elseif ($d['kind'] === 'open'): ?><small class="crm-t none"><?= e($sf->t('no_task')) ?></small><?php endif; ?>
      </a>
    <?php endforeach; ?></div></section>
  <?php endforeach; ?></div>
<?php else: ?>
<div class="card" style="padding:0;overflow:auto"><table class="tbl crm-tbl"><thead><tr>
  <th><a href="<?= e($q(['sort' => 'title'])) ?>"><?= e($sf->t('f_title')) ?></a></th><th><?= e($sf->t('f_stage')) ?></th><th class="num"><a href="<?= e($q(['sort' => 'amount'])) ?>"><?= e($sf->t('f_amount')) ?></a></th>
  <th><?= e($sf->t('f_contact')) ?></th><th><?= e($sf->t('f_owner')) ?></th><th><a href="<?= e($q(['sort' => 'close'])) ?>"><?= e($sf->t('f_close')) ?></a></th><th><?= e($sf->t('next_task')) ?></th></tr></thead><tbody>
  <?php foreach ($deals as $d): ?><tr><td><a href="<?= e($sf->url('/deal/' . (int)$d['id'])) ?>"><b><?= e($d['title']) ?></b></a><?php if ($d['tags']): ?><div class="muted sm"><?= e($d['tags']) ?></div><?php endif; ?></td>
    <td><span class="crm-dot" style="--c:<?= e($d['stage_color']) ?>"></span><?= e($d['stage_name']) ?></td><td class="num"><?= e(crm_money($d['amount'], $d['currency'])) ?></td>
    <td><?= e($d['contact_name'] ?: '—') ?></td><td><?= e($d['owner_name'] ?: '—') ?></td><td><?= e(crm_date($d['expected_close'])) ?></td>
    <td><?= $d['next_due'] ? '<span class="crm-t ' . e(\SysferaCrm\Pipe::taskState($d['next_due'], null, $nowTs)) . '">' . e(crm_dt($d['next_due'])) . '</span>' : '—' ?></td></tr><?php endforeach; ?>
  <?php if (!$deals): ?><tr><td colspan="7" class="muted" style="text-align:center;padding:24px"><?= e($sf->t('none')) ?></td></tr><?php endif; ?></tbody></table></div>
<?php endif; ?>
<script src="<?= e($sf->asset('crm.js')) ?>?v=<?= e($sf->manifest['version']) ?>"></script>
