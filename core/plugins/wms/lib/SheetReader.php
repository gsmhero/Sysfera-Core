<?php
declare(strict_types=1);

namespace SysferaWms;

use RuntimeException, SimpleXMLElement, XMLReader;

// Copy of Basebase src/import/SheetReader.php (the two projects live in separate repositories): keep the files in sync.

/**
 * Dependency-free spreadsheet reader: .xlsx (zip + XML, needs only zlib) and .csv/.txt.
 * No namespaces, no mbstring/zip/iconv requirement, so the very same file runs in Basebase and in Sysfera.
 *
 *   $sheets = SheetReader::read($path, 'export.xlsx');   // [['name' => 'Sheet1', 'rows' => [[cell, ...], ...]], ...]
 *
 * Every cell is returned as a string (shared strings resolved, E-notation numbers expanded).
 * Throws RuntimeException with a human readable message for unreadable / oversized files.
 */
final class SheetReader {
    public const MAX_BYTES = 12 * 1024 * 1024;    // uploaded file
    public const MAX_XML   = 80 * 1024 * 1024;    // one decompressed part (zip-bomb guard)
    public const MAX_ROWS  = 20000;               // rows kept per sheet
    public const MAX_COLS  = 200;

    public static function read(string $path, string $origName = '', int $maxRows = self::MAX_ROWS): array {
        if (!is_file($path)) throw new RuntimeException('File not found.');
        if (filesize($path) > self::MAX_BYTES) throw new RuntimeException('File is too large (max ' . (self::MAX_BYTES >> 20) . ' MB).');
        $head = (string)file_get_contents($path, false, null, 0, 4);
        $ext = strtolower(pathinfo($origName !== '' ? $origName : $path, PATHINFO_EXTENSION));
        if (str_starts_with($head, "PK\x03\x04")) return self::readXlsx($path, $maxRows);
        if (str_starts_with($head, "\xD0\xCF\x11\xE0")) throw new RuntimeException('Old .xls files are not supported - save the file as .xlsx or .csv.');
        if ($ext === 'xlsx' || $ext === 'xlsm') throw new RuntimeException('This is not a valid .xlsx file.');
        return [['name' => 'CSV', 'rows' => self::readCsv((string)file_get_contents($path), $maxRows), 'hidden' => false]];
    }

    /* ---------------- CSV ---------------- */

    public static function readCsv(string $raw, int $maxRows = self::MAX_ROWS): array {
        $raw = self::toUtf8($raw);
        $raw = str_replace(["\r\n", "\r"], "\n", $raw);
        if (trim($raw) === '') return [];
        $delim = self::detectDelimiter($raw);
        $fp = fopen('php://temp', 'r+');
        fwrite($fp, $raw); rewind($fp);
        $rows = [];
        while (($r = fgetcsv($fp, 0, $delim, '"', '')) !== false) {
            if ($r === [null]) continue;
            $rows[] = array_map(fn($c) => trim((string)$c), array_slice($r, 0, self::MAX_COLS));
            if (count($rows) >= $maxRows) break;
        }
        fclose($fp);
        return self::trimRows($rows);
    }

    public static function toUtf8(string $s): string {
        if (str_starts_with($s, "\xEF\xBB\xBF")) return substr($s, 3);
        if (str_starts_with($s, "\xFF\xFE") || str_starts_with($s, "\xFE\xFF")) {
            $le = $s[0] === "\xFF";
            $s = substr($s, 2);
            $out = '';
            for ($i = 0; $i + 1 < strlen($s); $i += 2) {
                $cp = $le ? ord($s[$i]) | (ord($s[$i + 1]) << 8) : (ord($s[$i]) << 8) | ord($s[$i + 1]);
                $out .= self::chr($cp);
            }
            return $out;
        }
        if (preg_match('//u', $s)) return $s;
        return self::cp1251ToUtf8($s);   // Russian Excel's "CSV" is windows-1251
    }

