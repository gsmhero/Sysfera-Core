# Sysfera WMS → Basebase (connector)

Separate component `wms-basebase` (requires `wms` ≥ 0.1.1 and core ≥ 0.1.2). It turns a Sysfera install into the **partner site** of Basebase Partner API v1 (`docs/BASEBASE-SYSFERA-API-v1.md` in the Basebase repo).

## Connect
1. Open *Basebase → Connection**: the page shows your **AGENT_KEY** (generated automatically). In Basebase: Cabinet → Partner site — enter this site address (`https://your-site`) and the AGENT_KEY; wait for admin approval.
2. Copy **Agent ID**, **API address** and **API secret** into *Basebase → Connection* and press *Test connection* (signed `GET /ping`, also loads the category list).
3. Create a seller on Basebase from the same page (`POST /sellers`) or enter an existing **Seller ID**.
4. Choose the warehouse whose *available* stock is sold. Commissions are not set here: the connector sends the selling price, and Basebase applies its own commission, Agency fee and blogger rules.

## Data flow
| Direction | What | How |
|---|---|---|
| WMS → Basebase | Products ticked in *Catalog* (title, description, category, price, stock, SKU, barcode/weight as specs, up to 8 https photos) | `PUT /sellers/{id}/products/{external_id}` |
| WMS → Basebase | Stock after **every posted/unposted document** (only when the number changed) | `PATCH …/stock` (404 → full product push) |
| WMS → Basebase | Un-ticked or archived product | `DELETE …/products/{external_id}` (hidden) |
| Basebase → WMS | New order item | webhook `order.created` (+ polling `GET /orders?since_id=`) → customer order **SO…** is created and **posted** (reserve); buyer saved as a shared contact with role *customer* |
| WMS → Basebase | Status `prepare` when the order is accepted; `shipped` when the shipment document covering the whole order is posted | `PATCH /orders/{item_id}` |
| Basebase → WMS | `order.status_changed` → `returned`/`cancelled` releases the reserve (if nothing shipped yet) | webhook |

One Basebase order item = one WMS customer order (`source = basebase`, `ref = item:<id>`). Orders that cannot be created (unknown SKU, not enough stock) stay visible in *Orders* with the reason and a **Retry** button.

## Endpoints on the site
- `POST /api/basebase/webhook` — verifies `X-BB-Agent`, `X-BB-Timestamp` (±5 min) and the HMAC signature; idempotent by `event_id`; replies 2xx only when handled, otherwise Basebase retries.
- `GET /api/basebase/poll?token=…` — for the hosting's cron (every 5–10 min): pulls missed orders and resends products flagged dirty. The token is shown on the Connection page. The Overview page also polls lazily every 5 minutes.

## Notes
- The API secret is kept in the settings table in plain text (as other credentials in v1); use HTTPS and a strong admin password.
- Core 0.1.2 adds `Plugin::api()` for public machine endpoints; WMS 0.1.1 adds the `wms.doc_changed` hook and the `wms.services` filter that this connector uses.
- Tests: `php tests/wmsbb.php` (signing, id/price/stock mapping, payloads).
