-- Missions 0.4: the public library of the whole Sysfera network (kept on the hub) and the "published to the network" flag of a mission.

CREATE TABLE IF NOT EXISTS {p}mis_net (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  source VARCHAR(190) NOT NULL,
  ext_id VARCHAR(120) NOT NULL,
  owner_sid VARCHAR(220) NOT NULL DEFAULT '',
  title VARCHAR(255) NOT NULL,
  type VARCHAR(100) NOT NULL DEFAULT '',
  category VARCHAR(100) NOT NULL DEFAULT '',
  category_ru VARCHAR(100) NOT NULL DEFAULT '',
  description TEXT NULL,
  author VARCHAR(100) NOT NULL DEFAULT '',
  tasks_n INT NOT NULL DEFAULT 0,
  content MEDIUMTEXT NULL,
  hidden TINYINT(1) NOT NULL DEFAULT 0,
  reports INT NOT NULL DEFAULT 0,
  src_updated DATETIME NULL,
  tactify_pushed DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY src_ext (source, ext_id),
  KEY upd (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}mis_net_reports (
  net_id INT UNSIGNED NOT NULL,
  sid VARCHAR(220) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (net_id, sid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE {p}mis_missions ADD COLUMN net_pub TINYINT(1) NOT NULL DEFAULT 0;