    public static function cp1251ToUtf8(string $s): string {
        static $map;
        if (!$map) {
            $map = [0x80 => 0x402, 0x81 => 0x403, 0x82 => 0x201A, 0x83 => 0x453, 0x84 => 0x201E, 0x85 => 0x2026, 0x86 => 0x2020, 0x87 => 0x2021, 0x88 => 0x20AC, 0x89 => 0x2030, 0x8A => 0x409, 0x8B => 0x2039, 0x8C => 0x40A, 0x8D => 0x40C, 0x8E => 0x40B, 0x8F => 0x40F,
                0x90 => 0x452, 0x91 => 0x2018, 0x92 => 0x2019, 0x93 => 0x201C, 0x94 => 0x201D, 0x95 => 0x2022, 0x96 => 0x2013, 0x97 => 0x2014, 0x99 => 0x2122, 0x9A => 0x459, 0x9B => 0x203A, 0x9C => 0x45A, 0x9D => 0x45C, 0x9E => 0x45B, 0x9F => 0x45F,
                0xA0 => 0xA0, 0xA1 => 0x40E, 0xA2 => 0x45E, 0xA3 => 0x408, 0xA4 => 0xA4, 0xA5 => 0x490, 0xA6 => 0xA6, 0xA7 => 0xA7, 0xA8 => 0x401, 0xA9 => 0xA9, 0xAA => 0x404, 0xAB => 0xAB, 0xAC => 0xAC, 0xAD => 0xAD, 0xAE => 0xAE, 0xAF => 0x407,
                0xB0 => 0xB0, 0xB1 => 0xB1, 0xB2 => 0x406, 0xB3 => 0x456, 0xB4 => 0x491, 0xB5 => 0xB5, 0xB6 => 0xB6, 0xB7 => 0xB7, 0xB8 => 0x451, 0xB9 => 0x2116, 0xBA => 0x454, 0xBB => 0xBB, 0xBC => 0x458, 0xBD => 0x405, 0xBE => 0x455, 0xBF => 0x457];
            for ($i = 0xC0; $i <= 0xFF; $i++) $map[$i] = 0x410 + ($i - 0xC0);
        }
        $out = '';
        for ($i = 0, $n = strlen($s); $i < $n; $i++) {
            $b = ord($s[$i]);
            $out .= $b < 0x80 ? $s[$i] : (isset($map[$b]) ? self::chr($map[$b]) : '?');
        }
        return $out;
    }

    private static function chr(int $cp): string {
        if ($cp < 0x80) return chr($cp);
        if ($cp < 0x800) return chr(0xC0 | ($cp >> 6)) . chr(0x80 | ($cp & 0x3F));
        return chr(0xE0 | ($cp >> 12)) . chr(0x80 | (($cp >> 6) & 0x3F)) . chr(0x80 | ($cp & 0x3F));
    }

    private static function detectDelimiter(string $raw): string {
        $lines = array_slice(explode("\n", $raw), 0, 12);
        $best = ','; $bestScore = -1;
        foreach ([';', ',', "\t", '|'] as $d) {
            $counts = [];
            foreach ($lines as $l) { if (trim($l) === '') continue; $counts[] = count(str_getcsv($l, $d, '"', '')); }
            if (!$counts) continue;
            $min = min($counts);
            $score = $min > 1 ? $min * 10 + (count(array_unique($counts)) === 1 ? 5 : 0) : 0;
            if ($score > $bestScore) { $bestScore = $score; $best = $d; }
        }
        return $best;
    }

    /* ---------------- XLSX ---------------- */

    private static function readXlsx(string $path, int $maxRows): array {
        $zip = self::unzip($path);
        if (!isset($zip['xl/workbook.xml'])) throw new RuntimeException('This is not a valid .xlsx file.');
        $strings = isset($zip['xl/sharedStrings.xml']) ? self::sharedStrings($zip['xl/sharedStrings.xml']) : [];
        $rels = [];
        if (isset($zip['xl/_rels/workbook.xml.rels'])) {
            $x = self::xml($zip['xl/_rels/workbook.xml.rels']);
            foreach ($x->Relationship ?? [] as $r) {
                $t = (string)$r['Target'];
                $rels[(string)$r['Id']] = ltrim(str_starts_with($t, '/') ? $t : 'xl/' . $t, '/');
            }
        }
        $wb = self::xml($zip['xl/workbook.xml']);
        $sheets = []; $i = 0;
        foreach ($wb->sheets->sheet ?? [] as $s) {
            $i++;
            $rid = (string)$s->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            $file = $rels[$rid] ?? "xl/worksheets/sheet$i.xml";
            if (!isset($zip[$file])) continue;
            $hidden = in_array((string)$s['state'], ['hidden', 'veryHidden'], true);
            $sheets[] = ['name' => (string)$s['name'], 'rows' => self::sheetRows($zip[$file], $strings, $maxRows), 'hidden' => $hidden];
        }
        if (!$sheets) throw new RuntimeException('The workbook has no readable sheets.');
        return $sheets;
    }

