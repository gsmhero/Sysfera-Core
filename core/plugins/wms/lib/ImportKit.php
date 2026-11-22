<?php
declare(strict_types=1);

namespace SysferaWms;

use RuntimeException, SimpleXMLElement, XMLReader;

// Copy of Basebase src/import/ImportKit.php (the two projects live in separate repositories): keep the files in sync.

/**
 * Shared, storage-free importer logic (used by Basebase "Import products" and by the Sysfera warehouse importer).
 * It knows nothing about databases, users or languages: the host app supplies a *catalog* describing the target
 * fields and then persists the rows this class produces.
 *
 * Catalog:
 *   'fields' => [ key => ['kind' => 'text'|'money'|'int'|'urls', 'unique' => bool, 'rules' => [regex on normalised header, ...]] ],
 *   'skip'   => [regex, ...]     headers that are noise for this app (service columns, old prices, ...)
 *
 * Mapping choice per column: a field key, or one of  'as_is' (keep as characteristic named like the header),
 * 'custom' (characteristic with its own name), 'skip'.
 *
 * Needs no mbstring; works on UTF-8 strings (Russian headers) via /u regexes.
 */
final class ImportKit {
    public const MAX_ROWS = 2000;     // data rows accepted per import

    /* ---------- text helpers ---------- */

    public static function lower(string $s): string {
        if (function_exists('mb_strtolower')) return mb_strtolower($s, 'UTF-8');
        return strtolower(preg_replace_callback('/[А-ЯЁ]/u', fn($m) => $m[0] === 'Ё' ? 'ё' : self::chrRu($m[0]), $s));
    }
    private static function chrRu(string $c): string {   // А..Я -> а..я
        $cp = (ord($c[0]) & 0x1F) << 6 | (ord($c[1]) & 0x3F);
        $cp += 0x20;
        return chr(0xC0 | ($cp >> 6)) . chr(0x80 | ($cp & 0x3F));
    }
    public static function len(string $s): int { return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : (int)preg_match_all('/./us', $s); }
    public static function cut(string $s, int $n): string {
        if (function_exists('mb_substr')) return mb_substr($s, 0, $n, 'UTF-8');
        return preg_match('/^.{0,' . $n . '}/us', $s, $m) ? $m[0] : $s;
    }

    /** "Цена на сайте, ₽" -> "цена на сайте";  "Артикул*" -> "артикул";  strips units, stars, brackets, extra spaces. */
    public static function norm(string $h): string {
        $h = self::lower($h);
        $h = str_replace(['ё', "\u{00A0}", "\n", "\r", "\t"], ['е', ' ', ' ', ' ', ' '], $h);
        $h = preg_replace('/\([^)]*\)/u', ' ', $h);        // (units, hints)
        $h = preg_replace('/,.*$/u', '', $h);              // ", руб." / ", шт."
        $h = preg_replace('/[*:#№_-]+/u', ' ', $h);
        return trim(preg_replace('/\s+/u', ' ', $h));
    }

    /** Header as shown to the user / used as characteristic name. */
    public static function label(string $h): string {
        $h = trim(preg_replace('/\s+/u', ' ', str_replace("\u{00A0}", ' ', $h)));
        return trim($h, " *");
    }

    /* ---------- table detection ---------- */

