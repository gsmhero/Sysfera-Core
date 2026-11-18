<?php
declare(strict_types=1);
namespace SysferaContactlog;

/** The only door to the data. Logical tables (without the {p}cl_ prefix): cards, logs, profiles. Same contract as the other plugins' stores. */
interface Store
{
    public function insert(string $table, array $row): int;
    public function update(string $table, int $id, array $row): void;
    public function delete(string $table, int $id): void;
    public function find(string $table, array $where = [], string $order = 'id', int $limit = 5000): array;
    public function one(string $table, array $where): ?array;
    public function atomic(callable $fn): mixed;
}
