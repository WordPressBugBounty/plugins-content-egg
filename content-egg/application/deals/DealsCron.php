<?php

namespace ContentEgg\application\deals;

defined('\ABSPATH') || exit;

/**
 * DealsCron class file
 *
 * Nothing fires when a date passes, so ended deals are swept hourly. Price and
 * stock changes need no sweep - they arrive through content_egg_save_data.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class DealsCron
{
    const HOOK = 'cegg_deals_cron';
    const BATCH = 200;

    public static function initAction(): void
    {
        \add_action(self::HOOK, array(__CLASS__, 'run'));

        if (!\wp_next_scheduled(self::HOOK))
            \wp_schedule_event(time() + 300, 'hourly', self::HOOK);
    }

    public static function unschedule(): void
    {
        if (\wp_next_scheduled(self::HOOK))
            \wp_clear_scheduled_hook(self::HOOK);
    }

    public static function run(): void
    {
        $ids = \get_posts(array(
            'post_type' => DealPostType::POST_TYPE,
            'post_status' => 'any',
            'fields' => 'ids',
            'posts_per_page' => self::BATCH,
            'no_found_rows' => true,
            'meta_query' => array(
                'relation' => 'AND',
                array('key' => DealPostType::META_STATUS, 'value' => DealStatus::ACTIVE),
                array('key' => DealPostType::META_ENDS, 'value' => array(1, time()), 'compare' => 'BETWEEN', 'type' => 'NUMERIC'),
            ),
        ));

        foreach ($ids as $id)
            DealService::refresh((int) $id);
    }
}
