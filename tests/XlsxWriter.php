<?php
declare(strict_types=1);

/** Tiny .xlsx writer for test fixtures (zip + XML, shared strings, optional hidden sheets). Needs only zlib. */
final class XlsxWriter {
    /** @param array<int,array{name:string,rows:array,hidden?:bool}> $sheets rows = list of lists; null/'' = empty cell, int/float = number, string = text */
    public static function build(array $sheets): string {
        $strings = []; $sIdx = [];
        $files = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
        ];
        $wbSheets = ''; $rels = '';
        foreach ($sheets as $i => $sh) {
            $n = $i + 1;
            $x = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
            foreach ($sh['rows'] as $r => $row) {
                if (!$row) continue;
                $x .= '<row r="' . ($r + 1) . '">';
                foreach ($row as $c => $v) {
                    if ($v === null || $v === '') continue;
                    $ref = self::col($c) . ($r + 1);
                    if (is_int($v) || is_float($v)) $x .= '<c r="' . $ref . '"><v>' . $v . '</v></c>';
                    elseif (str_starts_with((string)$v, '=inline:')) $x .= '<c r="' . $ref . '" t="inlineStr"><is><t>' . htmlspecialchars(substr((string)$v, 8), ENT_XML1) . '</t></is></c>';
                    else { $k = (string)$v; if (!isset($sIdx[$k])) { $sIdx[$k] = count($strings); $strings[] = $k; } $x .= '<c r="' . $ref . '" t="s"><v>' . $sIdx[$k] . '</v></c>'; }
                }
                $x .= '</row>';
            }
            $files["xl/worksheets/sheet$n.xml"] = $x . '</sheetData></worksheet>';
            $wbSheets .= '<sheet name="' . htmlspecialchars($sh['name'], ENT_XML1) . '" sheetId="' . $n . '"' . (!empty($sh['hidden']) ? ' state="hidden"' : '') . ' r:id="rId' . $n . '"/>';
            $rels .= '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>';
        }
        $ss = '<?xml version="1.0" encoding="UTF-8"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($strings) . '" uniqueCount="' . count($strings) . '">';
        foreach ($strings as $k => $s) $ss .= $k % 2 ? '<si><r><rPr><b/></rPr><t xml:space="preserve">' . htmlspecialchars($s, ENT_XML1) . '</t></r></si>' : '<si><t xml:space="preserve">' . htmlspecialchars($s, ENT_XML1) . '</t></si>';   // alternate plain / rich-text entries
        $files['xl/sharedStrings.xml'] = $ss . '</sst>';
        $files['xl/workbook.xml'] = '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>' . $wbSheets . '</sheets></workbook>';
        $rels .= '<Relationship Id="rId99" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>';
        $files['xl/_rels/workbook.xml.rels'] = '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $rels . '</Relationships>';
        return self::zip($files);
    }

    private static function col(int $i): string { $s = ''; for ($i++; $i > 0; $i = intdiv($i - 1, 26)) $s = chr(65 + ($i - 1) % 26) . $s; return $s; }

    private static function zip(array $files): string {
        $out = ''; $cd = ''; $n = 0;
        foreach ($files as $name => $data) {
            $crc = crc32($data); $z = gzdeflate($data);
            $off = strlen($out);
            $out .= "PK\x03\x04" . pack('vvvvvVVVvv', 20, 0, 8, 0, 0x21, $crc, strlen($z), strlen($data), strlen($name), 0) . $name . $z;
            $cd .= "PK\x01\x02" . pack('vvvvvvVVVvvvvvVV', 20, 20, 0, 8, 0, 0x21, $crc, strlen($z), strlen($data), strlen($name), 0, 0, 0, 0, 0, $off) . $name;
            $n++;
        }
        return $out . $cd . "PK\x05\x06" . pack('vvvvVVv', 0, 0, $n, $n, strlen($cd), strlen($out), 0);
    }
}
