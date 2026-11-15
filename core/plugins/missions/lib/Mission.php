<?php
declare(strict_types=1);
namespace SysferaMissions;

use Sysfera\{Db, TaskTypes};

/** Rules of the mission / task chain model (no output, no session: easy to test). */
final class Mission
{
    public const BRANCHES = ['good', 'alt', 'bad'];
    /** attachments: extension => mime ("inline" ones are shown in the gallery) */
    public const FILES = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp', 'pdf' => 'application/pdf', 'txt' => 'text/plain', 'csv' => 'text/csv',
        'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
    public const INLINE = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    public const MAX_FILE = 5242880, MAX_FILES_PER_TASK = 10;

    /** the type code of a task: any spelling is accepted ("Finish Mission", "visit_url"); unknown types become "note". $types = the task types registry (all of them). */
    public static function type(string $t, array $types): string { $c = TaskTypes::normalize($t); return isset($types[$c]) ? $c : 'note'; }
    public static function branch(?string $b): ?string { $b = $b === 'unexpected' ? 'bad' : $b; return in_array($b, self::BRANCHES, true) ? $b : null; }

    /** type_data from a form: only the fields of this type (see Sysfera\TaskTypes::clean). */
    public static function cleanData(string $type, array $in, array $types): array { return TaskTypes::clean(TaskTypes::get($types, $type), $in); }

    /** Check an attachment by name and size: [safe name, extension, mime] or null. */
    public static function file(string $name, int $size): ?array
    {
        $name = basename(str_replace('\\', '/', $name));
        $ext = strtolower((string)pathinfo($name, PATHINFO_EXTENSION));
        if (!isset(self::FILES[$ext]) || $size <= 0 || $size > self::MAX_FILE) return null;
        $base = trim((string)preg_replace('/[^\p{L}\p{N}._ -]+/u', '_', (string)pathinfo($name, PATHINFO_FILENAME)), " ._");
        return [mb_substr($base === '' ? 'file' : $base, 0, 80) . '.' . $ext, $ext, self::FILES[$ext]];
    }

    /** a link that shows a place on a map (Yandex for Russian, OpenStreetMap otherwise) */
    public static function mapUrl(string $place, string $lang): string
    {
        return $lang === 'ru' ? 'https://yandex.ru/maps/?text=' . rawurlencode($place) : 'https://www.openstreetmap.org/search?query=' . rawurlencode($place);
    }

    /** title ≤ 20 words and text ≤ 50 words for the Activity feed */
    public static function announce(string $title, string $text, string $prefix): array
    {
        $cut = function (string $s, int $n): string { $w = preg_split('/\s+/u', trim($s), -1, PREG_SPLIT_NO_EMPTY) ?: []; return implode(' ', array_slice($w, 0, $n)); };
        return [$cut($prefix . ' ' . $title, 20), $cut($text, 50)];
    }
    /** children keyed by parent id (0 = root) then by branch ('' for roots) */
    public static function tree(array $tasks): array
    {
        $t = [];
        foreach ($tasks as $x) $t[(int)($x['parent_id'] ?? 0)][(string)($x['branch'] ?? '')][] = $x;
        return $t;
    }

    /** ids from the first task along the "good" results up to Finish Mission; empty while there is no way to the finish */
    public static function luckyWay(array $tasks): array
    {
        $tree = self::tree($tasks); $cur = $tree[0][''][0] ?? null; $way = []; $seen = [];
        while ($cur && !isset($seen[$cur['id']])) {
            $seen[$cur['id']] = 1; $way[] = (int)$cur['id'];
            if (TaskTypes::normalize((string)$cur['type']) === 'finish_mission') return $way;
            $cur = $tree[(int)$cur['id']]['good'][0] ?? null;
        }
        return [];
    }

    public static function refreshLucky(Db $db, int $mission): array
    {
        $way = self::luckyWay($db->rows('SELECT id, parent_id, branch, type FROM {p}mis_tasks WHERE mission_id = ? ORDER BY id', [$mission]));
        $db->q('UPDATE {p}mis_missions SET lucky_way = ? WHERE id = ?', [$way ? json_encode($way) : null, $mission]);
        return $way;
    }

    /** Copy a mission (with its chain) to another user; progress and reminders are reset. */
    public static function copy(Db $db, int $srcId, int $uid): int
    {
        $m = $db->row('SELECT * FROM {p}mis_missions WHERE id = ?', [$srcId]) ?? throw new \RuntimeException('missing');
        $db->q('INSERT INTO {p}mis_missions (user_id, title, type, category_id, description, origin_id) VALUES (?,?,?,?,?,?)', [$uid, $m['title'], $m['type'], $m['category_id'], $m['description'], $srcId]);
        $new = $db->insertId(); $map = [];
        foreach ($db->rows('SELECT * FROM {p}mis_tasks WHERE mission_id = ? ORDER BY id', [$srcId]) as $t) {   // parents have smaller ids than their children
            $db->q('INSERT INTO {p}mis_tasks (mission_id, user_id, parent_id, branch, title, type, cost, description, result_good, result_alt, result_bad, type_data) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
                [$new, $uid, $t['parent_id'] ? ($map[(int)$t['parent_id']] ?? null) : null, $t['branch'], $t['title'], $t['type'], $t['cost'], $t['description'], $t['result_good'], $t['result_alt'], $t['result_bad'], $t['type_data']]);
            $map[(int)$t['id']] = $db->insertId();
        }
        foreach ($db->rows("SELECT id, type_data FROM {p}mis_tasks WHERE mission_id = ? AND type = 'go_to_task'", [$new]) as $t)   // "Go to task" points at the copied tasks
            $db->q('UPDATE {p}mis_tasks SET type_data = ? WHERE id = ?', [self::retarget($t['type_data'], $map), $t['id']]);
        return $new;
    }
    private static function retarget(?string $json, array $map): ?string
    {
        $d = json_decode((string)$json, true); if (!is_array($d)) return $json;
        if (isset($d['target'])) { $n = $map[(int)$d['target']] ?? null; if ($n) $d['target'] = (string)$n; else unset($d['target']); }
        return $d ? json_encode($d, JSON_UNESCAPED_UNICODE) : null;
    }

