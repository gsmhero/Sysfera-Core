# Data model

All table names carry the install's prefix (default `sf_`). Components add tables named `<prefix><slug>_<name>`.

## Core tables
| Table | Purpose | Key columns |
|---|---|---|
| `users` | accounts | `email` (unique), `name`, `password_hash`, `role` (`admin`/`user`), `status`, `caps` (JSON list), `lang`, `last_login` |
| `settings` | key/value store, also document counters (`counter.order`) and component settings (`plugin.<slug>.<key>`) | `k`, `v` |
| `plugins` | installed components | `slug`, `version`, `enabled` |
| `events` | shared audit log | `user_id`, `plugin`, `type`, `data` (JSON) |
| `migrations` | every SQL statement already run (`core` files and `plugin:<slug>:<file>#n`) | `name` |

## Shared records
| Table | Purpose | Key columns |
|---|---|---|
| `contacts` | clients, suppliers, candidates — any party | `type` (person/company), `name`, `email`, `phone`, `company`, `address`, `notes`, `source`, `ref`, `custom` (JSON), `owner_id` |
| `orders` | sales / purchase / work orders | `number` (ORD-000001), `contact_id`, `status`, `currency`, `total`, `source`, `ref`, `meta` (JSON), `owner_id` |
| `order_lines` | | `order_id`, `title`, `sku`, `qty`, `price`, `ref`, `meta` |
| `invoices` | | `number` (INV-000001), `order_id`, `contact_id`, `status`, `currency`, `amount`, `issued_at`, `due_at`, `paid_at`, `source`, `meta`, `owner_id` |
| `invoice_lines` | | `invoice_id`, `title`, `sku`, `qty`, `price`, `ref`, `meta` |

### Conventions
- **`source`** = slug of the component that created the row; **`ref`** = that component's own identifier. Together they let a component find "its" rows without owning the table.
- **`owner_id`** = user the row belongs to, `NULL` = shared. Not enforced by the core; components filter by it when they want per-user data.
- **`status`** is free text on purpose; each component documents its own values (suggested: orders `new, confirmed, shipped, done, cancelled`; invoices `draft, issued, paid, void`).
- **Money** is `DECIMAL(14,2)`, quantities `DECIMAL(14,3)`. Currency is a 3-letter code per document.
- **JSON fields** (`custom`, `meta`) are for small extras. Anything you filter or sum on belongs in your own table.

## Rules for migrations (core and components)
1. Additive only — new tables, new nullable columns, new indexes.
2. Never edit a released migration; add `00N_*.sql`.
3. No data deletion or rewriting of rows you do not own.
