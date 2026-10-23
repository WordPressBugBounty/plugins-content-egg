<?php

namespace ContentEgg\application;

use ContentEgg\application\admin\GeneralConfig;
use ContentEgg\application\components\ContentManager;
use ContentEgg\application\components\ShopStore;
use ContentEgg\application\components\VatNormalizer;
use ContentEgg\application\models\PriceAlertModel;
use ContentEgg\application\models\PriceHistoryModel;

defined('\ABSPATH') || exit;

/**
 * Brings one shop's stored data onto its current VAT settings, in batches:
 * price history and alert targets first, then a re-save of each post (which
 * normalizes the stored prices and syncs WooCommerce/deals; the history
 * guard sees no price change, so no new point and no alert).
 *
 * The job converges on the settings in effect at each batch rather than
 * replaying a change, so editing settings mid-run needs no lock: a changed
 * signature restarts the walk from the first post.
 */
class VatRecalcScheduler
{
    const CRON_TAG = 'cegg_vat_recalc_batch';
    const OPTION = 'cegg_vat_recalc';
    const AS_GROUP = 'content-egg';
    const BATCH_POSTS = 20;

    public static function initAction()
    {
        add_action(self::CRON_TAG, array(__CLASS__, 'processBatch'));
        add_action('update_option_' . GeneralConfig::getInstance()->option_name(), array(__CLASS__, 'onSettingsChange'), 10, 2);
    }

    /** Pure. */
    public static function nextBatchState($state, $signature, $shop_exists)
    {
        if (!$shop_exists)
            return null;

        if (!is_array($state) || !isset($state['signature']) || $state['signature'] !== $signature)
            return array('signature' => $signature, 'cursor' => 0, 'done' => 0);

        return $state;
    }

    public static function schedule($domain)
    {
        $domain = ShopStore::normalizeDomain($domain);
        if ($domain === '')
            return;

        $all = self::allStates();
        if (!isset($all[$domain]))
        {
            $all[$domain] = array('signature' => '', 'cursor' => 0, 'done' => 0, 'total' => self::countPosts());
            update_option(self::OPTION, $all, false);
        }

        self::enqueue($domain, 5);
    }

    public static function status($domain)
    {
        $all = self::allStates();
        $domain = ShopStore::normalizeDomain($domain);

        return isset($all[$domain]) ? array('done' => (int) $all[$domain]['done'], 'total' => (int) $all[$domain]['total']) : null;
    }

    /**
     * Restarts a walk whose chain died (timeout, fatal, deactivation): its
     * state stays behind with nothing queued to continue it, and the shop
     * screen would show it as running for good.
     */
    public static function ensureRunning($domain)
    {
        $domain = ShopStore::normalizeDomain($domain);
        $all = self::allStates();

        if ($domain === '' || !isset($all[$domain]) || self::isQueued($domain))
            return;

        self::enqueue($domain, 1);
    }

    /** Plugin deactivation: no batch outlives the plugin, no stale progress. */
    public static function clear()
    {
        if (function_exists('as_unschedule_all_actions'))
            as_unschedule_all_actions(self::CRON_TAG, null, self::AS_GROUP);

        // Events carry a [$domain] arg, and wp_clear_scheduled_hook() only
        // clears events with matching args; this clears every one.
        wp_unschedule_hook(self::CRON_TAG);

        delete_option(self::OPTION);
    }

    public static function onSettingsChange($old_value, $value)
    {
        $old = is_array($old_value) && isset($old_value['vat_normalize']) ? $old_value['vat_normalize'] : '';
        $new = is_array($value) && isset($value['vat_normalize']) ? $value['vat_normalize'] : '';

        if ($old === $new)
            return;

        foreach (ShopStore::all() as $domain => $shop)
        {
            if (!empty($shop['vat_included']))
                self::schedule($domain);
        }
    }

    public static function processBatch($domain)
    {
        // Action Scheduler can run several actions in one request: a shop cache
        // memoized by an earlier one would give the pre-change signature.
        ShopStore::resetCache();

        $all = self::allStates();
        $shop = ShopStore::get($domain);
        $signature = VatNormalizer::signature(VatNormalizer::siteTarget(), $shop);
        $prev = isset($all[$domain]) ? $all[$domain] : null;
        $state = self::nextBatchState($prev, $signature, (bool) $shop);

        if ($state === null)
        {
            self::removeState($all, $domain);
            return;
        }

        // Keep the count while a walk is in progress; recount on a (re)start.
        $state['total'] = ($state['cursor'] > 0 && $prev && isset($prev['total'])) ? (int) $prev['total'] : self::countPosts();

        $post_ids = self::nextPostIds((int) $state['cursor'], self::BATCH_POSTS);
        $factor = VatNormalizer::factorFor(VatNormalizer::siteTarget(), $shop);
        $history = (bool) GeneralConfig::getInstance()->option('price_history_days');
        $alerts = $history && GeneralConfig::getInstance()->option('price_alert_enabled');

        foreach ($post_ids as $post_id)
        {
            // One bad post (e.g. a removed module whose factory throws) must not
            // kill the action: the walk would stall with no retry.
            try
            {
                self::recalcPost((int) $post_id, $domain, $factor, $history, $alerts);
            }
            catch (\Throwable $e)
            {
                error_log('Content Egg VAT recalculation, post ' . $post_id . ': ' . $e->getMessage());
            }

            $state['cursor'] = (int) $post_id;
            $state['done']++;
        }

        if (count($post_ids) < self::BATCH_POSTS)
        {
            self::removeState($all, $domain);
            return;
        }

        $all[$domain] = $state;
        update_option(self::OPTION, $all, false);
        self::enqueue($domain, 1);
    }

