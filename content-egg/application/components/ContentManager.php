<?php

namespace ContentEgg\application\components;

defined('\ABSPATH') || exit;

use ContentEgg\application\helpers\ImageHelper;
use ContentEgg\application\helpers\ArrayHelper;
use ContentEgg\application\admin\GeneralConfig;
use ContentEgg\application\models\PriceHistoryModel;
use ContentEgg\application\PriceAlert;
use ContentEgg\application\helpers\CurrencyHelper;
use ContentEgg\application\helpers\TemplateHelper;
use ContentEgg\application\helpers\TextHelper;

use function ContentEgg\prn;
use function ContentEgg\prnx;

/**
 * ContentManager class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2024 keywordrush.com
 */
class ContentManager
{

    const META_PREFIX_DATA = '_cegg_data_';
    const META_PREFIX_LAST_ITEMS_UPDATE = '_cegg_last_update_';
    const META_PREFIX_KEYWORD = '_cegg_keyword';
    const META_PREFIX_UPDATE_PARAMS = '_cegg_update_params';
    const META_PREFIX_LAST_BYKEYWORD_UPDATE = '_cegg_last_bykeyword_update';

    private static $_view_data = array();

    public static function saveData(array $data, $module_id, $post_id, $is_last_iteration = true)
    {
        if (!$data)
        {
            self::deleteData($module_id, $post_id, $is_last_iteration);
            return;
        }

        $data = self::dataPresavePrepare($data, $module_id, $post_id);
        $old_data = ContentManager::getData($post_id, $module_id);

        $outdated = array();
        $data_changed = true;
        if ($old_data)
        {
            $outdated = array_diff_key($old_data, $data);
            $new = array_diff_key($data, $old_data);

            if (!$outdated && !$new)
            {
                $data_changed = false;
            }
            /*
             * we need force data update because title or description can be edited manually or items price update
             */
        }

        // save data
        \update_post_meta($post_id, self::META_PREFIX_DATA . $module_id, $data);
        self::clearData($outdated);

        // touch last update time only if data changed?
        if ($data_changed)
        {
            self::touchUpdateTime($post_id, $module_id);
        }

        // save price history
        if (GeneralConfig::getInstance()->option('price_history_days'))
        {
            PriceHistoryModel::model()->saveData($data, $module_id, $post_id);
            // ...and send price alerts
            if (GeneralConfig::getInstance()->option('price_alert_enabled'))
            {
                PriceAlert::getInstance()->sendAlerts($data, $module_id, $post_id);
            }
        }

        self::resetViewDataCache($module_id, $post_id);

        \do_action('content_egg_save_data', $data, $module_id, $post_id, $is_last_iteration);
    }

    public static function dataPresavePrepare(array $data, $module_id, $post_id)
    {
        foreach ($data as $i => $d)
        {
            if (is_object($d))
                $data[$i] = ArrayHelper::object2Array($d);
        }
        $data = self::setIds($data);
        // Sanitize content for allowed HTML tags and more.
        array_walk_recursive($data, array(__CLASS__, 'sanitizeData'));
        $module = ModuleManager::getInstance()->factory($module_id);
        $data = $module->presavePrepare($data, $post_id);

        foreach ($data as $id => $d)
        {
            foreach ($d as $field => $r)
            {
                if ($field[0] == '_')
                    $data[$id][$field] = null;
            }
        }

        return $data;
    }

    public static function deleteData($module_id, $post_id, $is_last_iteration = true)
    {
        $data = ContentManager::getData($post_id, $module_id);
        if (!$data)
        {
            // last chance to fire last_iteration hook
            \do_action('content_egg_save_data', array(), $module_id, $post_id, $is_last_iteration);

            return;
        }

        \delete_post_meta($post_id, self::META_PREFIX_DATA . $module_id);
        \delete_post_meta($post_id, self::META_PREFIX_LAST_BYKEYWORD_UPDATE . $module_id);
        \delete_post_meta($post_id, self::META_PREFIX_LAST_ITEMS_UPDATE . $module_id);

        self::clearData($data);
        self::resetViewDataCache($module_id, $post_id);

        \do_action('content_egg_save_data', array(), $module_id, $post_id, $is_last_iteration);
    }

