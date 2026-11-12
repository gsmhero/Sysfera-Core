# Architecture

## Principles
1. **Core + components.** The core owns identity (users, roles), the shared business records (clients, orders, invoices), language, plugin loading and updates. Everything else is a component.
2. **Shared records.** A client created in CRM is the same row the Warehouse and Accounting see. Components add their own tables, never copies of these.
3. **Stand-alone or together.** A component declares what it needs in `plugin.json` (`requires`). With only the core it works on shared clients/orders; with others installed it cooperates through hooks.
4. **Data is sacred.** Updates replace program files only. Migrations are additive. Uninstalling a component never drops its tables.
5. **Hosting-friendly.** PHP + MySQL, no Composer, no CLI, no build step on the server.

## Request flow
```
index.php → core/app/bootstrap.php (autoload, helpers)
          → App::run()
              not installed?  → /install.php
              load storage/config.php, connect DB
              maintenance.flag? → 503 page
              core_version ≠ files? → run core migrations (self-healing after an FTP upload)
              session, language (user → cookie → default), time zone
              routes.php (core pages)  →  Plugins::boot() (each enabled plugin.php registers routes/menu/hooks)
              Router::match → auth / capability / CSRF checks → handler → View (layout)
```

## Folders
| Path | Role | Touched by update? |
|---|---|---|
| `core/app` | classes, routes, migrations | replaced |
| `core/views`, `core/lang`, `core/assets` | templates, translations, css/js | replaced |
| `core/plugins/<slug>` | components | replaced only for that component, on its own update |
| `core/storage` | `config.php`, `installed.lock`, `backups/`, `tmp/`, `error.log` | never |
| `index.php`, `.htaccess` | entry point | never (kept stable on purpose) |

## Classes (namespace `Sysfera`)
| Class | Purpose |
|---|---|
| `App` | kernel, settings, menu, shared repositories, document numbers |
| `Db` | PDO wrapper; `{p}` in SQL = table prefix |
| `Router` | `/path/{param}` and `{rest*}` routes |
| `Hooks` | `on / do / filter` events shared by core and plugins |
| `I18n` | `lang/<code>.php` files; plugin keys are `slug.key` |
| `Auth` | login, sessions, roles (`admin`, `user`) and capabilities |
| `Plugins` / `Plugin` | discovery, install/enable/disable, dependency checks / the API a plugin receives |
| `Repo` / `Documents` | generic access to contacts, orders (+lines), invoices (+lines) with events |
| `Migrator` | statement-by-statement SQL migrations that can resume |
| `Updater` | manifest, download, verify, backup, replace, migrate, roll back |
| `View` | PHP templates inside the shared layout |

## Security notes
- Passwords: `password_hash`. Sessions: HttpOnly, SameSite=Lax, regenerated on login. Every POST needs a CSRF token.
- `core/` is blocked from the web (`.htaccess`); `storage/` additionally denies everything.
- Updates must come over HTTPS, are checked against a SHA-256 from the manifest and, if `core/app/update_key.php` holds a public key, an Ed25519 signature.
- Plugin zips are unpacked file by file; paths containing `..` are refused.
- Components are PHP code: install only components you trust.

## Roadmap hooks already in place
Roles are `admin` + capability-limited `user`. Multi-tenant separation uses `owner_id` on shared records (the field exists; components decide how to use it).
