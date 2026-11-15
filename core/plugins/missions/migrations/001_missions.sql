-- Missions component. Every mission and task belongs to one user; only shared (library) missions are visible to others.
CREATE TABLE IF NOT EXISTS {p}mis_categories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  name_ru VARCHAR(100) NOT NULL DEFAULT '',
  color VARCHAR(20) NOT NULL DEFAULT '#6b7280',
  sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}mis_missions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  title VARCHAR(255) NOT NULL,
  type VARCHAR(100) NOT NULL DEFAULT '',
  category_id INT UNSIGNED NULL,
  description TEXT NULL,
  due_date DATE NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'active',
  shared TINYINT(1) NOT NULL DEFAULT 0,
  lucky_way TEXT NULL,
  origin_id INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY ix_user (user_id),
  KEY ix_shared (shared)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}mis_tasks (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  mission_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  parent_id INT UNSIGNED NULL,
  branch VARCHAR(4) NULL,
  title VARCHAR(255) NOT NULL,
  type VARCHAR(40) NOT NULL DEFAULT 'Note',
  cost DECIMAL(12,2) NOT NULL DEFAULT 0,
  description TEXT NULL,
  result_good VARCHAR(255) NOT NULL DEFAULT '',
  result_alt VARCHAR(255) NOT NULL DEFAULT '',
  result_bad VARCHAR(255) NOT NULL DEFAULT '',
  type_data TEXT NULL,
  scheduled_at DATETIME NULL,
  reminder TINYINT(1) NOT NULL DEFAULT 0,
  reminder_sent TINYINT(1) NOT NULL DEFAULT 0,
  completed TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_mission (mission_id),
  KEY ix_parent (parent_id),
  KEY ix_due (reminder, reminder_sent, scheduled_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
