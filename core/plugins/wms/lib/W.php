<?php
declare(strict_types=1);
namespace SysferaWms;

/** Small formatting helpers used by the views. */
final class W
{
    /** 12.5 → "12,5", 3.000 → "3" */
    public static function qty($n): string
    {
        $s = rtrim(rtrim(number_format((float)$n, 3, ',', "\u{202F}"), '0'), ',');
        return $s === '' || $s === '-' ? '0' : $s;
    }
    public static function money($n): string { return number_format((float)$n, 2, ',', "\u{202F}") . ' ₽'; }
    public static function date(?string $s, bool $time = true): string { return $s ? date($time ? 'd.m.Y H:i' : 'd.m.Y', strtotime($s)) : '—'; }
    /** Value for <input type="datetime-local"> */
    public static function input(?string $s): string { return date('Y-m-d\TH:i', $s ? strtotime($s) : time()); }

    public static function type(\Sysfera\Plugin $sf, string $type): string { return $sf->t('type_' . $type); }

    public static function badge(\Sysfera\Plugin $sf, string $status): string
    {
        $cls = ['draft' => 'mute', 'posted' => 'ok', 'cancelled' => 'bad'][$status] ?? 'mute';
        return '<span class="badge ' . $cls . '">' . e($sf->t('status_' . $status)) . '</span>';
    }

    /** Stock level chip: ok / low / none. */
    public static function level(float $available, float $min): string
    {
        if ($available <= 0) return 'none';
        return ($min > 0 && $available < $min) ? 'low' : 'ok';
    }

    public static function page(int $total, int $per, int $cur): array
    {
        $pages = max(1, (int)ceil($total / $per));
        return ['pages' => $pages, 'cur' => min(max(1, $cur), $pages)];
    }

    /** Photo URLs from a text box: https only, unique, max 8, one per line. */
    public static function images(?string $text): string
    {
        $out = [];
        foreach (preg_split('/[\s,]+/', (string)$text, -1, PREG_SPLIT_NO_EMPTY) as $u) if (preg_match('~^https://[^\s]+$~i', $u) && mb_strlen($u) <= 500) $out[] = $u;
        return implode("\n", array_slice(array_values(array_unique($out)), 0, 8));
    }

    /** Specifications from parallel form arrays → list of {section,name,value}; rows without a name or value are dropped. */
    public static function specs(array $section, array $name, array $value): array
    {
        $out = [];
        foreach ($name as $i => $n) {
            $n = mb_substr(trim((string)$n), 0, 120); $v = mb_substr(trim((string)($value[$i] ?? '')), 0, 500);
            if ($n !== '' && $v !== '' && count($out) < 60) $out[] = ['section' => mb_substr(trim((string)($section[$i] ?? '')), 0, 120), 'name' => $n, 'value' => $v];
        }
        return $out;
    }

    /** Options (variants) from parallel form arrays → list of {name,price,stock}; price 0 = same as the product, names are unique. */
    public static function variants(array $name, array $price, array $stock): array
    {
        $out = []; $seen = [];
        foreach ($name as $i => $n) {
            $n = mb_substr(trim((string)$n), 0, 120); if ($n === '' || isset($seen[mb_strtolower($n)]) || count($out) >= 50) continue;
            $seen[mb_strtolower($n)] = 1;
            $out[] = ['name' => $n, 'price' => max(0.0, round((float)str_replace(',', '.', (string)($price[$i] ?? 0)), 2)), 'stock' => max(0, (int)($stock[$i] ?? 0))];
        }
        return $out;
    }

    /** A document the viewer may not look into: only what keeps the stock figures honest stays. */
    public static function mask(array $d): array
    {
        foreach (['contact', 'comment', 'ref'] as $k) if (array_key_exists($k, $d)) $d[$k] = null;
        if (array_key_exists('total', $d)) $d['total'] = 0;
        $d['private'] = true; $d['number'] = '·····'; unset($d['progress']);
        return $d;
    }

    public static function csv(string $name, array $head, array $rows): never
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $name) . '"');
        $f = fopen('php://output', 'w');
        fwrite($f, "\xEF\xBB\xBF");   // BOM so Excel opens UTF-8 correctly
        fputcsv($f, $head, ';');
        foreach ($rows as $r) fputcsv($f, $r, ';');
        fclose($f);
        exit;
    }
}
