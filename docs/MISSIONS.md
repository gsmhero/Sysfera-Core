# Missions component (`missions`)

A port of **Tactify Mission Manager** to a Sysfera Core component. A *mission* is a chain of *tasks*; every task has three possible results (Success / Alternative / Failed) and each result can lead to the next task.

## What it does
* **My missions** — list with search, status and category filter, progress bar; create, complete / back to active, delete.
* **Mission page** — the task tree. Task types: Call, Email, Meeting, Visit URL, Note, Go to task, No action, Task done, Finish mission, Buy product. Each type keeps its own fields (phone, e-mail address/subject/text, place/link, URL, target task).
* **Done button** — *Email*: sends the e-mail through Core's mailer (Settings → Mail); *Finish mission*: completes the mission and marks the **lucky way** (green path from the first task along the "success" results to Finish mission); *Go to task*: jumps to the target.
* **Library** — missions a user shares are visible to all users of the site; anyone can preview and copy one (progress and reminders are not copied).
* **JSON import / export** — own format, and it also reads Tactify Mission Manager exports (`parent_link_id`, `_good|_alt|_unexpected`).
* **Categories** — administrator-managed, bilingual (EN/RU), with a colour.
* **Reminders** — a task with a date and "remind me" sends an e-mail when due; scheduled *Email* tasks are sent, scheduled *Finish mission* tasks complete the mission. Add to the hosting cron (every minute); the exact line with the secret token is shown on the Missions page to administrators:
  `curl -s "https://<site>/api/missions/remind?token=<token>"`

## Privacy
Missions and tasks belong to their user (`user_id`); nobody else, administrators included, sees them unless the user shares the mission to the library.

## Data
`mis_categories`, `mis_missions`, `mis_tasks` (see `plugins/missions/migrations/001_missions.sql`). Task ids are numbers; a task points to its parent with `parent_id` + `branch` (`good|alt|bad`).

## Not ported yet (from Tactify)
Photo gallery and file attachments of tasks, maps (Yandex / Mapbox) for meetings, bookmarks, the in-app notification list, per-mission notification language, day/night switch (Sysfera has its own theming). The original app stored API keys and passwords in its `config.php` / `schema.sql`; none of that was carried over — rotate those secrets.
