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
    /** The service icons of a listing: line style, no fill, 24x24, drawn in the text colour. Keys: video, parking, h24, security, warehouse, heart, chat, list, pin, plus, tag. */
    function sr_icon(string $k, string $label = ''): string
    {
        $p = [
            // wall-mounted CCTV camera: wall plate, bracket, body with a lens, a stand
            'video' => '<path d="M3 4.5v9"/><path d="M3 8h3"/><rect x="6" y="5.5" width="14" height="6.5" rx="1.6" transform="rotate(8 13 8.7)"/><circle cx="17" cy="9.3" r="1.3"/><path d="M10 14.5v3"/><path d="M7 20.5h6"/>',
            // a warehouse: roof, walls and a roller door (placeholder of a card without a photo)
            'warehouse' => '<path d="M3 9.5L12 4l9 5.5"/><path d="M4.5 8.6V20h15V8.6"/><rect x="8" y="12.5" width="8" height="7.5" rx="0.8"/><path d="M8 15h8M8 17.5h8"/>',
            'heart' => '<path d="M12 20.3S3.6 15.1 2.9 9.6C2.5 6.5 4.6 4.3 7.2 4.3c1.9 0 3.6 1 4.8 2.9 1.2-1.9 2.9-2.9 4.8-2.9 2.6 0 4.7 2.2 4.3 5.3-.7 5.5-9.1 10.7-9.1 10.7z"/>',
            'chat' => '<path d="M4 4.5h16v11.2H10.2L6 19.5v-3.8H4z"/><path d="M8 9h8M8 12h5"/>',
            'list' => '<path d="M8.5 6h11.5M8.5 12h11.5M8.5 18h11.5"/><path d="M4 6h.01M4 12h.01M4 18h.01"/>',
            'pin' => '<path d="M12 21s-6.5-5.6-6.5-11a6.5 6.5 0 0 1 13 0c0 5.4-6.5 11-6.5 11z"/><circle cx="12" cy="10" r="2.3"/>',
            'plus' => '<path d="M12 5v14M5 12h14"/>',
            'tag' => '<path d="M3.5 12.6V4.5h8.1l9 9-8.1 8.1z"/><circle cx="8" cy="9" r="1.3"/>',
            'parking' => '<rect x="3.5" y="3.5" width="17" height="17" rx="3.2"/><path d="M9.6 17.2V7.3h3.4a3 3 0 0 1 0 6H9.6"/>',
            'h24' => '<path d="M20.2 12a8.2 8.2 0 1 1-2.4-5.8"/><path d="M18.6 2.8v3.8h-3.8"/><path d="M12 7.6V12l3 1.8"/>',
            'security' => '<path d="M12 3l7.5 2.8v5.6c0 4.4-3.1 7.9-7.5 9.6-4.4-1.7-7.5-5.2-7.5-9.6V5.8z"/><path d="M8.7 12.2l2.4 2.4 4.2-4.6"/>',
        ][$k] ?? '';
        $a = $label !== '' ? ' role="img" aria-label="' . htmlspecialchars($label, ENT_QUOTES) . '"' : ' aria-hidden="true"';
        return '<svg class="sr-ic" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"' . $a . '>' . $p . '</svg>';
    }
    /** The Add to / Remove from Favorites button (a plain POST form, no script needed). $back: where to return to. */
    function sr_fav(object $sf, string $ref, bool $on, string $back, bool $guest = false): string
    {
        $label = $sf->t($on ? 'fav_remove' : 'fav_add');
        if ($guest) return '<a class="sr-fav-btn" href="' . e(url('/login')) . '" title="' . e($sf->t('fav_login')) . '">' . sr_icon('heart') . '<span>' . e($sf->t('fav_add')) . '</span></a>';
        return '<form method="post" action="' . e($sf->url('/offers/favorite')) . '" class="sr-fav">' . csrf_field() . '<input type="hidden" name="ref" value="' . e($ref) . '"><input type="hidden" name="back" value="' . e($back) . '">'
            . '<button type="submit" class="sr-fav-btn' . ($on ? ' on' : '') . '" aria-pressed="' . ($on ? 'true' : 'false') . '">' . sr_icon('heart') . '<span>' . e($label) . '</span></button></form>';
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
