<?php

namespace ContentEgg\application\components;

defined('\ABSPATH') || exit;

/**
 * ShopScan class file
 *
 * Which shops actually appear in this site's product data.
 *
 * The Shops screen is named after something Content Egg discovers on its own,
 * so a screen that could only ever list hand-typed entries would be telling the
 * operator something false about their site: "no shops yet" beside a price
 * comparison with eleven of them.
 *
 * Deliberately a bounded, cached sample rather than an index: no schema, no
 * backfill, no write path to keep correct. The trade is that it sees recent
 * product rows rather than all of them, which is why every caller has to say so
 * on screen - a partial list presented as a census is worse than no list.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class ShopScan
{
    const TRANSIENT = 'cegg_shop_scan';
    const TTL = DAY_IN_SECONDS;

    // The cost is in the query, not the rows: reading 200 and reading every row
    // on a normal site both land around 15ms. A low default bought nothing and
    // hid shops - 200 rows showed 83 of 126 on a modest install. The cap exists
    // for the site with half a million product rows, not for everyone else.
    const DEFAULT_LIMIT = 2000;
    const MAX_LIMIT = 20000;

    /**
     * Serialized _cegg_data_* payloads => domain => how many items carried it.
     *
     * Sorted by count so the shops worth customizing come first. Pure.
     */
    public static function domainsFromRows(array $rows)
    {
        $counts = array();

        foreach ($rows as $row)
        {
            $items = \maybe_unserialize($row);

            if (!is_array($items))
                continue;

            foreach ($items as $item)
            {
                if (!is_array($item) || empty($item['domain']))
                    continue;

                $domain = ShopStore::normalizeDomain($item['domain']);

                if ($domain === '')
                    continue;

                if (!isset($counts[$domain]))
                    $counts[$domain] = 0;

                $counts[$domain]++;
            }
        }

        arsort($counts);

        return $counts;
    }

    public static function normalizeLimit($limit)
    {
        $limit = (int) $limit;

        if ($limit < 1)
            $limit = self::DEFAULT_LIMIT;

        return min($limit, self::MAX_LIMIT);
    }

    /**
     * Whether the scan stopped at its limit rather than running out of rows.
     *
     * This is what decides whether the page mentions a bound at all: quoting
     * "your 2,000 most recent products" when the scan read every row there is
     * would be noise, and hiding it when rows were left behind would present a
     * sample as a census. Pure.
     */
    public static function isTruncated(array $result)
    {
        if (empty($result['limit']))
            return false;

        return (int) $result['scanned'] >= (int) $result['limit'];
    }

    /**
     * The post-meta keys worth reading: every PRODUCT-type parser module,
     * active or not - data written by a module the operator has since switched
     * off still describes a shop that is still on their pages.
     */
    public static function metaKeys()
    {
        $ids = ModuleManager::getInstance()->getParserModuleIdsByTypes('PRODUCT', false);

        $keys = array();
        foreach ($ids as $id)
            $keys[] = ContentManager::META_PREFIX_DATA . $id;

        return $keys;
    }

    public static function run($limit = self::DEFAULT_LIMIT)
    {
        global $wpdb;

        $limit = self::normalizeLimit($limit);

        if (!$keys = self::metaKeys())
        {
            $result = array('domains' => array(), 'scanned' => 0, 'limit' => $limit, 'time' => time());
            \set_transient(self::TRANSIENT, $result, self::TTL);

            return $result;
        }

        $placeholders = implode(',', array_fill(0, count($keys), '%s'));

        // PRODUCT modules only, and ordered by meta_id so a scan of N reads the
        // N most recently written rows - the shops the operator works with now.
        //
        // A LIKE '_cegg_data_%' prefix would sweep in the media modules too, and
        // pexels.com and youtube.com are not shops. Exact keys also let the
        // meta_key index do the work instead of a prefix range.
        $rows = $wpdb->get_col($wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->postmeta}
             WHERE meta_key IN ($placeholders) AND meta_value != ''
             ORDER BY meta_id DESC
             LIMIT %d",
            array_merge($keys, array($limit))
        ));

        $result = array(
            'domains' => self::domainsFromRows((array) $rows),
            'scanned' => count((array) $rows),
            'limit' => $limit,
            'time' => time(),
        );

        \set_transient(self::TRANSIENT, $result, self::TTL);

        return $result;
    }

    /**
     * The cached scan, running one on first use. Never runs on a front-end
     * request - only the Shops screen asks for it.
     */
    public static function results()
    {
        $cached = \get_transient(self::TRANSIENT);

        if (is_array($cached) && isset($cached['domains']))
            return $cached;

        return self::run();
    }

    public static function flush()
    {
        \delete_transient(self::TRANSIENT);
    }
}
