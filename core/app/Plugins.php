<?php
declare(strict_types=1);
namespace Sysfera;

/** Finds, installs, enables and boots plugin components (folders in /plugins, each with plugin.json). */
final class Plugins
{
    private ?array $installed = null;

    public function __construct(private Db $db, private App $app) {}

    public static function dir(): string { return SF_ROOT . '/plugins'; }

    /** All plugin folders found on disk: slug => manifest (+ 'dir'). */
    public function available(): array
    {
        $out = [];
        foreach (glob(self::dir() . '/*/plugin.json') ?: [] as $f) {
            $m = self::readManifest($f);
            if ($m) $out[$m['slug']] = $m + ['dir' => dirname($f)];
        }
        ksort($out);
        return $out;
    }

    /** Rows of installed plugins: slug => row. */
    public function installed(): array
    {
        return $this->installed ??= array_column($this->db->rows('SELECT * FROM {p}plugins'), null, 'slug');
    }
    public function isEnabled(string $slug): bool { return !empty($this->installed()[$slug]['enabled']); }

    public static function readManifest(string $file): ?array
    {
        $m = json_decode((string)@file_get_contents($file), true);
        if (!is_array($m) || !preg_match('/^[a-z][a-z0-9_-]{1,40}$/', (string)($m['slug'] ?? '')) || !preg_match('/^\d+\.\d+\.\d+/', (string)($m['version'] ?? ''))) return null;
        return $m;
    }

    /** Text of a manifest field that may be a string or {"en":..,"ru":..}. */
    public static function text(array $m, string $field): string
    {
        $v = $m[$field] ?? '';
        return is_array($v) ? (string)($v[I18n::lang()] ?? $v['en'] ?? reset($v) ?: '') : (string)$v;
    }

    /** Register translations and run plugin.php of every enabled plugin. A broken plugin is skipped, never fatal. */
    public function boot(): void
    {
        foreach ($this->available() as $slug => $m) {
            if (!$this->isEnabled($slug)) continue;
            try {
                I18n::addDir($slug, $m['dir'] . '/lang');
                $entry = $m['dir'] . '/plugin.php';
                if (!is_file($entry)) continue;
                $fn = require $entry;
                if (is_callable($fn)) $fn(new Plugin($slug, $m, $m['dir'], $this->app));
            } catch (\Throwable $e) { App::log("plugin $slug failed to boot: " . $e->getMessage()); }
        }
    }

    /** Capabilities declared by all enabled plugins: cap => label. */
    public function capabilities(): array
    {
        $caps = [];
        foreach ($this->available() as $slug => $m) {
            if (!$this->isEnabled($slug)) continue;
            foreach ((array)($m['capabilities'] ?? []) as $cap => $label) $caps[$cap] = is_array($label) ? ($label[I18n::lang()] ?? $label['en'] ?? $cap) : (string)$label;
        }
        return $caps;
    }

    /** Checks requires:{core:">=0.1.0", plugins:{crm:">=1.0.0"}}. Returns a list of problems (empty = ok). */
    public function problems(array $m): array
    {
        $p = [];
        $core = $m['requires']['core'] ?? null;
        if ($core && !self::satisfies(SF_VERSION, (string)$core)) $p[] = t('plugins.need_core', ['v' => $core]);
        foreach ((array)($m['requires']['plugins'] ?? []) as $slug => $c) {
            $row = $this->installed()[$slug] ?? null;
            if (!$row || !$row['enabled']) $p[] = t('plugins.need_plugin', ['name' => $slug]);
            elseif (!self::satisfies((string)$row['version'], (string)$c)) $p[] = t('plugins.need_plugin_v', ['name' => $slug, 'v' => $c]);
        }
        return $p;
    }

    public static function satisfies(string $version, string $constraint): bool
    {
        foreach (preg_split('/\s*,\s*/', trim($constraint)) as $c) {
            if (!preg_match('/^(>=|<=|>|<|=|==)?\s*(\d[\w.\-]*)$/', $c, $m)) return false;
            if (!version_compare($version, $m[2], ($m[1] ?? '') === '' ? '>=' : $m[1])) return false;
        }
        return true;
    }

