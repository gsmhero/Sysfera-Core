-- Sysfera Storage Rent: every user may manage their own storages, so the data of the module is stamped with its owner (own_uid; warehouses already have owner_id). Additive only.
-- Rows that exist now keep own_uid = 0: they belong to the site and stay visible to the staff, as before.

ALTER TABLE {p}sr_units ADD COLUMN own_uid INT UNSIGNED NOT NULL DEFAULT 0, ADD KEY ix_own (own_uid);
ALTER TABLE {p}sr_tenants ADD COLUMN own_uid INT UNSIGNED NOT NULL DEFAULT 0, ADD KEY ix_own (own_uid);
ALTER TABLE {p}sr_contracts ADD COLUMN own_uid INT UNSIGNED NOT NULL DEFAULT 0, ADD KEY ix_own (own_uid);
ALTER TABLE {p}sr_invoices ADD COLUMN own_uid INT UNSIGNED NOT NULL DEFAULT 0, ADD KEY ix_own (own_uid);
ALTER TABLE {p}sr_payments ADD COLUMN own_uid INT UNSIGNED NOT NULL DEFAULT 0, ADD KEY ix_own (own_uid);
ALTER TABLE {p}sr_meters ADD COLUMN own_uid INT UNSIGNED NOT NULL DEFAULT 0, ADD KEY ix_own (own_uid);
ALTER TABLE {p}sr_documents ADD COLUMN own_uid INT UNSIGNED NOT NULL DEFAULT 0, ADD KEY ix_own (own_uid);
ALTER TABLE {p}sr_access_log ADD COLUMN own_uid INT UNSIGNED NOT NULL DEFAULT 0, ADD KEY ix_own (own_uid);
ALTER TABLE {p}sr_requests ADD COLUMN own_uid INT UNSIGNED NOT NULL DEFAULT 0, ADD KEY ix_own (own_uid);