    private static function xml(string $s): SimpleXMLElement {
        $prev = libxml_use_internal_errors(true);
        $x = simplexml_load_string($s, 'SimpleXMLElement', LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE);
        libxml_use_internal_errors($prev);
        if (!$x) throw new RuntimeException('The file is damaged (bad XML).');
        return $x;
    }

    private static function sharedStrings(string $xml): array {
        $r = new XMLReader();
        $r->XML($xml, null, LIBXML_NONET | LIBXML_PARSEHUGE);
        $out = [];
        $more = $r->read();
        while ($more) {
            if ($r->nodeType === XMLReader::ELEMENT && $r->name === 'si') {
                $out[] = self::inlineText($r->readOuterXml());
                $more = $r->next();          // jump over the entry's children to its next sibling
            } else $more = $r->read();
        }
        $r->close();
        return $out;
    }

    /** Text of an <si>/<is> node: plain <t> or the concatenated rich-text runs (phonetic hints skipped). */
    private static function inlineText(string $outer): string {
        $outer = preg_replace('/ xmlns(:\w+)?="[^"]*"/', '', $outer);
        $prev = libxml_use_internal_errors(true);
        $sx = simplexml_load_string($outer, 'SimpleXMLElement', LIBXML_NONET);
        libxml_use_internal_errors($prev);
        if (!$sx) return '';
        $t = isset($sx->t) ? (string)$sx->t : '';
        foreach ($sx->r ?? [] as $run) $t .= (string)$run->t;
        return $t;
    }

    private static function sheetRows(string $xml, array $strings, int $maxRows): array {
        $r = new XMLReader();
        $r->XML($xml, null, LIBXML_NONET | LIBXML_PARSEHUGE);
        $rows = []; $rowNo = 0;
        while ($r->read()) {
            if ($r->nodeType !== XMLReader::ELEMENT || $r->name !== 'row') continue;
            $rowNo = $r->getAttribute('r') !== null ? (int)$r->getAttribute('r') : $rowNo + 1;
            if ($rowNo > $maxRows) break;
            $cells = []; $col = -1;
            if (!$r->isEmptyElement) {
                $depth = $r->depth;
                while ($r->read() && !($r->nodeType === XMLReader::END_ELEMENT && $r->depth === $depth)) {
                    if ($r->nodeType !== XMLReader::ELEMENT || $r->name !== 'c' || $r->depth !== $depth + 1) continue;
                    $ref = $r->getAttribute('r'); $type = (string)$r->getAttribute('t');
                    $col = $ref !== null && preg_match('/^([A-Z]+)/', $ref, $m) ? self::colIndex($m[1]) : $col + 1;
                    $v = ''; $inline = '';
                    if (!$r->isEmptyElement) {
                        $cd = $r->depth;
                        while ($r->read() && !($r->nodeType === XMLReader::END_ELEMENT && $r->depth === $cd)) {
                            if ($r->nodeType !== XMLReader::ELEMENT) continue;
                            if ($r->name === 'v') $v = $r->readString();
                            elseif ($r->name === 'is') $inline = self::inlineText($r->readOuterXml());
                        }
                    }
                    if ($col >= self::MAX_COLS) continue;
                    $cells[$col] = self::cell($type, $v, $inline, $strings);
                }
            }
            $line = [];
            if ($cells) {
                $line = array_fill(0, max(array_keys($cells)) + 1, '');
                foreach ($cells as $k => $c) $line[$k] = $c;
            }
            while (count($rows) < $rowNo - 1) $rows[] = [];
            $rows[$rowNo - 1] = $line;
        }
        $r->close();
        return self::trimRows(array_values($rows));
    }

