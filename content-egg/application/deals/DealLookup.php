<?php

namespace ContentEgg\application\deals;

defined('\ABSPATH') || exit;

/**
 * DealLookup class file
 *
 * Finds deals by the keys DealService writes on pin (DealKey): the exact
 * offer, the normalized product URL, or a GTIN. Queries post meta directly,
 * so a deal created a moment ago is found at once.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class DealLookup
{
    /** Every post status but trash. */
    const POST_STATUSES = array('publish', 'pending', 'draft', 'future', 'private');

    /**
     * @param string $deal_status 'any', 'active' or 'expired'
     * @return int[] newest first
     */
    public static function byKey(string $key, string $deal_status = 'any'): array
    {
        return self::find(DealPostType::META_KEY, $key, $deal_status);
    }

    /** @return int[] */
    public static function byUrlKey(string $url_key, string $deal_status = 'any'): array
    {
        return self::find(DealPostType::META_URL_KEY, $url_key, $deal_status);
    }

    /** @return int[] */
    public static function byGtin(string $gtin, string $deal_status = 'any'): array
    {
        return self::find(DealPostType::META_GTIN, $gtin, $deal_status);
    }

    /**
     * The active deal for the same offer as $item: the same module item, or for
     * a manual Offer item (whose unique_id is generated) the same product URL.
     */
    public static function activeDuplicate(string $module_id, array $item): ?int
    {
        $keys = DealKey::fromItem($module_id, $item);

        $ids = self::byKey($keys['key'], DealStatus::ACTIVE);
        if (!$ids && explode('__', $module_id)[0] === 'Offer')
            $ids = self::byUrlKey($keys['url_key'], DealStatus::ACTIVE);

        return $ids ? $ids[0] : null;
    }

    /**
     * The deal an import must not duplicate: the same module item in any
     * status. An expired deal revives on its own when the offer is good again.
     */
    public static function importDuplicate(string $module_id, string $unique_id): int
    {
        $ids = self::byKey(DealKey::key($module_id, $unique_id));

        return $ids ? $ids[0] : 0;
    }

    private static function find(string $meta_key, string $value, string $deal_status): array
    {
        if ($value === '')
            return array();

        $meta_query = array(array('key' => $meta_key, 'value' => $value));
        if ($deal_status === DealStatus::ACTIVE || $deal_status === DealStatus::EXPIRED)
            $meta_query[] = array('key' => DealPostType::META_STATUS, 'value' => $deal_status);

        return array_map('intval', \get_posts(array(
            'post_type' => DealPostType::POST_TYPE,
            'post_status' => self::POST_STATUSES,
            'fields' => 'ids',
            'posts_per_page' => 50,
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => true,
            'suppress_filters' => true,
            'meta_query' => $meta_query,
        )));
    }
}
