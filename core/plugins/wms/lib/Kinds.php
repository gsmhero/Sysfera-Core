<?php
declare(strict_types=1);
namespace SysferaWms;

/**
 * Product types, as pure functions (no database).
 *  simple       an ordinary stock item: has its own stock.
 *  variable     a card with variations; every variation is a product from the warehouse (own stock, own price). The card itself has no stock.
 *  grouped      a showcase of separate products sold one by one. No stock, not sold as one item.
 *  virtual      a service or anything without a physical stock. Never limited by stock.
 *  downloadable like virtual, delivered as a file/link.
 *  bundle       a set of products sold together; stock is derived from the components, which are taken from stock when the set is sold.
 */
final class Kinds
{
    public const TYPES = ['simple', 'variable', 'grouped', 'virtual', 'downloadable', 'bundle'];

    public static function normal(?string $t): string { return in_array($t, self::TYPES, true) ? (string)$t : 'simple'; }
    /** Types that have their own rows in the stock ledger. */
    public static function ledger(string $t): bool { return $t === 'simple'; }
    /** Types made of other products (rows in the links table). */
    public static function composite(string $t): bool { return in_array($t, ['variable', 'grouped', 'bundle'], true); }
    /** Cards that cannot be put into a document. */
    public static function container(string $t): bool { return in_array($t, ['variable', 'grouped'], true); }
    public static function unlimited(string $t): bool { return in_array($t, ['virtual', 'downloadable'], true); }

    /** Which product types may be components of a type. */
    public static function allowedChild(string $parent, string $child): bool
    {
        return match ($parent) { 'variable', 'bundle' => $child === 'simple', 'grouped' => !self::container($child), default => false };
    }

    /**
     * Components from the form's parallel arrays. A product is written "#12 · Name" (what the list suggests), or just a number.
     * @return list<array{child:int,label:string,qty:float,price:float}>  unique, without the card itself
     */
    public static function links(string $type, int $self, array $child, array $label, array $qty, array $price): array
    {
        $out = []; $seen = [];
        if (!self::composite($type)) return [];
        foreach ($child as $i => $c) {
            if (!preg_match('/^\s*#?(\d+)/', (string)$c, $m)) continue;
            $id = (int)$m[1]; if ($id <= 0 || $id === $self || isset($seen[$id]) || count($out) >= 100) continue;
            $seen[$id] = 1;
            $q = $type === 'bundle' ? round((float)str_replace(',', '.', (string)($qty[$i] ?? 1)), 3) : 1.0;
            $out[] = ['child' => $id, 'label' => $type === 'variable' ? mb_substr(trim((string)($label[$i] ?? '')), 0, 120) : '',
                'qty' => $q > 0 ? $q : 1.0, 'price' => $type === 'variable' ? max(0.0, round((float)str_replace(',', '.', (string)($price[$i] ?? 0)), 2)) : 0.0];
        }
        return $out;
    }

    /**
     * Document lines → lines that really move stock.
     * @param list<array> $lines  each: id, product_id, qty, …
     * @param array<int,string> $kind  product_id => type
     * @param array<int,list<array{child:int,qty:float}>> $comps  bundle id => components
     * @param array<int,float> $open  product_id => quantity still open on the parent order
     * @return array{0:list<array>,1:array<int,float>,2:list<string>} lines, open, problems (translation keys)
     */
    public static function expand(string $type, array $lines, array $kind, array $comps, array $open = []): array
    {
        $out = []; $bad = [];
        foreach ($lines as $l) {
            $p = (int)$l['product_id']; $k = $kind[$p] ?? 'simple';
            if (self::container($k)) { $bad[] = 'err_container'; continue; }
            if (self::unlimited($k)) continue;
            if ($k === 'bundle') {
                if (in_array($type, ['receipt', 'purchase', 'receiving', 'inventory'], true)) { $bad[] = 'err_bundle_in'; continue; }
                foreach ($comps[$p] ?? [] as $c) $out[] = ['product_id' => (int)$c['child'], 'qty' => (float)$l['qty'] * (float)$c['qty']] + $l;
                continue;
            }
            $out[] = $l;
        }
        foreach ($open as $p => $q) {
            $k = $kind[$p] ?? 'simple';
            if ($k === 'bundle') { foreach ($comps[$p] ?? [] as $c) $open[(int)$c['child']] = ($open[(int)$c['child']] ?? 0.0) + $q * (float)$c['qty']; unset($open[$p]); }
            elseif (!self::ledger($k)) unset($open[$p]);
        }
        return [$out, $open, array_values(array_unique($bad))];
    }

    /** How many sets can be made from the components. @param list<array{qty:float,avail:float}> $parts */
    public static function bundleStock(array $parts): float
    {
        if (!$parts) return 0.0;
        $min = INF; foreach ($parts as $x) $min = min($min, max(0.0, (float)$x['avail']) / max(0.000001, (float)$x['qty']));
        return $min === INF ? 0.0 : $min;
    }
}
