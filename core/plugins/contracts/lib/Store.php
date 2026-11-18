<?php
declare(strict_types=1);
namespace SysferaContracts;

/**
 * The only door to the data. Logical table names (without the {p}ct_ prefix): profiles, categories, contracts, todos, offers, offer_todos,
 * offer_extra, comments, files, reviews, watch, invoices, notices, psections, pfields, pvalues.
 * $where: column => value (equality), column => [v1, v2] (IN), column => null (IS NULL). $order: "col" or "col DESC".
 * The rules in Svc run against any Store: the database one (DbStore) in production and an in-memory one in the tests.
 */
interface Store
{
    public function insert(string $table, array $row): int;
    public function update(string $table, int $id, array $row): void;
    public function delete(string $table, int $id): void;
    public function find(string $table, array $where = [], string $order = 'id', int $limit = 5000): array;
    public function one(string $table, array $where): ?array;
    public function atomic(callable $fn): mixed;
}
