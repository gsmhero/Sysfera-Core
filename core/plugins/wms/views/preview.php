<?php /** @var \Sysfera\Plugin $sf @var array $p @var array $imgs @var array $opts @var array $specs @var float $stockQty @var float $price @var string $cat @var string $back */
use SysferaWms\W;
$type = (string)$p['ptype']; $unlimited = in_array($type, ['virtual', 'downloadable'], true);
$noStock = !$unlimited && $stockQty < 1; ?>
<!doctype html>
<html lang="<?= e($sf->lang ?? 'en') ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex"><title><?= e($sf->t('pv_title')) ?> — <?= e($p['name']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Onest:wght@400;600;700&family=Unbounded:wght@600;700&display=swap" rel="stylesheet">
<style>
:root{--bg:#f6f4ef;--card:#fff;--ink:#15171e;--ink2:#2a2d38;--muted:#6c707c;--line:#e8e5dc;--soft:#f0ede5;--brand:#7a7a7a;--accent:#b8893a;--accent-d:#8a6420;--accent-soft:#f7ecd5;--bad:#c23d3d;--r:16px;--r-sm:10px;--sh:0 1px 2px rgba(20,22,29,.05),0 8px 24px -12px rgba(20,22,29,.14);--f:'Onest',system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;--fd:'Unbounded','Onest',sans-serif}
@media(prefers-color-scheme:dark){:root{--bg:#101218;--card:#181b23;--ink:#eceef3;--ink2:#d3d6df;--muted:#9298a6;--line:#272b36;--soft:#1f232d;--accent:#d9b25f;--accent-d:#e7c67e;--accent-soft:#2c2616}}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font:400 15px/1.55 var(--f);-webkit-font-smoothing:antialiased}
a{color:inherit;text-decoration:none}img{max-width:100%;display:block}h1,h4{margin:0 0 .5em;line-height:1.15;letter-spacing:-.02em;font-weight:700}
.wrap{width:min(1240px,100% - 32px);margin-inline:auto}.muted{color:var(--muted)}.sm{font-size:13px}
.pv{background:var(--ink);color:var(--bg);font-size:13px}.pv .wrap{display:flex;gap:14px;align-items:center;justify-content:space-between;padding:9px 0;flex-wrap:wrap}.pv a{border:1px solid currentColor;border-radius:99px;padding:4px 14px;font-weight:600}
.top{background:color-mix(in srgb,var(--bg) 86%,transparent);border-bottom:1px solid var(--line)}.top-in{display:flex;align-items:center;gap:22px;height:68px}
.logo{font:700 34px var(--fd);color:var(--brand);letter-spacing:-.05em}.logo sup{font-size:.45em;color:var(--accent)}
.nav{display:flex;gap:4px}.nav span{padding:8px 14px;font-weight:600;font-size:14px;color:var(--ink2);text-transform:uppercase;letter-spacing:.04em}
.search{flex:1;max-width:340px;margin-left:auto;background:var(--card);border:1px solid var(--line);border-radius:99px;padding:9px 16px;color:var(--muted)}
.crumbs{margin-top:24px;color:var(--muted);font-size:14px}
.pdp{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:48px;margin:20px 0 60px}
.gal-main{border-radius:var(--r);overflow:hidden;aspect-ratio:4/3.2;background:var(--soft);border:1px solid var(--line);display:grid;place-items:center;color:var(--muted)}.gal-main img{width:100%;height:100%;object-fit:cover}
.gal-th{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-top:12px}.gal-th button{padding:0;border:2px solid transparent;border-radius:10px;overflow:hidden;aspect-ratio:1;cursor:pointer;background:var(--soft)}.gal-th button.on{border-color:var(--accent)}.gal-th img{width:100%;height:100%;object-fit:cover}
h1{font-size:clamp(26px,3.4vw,40px)}
.prices{background:var(--card);border:1px solid var(--line);border-radius:var(--r);padding:20px;box-shadow:var(--sh);margin:18px 0}.prices .r{display:flex;justify-content:space-between;align-items:center;padding:6px 0}.big{font:700 32px var(--fd)}
.owner{display:flex;align-items:center;gap:12px;background:var(--card);border:1px solid var(--line);border-radius:var(--r);padding:14px 16px;margin-bottom:18px}.av{width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,#d9b25f,#b8893a);display:grid;place-items:center;font-weight:700;color:#1a1405}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:11px 20px;border-radius:12px;border:1px solid var(--line);background:var(--card);color:var(--ink);font:600 14px var(--f);cursor:default;white-space:nowrap}
.btn.sm{padding:8px 14px;font-size:13px;border-radius:10px}.btn.gold{background:linear-gradient(135deg,#d9b25f,#b8893a);color:#1a1405;border-color:transparent;box-shadow:0 8px 20px -8px rgba(184,137,58,.7)}.btn.lg{padding:15px 28px;font-size:16px;border-radius:14px}.btn[disabled]{opacity:.5}
.buybar{display:flex;gap:12px;align-items:center;flex-wrap:wrap}.qty{display:flex;align-items:center;border:1px solid var(--line);border-radius:14px;background:var(--card)}.qty span{width:44px;height:48px;display:grid;place-items:center;font-weight:600}
.opts{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px}.opts button{padding:8px 14px;border:1px solid var(--line);border-radius:10px;font:600 14px var(--f);background:var(--card);color:var(--ink);cursor:pointer}.opts button.on{border-color:var(--accent);background:var(--accent-soft);color:var(--accent-d)}.opts button[disabled]{opacity:.45;cursor:not-allowed;text-decoration:line-through}
.attrs{margin-top:22px}.attrs dl{margin:0;display:grid;grid-template-columns:minmax(120px,38%) 1fr;border:1px solid var(--line);border-radius:var(--r-sm);overflow:hidden;background:var(--card)}.attrs dt,.attrs dd{margin:0;padding:10px 14px;border-bottom:1px solid var(--line)}.attrs dt{color:var(--muted);font-size:13px;background:var(--soft)}.attrs dt:last-of-type,.attrs dd:last-of-type{border:0}
.chip{display:inline-block;padding:3px 10px;border-radius:99px;background:var(--soft);font-size:12px;font-weight:600;color:var(--ink2)}
@media(max-width:860px){.pdp{grid-template-columns:1fr;gap:28px}.nav{display:none}}
</style></head><body>
<div class="pv"><div class="wrap"><span><b><?= e($sf->t('pv_title')) ?></b> · <?= e($sf->t('pv_note')) ?></span><a href="<?= e($back) ?>">← <?= e($sf->t('pv_back')) ?></a></div></div>
<header class="top"><div class="wrap top-in"><span class="logo">base<sup>2</sup></span><nav class="nav"><span>Home</span><span>Products</span><span>Blogs</span><span>FAQ</span></nav><span class="search">Search products</span></div></header>
<div class="wrap">
  <div class="crumbs">Products<?= $cat !== '' ? ' / ' . e($cat) : '' ?></div>
  <div class="pdp">
    <div>
      <div class="gal-main" id="main"><?php if ($imgs): ?><img src="<?= e($imgs[0]) ?>" alt="" referrerpolicy="no-referrer"><?php else: ?><span><?= e($sf->t('pv_noimg')) ?></span><?php endif; ?></div>
      <?php if (count($imgs) > 1): ?><div class="gal-th"><?php foreach ($imgs as $i => $u): ?><button type="button" class="<?= $i === 0 ? 'on' : '' ?>" data-src="<?= e($u) ?>"><img src="<?= e($u) ?>" alt="" referrerpolicy="no-referrer"></button><?php endforeach; ?></div><?php endif; ?>
    </div>
    <div>
      <h1><?= e($p['name']) ?></h1>
      <?php if (trim((string)$p['short_desc']) !== ''): ?><p class="muted" style="font-size:16px"><?= e($p['short_desc']) ?></p><?php endif; ?>
      <div class="prices">
        <div class="r"><span class="muted">Price</span><span class="big" id="price"><?= $price > 0 ? e(W::money($price)) : '—' ?></span></div>
        <div class="r"><span class="muted">Stock</span><b id="stock"><?php if ($unlimited): ?>∞<?php elseif ($stockQty >= 1): ?><?= e(W::qty(floor($stockQty))) ?> pc<?php else: ?><span style="color:var(--bad)">Out of stock</span><?php endif; ?></b></div>
      </div>
      <div class="owner"><span class="av">Y</span><div style="flex:1"><div class="muted sm">Product author</div><b><?= e($sf->t('pv_shop')) ?></b></div><span class="btn sm">Visit shop</span></div>
      <?php if ($opts): ?><div class="muted sm" style="margin-bottom:8px;font-weight:600">Option</div><div class="opts"><?php foreach ($opts as $i => $o): ?><button type="button" class="<?= $i === 0 ? 'on' : '' ?>" data-price="<?= e($o['price'] > 0 ? W::money($o['price']) : '') ?>" data-stock="<?= (int)$o['stock'] ?>" <?= $o['stock'] < 1 ? 'disabled' : '' ?>><?= e($o['name']) ?><?= $o['price'] > 0 ? ' · ' . e(W::money($o['price'])) : '' ?></button><?php endforeach; ?></div><?php endif; ?>
      <div class="buybar"><div class="qty"><span>−</span><span>1</span><span>+</span></div><span class="btn gold lg" <?= $noStock ? 'disabled' : '' ?>>🛒 Add to cart</span></div>
      <div class="buybar" style="margin-top:12px"><span class="btn">Collect &amp; earn</span><span class="btn">Watch later</span></div>
      <?php if ($type === 'bundle' && $parts): ?><div class="attrs"><h4><?= e($sf->t('pv_bundle')) ?></h4><dl><?php foreach ($parts as $x): ?><dt><?= e($x['name']) ?></dt><dd>× <?= e(W::qty($x['qty'])) ?></dd><?php endforeach; ?></dl></div><?php endif; ?>
      <?php if (trim((string)$p['description']) !== ''): ?><div class="attrs"><h4>Description</h4><div><?= nl2br(e($p['description'])) ?></div></div><?php endif; ?>
      <?php foreach ($specs as $sec => $list): ?><div class="attrs"><h4><?= e($sec) ?></h4><dl><?php foreach ($list as $a): ?><dt><?= e($a['name']) ?></dt><dd><?= e($a['value']) ?></dd><?php endforeach; ?></dl></div><?php endforeach; ?>
    </div>
  </div>
</div>
<script>
document.querySelectorAll('.gal-th button').forEach(function (b) { b.addEventListener('click', function () {
  document.querySelector('#main img').src = b.dataset.src; document.querySelectorAll('.gal-th button').forEach(function (x) { x.classList.toggle('on', x === b); }); }); });
document.querySelectorAll('.opts button').forEach(function (b) { b.addEventListener('click', function () {
  if (b.disabled) return; document.querySelectorAll('.opts button').forEach(function (x) { x.classList.toggle('on', x === b); });
  if (b.dataset.price) document.getElementById('price').textContent = b.dataset.price; document.getElementById('stock').textContent = b.dataset.stock + ' pc'; }); });
</script>
</body></html>
