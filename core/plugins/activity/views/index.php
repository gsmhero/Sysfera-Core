<?php /** @var \Sysfera\Plugin $sf @var array $d @var string $from @var string $to @var string $sid @var string $chatUrl @var bool $mod */
sf_session(); $form = $_SESSION['act_form'] ?? ['title' => '', 'body' => '']; unset($_SESSION['act_form']);
$q = fn(array $o) => $sf->url('/?' . http_build_query(array_filter(array_merge(['page' => $d['page'], 'per' => $d['per'], 'from' => $from, 'to' => $to], $o), fn($v) => $v !== '' && $v !== null))); ?>
<link rel="stylesheet" href="<?= e($sf->asset('activity.css')) ?>?v=<?= e($sf->manifest['version']) ?>">
<div class="page-h"><h1><?= e($sf->t('title')) ?></h1><span class="row"><?php if ($sf->can('activity.embed')): ?><a class="btn" href="<?= e($sf->url('/embed')) ?>"><?= e($sf->t('embed')) ?></a><?php endif; ?><?php if ($mod): ?><a class="btn" href="<?= e($sf->url('/moderate')) ?>"><?= e($sf->t('moderate')) ?></a><?php endif; ?></span></div>

<form class="card" method="post" action="<?= e($sf->url('/post')) ?>" id="actform"><?= csrf_field() ?>
  <label class="fld"><span><?= e($sf->t('f_title')) ?> <small class="muted" id="ct">0 / 20</small></span><input name="title" id="at" value="<?= e($form['title']) ?>" maxlength="400" required></label>
  <label class="fld"><span><?= e($sf->t('f_body')) ?> <small class="muted" id="cb">0 / 50</small></span><textarea name="body" id="ab" rows="3" maxlength="1200" required><?= e($form['body']) ?></textarea></label>
  <div class="row"><button class="btn primary"><?= e($sf->t('publish')) ?></button><small class="muted"><?= e($sf->t('rules')) ?></small></div>
</form>

<form method="get" class="card act-f"><?php /* filters */ ?>
  <label><?= e($sf->t('per')) ?> <select name="per" onchange="this.form.submit()"><?php foreach ([5, 20, 50] as $n): ?><option value="<?= $n ?>"<?= $n === $d['per'] ? ' selected' : '' ?>><?= $n ?></option><?php endforeach; ?></select></label>
  <label><?= e($sf->t('from')) ?> <input type="date" name="from" value="<?= e($from) ?>"></label>
  <label><?= e($sf->t('to')) ?> <input type="date" name="to" value="<?= e($to) ?>"></label>
  <button class="btn"><?= e($sf->t('apply')) ?></button>
  <?php if ($from !== '' || $to !== ''): ?><a href="<?= e($q(['from' => '', 'to' => '', 'page' => 1])) ?>"><?= e($sf->t('reset')) ?></a><?php endif; ?>
  <small class="muted"><?= e($sf->t('total', ['n' => $d['total']])) ?></small></form>

<?php if (!$d['items']): ?><div class="card empty"><h3><?= e($sf->t('none')) ?></h3><p class="muted"><?= e($sf->t('none_hint')) ?></p></div><?php endif; ?>
<?php foreach ($d['items'] as $p): ?><article class="card act-post">
  <div class="act-h"><b><?= e($p['title']) ?></b><small class="muted"><?= e($p['at']) ?></small></div>
  <p><?= nl2br(e($p['body'])) ?></p>
  <div class="act-f2"><span class="muted sm"><?= e($p['name']) ?> · <?= e($p['sid']) ?></span>
    <span class="row">
    <?php if ($p['mine']): ?>
      <form method="post" action="<?= e($sf->url('/action')) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn sm danger" name="do" value="delete" data-confirm="<?= e($sf->t('confirm_delete')) ?>"><?= e($sf->t('delete')) ?></button></form>
    <?php else: ?>
      <a class="btn sm primary" href="<?= e($chatUrl . rawurlencode($p['sid'])) ?>">✉ <?= e($sf->t('write_author')) ?></a>
      <?php if (!$p['reported']): ?><form method="post" action="<?= e($sf->url('/action')) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn sm" name="do" value="report" data-confirm="<?= e($sf->t('confirm_report')) ?>"><?= e($sf->t('report')) ?></button></form>
      <?php else: ?><small class="muted"><?= e($sf->t('reported')) ?></small><?php endif; ?>
    <?php endif; ?></span></div></article><?php endforeach; ?>

<?php if ($d['pages'] > 1): ?><p style="text-align:center">
  <?php if ($d['page'] > 1): ?><a class="btn" href="<?= e($q(['page' => $d['page'] - 1])) ?>">‹</a><?php endif; ?>
  <span class="muted"> <?= (int)$d['page'] ?> / <?= (int)$d['pages'] ?> </span>
  <?php if ($d['page'] < $d['pages']): ?><a class="btn" href="<?= e($q(['page' => $d['page'] + 1])) ?>">›</a><?php endif; ?></p><?php endif; ?>
<script>
(function(){function w(s){return (s.match(/[\p{L}\p{N}][\p{L}\p{N}'’_-]*/gu)||[]).length}
function bind(i,c,max){var a=document.getElementById(i),b=document.getElementById(c);function u(){var n=w(a.value);b.textContent=n+' / '+max;b.style.color=n>max?'#c0392b':''}a.addEventListener('input',u);u()}
bind('at','ct',20);bind('ab','cb',50);
document.getElementById('actform').addEventListener('submit',function(e){if(w(document.getElementById('at').value)>20||w(document.getElementById('ab').value)>50){e.preventDefault();alert(<?= json_encode($sf->t('rules')) ?>)}})})();
</script>
