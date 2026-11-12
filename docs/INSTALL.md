# Installing Sysfera

## Requirements
| | |
|---|---|
| PHP | 8.1 or newer |
| PHP extensions | `pdo_mysql`, `mbstring`, `json` (required); `zip` (for in-app updates; install works without) |
| Database | MySQL 5.7+ / MariaDB 10.3+ (one empty database) |
| Web server | Apache with `mod_rewrite` and `.htaccess` (see nginx below) |
| Folders writable | `core/storage`, `core/plugins` |

## Way A — one file (easiest)
1. Create the domain / sub-domain in your hosting panel and an **empty MySQL database + user** for it.
2. Upload **`sysfera-install.php`** into the domain folder (`public_html`, `www`, or the sub-domain's folder) by FTP.
3. Open `https://your-domain/sysfera-install.php`, press **Unpack and continue**. The file unpacks the program and deletes itself.
4. The wizard starts: *server check → database → site name and administrator → done*. Each step explains what to enter.

## Way B — zip
Unzip `sysfera-<version>.zip` into the domain folder (or upload the unzipped files by FTP) and open `https://your-domain/install.php`.

## Sub-folder
It also works in a sub-folder, e.g. `https://example.com/sysfera/` — upload there and open `…/sysfera/install.php`.

## After installing
- Delete `install.php` (the last wizard step has a button).
- Sign in → **Components** → install what you need.
- **Settings**: language, time zone, whether visitors may register (off by default).

## nginx
Apache reads `.htaccess` for you. On nginx add:
```nginx
location / { try_files $uri /index.php?$query_string; }
location ^~ /core/ { deny all; }
```

## Troubleshooting
| Symptom | Fix |
|---|---|
| Wizard says a folder is not writable | In FTP set permissions `755` (or `775`) on `core/storage` and `core/plugins`. |
| "Unknown database" | Hosting often prefixes names with your login: `login_sysfera`. |
| 404 on every page except `/` | `mod_rewrite` / `.htaccess` is off: enable *AllowOverride All*, or use the nginx rules. |
| Blank page / 500 | Look at `core/storage/error.log`; set `'debug' => true` in `core/storage/config.php` temporarily. |
| Installing a second copy | Use another database, or another **table prefix** in step 2. |
