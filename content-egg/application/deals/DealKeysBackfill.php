<?php

namespace ContentEgg\application\deals;

defined('\ABSPATH') || exit;

/**
 * DealKeysBackfill class file
 *
 * Gives deals made before deal keys existed their keys (DealKey) and an
 * end-date source, in batches on WP-cron so a site with thousands of deals
 * never does it inside one admin request. Runs whether or not Deals are on.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class DealKeysBackfill
{
    const HOOK = 'cegg_deals_backfill';
    const CURSOR_OPTION = 'cegg_deals_backfill_after';
    const BATCH = 200;

    public static function initAction(): void
    {
        \add_action(self::HOOK, array(__CLASS__, 'run'));
    }

    /** Starts from the oldest deal and runs the first batch now. */
    public static function start(): void
    {
        \update_option(self::CURSOR_OPTION, 0, false);
        self::run();
    }

    public static function run(): void
    {
        $after = \get_option(self::CURSOR_OPTION, false);
        if ($after === false)
            return;

        global $wpdb;
        $batch = max(1, (int) \apply_filters('cegg_deals_backfill_batch', self::BATCH));
        // Every status, trash included: a restored deal keeps its end date too.
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND ID > %d ORDER BY ID ASC LIMIT %d",
            DealPostType::POST_TYPE,
            (int) $after,
            $batch
        ));

        foreach ($ids as $id)
            self::backfill((int) $id);

        if (count($ids) < $batch)
        {
            \delete_option(self::CURSOR_OPTION);
            \wp_clear_scheduled_hook(self::HOOK);
            return;
        }

        \update_option(self::CURSOR_OPTION, (int) end($ids), false);
        if (!\wp_next_scheduled(self::HOOK))
            \wp_schedule_single_event(time() + 60, self::HOOK);
    }

    private static function backfill(int $post_id): void
    {
        $pin = DealService::getPin($post_id);
        if ($pin)
            DealService::writeKeys($post_id, $pin['module_id'], DealService::pinnedItem($post_id));

        // An end date set before deals followed the store was set by a person.
        if (!\metadata_exists('post', $post_id, DealPostType::META_ENDS_SOURCE))
            \update_post_meta($post_id, DealPostType::META_ENDS_SOURCE, DealEnds::sourceFor('', (int) \get_post_meta($post_id, DealPostType::META_ENDS, true)));
    }
}
