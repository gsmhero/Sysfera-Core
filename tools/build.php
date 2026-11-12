<?php
declare(strict_types=1);
/**
 * Builds every distributable into dist/ (needs only PHP + zlib; no zip extension).
 *   php tools/build.php
 *
 *   dist/sysfera-<ver>.zip               full package: unzip into a domain folder, open /install.php
 *   dist/sysfera-install.php             ONE self-extracting file: upload it, open it in the browser
 *   dist/updates/core-<ver>.zip          core update (contents of core/ without storage/ and plugins/)
 *   dist/updates/plugins/<slug>-<v>.zip  one zip per bundled component
 *   dist/updates/manifest.json           what the in-app updater reads
 *
 * Environment (all optional):
 *   SYSFERA_UPDATE_BASE   public URL of the dist/updates folder (default https://core.sysfera.ru/updates)
 *   SYSFERA_SIGN_SECRET   base64 Ed25519 secret key (sodium_crypto_sign_keypair → secret) to sign packages
 *   SYSFERA_NOTES_EN / SYSFERA_NOTES_RU   release notes shown in the updater
 */
$root = dirname(__DIR__);
$ver = trim((string)file_get_contents("$root/core/VERSION"));
if (!preg_match('/^\d+\.\d+\.\d+$/', $ver)) { fwrite(STDERR, "core/VERSION must look like 1.2.3\n"); exit(1); }
$base = rtrim((string)getenv('SYSFERA_UPDATE_BASE'), '/');
if (!preg_match('~^https?://~', $base)) $base = 'https://core.sysfera.ru/updates';
$dist = "$root/dist";
@mkdir("$dist/updates/plugins", 0755, true);
foreach (glob("$dist/*.{zip,php}", GLOB_BRACE) ?: [] as $f) unlink($f);
foreach (glob("$dist/updates/*.{zip,json}", GLOB_BRACE) ?: [] as $f) unlink($f);
foreach (glob("$dist/updates/plugins/*.zip") ?: [] as $f) unlink($f);

/** Files below $dir as relative path => absolute path, skipping junk and anything matching $skip (regex on relative path). */
function files(string $dir, string $skip = ''): array
{
    $out = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!$f->isFile()) continue;
        $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($dir) + 1));
        if (preg_match('~(^|/)(\.git|\.DS_Store|Thumbs\.db)(/|$)~', $rel) || ($skip !== '' && preg_match($skip, $rel))) continue;
        $out[$rel] = $f->getPathname();
    }
    ksort($out);
    return $out;
}

/** Writes a ZIP (deflate) from name => file path or ['data' => string]. */
function make_zip(string $target, array $entries): void
{
    $fp = fopen($target, 'wb'); $cd = ''; $n = 0;
    foreach ($entries as $name => $src) {
        $data = is_array($src) ? $src['data'] : (string)file_get_contents($src);
        $crc = crc32($data); $def = gzdeflate($data, 9); $method = 8;
        if ($def === false || strlen($def) >= strlen($data)) { $def = $data; $method = 0; }
        $off = ftell($fp); $nl = strlen($name);
        $time = 0; $date = ((2026 - 1980) << 9) | (1 << 5) | 1;
        fwrite($fp, pack('VvvvvvVVVvv', 0x04034b50, 20, 0x0800, $method, $time, $date, $crc, strlen($def), strlen($data), $nl, 0) . $name . $def);
        $cd .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 0x031e, 20, 0x0800, $method, $time, $date, $crc, strlen($def), strlen($data), $nl, 0, 0, 0, 0, 0x81a40000, $off) . $name;
        $n++;
    }
    $cdOff = ftell($fp);
    fwrite($fp, $cd . pack('VvvvvVVv', 0x06054b50, 0, 0, $n, $n, strlen($cd), $cdOff, 0));
    fclose($fp);
}

function sign(string $sha): string
{
    $sk = getenv('SYSFERA_SIGN_SECRET');
    if (!$sk || !function_exists('sodium_crypto_sign_detached')) return '';
    return base64_encode(sodium_crypto_sign_detached($sha, (string)base64_decode($sk)));
}

/* ---- 1. full package ---- */
$full = [];
foreach (['index.php', 'install.php', '.htaccess'] as $f) $full[$f] = "$root/$f";
foreach (files("$root/core", '~^storage/(?!\.htaccess$|\.gitkeep$)~') as $rel => $abs) $full["core/$rel"] = $abs;
make_zip("$dist/sysfera-$ver.zip", $full);

/* ---- 2. single-file installer ---- */
$tpl = (string)file_get_contents(__DIR__ . '/container.tpl.php');
file_put_contents("$dist/sysfera-install.php", str_replace('@@VERSION@@', $ver, $tpl) . "\n" . chunk_split(base64_encode((string)file_get_contents("$dist/sysfera-$ver.zip")), 76, "\n"));

/* ---- 3. core update (replaces program files; storage/ and plugins/ are never in it) ---- */
$core = files("$root/core", '~^(storage|plugins)/~');
make_zip("$dist/updates/core-$ver.zip", $core);
$coreSha = hash_file('sha256', "$dist/updates/core-$ver.zip");

/* ---- 4. components ---- */
$plugins = [];
foreach (glob("$root/core/plugins/*/plugin.json") ?: [] as $pj) {
    $m = json_decode((string)file_get_contents($pj), true);
    if (!is_array($m) || empty($m['slug']) || empty($m['version'])) continue;
    $zipName = "plugins/{$m['slug']}-{$m['version']}.zip";
    make_zip("$dist/updates/$zipName", files(dirname($pj)));
    $sha = hash_file('sha256', "$dist/updates/$zipName");
    $plugins[$m['slug']] = ['version' => $m['version'], 'name' => $m['name'] ?? $m['slug'], 'description' => $m['description'] ?? '', 'url' => "$base/$zipName", 'sha256' => $sha, 'sig' => sign($sha)];
}

/* ---- 5. manifest ---- */
$manifest = [
    'generated' => gmdate('c'),
    'core' => ['version' => $ver, 'url' => "$base/core-$ver.zip", 'sha256' => $coreSha, 'sig' => sign($coreSha),
        'notes' => ['en' => (string)(getenv('SYSFERA_NOTES_EN') ?: ''), 'ru' => (string)(getenv('SYSFERA_NOTES_RU') ?: '')]],
    'plugins' => $plugins,
];
file_put_contents("$dist/updates/manifest.json", json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");

/* ---- 6. Core News: news/news.json → updates/news.json (shown on every install's dashboard) ---- */
$news = is_file("$root/news/news.json") ? json_decode((string)file_get_contents("$root/news/news.json"), true) : [];
if (!is_array($news)) { fwrite(STDERR, "news/news.json is not valid JSON (expected a list of posts)\n"); exit(1); }
file_put_contents("$dist/updates/news.json", json_encode(array_values($news), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");

printf("Sysfera %s built:\n", $ver);
foreach (array_merge(glob("$dist/*") ?: [], glob("$dist/updates/*") ?: [], glob("$dist/updates/plugins/*") ?: []) as $f) if (is_file($f)) printf("  %-48s %s\n", substr($f, strlen($dist) + 1), human($f));
function human(string $f): string { $b = filesize($f); return $b > 1048576 ? round($b / 1048576, 1) . ' MB' : round($b / 1024, 1) . ' KB'; }
