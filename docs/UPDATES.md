# Updates

## What users see
**Updates** page → *Check for updates* → *Update to x.y.z*. The same page lists component updates and the backups that were made.

## What happens during a core update
1. `manifest.json` is fetched from the update server (HTTPS; default `https://www.sysfera.ru/updates/manifest.json`, changeable in Settings).
2. The package is downloaded and its **SHA-256** must match the manifest; if `core/app/update_key.php` contains a public key, its **Ed25519 signature** must verify too.
3. **Backup**: `storage/backups/<date>-core-<old>/files.zip` (the files about to be replaced) and `database.sql` (full dump of this install's tables). The 5 newest backups are kept.
4. Maintenance flag on (visitors see "Updating…" for the few seconds it takes).
5. Files are copied over — **never `storage/`, never `plugins/`**. `index.php` and `.htaccess` are not part of the package.
6. New migrations run (additive; already-finished statements are skipped, so a partial run can resume).
7. On any error the old files are restored from `files.zip`, maintenance is lifted, and the message names the backup folder.

Because data lives in the database and `storage/`, and migrations never remove anything, **customers keep every client, order, invoice and setting** across updates. Manual FTP updates work too: upload the new `core/` files — on the next request the core notices the version changed and runs migrations itself.

## Rolling back
Replace `core/` files from `files.zip` (FTP) and, only if data must go back too, import `database.sql` with phpMyAdmin (it drops and recreates this install's tables).

## Publishing an update (maintainers)
1. Bump `core/VERSION` (and/or a component's `version` in its `plugin.json`).
2. Merge to `main` — the deploy workflow runs `tools/build.php` and uploads `updates/manifest.json`, `updates/core-<v>.zip`, `updates/plugins/*.zip` to the site. Installed sites see the update on their next check (cached 6 h; *Check for updates* forces it).
3. Tag `vX.Y.Z` (must equal `core/VERSION`) to also publish the packages on GitHub Releases.

### Signing (recommended)
```php
$kp = sodium_crypto_sign_keypair();
echo base64_encode(sodium_crypto_sign_secretkey($kp)), "\n";   // → GitHub secret UPDATE_SIGN_SECRET
echo base64_encode(sodium_crypto_sign_publickey($kp)), "\n";   // → core/app/update_key.php  (return '…';)
```
Ship the public key in a release **before** you start signing; from then on installs reject unsigned or tampered packages. Keep the secret out of the repository.

### manifest.json
```json
{ "core": { "version": "0.2.0", "url": "https://…/updates/core-0.2.0.zip", "sha256": "…", "sig": "…", "notes": { "en": "…", "ru": "…" } },
  "plugins": { "crm": { "version": "1.1.0", "url": "…", "sha256": "…", "sig": "…", "name": {…}, "description": {…} } } }
```
Plugins listed here but not on the site appear under *Available → on update server* with a **Download** button — this is the component marketplace mechanism.
