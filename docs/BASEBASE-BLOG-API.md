# Basebase Partner API v1: blog and sets (extension)

Status: **implemented on both sides** (Sysfera: My Blog; Basebase: `src/api_blog.php`).
A Basebase without these addresses returns `404 {"error":"not_found","message":"Unknown endpoint."}`; My Blog then keeps the posts, shows "Basebase does not accept posts yet" and tries again later.

Base address, signature headers and errors are those of `docs/BASEBASE-SYSFERA-API-v1.md` (sections 3 and 4). All paths are under `/sellers/{seller_id}`; the seller must belong to the calling Agent (as for products).

## Posts (`posts`, `post_items`)

| Method & path | Purpose |
|---|---|
| `GET /posts` | All posts of the seller's shop, drafts too → `{ "posts": [Post], "complete": true }`. `complete` is `true` only when the list is the whole blog; if Basebase ever cuts the list, it must send `false` (Sysfera then removes nothing). |
| `POST /posts` | Create. Idempotent by `external_id` (an existing one is updated and returned). → `201 { "post_id", "url", "updated_at" }` |
| `PUT /posts/{post_id}` | Replace title, body, status, product attachments, cover. → `200 { "post_id", "url", "updated_at" }`; `404 {"message":"Post not found."}` if it is gone or belongs to another shop. |
| `DELETE /posts/{post_id}` | Delete. → `200 {}`; `404 Post not found.` is also accepted. |

Request body of `POST` / `PUT`:

```json
{ "external_id": "sf-1a2b3c4d-12", "title": "…", "body": "plain text", "status": "published|draft",
  "products": [15, 18], "cover_url": "https://site.example/p/myblog/cover/<32 hex>.jpg", "remove_cover": true }
```

* `products` are Basebase product ids. Only products the shop may attach (own or collected, `status = active`) are accepted; others are skipped.
* **Set and group attachments** of the post (`post_items.kind` = `set` / `group`) are not part of this request and must be kept as they are.
* `cover_url`: download it as product images are downloaded (`api_fetch_image`), only when the address changed. `remove_cover: true` removes the cover. Neither field present = leave the cover alone.
* Limits as in the cabinet: title ≤ 255; body ≤ 20000.

`Post` in the answer of `GET /posts`:

```json
{ "post_id": 7, "external_id": "sf-1a2b3c4d-12", "title": "…", "body": "…", "status": "published",
  "products": [ { "product_id": 15 } ], "cover_url": "https://www.basebase.ru/uploads/ab.jpg",
  "url": "https://www.basebase.ru/blog/7", "created_at": "2026-11-26T10:00:00+03:00", "updated_at": "2026-11-26T10:00:00+03:00" }
```

* `cover_url` and `url` must be absolute `https` addresses on basebase.ru.
* `updated_at` must change on every edit, in the cabinet too: Basebase keeps it in `posts.updated_at` (set by the API and the cabinet; reading a post does not change it)
  and answers `COALESCE(updated_at, created_at)`; `external_id` is unique per shop.

## Sets

| Method & path | Purpose |
|---|---|
| `GET /sets` | `{ "sets": [ { "set_id": 3, "title": "…", "items": 4, "sum": 1250.0, "url": "https://www.basebase.ru/set/3" } ] }` — shown on the Sets tab of the author's Sysfera profile. |

## How Sysfera synchronises

1. Local changes first (`DELETE`, `POST`, `PUT`), then `GET /posts`.
2. A post changed on Sysfera and not yet sent wins. Otherwise a changed `updated_at` brings the Basebase version in.
3. A synchronised post missing from a `complete` list is deleted on Sysfera.
4. Runs when the user saves or deletes a post, presses "Synchronise now", or opens My Blog (at most every 5 minutes).
