<?php /** @var \Sysfera\Plugin $sf @var array $d @var ?array $contact @var array $stages @var array $defs @var array $users @var array $prof @var array $reasons @var string $cur @var array $tasks @var array $timeline @var array $pipeline @var int $nowTs */
require __DIR__ . '/_nav.php'; $pid = (int)$d['pipeline_id']; $backTo = '/p/crm/deal/' . (int)$d['id']; ?>
<div class="page-h"><div><a class="muted sm" href="<?= e($sf->url('/deals?pipeline=' . $pid)) ?>">← <?= e($pipeline['name'] ?? $sf->t('deals')) ?></a><h1 style="margin:2px 0 0"><?= e($d['title']) ?></h1></div>
  <span class="crm-amount"><?= e(crm_money($d['amount'], $d['currency'])) ?></span></div>

<div class="crm-stagebar" data-lost-prompt="<?= e($sf->t('lost_prompt')) ?>"><?php foreach ($stages as $s): $on = (int)$s['id'] === (int)$d['stage_id']; ?>
  <form method="post" action="<?= e($sf->url('/deal/save')) ?>" class="<?= $on ? 'on' : '' ?>" style="--c:<?= e($s['color']) ?>" data-kind="<?= e($s['kind']) ?>"><?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int)$d['id'] ?>"><input type="hidden" name="title" value="<?= e($d['title']) ?>"><input type="hidden" name="stage_id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="reason" value="">
    <input type="hidden" name="amount" value="<?= e((string)(float)$d['amount']) ?>"><input type="hidden" name="currency" value="<?= e($d['currency']) ?>"><input type="hidden" name="contact_id" value="<?= (int)$d['contact_id'] ?: '' ?>">
    <input type="hidden" name="expected_close" value="<?= e((string)$d['expected_close']) ?>"><input type="hidden" name="tags" value="<?= e((string)$d['tags']) ?>">
    <?php foreach ($defs as $df): $v = $d['extra'][$df['code']] ?? null; if ($v === null) continue; foreach ((array)$v as $one): ?><input type="hidden" name="f[<?= e($df['code']) ?>]<?= is_array($v) ? '[]' : '' ?>" value="<?= e(is_bool($one) ? ($one ? '1' : '') : (string)$one) ?>"><?php endforeach; endforeach; ?>
    <button<?= empty($prof['edit']) || $on ? ' disabled' : '' ?>><?= e($s['name']) ?></button></form><?php endforeach; ?></div>
<?php if ($d['kind'] === 'lost' && $d['lost_reason']): ?><p class="muted"><?= e($sf->t('lost_reason')) ?>: <b><?= e($d['lost_reason']) ?></b></p><?php endif; ?>

<div class="crm-two">
  <div>
    <section class="card"><h3><?= e($sf->t('details')) ?></h3>
      <?php if (!empty($prof['edit'])): ?><?php $pid = $pid; require __DIR__ . '/_dealform.php'; ?>
      <?php else: ?><dl class="crm-dl"><dt><?= e($sf->t('f_contact')) ?></dt><dd><?= e($contact['name'] ?? '—') ?></dd><dt><?= e($sf->t('f_stage')) ?></dt><dd><?= e($d['stage_name']) ?></dd>
        <?php foreach ($defs as $df): ?><dt><?= e($df['label']) ?></dt><dd><?= e(\SysferaCrm\Fields::show($df, $d['extra'][$df['code']] ?? null) ?: '—') ?></dd><?php endforeach; ?></dl><?php endif; ?></section>
    <section class="card"><h3><?= e($sf->t('history')) ?></h3>
      <?php if (!empty($prof['edit'])): ?><form method="post" action="<?= e($sf->url('/deal/' . (int)$d['id'] . '/note')) ?>" class="crm-note"><?= csrf_field() ?><textarea name="body" rows="2" maxlength="3000" placeholder="<?= e($sf->t('note_ph')) ?>" required></textarea><button class="btn"><?= e($sf->t('add_note')) ?></button></form><?php endif; ?>
      <?php require __DIR__ . '/_timeline.php'; ?></section>
  </div>
  <div>
    <?php if ($contact): ?><section class="card"><h3><?= e($sf->t('f_contact')) ?></h3><a href="<?= e($sf->url('/contact/' . (int)$contact['id'])) ?>"><b><?= e($contact['name']) ?></b></a>
      <div class="muted sm"><?= e(implode(' · ', array_filter([$contact['company'], $contact['email'], $contact['phone']]))) ?></div></section><?php endif; ?>
    <section class="card"><h3><?= e($sf->t('tasks')) ?></h3>
      <?php $rows = array_map(fn($t) => $t, $tasks); require __DIR__ . '/_tasklist.php'; ?>
      <?php if (!empty($prof['create'])): ?><details class="crm-add"><summary><?= e($sf->t('add_task')) ?></summary><?php $dealId = (int)$d['id']; $contactId = $d['contact_id'] ? (int)$d['contact_id'] : null; require __DIR__ . '/_taskform.php'; ?></details><?php endif; ?></section>
    <?php if (!empty($prof['delete'])): ?><form method="post" action="<?= e($sf->url('/deal/' . (int)$d['id'] . '/delete')) ?>" data-confirm="<?= e($sf->t('confirm_delete')) ?>"><?= csrf_field() ?><button class="btn danger"><?= e($sf->t('delete')) ?></button></form><?php endif; ?>
    <p class="muted sm"><?= e($sf->t('created')) ?>: <?= e(crm_dt($d['created_at'])) ?><?= $d['closed_at'] ? ' · ' . e($sf->t('closed')) . ': ' . e(crm_dt($d['closed_at'])) : '' ?></p>
  </div>
</div>
<script src="<?= e($sf->asset('crm.js')) ?>?v=<?= e($sf->manifest['version']) ?>"></script>
