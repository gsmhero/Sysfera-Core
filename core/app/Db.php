<?php
declare(strict_types=1);
namespace Sysfera;

use PDO;

/** Thin PDO wrapper. Write `{p}` in SQL for the table prefix: `SELECT * FROM {p}users`. */
final class Db
{
    private ?PDO $pdo = null;
    public readonly string $prefix;

    public function __construct(private array $cfg)
    {
        $this->prefix = (string)($cfg['prefix'] ?? 'sf_');
    }

    public function pdo(): PDO
    {
        return $this->pdo ??= new PDO(
            'mysql:host=' . $this->cfg['host'] . ';' . (!empty($this->cfg['port']) ? 'port=' . (int)$this->cfg['port'] . ';' : '') . 'dbname=' . $this->cfg['name'] . ';charset=utf8mb4',
            (string)$this->cfg['user'], (string)$this->cfg['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
        );
    }

    public function sql(string $sql): string { return str_replace('{p}', $this->prefix, $sql); }

    public function q(string $sql, array $params = []): \PDOStatement
    {
        $st = $this->pdo()->prepare($this->sql($sql));
        $st->execute($params);
        return $st;
    }
    public function rows(string $sql, array $params = []): array { return $this->q($sql, $params)->fetchAll(); }
    public function row(string $sql, array $params = []): ?array { $r = $this->q($sql, $params)->fetch(); return $r ?: null; }
    public function val(string $sql, array $params = []): mixed { $r = $this->q($sql, $params)->fetchColumn(); return $r === false ? null : $r; }
    public function insertId(): int { return (int)$this->pdo()->lastInsertId(); }

    /** All tables that belong to this install (own prefix). */
    public function tables(): array
    {
        $like = str_replace(['_', '%'], ['\_', '\%'], $this->prefix) . '%';
        return array_column($this->pdo()->query("SHOW TABLES LIKE " . $this->pdo()->quote($like))->fetchAll(PDO::FETCH_NUM), 0);
    }
}
