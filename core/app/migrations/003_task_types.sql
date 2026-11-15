-- Task types shared by the components (Missions, CRM). The administrator edits them in "Task types".
CREATE TABLE IF NOT EXISTS {p}task_types (
  code VARCHAR(20) NOT NULL PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  name_ru VARCHAR(80) NOT NULL DEFAULT '',
  icon VARCHAR(16) NOT NULL DEFAULT '',
  color VARCHAR(9) NOT NULL DEFAULT '#6b7280',
  apps VARCHAR(60) NOT NULL DEFAULT 'missions,crm',
  fields TEXT NULL,
  sort INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  builtin TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO {p}task_types (code, name, name_ru, icon, color, apps, fields, sort, active, builtin) VALUES ('todo', 'To-do', 'Задача', '☑', '#6b7280', 'missions,crm', '[]', 10, 1, 1);

INSERT IGNORE INTO {p}task_types (code, name, name_ru, icon, color, apps, fields, sort, active, builtin) VALUES ('call', 'Call', 'Звонок', '☎', '#2563eb', 'missions,crm', '[{"key":"contact_id","kind":"contact","label":"Contact","label_ru":"Контакт"},{"key":"phone","kind":"phone","label":"Phone","label_ru":"Телефон"}]', 20, 1, 1);

INSERT IGNORE INTO {p}task_types (code, name, name_ru, icon, color, apps, fields, sort, active, builtin) VALUES ('email', 'Email', 'Письмо', '✉', '#7c3aed', 'missions,crm', '[{"key":"contact_id","kind":"contact","label":"Contact","label_ru":"Контакт"},{"key":"to","kind":"email","label":"To (email)","label_ru":"Кому (email)"},{"key":"subject","kind":"text","label":"Subject","label_ru":"Тема"},{"key":"body","kind":"textarea","label":"Message","label_ru":"Текст письма"}]', 30, 1, 1);

INSERT IGNORE INTO {p}task_types (code, name, name_ru, icon, color, apps, fields, sort, active, builtin) VALUES ('meeting', 'Meeting', 'Встреча', '👥', '#059669', 'missions,crm', '[{"key":"place","kind":"text","label":"Place","label_ru":"Место"},{"key":"link","kind":"url","label":"Link","label_ru":"Ссылка"}]', 40, 1, 1);

INSERT IGNORE INTO {p}task_types (code, name, name_ru, icon, color, apps, fields, sort, active, builtin) VALUES ('deadline', 'Deadline', 'Дедлайн', '⏰', '#dc2626', 'missions,crm', '[]', 50, 1, 1);

INSERT IGNORE INTO {p}task_types (code, name, name_ru, icon, color, apps, fields, sort, active, builtin) VALUES ('visit_url', 'Visit URL', 'Открыть ссылку', '🔗', '#0891b2', 'missions,crm', '[{"key":"url","kind":"url","label":"Address (https:\/\/…)","label_ru":"Адрес (https:\/\/…)"}]', 60, 1, 1);

INSERT IGNORE INTO {p}task_types (code, name, name_ru, icon, color, apps, fields, sort, active, builtin) VALUES ('note', 'Note', 'Заметка', '📝', '#ca8a04', 'missions,crm', '[{"key":"note","kind":"textarea","label":"Note","label_ru":"Заметка"}]', 70, 1, 1);

INSERT IGNORE INTO {p}task_types (code, name, name_ru, icon, color, apps, fields, sort, active, builtin) VALUES ('buy_product', 'Buy product', 'Купить товар', '🛒', '#ea580c', 'missions,crm', '[{"key":"url","kind":"shop","label":"Basebase shop link","label_ru":"Ссылка на магазин Basebase"}]', 80, 1, 1);

INSERT IGNORE INTO {p}task_types (code, name, name_ru, icon, color, apps, fields, sort, active, builtin) VALUES ('go_to_task', 'Go to task', 'Перейти к задаче', '↪', '#4f46e5', 'missions', '[{"key":"target","kind":"task","label":"Go to","label_ru":"Перейти к"}]', 90, 1, 1);

INSERT IGNORE INTO {p}task_types (code, name, name_ru, icon, color, apps, fields, sort, active, builtin) VALUES ('no_action', 'No action', 'Без действия', '○', '#9ca3af', 'missions', '[]', 100, 1, 1);

INSERT IGNORE INTO {p}task_types (code, name, name_ru, icon, color, apps, fields, sort, active, builtin) VALUES ('task_done', 'Task done', 'Задача выполнена', '✔', '#16a34a', 'missions', '[]', 110, 1, 1);

INSERT IGNORE INTO {p}task_types (code, name, name_ru, icon, color, apps, fields, sort, active, builtin) VALUES ('finish_mission', 'Finish mission', 'Завершить миссию', '🏁', '#16a34a', 'missions', '[]', 120, 1, 1);
