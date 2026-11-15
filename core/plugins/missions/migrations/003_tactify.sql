-- Missions 0.3: the same categories as on tactify.ru (added only when a category of that name does not exist yet), public missions can hide their uploaded files.

INSERT INTO {p}mis_categories (name, name_ru, color, sort_order) SELECT 'FAQ', 'FAQ', '#0891b2', 10 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM {p}mis_categories WHERE name = 'FAQ');

INSERT INTO {p}mis_categories (name, name_ru, color, sort_order) SELECT 'Lifehack', 'Лайфхак', '#7c3aed', 20 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM {p}mis_categories WHERE name = 'Lifehack');

INSERT INTO {p}mis_categories (name, name_ru, color, sort_order) SELECT 'Logistics', 'Логистика', '#ea580c', 30 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM {p}mis_categories WHERE name = 'Logistics');

INSERT INTO {p}mis_categories (name, name_ru, color, sort_order) SELECT 'Repair', 'Ремонт', '#dc2626', 40 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM {p}mis_categories WHERE name = 'Repair');

INSERT INTO {p}mis_categories (name, name_ru, color, sort_order) SELECT 'Sales', 'Продажи', '#2563eb', 50 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM {p}mis_categories WHERE name = 'Sales');

INSERT INTO {p}mis_categories (name, name_ru, color, sort_order) SELECT 'Marketing', 'Маркетинг', '#c026d3', 60 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM {p}mis_categories WHERE name = 'Marketing');

INSERT INTO {p}mis_categories (name, name_ru, color, sort_order) SELECT 'Building', 'Строительство', '#ca8a04', 70 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM {p}mis_categories WHERE name = 'Building');

INSERT INTO {p}mis_categories (name, name_ru, color, sort_order) SELECT 'Development', 'Разработка', '#059669', 80 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM {p}mis_categories WHERE name = 'Development');

INSERT INTO {p}mis_categories (name, name_ru, color, sort_order) SELECT 'Finance', 'Финансы', '#d97706', 90 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM {p}mis_categories WHERE name = 'Finance');

ALTER TABLE {p}mis_missions ADD COLUMN hide_files TINYINT(1) NOT NULL DEFAULT 0;