    /** JSON-able copy of a mission: tasks refer to their parent by position in the list. */
    public static function export(Db $db, int $id): array
    {
        $m = $db->row('SELECT title, type, description, due_date FROM {p}mis_missions WHERE id = ?', [$id]) ?? [];
        $tasks = $db->rows('SELECT * FROM {p}mis_tasks WHERE mission_id = ? ORDER BY id', [$id]); $pos = []; $out = [];
        foreach ($tasks as $i => $t) $pos[(int)$t['id']] = $i;
        foreach ($tasks as $t) {
            $d = json_decode((string)$t['type_data'], true) ?: [];
            if (isset($d['target'])) $d['target'] = (string)($pos[(int)$d['target']] ?? '');
            $out[] = ['parent' => $t['parent_id'] ? ($pos[(int)$t['parent_id']] ?? null) : null, 'branch' => $t['branch'], 'title' => $t['title'], 'type' => $t['type'], 'cost' => (float)$t['cost'],
                'description' => $t['description'], 'result_good' => $t['result_good'], 'result_alt' => $t['result_alt'], 'result_bad' => $t['result_bad'], 'type_data' => $d ?: new \stdClass()];
        }
        return ['format' => 'sysfera-mission', 'version' => 1] + $m + ['tasks' => $out];
    }

    /** Import an export of this component, or a Tactify Mission Manager export (parent_link_id "mission_<id>" / "<task>_good|_alt|_unexpected"). */
    public static function import(Db $db, array $d, int $uid, ?array $types = null): int
    {
        $types ??= TaskTypes::all($db, null, true);
        $title = mb_substr(trim((string)($d['title'] ?? '')), 0, 255); if ($title === '') throw new \RuntimeException('bad_file');
        $db->q('INSERT INTO {p}mis_missions (user_id, title, type, description, due_date) VALUES (?,?,?,?,?)', [$uid, $title, mb_substr((string)($d['type'] ?? ''), 0, 100), mb_substr((string)($d['description'] ?? ''), 0, 5000),
            preg_match('/^\d{4}-\d\d-\d\d$/', (string)($d['due_date'] ?? '')) ? $d['due_date'] : null]);
        $mid = $db->insertId(); $tasks = array_slice(array_values((array)($d['tasks'] ?? [])), 0, 300);
        $tactify = isset($tasks[0]) && is_array($tasks[0]) && array_key_exists('parent_link_id', $tasks[0]);
        $ids = []; $legacy = []; $targets = [];
        foreach ($tasks as $i => $t) if (is_array($t) && isset($t['id'])) $legacy[(string)$t['id']] = $i;
        foreach ($tasks as $i => $t) {
            if (!is_array($t)) continue;
            $type = self::type((string)($t['type'] ?? ''), $types); $parent = null; $branch = null;
            if ($tactify) {
                if (preg_match('/^(.+)_(good|alt|unexpected|bad)$/', (string)($t['parent_link_id'] ?? ''), $mm) && isset($legacy[$mm[1]], $ids[$legacy[$mm[1]]])) { $parent = $ids[$legacy[$mm[1]]]; $branch = self::branch($mm[2]); }
            } elseif (isset($t['parent'], $ids[(int)$t['parent']])) { $parent = $ids[(int)$t['parent']]; $branch = self::branch($t['branch'] ?? null); }
            if ($parent && !$branch) $branch = 'good';
            $data = (array)($t['type_data'] ?? $t['typeData'] ?? []);
            $clean = self::cleanData($type, $data, $types);
            $db->q('INSERT INTO {p}mis_tasks (mission_id, user_id, parent_id, branch, title, type, cost, description, result_good, result_alt, result_bad, type_data) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
                [$mid, $uid, $parent, $branch, mb_substr((string)($t['title'] ?? ''), 0, 255) ?: '—', $type, round((float)($t['cost'] ?? 0), 2), mb_substr((string)($t['description'] ?? ''), 0, 5000),
                 mb_substr((string)($t['result_good'] ?? ''), 0, 255), mb_substr((string)($t['result_alt'] ?? ''), 0, 255), mb_substr((string)($t['result_bad'] ?? $t['result_unexpected'] ?? ''), 0, 255),
                 $clean ? json_encode($clean, JSON_UNESCAPED_UNICODE) : null]);
            $ids[$i] = $db->insertId();
            if ($type === 'go_to_task' && isset($data['target'])) $targets[$ids[$i]] = $data['target'];   // resolved once every task exists
        }
        foreach ($targets as $tid => $old) {
            $to = $tactify ? ($ids[$legacy[(string)$old] ?? -1] ?? null) : ($ids[(int)$old] ?? null);
            if ($to) $db->q('UPDATE {p}mis_tasks SET type_data = ? WHERE id = ?', [json_encode(['target' => (string)$to]), $tid]);
        }
        return $mid;
    }
}
