<?php

namespace ContentEgg\application\admin\import;

use ContentEgg\application\components\ModuleManager;
use ContentEgg\application\helpers\TextHelper;

defined('ABSPATH') || exit;

/**
 * FeedCategoryApi - lists the distinct categories present in a feed module's
 * product table, to suggest values for the preset category mapping field.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class FeedCategoryApi
{
    /** Enough for a large merchant tree without turning the datalist into a scroll. */
    const MAX_CATEGORIES = 500;

    public static function init(): void
    {
        add_action('wp_ajax_cegg_feed_categories', [__CLASS__, 'handle_categories']);
    }

    public static function handle_categories(): void
    {
        if (!current_user_can('manage_options'))
        {
            wp_send_json_error(['message' => __('You are not allowed to do this.', 'content-egg')], 403);
        }

        if (empty($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'cegg_feed_categories'))
        {
            wp_send_json_error(['message' => __('Invalid nonce.', 'content-egg')], 400);
        }

        $module_id = TextHelper::clearId($_POST['module_id'] ?? '');

        if (!$module_id || !ModuleManager::getInstance()->moduleExists($module_id))
        {
            wp_send_json_error(['message' => __('The module does not exist.', 'content-egg')], 400);
        }

        $module = ModuleManager::getInstance()->factory($module_id);

        if (!$module->isFeedModule())
        {
            wp_send_json_error(['message' => __('This module does not use a product feed.', 'content-egg')], 400);
        }

        global $wpdb;

        // Not user input: the name comes from the module's own model class.
        $table = $module->getProductModel()->tableName();

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT category, COUNT(*) AS cnt FROM `{$table}` WHERE category <> '' GROUP BY category ORDER BY cnt DESC LIMIT %d",
                self::MAX_CATEGORIES
            ),
            ARRAY_A
        );

        $categories = [];

        foreach ((array) $rows as $row)
        {
            $categories[] = [
                'name'  => (string) $row['category'],
                'count' => (int) $row['cnt'],
            ];
        }

        wp_send_json_success(['categories' => $categories]);
    }
}
