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

## Import products (xlsx / csv)
**Products → Import** loads products from an export of another system: OZON / Wildberries / Yandex Market, 1C or any table (`.xlsx`, `.csv` in UTF-8 or Windows-1251, comma / semicolon / tab).
1. **Upload file**, then **Start Import**. The reader finds the data sheet (hidden service sheets are ignored) and the header row (marketplace templates have group captions above the header and "required field" / help rows below it; reports have a title row).
2. **Fields Converter**: every column is matched automatically from its Russian or English header (SKU/Article, Product name, Barcode, Unit, Group, Images, Purchase / Selling / Promo price, Stock quantity, Minimum stock, Short description, Description). *As is* keeps a column as a product specification under its own name, *Custom* under a name you type, *Skip column* ignores it. Tick **Add to template** to remember a column's choice: a template is found again by the set of the file's headers (exact match, or ≥ 70 % of the saved headers) and applied automatically next time. Templates are per user (`wms_import_templates`).
3. **Finish Import** shows a preview (new / updated / rows with errors / notes, first rows, errors with row numbers), then **Import** writes the products.
- Products are matched by SKU, then barcode, then (rows with neither) exact name, within your own products; the same file imported twice updates instead of duplicating. Only the fields present in the file are changed. A SKU or barcode repeated inside the file is reported and skipped.
- Images given as links are stored as links (https only, up to 8 per product), exactly like the product card does.
- Quantities: *Receive opening stock for new products* (default) posts one receipt with the new products; *Set stock to the file* also posts an inventory count for the existing ones; *Ignore quantities* leaves stock alone. Stock always goes through a posted document, so history stays complete. The warehouse must be one without cells.
- Limits: 12 MB, 2000 product rows per import, 60 specifications and 8 images per product.
- Code: `lib/SheetReader.php` (dependency-free xlsx/csv reader), `lib/ImportKit.php` (header detection, mapping, templates; both are copies of the same files in Basebase `src/import/` — keep them in sync), `lib/ImportWms.php` (field catalog, validation, writes), `lib/ImportFlow.php` (the screens' controller), `views/import.php`. Tests: `php tests/wmsimport.php`.

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