    private static function cell(string $type, string $v, string $inline, array $strings): string {
        switch ($type) {
            case 's': return trim($strings[(int)$v] ?? '');
            case 'inlineStr': return trim($inline);
            case 'b': return $v === '1' ? 'TRUE' : 'FALSE';
            case 'e': return '';
            case 'str': return trim($v);
        }
        $v = trim($v);
        if ($v !== '' && preg_match('/^-?\d+(\.\d+)?[eE][+-]?\d+$/', $v)) {
            $f = (float)$v;
            if (abs($f) < 1e17 && floor($f) == $f) return sprintf('%.0f', $f);
        }
        return $v;
    }

    public static function colIndex(string $letters): int {
        $n = 0;
        for ($i = 0, $l = strlen($letters); $i < $l; $i++) $n = $n * 26 + (ord($letters[$i]) - 64);
        return $n - 1;
    }

    /** Drops trailing empty rows and pads every row to a common width. */
    private static function trimRows(array $rows): array {
        while ($rows && !array_filter(end($rows), fn($c) => $c !== '')) array_pop($rows);
        $w = 0;
        foreach ($rows as $r) { $k = count($r); while ($k > 0 && $r[$k - 1] === '') $k--; $w = max($w, $k); }
        foreach ($rows as &$r) { $r = array_slice($r, 0, $w); while (count($r) < $w) $r[] = ''; }
        return $rows;
    }

    /* ---------------- minimal zip reader (central directory + inflate) ---------------- */

    /** @return array<string,string> name => decompressed contents, only the xl/ parts that matter. */
    private static function unzip(string $path): array {
        $fp = fopen($path, 'rb');
        if (!$fp) throw new RuntimeException('Cannot open the file.');
        $size = (int)filesize($path);
        $tail = min($size, 65557);
        fseek($fp, $size - $tail);
        $buf = (string)fread($fp, $tail);
        $p = strrpos($buf, "PK\x05\x06");
        if ($p === false) { fclose($fp); throw new RuntimeException('This is not a valid .xlsx file.'); }
        $eocd = unpack('vdisk/vcdisk/ventries/vtotal/Vcdsize/Vcdoff', substr($buf, $p + 4, 16));
        fseek($fp, $eocd['cdoff']);
        $cd = (string)fread($fp, max(1, $eocd['cdsize']));
        $out = []; $pos = 0; $total = 0;
        for ($i = 0; $i < $eocd['total'] && $pos + 46 <= strlen($cd); $i++) {
            if (substr($cd, $pos, 4) !== "PK\x01\x02") break;
            $h = unpack('vver/vneed/vflags/vmethod/vtime/vdate/Vcrc/Vcsize/Vusize/vnlen/vxlen/vclen', substr($cd, $pos + 4, 42));
            $off = unpack('Voff', substr($cd, $pos + 42, 4))['off'];
            $name = substr($cd, $pos + 46, $h['nlen']);
            $pos += 46 + $h['nlen'] + $h['xlen'] + $h['clen'];
            if (!preg_match('~^xl/(workbook\.xml|_rels/workbook\.xml\.rels|sharedStrings\.xml|worksheets/[^/]+\.xml)$~', $name)) continue;
            if ($h['usize'] > self::MAX_XML) { fclose($fp); throw new RuntimeException('The spreadsheet is too large to import.'); }
            fseek($fp, $off);
            $lh = (string)fread($fp, 30);
            if (strlen($lh) < 30 || substr($lh, 0, 4) !== "PK\x03\x04") continue;
            $l = unpack('vnlen/vxlen', substr($lh, 26, 4));
            fseek($fp, $off + 30 + $l['nlen'] + $l['xlen']);
            $data = $h['csize'] > 0 ? (string)fread($fp, $h['csize']) : '';
            if ($h['method'] === 8) $data = (string)@gzinflate($data, self::MAX_XML);
            elseif ($h['method'] !== 0) continue;
            $total += strlen($data);
            if ($total > self::MAX_XML * 2) { fclose($fp); throw new RuntimeException('The spreadsheet is too large to import.'); }
            $out[$name] = $data;
        }
        fclose($fp);
        return $out;
    }
}
