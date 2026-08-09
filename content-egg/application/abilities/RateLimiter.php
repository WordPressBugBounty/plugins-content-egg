<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

/**
 * RateLimiter class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */

/**
 * Soft per-bucket rate limiting via transients. Applied only where an agent
 * loop can burn external API quota (design spec §7).
 */
final class RateLimiter
{
    /**
     * Counts a hit against the bucket. Returns 0 when allowed, otherwise the
     * number of seconds until the current window resets.
     */
    public static function hit(string $bucket, int $limit, int $window = 60): int
    {
        $key = 'cegg_rl_' . md5($bucket);
        $now = time();

        $state = \get_transient($key);
        if (!is_array($state) || !isset($state['count'], $state['reset']) || $now >= $state['reset'])
        {
            $state = array('count' => 0, 'reset' => $now + $window);
        }

        if ($state['count'] >= $limit)
        {
            return max(1, (int) $state['reset'] - $now);
        }

        $state['count']++;
        \set_transient($key, $state, $window);

        return 0;
    }
}
