# Sysfera WMS

Warehouse manager component (`core/plugins/wms`, slug `wms`). Russian and English, rouble only in v1.

## What it does
- **Products** with groups, SKU, barcode, prices, VAT, minimum stock; archive instead of delete.
- **Warehouses** with optional zones and cells (racks/shelves). Cells are mandatory in documents only for warehouses that enable them.
- **Stock ledger**: every posted document writes rows to `wms_moves` (`on_hand`, `reserve`, `expected` per product/warehouse/cell). Available = on hand − reserve. Nothing is edited in place, so history is always complete.
- **Documents** (draft → posted → cancelled): receipt, write-off, transfer, inventory; supplier order + receiving; customer order + shipment. Orders reserve/expect goods; receiving/shipment are created from the order and track the open quantity. Inventory books only the difference to the system quantity.
- **Reports**: stock, low stock, turnover (opening / in / out / closing) with CSV export.
- **Partners**: suppliers and customers are the shared Sysfera contacts (role stored in the contact's `custom.roles`).
- **Activity log** (events of plugin `wms`) and a setting to forbid negative stock (on by default).

## Numbering
RC receipt, WO write-off, TR transfer, IV inventory, PO supplier order, GR receiving, SO customer order, SH shipment. Counters live in settings `wms.counter.<type>`.

## Capabilities
`wms.use` — work with the warehouse; `wms.admin` — create/edit warehouses, settings.

## Code map
`lib/Posting.php` pure posting rules (unit-tested in `tests/wms.php`), `lib/Stock.php` queries, `lib/Docs.php` document lifecycle, `plugin.php` routes, `views/`, `assets/`, `lang/`.

## Install
Components → Available → Sysfera WMS → Install (delivered through the update catalog), or upload `wms-<ver>.zip`.

## Next
`wms-basebase` — separate connector component that talks to Basebase through Partner API v1 (push stock, pull orders as customer orders with reserve, return shipment status).