    private static function clearData($data)
    {
        // delete old img files if needed
        foreach ($data as $d)
        {
            if (empty($d['img_file']))
            {
                continue;
            }
            $img_file = ImageHelper::getFullImgPath($d['img_file']);

            if (is_file($img_file))
            {
                @unlink($img_file);
            }
        }
    }

    private static function setIds($data)
    {
        $results = array();
        foreach ($data as $d)
        {
            $results[$d['unique_id']] = $d;
        }

        return $results;
    }

    public static function touchUpdateTime($post_id, $module_id, $touch_items = true)
    {
        $time = time();
        \update_post_meta($post_id, self::META_PREFIX_LAST_BYKEYWORD_UPDATE . $module_id, $time);
        if ($touch_items)
        {
            self::touchUpdateItemsTime($post_id, $module_id, $time);
        }
    }

    public static function touchUpdateItemsTime($post_id, $module_id, $time = null)
    {
        if (!$time)
        {
            $time = time();
        }
        \update_post_meta($post_id, self::META_PREFIX_LAST_ITEMS_UPDATE . $module_id, $time);
    }

    private static function sanitizeData(&$data, $key)
    {
        if (in_array((string) $key, array('img', 'url', 'IFrameURL', 'orig_url')))
        {
            if ($key == 'img')
            {
                $data = \esc_url_raw($data);
            }
            else
            {
                $data = \wp_sanitize_redirect($data);
                $data = filter_var($data, FILTER_SANITIZE_URL);
            }
        }
        elseif ($key === 'description')
        {
            $data = TextHelper::sanitizeHtml($data);
        }
        elseif ($key === 'linkHtml')
        {
            $data = wp_kses_post($data);
        }
        elseif ($key === 'title')
        {
            $data = \sanitize_text_field($data);
        }
        elseif ($key === 'last_update' && !$data)
        {
            $data = time();
        }
        elseif ($key === 'ean' && $data)
        {
            $data = TextHelper::fixEan(sanitize_text_field($data));
        }
        else
        {
            $data = wp_strip_all_tags($data);
        }
    }

    public static function isDataExists($post_id, $module_id)
    {
        if (\get_post_meta($post_id, ContentManager::META_PREFIX_DATA . $module_id, true))
            return true;
        else
            return false;
    }

    public static function isAutoupdateKeywordExists($post_id, $module_id)
    {
        if (\get_post_meta($post_id, ContentManager::META_PREFIX_KEYWORD . $module_id, true))
            return true;
        else
            return false;
    }

    public static function getData($post_id, $module_id)
    {
        $data = self::fixData(\get_post_meta($post_id, ContentManager::META_PREFIX_DATA . $module_id, true), $module_id);
        if (!$data)
        {
            $data = array();
        }

        return $data;
    }

    public static function fixData($data, $module_id)
    {
        if (!$data || !is_array($data))
        {
            return $data;
        }

        return $data;
    }

