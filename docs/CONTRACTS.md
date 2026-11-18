# Contracts (`contracts`)

A marketplace of contracts, ported from the standalone Bablito app onto Sysfera users and permissions.
Pages are under `/p/contracts/`. Rules live in `core/plugins/contracts/lib/Svc.php` (database-free, tested with an in-memory store);
`plugin.php` only wires them to the platform.

## Flow
`draft` -> `published` -> `active` (the author accepts an offer) <-> `paused` -> `fulfilled` (all steps accepted) or `terminated` (dismissed, or no contractor within the configured days).

* **Author** describes the job as steps (name, price, days), publishes it. **Offerers** send an offer: accept as is, or a counteroffer (own price/days per step, steps marked unnecessary, own steps, own order).
* Before the deal every offerer has a **private thread** with the author; after it, a work chat and a comment thread per step.
* **Payment**: "when finished" (one invoice when the last step is accepted) or "step by step" (an invoice per accepted step; the next step waits until the previous one is paid).
  The payer presses *I paid*, the payee confirms; money moves between them outside the platform. The payee may change the invoice number (`CT-<id>`) until it is paid.
* **Reviews** (1-5) between author and contractor of a fulfilled contract; the average is kept in the profile.
* **Notifications**: bell (always) and e-mail through the platform mailer (a profile switch turns e-mail off).
* **Administrator** (a Sysfera admin): categories, the days before an unclaimed contract is archived (default 30), profile sections/fields per status (company / self-employed / personal) and their **CSV download** (`/p/contracts/admin/profile-fields.csv`).

## Data
Tables `{p}ct_*` (migration `001_contracts.sql`, additive). Users are the Sysfera users; the extra per-user data (statuses, about, e-mail switch, rating) is in `{p}ct_profiles`.
Uploaded files are kept in `core/storage/contracts/` (not web-reachable) and served by `/p/contracts/file/{id}` after a permission check.

## Bablito.ru synchronisation (0.2.0)
Administration -> *Bablito.ru*. Accounts of Sysfera users and Bablito are matched **by e-mail**; a match is linked automatically, a user without a Bablito account is only listed and gets one when an administrator (or the user, from their profile) asks. A new Bablito account has **no password**: Bablito e-mails a one-time link to choose it. Sysfera password hashes are never sent.
Profile **sections and fields are defined on Bablito** and mirrored here (never deleted here); **values** of all three statuses (company, self-employed, personal) are synchronised both ways, **the newest edit wins** (UTC, ties go to Bablito), an empty value never deletes, statuses only accumulate, the *public* / *required* flags travel along. A profile save pushes to Bablito at once (live mode); the administrator's *Sync now* does everything, *Preview* shows what it would do.
**Dry run is the default**: until *Live mode* is ticked nothing is changed on either side. Requests are HMAC-SHA256 signed (`X-Sysfera-Timestamp`, `X-Sysfera-Signature` = hex HMAC of `ts \n METHOD \n request-uri \n sha256(body)`, 5-minute window). The shared key (32+ characters) is set once as the `SYSFERA_API_KEY` GitHub secret of the Bablito repository and here as the `SYSFERA_BABLITO_KEY` environment variable (or in the write-only field); it is never shown or logged. Tables: `{p}ct_links` (migration `002_bablito.sql`).

**Contracts published on Bablito.ru** are copied into the list (badge *Bablito.ru*, table `{p}ct_remote`, migration `003_remote.sql`): the card and its page show title, category, place, description, steps, total price and days, the author's name and a button that opens the original on Bablito, where offers, work and payment happen. The copy is read-only and is refreshed every 15 minutes on page loads in live mode, and by *Sync now* (a contract that is no longer published there disappears here; if Bablito cannot be reached nothing is removed). Category filters match by name.

## Not ported from Bablito
YooKassa service-fee payments, tax calculation, PDF invoices and invoice templates, the VK / OK.ru mini-apps, the stopwatch / work log, comment attachments and live refresh, the Bablito sign-in and its home / static pages. No secret of the original app is used or stored.
