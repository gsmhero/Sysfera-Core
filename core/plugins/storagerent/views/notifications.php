<?php /** @var \Sysfera\Plugin $sf @var \SysferaStoragerent\Ctx $ctx @var string $lang @var array $tpl @var array $events @var array $placeholders @var array $log @var array $tenants @var array $set @var bool $tariff */
require __DIR__ . '/_nav.php'; ?>
<div class="card"><h3><?= e($sf->t('nt_schedule')) ?></h3>
  <p><?= e($sf->t('nt_schedule_text', ['before' => $set['before'], 'after' => $set['after'], 'expiring' => $set['expiring']])) ?></p>
  <?php if ($ctx->can('write')): ?><form method="post" action="<?= e($sf->url('/notifications/run')) ?>"><?= csrf_field() ?><button class="btn"><?= e($sf->t('nt_run')) ?></button></form><?php endif; ?>
  <p class="muted sm"><?= e($sf->t('nt_schedule_help')) ?></p></div>
<div class="card"><h3><?= e($sf->t('nt_templates')) ?></h3><p class="muted sm"><?= e($sf->t('nt_placeholders')) ?>: <?php foreach ($placeholders as $p): ?><code>{<?= e($p) ?>}</code> <?php endforeach; ?></p>
<?php foreach ($events as $ev): foreach (['ru', 'en'] as $l): $x = $tpl[$ev][$l]; ?>
  <details><summary><b><?= e($sf->t('ev_' . $ev)) ?></b> · <?= e(strtoupper($l)) ?><?= $x['custom'] ? ' <span class="badge info">' . e($sf->t('nt_custom')) . '</span>' : '' ?></summary>
  <?php if ($tariff): ?><form method="post" action="<?= e($sf->url('/notifications/template')) ?>" class="sr-form"><?= csrf_field() ?><input type="hidden" name="event" value="<?= e($ev) ?>"><input type="hidden" name="lang" value="<?= e($l) ?>">
    <?= sr_in('subject', $sf->t('nt_subject'), $x['subject'], 'text', 'maxlength="190"') ?><?= sr_area('body', $sf->t('nt_body'), $x['body'], '', 6) ?>
    <button class="btn primary"><?= e($sf->t('save')) ?></button> <?php if ($x['custom']): ?><button class="btn" name="reset" value="1"><?= e($sf->t('nt_reset')) ?></button><?php endif; ?></form>
  <?php else: ?><p><b><?= e($x['subject']) ?></b></p><pre class="sr-pre"><?= e($x['body']) ?></pre><?php endif; ?></details>
<?php endforeach; endforeach; ?></div>
<div class="card"><h3><?= e($sf->t('nt_log')) ?></h3><?php if (!$log): ?><p class="muted"><?= e($sf->t('none')) ?></p><?php else: ?><div class="sr-scroll"><table><thead><tr><th><?= e($sf->t('col_date')) ?></th><th><?= e($sf->t('col_event')) ?></th><th><?= e($sf->t('col_tenant')) ?></th><th><?= e($sf->t('f_email')) ?></th><th><?= e($sf->t('col_status')) ?></th></tr></thead><tbody>
<?php foreach ($log as $r): ?><tr><td><?= e(sr_dt((int)$r['created_at'])) ?></td><td><?= e($sf->t('ev_' . $r['event'])) ?></td><td><?= e($tenants[(int)$r['tenant_id']] ?? '') ?></td><td><?= e($r['to_email']) ?></td><td><span class="badge <?= e(sr_cls((string)$r['status'])) ?>"><?= e($sf->t('nt_' . $r['status'])) ?></span> <?= e($r['error']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div>