    public static function getViewData($module_id, $post_id, $params = array())
    {
        $data_id = $post_id . '-' . $module_id;
        if (!isset(self::$_view_data[$data_id]))
        {
            $data = self::getData($post_id, $module_id);

            if (!is_array($data))
                $data = array();

            $data = self::dataPreviewPrepare($data, $module_id, $post_id, $params);

            self::$_view_data[$data_id] = $data;
        }

        $data = self::$_view_data[$data_id];

        foreach ($data as $key => $d)
        {
            if (!$key)
            {
                unset($data[$key]);
            }
        }

        // out of stock products
        $outofstock_product = GeneralConfig::getInstance()->option('outofstock_product');
        //@see: ModuleViewer::getData for hide_product filter
        if ($outofstock_product == 'hide_price')
        {
            foreach ($data as $key => $d)
            {
                if (isset($d['stock_status']) && $d['stock_status'] == ContentProduct::STOCK_STATUS_OUT_OF_STOCK)
                {
                    $data[$key]['price'] = '';
                    $data[$key]['priceOld'] = '';
                    $data[$key]['total_price'] = '';
                }
            }
        }

        // locale filter
        if (!empty($params['locale']))
        {
            if (strstr($module_id, 'Amazon') && $params['locale'] == 'GB')
                $params['locale'] = 'UK';

            foreach ($data as $key => $d)
            {
                if (!isset($d['extra']['locale']))
                    continue;

                $product_locale = $d['extra']['locale'];

                if ($module_id == 'Ebay2')
                    $product_locale = str_replace('EBAY_', '', $product_locale);

                if (strtolower($product_locale) != strtolower($params['locale']))
                    unset($data[$key]);
            }
        }

        // ean filter
        if (!empty($params['ean']))
        {
            foreach ($data as $key => $d)
            {
                if (empty($d['ean']) || !in_array($d['ean'], $params['ean']))
                    unset($data[$key]);
            }
        }

        // convert all prices to one currency
        if (!empty($params['currency']))
        {
            foreach ($data as $key => $d)
            {
                $rate = CurrencyHelper::getCurrencyRate($d['currencyCode'], $params['currency']);
                if (!$rate)
                {
                    continue;
                }

                if (!empty($d['price']))
                {
                    $data[$key]['price'] = $d['price'] * $rate;
                    $data[$key]['currencyCode'] = $params['currency'];
                }
                if (!empty($d['priceOld']))
                {
                    $data[$key]['priceOld'] = $d['priceOld'] * $rate;
                }
            }
        }

        // add_query_arg
        if (!empty($params['add_query_arg']))
        {
            foreach ($data as $key => $d)
            {
                if (isset($d['url']))
                {
                    $data[$key]['url'] = \add_query_arg($params['add_query_arg'], $data[$key]['url']);
                }
            }
        }

        return $data;
    }

    public static function resetViewDataCache($module_id = null, $post_id = null)
    {
        if ($module_id && $post_id)
        {
            $data_id = $post_id . '-' . $module_id;
            if (isset(self::$_view_data[$data_id]))
            {
                unset(self::$_view_data[$data_id]);
            }
        }
        else
        {
            self::$_view_data = array();
        }
    }

    public static function dataPreviewPrepare(array $data, $module_id, $post_id, $params = array())
    {
        $is_ssl = \is_ssl();
        foreach ($data as $key => $d)
        {
            if ($module_id == 'Amazon' && !empty($d['extra']['IsEligibleForSuperSaverShipping']))
                $data[$key]['shipping_cost'] = '0.00';

            if (isset($d['shipping_cost']) && isset($d['price']))
                $data[$key]['total_price'] = (float) $data[$key]['price'] + (float) $data[$key]['shipping_cost'];
            elseif (isset($d['price']))
                $data[$key]['total_price'] = (float) $data[$key]['price'];
            else
                $data[$key]['total_price'] = '';

            if (!empty($data[$key]['title']))
            {
                // replace non-breaking space
                $data[$key]['title'] = str_replace("\xc2\xa0", ' ', $data[$key]['title']);
            }

            if (empty($data[$key]['extra']) || !is_array($data[$key]['extra']))
            {
                $data[$key]['extra'] = array();
            }

            // domain fix && logo
            if (empty($d['extra']['domain']) && isset($d['domain']))
            {
                $data[$key]['extra']['domain'] = $d['domain'];
            }
            elseif (empty($d['domain']) && isset($d['extra']['domain']))
            {
                $data[$key]['domain'] = $d['extra']['domain'];
            }
            if (empty($d['extra']['logo']) && isset($d['logo']))
            {
                $data[$key]['extra']['logo'] = $d['logo'];
            }
            elseif (empty($d['logo']) && isset($d['extra']['logo']))
            {
                $data[$key]['logo'] = $d['extra']['logo'];
            }

            // https fix for all images
            if ($is_ssl && isset($data[$key]['img']))
            {
                $data[$key]['img'] = str_replace('http://', '//', $d['img']);
            }

            if (isset($d['percentageSaved']))
            {
                $d['percentageSaved'] = (float) $d['percentageSaved'];
                if (!$d['percentageSaved'] || $d['percentageSaved'] < 0 || $d['percentageSaved'] >= 100)
                {
                    $d['percentageSaved'] = 0;
                }
                $data[$key]['percentageSaved'] = round($d['percentageSaved']);
            }

            if (empty($d['rating']) && isset($d['extra']['data']['rating']))
            {
                $data[$key]['rating'] = $d['extra']['data']['rating'];
            }

            if (isset($d['price']) && isset($d['priceOld']) && $d['price'] == $d['priceOld'])
                $data[$key]['priceOld'] = 0;

            if (isset($data[$key]['rating']))
            {
                $data[$key]['rating'] = (float) $data[$key]['rating'];
                if ($data[$key]['rating'] < 0 || $data[$key]['rating'] > 5)
                {
                    $data[$key]['rating'] = 0;
                }
                $data[$key]['rating'] = round(($data[$key]['rating'] * 2) / 2);
            }

            $data[$key]['number'] = 999;
            $number = TemplateHelper::getNumberFromTitle($data[$key]['title']);
            if ($number !== false)
            {
                $data[$key]['title'] = TemplateHelper::fixNumberedTitle($data[$key]['title']);
                $data[$key]['number'] = $number;
            }

            $data[$key]['post_id'] = $post_id;
            $data[$key]['module_id'] = $module_id;
        }

        // local redirect & other
        $module = ModuleManager::getInstance()->factory($module_id);

        if ($module->isParser())
        {
            $data = $module->viewDataPrepare($data);
        }

        return \apply_filters('cegg_view_data_prepare', $data, $module_id, $post_id, $params);
    }

