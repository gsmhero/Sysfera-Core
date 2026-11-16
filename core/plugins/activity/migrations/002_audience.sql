ALTER TABLE {p}act_posts ADD COLUMN audience VARCHAR(10) NOT NULL DEFAULT 'global', ADD KEY ix_aud (audience, hidden, id);
