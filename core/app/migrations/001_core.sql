-- Sysfera core schema. Migrations are ADDITIVE: new tables / new columns only, never drop or rewrite user data.
-- "{p}" is replaced with the table prefix chosen at install time.

CREATE TABLE IF NOT EXISTS {p}settings (
  k VARCHAR(190) NOT NULL PRIMARY KEY,
  v MEDIUMTEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  name VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','user') NOT NULL DEFAULT 'user',
  status ENUM('active','blocked') NOT NULL DEFAULT 'active',
  caps TEXT NULL,
  lang VARCHAR(8) NULL,
  last_login DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}plugins (
  slug VARCHAR(60) NOT NULL PRIMARY KEY,
  version VARCHAR(40) NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  installed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  plugin VARCHAR(60) NULL,
  type VARCHAR(80) NOT NULL,
  data MEDIUMTEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_type (type),
  KEY ix_plugin (plugin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Shared records: every plugin reads and writes the same clients, orders and invoices.
CREATE TABLE IF NOT EXISTS {p}contacts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  owner_id INT UNSIGNED NULL,
  type ENUM('person','company') NOT NULL DEFAULT 'person',
  name VARCHAR(190) NOT NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(60) NULL,
  company VARCHAR(190) NULL,
  address TEXT NULL,
  notes TEXT NULL,
  source VARCHAR(60) NULL,
  ref VARCHAR(120) NULL,
  custom MEDIUMTEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_owner (owner_id),
  KEY ix_email (email),
  KEY ix_ref (source, ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}orders (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  owner_id INT UNSIGNED NULL,
  number VARCHAR(40) NOT NULL,
  contact_id INT UNSIGNED NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'new',
  currency CHAR(3) NOT NULL DEFAULT 'RUB',
  total DECIMAL(14,2) NOT NULL DEFAULT 0,
  source VARCHAR(60) NULL,
  ref VARCHAR(120) NULL,
  meta MEDIUMTEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_number (number),
  KEY ix_contact (contact_id),
  KEY ix_owner (owner_id),
  KEY ix_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}order_lines (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  title VARCHAR(255) NOT NULL,
  sku VARCHAR(80) NULL,
  qty DECIMAL(14,3) NOT NULL DEFAULT 1,
  price DECIMAL(14,2) NOT NULL DEFAULT 0,
  ref VARCHAR(120) NULL,
  meta MEDIUMTEXT NULL,
  KEY ix_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}invoices (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  owner_id INT UNSIGNED NULL,
  number VARCHAR(40) NOT NULL,
  order_id INT UNSIGNED NULL,
  contact_id INT UNSIGNED NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'draft',
  currency CHAR(3) NOT NULL DEFAULT 'RUB',
  amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  issued_at DATE NULL,
  due_at DATE NULL,
  paid_at DATETIME NULL,
  source VARCHAR(60) NULL,
  meta MEDIUMTEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_number (number),
  KEY ix_order (order_id),
  KEY ix_contact (contact_id),
  KEY ix_owner (owner_id),
  KEY ix_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}invoice_lines (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  invoice_id INT UNSIGNED NOT NULL,
  title VARCHAR(255) NOT NULL,
  sku VARCHAR(80) NULL,
  qty DECIMAL(14,3) NOT NULL DEFAULT 1,
  price DECIMAL(14,2) NOT NULL DEFAULT 0,
  ref VARCHAR(120) NULL,
  meta MEDIUMTEXT NULL,
  KEY ix_invoice (invoice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
