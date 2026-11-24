-- Sysfera Storage Rent 0.4.0: Offers (public listings of storage for rent) and the cache of other domains' feeds. Additive only.

CREATE TABLE IF NOT EXISTS {p}sr_listings (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  warehouse_id INT UNSIGNED NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'published',
  name VARCHAR(190) NOT NULL,
  city VARCHAR(100) NOT NULL DEFAULT '',
  street VARCHAR(190) NOT NULL DEFAULT '',
  lat DECIMAL(9,6) NULL,
  lon DECIMAL(9,6) NULL,
  area DECIMAL(10,2) NOT NULL DEFAULT 0,
  price_m2 BIGINT NOT NULL DEFAULT 0,
  svc_video TINYINT NOT NULL DEFAULT 0,
  svc_parking TINYINT NOT NULL DEFAULT 0,
  svc_24h TINYINT NOT NULL DEFAULT 0,
  svc_security TINYINT NOT NULL DEFAULT 0,
  data MEDIUMTEXT NULL,
  contact_name VARCHAR(120) NOT NULL DEFAULT '',
  created_at INT UNSIGNED NOT NULL,
  updated_at INT UNSIGNED NOT NULL,
  KEY ix_user (user_id),
  KEY ix_status (status, city)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}sr_listing_photos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  listing_id INT UNSIGNED NOT NULL,
  file_key VARCHAR(64) NOT NULL,
  mime VARCHAR(40) NOT NULL DEFAULT '',
  size INT UNSIGNED NOT NULL DEFAULT 0,
  sort SMALLINT NOT NULL DEFAULT 0,
  created_at INT UNSIGNED NOT NULL,
  KEY ix_listing (listing_id, sort)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}sr_remote_cache (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  url_hash CHAR(40) NOT NULL,
  url VARCHAR(255) NOT NULL,
  fetched_at INT UNSIGNED NOT NULL,
  body MEDIUMTEXT NULL,
  UNIQUE KEY ux_url (url_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
