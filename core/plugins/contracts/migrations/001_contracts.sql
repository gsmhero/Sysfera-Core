-- Sysfera Contracts 0.1.0 (ported from the standalone Bablito app). Additive only. Users are the Sysfera users (no own accounts).
-- Money is DECIMAL(12,2) as in the original; relations are by id, no foreign keys to the core tables.

CREATE TABLE IF NOT EXISTS {p}ct_profiles (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  user_types VARCHAR(40) NOT NULL DEFAULT 'personal',
  about TEXT NULL,
  notify_email TINYINT(1) NOT NULL DEFAULT 1,
  rating_avg DECIMAL(3,2) NOT NULL DEFAULT 0,
  rating_count INT UNSIGNED NOT NULL DEFAULT 0,
  UNIQUE KEY ux_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}ct_categories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  name_ru VARCHAR(80) NULL,
  UNIQUE KEY ux_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}ct_contracts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  title VARCHAR(190) NOT NULL,
  category_id INT UNSIGNED NULL,
  location VARCHAR(190) NOT NULL DEFAULT '',
  description TEXT NULL,
  status ENUM('draft','published','active','paused','fulfilled','terminated') NOT NULL DEFAULT 'draft',
  allow_extra_todos TINYINT(1) NOT NULL DEFAULT 1,
  prepay_required TINYINT(1) NOT NULL DEFAULT 0,
  prepaid_at DATETIME NULL,
  deadline DATE NULL,
  currency CHAR(3) NOT NULL DEFAULT 'RUB',
  author_type VARCHAR(10) NOT NULL DEFAULT 'personal',
  payment_type VARCHAR(10) NOT NULL DEFAULT 'finish',
  contractor_id INT UNSIGNED NULL,
  accepted_offer_id INT UNSIGNED NULL,
  agreed_price DECIMAL(12,2) NULL,
  agreed_days INT UNSIGNED NULL,
  agreed_deadline DATE NULL,
  started_at DATETIME NULL,
  published_at DATETIME NULL,
  views INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY ix_status (status, created_at), KEY ix_user (user_id), KEY ix_contractor (contractor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}ct_todos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  contract_id INT UNSIGNED NOT NULL,
  position INT UNSIGNED NOT NULL DEFAULT 0,
  name VARCHAR(190) NOT NULL,
  description TEXT NULL,
  price DECIMAL(12,2) NOT NULL DEFAULT 0,
  days INT UNSIGNED NOT NULL DEFAULT 1,
  agreed_price DECIMAL(12,2) NULL,
  agreed_days INT UNSIGNED NULL,
  done_at DATETIME NULL,
  accepted_at DATETIME NULL,
  KEY ix_contract (contract_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}ct_offers (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  contract_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  price DECIMAL(12,2) NOT NULL,
  days INT UNSIGNED NOT NULL,
  notes TEXT NULL,
  payment_type VARCHAR(10) NOT NULL DEFAULT 'finish',
  offerer_type VARCHAR(10) NOT NULL DEFAULT 'personal',
  status ENUM('pending','accepted') NOT NULL DEFAULT 'pending',
  seen_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY ux_contract_user (contract_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}ct_offer_todos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  offer_id INT UNSIGNED NOT NULL,
  todo_id INT UNSIGNED NOT NULL,
  price DECIMAL(12,2) NOT NULL,
  days INT UNSIGNED NOT NULL,
  position INT NULL,
  excluded TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY ux_offer_todo (offer_id, todo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}ct_offer_extra (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  offer_id INT UNSIGNED NOT NULL,
  position INT UNSIGNED NOT NULL DEFAULT 0,
  name VARCHAR(190) NOT NULL,
  description TEXT NULL,
  price DECIMAL(12,2) NOT NULL DEFAULT 0,
  days INT UNSIGNED NOT NULL DEFAULT 0,
  KEY ix_offer (offer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}ct_comments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  contract_id INT UNSIGNED NOT NULL,
  scope ENUM('public','offer','work','todo') NOT NULL,
  offer_id INT UNSIGNED NULL,
  thread_user_id INT UNSIGNED NULL,
  todo_id INT UNSIGNED NULL,
  user_id INT UNSIGNED NOT NULL,
  body TEXT NOT NULL,
  created_at DATETIME NOT NULL,
  KEY ix_contract_scope (contract_id, scope), KEY ix_offer (offer_id), KEY ix_todo (todo_id), KEY ix_thread (thread_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}ct_files (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  contract_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  kind ENUM('image','file') NOT NULL,
  stored_name VARCHAR(80) NOT NULL,
  original_name VARCHAR(190) NOT NULL,
  by_contractor TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  KEY ix_contract (contract_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}ct_reviews (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  contract_id INT UNSIGNED NOT NULL,
  reviewer_id INT UNSIGNED NOT NULL,
  reviewed_id INT UNSIGNED NOT NULL,
  rating TINYINT UNSIGNED NOT NULL,
  body TEXT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY ux_contract_reviewer (contract_id, reviewer_id),
  KEY ix_reviewed (reviewed_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}ct_watch (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  contract_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY ux_user_contract (user_id, contract_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}ct_invoices (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  contract_id INT UNSIGNED NOT NULL,
  todo_id INT UNSIGNED NULL,
  payer_id INT UNSIGNED NOT NULL,
  payee_id INT UNSIGNED NOT NULL,
  number VARCHAR(40) NULL,
  amount DECIMAL(12,2) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'RUB',
  status VARCHAR(10) NOT NULL DEFAULT 'waiting',
  created_at DATETIME NOT NULL,
  paid_at DATETIME NULL,
  UNIQUE KEY ux_number (number), KEY ix_payer (payer_id, status), KEY ix_payee (payee_id, status), KEY ix_todo (todo_id), KEY ix_contract (contract_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}ct_notices (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  kind VARCHAR(30) NOT NULL,
  contract_id INT UNSIGNED NULL,
  data TEXT NULL,
  url VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  read_at DATETIME NULL,
  KEY ix_user (user_id, read_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}ct_psections (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(120) NOT NULL,
  type VARCHAR(10) NOT NULL DEFAULT 'personal',
  position INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}ct_pfields (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  section_id INT UNSIGNED NOT NULL,
  label VARCHAR(120) NOT NULL,
  type VARCHAR(20) NOT NULL DEFAULT 'text',
  options TEXT NULL,
  required TINYINT(1) NOT NULL DEFAULT 0,
  default_public TINYINT(1) NOT NULL DEFAULT 1,
  position INT NOT NULL DEFAULT 0,
  KEY ix_section (section_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {p}ct_pvalues (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  field_id INT UNSIGNED NOT NULL,
  value TEXT NULL,
  is_public TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY ux_user_field (user_id, field_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO {p}ct_categories (name, name_ru) VALUES ('Software', 'Программирование'), ('Design', 'Дизайн'), ('Repair', 'Ремонт'), ('Construction', 'Строительство'), ('Writing', 'Тексты'), ('Marketing', 'Маркетинг'), ('Admin & Management', 'Администрирование и управление'), ('Other', 'Другое');
