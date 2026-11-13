-- One Basebase seller per Sysfera user (they all use the Agent's credentials, the shop name is the user's own).
CREATE TABLE IF NOT EXISTS {p}wmsbb_sellers (
  owner_id INT UNSIGNED NOT NULL PRIMARY KEY,
  seller_id INT UNSIGNED NOT NULL DEFAULT 0,
  shop_name VARCHAR(190) NULL,
  warehouse_ids TEXT NULL,
  since_id INT UNSIGNED NOT NULL DEFAULT 0,
  last_poll INT UNSIGNED NOT NULL DEFAULT 0,
  KEY ix_seller (seller_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
ALTER TABLE {p}wmsbb_items ADD COLUMN owner_id INT UNSIGNED NULL;
ALTER TABLE {p}wmsbb_items ADD KEY ix_owner (owner_id);