    public static function getProductbyUniqueId($unique_id, $module_id, $post_id, $params = array())
    {
        $data = self::getViewData($module_id, $post_id, $params);
        if (!$data)
        {
            return null;
        }

        if (isset($data[$unique_id]))
        {
            return $data[$unique_id];
        }

        foreach ($data as $id => $d)
        {
            if ($unique_id == TextHelper::clearId($id))
            {
                return $data[$id];
            }
        }

        return null;
    }

    public static function updateByKeyword($post_id, $module_id, $is_last_interation = true, $force_feed_import = false)
    {
        if (!$keyword = ContentManager::getAutoupdateKeyword($post_id, $module_id))
        {
            // fix
            \delete_post_meta($post_id, self::META_PREFIX_LAST_BYKEYWORD_UPDATE . $module_id);
            return 0;
        }

        if (!$updateParams = \get_post_meta($post_id, ContentManager::META_PREFIX_UPDATE_PARAMS . $module_id, true))
            $updateParams = array();

        $module = ModuleManager::getInstance()->factory($module_id);

        if ($force_feed_import && $module->isFeedModule())
            $module->setLastImportDate(0);

        ContentManager::touchUpdateTime($post_id, $module_id, false);

        try
        {
            if (!$data = $module->doMultipleRequests($keyword, $updateParams, true))
            {
                \do_action('cegg_keyword_update_no_data', $post_id, $module_id);
                return -1;
            }
        }
        catch (\Exception $e)
        {
            return 0;
        }

        $data = array_map(array(__CLASS__, 'object2Array'), $data);

        ContentManager::saveData($data, $module_id, $post_id, $is_last_interation);
        return 1;
    }

    public static function updateItems($post_id, $module_id)
    {
        $module = ModuleManager::getInstance()->factory($module_id);
        if (!$module->isItemsUpdateAvailable())
            return;

        $items = ContentManager::getData($post_id, $module_id);

        if (!$items || !is_array($items))
        {
            // fix
            \delete_post_meta($post_id, self::META_PREFIX_LAST_ITEMS_UPDATE . $module_id);
            return;
        }

        try
        {
            $updated_data = $module->doRequestItems($items);
        }
        catch (\Exception $e)
        {
            // error
            ContentManager::touchUpdateItemsTime($post_id, $module_id);
            return;
        }

        $time = time();
        foreach ($updated_data as $key => $data)
        {
            $updated_data[$key]['last_update'] = $time;
        }

        // save & update time
        ContentManager::saveData($updated_data, $module_id, $post_id);
        ContentManager::touchUpdateItemsTime($post_id, $module_id);
    }

    /**
     *  Full depth recursive conversion to array
     *
     * @param type $object
     *
     * @return array
     */
    public static function object2Array($object)
    {
        return json_decode(json_encode($object), true);
    }

