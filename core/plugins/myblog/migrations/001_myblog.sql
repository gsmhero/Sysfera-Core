-- My Blog: the posts written on this site (additive only)
CREATE TABLE IF NOT EXISTS {p}myblog_posts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  owner_id INT UNSIGNED NOT NULL,
  title VARCHAR(255) NOT NULL,
  body MEDIUMTEXT NULL,
  cover VARCHAR(60) NULL,
  cover_url VARCHAR(500) NULL,
  products TEXT NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'published',
  scope VARCHAR(12) NOT NULL DEFAULT 'global',
  origin VARCHAR(12) NOT NULL DEFAULT 'sysfera',
  bb_post_id INT UNSIGNED NULL,
  bb_url VARCHAR(500) NULL,
  bb_updated VARCHAR(40) NULL,
  bb_dirty TINYINT(1) NOT NULL DEFAULT 1,
  hub_dirty TINYINT(1) NOT NULL DEFAULT 1,
  deleted TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY ix_owner (owner_id, deleted),
  KEY ix_public (status, deleted, created_at),
  KEY ix_bb (owner_id, bb_post_id),
  KEY ix_hubdirty (hub_dirty)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- used on the hub site only: the short version of every global post of every installation
CREATE TABLE IF NOT EXISTS {p}myblog_hub (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  domain VARCHAR(190) NOT NULL,
  uid INT UNSIGNED NOT NULL,
  post_id INT UNSIGNED NOT NULL,
  author VARCHAR(190) NOT NULL DEFAULT '',
  shop VARCHAR(190) NOT NULL DEFAULT '',
  title VARCHAR(255) NOT NULL,
  excerpt VARCHAR(400) NOT NULL DEFAULT '',
  cover VARCHAR(500) NOT NULL DEFAULT '',
  n_products INT UNSIGNED NOT NULL DEFAULT 0,
  hidden TINYINT(1) NOT NULL DEFAULT 0,
  created DATETIME NOT NULL,
  received TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_post (domain, post_id),
  KEY ix_feed (hidden, created),
  KEY ix_user (domain, uid, received)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
