<?php

namespace ContentEgg\application\components;

defined('\ABSPATH') || exit;

use ContentEgg\application\helpers\LegacyTransientHelper;

/**
 * ProductScanState class file
 *
 * Timestamp of the last product-index rebuild, stored as a non-autoloaded option.
 *
 * This used to be a transient whose expiry doubled as the schedule. On sites with
 * a persistent object cache the key is evicted early, and because ReviewNotice
 * checks it on every plugin admin screen, each page load truncated and rebuilt the
 * whole product index. The option never expires, so the TTL comparison is explicit
 * here instead.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class ProductScanState
{
    const OPTION = 'cegg_products_last_scan';

    /** Legacy transient key, read once during migration. */
    const LEGACY_TRANSIENT = 'cegg_products_last_sync';

    /** Unix timestamp of the last scan, or 0 when never scanned. */
    public static function get(): int
    {
        $value = \get_option(self::OPTION, null);

        if ($value === null)
        {
            $legacy = LegacyTransientHelper::read(self::LEGACY_TRANSIENT);
            if ($legacy === false)
            {
                return 0;
            }

            $value = (int) $legacy;
            self::save($value);
            LegacyTransientHelper::purge(self::LEGACY_TRANSIENT);
        }

        return (int) $value;
    }

    public static function touch($time = null): void
    {
        if ($time === null)
        {
            $time = time();
        }

        self::save((int) $time);
    }

    public static function isDue(int $ttl): bool
    {
        $last = self::get();

        return !$last || (time() - $last) > $ttl;
    }

    private static function save(int $time): void
    {
        // autoload=false: only read on Content Egg admin screens.
        if (false === \add_option(self::OPTION, $time, '', false))
        {
            \update_option(self::OPTION, $time, false);
        }
    }
}
