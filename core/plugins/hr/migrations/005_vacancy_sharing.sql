-- HR 0.6: who a published vacancy is for (only "global" ones are shared with other sites), and the hub's list of the shared ones. Additive.
ALTER TABLE {p}hr_vacancies ADD COLUMN audience VARCHAR(10) NOT NULL DEFAULT 'global';

-- used on the hub site only: the short version of every shared vacancy of every installation
CREATE TABLE IF NOT EXISTS {p}hr_hub (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  domain VARCHAR(190) NOT NULL,
  uuid CHAR(36) NOT NULL,
  title VARCHAR(160) NOT NULL,
  data TEXT NOT NULL,
  published DATETIME NOT NULL,
  received DATETIME NOT NULL,
  UNIQUE KEY ux_vac (domain, uuid),
  KEY ix_feed (published)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
