<?php /** @var \Sysfera\Plugin $sf @var string $tab @var array $rows @var string $st */
include __DIR__ . '/_nav.php'; ?>
<div class="page-h"><h1><?= e($sf->t('nav_incidents')) ?></h1><span class="row"><a class="btn sm <?= $st === 'open' ? 'primary' : '' ?>" href="<?= e($sf->url('/incidents')) ?>"><?= e($sf->t('open')) ?></a><a class="btn sm <?= $st === 'resolved' ? 'primary' : '' ?>" href="<?= e($sf->url('/incidents?status=resolved')) ?>"><?= e($sf->t('resolved')) ?></a></span></div>
<?php if (!$rows): ?><div class="card empty"><h3><?= e($sf->t('none')) ?></h3></div><?php endif; ?>
<?php foreach ($rows as $i): $d = json_decode((string)$i['details'], true) ?: []; $isOrder = preg_match('/^[0-9a-f-]{36}$/', $i['ref']); ?>
<div class="card"><p><span class="gi-sev gi-sev-<?= e($i['severity']) ?>"><?= e($sf->t('inc_' . $i['kind'])) ?></span> <b><?= e($i['wname'] ?? '') ?></b> <span class="muted"><?= e($i['created_at']) ?></span>
  <?php if ($isOrder): ?> · <a href="<?= e($sf->url('/orders/' . $i['ref'])) ?>"><?= e($sf->t('open_order')) ?></a><?php endif; ?></p>
  <pre class="gi-pre"><?= e(json_encode($d, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) ?></pre>
  <?php if ($i['status'] === 'open'): ?><form method="post" class="row"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$i['id'] ?>"><input name="note" placeholder="<?= e($sf->t('resolve_note')) ?>" maxlength="300"><button class="btn"><?= e($sf->t('close_incident')) ?></button></form>
  <?php else: ?><p class="muted"><?= e($sf->t('closed_by', ['n' => (string)$i['closed_by'], 't' => (string)$i['closed_at']])) ?> <?= e((string)$i['note']) ?></p><?php endif; ?></div>
<?php endforeach; ?>
