-- Sysfera Storage Rent 0.3.0: utilities (meters, readings), documents, notification templates and log, on-site access log, tenant requests. Additive only.

CREATE TABLE IF NOT EXISTS {p}sr_meters (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  unit_id INT UNSIGNED NOT NULL,
  type VARCHAR(16) NOT NULL DEFAULT 'electricity',
  serial VARCHAR(60) NOT NULL DEFAULT '',
  rate BIGINT NOT NULL DEFAULT 0,
  rollover DECIMAL(14,3) NULL,
  KEY ix_unit (unit_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}sr_readings (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  meter_id INT UNSIGNED NOT NULL,
  read_date DATE NOT NULL,
  value DECIMAL(14,3) NOT NULL,
  consumption DECIMAL(14,3) NOT NULL DEFAULT 0,
  charge BIGINT NOT NULL DEFAULT 0,
  photo_doc_id INT UNSIGNED NULL,
  billed_invoice_id INT UNSIGNED NULL,
  user_id INT UNSIGNED NOT NULL DEFAULT 0,
  created_at INT UNSIGNED NOT NULL,
  KEY ix_meter (meter_id, read_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}sr_documents (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  owner_type VARCHAR(12) NOT NULL,
  owner_id INT UNSIGNED NOT NULL,
  tenant_id INT UNSIGNED NULL,
  kind VARCHAR(16) NOT NULL DEFAULT 'file',
  title VARCHAR(190) NOT NULL,
  file_name VARCHAR(190) NOT NULL,
  stored VARCHAR(64) NOT NULL,
  mime VARCHAR(80) NOT NULL DEFAULT '',
  size INT UNSIGNED NOT NULL DEFAULT 0,
  uploaded_by INT UNSIGNED NOT NULL DEFAULT 0,
  created_at INT UNSIGNED NOT NULL,
  KEY ix_owner (owner_type, owner_id),
  KEY ix_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}sr_templates (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  event VARCHAR(24) NOT NULL,
  lang VARCHAR(5) NOT NULL,
  subject VARCHAR(190) NOT NULL,
  body TEXT NOT NULL,
  UNIQUE KEY ux_event (event, lang)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}sr_notify_log (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  dedupe VARCHAR(120) NOT NULL,
  event VARCHAR(24) NOT NULL,
  tenant_id INT UNSIGNED NOT NULL,
  to_email VARCHAR(190) NOT NULL DEFAULT '',
  status VARCHAR(10) NOT NULL DEFAULT 'sent',
  error VARCHAR(255) NOT NULL DEFAULT '',
  created_at INT UNSIGNED NOT NULL,
  UNIQUE KEY ux_dedupe (dedupe)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}sr_access_log (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  unit_id INT UNSIGNED NULL,
  person VARCHAR(150) NOT NULL DEFAULT '',
  direction VARCHAR(4) NOT NULL DEFAULT 'in',
  happened_at INT UNSIGNED NOT NULL,
  outside_schedule TINYINT NOT NULL DEFAULT 0,
  note VARCHAR(255) NOT NULL DEFAULT '',
  user_id INT UNSIGNED NOT NULL DEFAULT 0,
  KEY ix_tenant (tenant_id, happened_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}sr_requests (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  unit_id INT UNSIGNED NULL,
  title VARCHAR(190) NOT NULL,
  body TEXT NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'new',
  photo_doc_id INT UNSIGNED NULL,
  created_at INT UNSIGNED NOT NULL,
  updated_at INT UNSIGNED NOT NULL,
  KEY ix_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
