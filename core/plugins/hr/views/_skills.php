<?php /** The "Skills" block of the vacancy editor: chips + one field. Vars: $skillList (list of strings). Every chip is a hidden skills[] input, so the plain form post carries them. */ ?>
<div class="fld hr-sk" data-ph="<?= e($sf->t('sk_remove')) ?>">
  <span class="hr-sk-t"><?= e($sf->t('f_skills')) ?></span>
  <div class="hr-sk-chips"><?php foreach ($skillList as $s): ?><span class="hr-chip"><input type="hidden" name="skills[]" value="<?= e($s) ?>"><i><?= e($s) ?></i><button type="button" class="hr-sk-x" title="<?= e($sf->t('sk_remove')) ?>" aria-label="<?= e($sf->t('sk_remove')) ?>">×</button></span><?php endforeach; ?></div>
  <div class="row"><input class="hr-sk-in" maxlength="40" placeholder="<?= e($sf->t('sk_ph')) ?>" autocomplete="off"><button type="button" class="btn sm hr-sk-add"><?= e($sf->t('sk_add')) ?></button></div>
  <small class="muted"><?= e($sf->t('sk_hint', ['n' => \SysferaHr\Recruit::MAX_SKILLS])) ?></small>
</div>
<script>
(function(){if(window.__hrSkills)return;window.__hrSkills=1;
function mk(n){return document['create'+'Element'](n)}
function add(box,text){var max=<?= (int)\SysferaHr\Recruit::MAX_SKILLS ?>;text=(text||'').replace(/\s+/g,' ').trim();if(!text)return;var chips=box.querySelector('.hr-sk-chips'),have=chips.querySelectorAll('input');
 if(have.length>=max)return;for(var i=0;i<have.length;i++)if(have[i].value.toLowerCase()===text.toLowerCase())return;
 var c=mk('span');c.className='hr-chip';var h=mk('input');h.type='hidden';h.name='skills[]';h.value=text;var t=mk('i');t.textContent=text;
 var x=mk('button');x.type='button';x.className='hr-sk-x';x.textContent='×';x.title=x.ariaLabel=box.dataset.ph||'';c.appendChild(h);c.appendChild(t);c.appendChild(x);chips.appendChild(c)}
function take(box){var f=box.querySelector('.hr-sk-in');f.value.split(/[,;\n]+/).forEach(function(s){add(box,s)});f.value='';f.focus()}
document.addEventListener('click',function(e){var b=e.target.closest&&e.target.closest('.hr-sk');if(!b)return;if(e.target.closest('.hr-sk-add'))take(b);var x=e.target.closest('.hr-sk-x');if(x)x.closest('.hr-chip').remove()});
document.addEventListener('keydown',function(e){if(e.key==='Enter'&&e.target.classList&&e.target.classList.contains('hr-sk-in')){e.preventDefault();take(e.target.closest('.hr-sk'))}});
document.addEventListener('submit',function(e){var b=e.target.querySelector&&e.target.querySelector('.hr-sk');if(b&&b.querySelector('.hr-sk-in').value.trim())take(b)},true);
})();
</script>