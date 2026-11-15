# Plan: public mission libraries ⇄ tactify.ru

## Where we are
* **Tactify** (tactify.ru, v7.1) is a stand-alone PHP/MySQL app. Its source is only in a local folder (`Sysfera Core/Components/Sysfera Mission`), **not in Git**. Its library = the missions users marked "Public / Share to Library".
* **Sysfera Missions** (component) has a library per site: missions with `shared = 1`. Both sides already read the same JSON mission format (`format: sysfera-mission`, Tactify exports are imported too).
* Sites already talk to the hub **core.sysfera.ru** (Messenger connection); the hub can distribute settings (done: Missions → Setup) and relay requests.

## Target
A mission published as *Public* on any Sysfera site shows up in the library of every other site and on tactify.ru; a mission published on tactify.ru shows up on the sites. Copying a library mission makes a private copy (as now). Uploaded files are not synchronised (the "remove files from the public version" option).

## Design
1. **Tactify becomes a library source with a small signed API** (`/api/v1/library`): `GET ?since=<time>` (list of changed public missions: id, title, type, category, description, author, updated_at, tasks), `GET /<id>`, `POST` (publish / update from the hub), `DELETE /<id>` (withdraw). One shared secret between Tactify and the hub (HMAC of the request, timestamp, size and rate limits).
2. **The hub is the only gate between the sites and Tactify.** Sites never hold the Tactify key: they call hub operations `mis.publish`, `mis.withdraw`, `mis.library`. The hub stores the combined library (table of public missions: source `site:<domain>` or `tactify`, external id, content, updated_at, reports) and pulls Tactify every 15 minutes.
3. **Sites**: Library tab gets a source filter (this site / other Sysfera sites / Tactify). Publishing or withdrawing a mission pushes it to the hub in the background. Importing = the existing copy.
4. **No loops**: each mission carries `origin` + `origin_id`; a mission that came from the library is never published again as new, only its owner's own missions are.
5. **Moderation and safety**: reports hide a mission after N reports (as Activity does); administrators on the hub and on Tactify can remove; all text goes through the same cleaning as import (types, links, e-mails validated); maximum 300 tasks, 1 MB.

## Steps
| # | What | Who |
|---|---|---|
| 0 | **Decide**: repository name and visibility (suggest a private repo `Tactify`), how tactify.ru is deployed (FTP / SSH / hosting panel), one-way or two-way sync | you |
| 1 | **Secure and version the Tactify folder**: `git init`; `.gitignore` for `config.php`, `uploads/`, logs (`clients/email-debug.log`); remove the real admin password from `README.md`, and the real admin hash, SMTP password and map keys from `install/schema.sql` (they must be **rotated**, they have been in plain files); push to GitHub | me, after step 0 |
| 2 | **Deploy path**: a script / GitHub Action that uploads the repo to tactify.ru without touching `config.php` and `uploads/` | me + you (access) |
| 3 | **Tactify API** + migration (`updated_at`, `origin`, `external_id` on missions) + tests | me |
| 4 | **Hub operations** and the 15-minute pull; hub table of public missions | me |
| 5 | **Missions component**: source filter, background publish / withdraw, "from Tactify" badge | me |
| 6 | Staging test with one site, then roll out (hub first, then Core / Missions on the sites) | both |

## What I need from you to start
1. Confirm the repository name and visibility.
2. How do you upload to tactify.ru today (FTP, panel file manager, SSH)? I will build the deployment around it; credentials stay with you (GitHub secrets or your own machine), I never need to see them.
3. Rotate the secrets that were in the Tactify files: admin password, SMTP password, Yandex and Mapbox keys.
