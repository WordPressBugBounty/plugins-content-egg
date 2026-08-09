<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

/**
 * LeanCoupon class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */

/**
 * Compact envelope for coupon-module results (Admitad, CJ Links, Skimlinks,
 * Tradedoubler/Tradetracker coupons, ...). Dates are ISO (UTC) or null.
 */
final class LeanCoupon
{
    /**
     * @param object|array $item
     */
    public static function map($item): array
    {
        $a = is_object($item) ? get_object_vars($item) : (array) $item;
        list($excerpt, $truncated) = LeanText::excerpt((string) ($a['description'] ?? ''));

        $start = (int) ($a['startDate'] ?? 0);
        $end = (int) ($a['endDate'] ?? 0);

        return array(
            'unique_id' => (string) ($a['unique_id'] ?? ''),
            'title' => (string) ($a['title'] ?? ''),
            'code' => (string) ($a['code'] ?? ''),
            'url' => (string) ($a['url'] ?? ''),
            'domain' => (string) ($a['domain'] ?? ''),
            'start_date' => $start > 0 ? gmdate('Y-m-d', $start) : null,
            'end_date' => $end > 0 ? gmdate('Y-m-d', $end) : null,
            'description_excerpt' => $excerpt,
            'description_truncated' => $truncated,
        );
    }

    public static function mapList(array $items): array
    {
        return array_values(array_map(array(self::class, 'map'), $items));
    }
}
