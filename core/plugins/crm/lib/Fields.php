<?php
declare(strict_types=1);
namespace SysferaCrm;

/** Custom fields: types, validation and display. Pure PHP (no database), covered by tests/crm.php. */
final class Fields
{
    public const TYPES = ['text', 'textarea', 'number', 'money', 'date', 'select', 'multiselect', 'checkbox', 'url', 'email', 'phone'];
    public const ENTITIES = ['contact', 'deal', 'task'];

    /** Slug for a field code from its label: latin letters/digits/_, starts with a letter, unique among $taken. */
    public static function code(string $label, array $taken = []): string
    {
        $c = strtolower(trim((string)preg_replace('/[^A-Za-z0-9]+/', '_', $label), '_'));
        if ($c === '' || !ctype_alpha($c[0])) $c = 'f' . ($c === '' ? '' : '_' . $c);
        $c = substr($c, 0, 32); $base = $c; $i = 2;
        while (in_array($c, $taken, true)) $c = substr($base, 0, 28) . '_' . $i++;
        return $c;
    }

    /** Options typed as one per line or comma separated -> clean unique list (max 100). */
    public static function options(string $raw): array
    {
        $o = [];
        foreach (preg_split('/[\r\n,;]+/', $raw) ?: [] as $v) { $v = trim($v); if ($v !== '' && mb_strlen($v) <= 80 && !in_array($v, $o, true)) $o[] = $v; }
        return array_slice($o, 0, 100);
    }

    /**
     * Validate and normalise submitted values against field definitions.
     * @param list<array{code:string,label:string,type:string,options?:array,required?:bool}> $defs
     * @param array<string,mixed> $in code => raw value
     * @return array{0:array<string,mixed>,1:list<string>} [clean values (empty ones omitted), error messages "label: key"]
     */
    public static function clean(array $defs, array $in): array
    {
        $out = []; $err = [];
        foreach ($defs as $d) {
            $code = $d['code']; $raw = $in[$code] ?? null; $type = $d['type'];
            $empty = $raw === null || $raw === '' || $raw === [];
            if ($type === 'checkbox') { $out[$code] = !empty($raw) && $raw !== '0'; if (!empty($d['required']) && !$out[$code]) $err[] = $d['label'] . ': required'; continue; }
            if ($empty) { if (!empty($d['required'])) $err[] = $d['label'] . ': required'; continue; }
            switch ($type) {
                case 'number': case 'money':
                    $n = str_replace([',', ' ', "\u{202F}", "\u{00A0}"], ['.', '', '', ''], (string)$raw);
                    if (!is_numeric($n) || abs((float)$n) > 1e12) { $err[] = $d['label'] . ': number'; break; }
                    $out[$code] = $type === 'money' ? round((float)$n, 2) : (float)$n; break;
                case 'date':
                    $dt = \DateTime::createFromFormat('Y-m-d', (string)$raw);
                    if (!$dt || $dt->format('Y-m-d') !== (string)$raw) { $err[] = $d['label'] . ': date'; break; }
                    $out[$code] = (string)$raw; break;
                case 'select':
                    if (!in_array((string)$raw, $d['options'] ?? [], true)) { $err[] = $d['label'] . ': option'; break; }
                    $out[$code] = (string)$raw; break;
                case 'multiselect':
                    $vals = array_values(array_unique(array_map('strval', (array)$raw)));
                    if (array_diff($vals, $d['options'] ?? [])) { $err[] = $d['label'] . ': option'; break; }
                    if ($vals) $out[$code] = $vals; break;
                case 'email':
                    if (!filter_var((string)$raw, FILTER_VALIDATE_EMAIL)) { $err[] = $d['label'] . ': email'; break; }
                    $out[$code] = mb_strtolower(trim((string)$raw)); break;
                case 'url':
                    if (!preg_match('~^https?://[^\s<>"]+$~i', (string)$raw)) { $err[] = $d['label'] . ': url'; break; }
                    $out[$code] = (string)$raw; break;
                case 'phone':
                    if (!preg_match('/^[0-9+()\-\s.]{5,30}$/', (string)$raw)) { $err[] = $d['label'] . ': phone'; break; }
                    $out[$code] = trim((string)$raw); break;
                default: // text, textarea
                    $s = trim((string)$raw); $max = $type === 'textarea' ? 5000 : 500;
                    if (mb_strlen($s) > $max) { $err[] = $d['label'] . ': long'; break; }
                    $out[$code] = $s;
            }
        }
        return [$out, $err];
    }

    /** Human-readable value for lists and cards (plain text; escape on output). */
    public static function show(array $def, mixed $v): string
    {
        if ($v === null || $v === '' || $v === []) return '';
        return match ($def['type']) {
            'checkbox' => $v ? '✓' : '',
            'multiselect' => implode(', ', (array)$v),
            'money' => number_format((float)$v, 2, '.', ' '),
            'number' => rtrim(rtrim(number_format((float)$v, 4, '.', ' '), '0'), '.'),
            default => (string)$v,
        };
    }

    /** Field definitions that apply to a record: global ones (pipeline_id null) plus the ones of its pipeline (project type). */
    public static function applicable(array $defs, string $entity, ?int $pipelineId = null): array
    {
        return array_values(array_filter($defs, fn($d) => $d['entity'] === $entity && ($d['pipeline_id'] === null || (int)$d['pipeline_id'] === (int)$pipelineId)));
    }
}
