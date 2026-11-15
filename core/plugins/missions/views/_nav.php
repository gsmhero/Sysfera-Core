<?php /** @var \Sysfera\Plugin $sf @var string $tab */
$tabs = ['missions' => ['/', 'nav_missions', '🏁'], 'bookmarks' => ['/?bm=1', 'bookmarks', '🔖'], 'library' => ['/library', 'nav_library', '📖'], 'notes' => ['/notes', 'nav_notes', '🔔']];
$unread = (int)$sf->db()->val('SELECT COUNT(*) FROM {p}mis_notes WHERE user_id = ? AND read_at IS NULL', [$sf->auth()->id()]);
if ($sf->auth()->isAdmin()) { $tabs['categories'] = ['/categories', 'nav_categories', '🏷']; $tabs['setup'] = ['/setup', 'setup', '⚙']; } ?>
<link rel="stylesheet" href="<?= e($sf->asset('missions.css')) ?>">
<div class="tmx">
<nav class="wtabs"><?php foreach ($tabs as $k => [$p, $label, $ico]): ?><a href="<?= e($sf->url($p)) ?>" class="<?= ($tab ?? '') === $k ? 'on' : '' ?>"><?= $ico ?> <?= e($sf->t($label)) ?><?= $k === 'notes' && $unread ? ' <span class="badge bad">' . $unread . '</span>' : '' ?></a><?php endforeach; ?></nav>
