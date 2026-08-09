<?php

namespace ContentEgg\application\helpers;

defined('\ABSPATH') || exit;

/**
 * LegacyTransientHelper class file
 *
 * Reads state that used to live in transients, for a one-time migration into
 * options.
 *
 * With a persistent object cache active, core's set_transient() bypasses the
 * options table entirely, so get_transient() cannot see rows written before the
 * cache was enabled -- and the object cache itself is free to evict the key long
 * before the requested lifetime. This helper therefore falls back to the raw
 * `_transient_*` option rows, honouring `_transient_timeout_*`.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class LegacyTransientHelper
{
    /**
     * @return mixed Stored value, or false when absent or expired.
     */
    public static function read(string $key)
    {
        $found = self::readWithExpiry($key);

        return $found === null ? false : $found['value'];
    }

    /**
     * Same as read(), but also reports when the value was due to expire.
     *
     * 'expires' is a unix timestamp, or 0 when the transient had no expiry -- or
     * when the remaining lifetime is not knowable, which is the case for a value
     * served by the object cache (core does not expose the TTL). Callers that
     * carry the expiry over should treat 0 as "keep it", which is the safe
     * direction for a dismissal.
     *
     * @return array|null array('value' => mixed, 'expires' => int), or null when absent/expired.
     */
    public static function readWithExpiry(string $key): ?array
    {
        $value = \get_transient($key);
        if ($value !== false)
        {
            // A `_transient_timeout_*` row in the past cannot belong to a value
            // core just reported as live: it is a leftover from before the object
            // cache was enabled, and core stops cleaning those up at that point.
            // Treat it as unknown rather than inheriting an expiry already spent.
            $expires = self::dbTimeout($key);

            return array('value' => $value, 'expires' => $expires > time() ? $expires : 0);
        }

        $sentinel = new \stdClass();
        $value = \get_option('_transient_' . $key, $sentinel);
        if ($value === $sentinel)
        {
            return null;
        }

        $expires = self::dbTimeout($key);
        if ($expires && $expires < time())
        {
            return null;
        }

        return array('value' => $value, 'expires' => $expires);
    }

    /** @return int Unix timestamp from the `_transient_timeout_*` row, or 0 when there is none. */
    private static function dbTimeout(string $key): int
    {
        $timeout = \get_option('_transient_timeout_' . $key);

        return $timeout === false ? 0 : (int) $timeout;
    }

    /** Drop the cached copy and both DB rows. */
    public static function purge(string $key): void
    {
        \delete_transient($key);
        \delete_option('_transient_' . $key);
        \delete_option('_transient_timeout_' . $key);
    }
}