    public static function getNormalizedReviews($data)
    {
        $struct = array(
            'summary' => '',
            'comment' => '',
            'rating' => '',
            'name' => '',
            'date' => '',
            'pros' => '',
            'cons' => '',
            'review' => '',
            'parent_id' => '',
        );

        $reviews = array();
        foreach ($data as $item)
        {
            if (is_object($item))
            {
                $item = ContentManager::object2Array($item);
            }

            // AE modules & walmart
            if (!empty($item['extra']['comments']))
            {
                foreach ($item['extra']['comments'] as $r)
                {
                    $review = $struct;
                    $review['comment'] = $r['comment'];
                    if (!empty($r['name']))
                    {
                        $review['name'] = $r['name'];
                    }
                    if (!empty($r['date']))
                    {
                        $review['date'] = $r['date'];
                    }
                    if (!empty($r['review']))
                    {
                        $review['review'] = $r['review'];
                    }
                    if (!empty($r['rating']))
                    {
                        $review['rating'] = $r['rating'];
                    }
                    if (!empty($r['pros']))
                    {
                        $review['pros'] = $r['pros'];
                    }
                    if (!empty($r['cons']))
                    {
                        $review['cons'] = $r['cons'];
                    }
                    if (isset($r['parent_id']))
                    {
                        $review['parent_id'] = (int) $r['parent_id'];
                    }

                    $reviews[] = $review;
                }
            } // Ozon
            elseif (!empty($item['extra']['Reviews']))
            {
                foreach ($item['extra']['Reviews'] as $r)
                {
                    $review = $struct;
                    $review['summary'] = $r->Title;
                    $review['date'] = $r->Date;
                    $review['rating'] = $r->Rate;
                    $review['comment'] = $r->Comment;
                    $review['name'] = $r->FIO;
                    $reviews[] = $review;
                }
            }
        }

        foreach ($reviews as $i => $review)
        {
            if (!$review['comment'])
            {
                if ($review['review'])
                {
                    $review['comment'] = $review['review'];
                }
                if ($review['pros'])
                {
                    $review['comment'] .= "\r\n" . __('Pros:', 'content-egg-tpl') . $review['pros'];
                }
                if ($review['cons'])
                {
                    $review['comment'] .= "\r\n" . __('Cons:', 'content-egg-tpl') . $review['cons'];
                }
                $review['comment'] = trim($review['comment']);
                $reviews[$i] = $review;
            }
        }

        return $reviews;
    }

    public static function removeReviews($data)
    {
        foreach ($data as $i => $item)
        {
            if (!empty($item['extra']['comments']))
            {
                $data[$i]['extra']['comments'] = array();
            }
            elseif (!empty($item['extra']['Reviews']))
            {
                $data[$i]['extra']['Reviews'] = array();
            }
        }

        return $data;
    }

