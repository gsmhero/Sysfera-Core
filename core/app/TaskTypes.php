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

    /** One monochrome line-icon set (24x24, stroke = currentColor). A type stores the icon NAME, never an emoji (emoji broke on databases without utf8mb4). */
    public const ICONS = [
        'check'       => '<rect x="3" y="3" width="18" height="18" rx="3"/><path d="m8 12 3 3 5-6"/>',
        'checkcircle' => '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>',
        'circle'      => '<circle cx="12" cy="12" r="8"/>',
        'dot'         => '<circle cx="12" cy="12" r="3"/>',
        'phone'       => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/>',
        'mail'        => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'chat'        => '<path d="M4 5h16v11H9l-5 4z"/>',
        'users'       => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14a6.5 6.5 0 0 1 3.5 6"/>',
        'clock'       => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'calendar'    => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'link'        => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
        'note'        => '<path d="M6 3h9l4 4v14H6z"/><path d="M14 3v5h5M9 13h7M9 17h5"/>',
        'cart'        => '<circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h3l2.5 12H19l2-8H6"/>',
        'gift'        => '<rect x="3" y="9" width="18" height="12" rx="1"/><path d="M3 13h18M12 9v12M12 9c-3 0-4-4-1.5-4 2 0 1.5 4 1.5 4zm0 0c3 0 4-4 1.5-4-2 0-1.5 4-1.5 4z"/>',
        'truck'       => '<path d="M2 6h11v10H2zM13 9h4l4 4v3h-8"/><circle cx="7" cy="18" r="1.8"/><circle cx="17" cy="18" r="1.8"/>',
        'dollar'      => '<circle cx="12" cy="12" r="9"/><path d="M14.5 9a2.5 2 0 0 0-2.5-1.5c-1.5 0-2.5.8-2.5 2s1 1.7 2.5 2 2.5.8 2.5 2-1 2-2.5 2A2.5 2 0 0 1 9.5 15M12 6v1.5M12 16.5V18"/>',
        'arrow'       => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'flag'        => '<path d="M5 21V4M5 4h12l-2 4 2 4H5"/>',
        'star'        => '<path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z"/>',
        'heart'       => '<path d="M12 20s-8-5-8-11a4.5 4.5 0 0 1 8-2.5A4.5 4.5 0 0 1 20 9c0 6-8 11-8 11z"/>',
        'pin'         => '<path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/>',
        'tag'         => '<path d="M3 12V4h8l10 10-8 8z"/><circle cx="7.5" cy="8.5" r="1.2"/>',
        'bell'        => '<path d="M6 9a6 6 0 0 1 12 0c0 6 2 7 2 7H4s2-1 2-7zM10 20a2 2 0 0 0 4 0"/>',
        'bolt'        => '<path d="M13 2 4 14h7l-1 8 9-12h-7z"/>',
    ];

    /** a safe icon name (anything unknown, e.g. an old emoji, becomes "dot") */
    public static function iconName(string $n): string { $n = strtolower(trim($n)); return isset(self::ICONS[$n]) ? $n : 'dot'; }

    /** the icon as inline SVG, takes the text colour */
    public static function svg(string $icon, int $px = 16): string
    {
        return '<svg class="ttico" width="' . $px . '" height="' . $px . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:-0.15em">' . self::ICONS[self::iconName($icon)] . '</svg>';
    }

    /** the built-in types: code => [name, name_ru, icon, color, apps, fields] (field = [key, kind, label, label_ru]) */
    public const DEFAULTS = [
        'todo'           => ['To-do', 'Задача', 'check', '#6b7280', 'missions,crm', []],
        'call'           => ['Call', 'Звонок', 'phone', '#2563eb', 'missions,crm', [['contact_id', 'contact', 'Contact', 'Контакт'], ['phone', 'phone', 'Phone', 'Телефон']]],
        'email'          => ['Email', 'Письмо', 'mail', '#7c3aed', 'missions,crm', [['contact_id', 'contact', 'Contact', 'Контакт'], ['to', 'email', 'To (email)', 'Кому (email)'], ['subject', 'text', 'Subject', 'Тема'], ['body', 'textarea', 'Message', 'Текст письма']]],
        'meeting'        => ['Meeting', 'Встреча', 'users', '#059669', 'missions,crm', [['contact_id', 'contact', 'Contact', 'Контакт'], ['phone', 'phone', 'Phone', 'Телефон'], ['place', 'text', 'Place name', 'Название места'], ['address', 'text', 'Address', 'Адрес'], ['link', 'url', 'Link', 'Ссылка']]],
        'deadline'       => ['Deadline', 'Дедлайн', 'clock', '#dc2626', 'missions,crm', []],
        'visit_url'      => ['Visit URL', 'Открыть ссылку', 'link', '#0891b2', 'missions,crm', [['url', 'url', 'Address (https://…)', 'Адрес (https://…)']]],
        'note'           => ['Note', 'Заметка', 'note', '#ca8a04', 'missions,crm', [['note', 'textarea', 'Note', 'Заметка']]],
        'buy_product'    => ['Buy product', 'Купить товар', 'cart', '#ea580c', 'missions,crm', [['url', 'shop', 'Basebase shop link', 'Ссылка на магазин Basebase']]],
        'go_to_task'     => ['Go to task', 'Перейти к задаче', 'arrow', '#4f46e5', 'missions', [['target', 'task', 'Go to', 'Перейти к']]],
        'no_action'      => ['No action', 'Без действия', 'circle', '#9ca3af', 'missions', []],
        'task_done'      => ['Task done', 'Задача выполнена', 'checkcircle', '#16a34a', 'missions', []],
        'finish_mission' => ['Finish mission', 'Завершить миссию', 'flag', '#16a34a', 'missions', []],
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
