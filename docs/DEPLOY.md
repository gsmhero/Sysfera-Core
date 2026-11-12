# Deploying this repository to sysfera.ru

Pushing to `main` runs `.github/workflows/deploy.yml`: tests → build → upload. It does nothing until the secrets below exist.

## Secrets (Settings → Secrets and variables → Actions)
| Secret | Example | Needed for |
|---|---|---|
| `SITE_URL` | `https://core.sysfera.ru` | update URLs in the manifest, health check |
| `SSH_HOST`, `SSH_PORT`, `SSH_USER`, `SSH_PASS` | | SSH/rsync deploy (preferred) |
| `DEPLOY_PATH` | `/var/www/sysfera.ru` | folder the site lives in (no trailing `/`) |
| `FTP_HOST`, `FTP_USER`, `FTP_PASS`, `FTP_PATH` | | FTP deploy (used if no `SSH_HOST`, or when started manually with method *ftp*) |
| `UPDATE_SIGN_SECRET` | base64 | optional: sign update packages (see UPDATES.md) |

## What is uploaded
```
<DEPLOY_PATH>/index.php  .htaccess
<DEPLOY_PATH>/core/              program files          (rsync --delete, but storage/ and plugins/ excluded)
<DEPLOY_PATH>/core/plugins/      bundled components     (added/updated, never deleted)
<DEPLOY_PATH>/updates/           manifest.json, core-<v>.zip, plugins/*.zip   ← the update server
<DEPLOY_PATH>/download/          sysfera-install.php, sysfera-<v>.zip          ← download page files
<DEPLOY_PATH>/install.php        only while core/storage/installed.lock does not exist
```
`core/storage` (config, backups, logs) is never touched, so redeploys do not log anyone out or lose data.

## First time
1. Add the secrets, push (or *Run workflow*).
2. Create an empty MySQL database for the site.
3. Open `https://core.sysfera.ru/install.php` and run the wizard.
4. From now on every push to `main` redeploys; sites you installed elsewhere see the new version under **Updates**.

## Note on FTP deploys
The FTP action excludes `core/plugins/**` and `core/storage/**` (it cannot do per-folder "never delete"). Bundled component changes therefore reach FTP-deployed sites through the Updates page rather than the deploy.
