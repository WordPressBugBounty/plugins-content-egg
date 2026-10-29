<?php

namespace ContentEgg\application\deals;

defined('\ABSPATH') || exit;

/**
 * DealSignals class file
 *
 * The deal facts a module already stores on an item: the store's own deal
 * badge and end time, and what the old price is measured against. Only data
 * the module has fetched anyway; nothing here calls an API. Pure.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class DealSignals
{
    const BASIS_WAS_PRICE = 'was_price';
    const BASIS_LIST_PRICE = 'list_price';
    const BASIS_OTHER = 'other';

    /**
     * @return array|null {badge?, starts?, ends?, price_basis?, price_basis_label?,
     *                    access?, percent_claimed?}; times are unix timestamps.
     */
    public static function fromItem(string $module_id, array $item): ?array
    {
        $extra = $item['extra'] ?? array();
        $extra = is_object($extra) ? get_object_vars($extra) : (array) $extra;

        switch (explode('__', $module_id)[0])
        {
            case 'Amazon':
                $s = self::amazon($extra);
                break;
            case 'Walmart':
                $s = self::walmart($extra);
                break;
            default:
                $s = array();
        }

        return $s ? $s : null;
    }

    private static function amazon(array $x): array
    {
        $s = array();

        if (($badge = self::text($x['DealBadge'] ?? null)) !== '')
            $s['badge'] = $badge;
        if ($starts = self::time($x['DealStartTime'] ?? null))
            $s['starts'] = $starts;
        if ($ends = self::time($x['DealEndTime'] ?? null))
            $s['ends'] = $ends;

        $basis = self::text($x['SavingBasisType'] ?? null);
        if ($basis !== '')
        {
            if ($basis === 'WAS_PRICE')
                $s['price_basis'] = self::BASIS_WAS_PRICE;
            elseif ($basis === 'LIST_PRICE')
                $s['price_basis'] = self::BASIS_LIST_PRICE;
            else
                $s['price_basis'] = self::BASIS_OTHER;
        }
        if (($label = self::text($x['SavingBasisTypeLabel'] ?? null)) !== '')
            $s['price_basis_label'] = $label;

        if (($access = self::text($x['DealAccessType'] ?? null)) !== '')
            $s['access'] = strtolower($access);
        elseif (filter_var($x['IsPrimeExclusive'] ?? false, FILTER_VALIDATE_BOOLEAN))
            $s['access'] = 'prime_exclusive';

        if (isset($x['DealPercentClaimed']) && is_numeric($x['DealPercentClaimed']))
            $s['percent_claimed'] = (int) $x['DealPercentClaimed'];

        return $s;
    }

    private static function walmart(array $x): array
    {
        if (filter_var($x['clearance'] ?? false, FILTER_VALIDATE_BOOLEAN))
            return array('badge' => 'Clearance');
        if (filter_var($x['specialBuy'] ?? false, FILTER_VALIDATE_BOOLEAN))
            return array('badge' => 'Special Buy');

        return array();
    }

    private static function text($value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    /** Unix time of an ISO date, or 0 when there is none or it can't be read. */
    private static function time($value): int
    {
        $value = self::text($value);
        if ($value === '')
            return 0;

        $t = strtotime($value);

        return $t !== false && $t > 0 ? $t : 0;
    }
}
