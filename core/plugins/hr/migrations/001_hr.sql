-- Sysfera HR 0.1.0: roles, recruiting, employees, documents, pay, teams, time off, onboarding, consents, payroll runs and the append-only audit chain. Additive only.
-- Relations are by internal id, the outside world uses uuid. Salary, passport, candidate phone and scans are stored sealed (Crypt, key outside the database).

CREATE TABLE IF NOT EXISTS {p}hr_roles (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  uuid CHAR(36) NOT NULL,
  code VARCHAR(40) NOT NULL,
  name VARCHAR(120) NOT NULL,
  name_ru VARCHAR(120) NOT NULL DEFAULT '',
  descr VARCHAR(1000) NOT NULL DEFAULT '',
  perms TEXT NULL,
  builtin TINYINT(1) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  UNIQUE KEY ux_uuid (uuid),
  UNIQUE KEY ux_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}hr_user_roles (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  role_id INT UNSIGNED NOT NULL,
  granted_by VARCHAR(190) NOT NULL DEFAULT '',
  granted_at DATETIME NOT NULL,
  UNIQUE KEY ux_user_role (user_id, role_id),
  KEY ix_role (role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}hr_teams (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  uuid CHAR(36) NOT NULL,
  name VARCHAR(120) NOT NULL,
  descr VARCHAR(500) NOT NULL DEFAULT '',
  warehouse_id INT UNSIGNED NULL,
  mission_ref INT UNSIGNED NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  UNIQUE KEY ux_uuid (uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}hr_team_members (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  team_id INT UNSIGNED NOT NULL,
  employee_id INT UNSIGNED NOT NULL,
  role VARCHAR(10) NOT NULL DEFAULT 'member',
  since DATETIME NOT NULL,
  UNIQUE KEY ux_member (team_id, employee_id),
  KEY ix_emp (employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}hr_schedules (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  uuid CHAR(36) NOT NULL,
  name VARCHAR(100) NOT NULL,
  week VARCHAR(200) NOT NULL,
  UNIQUE KEY ux_uuid (uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}hr_vacancies (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  uuid CHAR(36) NOT NULL,
  role_id INT UNSIGNED NOT NULL,
  team_id INT UNSIGNED NULL,
  title VARCHAR(160) NOT NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'open',
  wf VARCHAR(10) NOT NULL DEFAULT 'draft',
  version INT UNSIGNED NOT NULL DEFAULT 0,
  post_ref VARCHAR(80) NULL,
  external TEXT NULL,
  approved_by VARCHAR(190) NULL,
  created_by VARCHAR(190) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL,
  published_at DATETIME NULL,
  UNIQUE KEY ux_uuid (uuid),
  KEY ix_status (status, wf)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}hr_vacancy_versions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  vacancy_id INT UNSIGNED NOT NULL,
  version INT UNSIGNED NOT NULL,
  description MEDIUMTEXT NOT NULL,
  sha256 CHAR(64) NOT NULL,
  created_by VARCHAR(190) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL,
  UNIQUE KEY ux_ver (vacancy_id, version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}hr_employees (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  uuid CHAR(36) NOT NULL,
  number VARCHAR(20) NOT NULL DEFAULT '',
  user_id INT UNSIGNED NULL,
  full_name VARCHAR(160) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(40) NOT NULL DEFAULT '',
  avatar VARCHAR(300) NOT NULL DEFAULT '',
  birth_date DATE NULL,
  passport_enc TEXT NULL,
  department VARCHAR(120) NOT NULL DEFAULT '',
  position_role_id INT UNSIGNED NULL,
  team_id INT UNSIGNED NULL,
  emp_type VARCHAR(12) NOT NULL DEFAULT 'full_time',
  hire_date DATE NOT NULL,
  manager_id INT UNSIGNED NULL,
  warehouse_id INT UNSIGNED NULL,
  schedule_id INT UNSIGNED NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'active',
  dismissed_at DATE NULL,
  archived_at DATETIME NULL,
  source_candidate_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY ux_uuid (uuid),
  UNIQUE KEY ux_email (email),
  UNIQUE KEY ux_user (user_id),
  KEY ix_team (team_id),
  KEY ix_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}hr_candidates (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  uuid CHAR(36) NOT NULL,
  vacancy_id INT UNSIGNED NOT NULL,
  full_name VARCHAR(160) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone_enc TEXT NULL,
  source VARCHAR(60) NOT NULL DEFAULT '',
  stage VARCHAR(12) NOT NULL DEFAULT 'new',
  employee_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY ux_uuid (uuid),
  UNIQUE KEY ux_vac_email (vacancy_id, email),
  KEY ix_stage (vacancy_id, stage)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}hr_stage_log (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  candidate_id INT UNSIGNED NOT NULL,
  from_stage VARCHAR(12) NOT NULL DEFAULT '',
  to_stage VARCHAR(12) NOT NULL,
  note VARCHAR(500) NOT NULL DEFAULT '',
  `at` DATETIME NOT NULL,
  actor VARCHAR(190) NOT NULL DEFAULT '',
  KEY ix_cand (candidate_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}hr_interviews (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  uuid CHAR(36) NOT NULL,
  candidate_id INT UNSIGNED NOT NULL,
  interviewer_user_id INT UNSIGNED NOT NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NOT NULL,
  place VARCHAR(200) NOT NULL DEFAULT '',
  status VARCHAR(12) NOT NULL DEFAULT 'scheduled',
  created_by VARCHAR(190) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL,
  UNIQUE KEY ux_uuid (uuid),
  KEY ix_interviewer (interviewer_user_id, starts_at),
  KEY ix_cand (candidate_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}hr_feedback (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  interview_id INT UNSIGNED NOT NULL,
  candidate_id INT UNSIGNED NOT NULL,
  interviewer_user_id INT UNSIGNED NOT NULL,
  scores VARCHAR(200) NOT NULL,
  avg DECIMAL(4,2) NOT NULL DEFAULT 0,
  recommend VARCHAR(12) NOT NULL,
  `comment` TEXT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY ux_interview (interview_id),
  KEY ix_cand (candidate_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}hr_status_history (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  employee_id INT UNSIGNED NOT NULL,
  kind VARCHAR(20) NOT NULL,
  data TEXT NULL,
  `at` DATETIME NOT NULL,
  actor VARCHAR(190) NOT NULL DEFAULT '',
  KEY ix_emp (employee_id, `at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}hr_docs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  uuid CHAR(36) NOT NULL,
  employee_id INT UNSIGNED NOT NULL,
  doc_key CHAR(36) NOT NULL,
  kind VARCHAR(20) NOT NULL,
  title VARCHAR(160) NOT NULL,
  version INT UNSIGNED NOT NULL DEFAULT 1,
  body MEDIUMTEXT NOT NULL,
  body_sha256 CHAR(64) NOT NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'unsigned',
  expires_at DATE NULL,
  signed_at DATETIME NULL,
  sign_method VARCHAR(10) NULL,
  scan_key VARCHAR(80) NULL,
  scan_sha256 CHAR(64) NULL,
  superseded TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  created_by VARCHAR(190) NOT NULL DEFAULT '',
  UNIQUE KEY ux_uuid (uuid),
  UNIQUE KEY ux_ver (doc_key, version),
  KEY ix_emp (employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}hr_pay (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  employee_id INT UNSIGNED NOT NULL,
  profile_enc TEXT NOT NULL,
  updated_at DATETIME NOT NULL,
  updated_by VARCHAR(190) NOT NULL DEFAULT '',
  UNIQUE KEY ux_emp (employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}hr_leave (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  uuid CHAR(36) NOT NULL,
  employee_id INT UNSIGNED NOT NULL,
  kind VARCHAR(10) NOT NULL,
  starts_on DATE NOT NULL,
  ends_on DATE NOT NULL,
  days DECIMAL(5,1) NOT NULL DEFAULT 0,
  status VARCHAR(16) NOT NULL,
  note VARCHAR(300) NOT NULL DEFAULT '',
  manager_by VARCHAR(190) NOT NULL DEFAULT '',
  hr_by VARCHAR(190) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL,
  UNIQUE KEY ux_uuid (uuid),
  KEY ix_emp (employee_id, starts_on),
  KEY ix_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}hr_tasks (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  uuid CHAR(36) NOT NULL,
  employee_id INT UNSIGNED NOT NULL,
  phase VARCHAR(12) NOT NULL,
  code VARCHAR(40) NOT NULL,
  done TINYINT(1) NOT NULL DEFAULT 0,
  done_at DATETIME NULL,
  done_by VARCHAR(190) NOT NULL DEFAULT '',
  due_at DATE NOT NULL,
  notified_at DATETIME NULL,
  note VARCHAR(300) NOT NULL DEFAULT '',
  UNIQUE KEY ux_uuid (uuid),
  UNIQUE KEY ux_item (employee_id, phase, code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}hr_consents (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  employee_id INT UNSIGNED NOT NULL,
  kind VARCHAR(40) NOT NULL,
  version VARCHAR(20) NOT NULL,
  action VARCHAR(8) NOT NULL,
  `at` DATETIME NOT NULL,
  actor VARCHAR(190) NOT NULL DEFAULT '',
  KEY ix_emp (employee_id, kind)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}hr_payroll_runs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  export_id CHAR(36) NOT NULL,
  period_from DATE NOT NULL,
  period_to DATE NOT NULL,
  document MEDIUMTEXT NOT NULL,
  created_at DATETIME NOT NULL,
  created_by VARCHAR(190) NOT NULL DEFAULT '',
  UNIQUE KEY ux_export (export_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- append-only, hash-chained: the plugin has no UPDATE / DELETE statement for this table
CREATE TABLE IF NOT EXISTS {p}hr_audit (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ts DATETIME NOT NULL,
  actor VARCHAR(190) NOT NULL,
  action VARCHAR(40) NOT NULL,
  entity VARCHAR(20) NOT NULL,
  ref VARCHAR(64) NOT NULL DEFAULT '',
  data TEXT NULL,
  prev_hash CHAR(64) NOT NULL,
  hash CHAR(64) NOT NULL,
  KEY ix_entity (entity, ref),
  KEY ix_action (action, ts)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
