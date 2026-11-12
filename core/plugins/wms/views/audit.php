<?php /** @var \Sysfera\Plugin $sf @var string $tab @var array $rows @var array $pg @var int $total */
use SysferaWms\W;
include __DIR__ . '/_nav.php'; ?>
<div class="page-h"><h1><?= e($sf->t('nav_audit')) ?> <small class="muted"><?= (int)$total ?></small></h1></div>
<div class="hint"><?= e($sf->t('audit_hint')) ?></div>
<?php if (!$rows): ?><div class="card empty"><h3><?= e($sf->t('no_events')) ?></h3></div>
<?php else: ?>
<div class="card flush"><table class="tbl">
  <thead><tr><th><?= e($sf->t('date')) ?></th><th><?= e($sf->t('user')) ?></th><th><?= e($sf->t('event')) ?></th><th><?= e($sf->t('details')) ?></th></tr></thead>
  <tbody><?php foreach ($rows as $r): $d = json_decode((string)$r['data'], true) ?: [];
    $label = $sf->t('ev_' . str_replace('.', '_', $r['type'])); ?>
    <tr><td class="muted"><?= e(W::date($r['created_at'])) ?></td><td><?= e($r['user'] ?? '—') ?></td><td><?= e($label) ?></td>
      <td><?php if (!empty($d['id']) && str_starts_with($r['type'], 'doc.')): ?><a href="<?= e($sf->url('/docs/' . (int)$d['id'])) ?>"><?= e($d['number'] ?? ('#' . $d['id'])) ?></a>
        <?php elseif (!empty($d['id']) && str_starts_with($r['type'], 'product.')): ?><a href="<?= e($sf->url('/products/' . (int)$d['id'])) ?>"><?= e($d['name'] ?? ('#' . $d['id'])) ?></a>
        <?php else: ?><span class="muted"><?= e($d['name'] ?? $d['number'] ?? '') ?></span><?php endif; ?></td></tr>
  <?php endforeach; ?></tbody></table></div>
<?php if ($pg['pages'] > 1): ?><div class="pager"><?php for ($i = 1; $i <= $pg['pages']; $i++): ?><a class="<?= $i === $pg['cur'] ? 'on' : '' ?>" href="<?= e($sf->url('/audit') . '?page=' . $i) ?>"><?= $i ?></a><?php endfor; ?></div><?php endif; ?>
<?php endif; ?>
