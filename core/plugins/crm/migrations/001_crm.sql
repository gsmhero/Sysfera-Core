-- Sysfera CRM schema (additive only). Clients live in the shared core table {p}contacts; their custom CRM values are kept in contacts.custom under "crm".

-- A pipeline is a "project type": its own stages and its own extra deal fields.
CREATE TABLE IF NOT EXISTS {p}crm_pipelines (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  color VARCHAR(9) NOT NULL DEFAULT '#4f46e5',
  sort INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}crm_stages (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  pipeline_id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  prob TINYINT UNSIGNED NOT NULL DEFAULT 0,
  kind ENUM('open','won','lost') NOT NULL DEFAULT 'open',
  color VARCHAR(9) NOT NULL DEFAULT '#6b7280',
  sort INT NOT NULL DEFAULT 0,
  KEY ix_pipe (pipeline_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Custom field definitions. pipeline_id NULL = for every pipeline.
CREATE TABLE IF NOT EXISTS {p}crm_fields (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  entity ENUM('contact','deal','task') NOT NULL,
  pipeline_id INT UNSIGNED NULL,
  code VARCHAR(40) NOT NULL,
  label VARCHAR(120) NOT NULL,
  type VARCHAR(20) NOT NULL DEFAULT 'text',
  options TEXT NULL,
  required TINYINT(1) NOT NULL DEFAULT 0,
  sort INT NOT NULL DEFAULT 0,
  KEY ix_entity (entity, pipeline_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}crm_deals (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  pipeline_id INT UNSIGNED NOT NULL,
  stage_id INT UNSIGNED NOT NULL,
  title VARCHAR(190) NOT NULL,
  contact_id INT UNSIGNED NULL,
  amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  currency VARCHAR(8) NOT NULL DEFAULT 'RUB',
  owner_id INT UNSIGNED NULL,
  expected_close DATE NULL,
  lost_reason VARCHAR(190) NULL,
  tags VARCHAR(400) NULL,
  extra MEDIUMTEXT NULL,
  closed_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_pipe_stage (pipeline_id, stage_id),
  KEY ix_owner (owner_id),
  KEY ix_contact (contact_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}crm_tasks (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  deal_id INT UNSIGNED NULL,
  contact_id INT UNSIGNED NULL,
  type VARCHAR(20) NOT NULL DEFAULT 'todo',
  title VARCHAR(190) NOT NULL,
  notes TEXT NULL,
  due_at DATETIME NULL,
  done_at DATETIME NULL,
  assignee_id INT UNSIGNED NULL,
  created_by INT UNSIGNED NULL,
  extra MEDIUMTEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_assignee (assignee_id, done_at),
  KEY ix_deal (deal_id),
  KEY ix_contact (contact_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- History of a deal or a client: notes and automatic entries (created, stage changed, task done ...).
CREATE TABLE IF NOT EXISTS {p}crm_timeline (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  deal_id INT UNSIGNED NULL,
  contact_id INT UNSIGNED NULL,
  user_id INT UNSIGNED NULL,
  kind VARCHAR(20) NOT NULL DEFAULT 'note',
  body TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_deal (deal_id),
  KEY ix_contact (contact_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Permission profiles ("user profiles") and who has which.
CREATE TABLE IF NOT EXISTS {p}crm_profiles (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  perms TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}crm_user_profile (
  user_id INT UNSIGNED NOT NULL PRIMARY KEY,
  profile_id INT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
