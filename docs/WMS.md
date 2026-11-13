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

## Product types
Choose the type at the top of the product card; the card shows only the fields that make sense for it.
| Type | What it is | Stock |
|---|---|---|
| **Simple** | One item. | Its own, through documents. |
| **Variable** | A card with variations; each variation is a product from the warehouse (own SKU, stock, price). Optionally a variation name and an own price. | Sum of the variations. The card cannot be put into a document: pick a variation. |
| **Grouped** | A showcase of separate products. | None. Not put into documents; not sold as one item on Basebase. |
| **Virtual** | A service or anything without physical stock. | Never limited; documents skip it. |
| **Downloadable** | Virtual plus a download link (for you; not sent to Basebase). | Never limited. |
| **Bundle** | Products sold together, with the quantity of each. The price of the set is yours. | Worked out from the parts: how many sets can be made. Selling the set takes its parts from stock. A bundle cannot be received or counted. |

Only simple products can be variations or parts of a bundle; a grouped product may also contain virtual, downloadable products and bundles. Products created before 0.2.0 stay simple; the old per-product "options" list was replaced by variations from the warehouse.
