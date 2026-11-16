CREATE TABLE IF NOT EXISTS {p}teams_invites (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  team_id INT UNSIGNED NOT NULL,
  email VARCHAR(190) NULL,
  role ENUM('admin','member') NOT NULL DEFAULT 'member',
  token CHAR(32) NOT NULL,
  invited_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  used_by INT UNSIGNED NULL,
  UNIQUE KEY uq_token (token),
  KEY ix_team (team_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
