<?php /** The start page for guests: blog posts with a search. The posts come from a component (hook "landing.feed").
 * @var array $feed ['html' => string, 'global' => bool the shared feed of all sites can be chosen] @var string $scope 'local' or 'global' @var string $q @var string $blocks public blocks of components (HTML) */
$to = fn(string $s): string => url('/') . '?' . http_build_query(array_filter(['scope' => $s, 'q' => $q], fn($v) => $v !== '')); ?>
<section class="land-sec land-feed"><div class="land-w">
  <h1 class="land-h1"><?= e(t('land.feed_h')) ?></h1>
  <form class="land-search" method="get" action="<?= e(url('/')) ?>" role="search"><input type="hidden" name="scope" value="<?= e($scope) ?>">
    <input type="search" name="q" value="<?= e($q) ?>" maxlength="100" placeholder="<?= e(t('land.search_ph')) ?>" aria-label="<?= e(t('land.search_ph')) ?>"><button class="btn primary"><?= e(t('land.search_btn')) ?></button></form>
  <?php if ($feed['global']): ?><nav class="land-switch"><a class="<?= $scope === 'local' ? 'on' : '' ?>" href="<?= e($to('local')) ?>"><?= e(t('land.feed_local')) ?></a><a class="<?= $scope === 'global' ? 'on' : '' ?>" href="<?= e($to('global')) ?>"><?= e(t('land.feed_global')) ?></a></nav><?php endif; ?>
  <?= $feed['html'] /* built and escaped by the component */ ?>
</div></section>
<?= $blocks ?>