    /** Install = run the plugin's migrations, remember it, enable it. @throws \RuntimeException */
    public function install(string $slug): void
    {
        $m = $this->available()[$slug] ?? throw new \RuntimeException(t('plugins.not_found'));
        if ($problems = $this->problems($m)) throw new \RuntimeException(implode(' ', $problems));
        (new Migrator($this->db))->run($m['dir'] . '/migrations', "plugin:$slug:");
        $this->db->q('INSERT INTO {p}plugins (slug, version, enabled) VALUES (?,?,1) ON DUPLICATE KEY UPDATE version = VALUES(version), enabled = 1', [$slug, $m['version']]);
        $this->installed = null;
        Hooks::do('plugin.installed', $slug);
    }

    public function enable(string $slug): void { $this->install($slug); }   // also applies migrations added by a newer version
    public function disable(string $slug): void
    {
        foreach ($this->installed() as $s => $row) {
            $m = $this->available()[$s] ?? null;
            if ($row['enabled'] && $s !== $slug && in_array($slug, array_keys((array)($m['requires']['plugins'] ?? [])), true)) throw new \RuntimeException(t('plugins.used_by', ['name' => $s]));
        }
        $this->db->q('UPDATE {p}plugins SET enabled = 0 WHERE slug = ?', [$slug]);
        $this->installed = null;
        Hooks::do('plugin.disabled', $slug);
    }

    /** Removes the plugin registration. Data tables are NOT dropped (reinstalling brings everything back). Files are removed only if $files. */
    public function uninstall(string $slug, bool $files = false): void
    {
        $this->disable($slug);
        $this->db->q('DELETE FROM {p}plugins WHERE slug = ?', [$slug]);
        if ($files) self::rrmdir(self::dir() . '/' . $slug);
        $this->installed = null;
    }

    /** Unpack a plugin zip (plugin.json in the root or in a single top folder) into /plugins/<slug>. Returns the slug. */
    public function extractZip(string $zipFile): string
    {
        if (!class_exists(\ZipArchive::class)) throw new \RuntimeException(t('upd.need_zip'));
        $z = new \ZipArchive();
        if ($z->open($zipFile) !== true) throw new \RuntimeException(t('plugins.bad_zip'));
        $root = null;
        for ($i = 0; $i < $z->numFiles; $i++) {
            $n = $z->getNameIndex($i);
            if (preg_match('~^(?:([^/]+)/)?plugin\.json$~', $n, $mm)) { $root = $mm[1] ?? ''; break; }
        }
        if ($root === null) { $z->close(); throw new \RuntimeException(t('plugins.bad_zip')); }
        $manifest = json_decode((string)$z->getFromName(($root !== '' ? "$root/" : '') . 'plugin.json'), true);
        $slug = (string)($manifest['slug'] ?? '');
        if (!preg_match('/^[a-z][a-z0-9_-]{1,40}$/', $slug)) { $z->close(); throw new \RuntimeException(t('plugins.bad_zip')); }
        $tmp = SF_ROOT . '/storage/tmp/plug-' . bin2hex(random_bytes(4));
        @mkdir($tmp, 0755, true);
        for ($i = 0; $i < $z->numFiles; $i++) {   // unpack file by file: refuse anything that would leave the folder
            $n = $z->getNameIndex($i);
            if ($root !== '' && !str_starts_with($n, "$root/")) continue;
            $rel = $root !== '' ? substr($n, strlen($root) + 1) : $n;
            if ($rel === '' || str_contains($rel, '..') || str_starts_with($rel, '/') || str_contains($rel, '\\')) continue;
            if (str_ends_with($n, '/')) { @mkdir("$tmp/$rel", 0755, true); continue; }
            @mkdir(dirname("$tmp/$rel"), 0755, true);
            file_put_contents("$tmp/$rel", $z->getFromIndex($i));
        }
        $z->close();
        $dest = self::dir() . '/' . $slug;
        $old = $dest . '.old-' . bin2hex(random_bytes(3));
        if (is_dir($dest)) rename($dest, $old);
        if (!@rename($tmp, $dest)) { if (is_dir($old)) rename($old, $dest); self::rrmdir($tmp); throw new \RuntimeException(t('plugins.write_failed')); }
        if (is_dir($old)) self::rrmdir($old);
        return $slug;
    }

    public static function rrmdir(string $d): void
    {
        if (!is_dir($d)) return;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($d, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($d);
    }
}
