-- Sysfera Activity: tables are only used on the hub site (core.sysfera.ru).

CREATE TABLE IF NOT EXISTS {p}act_posts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  sid VARCHAR(220) NOT NULL,
  title VARCHAR(400) NOT NULL,
  body TEXT NOT NULL,
  hidden TINYINT(1) NOT NULL DEFAULT 0,
  reports INT UNSIGNED NOT NULL DEFAULT 0,
  created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_feed (hidden, id),
  KEY ix_sid (sid, created)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}act_reports (
  post_id INT UNSIGNED NOT NULL,
  sid VARCHAR(220) NOT NULL,
  PRIMARY KEY (post_id, sid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
