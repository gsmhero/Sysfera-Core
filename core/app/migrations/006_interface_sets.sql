-- Interface sets: each user's own choices of which components the menu shows. At most one per user is active; none active = show everything.
CREATE TABLE IF NOT EXISTS {p}ui_sets (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  name VARCHAR(60) NOT NULL,
  items TEXT NULL,
  active TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
