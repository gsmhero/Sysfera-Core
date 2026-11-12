-- Sysfera WMS schema (additive only). Customers and suppliers live in the shared core table {p}contacts.

CREATE TABLE IF NOT EXISTS {p}wms_warehouses (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  code VARCHAR(40) NULL,
  address VARCHAR(255) NULL,
  comment TEXT NULL,
  use_cells TINYINT(1) NOT NULL DEFAULT 0,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}wms_zones (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  warehouse_id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  KEY ix_wh (warehouse_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}wms_cells (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  warehouse_id INT UNSIGNED NOT NULL,
  zone_id INT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  barcode VARCHAR(80) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  KEY ix_wh (warehouse_id),
  KEY ix_barcode (barcode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}wms_groups (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  parent_id INT UNSIGNED NULL,
  name VARCHAR(160) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}wms_products (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  group_id INT UNSIGNED NULL,
  name VARCHAR(255) NOT NULL,
  sku VARCHAR(80) NULL,
  code VARCHAR(40) NULL,
  barcode VARCHAR(80) NULL,
  unit VARCHAR(20) NOT NULL DEFAULT 'шт',
  weight DECIMAL(12,3) NOT NULL DEFAULT 0,
  volume DECIMAL(12,3) NOT NULL DEFAULT 0,
  vat DECIMAL(5,2) NULL,
  buy_price DECIMAL(14,2) NOT NULL DEFAULT 0,
  sell_price DECIMAL(14,2) NOT NULL DEFAULT 0,
  min_stock DECIMAL(14,3) NOT NULL DEFAULT 0,
  description TEXT NULL,
  archived TINYINT(1) NOT NULL DEFAULT 0,
  source VARCHAR(60) NULL,
  ref VARCHAR(120) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY ix_group (group_id),
  KEY ix_sku (sku),
  KEY ix_code (code),
  KEY ix_barcode (barcode),
  KEY ix_ref (source, ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}wms_docs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  type VARCHAR(20) NOT NULL,
  number VARCHAR(40) NOT NULL,
  status ENUM('draft','posted','cancelled') NOT NULL DEFAULT 'draft',
  moment DATETIME NOT NULL,
  warehouse_id INT UNSIGNED NULL,
  to_warehouse_id INT UNSIGNED NULL,
  contact_id INT UNSIGNED NULL,
  parent_id INT UNSIGNED NULL,
  total DECIMAL(14,2) NOT NULL DEFAULT 0,
  comment TEXT NULL,
  source VARCHAR(60) NULL,
  ref VARCHAR(120) NULL,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  posted_at DATETIME NULL,
  UNIQUE KEY uq_number (number),
  KEY ix_type (type, status),
  KEY ix_parent (parent_id),
  KEY ix_contact (contact_id),
  KEY ix_ref (source, ref),
  KEY ix_moment (moment)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}wms_doc_lines (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  doc_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  qty DECIMAL(14,3) NOT NULL DEFAULT 0,
  price DECIMAL(14,2) NOT NULL DEFAULT 0,
  cell_id INT UNSIGNED NULL,
  to_cell_id INT UNSIGNED NULL,
  sys_qty DECIMAL(14,3) NULL,
  note VARCHAR(255) NULL,
  KEY ix_doc (doc_id),
  KEY ix_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- The stock ledger: every posted document writes rows here; stock = sums of these columns.
CREATE TABLE IF NOT EXISTS {p}wms_moves (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  doc_id INT UNSIGNED NOT NULL,
  line_id INT UNSIGNED NULL,
  product_id INT UNSIGNED NOT NULL,
  warehouse_id INT UNSIGNED NOT NULL,
  cell_id INT UNSIGNED NULL,
  on_hand DECIMAL(14,3) NOT NULL DEFAULT 0,
  reserve DECIMAL(14,3) NOT NULL DEFAULT 0,
  expected DECIMAL(14,3) NOT NULL DEFAULT 0,
  moment DATETIME NOT NULL,
  KEY ix_stock (product_id, warehouse_id, cell_id),
  KEY ix_doc (doc_id),
  KEY ix_moment (moment)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