    public static function saveReviewsAsComments($post_id, array $normalized_comments)
    {
        $comment_data = array(
            'comment_post_ID' => $post_id,
            'comment_author_email' => '',
            'comment_author_url' => '',
            'comment_type' => '',
            'comment_parent' => 0,
            'user_id' => 0,
            'comment_approved' => 1,
        );

        $is_rehub_theme = (in_array(basename(\get_template_directory()), array(
            'rehub',
            'rehub-theme'
        ))) ? true : false;
        $rehub_post_type = \get_post_meta($post_id, 'rehub_framework_post_type', true);

        if ($rehub_post_type && $rehub_post_type == 'review')
        {
            $is_review_post_type = true;
        }
        else
        {
            $is_review_post_type = false;
        }

        if (\get_post_type($post_id) == 'product')
        {
            $is_woo_product = true;
            $comment_data['comment_type'] = 'review';
        }
        else
        {
            $is_woo_product = false;
        }

        $comments_keys_map = array();

        foreach ($normalized_comments as $i => $comment)
        {
            $comment_pros = '';
            $comment_cons = '';
            $comment_rating = 0;

            // rehub comment meta
            if ($is_rehub_theme && $is_review_post_type && !empty($comment['review']))
            {
                $comment_content = $comment['review'];
            }
            else
            {
                $comment_content = $comment['comment'];
            }

            $comment_data['comment_content'] = \wp_kses($comment_content, 'default');
            if (!empty($comment['name']))
            {
                $comment_data['comment_author'] = $comment['name'];
            }

            if (!empty($comment['date']))
            {
                $comment_data['comment_date'] = date('Y-m-d H:i:s', $comment['date']);
            }

            if (isset($comment['parent_id']) && is_numeric($comment['parent_id']) && isset($comments_keys_map[$comment['parent_id']]))
            {
                $comment_data['comment_parent'] = $comments_keys_map[$comment['parent_id']];
            }
            else
            {
                $comment_data['comment_parent'] = 0;
            }

            $comment_id = \wp_insert_comment($comment_data);

            //$comment_id = \wp_new_comment($comment_data);
            $comments_keys_map[$i] = $comment_id;

            if ($is_rehub_theme && $is_review_post_type)
            {
                if (!empty($comment['pros']))
                {
                    \add_comment_meta($comment_id, 'pros_review', $comment['pros']);
                }
                if (!empty($comment['cons']))
                {
                    \add_comment_meta($comment_id, 'cons_review', $comment['cons']);
                }
                if (!empty($comment['rating']))
                {
                    $rating_value = $comment['rating'] * 2;
                    \add_comment_meta($comment_id, 'user_average', $rating_value);
                    \add_comment_meta($comment_id, 'user_criteria', array(
                        array(
                            'name' => __('Rating', 'content-egg-tpl'),
                            'value' => $rating_value
                        )
                    ));
                }
                \add_comment_meta($comment_id, 'counted', 0);
                // calculate rating
                if (function_exists('add_comment_rates'))
                {
                    \add_comment_rates($comment_id);
                }
            }

            if ($is_woo_product && !empty($comment['rating']) && $comment['rating'] > 0 && $comment['rating'] <= 5)
            {
                \add_comment_meta($comment_id, 'rating', $comment['rating'], true);
            }
        }

        if ($is_woo_product)
        {
            \update_post_meta($post_id, '_wc_review_count', count($normalized_comments));
        }

        if ($is_woo_product && class_exists('\WC_Comments'))
        {
            \WC_Comments::clear_transients($post_id);
        }
    }

    public static function getMainProduct($modules_data, $main_product_selector = 'min_price')
    {
        $all_items = array();
        foreach ($modules_data as $module_id => $items)
        {
            foreach ($items as $item)
            {
                $item = ArrayHelper::object2Array($item);
                $item['module_id'] = $module_id;
                $all_items[] = $item;
            }
        }

        if (!$all_items)
        {
            return null;
        }
        if ($main_product_selector == 'random')
        {
            return $all_items[array_rand($all_items)];
        }

        if ($main_product_selector == 'max_price')
        {
            $order = 'desc';
        }
        else
        {
            $order = 'asc';
        }

        $sorted = TemplateHelper::sortByPrice($all_items, $order);

        return $sorted[0];
    }

    public static function getViewProductData($post_id, $merged = true)
    {
        $affiliate_modules = ModuleManager::getInstance()->getAffiliteModulesList(true);
        $modules_data = array();
        foreach (array_keys($affiliate_modules) as $module_id)
        {
            if (!$data = ContentManager::getViewData($module_id, $post_id))
                continue;

            $modules_data[$module_id] = $data;
        }

        if ($merged)
            $modules_data = TemplateHelper::mergeData($modules_data);

        return $modules_data;
    }

    public static function updateAllItems($post_id)
    {
        foreach (array_keys(ModuleManager::getInstance()->getAffiliteModulesList(true)) as $module_id)
        {
            ContentManager::updateItems($post_id, $module_id);
        }
    }

    public static function updateAllByKeyword($post_id)
    {
        $i = 0;
        $module_ids = array_keys(ModuleManager::getInstance()->getAffiliteModulesList(true));
        foreach ($module_ids as $module_id)
        {
            if ($i >= count($module_ids) - 1)
                $is_last_interation = true;
            else
                $is_last_interation = false;

            ContentManager::updateByKeyword($post_id, $module_id, $is_last_interation);
            $i++;
        }
    }