    private static function recalcPost($post_id, $domain, $factor, $history, $alerts)
    {
        foreach (self::postModules($post_id) as $module_id)
        {
            $data = ContentManager::getData($post_id, $module_id);
            if (!is_array($data) || !$data)
                continue;

            $keys = VatNormalizer::itemsForDomain($data, $domain);
            if (!$keys)
                continue;

            $current = true;
            foreach ($keys as $key)
            {
                $item = $data[$key];
                if (empty($item['unique_id']))
                    continue;

                if ($history)
                    PriceHistoryModel::model()->convertForItem($item['unique_id'], $module_id, $factor);
                if ($alerts)
                    PriceAlertModel::model()->convertForItem($item['unique_id'], $module_id, $factor);

                $stored = isset($item['vat_factor']) && $item['vat_factor'] !== '' ? (float) $item['vat_factor'] : 1.0;
                if (abs($stored - $factor) > 0.000001)
                    $current = false;
            }

            // Already on the current factor: re-saving would only trigger needless
            // WooCommerce/deal syncs on a large site.
            if ($current)
                continue;

            // Every save is its own last iteration, as in ProductDataService:
            // the WooCommerce handler returns unless it is, and some modules
            // here are skipped above, so "last of the loop" is unknowable.
            ContentManager::saveData(array_values($data), $module_id, $post_id, true);
        }
    }

    private static function postModules($post_id)
    {
        global $wpdb;
        $like = $wpdb->esc_like(ContentManager::META_PREFIX_DATA) . '%';
        $keys = $wpdb->get_col($wpdb->prepare("SELECT meta_key FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE %s", $post_id, $like));

        return array_values(array_map(function ($k)
        {
            return substr($k, strlen(ContentManager::META_PREFIX_DATA));
        }, $keys));
    }

    private static function nextPostIds($after, $limit)
    {
        global $wpdb;
        $like = $wpdb->esc_like(ContentManager::META_PREFIX_DATA) . '%';

        return $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key LIKE %s AND post_id > %d ORDER BY post_id ASC LIMIT %d",
            $like, $after, $limit
        ));
    }

    private static function countPosts()
    {
        global $wpdb;
        $like = $wpdb->esc_like(ContentManager::META_PREFIX_DATA) . '%';

        return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $like));
    }

    private static function allStates()
    {
        $all = get_option(self::OPTION, array());

        return is_array($all) ? $all : array();
    }

    private static function removeState(array $all, $domain)
    {
        unset($all[$domain]);

        if ($all)
            update_option(self::OPTION, $all, false);
        else
            delete_option(self::OPTION);
    }

    /** A batch for the domain is waiting or running. */
    private static function isQueued($domain)
    {
        $args = array($domain);

        if (function_exists('as_schedule_single_action'))
        {
            // Without the query API there is no telling; do not pile up chains.
            if (!function_exists('as_get_scheduled_actions'))
                return true;

            foreach (array(\ActionScheduler_Store::STATUS_PENDING, \ActionScheduler_Store::STATUS_RUNNING) as $status)
            {
                if (as_get_scheduled_actions(array(
                    'hook'     => self::CRON_TAG,
                    'args'     => $args,
                    'group'    => self::AS_GROUP,
                    'status'   => $status,
                    'per_page' => 1,
                ), 'ids'))
                    return true;
            }

            return false;
        }

        return (bool) wp_next_scheduled(self::CRON_TAG, $args);
    }

    private static function enqueue($domain, $delay)
    {
        $args = array($domain);

        if (function_exists('as_schedule_single_action'))
        {
            // Pending only: Action Scheduler marks the running action as in-progress
            // before executing it, and as_next_scheduled_action() counts that one
            // too - so a batch asking for its successor would see itself and the
            // chain would stop after the first batch.
            $pending = function_exists('as_get_scheduled_actions') ? as_get_scheduled_actions(array(
                'hook'     => self::CRON_TAG,
                'args'     => $args,
                'group'    => self::AS_GROUP,
                'status'   => \ActionScheduler_Store::STATUS_PENDING,
                'per_page' => 1,
            ), 'ids') : array();

            if (!$pending)
                as_schedule_single_action(time() + $delay, self::CRON_TAG, $args, self::AS_GROUP);

            return;
        }

        // wp-cron removes an event before running it, so the plain check is safe.
        if (!wp_next_scheduled(self::CRON_TAG, $args))
            wp_schedule_single_event(time() + $delay, self::CRON_TAG, $args);
    }
}
