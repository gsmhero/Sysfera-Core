-- Plugin tables are prefixed with the plugin slug. Additive only: add new 00N_*.sql files for later versions.
CREATE TABLE IF NOT EXISTS {p}hello_notes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  contact_id INT UNSIGNED NULL,
  user_id INT UNSIGNED NULL,
  body TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_contact (contact_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
