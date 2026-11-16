<?php
declare(strict_types=1);
namespace SysferaGi;

/**
 * What the exchange engine needs from storage. DbStore is the real one (MySQL); the tests use an in-memory copy.
 * Arrays are plain rows: transfer, order, item, doc, code, incident, report (see migrations/001_goodsissue.sql).
 */
interface Store
{
    public function transfer(string $uuid): ?array;
    public function saveTransfer(array $t): void;
    /** @return list<array> items of a transfer */
    public function items(string $transferUuid): array;
    /** @param list<array> $items new items; an item with an 'id' is updated */
    public function saveItems(string $transferUuid, array $items): void;
    public function order(string $uuid): ?array;
    public function saveOrder(array $o): void;
    public function ordersOfTransfer(string $transferUuid): array;
    public function ordersOfWarehouse(int $warehouseId): array;
    public function orderItems(string $orderUuid): array;

    public function doc(string $uuid): ?array;
    /** @return bool false when the uuid is already taken (a parallel duplicate): the caller then returns the stored one */
    public function saveDoc(array $d): bool;
    /** @return list<array> documents of a warehouse of a type; $day = 'Y-m-d' (server day) or '' for all */
    public function docs(int $warehouseId, string $type, string $day = ''): array;

    public function code(string $orderUuid): ?array;
    public function saveCode(array $c): void;

    /** appends to the audit chain; returns the stored row */
    public function audit(array $e): array;

    public function addIncident(array $i): int;
    public function openIncident(string $kind, string $ref): ?array;
    public function closeIncident(int $id, string $by, string $note): void;
    public function report(int $warehouseId, string $day): ?array;
    public function saveReport(array $r): void;
}
