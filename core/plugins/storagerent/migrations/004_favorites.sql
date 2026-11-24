-- Sysfera Storage Rent: favorite listings of a user (local ones and ones of other domains, kept as a snapshot). Additive only.

CREATE TABLE IF NOT EXISTS {p}sr_favorites (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  ref VARCHAR(40) NOT NULL,
  snapshot TEXT NOT NULL,
  created_at INT UNSIGNED NOT NULL,
  UNIQUE KEY ux_fav (user_id, ref),
  KEY ix_user (user_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
