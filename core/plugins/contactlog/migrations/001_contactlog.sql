-- Sysfera Contact Log 0.1.0 (ported from the standalone "Agent's Call List" page). Additive only. Users are the Sysfera users.
-- Times are unix seconds (UTC); no foreign keys to the core tables.

CREATE TABLE IF NOT EXISTS {p}cl_cards (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  name VARCHAR(100) NOT NULL,
  second_name VARCHAR(100) NOT NULL DEFAULT '',
  surname VARCHAR(100) NOT NULL DEFAULT '',
  mobile VARCHAR(40) NOT NULL DEFAULT '',
  messenger VARCHAR(100) NOT NULL DEFAULT '',
  email VARCHAR(190) NOT NULL DEFAULT '',
  status VARCHAR(24) NOT NULL DEFAULT 'busy',
  theme VARCHAR(190) NOT NULL DEFAULT '',
  notes TEXT NULL,
  next_call DATE NULL,
  created_at INT UNSIGNED NOT NULL,
  updated_at INT UNSIGNED NOT NULL,
  KEY ix_user (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}cl_logs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  card_id INT UNSIGNED NOT NULL,
  action VARCHAR(16) NOT NULL,
  created_at INT UNSIGNED NOT NULL,
  KEY ix_card (card_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}cl_profiles (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  copy_email VARCHAR(190) NOT NULL DEFAULT '',
  UNIQUE KEY ux_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
