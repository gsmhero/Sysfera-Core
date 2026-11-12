<?php
declare(strict_types=1);
namespace Sysfera;

/**
 * Keeps the core and the plugins up to date without losing data (see docs/UPDATES.md).
 *
 *  1. The update server publishes manifest.json: {"core":{"version","url","sha256","sig","notes":{en,ru}},"plugins":{slug:{...}}}
 *  2. An update is downloaded, its SHA-256 (and Ed25519 signature, if a public key is configured) is verified.
 *  3. A backup is made first: a zip of the files that will be replaced + an SQL dump of all tables of this install.
 *  4. Files are replaced — NEVER storage/ (config, uploads, backups) and never other plugins' data — then migrations run.
 *  5. If anything fails, the previous files are restored; the SQL dump stays in storage/backups for manual recovery.
 */
final class Updater
{
    public const DEFAULT_URL = 'https://core.sysfera.ru/updates/manifest.json';

    public function __construct(private App $app) {}

    public function url(): string { return $this->app->setting('update_url', self::DEFAULT_URL) ?: self::DEFAULT_URL; }

    /** Latest manifest (cached 6 hours unless $force). Returns null if the server cannot be reached. */
    public function manifest(bool $force = false): ?array
    {
        $at = (int)$this->app->setting('update_checked', '0');
        $cached = json_decode($this->app->setting('update_cache', ''), true);
        if (!$force && is_array($cached) && time() - $at < 21600) return $cached;
        $raw = $this->fetch($this->url(), 5 * 1024 * 1024);
        $m = $raw !== null ? json_decode($raw, true) : null;
        if (!is_array($m)) return is_array($cached) ? $cached : null;
        $this->app->setSetting('update_cache', json_encode($m, JSON_UNESCAPED_UNICODE));
        $this->app->setSetting('update_checked', (string)time());
        return $m;
    }

    public function coreUpdate(?array $m = null): ?array
    {
        $m ??= $this->manifest();
        $c = $m['core'] ?? null;
        return ($c && !empty($c['version']) && version_compare((string)$c['version'], SF_VERSION, '>')) ? $c : null;
    }

    /** @return array<string,array> slug => manifest entry for installed plugins with a newer version available */
    public function pluginUpdates(?array $m = null): array
    {
        $m ??= $this->manifest();
        $out = [];
        foreach ((array)($m['plugins'] ?? []) as $slug => $p) {
            $row = $this->app->plugins()->installed()[$slug] ?? null;
            if ($row && !empty($p['version']) && version_compare((string)$p['version'], (string)$row['version'], '>')) $out[$slug] = $p;
        }
        return $out;
    }

    public function applyCore(): string
    {
        $m = $this->manifest(true);
        $c = $this->coreUpdate($m) ?? throw new \RuntimeException(t('upd.none'));
        $zip = $this->download($c);
        $backup = $this->backup('core-' . SF_VERSION);
        $new = SF_ROOT . '/storage/tmp/core-' . bin2hex(random_bytes(4));
        try {
            $this->extract($zip, $new);
            if (!is_file("$new/VERSION") || !is_file("$new/app/bootstrap.php")) throw new \RuntimeException(t('upd.bad_package'));
            if (version_compare(trim((string)file_get_contents("$new/VERSION")), SF_VERSION, '<=')) throw new \RuntimeException(t('upd.bad_package'));
            $this->maintenance(true);
            $this->copyTree($new, SF_ROOT, ['storage', 'plugins']);
            (new Migrator($this->app->db()))->run(SF_ROOT . '/app/migrations');   // new files are in place now; finished statements are skipped
        } catch (\Throwable $e) {
            $this->restore($backup . '/files.zip');
            $this->maintenance(false);
            Plugins::rrmdir($new); @unlink($zip);
            App::log('core update failed: ' . $e->getMessage());
            throw new \RuntimeException(t('upd.failed', ['why' => $e->getMessage(), 'dir' => basename($backup)]));
        }
        $this->maintenance(false);
        Plugins::rrmdir($new); @unlink($zip);
        $this->app->setSetting('update_cache', '');
        return (string)$c['version'];
    }

