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
    /** The service icons of a listing: line style, no fill, 24x24, drawn in the text colour. Keys: video, parking, h24, security, warehouse. */
    function sr_icon(string $k, string $label = ''): string
    {
        $p = [
            // wall-mounted CCTV camera: wall plate, bracket, body with a lens, a stand
            'video' => '<path d="M3 4.5v9"/><path d="M3 8h3"/><rect x="6" y="5.5" width="14" height="6.5" rx="1.6" transform="rotate(8 13 8.7)"/><circle cx="17" cy="9.3" r="1.3"/><path d="M10 14.5v3"/><path d="M7 20.5h6"/>',
            // a warehouse: roof, walls and a roller door (placeholder of a card without a photo)
            'warehouse' => '<path d="M3 9.5L12 4l9 5.5"/><path d="M4.5 8.6V20h15V8.6"/><rect x="8" y="12.5" width="8" height="7.5" rx="0.8"/><path d="M8 15h8M8 17.5h8"/>',
            'parking' => '<rect x="3.5" y="3.5" width="17" height="17" rx="3.2"/><path d="M9.6 17.2V7.3h3.4a3 3 0 0 1 0 6H9.6"/>',
            'h24' => '<path d="M20.2 12a8.2 8.2 0 1 1-2.4-5.8"/><path d="M18.6 2.8v3.8h-3.8"/><path d="M12 7.6V12l3 1.8"/>',
            'security' => '<path d="M12 3l7.5 2.8v5.6c0 4.4-3.1 7.9-7.5 9.6-4.4-1.7-7.5-5.2-7.5-9.6V5.8z"/><path d="M8.7 12.2l2.4 2.4 4.2-4.6"/>',
        ][$k] ?? '';
        $a = $label !== '' ? ' role="img" aria-label="' . htmlspecialchars($label, ENT_QUOTES) . '"' : ' aria-hidden="true"';
        return '<svg class="sr-ic" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"' . $a . '>' . $p . '</svg>';
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
