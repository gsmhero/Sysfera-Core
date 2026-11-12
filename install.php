<?php
declare(strict_types=1);
/**
 * Sysfera web installer. Upload the package by FTP, open https://your-domain/install.php and follow the steps.
 * Every step explains what to do. Delete this file when finished (the last step offers a button for it).
 */
if (PHP_VERSION_ID < 80100) { header('Content-Type: text/plain; charset=utf-8'); exit('Sysfera needs PHP 8.1 or newer. Your server runs PHP ' . PHP_VERSION . ". Ask your hosting to switch the PHP version for this domain.\nSysfera требует PHP 8.1 или новее (сейчас " . PHP_VERSION . '). Попросите хостинг переключить версию PHP.'); }

$core = is_dir(__DIR__ . '/core') ? __DIR__ . '/core' : __DIR__;
require $core . '/app/bootstrap.php';

use Sysfera\{App, Db, Migrator, I18n};

session_name('sfinst'); session_start();
$langs = I18n::languages();
if (isset($_GET['lang']) && isset($langs[$_GET['lang']])) $_SESSION['lang'] = $_GET['lang'];
if (!isset($_SESSION['lang'])) { $al = strtolower(substr((string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''), 0, 2)); $_SESSION['lang'] = isset($langs[$al]) ? $al : 'en'; }
$L = $_SESSION['lang'];
$tx = (array)require $core . '/lang/install/en.php';
if ($L !== 'en' && is_file($core . "/lang/install/$L.php")) { $own = (array)require $core . "/lang/install/$L.php"; $tx = $own + $tx; }
$T = function (string $k, array $v = []) use ($tx): string { $s = $tx[$k] ?? $k; foreach ($v as $n => $x) $s = str_replace('{' . $n . '}', (string)$x, $s); return $s; };
$tok = $_SESSION['tok'] ??= bin2hex(random_bytes(16));
$post = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && hash_equals($tok, (string)($_POST['_t'] ?? ''));

$step = (int)($_SESSION['step'] ?? 1);
$err = ''; $extra = '';

/* ---------- requirements ---------- */
$checks = function () use ($core, $T): array {
    $w = fn($p) => is_dir($p) ? is_writable($p) : (@mkdir($p, 0755, true) && is_writable($p));
    return [
        [$T('r_php', ['v' => '8.1']), PHP_VERSION_ID >= 80100, true, 'PHP ' . PHP_VERSION],
        [$T('r_pdo'), extension_loaded('pdo_mysql'), true, ''],
        [$T('r_mb'), extension_loaded('mbstring'), true, ''],
        [$T('r_json'), extension_loaded('json'), true, ''],
        [$T('r_w_storage'), $w($core . '/storage'), true, $T('r_fix_w')],
        [$T('r_w_plugins'), $w($core . '/plugins'), true, $T('r_fix_w')],
        [$T('r_zip'), class_exists('ZipArchive'), false, ''],
    ];
};

