<?php

namespace ContentEgg\application\components;

defined('\ABSPATH') || exit;

use ContentEgg\application\admin\GeneralConfig;
use ContentEgg\application\helpers\ImageHelper;

/**
 * ImageOptimizeService class file
 *
 * Finds locally saved product-module images (img_file in _cegg_data_* meta)
 * and resizes oversized ones in place. "Processed" is physical: a file whose
 * longest side is <= the max size needs no work, so no bookkeeping flags are
 * stored per file. A cursor over post IDs drives the one-shot backfill.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class ImageOptimizeService
{
    const CURSOR_OPTION = 'cegg_image_optimize_cursor';
    const DONE_OPTION = 'cegg_image_optimize_done';
    const DEFAULT_MAX_SIZE = 1000;
    const MIN_MAX_SIZE = 200;
    const MAX_MAX_SIZE = 5000;
    const DEFAULT_QUALITY = 82;
    const MIN_QUALITY = 40;
    const RESIZE_BUDGET = 20;
    const TIME_BUDGET = 60;
    const POSTS_PER_QUERY = 25;

    public static function isEnabled()
    {
        // The key must be physically saved: fresh installs are seeded
        // 'enabled' and upgraded installs 'disabled' by the Installer. With
        // no key stored yet (e.g. cron fires between file deploy and the
        // admin-triggered upgrade), do nothing.
        $options = \get_option(GeneralConfig::getInstance()->option_name());

        return is_array($options)
            && isset($options['image_optimization'])
            && $options['image_optimization'] === 'enabled';
    }

    public static function getMaxSize()
    {
        $max = (int) GeneralConfig::getInstance()->option('image_optimization_max_size');

        // The resize is in-place and irreversible, so a typo like "100"
        // must never shrink the whole library: out of range -> default.
        if ($max < self::MIN_MAX_SIZE || $max > self::MAX_MAX_SIZE)
            return self::DEFAULT_MAX_SIZE;

        return $max;
    }

    public static function getQuality()
    {
        $quality = (int) GeneralConfig::getInstance()->option('image_optimization_quality');

        if ($quality < self::MIN_QUALITY || $quality > 100)
            return self::DEFAULT_QUALITY;

        return $quality;
    }

    public static function isEditorAvailable()
    {
        return (bool) \wp_image_editor_supports(array('methods' => array('resize')));
    }

    public static function isBackfillDone()
    {
        return (bool) \get_option(self::DONE_OPTION);
    }

    public static function markBackfillDone()
    {
        \update_option(self::DONE_OPTION, time(), false);
        \delete_option(self::CURSOR_OPTION);
    }

    public static function resetBackfill()
    {
        \delete_option(self::DONE_OPTION);
        \update_option(self::CURSOR_OPTION, 0, false);
    }

    /**
     * Optimize all product-module images of one post.
     *
     * @return int number of files actually resized.
     */
    public static function processPost($post_id)
    {
        $resized = 0;
        $max_size = self::getMaxSize();
        $quality = self::getQuality();

        foreach (self::collectImgFiles($post_id) as $img_file)
        {
            $full_path = ImageHelper::getFullImgPath($img_file);
            if (ImageHelper::optimizeImage($full_path, $max_size, $quality))
                $resized++;
        }

        return $resized;
    }

    /**
     * Process the next slice of the backfill.
     *
     * @return bool true when more work remains, false when the backfill
     *              completed (done flag set).
     */
    public static function processBatch()
    {
        $started = time();
        $resized = 0;
        $cursor = (int) \get_option(self::CURSOR_OPTION, 0);

        while (true)
        {
            $post_ids = self::getNextPostIds($cursor, self::POSTS_PER_QUERY);

            if (!$post_ids)
            {
                self::markBackfillDone();
                return false;
            }

            foreach ($post_ids as $post_id)
            {
                $resized += self::processPost($post_id);
                $cursor = (int) $post_id;

                if ($resized >= self::RESIZE_BUDGET || (time() - $started) >= self::TIME_BUDGET)
                {
                    \update_option(self::CURSOR_OPTION, $cursor, false);
                    return true;
                }
            }

            \update_option(self::CURSOR_OPTION, $cursor, false);
        }
    }

    private static function getNextPostIds($cursor, $limit)
    {
        global $wpdb;

        return $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT post_id FROM {$wpdb->postmeta}
                WHERE meta_key LIKE %s AND post_id > %d
                ORDER BY post_id ASC LIMIT %d",
            $wpdb->esc_like(ContentManager::META_PREFIX_DATA) . '%',
            $cursor,
            $limit
        ));
    }

    /**
     * Unique img_file values from product-module data rows of one post.
     */
    private static function collectImgFiles($post_id)
    {
        $files = array();
        $keys = \get_post_custom_keys($post_id);

        if (!$keys)
            return $files;

        foreach ($keys as $key)
        {
            if (strpos($key, ContentManager::META_PREFIX_DATA) !== 0)
                continue;

            $module_id = substr($key, strlen(ContentManager::META_PREFIX_DATA));
            if (!self::isProductModule($module_id))
                continue;

            $items = \get_post_meta($post_id, $key, true);
            if (!is_array($items))
                continue;

            foreach ($items as $item)
            {
                if (!empty($item['img_file']) && is_string($item['img_file']))
                    $files[] = $item['img_file'];
            }
        }

        return array_unique($files);
    }

    private static function isProductModule($module_id)
    {
        static $cache = array();

        if (!array_key_exists($module_id, $cache))
        {
            try
            {
                $module = ModuleManager::factory($module_id);
                $cache[$module_id] = $module && $module->isParser() && $module->getParserType() === ParserModule::PARSER_TYPE_PRODUCT;
            }
            catch (\Exception $e)
            {
                // Module can't be resolved (deactivated/removed): image-type
                // unknown, so skip conservatively per spec.
                $cache[$module_id] = false;
            }
        }

        return $cache[$module_id];
    }
}
