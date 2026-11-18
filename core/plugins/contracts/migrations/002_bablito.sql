-- Contracts 0.2.0: synchronisation with Bablito.ru. Additive only.
-- Links Sysfera users to Bablito accounts; remembers which local section / field mirrors which Bablito one; keeps the edit time (UTC) of every profile value so the newest edit wins.

CREATE TABLE IF NOT EXISTS {p}ct_links (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  remote_id INT UNSIGNED NOT NULL,
  remote_email VARCHAR(190) NOT NULL DEFAULT '',
  how VARCHAR(12) NOT NULL DEFAULT 'email',
  linked_at DATETIME NOT NULL,
  synced_at DATETIME NULL,
  UNIQUE KEY ux_user (user_id),
  UNIQUE KEY ux_remote (remote_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE {p}ct_psections ADD COLUMN remote_id INT UNSIGNED NULL;

ALTER TABLE {p}ct_pfields ADD COLUMN remote_id INT UNSIGNED NULL;

ALTER TABLE {p}ct_pvalues ADD COLUMN updated_at DATETIME NULL;
