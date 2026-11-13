<?php
declare(strict_types=1);
namespace SysferaCrm;

/** Pipeline maths, task states, permissions and CSV. Pure PHP (no database), covered by tests/crm.php. */
final class Pipe
{
    public const KINDS = ['open', 'won', 'lost'];
    public const TASK_TYPES = ['todo', 'call', 'meeting', 'email', 'deadline'];

    /**
     * Totals for a list of deals.
     * @param list<array{amount:mixed,stage_id:mixed}> $deals
     * @param array<int,array{kind:string,prob:mixed}> $stages stage id => stage
     */
    public static function summary(array $deals, array $stages): array
    {
        $r = ['open_n' => 0, 'open_sum' => 0.0, 'weighted' => 0.0, 'won_n' => 0, 'won_sum' => 0.0, 'lost_n' => 0, 'lost_sum' => 0.0, 'win_rate' => null];
        foreach ($deals as $d) {
            $s = $stages[(int)$d['stage_id']] ?? null; if (!$s) continue;
            $a = (float)$d['amount'];
            if ($s['kind'] === 'won') { $r['won_n']++; $r['won_sum'] += $a; }
            elseif ($s['kind'] === 'lost') { $r['lost_n']++; $r['lost_sum'] += $a; }
            else { $r['open_n']++; $r['open_sum'] += $a; $r['weighted'] += $a * max(0, min(100, (int)$s['prob'])) / 100; }
        }
        $closed = $r['won_n'] + $r['lost_n'];
        if ($closed > 0) $r['win_rate'] = round($r['won_n'] * 100 / $closed, 1);
        foreach (['open_sum', 'weighted', 'won_sum', 'lost_sum'] as $k) $r[$k] = round($r[$k], 2);
        return $r;
    }

    /** done | overdue | today | upcoming | none (no due date). $now is a unix time; due is "Y-m-d" or "Y-m-d H:i:s". */
    public static function taskState(?string $due, ?string $doneAt, int $now): string
    {
        if ($doneAt) return 'done';
        if (!$due) return 'none';
        $t = strtotime($due); if ($t === false) return 'none';
        $hasTime = strlen($due) > 10 && !str_ends_with($due, '00:00:00');
        if ($hasTime ? $t < $now : date('Y-m-d', $t) < date('Y-m-d', $now)) return 'overdue';
        return date('Y-m-d', $t) === date('Y-m-d', $now) ? 'today' : 'upcoming';
    }

    /** Next free sort position for a list of rows with a "sort" key. */
    public static function nextSort(array $rows): int
    {
        $m = 0; foreach ($rows as $r) $m = max($m, (int)($r['sort'] ?? 0)); return $m + 10;
    }

    /** Group deals by stage id (keeps the stage order given by $stageIds). */
    public static function board(array $deals, array $stageIds): array
    {
        $b = array_fill_keys($stageIds, []);
        foreach ($deals as $d) if (isset($b[(int)$d['stage_id']])) $b[(int)$d['stage_id']][] = $d;
        return $b;
    }

    /* ---------- permission profiles ---------- */
    public const PERMS = ['create', 'edit', 'delete', 'export', 'import', 'settings', 'reassign'];

    /** Clean a profile: scope own|all plus yes/no permissions. */
    public static function profile(array $raw): array
    {
        $p = ['scope' => ($raw['scope'] ?? 'own') === 'all' ? 'all' : 'own'];
        foreach (self::PERMS as $k) $p[$k] = !empty($raw[$k]);
        return $p;
    }

    /** Profiles every install starts with. */
    public static function defaultProfiles(): array
    {
        return [
            'Manager'   => self::profile(['scope' => 'own', 'create' => 1, 'edit' => 1]),
            'Team lead' => self::profile(['scope' => 'all', 'create' => 1, 'edit' => 1, 'delete' => 1, 'export' => 1, 'import' => 1, 'reassign' => 1]),
            'Observer'  => self::profile(['scope' => 'all']),
        ];
    }

    /** May this user see a record owned by $ownerId? Records without an owner are visible to everybody. */
    public static function visible(array $profile, ?int $ownerId, int $uid): bool
    {
        return $profile['scope'] === 'all' || $ownerId === null || $ownerId === 0 || $ownerId === $uid;
    }

    /* ---------- CSV ---------- */
    /** Parse CSV text (UTF-8, delimiter ; , or tab detected from the first line, quotes supported). */
    public static function csvParse(string $text): array
    {
        $text = (string)preg_replace('/^\xEF\xBB\xBF/', '', $text);
        $first = strtok($text, "\n") ?: '';
        $d = ';'; $best = -1;
        foreach ([';', ',', "\t"] as $c) { $n = substr_count($first, $c); if ($n > $best) { $best = $n; $d = $c; } }
        $rows = []; $row = []; $cell = ''; $q = false; $n = strlen($text);
        for ($i = 0; $i < $n; $i++) {
            $ch = $text[$i];
            if ($q) {
                if ($ch === '"') { if (($text[$i + 1] ?? '') === '"') { $cell .= '"'; $i++; } else $q = false; } else $cell .= $ch;
            } elseif ($ch === '"') $q = true;
            elseif ($ch === $d) { $row[] = $cell; $cell = ''; }
            elseif ($ch === "\n" || $ch === "\r") {
                if ($ch === "\r" && ($text[$i + 1] ?? '') === "\n") $i++;
                $row[] = $cell; $cell = '';
                if (count($row) > 1 || $row[0] !== '') $rows[] = $row; $row = [];
            } else $cell .= $ch;
        }
        if ($cell !== '' || $row) { $row[] = $cell; if (count($row) > 1 || $row[0] !== '') $rows[] = $row; }
        return $rows;
    }

    /** One CSV line. Cells that a spreadsheet could run as a formula are prefixed with an apostrophe. */
    public static function csvLine(array $cells, string $d = ';'): string
    {
        return implode($d, array_map(function ($v) use ($d) {
            $s = (string)$v;
            if ($s !== '' && strpbrk($s[0], "=+-@\t\r") !== false && !is_numeric($s)) $s = "'" . $s;
            return preg_match('/["\r\n' . preg_quote($d, '/') . ']/', $s) ? '"' . str_replace('"', '""', $s) . '"' : $s;
        }, $cells));
    }
}
