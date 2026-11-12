-- Sysfera Messenger: tables are only used on the hub site (core.sysfera.ru); other installations keep nothing here.

CREATE TABLE IF NOT EXISTS {p}msg_installs (
  domain VARCHAR(190) NOT NULL PRIMARY KEY,
  secret CHAR(64) NOT NULL,
  base VARCHAR(300) NOT NULL DEFAULT '',
  enrolled_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}msg_users (
  sid VARCHAR(220) NOT NULL PRIMARY KEY,
  domain VARCHAR(190) NOT NULL,
  uid INT UNSIGNED NOT NULL,
  name VARCHAR(190) NOT NULL DEFAULT '',
  email_hash CHAR(64) NULL,
  discoverable TINYINT(1) NOT NULL DEFAULT 0,
  last_seen DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_name (name),
  KEY ix_email (email_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}msg_messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  from_sid VARCHAR(220) NOT NULL,
  to_sid VARCHAR(220) NOT NULL,
  body TEXT NULL,
  file_id CHAR(32) NULL,
  created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  read_at DATETIME NULL,
  KEY ix_to (to_sid, read_at),
  KEY ix_pair (from_sid, to_sid, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}msg_files (
  id CHAR(32) NOT NULL PRIMARY KEY,
  owner_sid VARCHAR(220) NOT NULL,
  name VARCHAR(190) NOT NULL,
  mime VARCHAR(100) NOT NULL,
  size INT UNSIGNED NOT NULL DEFAULT 0,
  attached TINYINT(1) NOT NULL DEFAULT 0,
  created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_owner (owner_sid, created)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}msg_blocks (
  sid VARCHAR(220) NOT NULL,
  blocked_sid VARCHAR(220) NOT NULL,
  PRIMARY KEY (sid, blocked_sid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
