<?php
declare(strict_types=1);
namespace SysferaHr;

/**
 * Storage port of the HR engine. Tables are named without the prefix (employees, roles, ...). Implemented by DbStore (MySQL) and by the in-memory store of the tests.
 * where: column => scalar | null | list (IN). order: "col" or "col DESC".
 * audit() appends to the hash chain; there is no update/delete of the audit anywhere.
 */
interface Store
{
    public function insert(string $table, array $row): int;
    public function update(string $table, int $id, array $row): void;
    /** physical delete: only for state tables (user_roles, team_members...); the audit, status history and consents refuse it */
    public function delete(string $table, int $id): void;
    public function find(string $table, array $where = [], string $order = 'id', int $limit = 1000): array;
    public function one(string $table, array $where): ?array;
    public function audit(array $e): array;
    /** one unit of work: all writes or none (MySQL transaction) */
    public function atomic(callable $fn): mixed;
}
