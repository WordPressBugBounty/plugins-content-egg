<?php

namespace ContentEgg\application\EggBlocks\shared;

use ContentEgg\application\helpers\CurrencyHelper;

defined('ABSPATH') || exit;

/**
 * Bridges the EggBlocks `eggb/format_price` filter to Content Egg's CurrencyHelper.
 *
 * This filter is consumed by external resolvers (e.g. TMN's
 * ProfileCollectionResolver) that need to format raw numeric prices from
 * profile data into display strings with the correct currency symbol and
 * placement, without depending directly on Content Egg internals.
 *
 * Filter contract:
 *   apply_filters('eggb/format_price', '', $amount, $currency) : string
 *
 * The filter short-circuits if a prior callback already produced a non-empty
 * value, allowing other plugins to override the formatting if needed.
 */
class PriceFormatBridge
{
    public static function register(): void
    {
        add_filter('eggb/format_price', [self::class, 'format'], 10, 3);
    }

    /**
     * @param string $value    Existing formatted value from earlier filters.
     * @param mixed  $amount   Numeric price (int|float|numeric-string).
     * @param string $currency ISO currency code (e.g. "USD"). May be empty.
     */
    public static function format(string $value, $amount, $currency): string
    {
        if ($value !== '')
        {
            return $value;
        }
        if (!is_numeric($amount))
        {
            return '';
        }

        return (string) CurrencyHelper::getInstance()->currencyFormat((float) $amount, (string) $currency);
    }
}
