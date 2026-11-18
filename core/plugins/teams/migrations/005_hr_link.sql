-- Teams 0.4: a team that mirrors a team of the HR Department keeps its uuid (additive).
ALTER TABLE {p}teams_teams ADD COLUMN hr_uuid CHAR(36) NULL;
ALTER TABLE {p}teams_teams ADD UNIQUE KEY ux_hr_uuid (hr_uuid);
