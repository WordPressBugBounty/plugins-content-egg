<?php

namespace ContentEgg\application\components;

defined('\ABSPATH') || exit;

use ContentEgg\application\helpers\LegacyTransientHelper;

/**
 * DurableTransient class file
 *
 * Transient semantics -- a value with an optional lifetime, 0 meaning "forever"
 * -- on storage that actually keeps it.
 *
 * Core stores transients in the object cache alone once one is active, so on a
 * site running Redis/Memcached (or LiteSpeed Cache 7.8+, which routes transients
 * through the object cache) a value is dropped whenever the cache decides to drop
 * it. That is fine for a cache, and wrong for anything the plugin must remember:
 * a dismissed notice, or a queued job's configuration.
 *
 * Use this for state. Keep using set_transient() for values that are merely
 * expensive to recompute.
 *
 * Layout follows core's: the value lives in one option and its expiry in a
 * companion row, so gc() can sweep expired entries without loading payloads.
 * Core's own delete_expired_transients() gives up when an object cache is
 * active, which is exactly when this class is needed, so the sweep is ours to
 * run -- see MaintenanceScheduler::runDaily().
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class DurableTransient
{
    const OPTION_PREFIX = 'cegg_dt_';
    const TIMEOUT_PREFIX = 'cegg_dt_timeout_';

    public static function optionName(string $key): string
    {
        return self::OPTION_PREFIX . $key;
    }

    public static function timeoutName(string $key): string
    {
        return self::TIMEOUT_PREFIX . $key;
    }

    /**
     * @return mixed Stored value, or false when absent or expired.
     */
    public static function get(string $key)
    {
        $sentinel = new \stdClass();
        $value = \get_option(self::optionName($key), $sentinel);

        if ($value === $sentinel)
        {
            return self::migrate($key);
        }

        $expires = (int) \get_option(self::timeoutName($key), 0);
        if ($expires && $expires < time())
        {
            self::delete($key);

            return false;
        }

        return $value;
    }

    /** @param int $ttl Lifetime in seconds; 0 never expires. */
    public static function set(string $key, $value, int $ttl = 0): void
    {
        self::save(self::optionName($key), $value);

        if ($ttl > 0)
        {
            self::save(self::timeoutName($key), time() + $ttl);
        }
        else
        {
            \delete_option(self::timeoutName($key));
        }

        // Once a key is written here the transient it used to be must never be
        // consulted again, or a later delete() would let migrate() resurrect it.
        LegacyTransientHelper::purge($key);
    }

    public static function delete(string $key): void
    {
        \delete_option(self::optionName($key));
        \delete_option(self::timeoutName($key));
        LegacyTransientHelper::purge($key);
    }

    /**
     * Remove entries whose lifetime has passed. Entries are otherwise only
     * dropped lazily, on a read of that exact key -- which never comes for the
     * per-run keys the prefill queue generates.
     *
     * @return int Number of entries removed.
     */
    public static function gc(): int
    {
        global $wpdb;

        $names = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like(self::TIMEOUT_PREFIX) . '%'
            )
        );

        $now = time();
        $removed = 0;

        foreach ((array) $names as $name)
        {
            $expires = (int) \get_option($name, 0);
            if (!$expires || $expires >= $now)
            {
                continue;
            }

            $key = substr($name, strlen(self::TIMEOUT_PREFIX));
            \delete_option(self::optionName($key));
            \delete_option($name);
            $removed++;
        }

        return $removed;
    }

    private static function save(string $option, $value): void
    {
        // autoload=false: read on demand, never needed on every page load.
        if (false === \add_option($option, $value, '', false))
        {
            \update_option($option, $value, false);
        }
    }

    /**
     * One-time read-through migration from the transient this key used to be.
     * Returns the migrated value, or false when there was nothing to migrate
     * (in which case nothing is written).
     *
     * @return mixed
     */
    private static function migrate(string $key)
    {
        $legacy = LegacyTransientHelper::readWithExpiry($key);
        if ($legacy === null)
        {
            return false;
        }

        $ttl = $legacy['expires'] ? max(1, $legacy['expires'] - time()) : 0;
        self::set($key, $legacy['value'], $ttl);

        return $legacy['value'];
    }
}
