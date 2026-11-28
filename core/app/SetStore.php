<?php
declare(strict_types=1);
namespace Sysfera;

/** Storage of interface sets. Every call is limited to one user, so nobody can touch another user's sets. */
interface SetStore
{
    /** @return list<array{id:int, name:string, items:list<string>, active:bool}> oldest first */
    public function all(int $user): array;
    public function create(int $user, string $name): int;
    /** @param list<string> $items */
    public function update(int $user, int $id, string $name, array $items): void;
    public function delete(int $user, int $id): void;
    /** Makes this set the only active one; 0 = none. */
    public function activate(int $user, int $id): void;
    public function deleteUser(int $user): void;
}
