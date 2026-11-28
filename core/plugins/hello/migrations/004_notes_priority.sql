ALTER TABLE {p}hello_notes ADD COLUMN priority TINYINT UNSIGNED NOT NULL DEFAULT 2, ADD KEY ix_priority (user_id, priority);
