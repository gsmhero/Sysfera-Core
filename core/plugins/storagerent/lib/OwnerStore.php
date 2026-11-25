<?php
declare(strict_types=1);
namespace SysferaStoragerent;

/**
 * Keeps the data of every owner apart. A user without a staff role manages "their own" storages: for them (scope = user id) every read of the owned tables is limited to the rows
 * stamped with that user, and every write is refused on a row of somebody else (reported as "not found", so nothing leaks). Staff (scope 0) see everything, as before.
 * In both modes a new row is stamped with its owner: the scoped user, or - for the daily run, payments of tenants, etc. - the owner of the parent row (tenant, contract, invoice, unit, warehouse).
 * Children (invoice items, allocations, contract units, versions, readings) are reached through a parent that has already been looked up through this store.
 */
final class OwnerStore implements Store
{
    /** owned tables => the column that holds the owner */
    public const COLS = ['warehouses' => 'owner_id', 'units' => 'own_uid', 'tenants' => 'own_uid', 'contracts' => 'own_uid', 'invoices' => 'own_uid', 'payments' => 'own_uid',
        'meters' => 'own_uid', 'documents' => 'own_uid', 'access_log' => 'own_uid', 'requests' => 'own_uid'];
    /** columns that point to an owned parent, the first one that is set decides who owns a new row */
    private const PARENTS = ['tenant_id' => 'tenants', 'contract_id' => 'contracts', 'invoice_id' => 'invoices', 'unit_id' => 'units', 'warehouse_id' => 'warehouses'];
    private const DOC_PARENTS = ['tenant' => 'tenants', 'contract' => 'contracts', 'invoice' => 'invoices', 'unit' => 'units', 'warehouse' => 'warehouses', 'meter' => 'meters'];

    /** $legacy: also the rows of the site itself (owner 0, made before owners existed): the platform administrator manages them */
    public function __construct(private Store $in, private int $scope = 0, private bool $legacy = false) {}
    private function lim(): int|array { return $this->legacy ? [$this->scope, 0] : $this->scope; }

    public function scope(): int { return $this->scope; }
    /** the store without the limit: only for checks that must see the whole site (is this user already linked to a tenant of somebody else?) */
    public function unscoped(): Store { return $this->in; }

    private function ownerOf(string $table, array $row): int
    {
        foreach (self::PARENTS as $col => $pt) {
            if (empty($row[$col])) continue;
            $p = $this->in->one($pt, ['id' => (int)$row[$col]]); if ($p) return (int)($p[self::COLS[$pt]] ?? 0);
        }
        if ($table === 'documents' && isset(self::DOC_PARENTS[(string)($row['owner_type'] ?? '')]) && !empty($row['owner_id'])) {
            $pt = self::DOC_PARENTS[$row['owner_type']]; $p = $this->in->one($pt, ['id' => (int)$row['owner_id']]); if ($p) return (int)($p[self::COLS[$pt]] ?? 0);
        }
        return 0;
    }
    public function insert(string $table, array $row): int
    {
        if ($col = self::COLS[$table] ?? null) {
            if ($this->scope > 0) $row[$col] = $this->scope;
            elseif (!isset($row[$col])) $row[$col] = $this->ownerOf($table, $row);
        }
        return $this->in->insert($table, $row);
    }
    private function mine(string $table, int $id): void
    {
        if ($this->scope > 0 && isset(self::COLS[$table]) && !$this->in->one($table, ['id' => $id, self::COLS[$table] => $this->lim()])) throw new Err('not_found', '', 404);
    }
    public function update(string $table, int $id, array $row): void
    {
        $this->mine($table, $id);
        if ($this->scope > 0 && ($col = self::COLS[$table] ?? null)) unset($row[$col]);
        $this->in->update($table, $id, $row);
    }
    public function delete(string $table, int $id): void { $this->mine($table, $id); $this->in->delete($table, $id); }
    public function find(string $table, array $where = [], string $order = 'id', int $limit = 100000): array
    {
        if ($this->scope > 0 && ($col = self::COLS[$table] ?? null)) $where[$col] = $this->lim();
        return $this->in->find($table, $where, $order, $limit);
    }
    public function one(string $table, array $where): ?array
    {
        if ($this->scope > 0 && ($col = self::COLS[$table] ?? null)) $where[$col] = $this->lim();
        return $this->in->one($table, $where);
    }
    public function atomic(callable $fn): mixed { return $this->in->atomic($fn); }
}
