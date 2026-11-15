-- Missions 0.2: shared task types (codes instead of names), team sharing, notification list, attachments, bookmarks, language per mission.

UPDATE {p}mis_tasks SET type = 'call' WHERE type = 'Call';

UPDATE {p}mis_tasks SET type = 'email' WHERE type = 'Email';

UPDATE {p}mis_tasks SET type = 'meeting' WHERE type = 'Meeting';

UPDATE {p}mis_tasks SET type = 'visit_url' WHERE type = 'Visit URL';

UPDATE {p}mis_tasks SET type = 'note' WHERE type = 'Note';

UPDATE {p}mis_tasks SET type = 'go_to_task' WHERE type = 'Go to task';

UPDATE {p}mis_tasks SET type = 'no_action' WHERE type = 'No action';

UPDATE {p}mis_tasks SET type = 'task_done' WHERE type = 'Task Done';

UPDATE {p}mis_tasks SET type = 'finish_mission' WHERE type = 'Finish Mission';

UPDATE {p}mis_tasks SET type = 'buy_product' WHERE type = 'Buy product';

ALTER TABLE {p}mis_missions ADD COLUMN lang VARCHAR(5) NOT NULL DEFAULT '', ADD COLUMN announce TINYINT(1) NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS {p}mis_shares (
  mission_id INT UNSIGNED NOT NULL,
  team_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (mission_id, team_id),
  KEY ix_team (team_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}mis_notes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  mission_id INT UNSIGNED NULL,
  task_id INT UNSIGNED NULL,
  kind VARCHAR(20) NOT NULL DEFAULT 'reminder',
  body VARCHAR(500) NOT NULL,
  read_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_user (user_id, read_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}mis_files (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  mission_id INT UNSIGNED NOT NULL,
  task_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  ext VARCHAR(8) NOT NULL,
  size INT UNSIGNED NOT NULL,
  blob_key VARCHAR(64) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_task (task_id),
  KEY ix_mission (mission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}mis_bookmarks (
  user_id INT UNSIGNED NOT NULL,
  mission_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (user_id, mission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