    public static function prepareMultipleKeywords($keyword)
    {
        $keywords = array();
        $groups = array();

        if (!$keyword)
            return array($keywords, $groups);

        // format 1: keyword1->grp1,keyword2->grp2
        if (!\apply_filters('cegg_disable_group_matching', false) && !\apply_filters('cegg_disable_multiple_keywords', false))
        {
            if (substr_count($keyword, '->') > 1 && substr_count($keyword, ',') >= 1)
            {
                $words = explode(',', $keyword);

                foreach ($words as $w)
                {
                    $parts = explode('->', $w);
                    $keywords[] = $parts[0];
                    if (isset($parts[1]))
                        $groups[] = $parts[1];
                    else
                        $groups[] = '';
                }
            }
        }

        // format 2: keyword1,keyword2->grp1,grp2
        if (!$keywords)
        {
            if (!\apply_filters('cegg_disable_group_matching', false))
            {
                $parts = explode('->', $keyword);
                if (count($parts) == 2)
                {
                    $groups = explode(',', $parts[1]);
                    $keyword = trim($parts[0]);
                }
            }

            if (!\apply_filters('cegg_disable_multiple_keywords', false))
                $keywords = explode(',', $keyword, 30);
            else
                $keywords = array($keyword);
        }

        $keywords = array_map('trim', $keywords);
        $keywords = array_map(array(__CLASS__, 'sanitizeKeyword'), $keywords);

        $groups = array_map('sanitize_text_field', $groups);
        $groups = array_map('trim', $groups);

        $groups = array_pad($groups, count($keywords) - count($groups) + 1, '');

        return array($keywords, $groups);
    }

    public static function sanitizeKeyword($keyword)
    {
        if (!$keyword || !is_string($keyword))
            return '';

        if ($keyword[0] == '[' || filter_var($keyword, FILTER_VALIDATE_URL))
        {
            $keyword = filter_var($keyword, FILTER_SANITIZE_URL);
            $keyword = str_replace('[cataloglimit', '[catalog limit', $keyword);
        }
        else
            $keyword = sanitize_text_field($keyword);

        return $keyword;
    }

    public static function findDuplicateIds(array $items, $field)
    {
        $used = array();
        $duplicate_ids = array();
        $modules_priority = self::getModulesPriority($items);

        foreach ($items as $module_id => $module_data)
        {
            foreach ($module_data as $unique_id => $data)
            {
                if (empty($data[$field]))
                    continue;

                if (isset($used[$data[$field]]))
                {
                    $d_module_id = $used[$data[$field]][0];

                    if ($d_module_id == $module_id)
                    {
                        $duplicate_ids[] = $unique_id;
                        continue;
                    }

                    if ($modules_priority[$module_id] > $modules_priority[$d_module_id])
                    {
                        $used[$data[$field]] = array($module_id, $unique_id);
                        $duplicate_ids[] = $unique_id;
                    }
                    else
                        $duplicate_ids[] = $used[$data[$field]][1];
                }
                else
                    $used[$data[$field]] = array($module_id, $unique_id);
            }
        }

        $duplicate_ids = array_unique($duplicate_ids);

        return $duplicate_ids;
    }

    public static function getModulesPriority(array $items)
    {
        $modules_priority = array();

        foreach ($items as $module_id => $module_data)
        {
            foreach ($module_data as $unique_id => $data)
            {
                $module_id = $data['module_id'];

                if (isset($modules_priority[$module_id]))
                    continue;

                if (!ModuleManager::getInstance()->moduleExists($module_id))
                    continue;

                $module = ModuleManager::getInstance()->factory($module_id);
                $modules_priority[$module_id] = (int) $module->config('priority');
            }
        }

        return $modules_priority;
    }

    public static function getAutoupdateKeyword($post_id, $module_id)
    {
        if (!$keyword = \get_post_meta($post_id, ContentManager::META_PREFIX_KEYWORD . $module_id, true))
            $keyword = \get_post_meta($post_id, '_cegg_global_autoupdate_keyword', true);

        if (!$keyword)
            $keyword = '';

        return \apply_filters('cegg_keyword_update', $keyword, $post_id, $module_id);
    }
}
