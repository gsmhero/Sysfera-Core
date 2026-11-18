-- HR 0.4: the skills a vacancy asks for (a JSON list of short texts). Additive.
ALTER TABLE {p}hr_vacancies ADD COLUMN skills TEXT NULL;
