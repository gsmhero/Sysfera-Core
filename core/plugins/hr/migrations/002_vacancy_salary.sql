-- HR 0.3: the salary range of a vacancy (whole units of the currency; both ends optional). Additive.
ALTER TABLE {p}hr_vacancies ADD COLUMN salary_from INT UNSIGNED NULL;
ALTER TABLE {p}hr_vacancies ADD COLUMN salary_to INT UNSIGNED NULL;
ALTER TABLE {p}hr_vacancies ADD COLUMN salary_cur CHAR(3) NOT NULL DEFAULT 'RUB';
