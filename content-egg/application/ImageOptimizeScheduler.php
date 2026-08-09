<?php

namespace ContentEgg\application;

defined('\ABSPATH') || exit;

use ContentEgg\application\admin\GeneralConfig;
use ContentEgg\application\components\ImageOptimizeService;
use ContentEgg\application\components\ModuleManager;
use ContentEgg\application\components\ParserModule;

/**
 * ImageOptimizeScheduler class file
 *
 * Drives background resizing of locally saved product images:
 * - a WP-Cron heartbeat + self-chaining batches for the one-shot backfill
 *   (cursor over posts), self-unscheduling when done;
 * - a targeted single event per newly saved post (Action Scheduler when
 *   available, WP-Cron otherwise).
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class ImageOptimizeScheduler
{
    const CRON_TAG_HEARTBEAT = 'cegg_image_optimize_heartbeat';
    const CRON_TAG_BATCH = 'cegg_run_image_optimize_batch';
    const CRON_TAG_POST = 'cegg_optimize_post_images';
    const LOCK_TRANSIENT = 'cegg_image_optimize_lock';
    const AS_GROUP = 'content-egg';

    public static function initAction()
    {
        add_filter('cron_schedules', array(__CLASS__, 'addSchedule'));

        add_action(self::CRON_TAG_HEARTBEAT, array(__CLASS__, 'processBatch'));
        add_action(self::CRON_TAG_BATCH, array(__CLASS__, 'processBatch'));
        add_action(self::CRON_TAG_POST, array(__CLASS__, 'processPost'));

        add_action('content_egg_save_data', array(__CLASS__, 'onSaveData'), 15, 4);
        add_action('update_option_' . GeneralConfig::getInstance()->option_name(), array(__CLASS__, 'onSettingsChange'), 10, 2);
    }

    public static function addSchedule($schedules)
    {
        $schedules['ten_min'] = array(
            'interval' => 600,
            'display' => __('Every 10 minutes', 'content-egg'),
        );

        return $schedules;
    }

    public static function addScheduleEvent()
    {
        if (!wp_next_scheduled(self::CRON_TAG_HEARTBEAT))
            wp_schedule_event(time() + 10, 'ten_min', self::CRON_TAG_HEARTBEAT);
    }

    public static function maybeAddScheduleEvent()
    {
        if (ImageOptimizeService::isEnabled() && !ImageOptimizeService::isBackfillDone())
            self::addScheduleEvent();
    }

    public static function clearScheduleEvent()
    {
        wp_clear_scheduled_hook(self::CRON_TAG_HEARTBEAT);
        wp_clear_scheduled_hook(self::CRON_TAG_BATCH);

        // Per-post events carry a [$post_id] arg; wp_clear_scheduled_hook()
        // only clears events with MATCHING args, so it would miss them all.
        // wp_unschedule_hook() clears every event for the hook.
        wp_unschedule_hook(self::CRON_TAG_POST);

        delete_transient(self::LOCK_TRANSIENT);

        if (function_exists('as_unschedule_all_actions'))
            as_unschedule_all_actions(self::CRON_TAG_POST);
    }

    public static function processBatch()
    {
        if (!ImageOptimizeService::isEnabled() || ImageOptimizeService::isBackfillDone())
        {
            wp_clear_scheduled_hook(self::CRON_TAG_HEARTBEAT);
            wp_clear_scheduled_hook(self::CRON_TAG_BATCH);
            return;
        }

        if (!ImageOptimizeService::isEditorAvailable())
        {
            // No GD/Imagick on this server: mark the backfill done so we
            // stop rescheduling futilely. Per-post jobs stay cheap no-ops.
            ImageOptimizeService::markBackfillDone();
            wp_clear_scheduled_hook(self::CRON_TAG_HEARTBEAT);
            wp_clear_scheduled_hook(self::CRON_TAG_BATCH);
            return;
        }

        // Prevent multiple instances.
        if (get_transient(self::LOCK_TRANSIENT))
            return;

        set_transient(self::LOCK_TRANSIENT, 1, 15 * MINUTE_IN_SECONDS);

        $more = false;
        try
        {
            @set_time_limit(300);
            $more = ImageOptimizeService::processBatch();
        }
        finally
        {
            delete_transient(self::LOCK_TRANSIENT);
        }

        if ($more)
        {
            if (!wp_next_scheduled(self::CRON_TAG_BATCH))
                wp_schedule_single_event(time() + 10, self::CRON_TAG_BATCH);
        }
        else
        {
            wp_clear_scheduled_hook(self::CRON_TAG_HEARTBEAT);
            wp_clear_scheduled_hook(self::CRON_TAG_BATCH);
        }
    }

    public static function processPost($post_id)
    {
        if (!ImageOptimizeService::isEnabled() || !ImageOptimizeService::isEditorAvailable())
            return;

        @set_time_limit(300);

        ImageOptimizeService::processPost((int) $post_id);
    }

    public static function onSaveData($data, $module_id, $post_id, $is_last_iteration)
    {
        if (!is_array($data) || !$data)
            return;

        if (!ImageOptimizeService::isEnabled())
            return;

        try
        {
            $module = ModuleManager::factory($module_id);
        }
        catch (\Exception $e)
        {
            return;
        }

        if (!$module || !$module->isParser() || $module->getParserType() !== ParserModule::PARSER_TYPE_PRODUCT)
            return;

        foreach ($data as $item)
        {
            if (!empty($item['img_file']))
            {
                self::enqueuePost((int) $post_id);
                return;
            }
        }
    }

    public static function onSettingsChange($old_value, $value)
    {
        $new_enabled = is_array($value) && isset($value['image_optimization']) && $value['image_optimization'] === 'enabled';
        if (!$new_enabled)
            return;

        $old_enabled = is_array($old_value) && isset($old_value['image_optimization']) && $old_value['image_optimization'] === 'enabled';

        $old_max = (is_array($old_value) && !empty($old_value['image_optimization_max_size'])) ? (int) $old_value['image_optimization_max_size'] : ImageOptimizeService::DEFAULT_MAX_SIZE;
        $new_max = !empty($value['image_optimization_max_size']) ? (int) $value['image_optimization_max_size'] : ImageOptimizeService::DEFAULT_MAX_SIZE;

        // Newly switched on, or the cap was lowered: previously "processed"
        // files may now be oversized, so re-run the backfill.
        if (!$old_enabled || $new_max < $old_max)
        {
            ImageOptimizeService::resetBackfill();
            self::addScheduleEvent();
        }
    }

    private static function enqueuePost($post_id)
    {
        if (function_exists('as_schedule_single_action'))
        {
            if (function_exists('as_next_scheduled_action') && as_next_scheduled_action(self::CRON_TAG_POST, array($post_id), self::AS_GROUP))
                return;

            as_schedule_single_action(time() + 10, self::CRON_TAG_POST, array($post_id), self::AS_GROUP);
            return;
        }

        if (!wp_next_scheduled(self::CRON_TAG_POST, array($post_id)))
            wp_schedule_single_event(time() + 10, self::CRON_TAG_POST, array($post_id));
    }
}
