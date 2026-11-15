<?php
declare(strict_types=1);
namespace Sysfera;

/**
 * Task types shared by the components (Missions, CRM ...): one list, edited by the administrator in "Task types".
 * A type has a code, names (EN/RU), an icon, a colour, the components it is offered in ("apps") and the extra fields of its tasks
 * (kept by the component in the task's type_data). The built-in types work even when the table is missing (before the migration ran).
 */
final class TaskTypes
{
    /** field kinds; the component draws the input, clean() validates the value */
    public const KINDS = ['text', 'textarea', 'email', 'phone', 'url', 'shop', 'task', 'contact'];
    public const APPS = ['missions', 'crm'];

    /** the built-in types: code => [name, name_ru, icon, color, apps, fields] (field = [key, kind, label, label_ru]) */
    public const DEFAULTS = [
        'todo'           => ['To-do', 'Задача', '☑', '#6b7280', 'missions,crm', []],
        'call'           => ['Call', 'Звонок', '☎', '#2563eb', 'missions,crm', [['contact_id', 'contact', 'Contact', 'Контакт'], ['phone', 'phone', 'Phone', 'Телефон']]],
        'email'          => ['Email', 'Письмо', '✉', '#7c3aed', 'missions,crm', [['contact_id', 'contact', 'Contact', 'Контакт'], ['to', 'email', 'To (email)', 'Кому (email)'], ['subject', 'text', 'Subject', 'Тема'], ['body', 'textarea', 'Message', 'Текст письма']]],
        'meeting'        => ['Meeting', 'Встреча', '👥', '#059669', 'missions,crm', [['place', 'text', 'Place', 'Место'], ['link', 'url', 'Link', 'Ссылка']]],
        'deadline'       => ['Deadline', 'Дедлайн', '⏰', '#dc2626', 'missions,crm', []],
        'visit_url'      => ['Visit URL', 'Открыть ссылку', '🔗', '#0891b2', 'missions,crm', [['url', 'url', 'Address (https://…)', 'Адрес (https://…)']]],
        'note'           => ['Note', 'Заметка', '📝', '#ca8a04', 'missions,crm', [['note', 'textarea', 'Note', 'Заметка']]],
        'buy_product'    => ['Buy product', 'Купить товар', '🛒', '#ea580c', 'missions,crm', [['url', 'shop', 'Basebase shop link', 'Ссылка на магазин Basebase']]],
        'go_to_task'     => ['Go to task', 'Перейти к задаче', '↪', '#4f46e5', 'missions', [['target', 'task', 'Go to', 'Перейти к']]],
        'no_action'      => ['No action', 'Без действия', '○', '#9ca3af', 'missions', []],
        'task_done'      => ['Task done', 'Задача выполнена', '✔', '#16a34a', 'missions', []],
        'finish_mission' => ['Finish mission', 'Завершить миссию', '🏁', '#16a34a', 'missions', []],
    ];

    /** names used by older versions and by Tactify exports => code */
    private const LEGACY = ['finish mission' => 'finish_mission', 'task done' => 'task_done', 'to-do' => 'todo', 'e-mail' => 'email'];

    /** a code from any spelling ("Finish Mission", "go to task", "visit_url") */
    public static function normalize(string $t): string
    {
        $k = strtolower(trim($t));
        if (isset(self::LEGACY[$k])) return self::LEGACY[$k];
        return trim((string)preg_replace('/[^a-z0-9]+/', '_', $k), '_');
    }

    public static function code(string $c): string { return substr(trim((string)preg_replace('/[^a-z0-9]+/', '_', strtolower($c)), '_'), 0, 20); }

    /** field definitions of a type, tidy: [{key,kind,label,label_ru}] */
    public static function fieldDefs(mixed $raw): array
    {
        $a = is_string($raw) ? json_decode($raw, true) : $raw; $out = []; $seen = [];
        foreach (is_array($a) ? $a : [] as $f) {
            if (array_is_list($f) && count($f) >= 2) $f = ['key' => $f[0], 'kind' => $f[1], 'label' => $f[2] ?? '', 'label_ru' => $f[3] ?? ''];
            if (!is_array($f)) continue;
            $key = strtolower((string)($f['key'] ?? ''));
            if (!preg_match('/^[a-z][a-z0-9_]{0,23}$/', $key) || isset($seen[$key])) continue;
            $kind = in_array($f['kind'] ?? '', self::KINDS, true) ? $f['kind'] : 'text';
            $seen[$key] = 1;
            $out[] = ['key' => $key, 'kind' => $kind, 'label' => mb_substr(trim((string)($f['label'] ?? '')), 0, 60) ?: $key, 'label_ru' => mb_substr(trim((string)($f['label_ru'] ?? '')), 0, 60)];
            if (count($out) >= 8) break;
        }
        return $out;
    }

