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
| WMS → Basebase | Products ticked in *Catalog*: name, short description, description, category, selling price, promo price, stock, SKU, up to 8 https photos, variations (warehouse products with their own stock and price), specifications (section / name / value; barcode and weight are added automatically) | `PUT /sellers/{id}/products/{external_id}` |
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

## Product structure (WMS ↔ Basebase)
The WMS product card has the same fields as the Basebase product form:

| Basebase | WMS product card |
|---|---|
| Product name | Name |
| Short description | Short description |
| Description | Description |
| Category | *Basebase → Catalog* (category is a Basebase list, chosen per product there) |
| SKU | SKU |
| Price | Selling price |
| Promo price (you receive) | Promo price |
| Stock | Warehouse available quantity; variable: sum of the variations; bundle: sets that can be made from the parts; virtual and downloadable: always available |
| Variable product | Variations picked from the warehouse: variation name (optional), own price (optional); stock comes from each product |
| Specifications | Specifications: section, name, value |
| Main image + gallery (8) | Photos: https links, first = main (the *Catalog* photo box, if filled, overrides) |
| Status Active / Draft | *Published* tick in the Catalog (unticking hides the product) |

When a buyer orders a variation, the WMS order is made for that warehouse product; a bundle is taken apart into its parts, so their stock goes down. A change of stock of a part is delivered as a full push of the variable product or bundle it belongs to. A grouped product is not sold as one item (publish its products one by one).

## Users, shops and privacy (connector 0.3, WMS 0.3, core 0.1.24)

* Everyone — the Agent (site administrator) included — works as an independent seller: **products, documents, stock figures, reports and Basebase orders are visible and editable only by their owner** (`owner_id`). Warehouses, cells and zones are shared infrastructure managed by the administrator.
* Users never connect to Basebase themselves. The administrator enters the **Agent credentials once** (Connection tab). Each user gets a Basebase seller (created through the Agent account, `external_id = sfu-<user id>`) automatically on their first published product, or from **My shop**; the shop name they set there is what buyers see on Basebase. The Agent fee on Basebase works as before.
* Orders from Basebase are matched to the user by `seller_id`; stock pushes, polling and status updates run per user.
* Users list (Core): the administrator can **block** (Suspend) a user; **Delete** only sends a request, the account is removed when the user clicks *Agree* in their profile.
* Upgrading: products and documents get their owner from `created_by`, otherwise the first administrator; the former single connected seller moves to the user who owned the connection.

## When Basebase deletes a user (connector 0.5.1)
If the Basebase administrator deletes a seller (Users manager → Delete: the shop and products are removed, the wallet moves to the admin wallet, the user disappears from the Agent's seller list), the next request for that user answers "Seller not found". The connector then forgets the seller id and marks the user's products for a new upload. The user can publish a product again or press **Create my shop now** in *My shop*: a new seller is created through the Agent account (same email, same or a new shop name) and the products are uploaded again.
