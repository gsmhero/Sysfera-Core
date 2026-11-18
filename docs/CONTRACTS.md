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

## Not ported from Bablito
YooKassa service-fee payments, tax calculation, PDF invoices and invoice templates, the VK / OK.ru mini-apps, the stopwatch / work log, comment attachments and live refresh, the Bablito sign-in and its home / static pages. No secret of the original app is used or stored.