if (App::installed()) {
    $step = 5;
    if ($post && ($_POST['do'] ?? '') === 'delete') $_SESSION['deleted'] = @unlink(__FILE__) ? 1 : 2;
}
elseif ($post) {
    $do = (string)($_POST['do'] ?? '');
    if ($do === 'back' && $step > 1 && $step < 4) $step--;
    elseif ($do === 'recheck' || ($do === 'next' && $step === 1)) {
        $okAll = true; foreach ($checks() as [$n, $ok, $req]) if ($req && !$ok) $okAll = false;
        if ($okAll && $do === 'next') $step = 2;
    } elseif ($do === 'db' && $step === 2) {
        $d = ['host' => trim((string)$_POST['host']) ?: 'localhost', 'port' => (int)($_POST['port'] ?? 0) ?: '', 'name' => trim((string)$_POST['name']), 'user' => trim((string)$_POST['user']), 'pass' => (string)($_POST['pass'] ?? ''), 'prefix' => trim((string)($_POST['prefix'] ?? 'sf_'))];
        $_SESSION['db'] = $d;
        if (!preg_match('/^[A-Za-z0-9]{1,12}_$/', $d['prefix'])) $err = $T('db_prefix_bad');
        else {
            try {
                $db = new Db($d);
                $db->pdo();
                // a previous, interrupted attempt left empty tables: that is fine (migrations are re-runnable); real data is not
                if (in_array($d['prefix'] . 'users', $db->tables(), true) && (int)$db->val('SELECT COUNT(*) FROM {p}users') > 0) $err = $T('db_has_tables');
                else $step = 3;
            } catch (Throwable $e) {
                $m = $e->getMessage();
                $err = str_contains($m, '1049') ? $T('db_unknown') : (str_contains($m, '1045') || str_contains($m, '1044') ? $T('db_denied') : $T('db_fail', ['err' => $m]));
            }
        }
    } elseif ($do === 'install' && $step === 3 && isset($_SESSION['db'])) {
        $site = trim((string)($_POST['site'] ?? '')) ?: 'Sysfera';
        $name = trim((string)($_POST['name'] ?? '')); $email = mb_strtolower(trim((string)($_POST['email'] ?? ''))); $pass = (string)($_POST['password'] ?? '');
        $_SESSION['adm'] = ['site' => $site, 'name' => $name, 'email' => $email];
        if ($name === '') $err = $T('err_name');
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err = $T('err_email');
        elseif (mb_strlen($pass) < 8) $err = $T('err_pass');
        else {
            try {
                $d = $_SESSION['db'];
                $db = new Db($d);
                $mig = new Migrator($db);
                $mig->run($core . '/app/migrations');
                $db->q('INSERT INTO {p}users (email, name, password_hash, role, lang) VALUES (?,?,?,?,?)', [$email, $name, password_hash($pass, PASSWORD_DEFAULT), 'admin', $L]);
                foreach (['site_name' => $site, 'default_lang' => $L, 'timezone' => date_default_timezone_get() ?: 'UTC', 'registration' => '0', 'core_version' => SF_VERSION, 'installed_at' => date('c')] as $k => $v)
                    $db->q('INSERT INTO {p}settings (k, v) VALUES (?,?) ON DUPLICATE KEY UPDATE v = VALUES(v)', [$k, $v]);
                $cfg = "<?php\nreturn " . var_export(['db' => $d, 'debug' => false, 'key' => bin2hex(random_bytes(24))], true) . ";\n";
                @mkdir($core . '/storage', 0755, true);
                if (@file_put_contents($core . '/storage/config.php', $cfg) === false) { $extra = $cfg; throw new RuntimeException($T('cfg_help')); }
                file_put_contents($core . '/storage/installed.lock', date('c'));
                @file_put_contents($core . '/storage/.htaccess', "Require all denied\nDeny from all\n");
                unset($_SESSION['db'], $_SESSION['adm']);
                $step = 4;
            } catch (Throwable $e) { $err = $T('err_install', ['err' => $e->getMessage()]); }
        }
    }
}
$_SESSION['step'] = $step;
if ($step === 4 && !App::installed()) $step = 3;

$esc = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$css = (string)@file_get_contents($core . '/assets/sysfera.css');
$total = 4;
$self = basename($_SERVER['SCRIPT_NAME'] ?? 'install.php');
$home = (App::basePath() ?: '') . '/';
?><!doctype html>
<html lang="<?= $esc($L) ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $esc($T('title')) ?></title><style><?= $css ?></style></head>
<body class="bare"><main class="auth"><div class="auth-box wide">
<div class="wordmark"><?= sf_logo() ?></div>
<p class="langs"><?php foreach ($langs as $c => $n): ?><a class="<?= $c === $L ? 'on' : '' ?>" href="<?= $esc($self) ?>?lang=<?= $esc($c) ?>"><?= $esc($n) ?></a><?php endforeach; ?></p>
<?php if ($step <= 4): ?><div class="steps"><?php for ($i = 1; $i <= $total; $i++): ?><i class="<?= $i < $step ? 'done' : ($i === $step ? 'on' : '') ?>"></i><?php endfor; ?></div>
<small class="muted"><?= $esc($T('step', ['n' => min($step, $total), 'total' => $total])) ?></small><?php endif; ?>
<?php if ($err !== ''): ?><div class="flash bad"><?= $esc($err) ?></div><?php if ($extra !== ''): ?><pre class="code"><?= $esc($extra) ?></pre><?php endif; endif; ?>

<?php if ($step === 1): $rows = $checks(); $okAll = true; ?>
  <h1><?= $esc($T('s1')) ?></h1><p class="lead"><?= $esc($T('lead')) ?></p><div class="hint"><?= $esc($T('s1_hint')) ?></div>
  <ul class="checks"><?php foreach ($rows as [$n, $ok, $req, $note]): if ($req && !$ok) $okAll = false; ?>
    <li class="<?= $ok ? 'ok' : ($req ? 'bad' : 'warn') ?>"><b><?= $esc($ok ? $T('ok') : ($req ? $T('bad') : $T('warn'))) ?></b> <?= $esc($n) ?><?php if ($note && (!$ok || str_starts_with($note, 'PHP'))): ?><small class="muted"> — <?= $esc($note) ?></small><?php endif; ?></li>
  <?php endforeach; ?></ul>
  <form method="post"><input type="hidden" name="_t" value="<?= $esc($tok) ?>">
    <?php if ($okAll): ?><button class="btn primary" name="do" value="next"><?= $esc($T('next')) ?></button><?php else: ?><button class="btn primary" name="do" value="recheck"><?= $esc($T('retry')) ?></button><?php endif; ?></form>

