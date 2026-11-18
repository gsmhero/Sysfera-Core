-- CRM 0.4: a discussion under every task: comments with attachments (the files live in core/storage/crm-tasks, outside the web root).
CREATE TABLE IF NOT EXISTS {p}crm_task_comments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  task_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NULL,
  body TEXT NULL,
  created_at DATETIME NOT NULL,
  KEY ix_task (task_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}crm_task_files (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  comment_id INT UNSIGNED NOT NULL,
  task_id INT UNSIGNED NOT NULL,
  original_name VARCHAR(190) NOT NULL,
  stored_name VARCHAR(60) NOT NULL,
  size INT UNSIGNED NOT NULL DEFAULT 0,
  KEY ix_comment (comment_id),
  KEY ix_task (task_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