    public function applyPlugin(string $slug): string
    {
        $m = $this->manifest(true);
        $p = $m['plugins'][$slug] ?? throw new \RuntimeException(t('upd.none'));
        $zip = $this->download($p);
        $installed = isset($this->app->plugins()->installed()[$slug]);
        $backup = $installed ? $this->backup('plugin-' . $slug . '-' . $this->app->plugins()->installed()[$slug]['version'], $slug) : null;
        try {
            $got = $this->app->plugins()->extractZip($zip);
            if ($got !== $slug) throw new \RuntimeException(t('upd.bad_package'));
            if ($installed) $this->app->plugins()->enable($slug);   // runs the new migrations, keeps all data
        } catch (\Throwable $e) {
            if ($backup) $this->restore($backup . '/files.zip');
            @unlink($zip);
            throw new \RuntimeException(t('upd.failed', ['why' => $e->getMessage(), 'dir' => $backup ? basename($backup) : '-']));
        }
        @unlink($zip);
        $this->app->setSetting('update_cache', '');
        return (string)$p['version'];
    }

    /** Download a package described by a manifest entry and verify it. Returns the local zip path. */
    public function download(array $entry): string
    {
        $url = (string)($entry['url'] ?? '');
        if (!preg_match('~^https://~', $url) && $this->app->setting('allow_http_updates', '0') !== '1') throw new \RuntimeException(t('upd.https_only'));
        @mkdir(SF_ROOT . '/storage/tmp', 0755, true);
        $file = SF_ROOT . '/storage/tmp/pkg-' . bin2hex(random_bytes(4)) . '.zip';
        $raw = $this->fetch($url, 100 * 1024 * 1024);
        if ($raw === null || file_put_contents($file, $raw) === false) throw new \RuntimeException(t('upd.download_failed'));
        $sha = hash('sha256', $raw);
        if (empty($entry['sha256']) || !hash_equals(strtolower((string)$entry['sha256']), $sha)) { @unlink($file); throw new \RuntimeException(t('upd.bad_checksum')); }
        $key = (string)(@include SF_ROOT . '/app/update_key.php');
        if ($key !== '') {
            $ok = function_exists('sodium_crypto_sign_verify_detached') && !empty($entry['sig'])
                && @sodium_crypto_sign_verify_detached((string)base64_decode((string)$entry['sig'], true), $sha, (string)base64_decode($key, true));
            if (!$ok) { @unlink($file); throw new \RuntimeException(t('upd.bad_signature')); }
        }
        return $file;
    }

    private function fetch(string $url, int $max): ?string
    {
        $ctx = stream_context_create(['http' => ['timeout' => 20, 'follow_location' => 1, 'max_redirects' => 3, 'user_agent' => 'Sysfera/' . SF_VERSION], 'ssl' => ['verify_peer' => true]]);
        $fp = @fopen($url, 'rb', false, $ctx);
        if (!$fp) return null;
        $d = stream_get_contents($fp, $max + 1); fclose($fp);
        return ($d === false || strlen($d) > $max) ? null : $d;
    }

    /* ---------- backup / restore ---------- */

