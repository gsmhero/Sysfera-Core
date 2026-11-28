<?php /** The text of a vacancy in sections; empty ones are not shown. Vars: $vd (Recruit::detailsOf), $vdText (the free description) */
$vdPara = function (string $h, string $text) use ($sf): void { if (trim($text) === '') return; ?>
  <section class="hr-sec"><h3><?= e($sf->t($h)) ?></h3><p><?= nl2br(e($text)) ?></p></section>
<?php };
$vdList = fn(array $items, string $tag = 'ul'): string => '<' . $tag . '>' . implode('', array_map(fn($x) => '<li>' . e((string)$x) . '</li>', $items)) . '</' . $tag . '>';
$vdChips = []; foreach (['contracts', 'benefits'] as $vdKey) foreach ((array)($vd[$vdKey] ?? []) as $vdVal) if (in_array($vdVal, \SysferaHr\Recruit::DETAIL_SETS[$vdKey], true)) $vdChips[] = $sf->t(\SysferaHr\Recruit::DETAIL_LABELS[$vdKey] . $vdVal);
$vdPara('d_company', (string)($vd['company'] ?? ''));
if (!$vd): ?><div class="hr-pv-text"><?= nl2br(e($vdText)) ?></div><?php else: $vdPara('f_description', $vdText); endif; ?>
<?php if (!empty($vd['responsibilities'])): ?>
  <section class="hr-sec"><h3><?= e($sf->t('d_responsibilities')) ?></h3><?= $vdList($vd['responsibilities'], 'ol') ?></section>
<?php endif; ?>
<?php if (!empty($vd['req_must']) || !empty($vd['req_nice'])): ?>
  <section class="hr-sec"><h3><?= e($sf->t('d_requirements')) ?></h3>
    <?php foreach (['req_must', 'req_nice'] as $vdKey): if (empty($vd[$vdKey])) continue; ?><h4><?= e($sf->t('d_' . $vdKey)) ?></h4><?= $vdList($vd[$vdKey]) ?><?php endforeach; ?></section>
<?php endif; ?>
<?php if (!empty($vd['tools'])): ?>
  <section class="hr-sec"><h3><?= e($sf->t('d_tools')) ?></h3><?= $vdList($vd['tools']) ?></section>
<?php endif; ?>
<?php if ($vdChips || !empty($vd['benefits_note'])): ?>
  <section class="hr-sec"><h3><?= e($sf->t('d_terms')) ?></h3>
    <?php if ($vdChips): ?><p class="hr-chips"><?php foreach ($vdChips as $vdVal): ?><span class="hr-chip"><i><?= e($vdVal) ?></i></span><?php endforeach; ?></p><?php endif; ?>
    <?php if (!empty($vd['benefits_note'])): ?><p><?= nl2br(e((string)$vd['benefits_note'])) ?></p><?php endif; ?></section>
<?php endif; ?>
<?php $vdPara('d_stages', (string)($vd['stages'] ?? '')); $vdPara('d_apply_info', (string)($vd['apply_info'] ?? '')); ?>
