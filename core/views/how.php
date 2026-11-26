<?php /** How it works. @var bool $open registration is open */ ?>
<section class="land-sec"><div class="land-w">
  <h1 class="land-h1"><?= e(t('land.how_h')) ?></h1>
  <ol class="land-steps"><li><b><?= e(t('land.s1')) ?></b><span><?= e(t('land.s1d')) ?></span></li><li><b><?= e(t('land.s2')) ?></b><span><?= e(t('land.s2d')) ?></span></li><li><b><?= e(t('land.s3')) ?></b><span><?= e(t('land.s3d')) ?></span></li></ol>
</div></section>
<section class="land-sec alt"><div class="land-w">
  <h2><?= e(t('land.new_h')) ?></h2><p class="muted"><?= e(t('land.new_p')) ?></p>
  <div class="land-grid">
  <?php foreach (['blog', 'shared', 'profile', 'products', 'search'] as $k): ?><article class="land-card"><h3><?= e(t("land.f_$k")) ?></h3><p><?= e(t("land.f_{$k}_d")) ?></p></article><?php endforeach; ?>
  </div>
  <p class="land-cta"><a class="btn primary big" href="<?= e(url($open ? '/register' : '/login')) ?>"><?= e(t($open ? 'land.cta_register' : 'land.cta_login')) ?></a></p>
</div></section>
