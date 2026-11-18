-- CRM 0.5: missions of the Missions library attached to a task (ids, as JSON; same idea as wdocs / tfiles).
ALTER TABLE {p}crm_tasks ADD COLUMN tmissions TEXT NULL;
