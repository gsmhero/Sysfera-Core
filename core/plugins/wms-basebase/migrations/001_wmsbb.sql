-- Sysfera WMS → Basebase connector (additive only)

-- which WMS products are offered on Basebase, and how
CREATE TABLE IF NOT EXISTS {p}wmsbb_products (
  product_id INT UNSIGNED NOT NULL PRIMARY KEY,
  external_id VARCHAR(120) NOT NULL,
  published TINYINT(1) NOT NULL DEFAULT 0,
  category VARCHAR(120) NULL,
  images TEXT NULL,
  last_stock INT NULL,
  dirty TINYINT(1) NOT NULL DEFAULT 0,
  synced_at DATETIME NULL,
  last_error VARCHAR(255) NULL,
  UNIQUE KEY uq_ext (external_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Basebase order items received (one item = one WMS customer order)
CREATE TABLE IF NOT EXISTS {p}wmsbb_items (
  item_id INT UNSIGNED NOT NULL PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  doc_id INT UNSIGNED NULL,
  product_id INT UNSIGNED NULL,
  external_id VARCHAR(120) NULL,
  title VARCHAR(255) NULL,
  qty DECIMAL(14,3) NOT NULL DEFAULT 0,
  price DECIMAL(14,2) NOT NULL DEFAULT 0,
  seller_receives DECIMAL(14,2) NOT NULL DEFAULT 0,
  customer TEXT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  error VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY ix_doc (doc_id),
  KEY ix_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- webhook events already handled (idempotency)
CREATE TABLE IF NOT EXISTS {p}wmsbb_events (
  event_id VARCHAR(80) NOT NULL PRIMARY KEY,
  event VARCHAR(60) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
