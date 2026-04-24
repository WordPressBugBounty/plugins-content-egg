<?php

namespace ContentEgg\application\components;

use ContentEgg\application\admin\GeneralConfig;
use ContentEgg\application\components\ContentManager;
use ContentEgg\application\components\ModuleManager;

use function ContentEgg\prnx;

defined('\ABSPATH') || exit;

/**
 * OfferCountService class file
 *
 * Stores a cached offers count per post in post meta.
 * Meta key (default): _cegg_offers_count
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class OfferCountService
{
    const DEFAULT_META_KEY = '_cegg_offers_count';

    public static function initAction()
    {
        \add_action('content_egg_save_data', [__CLASS__, 'onSaveData'], 20, 4);

        // Shortcodes for manual placement in themes/builders
        \add_shortcode('content-egg-offers-count', [__CLASS__, 'shortcodeCount']);
        \add_shortcode('content-egg-offers-badge', [__CLASS__, 'shortcodeBadge']);

        // WooCommerce badge (shop/archive loops)
        self::maybeInitWooCommerceBadge();
    }

    public static function getMetaKey(): string
    {
        return (string) \apply_filters('cegg_offers_count_meta_key', self::DEFAULT_META_KEY);
    }

    /**
     * Update count when Content Egg saves data (called per module; update only on last iteration).
     */
    public static function onSaveData($data, $module_id, $post_id, $is_last_iteration)
    {
        if (!$is_last_iteration)
        {
            return;
        }

        $post_id = (int) $post_id;
        if (!$post_id)
        {
            return;
        }

        $count = self::calculateCount($post_id);

        \update_post_meta($post_id, self::getMetaKey(), (int) $count);
        \do_action('cegg_offers_count_updated', $post_id, (int) $count);
    }

    /**
     * Calculates offers count for a post using canonical aggregated view data.
     * Default: excludes offers with stock_status == -1.
     */
    public static function calculateCount(int $post_id): int
    {
        $items = ContentManager::getViewProductData($post_id);

        if (!\is_array($items) || !$items)
        {
            return 0;
        }

        $include_oos = (bool) \apply_filters('cegg_offers_count_include_oos', false, $post_id);

        $filtered = \array_filter($items, function ($item) use ($include_oos)
        {
            if (!\is_array($item))
            {
                return false;
            }

            // If stock_status not present, keep item (backwards compatibility)
            if (!isset($item['stock_status']))
            {
                return true;
            }

            if ($include_oos)
            {
                return true;
            }

            return (int) $item['stock_status'] !== ContentProduct::STOCK_STATUS_OUT_OF_STOCK;
        });

        $count = count($filtered);

        return (int) \apply_filters('cegg_offers_count', $count, $post_id, $items);
    }

    /**
     * Gets cached count from post meta.
     *
     * IMPORTANT: By default, this does NOT compute on demand if meta is missing,
     * to avoid repeated heavy work for old posts or archive loops.
     *
     * If you want compute-on-demand, enable via:
     * add_filter('cegg_offers_count_compute_on_demand', '__return_true');
     */
    public static function getCount(int $post_id): int
    {
        $post_id = (int) $post_id;
        if (!$post_id)
        {
            return 0;
        }

        $val = \get_post_meta($post_id, self::getMetaKey(), true);

        if ($val === '' || $val === null)
        {
            $compute = (bool) \apply_filters('cegg_offers_count_compute_on_demand', false, $post_id);

            if ($compute)
            {
                $count = self::calculateCount($post_id);
                \update_post_meta($post_id, self::getMetaKey(), (int) $count);
                return (int) $count;
            }

            // Missing meta => treat as 0
            return 0;
        }

        return max(0, (int) $val);
    }

    public static function getBadgeHtml(int $post_id, array $args = []): string
    {
        $count = self::getCount($post_id);

        $args = \wp_parse_args($args, [
            'min'   => 1,
            'label' => __('Offers', 'content-egg'),
            'class' => 'cegg-offers-badge',
        ]);

        if ($count < (int) $args['min'])
        {
            return '';
        }

        $html = \sprintf(
            '<span class="%s">%s: %d</span>',
            \esc_attr($args['class']),
            \esc_html($args['label']),
            (int) $count
        );

        return (string) \apply_filters('cegg_offers_badge_html', $html, $count, (int) $post_id, $args);
    }

    public static function shortcodeCount($atts): string
    {
        $atts = \shortcode_atts(['post_id' => 0], (array) $atts);
        $post_id = (int) $atts['post_id'];

        if (!$post_id)
        {
            $post_id = (int) \get_the_ID();
        }

        return (string) self::getCount((int) $post_id);
    }

    public static function shortcodeBadge($atts): string
    {
        $atts = \shortcode_atts([
            'post_id' => 0,
            'min'     => 1,
            'label'   => __('Offers', 'content-egg'),
            'class'   => 'cegg-offers-badge',
        ], (array) $atts);

        $post_id = (int) $atts['post_id'];
        if (!$post_id)
        {
            $post_id = (int) \get_the_ID();
        }

        return self::getBadgeHtml((int) $post_id, $atts);
    }

    /**
     * One-shot rebuild on upgrade.
     */
    public static function maybeRebuildOnUpgrade()
    {
        // Default: enable rebuild unless large site (>= 5000 posts/products).
        $default_rebuild = self::shouldRebuildOnUpgradeByDefault();

        // Allow overriding
        $rebuild = (bool) apply_filters('cegg_offers_count_rebuild_on_upgrade', $default_rebuild);
        if (!$rebuild)
        {
            return;
        }

        if (function_exists('ignore_user_abort'))
        {
            ignore_user_abort(true);
        }

        if (function_exists('set_time_limit'))
        {
            @set_time_limit(300);
        }

        if (function_exists('wp_raise_memory_limit'))
        {
            wp_raise_memory_limit('admin');
        }

        self::rebuildAllOffersCount();
    }

    /**
     * Decide whether rebuild should be enabled by default on upgrade.
     * Disable by default if total posts + Woo products >= 5000.
     */
    private static function shouldRebuildOnUpgradeByDefault()
    {
        $threshold = (int) apply_filters('cegg_offers_count_rebuild_threshold', 5000);

        $total = 0;

        // Count "post" posts (all statuses).
        $post_counts = wp_count_posts('post');
        if ($post_counts)
        {
            $total += array_sum((array) $post_counts);
        }

        // If WooCommerce product post type exists, count products too.
        if (post_type_exists('product'))
        {
            $product_counts = wp_count_posts('product');
            if ($product_counts)
            {
                $total += array_sum((array) $product_counts);
            }
        }

        // Disable rebuild by default on large sites.
        return ($total < $threshold);
    }

    /**
     * Rebuild counts for all posts that contain any Content Egg module data meta.
     */
    public static function rebuildAllOffersCount()
    {
        $module_ids = array_keys(ModuleManager::getInstance()->getAffiliateParsers(true, true));
        if (empty($module_ids))
        {
            return;
        }

        $meta_keys = array();
        foreach ($module_ids as $id)
        {
            $meta_keys[] = '_cegg_data_' . $id;
        }

        $batch_size = (int) apply_filters('cegg_offers_count_rebuild_batch_size', 25);
        if ($batch_size < 5)
        {
            $batch_size = 5;
        }

        // Perf helpers for large loops
        wp_suspend_cache_invalidation(true);
        wp_defer_term_counting(true);
        wp_defer_comment_counting(true);

        $processed = 0;
        $last_id   = 0;

        while (true)
        {
            // Seek pagination (no OFFSET)
            $post_ids = self::queryPostIdsWithMetaKeysAfterId($meta_keys, $batch_size, $last_id);
            if (empty($post_ids))
            {
                break;
            }

            // If query returns sorted IDs ASC, we can advance cursor efficiently
            $max_id_in_batch = $last_id;

            foreach ($post_ids as $post_id)
            {
                $post_id = (int) $post_id;
                if ($post_id > $max_id_in_batch)
                {
                    $max_id_in_batch = $post_id;
                }

                $count = (int) self::calculateCount($post_id);
                update_post_meta($post_id, self::getMetaKey(), $count);

                $processed++;

                // Targeted cleanup: keep caches from growing during huge runs
                //wp_cache_delete($post_id, 'post_meta');
                //wp_cache_delete($post_id, 'posts');
                //clean_post_cache($post_id);
            }

            $last_id = $max_id_in_batch;

            // Batch-level cleanup (once per batch)
            foreach ($post_ids as $pid)
            {
                $pid = (int) $pid;
                //wp_cache_delete($pid, 'post_meta');
                //wp_cache_delete($pid, 'posts');
            }

            unset($post_ids);

            if (function_exists('gc_collect_cycles'))
            {
                gc_collect_cycles();
            }

            // Adaptive batch shrinking if memory gets tight
            $mem_limit = self::iniBytes(ini_get('memory_limit'));
            if ($mem_limit > 0)
            {
                $usage = memory_get_usage(true);
                if ($usage > ($mem_limit * 0.75) && $batch_size > 5)
                {
                    $batch_size = (int) floor($batch_size / 2);
                    if ($batch_size < 5)
                    {
                        $batch_size = 5;
                    }
                }
            }
        }

        wp_defer_comment_counting(false);
        wp_defer_term_counting(false);
        wp_suspend_cache_invalidation(false);
    }

    /**
     * Seek-pagination query: returns post IDs (ASC) that have ANY of the $meta_keys,
     * only for posts with ID > $after_id.
     *
     * This avoids LIMIT/OFFSET, which becomes very slow and memory-hungry on large tables.
     */
    private static function queryPostIdsWithMetaKeysAfterId($meta_keys, $limit, $after_id)
    {
        global $wpdb;

        if (empty($meta_keys))
        {
            return array();
        }

        $limit    = max(1, (int) $limit);
        $after_id = (int) $after_id;

        // Placeholders for IN (%s,%s,...)
        $placeholders = implode(',', array_fill(0, count($meta_keys), '%s'));

        // DISTINCT prevents duplicates when a post has multiple matching meta rows
        $sql = "
        SELECT DISTINCT pm.post_id
        FROM {$wpdb->postmeta} pm
        INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
        WHERE pm.meta_key IN ($placeholders)
          AND pm.post_id > %d
          AND p.post_status NOT IN ('auto-draft','trash')
        ORDER BY pm.post_id ASC
        LIMIT %d
    ";

        $params = array_merge($meta_keys, array($after_id, $limit));

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $prepared = $wpdb->prepare($sql, $params);

        return $wpdb->get_col($prepared);
    }

    /**
     * Converts php.ini memory_limit (e.g. "512M", "2G") to bytes.
     * Returns -1 for unlimited, 0 for invalid.
     */
    protected static function iniBytes($val)
    {
        $val = trim((string) $val);
        if ($val === '')
        {
            return 0;
        }
        if ($val === '-1')
        {
            return -1;
        }

        $last = strtolower(substr($val, -1));
        $num = (int) $val;

        switch ($last)
        {
            case 'g':
                $num *= 1024;
                // no break
            case 'm':
                $num *= 1024;
                // no break
            case 'k':
                $num *= 1024;
                break;
        }

        return $num;
    }

    /**
     * WooCommerce integration
     * Option: GeneralConfig::getInstance()->option('woocommerce_offers_count_badge')
     */
    public static function maybeInitWooCommerceBadge()
    {
        if (is_admin())
        {
            return;
        }

        if (!class_exists('\WooCommerce'))
        {
            return;
        }

        $hook = (string) GeneralConfig::getInstance()->option('woocommerce_offers_count_badge_hook');
        if (!$hook || $hook === 'disabled')
        {
            return;
        }

        $hook = (string) \apply_filters('cegg_wc_offers_count_badge_hook', $hook);
        $allowed = array(
            'woocommerce_before_shop_loop_item_title',
            'woocommerce_shop_loop_item_title',
            'woocommerce_after_shop_loop_item_title',
            'woocommerce_after_shop_loop_item',
        );

        if (!in_array($hook, $allowed, true))
        {
            $hook = 'woocommerce_before_shop_loop_item_title';
        }
        $priority = (int) \apply_filters('cegg_wc_offers_count_badge_priority', 9);

        \add_action($hook, [__CLASS__, 'renderWooCommerceLoopBadge'], $priority);
    }

    public static function renderWooCommerceLoopBadge()
    {
        if (!\function_exists('wc_get_product'))
        {
            return;
        }

        global $product;

        if (!$product || !\is_a($product, '\WC_Product'))
        {
            $product = \wc_get_product(\get_the_ID());
        }

        if (!$product || !\is_a($product, '\WC_Product'))
        {
            return;
        }

        $post_id = (int) $product->get_id();

        // Allow per-site customization
        $args = (array) \apply_filters('cegg_wc_offers_count_badge_args', [
            'min'   => 1,
            'label' => __('Offers', 'content-egg'),
            'class' => 'cegg-offers-badge cegg-offers-badge--wc',
        ], $post_id);

        $count = (int) self::getCount($post_id);
        if ($count < (int) ($args['min'] ?? 1))
        {
            return;
        }

        $label = isset($args['label']) ? (string) $args['label'] : __('Offers', 'content-egg');
        $class = isset($args['class']) ? (string) $args['class'] : 'cegg-offers-badge cegg-offers-badge--wc';

        $url = $product->get_permalink();
        if (!$url)
        {
            return;
        }

        // Allow link customization if needed
        $url = (string) \apply_filters('cegg_wc_offers_count_badge_url', $url, $post_id, $product);

        $title = sprintf(
            /* translators: %d: number of offers */
            _n(
                'View product (%d offer)',
                'View product (%d offers)',
                $count,
                'content-egg'
            ),
            $count
        );

        $html = \sprintf(
            '<a class="%s" href="%s" title="%s" rel="nofollow">%s: %d</a>',
            \esc_attr($class),
            \esc_url($url),
            \esc_attr($title),
            \esc_html($label),
            (int) $count
        );

        $allowed = [
            'a' => [
                'class'  => true,
                'href'   => true,
                'title'  => true,
                'rel'    => true,
                'target' => true,
            ],
        ];

        // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
        echo self::getWooBadgeCssOnce();
        echo \wp_kses($html, $allowed);
    }

    protected static function getWooBadgeCssOnce(): string
    {
        static $printed = false;
        if ($printed)
        {
            return '';
        }
        $printed = true;

        $css = '
.cegg-offers-badge--wc {
  display: inline-block;
  padding: 2px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 600;
  line-height: 1.6;
  background: rgba(30, 115, 190, 0.12);
}
';

        // Allow override/extend (CSS only)
        $css = (string) apply_filters('cegg_wc_offers_count_badge_css', $css);

        // Remove any tags
        $css = wp_strip_all_tags($css);

        $css = trim($css);
        if ($css === '')
        {
            return '';
        }

        return '<style id="cegg-wc-offers-count-badge-css">' . $css . '</style>';
    }

    /**
     * Returns post IDs that have any of the specified postmeta keys.
     */
    protected static function queryPostIdsWithMetaKeys(array $meta_keys, int $limit, int $offset): array
    {
        global $wpdb;

        $meta_keys = array_values(array_filter($meta_keys));
        if (!$meta_keys)
        {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($meta_keys), '%s'));

        // Include common statuses; filterable.
        $statuses = (array) apply_filters('cegg_offers_count_rebuild_statuses', ['publish', 'private', 'draft']);
        $statuses = array_values(array_filter($statuses));
        $status_placeholders = implode(',', array_fill(0, count($statuses), '%s'));

        $sql = $wpdb->prepare(
            "SELECT DISTINCT pm.post_id
             FROM {$wpdb->postmeta} pm
             JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key IN ($placeholders)
               AND p.post_status IN ($status_placeholders)
             ORDER BY pm.post_id
             LIMIT %d OFFSET %d",
            array_merge($meta_keys, $statuses, [$limit, $offset])
        );

        $ids = (array) $wpdb->get_col($sql);

        return array_map('intval', $ids);
    }
}
