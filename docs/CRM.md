# Sysfera CRM

Component `crm` (requires core ≥ 0.1.9). Clients, deals on kanban boards, tasks, reports — with pipelines, fields and access profiles that you configure yourself.

## What is inside
| Area | What you get |
|---|---|
| **Pipelines = project types** | Any number of pipelines (Sales, Projects, Support, …). Each has its own stages (name, colour, win probability, kind *open / won / lost*). A won/lost stage closes the deal; "lost" asks for a reason. |
| **Deals** | Kanban board with drag & drop, list view with sorting, filters (search, owner, status, tag), amount + currency, expected close date, tags, owner, history. |
| **Clients** | Stored in the shared core table `contacts`, so Warehouse (WMS), Accounting and others see the same people and companies. Person or company, e-mail, phone, address, notes, own fields, deals and tasks of the client. |
| **Tasks** | To-do, call, meeting, e-mail, deadline; due date and time; assignee; grouped Overdue / Today / Upcoming; linked to a deal or a client. Dashboard widget with today's and overdue counts. |
| **History** | Every deal/client has a timeline: notes, stage changes, tasks done. |
| **Reports** | Open pipeline, weighted forecast (amount × stage probability), win rate, funnel, won by month, results by manager, lost reasons — for a period and a pipeline. |
| **Custom fields** | For clients, deals (all pipelines or one) and tasks. Types: text, long text, number, money, date, list, multi-select, checkbox, link, e-mail, phone; required flag; validated on the server. Values are stored as JSON, so adding a field never changes the database. |
| **User profiles** | Permission profiles: *sees own data / all data* and create, edit, delete, export, import, settings, reassign. Three are created for you (Manager, Team lead, Observer); make your own and assign them to users. Administrators always have full rights. |
| **CSV** | Export clients, deals, tasks (cells that a spreadsheet could run as a formula are neutralised). Import clients or deals from CSV (UTF-8, ≤ 2 MB, ≤ 2000 rows; e-mails that already exist are skipped). |

## First steps
1. *Components → Sysfera CRM → Install.* On the first visit example pipelines (Sales, Projects), a lead-source field and the three profiles are created in the interface language.
2. Give users the capability **CRM** (Users page, or *Settings → New users get access to*) and pick their profile in *CRM → CRM settings → User profiles*. Users without a profile get the default one (Manager: own data only).
3. *CRM settings* — edit stages, add fields, add pipelines.

## For other components
Hooks fired by CRM: `crm.deal.created`, `crm.deal.won`, `crm.deal.lost` (argument: deal id). A won deal can create an order or an invoice in another component — see `docs/PLUGIN-SDK.md`.

## Notes and limits of 0.1
* Data of a deleted stage/pipeline is protected: stages and pipelines that still have deals cannot be deleted.
* "Own data" means records whose owner is the user or that have no owner; tasks are limited to the user's own.
* No e-mail reminders yet, no automations/webhooks, no saved filters, no file attachments on deals (planned).
* Demo mode: visitors get the capability, so they can try the CRM; data of the demo is wiped by the normal demo reset of the install only for core tables, not for CRM tables yet.
* Tests: `php tests/crm.php` (fields, pipeline maths, tasks, permissions, CSV, language files) and `php tests/crmviews.php` (renders every screen with sample data).