    /**
     * Picks the sheet and header row. Handles marketplace templates with group captions above the header,
     * "required field"/help rows below it, title rows ("Отчёт по товарам"), hidden service sheets.
     *
     * @param array $sheets output of SheetReader::read()
     * @return array|null ['sheet','sheetIndex','header_row' (0-based),'headers','rows' (data rows, keys = 0-based sheet row index),'groups' (col => section)]
     */
    public static function detect(array $sheets, array $cat, ?int $sheetIndex = null, ?int $headerRow = null): ?array {
        $best = null; $bestScore = -1;
        foreach ($sheets as $si => $sh) {
            if ($sheetIndex !== null && $si !== $sheetIndex) continue;
            if ($sheetIndex === null && !empty($sh['hidden'])) continue;
            $rows = $sh['rows'];
            if (count($rows) < 2 || count($rows[0] ?? []) < 2) continue;
            [$hr, $score] = $headerRow !== null ? [$headerRow, 1] : self::findHeader($rows, $cat);
            if ($hr === null) continue;
            $score = $score * 1000 + min(count($rows), 999);
            if ($score > $bestScore) { $bestScore = $score; $best = [$si, $hr]; }
        }
        if (!$best) return null;
        [$si, $hr] = $best;
        $rows = $sheets[$si]['rows'];
        $headers = [];
        foreach ($rows[$hr] as $c => $h) $headers[$c] = (string)$h;
        // trailing empty header cells carry no data
        while ($headers && end($headers) === '') array_pop($headers);
        $data = [];
        $skipping = true;
        for ($i = $hr + 1; $i < count($rows); $i++) {
            $r = $rows[$i];
            if (!array_filter($r, fn($c) => $c !== '')) continue;
            if ($skipping && $i <= $hr + 6 && self::isServiceRow($r)) continue;
            $skipping = false;
            $data[$i] = $r;
        }
        $groups = [];
        if ($hr > 0) {
            $above = $rows[$hr - 1];
            if (count(array_filter($above, fn($c) => $c !== '')) >= 2) {
                $cur = '';
                foreach ($headers as $c => $_) {
                    if (($above[$c] ?? '') !== '') $cur = self::cut(trim(strtok((string)$above[$c], "\n")), 60);
                    $groups[$c] = $cur;
                }
            }
        }
        return ['sheet' => $sheets[$si]['name'], 'sheetIndex' => $si, 'header_row' => $hr, 'headers' => $headers, 'rows' => $data, 'groups' => $groups];
    }

    /** @return array{0:?int,1:int} header row index and its score */
    private static function findHeader(array $rows, array $cat): array {
        $best = null; $bestScore = 0;
        $known = self::knownCount(...);
        foreach (array_slice($rows, 0, 15, true) as $i => $r) {
            $filled = array_filter($r, fn($c) => $c !== '');
            if (count($filled) < 2) continue;
            $short = array_filter($filled, fn($c) => self::len($c) <= 70 && !str_contains($c, "\n"));
            $score = $known($r, $cat) * 6 + count($short) - 2 * (count($filled) - count($short));
            if (count($short) < count($filled) * 0.6) $score -= 20;
            if ($score > $bestScore) { $bestScore = $score; $best = $i; }
        }
        return [$best, $bestScore];
    }

    private static function knownCount(array $row, array $cat): int {
        $n = 0;
        foreach ($row as $h) {
            if ($h === '') continue;
            $nh = self::norm($h);
            if (self::fieldOf($nh, $cat) !== null) $n++;
        }
        return $n;
    }

    private static function isServiceRow(array $r): bool {
        $filled = array_values(array_filter($r, fn($c) => $c !== ''));
        foreach ($filled as $c) {
            if (preg_match('/^(обязательное поле|необязательное поле|required)|Ⓜ|ϟ/u', $c)) return true;
        }
        // help row of a marketplace template: many cells, at least half of them long instructions, no plain numbers
        if (count($filled) >= 6) {
            $long = 0; $numeric = false;
            foreach ($filled as $c) { if (self::len($c) >= 30) $long++; if (preg_match('/^-?[\d\s.,]+$/', $c)) $numeric = true; }
            if (!$numeric && $long >= count($filled) * 0.5) return true;
        }
        return false;
    }

    /* ---------- mapping guess ---------- */

    private static function fieldOf(string $nh, array $cat): ?array {
        foreach ($cat['skip'] ?? [] as $re) if (preg_match($re, $nh)) return null;
        foreach ($cat['fields'] as $key => $f) foreach ($f['rules'] as $ri => $re) if (preg_match($re, $nh)) return [$key, $ri];
        return null;
    }

