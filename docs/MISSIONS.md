# Missions component (`missions`)

A port of **Tactify Mission Manager** to a Sysfera Core component. A *mission* is a chain of *tasks*; every task has three possible results (Success / Alternative / Failed) and each result can lead to the next task.

## What it does
* **My missions** — list with search, status, category and ★ bookmark filter, progress bar; create, complete / back to active, delete.
* **Mission page** — the task tree. Task types come from the **shared registry** (Core → *Task types*, the same list CRM uses): Call, Email, Meeting, Visit URL, Note, Buy product, To-do, Deadline, and the mission-flow types Go to task, No action, Task done, Finish mission, plus any type the administrator adds. Each type keeps its own fields.
* **Done button** — *Email*: sends the e-mail through Core's mailer (Settings → Mail); *Finish mission*: completes the mission and marks the **lucky way** (green path from the first task along the "success" results to Finish mission); *Go to task*: jumps to the target.
* **Library** — missions a user shares are visible to all users of the site; anyone can preview and copy one (progress and reminders are not copied).
* **JSON import / export** — own format, and it also reads Tactify Mission Manager exports (`parent_link_id`, `_good|_alt|_unexpected`).
* **Categories** — administrator-managed, bilingual (EN/RU), with a colour.
* **Reminders** — a task with a date and "remind me" sends an e-mail when due; scheduled *Email* tasks are sent, scheduled *Finish mission* tasks complete the mission. Add to the hosting cron (every minute); the exact line with the secret token is shown on the Missions page to administrators:
  `curl -s "https://<site>/api/missions/remind?token=<token>"`

## Version 0.2
* **Task types** are read from Core (see [TASK-TYPES.md](TASK-TYPES.md)); old tasks are converted to type codes by the migration.
* **Call / Email**: pick a **CRM contact** (needs the CRM component and the `crm.use` right); the phone / e-mail fields are filled in from the contact. Only contacts that the user may see in CRM are offered.
* **Buy product**: a Basebase shop link. If the WMS → Basebase connector (0.6+) is installed, the user's own published products are suggested in the field (their links are stored when a product is published; products published before the update get the link at their next sync).
* **Teams**: a mission can be shared with the teams the user belongs to (Teams component). Members see it read-only under "Shared with my teams" and can copy it; they are notified when it is completed.
* **Activity**: completing a mission is written to the event log; if the owner ticked "Announce completion in Activity", a short post (title ≤ 20 words, text ≤ 50 words) goes to the public Activity feed.
* **Notification list** (🔔 tab and dashboard widget): reminders and team events; the e-mail reminder is still sent.
* **Attachments and photos** on tasks (images, PDF, text, Office; 5 MB, 10 per task), stored in `storage/missions` (not reachable from the web) and served only to people who may see the mission.
* **Maps**: a "place" field gets a map link (Yandex for Russian, OpenStreetMap otherwise; no API key).
* **Bookmarks** (★) and a **notification language** per mission (reminders are written in it).
## Privacy
Missions and tasks belong to their user (`user_id`); nobody else, administrators included, sees them unless the user shares the mission to the library.

## Data
`mis_categories`, `mis_missions`, `mis_tasks` (see `plugins/missions/migrations/001_missions.sql`). Task ids are numbers; a task points to its parent with `parent_id` + `branch` (`good|alt|bad`).

## Not ported yet (from Tactify)
Map pickers with API keys (Yandex / Mapbox: only plain map links are used), day/night switch (Sysfera has its own theming). The original app stored API keys and passwords in its `config.php` / `schema.sql`; none of that was carried over — rotate those secrets.
