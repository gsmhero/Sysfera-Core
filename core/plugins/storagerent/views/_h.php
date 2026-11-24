<?php
/* Helpers shared by the views of Storage Rent (money, dates, badges, form fields). Included with require_once; every function is guarded so views can be rendered more than once (tests). */
use SysferaStoragerent\Billing;
if (!function_exists('sr_m')) {
    function sr_m(int|float|string|null $k, string $lang = 'ru'): string { return Billing::fmt((int)$k, $lang) . ($lang === 'ru' ? "\u{a0}₽" : ' RUB'); }
    function sr_d(?string $d): string { return Billing::dmy($d); }
    function sr_dt(?int $t): string { return $t ? gmdate('d.m.Y H:i', $t + 3 * 3600) : ''; }
    /** Badge class of a status word. */
    function sr_cls(string $s): string
    {
        return match ($s) { 'paid', 'active', 'free', 'done', 'sent', 'published' => 'ok', 'overdue', 'terminated', 'cancelled', 'maintenance', 'suspended', 'failed' => 'bad', 'partially_paid', 'expiring', 'reserved', 'draft', 'in_progress', 'ended', 'new' => 'warn', 'occupied', 'issued' => 'info', default => 'mute' };
    }
    function sr_num(float|int|string|null $n): string { return rtrim(rtrim(number_format((float)$n, 2, '.', ''), '0'), '.') ?: '0'; }
    /** A form field: label + input. $v is the current value. */
    function sr_in(string $name, string $label, mixed $v = '', string $type = 'text', string $extra = ''): string
    {
        return '<label class="fld"><span>' . e($label) . '</span><input type="' . e($type) . '" name="' . e($name) . '" value="' . e((string)$v) . '" ' . $extra . '></label>';
    }
    function sr_sel(string $name, string $label, array $opts, mixed $cur, string $extra = ''): string
    {
        $o = ''; foreach ($opts as $val => $text) $o .= '<option value="' . e((string)$val) . '"' . ((string)$val === (string)$cur ? ' selected' : '') . '>' . e((string)$text) . '</option>';
        return '<label class="fld"><span>' . e($label) . '</span><select name="' . e($name) . '" ' . $extra . '>' . $o . '</select></label>';
    }
    function sr_area(string $name, string $label, mixed $v = '', string $hint = '', int $rows = 3): string
    {
        return '<label class="fld"><span>' . e($label) . '</span><textarea name="' . e($name) . '" rows="' . $rows . '">' . e((string)$v) . '</textarea>' . ($hint !== '' ? '<small class="muted">' . e($hint) . '</small>' : '') . '</label>';
    }
    function sr_chk(string $name, string $label, mixed $on, string $extra = ''): string
    {
        return '<label class="sr-chk"><input type="checkbox" name="' . e($name) . '" value="1"' . ($on ? ' checked' : '') . ' ' . $extra . '> <span>' . e($label) . '</span></label>';
    }
}
