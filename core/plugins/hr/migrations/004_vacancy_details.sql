-- HR 0.5: the structured description of a vacancy (JSON: place and format, schedule, employment, terms, duties, requirements...), kept per version. Additive.
ALTER TABLE {p}hr_vacancy_versions ADD COLUMN details MEDIUMTEXT NULL;