    /**
     * Auto-detects the matching field for every column.
     * @return array<int,array{field:string,name:string}> column index => choice
     */
    public static function guess(array $headers, array $cat): array {
        $out = []; $cand = [];
        foreach ($headers as $c => $h) {
            $nh = self::norm($h);
            if ($nh === '') { $out[$c] = ['field' => 'skip', 'name' => '']; continue; }
            $hit = self::fieldOf($nh, $cat);
            if ($hit) { $cand[$hit[0]][] = [$hit[1], $c]; $out[$c] = ['field' => $hit[0], 'name' => '']; continue; }
            $isSkip = false;
            foreach ($cat['skip'] ?? [] as $re) if (preg_match($re, $nh)) { $isSkip = true; break; }
            $out[$c] = ['field' => $isSkip ? 'skip' : 'as_is', 'name' => ''];
        }
        foreach ($cand as $key => $list) {
            if (empty($cat['fields'][$key]['unique'])) continue;
            usort($list, fn($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
            foreach (array_slice($list, 1) as [, $c]) $out[$c] = ['field' => 'skip', 'name' => ''];   // runner-up, e.g. second "Цена"
        }
        return $out;
    }

    /* ---------- templates ---------- */

    /** Stable id of a header set (order and case independent). */
    public static function signature(array $headers): string {
        $n = array_values(array_unique(array_filter(array_map([self::class, 'norm'], $headers), fn($x) => $x !== '')));
        sort($n);
        return sha1(implode('|', $n));
    }

    /**
     * Picks the saved template for these headers: exact signature, else the best one sharing ≥ 70% of its headers.
     * @param array $templates each ['signature' => string, 'mapping' => [normHeader => ['field','name']]]
     */
    public static function pickTemplate(array $headers, array $templates): ?array {
        $sig = self::signature($headers);
        foreach ($templates as $t) if (($t['signature'] ?? '') === $sig) return $t;
        $have = array_flip(array_filter(array_map([self::class, 'norm'], $headers), fn($x) => $x !== ''));
        $best = null; $bestShare = 0.0;
        foreach ($templates as $t) {
            $keys = array_keys($t['mapping'] ?? []);
            if (!$keys) continue;
            $share = count(array_intersect_key($t['mapping'], $have)) / count($keys);
            if ($share >= 0.7 && $share > $bestShare) { $best = $t; $bestShare = $share; }
        }
        return $best;
    }

    /** Overlays a template's per-header choices onto the auto-detected mapping. */
    public static function applyTemplate(array $guess, array $headers, array $template): array {
        foreach ($headers as $c => $h) {
            $nh = self::norm($h);
            if (isset($template['mapping'][$nh])) $guess[$c] = ['field' => (string)$template['mapping'][$nh]['field'], 'name' => (string)($template['mapping'][$nh]['name'] ?? '')];
        }
        return $guess;
    }

    /** Builds the template mapping from what the seller submitted (only columns with "Add to Template" ticked). */
    public static function templateMapping(array $headers, array $choice, array $ticked): array {
        $m = [];
        foreach ($headers as $c => $h) {
            if (empty($ticked[$c])) continue;
            $nh = self::norm($h);
            if ($nh !== '') $m[$nh] = ['field' => $choice[$c]['field'], 'name' => $choice[$c]['name']];
        }
        return $m;
    }

    /** Cleans the mapping posted from the converter form; unknown values fall back to as_is. */
    public static function cleanChoice(array $headers, array $postField, array $postName, array $cat): array {
        $ok = array_merge(array_keys($cat['fields']), ['as_is', 'custom', 'skip']);
        $out = []; $seenUnique = [];
        foreach ($headers as $c => $_) {
            $f = (string)($postField[$c] ?? 'as_is');
            if (!in_array($f, $ok, true)) $f = 'as_is';
            $name = self::cut(self::label((string)($postName[$c] ?? '')), 120);
            if ($f === 'custom' && $name === '') $f = 'as_is';
            if (isset($cat['fields'][$f]) && !empty($cat['fields'][$f]['unique'])) {
                if (isset($seenUnique[$f])) $f = 'skip';   // the same target twice: first column wins
                else $seenUnique[$f] = true;
            }
            $out[$c] = ['field' => $f, 'name' => $f === 'custom' ? $name : ''];
        }
        return $out;
    }

    /* ---------- value parsing ---------- */

    public static function parseMoney(string $s): ?float {
        $s = trim(str_replace(["\u{00A0}", "\u{202F}", ' '], '', $s));
        $s = preg_replace('/(?:₽|руб\.?|р\.?|rub|rur|usd|eur|\$|€)$/iu', '', $s);
        if ($s === '' || !preg_match('/^-?[\d.,]+$/', $s)) return null;
        $dot = strrpos($s, '.'); $com = strrpos($s, ',');
        if ($dot !== false && $com !== false) {                      // 1,450.50  or  1.450,50
            $dec = $dot > $com ? '.' : ',';
            $s = str_replace($dec === '.' ? ',' : '.', '', $s);
            if ($dec === ',') $s = str_replace(',', '.', $s);
        } elseif ($com !== false) {
            if (substr_count($s, ',') > 1 || preg_match('/^-?\d{1,3},\d{3}$/', $s)) $s = str_replace(',', '', $s);   // 1,450 / 1,450,000 = thousands
            else $s = str_replace(',', '.', $s);                     // 445,00
        } elseif (substr_count($s, '.') > 1) $s = str_replace('.', '', $s);
        return is_numeric($s) ? (float)$s : null;
    }

    public static function parseInt(string $s): ?int {
        $f = self::parseMoney($s);
        return $f === null ? null : (int)round($f);
    }

    /** Extracts image URLs from a cell: newline/space/;/| separated (commas only before another http). */
    public static function splitUrls(string $s): array {
        if (!preg_match_all('~https?://[^\s;|"\'<>]+~iu', $s, $m)) return [];
        $out = [];
        foreach ($m[0] as $u) foreach (preg_split('~,(?=https?://)~i', $u) as $p) { $p = rtrim($p, ',.'); if ($p !== '') $out[] = $p; }
        return array_values(array_unique($out));
    }

    /* ---------- row extraction ---------- */

    /**
     * Applies the mapping to the data rows.
     * @return list<array{line:int,v:array,attrs:list<array>,errors:list<array>}> one entry per non-empty source row;
     *         'line' is the 1-based row number in the sheet. Error entries: ['code' => 'bad_number'|'bad_int', 'field' => key, 'value' => raw].
     */
    public static function extract(array $table, array $choice, array $cat): array {
        $out = [];
        foreach ($table['rows'] as $i => $r) {
            $v = []; $attrs = []; $errors = [];
            foreach ($choice as $c => $ch) {
                $raw = trim((string)($r[$c] ?? ''));
                if ($raw === '') continue;
                $f = $ch['field'];
                if ($f === 'skip') continue;
                if ($f === 'as_is' || $f === 'custom') {
                    $name = $f === 'custom' ? $ch['name'] : self::label((string)($table['headers'][$c] ?? ''));
                    if ($name === '') continue;
                    $attrs[] = ['section' => (string)($table['groups'][$c] ?? ''), 'name' => self::cut($name, 120), 'value' => self::cut($raw, 500)];
                    continue;
                }
                $kind = $cat['fields'][$f]['kind'] ?? 'text';
                if ($kind === 'urls') { $v[$f] = array_merge($v[$f] ?? [], self::splitUrls($raw)); continue; }
                if (isset($v[$f])) continue;          // first non-empty column wins (unique fields already deduplicated)
                if ($kind === 'money') { $n = self::parseMoney($raw); if ($n === null) $errors[] = ['code' => 'bad_number', 'field' => $f, 'value' => self::cut($raw, 40)]; else $v[$f] = $n; }
                elseif ($kind === 'int') { $n = self::parseInt($raw); if ($n === null) $errors[] = ['code' => 'bad_int', 'field' => $f, 'value' => self::cut($raw, 40)]; else $v[$f] = $n; }
                else $v[$f] = $raw;
            }
            if (isset($v['images'])) $v['images'] = array_values(array_unique($v['images']));
            if (!$v && !$attrs && !$errors) continue;
            $out[] = ['line' => $i + 1, 'v' => $v, 'attrs' => $attrs, 'errors' => $errors];
        }
        return $out;
    }
}
