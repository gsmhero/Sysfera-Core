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
