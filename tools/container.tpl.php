<?php
declare(strict_types=1);
/*
 * Sysfera self-extracting installer (one file).
 * 1. Upload this single file to the folder of your domain (public_html / www / the sub-domain folder) by FTP.
 * 2. Open it in the browser:  https://your-domain/<this file name>
 * 3. Press "Unpack and continue" — the files are unpacked next to it and the installation wizard starts.
 * It needs no PHP zip extension. This file deletes itself after unpacking.
 */
$VERSION = '@@VERSION@@';
$ru = str_starts_with(strtolower((string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')), 'ru');
if (isset($_GET['lang'])) $ru = $_GET['lang'] === 'ru';
$X = $ru ? [
    'title' => 'Распаковка Sysfera', 'lead' => 'Это единый файл-установщик Sysfera ' . $VERSION . '. Он распакует программу в эту папку, а затем запустит мастер установки.',
    'hint' => 'Ничего не нужно настраивать. Убедитесь, что вы загрузили файл в папку нужного домена и что папка доступна для записи (права 755).',
    'go' => 'Распаковать и продолжить', 'php' => 'Нужен PHP 8.1 или новее. Сейчас: ', 'exists' => 'В этой папке уже есть Sysfera (файл core). Чтобы не затереть данные, распаковка остановлена. Откройте сайт или удалите этот файл.',
    'fail' => 'Не удалось распаковать: ', 'perm' => 'Нет прав на запись в папку. Выставьте права 755 (или 775) этой папке в FTP-клиенте или файловом менеджере хостинга.', 'switch' => 'English',
] : [
    'title' => 'Unpack Sysfera', 'lead' => 'This is the single-file Sysfera ' . $VERSION . ' installer. It unpacks the program into this folder and then starts the installation wizard.',
    'hint' => 'Nothing to configure. Make sure you uploaded the file into the folder of the right domain and that the folder is writable (permissions 755).',
    'go' => 'Unpack and continue', 'php' => 'PHP 8.1 or newer is required. Now: ', 'exists' => 'Sysfera is already present in this folder (the core folder). Unpacking was stopped so your data is not overwritten. Open the site or delete this file.',
    'fail' => 'Could not unpack: ', 'perm' => 'The folder is not writable. Set permissions 755 (or 775) on it in your FTP client or hosting file manager.', 'switch' => 'Русский',
];
$msg = ''; $ok = false;
$dir = __DIR__;

/** Minimal ZIP reader (stored + deflate) so no php-zip extension is needed. */
function sf_unzip(string $zip, string $to): int
{
    $eocd = strrpos($zip, "PK\x05\x06");
    if ($eocd === false) throw new RuntimeException('bad archive');
    $n = unpack('vcount/Vsize/Voffset', substr($zip, $eocd + 10, 10));
    $pos = $n['offset']; $done = 0;
    for ($i = 0; $i < $n['count']; $i++) {
        $h = unpack('vmethod/vtime/vdate/Vcrc/Vcsize/Vsize/vnlen/velen/vclen', substr($zip, $pos + 10, 32));
        $name = substr($zip, $pos + 46, $h['nlen']);
        $lh = unpack('vnlen/velen', substr($zip, unpack('V', substr($zip, $pos + 42, 4))[1] + 26, 4));
        $start = unpack('V', substr($zip, $pos + 42, 4))[1] + 30 + $lh['nlen'] + $lh['elen'];
        $pos += 46 + $h['nlen'] + $h['elen'] + $h['clen'];
        if ($name === '' || str_contains($name, '..') || str_starts_with($name, '/') || str_contains($name, '\\')) continue;
        if (str_ends_with($name, '/')) { @mkdir("$to/$name", 0755, true); continue; }
        $raw = substr($zip, $start, $h['csize']);
        $data = $h['method'] === 8 ? gzinflate($raw) : $raw;
        if ($data === false || crc32($data) !== $h['crc']) throw new RuntimeException("damaged file $name");
        @mkdir(dirname("$to/$name"), 0755, true);
        if (file_put_contents("$to/$name", $data) === false) throw new RuntimeException('perm');
        $done++;
    }
    return $done;
}

if (PHP_VERSION_ID < 80100) $msg = $X['php'] . PHP_VERSION;
elseif (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (is_dir("$dir/core")) $msg = $X['exists'];
    elseif (!is_writable($dir)) $msg = $X['perm'];
    else {
        try {
            $fp = fopen(__FILE__, 'rb'); fseek($fp, __COMPILER_HALT_OFFSET__);
            $zip = base64_decode(trim((string)stream_get_contents($fp))); fclose($fp);
            if ($zip === false || $zip === '') throw new RuntimeException('empty payload');
            sf_unzip($zip, $dir);
            @unlink(__FILE__);
            header('Location: install.php'); exit;
        } catch (Throwable $e) { $msg = $e->getMessage() === 'perm' ? $X['perm'] : $X['fail'] . $e->getMessage(); }
    }
}
?><!doctype html><html lang="<?= $ru ? 'ru' : 'en' ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= htmlspecialchars($X['title']) ?></title>
<style>body{margin:0;background:#f5f6f8;color:#16181d;font:15px/1.5 system-ui,sans-serif;display:grid;place-items:center;min-height:100vh;padding:16px}.b{background:#fff;border:1px solid #e3e6eb;border-radius:14px;padding:28px;max-width:520px;width:100%}.w{font-size:30px;font-weight:300;text-align:center}.w b{color:#4f46e5}.h{background:#e6eefc;border-radius:10px;padding:11px 14px;font-size:14px;margin:14px 0}.e{background:#fdecec;color:#b91c1c;border-radius:10px;padding:11px 14px;margin:14px 0}button{background:#4f46e5;color:#fff;border:0;border-radius:8px;padding:10px 18px;font:inherit;cursor:pointer}a{color:#4f46e5;float:right;font-size:13px}@media(prefers-color-scheme:dark){body{background:#0f1115;color:#e8eaee}.b{background:#171a21;border-color:#262b35}.h{background:#15213a}.e{background:#2e1616;color:#f87171}}</style></head>
<body><div class="b"><div class="w">sys<b>fera</b></div><a href="?lang=<?= $ru ? 'en' : 'ru' ?>"><?= $X['switch'] ?></a><h2><?= htmlspecialchars($X['title']) ?></h2><p><?= htmlspecialchars($X['lead']) ?></p>
<div class="h"><?= htmlspecialchars($X['hint']) ?></div><?php if ($msg !== ''): ?><div class="e"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if (PHP_VERSION_ID >= 80100): ?><form method="post"><button><?= htmlspecialchars($X['go']) ?></button></form><?php endif; ?></div></body></html>
<?php __halt_compiler();
