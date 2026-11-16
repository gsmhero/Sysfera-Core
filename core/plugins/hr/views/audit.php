<?php /** @var \Sysfera\Plugin $sf @var array $rows @var ?array $verify @var string $entity @var string $action */
include __DIR__ . '/_nav.php'; ?>
<div class="page-h"><h1><?= e($sf->t('nav_audit')) ?></h1></div>
<form method="get" class="row hr-noprint"><select name="entity"><option value=""><?= e($sf->t('all')) ?></option><?php foreach (['employee', 'user', 'role', 'vacancy', 'candidate', 'team', 'export', 'payroll', 'schedule'] as $en): ?><option value="<?= e($en) ?>" <?= $entity === $en ? 'selected' : '' ?>><?= e($en) ?></option><?php endforeach; ?></select>
  <input name="action" value="<?= e($action) ?>" placeholder="<?= e($sf->t('f_action')) ?>"><button class="btn"><?= e($sf->t('search')) ?></button><button class="btn" name="verify" value="1"><?= e($sf->t('verify_chain')) ?></button></form>
<?php if ($verify): ?><div class="<?= $verify['ok'] ? 'hint' : 'flash warn' ?>"><?= e($sf->t($verify['ok'] ? 'chain_ok' : 'chain_broken', ['n' => $verify['rows'], 'id' => (string)$verify['broken_at']])) ?></div><?php endif; ?>
<div class="card flush"><table class="tbl"><tr><th>#</th><th><?= e($sf->t('f_when')) ?></th><th><?= e($sf->t('f_who')) ?></th><th><?= e($sf->t('f_action')) ?></th><th><?= e($sf->t('f_object')) ?></th><th><?= e($sf->t('f_details')) ?></th></tr>
  <?php if (!$rows): ?><tr><td colspan="6" class="muted"><?= e($sf->t('none')) ?></td></tr><?php endif; ?>
  <?php foreach ($rows as $r): ?><tr><td><?= (int)$r['id'] ?></td><td><?= e($r['ts']) ?></td><td><?= e($r['actor']) ?></td><td><code><?= e($r['action']) ?></code></td><td><?= e($r['entity']) ?> <code class="muted"><?= e(substr((string)$r['ref'], 0, 8)) ?></code></td><td class="muted" style="word-break:break-word"><?= e((string)$r['data']) ?></td></tr><?php endforeach; ?></table></div>
