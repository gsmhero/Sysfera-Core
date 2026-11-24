<?php
declare(strict_types=1);
namespace SysferaStoragerent;

/** Turns the machine descriptions stored on invoice lines ("rent|01.03.2026 - 31.03.2026|m2|500.00") into text in the reader's language. Manual lines are plain text and pass through. */
final class Text
{
    /** @param callable(string,array):string $t translation function of the plugin */
    public static function item(array $it, callable $t): string
    {
        $d = (string)$it['description']; $kind = (string)$it['kind'];
        $p = explode('|', $d);
        if ($kind === 'rent' && $p[0] === 'rent') return $t('item_rent', ['period' => $p[1] ?? '', 'qty' => rtrim(rtrim(number_format((float)$it['qty'], 3, '.', ''), '0'), '.'), 'unit' => $t('unit_' . ($p[2] ?? 'm2'), []), 'rate' => $p[3] ?? '', 'part' => isset($p[4]) ? $t('item_part', ['days' => $p[4]]) : '']);
        if ($kind === 'utility' && $p[0] === 'utility_fixed') return $t('item_util_fixed', ['period' => $p[1] ?? '']);
        if ($kind === 'utility' && $p[0] === 'utility') return $t('item_util', ['type' => $t('meter_' . ($p[1] ?? 'electricity'), []), 'unit' => $p[2] ?? '', 'date' => $p[3] ?? '', 'qty' => $p[4] ?? '']);
        if ($kind === 'penalty' && $p[0] === 'penalty') return $t('item_penalty', ['invoices' => $p[1] ?? '']);
        return $d;
    }
}
