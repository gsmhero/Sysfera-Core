<?php /** @var \Sysfera\Plugin $sf @var string $lang @var array $L @var array $cards @var string $copyEmail @var string $ownEmail @var string $userName @var bool $isAdmin @var string $api */
use SysferaContactlog\Svc;
$boot = ['api' => $api, 'token' => function_exists('csrf_token') ? csrf_token() : '', 'lang' => $lang, 'L' => $L, 'cards' => $cards, 'statuses' => Svc::STATUSES, 'colors' => Svc::COLORS, 'actions' => Svc::ACTIONS, 'copyEmail' => $copyEmail];
$json = json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_SUBSTITUTE);
?>
<link rel="stylesheet" href="<?= e($sf->asset('contactlog.css')) ?>?v=<?= e($sf->manifest['version']) ?>">
<div class="cl-app" id="clApp">
  <div class="page-h cl-top">
    <div><h1><?= e($sf->t('heading')) ?></h1><p class="muted"><?= e($sf->t('subtitle')) ?></p></div>
    <div class="row cl-controls">
      <select id="clCols" class="cl-sel" aria-label="<?= e($sf->t('cols', ['n' => ''])) ?>"><?php foreach ([2, 3, 4] as $n): ?><option value="<?= $n ?>"><?= e($sf->t('cols', ['n' => $n])) ?></option><?php endforeach; ?></select>
      <button type="button" class="btn primary" id="clAdd"><i data-ico="plus"></i><?= e($sf->t('add_card')) ?></button>
      <?php if ($isAdmin): ?><a class="btn" href="<?= e(url('/settings')) ?>" title="<?= e($sf->t('mail_settings_help')) ?>"><i data-ico="sliders"></i><?= e($sf->t('mail_settings')) ?></a><?php endif; ?>
      <button type="button" class="btn" id="clProfile" title="<?= e($sf->t('user_label')) ?> <?= e($userName) ?>"><i data-ico="user"></i><?= e($sf->t('profile')) ?></button>
    </div>
  </div>
  <div class="cl-bar">
    <label class="cl-search"><i data-ico="search"></i><input type="search" id="clSearch" placeholder="<?= e($sf->t('search_ph')) ?>" autocomplete="off"></label>
    <select id="clOrder" class="cl-sel"><option value="date"><?= e($sf->t('order_date')) ?></option><option value="status"><?= e($sf->t('order_status')) ?></option></select>
  </div>
  <div id="clCards" class="cl-grid" data-cols="3"></div>

  <div id="clCardModal" class="cl-modal cl-hide" role="dialog" aria-modal="true"><div class="cl-modal-c">
    <h3 id="clModalTitle"><?= e($sf->t('modal_add')) ?></h3>
    <div class="cl-err" id="clCardErr"></div>
    <input type="hidden" id="clId">
    <div class="cl-f"><label for="clName"><?= e($sf->t('f_name')) ?></label><input id="clName" maxlength="100" placeholder="<?= e($sf->t('ph_name')) ?>"></div>
    <div class="cl-f"><label for="clSecond"><?= e($sf->t('f_second')) ?></label><input id="clSecond" maxlength="100" placeholder="<?= e($sf->t('ph_second')) ?>"></div>
    <div class="cl-f"><label for="clSurname"><?= e($sf->t('f_surname')) ?></label><input id="clSurname" maxlength="100" placeholder="<?= e($sf->t('ph_surname')) ?>"></div>
    <div class="cl-f"><label for="clMobile"><?= e($sf->t('f_mobile')) ?></label><input id="clMobile" maxlength="40" placeholder="<?= e($sf->t('ph_mobile')) ?>"></div>
    <div class="cl-f"><label for="clMessenger"><?= e($sf->t('f_messenger')) ?></label><input id="clMessenger" maxlength="100" placeholder="<?= e($sf->t('ph_messenger')) ?>"></div>
    <div class="cl-f"><label for="clEmail"><?= e($sf->t('f_email')) ?></label><input id="clEmail" type="email" maxlength="190" placeholder="<?= e($sf->t('ph_email')) ?>"></div>
    <div class="cl-f"><label for="clStatus"><?= e($sf->t('f_status')) ?></label><select id="clStatus"><?php foreach (Svc::STATUSES as $s): ?><option value="<?= e($s) ?>"><?= e($sf->t('st_' . $s)) ?></option><?php endforeach; ?></select></div>
    <div class="cl-f"><label for="clTheme2"><?= e($sf->t('f_theme')) ?></label><input id="clTheme2" maxlength="190" placeholder="<?= e($sf->t('ph_theme')) ?>"></div>
    <div class="cl-f"><label for="clNotes"><?= e($sf->t('f_notes')) ?></label><textarea id="clNotes" rows="2" maxlength="4000"></textarea></div>
    <div class="cl-f"><label for="clNext"><?= e($sf->t('f_next')) ?></label><input type="date" id="clNext"></div>
    <div class="cl-actions"><button type="button" class="btn primary" id="clSave"><?= e($sf->t('save')) ?></button><button type="button" class="btn" id="clCardCancel"><?= e($sf->t('cancel')) ?></button></div>
  </div></div>

  <div id="clProfileModal" class="cl-modal cl-hide" role="dialog" aria-modal="true"><div class="cl-modal-c">
    <h3><?= e($sf->t('profile_title')) ?></h3>
    <p class="cl-help"><?= e($sf->t('profile_help')) ?></p>
    <div class="cl-err" id="clProfileErr"></div>
    <div class="cl-f"><label for="clOwn"><?= e($sf->t('own_email')) ?></label><input id="clOwn" value="<?= e($ownEmail) ?>" readonly class="cl-ro"></div>
    <div class="cl-f"><label for="clCopy"><?= e($sf->t('copy_email')) ?></label><input id="clCopy" type="email" maxlength="190" placeholder="copy@example.com"></div>
    <div class="cl-actions"><button type="button" class="btn primary" id="clProfileSave"><?= e($sf->t('update_profile')) ?></button><button type="button" class="btn" id="clProfileClose"><?= e($sf->t('close')) ?></button></div>
  </div></div>
  <div id="clToasts"></div>
</div>
<script type="application/json" id="clBoot"><?= $json ?></script>
<script src="<?= e($sf->asset('contactlog.js')) ?>?v=<?= e($sf->manifest['version']) ?>"></script>
