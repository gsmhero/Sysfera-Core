<?php /** The counteroffer form (new and edit). Vars: $action (url), $rows (Svc::offerRows or offerPage items), $cur (symbol), $myTypes, $pre = ['notes', 'payment_type', 'offerer_type'], $asIs (bool: show "accept as is") */
$rowHtml = function (string $i, array $r) use ($sf, $ctm): string { $extra = $r['type'] === 'extra'; ob_start(); ?>
  <div class="ct-step <?= !empty($r['excluded']) ? 'out' : '' ?>" data-row>
    <?php if ($extra): ?><input type="hidden" name="items[<?= e($i) ?>][type]" value="extra"><?php else: ?><input type="hidden" name="items[<?= e($i) ?>][type]" value="todo"><input type="hidden" name="items[<?= e($i) ?>][id]" value="<?= (int)$r['id'] ?>"><?php endif; ?>
    <div class="row"><?php if ($extra): ?><label class="ct-lbl" style="flex:3 1 200px"><?= e($sf->t('step_name')) ?><input name="items[<?= e($i) ?>][name]" value="<?= e($r['name']) ?>" maxlength="190"></label>
      <?php else: ?><b style="flex:3 1 200px"><?= e($r['name']) ?><?php if ($r['price'] !== null): ?> <small class="muted">(<?= e($ctm($r['price'])) ?> / <?= (int)$r['days'] ?> <?= e($sf->t('days_short')) ?>)</small><?php endif; ?></b><?php endif; ?>
      <label class="ct-lbl"><?= e($sf->t('price')) ?><input name="items[<?= e($i) ?>][price]" value="<?= e((string)($r['o_price'] === null ? '' : ($r['o_price'] === '' ? '' : $ctm($r['o_price'])))) ?>" inputmode="decimal"></label>
      <label class="ct-lbl"><?= e($sf->t('days')) ?><input name="items[<?= e($i) ?>][days]" value="<?= e((string)$r['o_days']) ?>" inputmode="numeric"></label>
      <span><?php if (!$extra): ?><label title="<?= e($sf->t('unnecessary')) ?>"><input type="checkbox" name="items[<?= e($i) ?>][excluded]" value="1" <?= !empty($r['excluded']) ? 'checked' : '' ?>> <?= e($sf->t('unnecessary')) ?></label> <?php endif; ?><button class="btn sm" data-up>↑</button> <button class="btn sm" data-down>↓</button><?php if ($extra): ?> <button class="btn sm danger" data-remove>×</button><?php endif; ?></span></div>
    <?php if ($extra): ?><label class="ct-lbl"><?= e($sf->t('step_descr')) ?><textarea name="items[<?= e($i) ?>][description]" rows="2"><?= e($r['description']) ?></textarea></label><?php endif; ?></div>
<?php return (string)ob_get_clean(); }; ?>
<form method="post" action="<?= e($action) ?>" class="ct-form card" id="ct-offer"><?= csrf_field() ?>
  <h3><?= e($sf->t('counteroffer')) ?></h3><p class="muted"><?= e($sf->t('counteroffer_help')) ?></p>
  <div data-rows id="ct-offer-rows"><div data-list class="ct-rows"><?php foreach ($rows as $i => $r) echo $rowHtml((string)$i, $r); ?></div>
    <template><?= $rowHtml('__', ['type' => 'extra', 'id' => 0, 'name' => '', 'description' => '', 'price' => null, 'days' => null, 'o_price' => '', 'o_days' => '', 'excluded' => false]) ?></template>
    <p><button class="btn" data-add="#ct-offer-rows"><?= e($sf->t('add_own_step')) ?></button></p></div>
  <label class="ct-lbl"><?= e($sf->t('notes')) ?><textarea name="notes" rows="3"><?= e($pre['notes'] ?? '') ?></textarea></label>
  <div class="row"><label class="ct-lbl"><?= e($sf->t('payment')) ?><select name="payment_type"><?php foreach (['finish', 'step'] as $t): ?><option value="<?= $t ?>" <?= ($pre['payment_type'] ?? 'finish') === $t ? 'selected' : '' ?>><?= e($sf->t('pay_' . $t)) ?></option><?php endforeach; ?></select></label>
    <?php if (count($myTypes) > 1): ?><label class="ct-lbl"><?= e($sf->t('act_as')) ?><select name="offerer_type"><?php foreach ($myTypes as $t): ?><option value="<?= e($t) ?>" <?= ($pre['offerer_type'] ?? '') === $t ? 'selected' : '' ?>><?= e($sf->t('as_' . $t)) ?></option><?php endforeach; ?></select></label><?php endif; ?></div>
  <div class="row"><button class="btn primary"><?= e($sf->t('send_offer')) ?></button><?php if (!empty($asIs)): ?><button class="btn" name="mode" value="accept" formnovalidate><?= e($sf->t('accept_contract')) ?></button><?php endif; ?></div>
</form>