    /** Zip of the files that an update may replace + SQL dump of this install's tables. Returns the backup folder. */
    public function backup(string $label, ?string $onlyPlugin = null): string
    {
        if (!class_exists(\ZipArchive::class)) throw new \RuntimeException(t('upd.need_zip'));
        $dir = SF_ROOT . '/storage/backups/' . date('Ymd-His') . '-' . preg_replace('/[^A-Za-z0-9._-]/', '', $label);
        if (!@mkdir($dir, 0755, true) && !is_dir($dir)) throw new \RuntimeException(t('upd.not_writable'));
        $z = new \ZipArchive();
        $z->open("$dir/files.zip", \ZipArchive::CREATE);
        $base = $onlyPlugin ? SF_ROOT . '/plugins/' . $onlyPlugin : SF_ROOT;
        $prefix = $onlyPlugin ? 'plugins/' . $onlyPlugin . '/' : '';
        if (is_dir($base)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)) as $f) {
                $rel = substr($f->getPathname(), strlen($base) + 1);
                $rel = str_replace('\\', '/', $rel);
                if (!$onlyPlugin && (str_starts_with($rel, 'storage/') || str_starts_with($rel, 'plugins/'))) continue;
                if ($f->isFile()) $z->addFile($f->getPathname(), $prefix . $rel);
            }
        }
        $z->close();
        $this->dump("$dir/database.sql");
        $this->pruneBackups(5);
        return $dir;
    }

    public function dump(string $file): void
    {
        $db = $this->app->db(); $pdo = $db->pdo();
        $fp = fopen($file, 'wb');
        fwrite($fp, "-- Sysfera backup " . date('c') . " (core " . SF_VERSION . ")\nSET FOREIGN_KEY_CHECKS=0;\n\n");
        foreach ($db->tables() as $t) {
            $create = $pdo->query('SHOW CREATE TABLE `' . $t . '`')->fetch(\PDO::FETCH_NUM)[1];
            fwrite($fp, "DROP TABLE IF EXISTS `$t`;\n$create;\n\n");
            $st = $pdo->query('SELECT * FROM `' . $t . '`');
            $buf = [];
            while ($r = $st->fetch(\PDO::FETCH_NUM)) {
                $buf[] = '(' . implode(',', array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v), $r)) . ')';
                if (count($buf) >= 200) { fwrite($fp, "INSERT INTO `$t` VALUES " . implode(",\n", $buf) . ";\n"); $buf = []; }
            }
            if ($buf) fwrite($fp, "INSERT INTO `$t` VALUES " . implode(",\n", $buf) . ";\n");
            fwrite($fp, "\n");
        }
        fwrite($fp, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($fp);
    }

    public function restore(string $zipFile): void
    {
        if (!is_file($zipFile)) return;
        $z = new \ZipArchive();
        if ($z->open($zipFile) !== true) return;
        for ($i = 0; $i < $z->numFiles; $i++) {
            $n = $z->getNameIndex($i);
            if (str_contains($n, '..') || str_starts_with($n, '/')) continue;
            @mkdir(dirname(SF_ROOT . '/' . $n), 0755, true);
            file_put_contents(SF_ROOT . '/' . $n, $z->getFromIndex($i));
        }
        $z->close();
    }

    public function backups(): array
    {
        $out = [];
        foreach (glob(SF_ROOT . '/storage/backups/*', GLOB_ONLYDIR) ?: [] as $d) $out[] = ['name' => basename($d), 'size' => (int)@filesize("$d/files.zip") + (int)@filesize("$d/database.sql")];
        rsort($out);
        return $out;
    }

    private function pruneBackups(int $keep): void
    {
        $dirs = glob(SF_ROOT . '/storage/backups/*', GLOB_ONLYDIR) ?: [];
        sort($dirs);
        foreach (array_slice($dirs, 0, max(0, count($dirs) - $keep)) as $d) Plugins::rrmdir($d);
    }

    /* ---------- files ---------- */

    private function extract(string $zipFile, string $to): void
    {
        $z = new \ZipArchive();
        if ($z->open($zipFile) !== true) throw new \RuntimeException(t('upd.bad_package'));
        @mkdir($to, 0755, true);
        for ($i = 0; $i < $z->numFiles; $i++) {
            $n = $z->getNameIndex($i);
            if ($n === '' || str_contains($n, '..') || str_starts_with($n, '/') || str_contains($n, '\\')) continue;
            if (str_ends_with($n, '/')) { @mkdir("$to/$n", 0755, true); continue; }
            @mkdir(dirname("$to/$n"), 0755, true);
            file_put_contents("$to/$n", $z->getFromIndex($i));
        }
        $z->close();
    }

    private function copyTree(string $from, string $to, array $skipTop): void
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($from, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST) as $f) {
            $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($from) + 1));
            if (in_array(explode('/', $rel)[0], $skipTop, true)) continue;
            if ($f->isDir()) { @mkdir("$to/$rel", 0755, true); continue; }
            @mkdir(dirname("$to/$rel"), 0755, true);
            if (!copy($f->getPathname(), "$to/$rel")) throw new \RuntimeException(t('upd.not_writable') . ' ' . $rel);
        }
    }

    public function maintenance(bool $on): void
    {
        $f = SF_ROOT . '/storage/maintenance.flag';
        if ($on) file_put_contents($f, (string)time()); else @unlink($f);
    }
}
