-- CRM 0.2: tasks use the shared task types (Core → Task types); the extra fields of a type are kept here.
ALTER TABLE {p}crm_tasks ADD COLUMN type_data TEXT NULL;
