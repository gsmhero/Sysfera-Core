-- Product types: simple / variable / grouped / virtual / downloadable / bundle. Components (variations, grouped items, bundle parts) are other products.
ALTER TABLE {p}wms_products ADD COLUMN ptype VARCHAR(12) NOT NULL DEFAULT 'simple' AFTER name;
ALTER TABLE {p}wms_products ADD COLUMN download_url VARCHAR(500) NULL AFTER images;
CREATE TABLE IF NOT EXISTS {p}wms_product_links (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  parent_id INT UNSIGNED NOT NULL,
  child_id INT UNSIGNED NOT NULL,
  label VARCHAR(120) NULL,
  qty DECIMAL(14,3) NOT NULL DEFAULT 1,
  price DECIMAL(14,2) NOT NULL DEFAULT 0,
  sort INT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_link (parent_id, child_id),
  KEY k_child (child_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
