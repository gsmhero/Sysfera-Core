-- Task type icons are names of one monochrome line-icon set (emoji showed as ???? on databases without utf8mb4).
UPDATE {p}task_types SET icon = 'check' WHERE code = 'todo' AND builtin = 1;
UPDATE {p}task_types SET icon = 'phone' WHERE code = 'call' AND builtin = 1;
UPDATE {p}task_types SET icon = 'mail' WHERE code = 'email' AND builtin = 1;
UPDATE {p}task_types SET icon = 'users' WHERE code = 'meeting' AND builtin = 1;
UPDATE {p}task_types SET icon = 'clock' WHERE code = 'deadline' AND builtin = 1;
UPDATE {p}task_types SET icon = 'link' WHERE code = 'visit_url' AND builtin = 1;
UPDATE {p}task_types SET icon = 'note' WHERE code = 'note' AND builtin = 1;
UPDATE {p}task_types SET icon = 'cart' WHERE code = 'buy_product' AND builtin = 1;
UPDATE {p}task_types SET icon = 'arrow' WHERE code = 'go_to_task' AND builtin = 1;
UPDATE {p}task_types SET icon = 'circle' WHERE code = 'no_action' AND builtin = 1;
UPDATE {p}task_types SET icon = 'checkcircle' WHERE code = 'task_done' AND builtin = 1;
UPDATE {p}task_types SET icon = 'flag' WHERE code = 'finish_mission' AND builtin = 1;
UPDATE {p}task_types SET icon = 'tag' WHERE builtin = 0;
