# Sysfera

**Sysfera** is a small, installable management platform: a **core** (users, clients, orders, invoices, languages, updates)
and **components** (plugins) you add as needed — Warehouse Manager, CRM, Accounting, Vacancy Manager, or your own.
All components share the same clients, orders and invoices, and each can also be used on its own.

- Plain **PHP 8.1+ / MySQL**, no framework, no build step — runs on ordinary shared hosting.
- Installs on **any domain or sub-domain** in about two minutes, WordPress-style, with a hint at every step.
- **English and Russian** out of the box; add a language by copying one file.
- **Updates without data loss**: only program files are replaced, a backup is made first, a failed update rolls back.

## Install (2 ways)

**A. One file** — upload `sysfera-install.php` (from [Releases](../../releases) or `dist/`) to the domain folder by FTP, open it in the browser, press *Unpack and continue*, then follow the wizard.

**B. Zip** — unzip `sysfera-<version>.zip` into the domain folder (or upload by FTP), open `https://your-domain/install.php`.

You need: PHP 8.1+ (`pdo_mysql`, `mbstring`, `json`; `zip` for auto-updates) and an empty MySQL database. Details: [docs/INSTALL.md](docs/INSTALL.md).

## Repository layout

```
index.php            entry point (never changes between versions)
install.php          installation wizard (delete after install)
.htaccess            pretty URLs; blocks /core from the web
core/
  app/               the platform code (namespace Sysfera)  ← replaced by updates
  views/ lang/ assets/                                      ← replaced by updates
  plugins/           components (one folder each)           ← kept by updates
  storage/           config, backups, logs, uploads         ← never touched by updates
tools/build.php      builds zip, single-file installer, update packages, manifest
tests/run.php        tests that need no database
docs/                architecture, plugin SDK, data model, updates, deploy
```

## Develop

```bash
php tests/run.php     # tests (no database needed)
php tools/build.php   # build everything into dist/
```

Write a component: copy `core/plugins/hello`, edit `plugin.json` — see [docs/PLUGIN-SDK.md](docs/PLUGIN-SDK.md).

## Docs

[INSTALL](docs/INSTALL.md) · [ARCHITECTURE](docs/ARCHITECTURE.md) · [PLUGIN-SDK](docs/PLUGIN-SDK.md) · [DATA-MODEL](docs/DATA-MODEL.md) · [UPDATES](docs/UPDATES.md) · [DEPLOY](docs/DEPLOY.md)
