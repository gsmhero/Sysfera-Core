-- Sysfera Storage Rent 0.1.0: the WRMS core (warehouses, units, tenants, contracts, invoices, payments). Additive only.
-- Money = whole kopecks (BIGINT), dates = DATE, times = unix seconds (UTC). No foreign keys to the core tables; user_id is a Sysfera user id.

CREATE TABLE IF NOT EXISTS {p}sr_warehouses (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  owner_id INT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL,
  city VARCHAR(100) NOT NULL DEFAULT '',
  address VARCHAR(255) NOT NULL DEFAULT '',
  lat DECIMAL(9,6) NULL,
  lon DECIMAL(9,6) NULL,
  note TEXT NULL,
  created_at INT UNSIGNED NOT NULL,
  KEY ix_owner (owner_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}sr_units (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  warehouse_id INT UNSIGNED NOT NULL,
  name VARCHAR(100) NOT NULL,
  type VARCHAR(24) NOT NULL DEFAULT 'dry',
  area DECIMAL(10,2) NOT NULL DEFAULT 0,
  volume DECIMAL(10,2) NOT NULL DEFAULT 0,
  pallets INT UNSIGNED NOT NULL DEFAULT 0,
  load_t DECIMAL(6,2) NOT NULL DEFAULT 0,
  height DECIMAL(5,2) NOT NULL DEFAULT 0,
  dock TINYINT NOT NULL DEFAULT 0,
  features VARCHAR(255) NOT NULL DEFAULT '',
  status_manual VARCHAR(16) NOT NULL DEFAULT '',
  plan_x INT NOT NULL DEFAULT 0,
  plan_y INT NOT NULL DEFAULT 0,
  plan_w INT NOT NULL DEFAULT 10,
  plan_h INT NOT NULL DEFAULT 6,
  note TEXT NULL,
  created_at INT UNSIGNED NOT NULL,
  KEY ix_wh (warehouse_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}sr_tenants (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  kind VARCHAR(12) NOT NULL DEFAULT 'legal',
  name VARCHAR(190) NOT NULL,
  tin VARCHAR(20) NOT NULL DEFAULT '',
  contact VARCHAR(150) NOT NULL DEFAULT '',
  phone VARCHAR(40) NOT NULL DEFAULT '',
  email VARCHAR(190) NOT NULL DEFAULT '',
  address VARCHAR(255) NOT NULL DEFAULT '',
  restrictions TEXT NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'active',
  vat_mode VARCHAR(10) NOT NULL DEFAULT 'none',
  vat_rate DECIMAL(5,2) NOT NULL DEFAULT 20,
  note TEXT NULL,
  created_at INT UNSIGNED NOT NULL,
  updated_at INT UNSIGNED NOT NULL,
  KEY ix_user (user_id),
  KEY ix_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}sr_contracts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  number VARCHAR(40) NOT NULL,
  tenant_id INT UNSIGNED NOT NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'draft',
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  rate_type VARCHAR(8) NOT NULL DEFAULT 'm2',
  rate BIGINT NOT NULL DEFAULT 0,
  billing_months TINYINT UNSIGNED NOT NULL DEFAULT 1,
  due_day TINYINT UNSIGNED NOT NULL DEFAULT 5,
  grace_days SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  penalty_type VARCHAR(8) NOT NULL DEFAULT 'none',
  penalty_value DECIMAL(10,4) NOT NULL DEFAULT 0,
  penalty_cap_pct SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  deposit BIGINT NOT NULL DEFAULT 0,
  util_method VARCHAR(10) NOT NULL DEFAULT 'included',
  util_fixed BIGINT NOT NULL DEFAULT 0,
  access_24h TINYINT NOT NULL DEFAULT 1,
  access_from VARCHAR(5) NOT NULL DEFAULT '',
  access_to VARCHAR(5) NOT NULL DEFAULT '',
  notice_days SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  flag_days SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  prorate TINYINT NOT NULL DEFAULT 0,
  note TEXT NULL,
  terminated_on DATE NULL,
  termination_reason VARCHAR(255) NOT NULL DEFAULT '',
  renewed_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_by INT UNSIGNED NOT NULL DEFAULT 0,
  created_at INT UNSIGNED NOT NULL,
  updated_at INT UNSIGNED NOT NULL,
  UNIQUE KEY ux_number (number),
  KEY ix_tenant (tenant_id),
  KEY ix_status (status, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}sr_contract_units (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  contract_id INT UNSIGNED NOT NULL,
  unit_id INT UNSIGNED NOT NULL,
  share_pct DECIMAL(5,2) NOT NULL DEFAULT 100,
  KEY ix_contract (contract_id),
  KEY ix_unit (unit_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}sr_contract_versions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  contract_id INT UNSIGNED NOT NULL,
  version SMALLINT UNSIGNED NOT NULL,
  note VARCHAR(255) NOT NULL DEFAULT '',
  snapshot MEDIUMTEXT NOT NULL,
  user_id INT UNSIGNED NOT NULL DEFAULT 0,
  created_at INT UNSIGNED NOT NULL,
  UNIQUE KEY ux_ver (contract_id, version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}sr_invoices (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  number VARCHAR(40) NOT NULL,
  contract_id INT UNSIGNED NOT NULL,
  tenant_id INT UNSIGNED NOT NULL,
  kind VARCHAR(10) NOT NULL DEFAULT 'rent',
  period_start DATE NULL,
  period_end DATE NULL,
  issue_date DATE NOT NULL,
  due_date DATE NOT NULL,
  stored_status VARCHAR(12) NOT NULL DEFAULT 'issued',
  status VARCHAR(16) NOT NULL DEFAULT 'issued',
  net BIGINT NOT NULL DEFAULT 0,
  vat BIGINT NOT NULL DEFAULT 0,
  total BIGINT NOT NULL DEFAULT 0,
  principal BIGINT NOT NULL DEFAULT 0,
  penalty_billed BIGINT NOT NULL DEFAULT 0,
  reason VARCHAR(255) NOT NULL DEFAULT '',
  created_by INT UNSIGNED NOT NULL DEFAULT 0,
  created_at INT UNSIGNED NOT NULL,
  UNIQUE KEY ux_number (number),
  UNIQUE KEY ux_period (contract_id, kind, period_start),
  KEY ix_tenant (tenant_id),
  KEY ix_due (due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}sr_invoice_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  invoice_id INT UNSIGNED NOT NULL,
  kind VARCHAR(10) NOT NULL DEFAULT 'rent',
  description VARCHAR(255) NOT NULL,
  qty DECIMAL(12,3) NOT NULL DEFAULT 1,
  amount BIGINT NOT NULL DEFAULT 0,
  vatable TINYINT NOT NULL DEFAULT 1,
  ref_id INT UNSIGNED NULL,
  KEY ix_invoice (invoice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}sr_payments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  contract_id INT UNSIGNED NULL,
  pay_date DATE NOT NULL,
  amount BIGINT NOT NULL,
  kind VARCHAR(8) NOT NULL DEFAULT 'payment',
  method VARCHAR(16) NOT NULL DEFAULT 'bank',
  reference VARCHAR(120) NOT NULL DEFAULT '',
  note VARCHAR(255) NOT NULL DEFAULT '',
  created_by INT UNSIGNED NOT NULL DEFAULT 0,
  created_at INT UNSIGNED NOT NULL,
  KEY ix_tenant (tenant_id, pay_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}sr_allocations (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  payment_id INT UNSIGNED NOT NULL,
  invoice_id INT UNSIGNED NOT NULL,
  amount BIGINT NOT NULL,
  KEY ix_payment (payment_id),
  KEY ix_invoice (invoice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}sr_adjustments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  invoice_id INT UNSIGNED NOT NULL,
  kind VARCHAR(14) NOT NULL,
  amount BIGINT NOT NULL,
  adj_date DATE NOT NULL,
  reason VARCHAR(255) NOT NULL,
  user_id INT UNSIGNED NOT NULL DEFAULT 0,
  created_at INT UNSIGNED NOT NULL,
  KEY ix_invoice (invoice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}sr_audit (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL DEFAULT 0,
  entity VARCHAR(24) NOT NULL,
  entity_id INT UNSIGNED NOT NULL DEFAULT 0,
  action VARCHAR(24) NOT NULL,
  diff TEXT NULL,
  created_at INT UNSIGNED NOT NULL,
  KEY ix_entity (entity, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
