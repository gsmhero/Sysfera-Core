<?php
declare(strict_types=1);
namespace SysferaGi;

/** Demo / test data: transfer orders in the exchange format (the same JSON the head-office system sends). */
final class Seed
{
    /** fixed UUIDs so that tests and the demo can refer to them */
    public const T1 = '11111111-1111-4111-8111-111111111111';
    public const T2 = '22222222-2222-4222-8222-222222222222';
    public const O1 = 'a0000001-0000-4000-8000-000000000001';   // 2 items, small total
    public const O2 = 'a0000002-0000-4000-8000-000000000002';   // 1 item, above the ID-check threshold
    public const O3 = 'a0000003-0000-4000-8000-000000000003';   // 1 item
    public const O4 = 'a0000004-0000-4000-8000-000000000004';

    /** @return array two manifests for one warehouse: T1 (3 orders) and T2 (1 order) */
    public static function transfers(int $warehouseId): array
    {
        $it = fn(string $sku, string $name, int $qty, int $g, string $bc, string $price) => ['sku' => $sku, 'name' => $name, 'qty' => $qty, 'weight_g' => $g, 'barcode' => $bc, 'price_rub' => $price];
        return [
            ['uuid' => self::T1, 'warehouse_id' => $warehouseId, 'units' => ['weight' => 'g', 'money' => 'RUB'], 'orders' => [
                ['uuid' => self::O1, 'number' => 'A-1001', 'customer_name' => 'Ivan Petrov', 'customer_phone' => '+79990000001', 'total_rub' => '2700.00', 'items' => [
                    $it('SKU-KETTLE', 'Electric kettle', 1, 1200, 'GI4600000001', '1500.00'), $it('SKU-MUG', 'Mug', 3, 350, 'GI4600000002', '400.00')]],
                ['uuid' => self::O2, 'number' => 'A-1002', 'customer_name' => 'Anna Smirnova', 'customer_phone' => '+79990000002', 'total_rub' => '45990.50', 'items' => [
                    $it('SKU-TV', 'TV 43"', 1, 7800, 'GI4600000003', '45990.50')]],
                ['uuid' => self::O3, 'number' => 'A-1003', 'customer_name' => 'Oleg Sidorov', 'customer_phone' => '+79990000003', 'total_rub' => '990.00', 'items' => [
                    $it('SKU-LAMP', 'Desk lamp', 2, 800, 'GI4600000004', '495.00')]],
            ]],
            ['uuid' => self::T2, 'warehouse_id' => $warehouseId, 'units' => ['weight' => 'g', 'money' => 'RUB'], 'orders' => [
                ['uuid' => self::O4, 'number' => 'A-1004', 'customer_name' => 'Maria Kuznetsova', 'customer_phone' => '+79990000004', 'total_rub' => '1290.00', 'items' => [
                    $it('SKU-BAG', 'Backpack', 1, 900, 'GI4600000005', '1290.00')]],
            ]],
        ];
    }
}
