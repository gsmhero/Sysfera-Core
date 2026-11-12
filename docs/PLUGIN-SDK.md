# Component (plugin) SDK

A component is a folder in `core/plugins/<slug>/`. Start by copying `core/plugins/hello`.

```
plugins/crm/
  plugin.json        manifest (required)
  plugin.php         returns function (\Sysfera\Plugin $sf) { … }   (required to do anything)
  migrations/        001_init.sql, 002_….sql   additive SQL, run on install/update
  lang/              en.php, ru.php, …         keys used as "crm.key"
  views/             PHP templates
  assets/            css/js/images served at /assets/p/crm/…
```

## plugin.json
```json
{
  "slug": "crm",
  "version": "1.0.0",
  "name":        { "en": "CRM", "ru": "CRM" },
  "description": { "en": "Clients and deals", "ru": "Клиенты и сделки" },
  "requires": { "core": ">=0.1.0", "plugins": { "warehouse": ">=1.0.0" } },
  "capabilities": { "crm.use": { "en": "CRM", "ru": "CRM" }, "crm.manage": { "en": "CRM: manage", "ru": "CRM: управление" } }
}
```
`slug`: `a-z 0-9 _ -`, starts with a letter. `version`: `x.y.z`. `name`/`description` may be plain strings.
Capabilities appear as checkboxes on the Users page; administrators always have all of them.

## plugin.php — the `$sf` object
```php
return function (\Sysfera\Plugin $sf): void {
    $sf->menu('menu', '/', ['cap' => 'crm.use', 'order' => 20, 'icon' => 'users']);   // label = lang key "crm.menu"
    $sf->route('*', '/', fn() => $sf->render('index', ['rows' => …]), ['cap' => 'crm.use']);   // /p/crm/
    $sf->route('POST', '/save', function () use ($sf) { … redirect('/p/crm/'); }, ['cap' => 'crm.manage']);
    $sf->route('GET', '/api/ping', fn() => print('ok'), ['auth' => false]);                   // public page
};
```
| Method | Purpose |
|---|---|
| `route($method, $path, $fn, $opt)` | page under `/p/<slug>/`; `$method` may be `*`; `$opt`: `cap`, `auth` (false = public), `csrf` (false = no token check) |
| `menu($labelKey, $path, $opt)` | sidebar entry; `$opt`: `cap`, `order` (core items use 0, 90–93), `icon` (`home puzzle users cog refresh`) |
| `render($view, $vars, $title)` | template `views/<view>.php` inside the layout; `$sf` is available in it |
| `t($key, $vars)` | translate `slug.key` |
| `db()` | `Db`: `q`, `rows`, `row`, `val`, `insertId`; write `{p}` for the prefix |
| `contacts()`, `orders()`, `invoices()` | shared records, see below |
| `on($hook, $fn, $priority)` | listen to events |
| `can($cap)`, `auth()` | current user checks |
| `setting($k)`, `setSetting($k, $v)` | plugin-private settings |
| `url($path)`, `asset($file)` | links |
| `log($type, $data)` | write to the shared `events` log |

Helpers in templates: `e()` (escape), `t()`, `url()`, `csrf_field()` (**every POST form needs it**), `flash($msg, 'ok|bad|warn')`, `redirect($path)`, `ago($datetime)`.

## Shared records
```php
$cid = $sf->contacts()->create(['name' => 'Acme', 'email' => 'a@b.c', 'source' => 'crm', 'ref' => 'lead-17']);
$sf->contacts()->list(['email' => 'a@b.c']);   $sf->contacts()->search('acm', ['name', 'email', 'company']);
$sf->contacts()->update($cid, ['phone' => '+7…']);   $sf->contacts()->delete($cid);

$oid = $sf->orders()->createWithLines(['contact_id' => $cid, 'currency' => 'RUB', 'source' => 'warehouse'],
        [['title' => 'Widget', 'sku' => 'W-1', 'qty' => 2, 'price' => 490.00]]);   // number ORD-000001 + total are automatic
$sf->orders()->lines($oid);   $sf->invoices()->createWithLines(['order_id' => $oid, 'contact_id' => $cid, 'status' => 'issued'], $lines);
```
`source`/`ref` say who created a record and let that component find it again. Component-specific data goes in the `custom` (contacts) / `meta` (orders, invoices) JSON field **or** in the component's own table keyed by the record id — prefer your own table for anything you query.

## Hooks
| Hook | Arguments | Type |
|---|---|---|
| `contact.created / updated / deleted` | `$id, $row` | event |
| `order.created / updated / deleted` | `$id, $row` | event |
| `invoice.created / updated / deleted` | `$id, $row` | event |
| `user.registered`, `user.login` | `$userId` | event |
| `plugin.installed`, `plugin.disabled` | `$slug` | event |
| `app.booted` | `$app` | event |
| `dashboard.widgets` | `array $html` | filter — return the array with your HTML added |
| `menu.items` | `array $items` | filter |

Example — Accounting issues an invoice whenever the Warehouse ships an order (the two never call each other directly):
```php
$sf->on('order.updated', function (int $id, array $o) use ($sf) {
    if ($o['status'] === 'shipped' && !$sf->invoices()->list(['order_id' => $id], 1)) $sf->invoices()->createWithLines(…);
});
```
Components can define **their own hooks** with `\Sysfera\Hooks::do('warehouse.stock.low', $sku)` — document them in your component's README.

## Migrations
- Files `migrations/NNN_name.sql`, statements separated by `;` + newline, table names `{p}<slug>_<name>`.
- **Additive only**: `CREATE TABLE IF NOT EXISTS`, `ALTER TABLE … ADD COLUMN`. Never `DROP`/`TRUNCATE`/`UPDATE` all rows. Users trust updates because of this rule.
- Each statement is remembered, so an interrupted update resumes. Never edit a released migration — add a new file.

## Translations
`lang/en.php` returns `['key' => 'text {name}']`; add `ru.php` etc. Missing keys fall back to English. Keys are used as `$sf->t('key')` or `t('slug.key')`. Manifest `name/description` can be `{ "en": "…", "ru": "…" }`.

## Packaging & updating
`php tools/build.php` zips every folder in `core/plugins` into `dist/updates/plugins/<slug>-<version>.zip` and lists it in `manifest.json`. Bump `version` in `plugin.json` to ship an update; the Components page shows an *Update* button, runs the new migrations and keeps all data.
Third-party components can also be uploaded as a `.zip` on the Components page.

## Checklist before releasing a component
- [ ] every POST form has `csrf_field()`, every output goes through `e()`
- [ ] every page has a `cap` (or is intentionally public)
- [ ] all texts are in `lang/en.php` (+ `ru.php`)
- [ ] migrations only add; version bumped
- [ ] works when optional neighbour components are absent
