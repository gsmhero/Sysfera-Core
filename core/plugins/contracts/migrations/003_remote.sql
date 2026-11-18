-- Contracts 0.3.0: a read-only copy of the contracts published on Bablito.ru (offers are made there). Additive only.

CREATE TABLE IF NOT EXISTS {p}ct_remote (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  remote_id INT UNSIGNED NOT NULL,
  title VARCHAR(190) NOT NULL,
  category VARCHAR(80) NOT NULL DEFAULT '',
  location VARCHAR(190) NOT NULL DEFAULT '',
  description TEXT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'RUB',
  payment_type VARCHAR(10) NOT NULL DEFAULT 'finish',
  prepay_required TINYINT(1) NOT NULL DEFAULT 0,
  deadline DATE NULL,
  author_name VARCHAR(120) NOT NULL DEFAULT '',
  author_remote_id INT UNSIGNED NOT NULL DEFAULT 0,
  total_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  total_days INT UNSIGNED NOT NULL DEFAULT 0,
  steps_json MEDIUMTEXT NULL,
  created_at DATETIME NOT NULL,
  synced_at DATETIME NOT NULL,
  UNIQUE KEY ux_remote (remote_id),
  KEY ix_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
