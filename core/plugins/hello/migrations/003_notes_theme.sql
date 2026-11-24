ALTER TABLE {p}hello_notes ADD COLUMN theme VARCHAR(80) NOT NULL DEFAULT '', ADD KEY ix_theme (user_id, theme);