    /** the built-in list as rows (same shape as the table) */
    public static function defaults(): array
    {
        $out = []; $i = 0;
        foreach (self::DEFAULTS as $code => [$n, $nr, $icon, $color, $apps, $fields]) $out[$code] = ['code' => $code, 'name' => $n, 'name_ru' => $nr, 'icon' => $icon, 'color' => $color, 'apps' => $apps, 'fields' => self::fieldDefs($fields), 'sort' => ++$i * 10, 'active' => 1, 'builtin' => 1];
        return $out;
    }

    /** @return array<string,array> code => type; $app limits to the types offered in that component; inactive types only with $all */
    public static function all(?Db $db = null, ?string $app = null, bool $all = false): array
    {
        $rows = null;
        if ($db) { try { $rows = $db->rows('SELECT * FROM {p}task_types ORDER BY sort, code'); } catch (\Throwable) { $rows = null; } }
        $out = [];
        if (!$rows) $out = self::defaults();
        else foreach ($rows as $r) $out[$r['code']] = ['code' => $r['code'], 'name' => $r['name'], 'name_ru' => (string)$r['name_ru'], 'icon' => (string)$r['icon'], 'color' => (string)$r['color'], 'apps' => (string)$r['apps'],
            'fields' => self::fieldDefs($r['fields']), 'sort' => (int)$r['sort'], 'active' => (int)$r['active'], 'builtin' => (int)$r['builtin']];
        return array_filter($out, fn($t) => ($all || $t['active']) && ($app === null || in_array($app, explode(',', $t['apps']), true)));
    }

    /** a stored type: unknown codes fall back to $fallback (a deleted type must not break the old tasks) */
    public static function get(array $types, string $code, string $fallback = 'note'): array
    {
        $code = self::normalize($code);
        return $types[$code] ?? self::defaults()[$code] ?? $types[$fallback] ?? self::defaults()[$fallback];
    }

    public static function label(array $t, string $lang): string { return ($lang === 'ru' && $t['name_ru'] !== '') ? $t['name_ru'] : $t['name']; }
    public static function fieldLabel(array $f, string $lang): string { return ($lang === 'ru' && $f['label_ru'] !== '') ? $f['label_ru'] : $f['label']; }

    /** Values from a form, only the fields of the type: trimmed, e-mail/phone/ids checked, links must be http(s). Empty values are dropped. */
    public static function clean(array $type, array $in): array
    {
        $out = [];
        foreach ($type['fields'] as $f) {
            $v = trim((string)($in[$f['key']] ?? ''));
            $v = mb_substr($v, 0, $f['kind'] === 'textarea' ? 5000 : 500);
            switch ($f['kind']) {
                case 'url': case 'shop': if ($v !== '' && !preg_match('~^https?://[^\s<>"\']+$~i', $v)) $v = ''; break;
                case 'email': if ($v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) $v = ''; break;
                case 'phone': if ($v !== '' && !preg_match('/^[0-9+()\-.\s]{3,30}$/', $v)) $v = ''; break;
                case 'task': case 'contact': $v = ctype_digit($v) && (int)$v > 0 ? (string)(int)$v : ''; break;
            }
            if ($v !== '') $out[$f['key']] = $v;
        }
        return $out;
    }

    /** Values of a type from the editor form: [{key,kind,label,label_ru}, ...] posted as arrays. */
    public static function fieldsFromPost(array $p): array
    {
        $rows = [];
        foreach ((array)($p['f_key'] ?? []) as $i => $k) {
            if (trim((string)$k) === '') continue;
            $rows[] = ['key' => (string)$k, 'kind' => (string)($p['f_kind'][$i] ?? 'text'), 'label' => (string)($p['f_label'][$i] ?? ''), 'label_ru' => (string)($p['f_label_ru'][$i] ?? '')];
        }
        return self::fieldDefs($rows);
    }
}
