<?php
declare(strict_types=1);
namespace Sysfera;

/**
 * Runs *.sql files from a folder, statement by statement ("; + newline" separates statements), and remembers every
 * statement it ran. Re-running is safe: finished statements are skipped, so an interrupted update resumes where it stopped.
 * Rule for authors: migrations are ADDITIVE (new tables/columns). Never drop or rewrite user data in a migration.
 */
final class Migrator
{
    public function __construct(private Db $db) {}

    public function ensureTable(): void
    {
        $this->db->q('CREATE TABLE IF NOT EXISTS {p}migrations (name VARCHAR(190) NOT NULL PRIMARY KEY, applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    /** @return int number of statements applied */
    public function run(string $dir, string $keyPrefix = ''): int
    {
        $this->ensureTable();
        $files = glob(rtrim($dir, '/') . '/*.sql') ?: [];
        sort($files);
        $applied = 0;
        foreach ($files as $f) {
            $sql = str_replace("\r\n", "\n", (string)file_get_contents($f));
            $parts = array_values(array_filter(array_map('trim', preg_split('/;\s*\n/', $sql . "\n") ?: []), fn($s) => $s !== '' && !preg_match('/^(--[^\n]*\n?)+$/', $s)));
            foreach ($parts as $i => $stmt) {
                $key = $keyPrefix . basename($f) . '#' . ($i + 1);
                if ($this->db->val('SELECT 1 FROM {p}migrations WHERE name = ?', [$key])) continue;
                try { $this->db->pdo()->exec($this->db->sql($stmt)); }
                catch (\Throwable $e) { throw new \RuntimeException(basename($f) . ' statement ' . ($i + 1) . ': ' . $e->getMessage(), 0, $e); }
                $this->db->q('INSERT INTO {p}migrations (name) VALUES (?)', [$key]);
                $applied++;
            }
        }
        return $applied;
    }
}
