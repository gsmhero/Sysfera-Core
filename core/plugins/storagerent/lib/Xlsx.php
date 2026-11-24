<?php
declare(strict_types=1);
namespace SysferaStoragerent;

/** Minimal .xlsx writer (one sheet, text and numbers, bold header) in pure PHP: the zip container is written by hand, so neither ZipArchive nor any library is needed (zlib only, for deflate). */
final class Xlsx
{
    /** @param array $matrix list of rows; int/float = number, anything else = text. The first row is the header. */
    public static function build(array $matrix, string $sheet = 'Report'): string
    {
        $col = function (int $i): string { $s = ''; for ($i++; $i > 0; $i = intdiv($i - 1, 26)) $s = chr(65 + ($i - 1) % 26) . $s; return $s; };
        $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach ($matrix as $r => $row) {
            $x .= '<row r="' . ($r + 1) . '">';
            foreach (array_values($row) as $c => $v) {
                $ref = $col($c) . ($r + 1); $st = $r === 0 ? ' s="1"' : '';
                if ($v === null || $v === '') continue;
                if (is_int($v) || is_float($v)) $x .= '<c r="' . $ref . '"' . $st . '><v>' . (is_float($v) ? rtrim(rtrim(number_format($v, 6, '.', ''), '0'), '.') : $v) . '</v></c>';
                else $x .= '<c r="' . $ref . '"' . $st . ' t="inlineStr"><is><t xml:space="preserve">' . htmlspecialchars(self::xmlSafe((string)$v), ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</t></is></c>';
            }
            $x .= '</row>';
        }
        $x .= '</sheetData></worksheet>';
        $name = htmlspecialchars(substr(preg_replace('/[\\\\\/?*\[\]:]/', ' ', $sheet), 0, 31), ENT_XML1 | ENT_QUOTES);
        return self::zip([
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="' . $name . '" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
            'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs></styleSheet>',
            'xl/worksheets/sheet1.xml' => $x,
        ]);
    }
    /** Drops the characters XML 1.0 cannot carry. */
    private static function xmlSafe(string $s): string { return (string)preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $s); }
    /** @param array<string,string> $files */
    public static function zip(array $files): string
    {
        $out = ''; $cd = ''; $n = 0;
        foreach ($files as $name => $data) {
            $crc = crc32($data); $size = strlen($data); $def = function_exists('gzdeflate') ? gzdeflate($data, 6) : false; $method = $def !== false ? 8 : 0; $comp = $def !== false ? $def : $data;
            $off = strlen($out);
            $out .= "PK\x03\x04" . pack('vvvvvVVVvv', 20, 0x0800, $method, 0, 0x21, $crc, strlen($comp), $size, strlen($name), 0) . $name . $comp;
            $cd .= "PK\x01\x02" . pack('vvvvvvVVVvvvvvVV', 20, 20, 0x0800, $method, 0, 0x21, $crc, strlen($comp), $size, strlen($name), 0, 0, 0, 0, 0, $off) . $name; $n++;
        }
        return $out . $cd . "PK\x05\x06" . pack('vvvvVVv', 0, 0, $n, $n, strlen($cd), strlen($out), 0);
    }
}
