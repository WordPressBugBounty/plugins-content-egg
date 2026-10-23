<?php

namespace ContentEgg\application\deals;

defined('\ABSPATH') || exit;

/**
 * DealBadges class file
 *
 * "Lowest price in 30 days", held to what the price history can actually
 * prove. Pure.
 *
 * History rows are written only when a price changes, so the price in force
 * when the window opened is the last row before it - it has to count, or a
 * long-held lower price would be invisible.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class DealBadges
{
    public static function isThirtyDayLow(array $rows, float $current, int $now, int $days = 30): bool
    {
        if ($current <= 0 || !$rows)
            return false;

        $start = $now - $days * DAY_IN_SECONDS;

        // Not enough history to say anything about 30 days.
        if (strtotime((string) $rows[0]['create_date']) > $start)
            return false;

        $window = array();
        $carried = null;
        foreach ($rows as $row)
        {
            $price = (float) $row['price'];
            if (strtotime((string) $row['create_date']) < $start)
                $carried = $price;
            else
                $window[] = $price;
        }

        if ($carried !== null)
            $window[] = $carried;

        if (!$window)
            return false;

        // A price that never moved is not a deal, however low.
        return $current <= min($window) + 0.001 && max($window) > $current + 0.001;
    }

    /**
     * Hot = the community score reached the site's threshold. 0 turns the
     * badge off; an expired deal is never hot.
     */
    public static function isHot(int $score, int $threshold, string $status): bool
    {
        return $threshold > 0 && $status !== DealStatus::EXPIRED && $score >= $threshold;
    }
}
