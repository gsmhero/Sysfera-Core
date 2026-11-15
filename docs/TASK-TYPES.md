# Task types (Core)

One list of task types for the components that have tasks: **Missions** and **CRM**. The administrator edits it in *Task types* (menu, administrators only); the table is `{p}task_types`.

## A type
| Field | Meaning |
|---|---|
| code | latin id (`call`, `buy_product` …), never changes |
| name / name (RU) | shown according to the user's language |
| icon, colour | shown in lists |
| offered in | Missions, CRM or both |
| on | switched-off types disappear from the "Type" lists, old tasks keep showing |
| fields | up to 8 extra fields of the task: key, kind, label EN/RU |

Field kinds: text, long text, e-mail, phone, link, **Basebase shop link**, **task of the mission** (Missions only), **CRM contact** (the contact picker; in CRM it becomes the task's client).

Built-in types (can be renamed, switched off and re-fielded, not deleted; "Restore built-in types" resets them): To-do, Call, Email, Meeting, Deadline, Visit URL, Note, Buy product — in both components; Go to task, No action, Task done, Finish mission — Missions only (they steer a mission chain).

## Behaviour
* A task stores the **type code** and the values of the fields (`type_data`, JSON). Values are validated by the field kind (e-mail, phone, http(s) link, numeric ids).
* Deleting a custom type does not break tasks: a task of an unknown type is shown as a note.
* Missions: the `email` type sends the message when the task is marked done; `finish_mission` completes the mission; `go_to_task` jumps. Other types, also custom ones, are data only.
* CRM: the task keeps its deal / client; if a contact is chosen, empty phone / e-mail fields are filled in from the contact (only for contacts the user may see).
* Without the table (before the update ran) both components use the built-in list.

## For component authors
`Sysfera\TaskTypes::all($db, 'crm')` — the active types of a component; `::get($types, $code)`; `::clean($type, $post)` — validated values; `::label($type, $lang)`.