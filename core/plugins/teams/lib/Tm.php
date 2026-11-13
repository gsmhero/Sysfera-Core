<?php
declare(strict_types=1);
namespace SysferaTeams;

/** Pure logic of Sysfera Teams: roles, @mentions, safe attachments, calendar grid. No database; covered by tests/teams.php. */
final class Tm
{
    public const ROLES = ['owner', 'admin', 'member'];
    public const MAX_FILE = 5 * 1024 * 1024;
    public const MAX_TEXT = 4000;
    public const EMOJI = ['👍', '❤️', '😄', '🎉', '👀', '✅'];
    /** extension => mime. Only these can be uploaded; the type always comes from the extension, never from the browser. */
    public const FILES = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp', 'pdf' => 'application/pdf', 'txt' => 'text/plain', 'csv' => 'text/csv',
        'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint', 'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'odt' => 'application/vnd.oasis.opendocument.text', 'ods' => 'application/vnd.oasis.opendocument.spreadsheet', 'zip' => 'application/zip'];
    public const INLINE = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /** What a role may do inside a team. Members work (chat, tasks, events, files); admins also manage people and channels; the owner also the team itself. */
    public static function can(?string $role, string $action): bool
    {
        if ($role === null) return false;
        $member = ['read', 'post', 'react', 'upload', 'task', 'event', 'leave'];
        $admin = [...$member, 'channel', 'members', 'moderate', 'edit_team'];
        $owner = [...$admin, 'delete_team', 'roles'];
        return in_array($action, match ($role) { 'owner' => $owner, 'admin' => $admin, 'member' => $member, default => [] }, true);
    }

    /** Ids of members mentioned as @Name in a message (longest names first, case-insensitive, whole word). @param array<int,string> $members id => name */
    public static function mentions(string $body, array $members, int $exceptId = 0): array
    {
        $out = []; $names = $members; uasort($names, fn($a, $b) => strlen($b) <=> strlen($a));
        $text = mb_strtolower($body);
        foreach ($names as $id => $name) {
            $n = mb_strtolower(trim($name)); if ($n === '' || $id === $exceptId) continue;
            $pos = 0;
            while (($i = mb_strpos($text, '@' . $n, $pos)) !== false) {
                $after = mb_substr($text, $i + 1 + mb_strlen($n), 1);
                if ($after === '' || !preg_match('/[\p{L}\p{N}_]/u', $after)) { $out[$id] = true; $text = mb_substr($text, 0, $i) . str_repeat(' ', mb_strlen($n) + 1) . mb_substr($text, $i + 1 + mb_strlen($n)); break; }
                $pos = $i + 1;
            }
        }
        return array_map('intval', array_keys($out));
    }

    /** Validate a message text; returns [clean text, error key or null]. */
    public static function text(string $raw, bool $hasFile = false): array
    {
        $t = trim(str_replace("\r\n", "\n", $raw));
        if ($t === '' && !$hasFile) return ['', 'e_empty'];
        if (mb_strlen($t) > self::MAX_TEXT) return ['', 'e_long'];
        return [(string)preg_replace('/\n{4,}/', "\n\n\n", $t), null];
    }

    /** Check an uploaded file by name and size; returns [safe display name, extension, mime] or null. */
    public static function file(string $name, int $size): ?array
    {
        $name = basename(str_replace('\\', '/', $name));
        $ext = strtolower((string)pathinfo($name, PATHINFO_EXTENSION));
        if (!isset(self::FILES[$ext]) || $size <= 0 || $size > self::MAX_FILE) return null;
        $base = trim((string)preg_replace('/[^\p{L}\p{N}._ -]+/u', '_', (string)pathinfo($name, PATHINFO_FILENAME)), " ._");
        if ($base === '') $base = 'file';
        return [mb_substr($base, 0, 80) . '.' . $ext, $ext, self::FILES[$ext]];
    }

    /** Month grid starting on Monday: weeks of 7 days [date, in (belongs to the month), today]. */
    public static function month(string $ym, int $now): array
    {
        if (!preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $ym, $m)) $ym = date('Y-m', $now);
        $first = strtotime($ym . '-01 12:00:00'); $dow = (int)date('N', $first);
        $start = strtotime('-' . ($dow - 1) . ' days', $first); $days = (int)date('t', $first);
        $weeks = (int)ceil(($dow - 1 + $days) / 7); $today = date('Y-m-d', $now); $grid = [];
        for ($w = 0; $w < $weeks; $w++) for ($d = 0; $d < 7; $d++) {
            $t = strtotime('+' . ($w * 7 + $d) . ' days', $start); $ds = date('Y-m-d', $t);
            $grid[$w][] = ['date' => $ds, 'in' => date('Y-m', $t) === $ym, 'today' => $ds === $today];
        }
        return ['ym' => $ym, 'prev' => date('Y-m', strtotime('-1 month', $first)), 'next' => date('Y-m', strtotime('+1 month', $first)), 'weeks' => $grid];
    }

    /** Events by every day they cover (max 62 days per event). @param list<array{starts:string,ends:?string}> $events @return array<string,list<array>> */
    public static function byDay(array $events): array
    {
        $out = [];
        foreach ($events as $e) {
            $a = substr((string)$e['starts'], 0, 10); $b = $e['ends'] ? substr((string)$e['ends'], 0, 10) : $a;
            if ($b < $a) $b = $a;
            for ($i = 0, $d = $a; $d <= $b && $i < 62; $i++, $d = date('Y-m-d', strtotime($d . ' +1 day'))) $out[$d][] = $e;
        }
        ksort($out);
        return $out;
    }

    /** Parse "Y-m-d" + optional "H:i" into a datetime string, or null when invalid. */
    public static function when(string $date, string $time = ''): ?string
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        if (!$d || $d->format('Y-m-d') !== $date) return null;
        return $date . ' ' . (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time) ? $time . ':00' : '00:00:00');
    }

    public static function nextSort(array $rows): int { $m = 0; foreach ($rows as $r) $m = max($m, (int)($r['sort'] ?? 0)); return $m + 10; }

    /** Short plain preview of a message for quotes and lists. */
    public static function clip(string $s, int $n = 90): string
    {
        $s = trim((string)preg_replace('/\s+/u', ' ', $s));
        return mb_strlen($s) > $n ? mb_substr($s, 0, $n - 1) . '…' : $s;
    }

    /** Task priorities, low to high. */
    public const PRIO = [0, 1, 2];
}
