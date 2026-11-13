# Sysfera Teams

Component `teams` (requires core ≥ 0.1.9). Team collaboration on your own install: chat, task board, calendar and files, one workspace per team. No hub or external service is needed.

## What is inside
| Area | What you get |
|---|---|
| **Teams** | Open (anyone can join) or private (by invitation). Name, description, colour. Each team has its own channels, tasks, events, files. |
| **Roles** | *Owner* (everything, roles, archiving), *Admin* (channels, people, moderation, settings), *Member* (chat, tasks, events, files). A site administrator acts as an owner everywhere. A team always keeps at least one owner. |
| **Chat** | Public and private channels (private ones have their own member list). Replies with a quote, `@mentions` by member name, six emoji reactions, pinned messages, edit and delete (soft delete), file attachments. New messages arrive by polling every 6 seconds. Unread counters per channel, a separate `@` counter for mentions. 30 messages per minute per user. |
| **Task board** | Columns you configure (default: To do, In progress, Done), drag & drop, assignee, due date, priority, "only mine" filter. A column can be marked "counts as done". |
| **Calendar** | Month grid starting on Monday; events can span several days; list of upcoming events. |
| **Files** | Files up to 5 MB (images, PDF, Office, text, zip). Scripts, HTML and SVG are refused. Stored outside the web root in `storage/teams/`; downloads are sent with `nosniff` and a sandbox policy. |
| **Dashboard widget** | Unread messages and your open tasks. |

## First steps
1. *Components → Sysfera Teams → Install.*
2. Give users the capability **Teams** (use) and, for those who may start teams, **Create teams**.
3. *Teams → New team*; the channel `general` and the three default columns are created for you in the interface language.

## For other components
Hook `teams.message.created` (argument: message id).

## Notes and limits of 0.1
* Real-time delivery is polling, not websockets (works on any shared hosting).
* No e-mail notices, no threads, no direct messages between two people (use Messenger for that), no search in messages yet.
* Demo mode: file upload is switched off.
