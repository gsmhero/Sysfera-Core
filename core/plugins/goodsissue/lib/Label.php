<?php
declare(strict_types=1);
namespace SysferaGi;

/** Code 128 (subset B) barcodes as inline SVG for package and shelf-cell labels. Printable text up to ASCII 126. */
final class Label
{
    /** bar/space widths of the 107 symbols: 0..102 data, 103 Start A, 104 Start B, 105 Start C, 106 Stop (7 elements) */
    public const PATTERNS = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213', '221312', '231212', '112232', '122132', '122231', '113222',
        '123122', '123221', '223211', '221132', '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211', '212123', '212321',
        '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313', '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121',
        '313121', '211331', '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111', '314111', '221411', '431111', '111224',
        '111422', '121124', '121421', '141122', '141221', '112214', '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141', '214121', '412121', '111143', '111341', '131141', '114113',
        '114311', '411113', '411311', '113141', '114131', '311141', '411131', '211412', '211214', '211232', '2331112',
    ];

    /** @return list<int> symbol values incl. start, checksum and stop */
    public static function symbols(string $text): array
    {
        if ($text === '' || strlen($text) > 40 || preg_match('/[^\x20-\x7E]/', $text)) throw new Err('bad_label', 'a label text is 1-40 printable ASCII characters', 422);
        $v = [104]; $sum = 104;
        foreach (str_split($text) as $k => $ch) { $c = ord($ch) - 32; $v[] = $c; $sum += $c * ($k + 1); }
        $v[] = $sum % 103; $v[] = 106;
        return $v;
    }

    /** @return string the bar widths of the whole symbol as a string of digits (alternating bar, space, ...) */
    public static function widths(string $text): string { return implode('', array_map(fn($s) => self::PATTERNS[$s], self::symbols($text))); }

    /** SVG of the barcode; $module = width of the thinnest bar in px */
    public static function svg(string $text, int $height = 60, int $module = 2): string
    {
        $w = self::widths($text); $x = 10 * $module; $bars = '';   // 10 modules of quiet zone
        for ($i = 0, $n = strlen($w); $i < $n; $i++) { $u = (int)$w[$i] * $module; if ($i % 2 === 0) $bars .= '<rect x="' . $x . '" y="0" width="' . $u . '" height="' . $height . '"/>'; $x += $u; }
        $total = $x + 10 * $module;
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $total . ' ' . $height . '" width="' . $total . '" height="' . $height . '" role="img" aria-label="' . htmlspecialchars($text, ENT_QUOTES) . '"><rect width="' . $total . '" height="' . $height . '" fill="#fff"/><g fill="#000">' . $bars . '</g></svg>';
    }

    /** shelf cell label text: the app understands the "SH:" prefix */
    public static function shelf(string $code): string { return 'SH:' . $code; }

    /** the cells of a range: prefix A, 1..12 -> A1..A12 (at most 200) */
    public static function range(string $prefix, int $from, int $to): array
    {
        $prefix = strtoupper(trim($prefix));
        if ($to < $from || $to - $from >= 200 || $from < 0 || !preg_match('/^[A-Z0-9-]{0,12}$/', $prefix)) throw new Err('bad_range', 'a range is at most 200 cells, prefix letters/digits', 422);
        $o = []; for ($i = $from; $i <= $to; $i++) $o[] = Exchange::shelfCode($prefix . $i);
        return $o;
    }
}
