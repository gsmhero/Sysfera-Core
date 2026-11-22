-- Saved column mappings of the product importer (per owner, found again by the signature of the file's headers).
CREATE TABLE IF NOT EXISTS {p}wms_import_templates (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  owner_id INT UNSIGNED NOT NULL,
  name VARCHAR(190) NOT NULL,
  signature CHAR(40) NOT NULL,
  mapping MEDIUMTEXT NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_owner_sig (owner_id, signature)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
