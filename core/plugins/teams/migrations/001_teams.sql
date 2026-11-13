-- Sysfera Teams schema (additive only). Users are the core users; files are kept in storage/teams (not reachable from the web).

CREATE TABLE IF NOT EXISTS {p}teams_teams (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  descr VARCHAR(500) NULL,
  color VARCHAR(9) NOT NULL DEFAULT '#4f46e5',
  privacy ENUM('open','private') NOT NULL DEFAULT 'private',
  created_by INT UNSIGNED NULL,
  archived TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}teams_members (
  team_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  role ENUM('owner','admin','member') NOT NULL DEFAULT 'member',
  joined_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (team_id, user_id),
  KEY ix_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}teams_channels (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  team_id INT UNSIGNED NOT NULL,
  name VARCHAR(60) NOT NULL,
  topic VARCHAR(250) NULL,
  is_private TINYINT(1) NOT NULL DEFAULT 0,
  archived TINYINT(1) NOT NULL DEFAULT 0,
  sort INT NOT NULL DEFAULT 0,
  created_by INT UNSIGNED NULL,
  KEY ix_team (team_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- members of a private channel (public channels are open to every team member)
CREATE TABLE IF NOT EXISTS {p}teams_channel_members (
  channel_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (channel_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}teams_files (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  team_id INT UNSIGNED NOT NULL,
  channel_id INT UNSIGNED NULL,
  user_id INT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  ext VARCHAR(8) NOT NULL,
  size INT UNSIGNED NOT NULL,
  stored VARCHAR(64) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_team (team_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}teams_messages (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  channel_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NULL,
  reply_to INT UNSIGNED NULL,
  body TEXT NULL,
  file_id INT UNSIGNED NULL,
  pinned TINYINT(1) NOT NULL DEFAULT 0,
  deleted TINYINT(1) NOT NULL DEFAULT 0,
  edited_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_channel (channel_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}teams_reactions (
  message_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  emoji VARCHAR(16) NOT NULL,
  PRIMARY KEY (message_id, user_id, emoji)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}teams_mentions (
  message_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  channel_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (message_id, user_id),
  KEY ix_user (user_id, channel_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- the last message each user has seen in a channel (unread counters)
CREATE TABLE IF NOT EXISTS {p}teams_reads (
  channel_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  last_id INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (channel_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}teams_cols (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  team_id INT UNSIGNED NOT NULL,
  name VARCHAR(60) NOT NULL,
  is_done TINYINT(1) NOT NULL DEFAULT 0,
  sort INT NOT NULL DEFAULT 0,
  KEY ix_team (team_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}teams_tasks (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  team_id INT UNSIGNED NOT NULL,
  col_id INT UNSIGNED NOT NULL,
  title VARCHAR(190) NOT NULL,
  descr TEXT NULL,
  assignee_id INT UNSIGNED NULL,
  due DATE NULL,
  prio TINYINT UNSIGNED NOT NULL DEFAULT 1,
  created_by INT UNSIGNED NULL,
  done_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_team_col (team_id, col_id),
  KEY ix_assignee (assignee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}teams_events (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  team_id INT UNSIGNED NOT NULL,
  title VARCHAR(190) NOT NULL,
  descr TEXT NULL,
  starts DATETIME NOT NULL,
  ends DATETIME NULL,
  created_by INT UNSIGNED NULL,
  KEY ix_team_start (team_id, starts)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