<?php elseif ($step === 2): $d = $_SESSION['db'] ?? ['host' => 'localhost', 'port' => '', 'name' => '', 'user' => '', 'pass' => '', 'prefix' => 'sf_']; ?>
  <h1><?= $esc($T('s2')) ?></h1><div class="hint"><?= $esc($T('s2_hint')) ?></div>
  <form method="post"><input type="hidden" name="_t" value="<?= $esc($tok) ?>"><input type="hidden" name="do" value="db">
    <div class="cols">
      <label class="fld"><span><?= $esc($T('db_host')) ?></span><input name="host" value="<?= $esc($d['host']) ?>" required></label>
      <label class="fld"><span><?= $esc($T('db_port')) ?></span><input name="port" value="<?= $esc($d['port']) ?>" inputmode="numeric" placeholder="3306"></label>
      <label class="fld"><span><?= $esc($T('db_name')) ?></span><input name="name" value="<?= $esc($d['name']) ?>" required></label>
      <label class="fld"><span><?= $esc($T('db_user')) ?></span><input name="user" value="<?= $esc($d['user']) ?>" required autocomplete="off"></label>
      <label class="fld"><span><?= $esc($T('db_pass')) ?></span><input type="password" name="pass" value="" autocomplete="new-password"></label>
      <label class="fld"><span><?= $esc($T('db_prefix')) ?></span><input name="prefix" value="<?= $esc($d['prefix']) ?>" required></label>
    </div>
    <div class="row"><button class="btn" name="do" value="back" formnovalidate><?= $esc($T('back')) ?></button><button class="btn primary"><?= $esc($T('next')) ?></button></div></form>

<?php elseif ($step === 3): $a = $_SESSION['adm'] ?? ['site' => '', 'name' => '', 'email' => '']; ?>
  <h1><?= $esc($T('s3')) ?></h1><div class="hint"><?= $esc($T('s3_hint')) ?></div>
  <form method="post"><input type="hidden" name="_t" value="<?= $esc($tok) ?>"><input type="hidden" name="do" value="install">
    <label class="fld"><span><?= $esc($T('site_name')) ?></span><input name="site" value="<?= $esc($a['site']) ?>" placeholder="My Company" maxlength="80"></label>
    <div class="cols">
      <label class="fld"><span><?= $esc($T('adm_name')) ?></span><input name="name" value="<?= $esc($a['name']) ?>" required></label>
      <label class="fld"><span><?= $esc($T('adm_email')) ?></span><input type="email" name="email" value="<?= $esc($a['email']) ?>" required></label>
      <label class="fld"><span><?= $esc($T('adm_pass')) ?></span><input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
    </div>
    <div class="row"><button class="btn" name="do" value="back" formnovalidate><?= $esc($T('back')) ?></button><button class="btn primary"><?= $esc($T('install_now')) ?></button></div></form>

<?php else: ?>
  <h1><?= $esc($T($step === 5 && !isset($_SESSION['deleted']) ? 'already' : 's4')) ?></h1>
  <?php if (isset($_SESSION['deleted'])): ?><div class="flash <?= $_SESSION['deleted'] === 1 ? 'ok' : 'warn' ?>"><?= $esc($T($_SESSION['deleted'] === 1 ? 'deleted' : 'not_deleted')) ?></div>
  <?php elseif (is_file(__FILE__)): ?><div class="hint"><?= $esc($T('s4_hint')) ?></div><?php endif; ?>
  <h3><?= $esc($T('next_steps')) ?></h3><ol class="steps-list"><li><?= $esc($T('next1')) ?></li><li><?= $esc($T('next2')) ?></li><li><?= $esc($T('next3')) ?></li></ol>
  <form method="post" class="row"><input type="hidden" name="_t" value="<?= $esc($tok) ?>">
    <a class="btn primary" href="<?= $esc($home) ?>"><?= $esc($T('open')) ?></a>
    <?php if (is_file(__FILE__)): ?><button class="btn" name="do" value="delete"><?= $esc($T('delete_installer')) ?></button><?php endif; ?></form>
<?php endif; ?>
</div></main></body></html>
