-- Goods issue: documents of the head office confirmed by pickup warehouses. Additive only.
CREATE TABLE IF NOT EXISTS {p}gi_warehouses (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(40) NOT NULL,
  name VARCHAR(160) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY ux_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}gi_staff (
  user_id INT UNSIGNED NOT NULL PRIMARY KEY,
  role VARCHAR(20) NOT NULL DEFAULT 'office',
  warehouse_id INT UNSIGNED NULL,
  KEY ix_wh (warehouse_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}gi_devices (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  device_id VARCHAR(64) NOT NULL,
  label VARCHAR(120) NOT NULL DEFAULT '',
  secret CHAR(64) NOT NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL,
  last_seen DATETIME NULL,
  UNIQUE KEY ux_dev (user_id, device_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}gi_sessions (
  token_hash CHAR(64) NOT NULL PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  device_id VARCHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  KEY ix_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}gi_transfers (
  uuid CHAR(36) NOT NULL PRIMARY KEY,
  warehouse_id INT UNSIGNED NOT NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'created',
  checksum CHAR(64) NOT NULL,
  created_by VARCHAR(120) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL,
  delivered_at DATETIME NULL,
  confirmed_at DATETIME NULL,
  KEY ix_wh (warehouse_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}gi_orders (
  uuid CHAR(36) NOT NULL PRIMARY KEY,
  number VARCHAR(40) NOT NULL DEFAULT '',
  warehouse_id INT UNSIGNED NOT NULL,
  transfer_uuid CHAR(36) NOT NULL,
  customer_name VARCHAR(160) NOT NULL DEFAULT '',
  customer_phone VARCHAR(40) NOT NULL DEFAULT '',
  customer_email VARCHAR(160) NOT NULL DEFAULT '',
  total_rub DECIMAL(12,2) NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'created',
  version INT UNSIGNED NOT NULL DEFAULT 1,
  ready_at DATETIME NULL,
  issued_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY ix_wh (warehouse_id, status),
  KEY ix_tr (transfer_uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}gi_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  transfer_uuid CHAR(36) NOT NULL,
  order_uuid CHAR(36) NOT NULL,
  sku VARCHAR(64) NOT NULL DEFAULT '',
  name VARCHAR(190) NOT NULL DEFAULT '',
  qty INT UNSIGNED NOT NULL,
  weight_g INT UNSIGNED NOT NULL DEFAULT 0,
  barcode VARCHAR(64) NOT NULL,
  price_rub DECIMAL(12,2) NOT NULL DEFAULT 0,
  received_qty INT UNSIGNED NOT NULL DEFAULT 0,
  state VARCHAR(12) NOT NULL DEFAULT 'expected',
  UNIQUE KEY ux_bc (transfer_uuid, barcode),
  KEY ix_order (order_uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}gi_docs (
  uuid CHAR(36) NOT NULL PRIMARY KEY,
  type VARCHAR(12) NOT NULL,
  warehouse_id INT UNSIGNED NOT NULL,
  ref VARCHAR(64) NOT NULL DEFAULT '',
  fingerprint CHAR(64) NOT NULL,
  payload MEDIUMTEXT NULL,
  result MEDIUMTEXT NULL,
  day DATE NOT NULL,
  client_ts DATETIME NULL,
  created_at DATETIME NOT NULL,
  actor VARCHAR(160) NOT NULL DEFAULT '',
  device_id VARCHAR(64) NULL,
  KEY ix_wh (warehouse_id, type, day)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}gi_codes (
  order_uuid CHAR(36) NOT NULL PRIMARY KEY,
  code_hash CHAR(64) NOT NULL,
  expires_at INT UNSIGNED NOT NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}gi_audit (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ts DATETIME NOT NULL,
  client_ts DATETIME NULL,
  actor VARCHAR(160) NOT NULL,
  action VARCHAR(40) NOT NULL,
  entity VARCHAR(20) NOT NULL,
  entity_uuid VARCHAR(64) NOT NULL,
  device_id VARCHAR(64) NULL,
  code_hash CHAR(64) NULL,
  data TEXT NULL,
  prev_hash CHAR(64) NOT NULL,
  hash CHAR(64) NOT NULL,
  KEY ix_entity (entity_uuid),
  KEY ix_action (action, ts)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}gi_incidents (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  warehouse_id INT UNSIGNED NOT NULL,
  kind VARCHAR(24) NOT NULL,
  ref VARCHAR(80) NOT NULL,
  severity VARCHAR(8) NOT NULL DEFAULT 'medium',
  status VARCHAR(10) NOT NULL DEFAULT 'open',
  details TEXT NULL,
  note VARCHAR(300) NULL,
  created_at DATETIME NOT NULL,
  closed_at DATETIME NULL,
  closed_by VARCHAR(120) NULL,
  KEY ix_status (status, warehouse_id),
  KEY ix_ref (kind, ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}gi_reports (
  warehouse_id INT UNSIGNED NOT NULL,
  day DATE NOT NULL,
  body MEDIUMTEXT NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (warehouse_id, day)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
