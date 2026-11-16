<?php /** @var \Sysfera\Plugin $sf @var array $task @var array $users @var array $prof @var string $backTo */
require __DIR__ . '/_nav.php'; $dealId = null; $contactId = null; ?>
<div class="page-h"><div><a class="btn sm" href="<?= e(url($backTo)) ?>">← <?= e($sf->t('tasks')) ?></a><h1 style="margin:2px 0 0"><?= e($sf->t('edit_task')) ?></h1></div></div>
<div class="card"><?php require __DIR__ . '/_taskform.php'; ?></div>
