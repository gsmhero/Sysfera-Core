-- Goods issue 0.2.0: shelf cells, handover to couriers, operator permissions, and one generic table for the new documents (returns, stock counts, adjustment acts, rules, per-warehouse settings, weekly registries). Additive only.
ALTER TABLE {p}gi_orders ADD COLUMN shelf VARCHAR(20) NULL, ADD COLUMN handover VARCHAR(10) NULL, ADD COLUMN courier TEXT NULL, ADD KEY ix_shelf (warehouse_id, shelf);
ALTER TABLE {p}gi_staff ADD COLUMN perms VARCHAR(160) NOT NULL DEFAULT '';
CREATE TABLE IF NOT EXISTS {p}gi_records (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  kind VARCHAR(16) NOT NULL,
  ref VARCHAR(80) NOT NULL,
  warehouse_id INT UNSIGNED NOT NULL DEFAULT 0,
  status VARCHAR(12) NOT NULL DEFAULT '',
  data MEDIUMTEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY ux_ref (kind, ref),
  KEY ix_kind (kind, status, warehouse_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
