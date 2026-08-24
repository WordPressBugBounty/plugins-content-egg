<?php

namespace ContentEgg\application;

defined('\ABSPATH') || exit;

use ContentEgg\application\components\ModuleManager;
use ContentEgg\application\components\ContentManager;
use ContentEgg\application\components\ModuleTemplateManager;
use ContentEgg\application\components\Shortcoded;
use ContentEgg\application\helpers\ArrayHelper;
use ContentEgg\application\components\BlockTemplateManager;
use ContentEgg\application\admin\GeneralConfig;
use ContentEgg\application\components\ContentProduct;
use ContentEgg\application\components\ProductBindingFilter;
use ContentEgg\application\components\ShortcodeAtts;
use ContentEgg\application\helpers\TemplateHelper;
use ContentEgg\application\components\ShopCoupons;

/**
 * ModuleViewer class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class ModuleViewer
{
    private static $instance = null;
    private $module_data_pointer = array();
    private $block_data_pointer = array();
    private $data = array();

    public static function getInstance()
    {
        if (self::$instance == null)
            self::$instance = new self;

        return self::$instance;
    }

    private function __construct()
    {
    }

    public function init()
    {
        // priority = 12 because do_shortcode() is registered as a default filter on 'the_content' with a priority of 11.
        \add_filter('the_content', array($this, 'viewData'), 12);
    }

    public function setData($module_id, $post_id, array $data)
    {
        if (!isset($this->data[$post_id]))
            $this->data[$post_id] = array();
        $this->data[$post_id][$module_id] = $data;
    }

    public function getData($module_id, $post_id, $params = array())
    {
        if (isset($this->data[$post_id]) && isset($this->data[$post_id][$module_id]))
            return $this->data[$post_id][$module_id];
        else
        {
            $data = ContentManager::getViewData($module_id, $post_id, $params);
            $outofstock_product = GeneralConfig::getInstance()->option('outofstock_product');
            if ($outofstock_product == 'hide_product')
            {
                foreach ($data as $key => $d)
                {
                    if (isset($d['stock_status']) && $d['stock_status'] == ContentProduct::STOCK_STATUS_OUT_OF_STOCK)
                    {
                        unset($data[$key]);
                    }
                }
            }

            return $data;
        }
    }

    public function viewData($content)
    {
        global $post;
        if ($post)
            $post_id = $post->ID;
        else
            $post_id = -1;

        $top_modules_priorities = array();
        $bottom_modules_priorities = array();
        foreach (ModuleManager::getInstance()->getModules(true) as $module_id => $module)
        {
            $embed_at = $module->config('embed_at');
            if ($embed_at != 'post_bottom' && $embed_at != 'post_top')
                continue;
            if (Shortcoded::getInstance($post_id)->isShortcoded($module->getId()))
                continue;

            $priority = (int) $module->config('priority');
            if ($embed_at == 'post_top')
                $top_modules_priorities[$module_id] = $priority;
            elseif ($embed_at == 'post_bottom')
                $bottom_modules_priorities[$module_id] = $priority;
        }

        // sort by priority, keep module_id order
        $top_modules_priorities = ArrayHelper::asortStable($top_modules_priorities);
        $bottom_modules_priorities = ArrayHelper::asortStable($bottom_modules_priorities);

        // reverse for corret gluing order
        $top_modules_priorities = array_reverse($top_modules_priorities, true);
        foreach ($top_modules_priorities as $module_id => $p)
        {
            $content = $this->viewModuleData($module_id, $post_id, array()) . $content;
        }
        foreach ($bottom_modules_priorities as $module_id => $p)
        {
            $content = $content . $this->viewModuleData($module_id, $post_id, array());
        }

        return $content;
    }

    public function viewModuleData($module_id, $post_id = null, $params = array(), $content = '')
    {
        if (!$post_id)
        {
            global $post;
            $post_id = $post->ID;
        }

        if (!$params)
            $params = ShortcodeAtts::prepare(array());

        $data = $this->getData($module_id, $post_id, $params);
        if (!$data)
            return '';

        //groups
        if (!empty($params['groups']))
        {
            if (!is_array($params['groups']))
                $params['groups'] = array($params['groups']);

            foreach ($data as $key => $d)
            {
                if (empty($d['group']) || !in_array($d['group'], $params['groups']))
                    unset($data[$key]);
            }
        }

        // product IDs (supports legacy bare unique_id and composite module_id:unique_id)
        if (!empty($params['products']))
        {
            foreach ($data as $key => $d)
            {
                if (!ProductBindingFilter::matches((string) $d['unique_id'], (string) $module_id, $params['products']))
                    unset($data[$key]);
            }
        }

        // hide fields
        if (!empty($params['hide']))
        {
            foreach ($data as $key => $d)
            {
                foreach ($params['hide'] as $hide)
                {
                    if (isset($d[$hide]))
                    {
                        if ($hide == 'title')
                            $data[$key]['_alt'] = $data[$key][$hide];
                        $data[$key][$hide] = '';
                    }
                }
            }
        }

        // sort
        if (!empty($params['sort']))
        {
            if ($params['sort'] == 'reverse')
                $data = array_reverse($data);
            elseif ($params['sort'] == 'price' || $params['sort'] == 'discount' || $params['sort'] == 'total_price')
                $data = TemplateHelper::sortByPrice($data, $params['order'], $params['sort']);
        }

        $module = ModuleManager::factory($module_id);
        $keyword = \get_post_meta($post_id, ContentManager::META_PREFIX_KEYWORD . $module->getId(), true);

        if (!isset($this->module_data_pointer[$post_id]))
            $this->module_data_pointer[$post_id] = array();

        // next param
        if (!empty($params['next']))
        {
            if (!isset($this->module_data_pointer[$post_id][$module_id]))
                $this->module_data_pointer[$post_id][$module_id] = 0;

            $data = array_splice($data, $this->module_data_pointer[$post_id][$module_id], $params['next']);
            if (count($data) < $params['next'])
                $params['next'] = count($data);

            $this->module_data_pointer[$post_id][$module_id] += $params['next'];
        }
        elseif (!empty($params['limit']))
        {
            if (!isset($params['offset']))
                $params['offset'] = 0;

            $data = array_splice($data, $params['offset'], $params['limit']);
            $this->module_data_pointer[$post_id][$module_id] = $params['offset'] + $params['limit'];
        }

        if (!$data)
            return;

        // template
        $tpl_manager = ModuleTemplateManager::getInstance($module_id);
        if (!empty($params['template']) && $tpl_manager->isTemplateExists($params['template']))
            $template = $params['template'];
        else
            $template = $module->config('template');

        if (!empty($params['title']))
            $title = $params['title'];
        else
            $title = $module->config('tpl_title');

        if (!empty($params['cols']))
            $cols = $params['cols'];
        else
            $cols = 0;

        if (isset($params['disable_features']))
            $disable_features = $params['disable_features'];
        else
            $disable_features = 0;

        if (isset($params['btn_text']))
            $btn_text = $params['btn_text'];
        else
            $btn_text = '';

        $tpl_manager->setParams($params);
        $tpl_manager->setItems(array_values($data));

        return $tpl_manager->render($template, array('items' => array_values($data), 'title' => $title, 'keyword' => $keyword, 'post_id' => $post_id, 'module_id' => $module_id, 'cols' => $cols, 'disable_features' => $disable_features, 'btn_text' => $btn_text, 'atts' => $params, 'params' => $params, 'content' => $content));
    }

    public function viewBlockData(array $module_ids, $post_id = null, $params = array(), $content = '', $only_return_data = false)
    {
        $use_sources = !empty($params['sources']) && is_array($params['sources']);
        $user_specified_sort = !empty($params['sort']);

        if (!$post_id)
        {
            global $post;
            $post_id = $post->ID;
        }

        // Get modules data
        if ($use_sources)
        {
            $data = $this->getSourcedBlockData($module_ids, $params['sources'], $params);

            // shortcoded!
            if (!isset($params['shortcoded']) || (bool) $params['shortcoded'])
            {
                foreach ($module_ids as $module_id)
                    Shortcoded::getInstance($post_id)->setShortcodedModule($module_id);
            }
        }
        else
        {
            $data = array();
            foreach ($module_ids as $module_id)
            {
                $module_data = $this->getData($module_id, $post_id, $params);

                //groups filter
                if (!empty($params['groups']))
                {
                    foreach ($module_data as $key => $d)
                    {
                        if (empty($d['group']) || !in_array($d['group'], $params['groups']))
                            unset($module_data[$key]);
                    }
                }

                // product IDs filter (supports legacy bare unique_id and composite module_id:unique_id)
                if (!empty($params['products']))
                {
                    foreach ($module_data as $key => $d)
                    {
                        if (!ProductBindingFilter::matches((string) $d['unique_id'], (string) $module_id, $params['products']))
                            unset($module_data[$key]);
                    }
                }

                if ($module_data)
                    $data[$module_id] = $module_data;

                // shortcoded!
                if (!isset($params['shortcoded']) || (bool) $params['shortcoded'])
                    Shortcoded::getInstance($post_id)->setShortcodedModule($module_id);
            }
        }

        // hide fields
        if (!empty($params['hide']))
        {
            foreach ($data as $module_id => $module_data)
            {
                foreach ($module_data as $key => $d)
                {
                    foreach ($params['hide'] as $hide)
                    {
                        if ($hide == 'title')
                            $data[$module_id][$key]['_alt'] = $data[$module_id][$key]['title'];
                        if (isset($d[$hide]))
                            $data[$module_id][$key][$hide] = '';
                    }
                }
            }
        }

        // group pick
        if (!empty($params['group_pick']))
        {
            $data = TemplateHelper::pickByGroups($data, $params['group_pick']);
        }

        // remove duplicates
        if (!empty($params['remove_duplicates_by']))
        {
            if ($duplicate_ids = ContentManager::findDuplicatesByField($data, $params['remove_duplicates_by']))
            {
                foreach ($data as $module_id => $module_data)
                {
                    foreach ($module_data as $unique_id => $d)
                    {
                        if (in_array($unique_id, $duplicate_ids))
                            unset($data[$module_id][$unique_id]);
                    }

                    if (!count($data[$module_id]))
                        unset($data[$module_id]);
                }
            }
        }

        if (!$data)
            return;

        // template
        if ($params['template'] != 'block_greenshift')
        {
            $tpl_manager = BlockTemplateManager::getInstance();
            if (empty($params['template']) || !$tpl_manager->isTemplateExists($params['template']))
                return;
        }
        $template = $params['template'];

        $sorted_templates = array('block_offers_logo', 'block_offers_list', 'block_price_comparison', 'block_offers_logo_shipping', 'block_price_alert', 'block_popup_button', 'block_popup_compare', 'block_price_comparison_card', 'block_price_statistics', 'block_offers_logo_groups', 'block_offers_logo_shipping_groups', 'block_offers_logo_btn', 'block_review_box', 'block_text_links');
        if (in_array($params['template'], $sorted_templates))
        {
            if (empty($params['order']))
                $params['order'] = 'asc';
            if (empty($params['sort']))
                $params['sort'] = 'price';
        }

        // next, limit, offset
        if (!isset($this->block_data_pointer[$post_id]))
            $this->block_data_pointer[$post_id] = array();

        if (!empty($params['next']))
        {
            if (!isset($this->block_data_pointer[$post_id][$template]))
                $this->block_data_pointer[$post_id][$template] = 0;

            $data = $this->spliceBlockData($data, $this->block_data_pointer[$post_id][$template], $params['next'], $params['order'], $params['sort']);
            $count = $this->countBlockData($data);
            if ($count < $params['next'])
                $params['next'] = $count;
            $this->block_data_pointer[$post_id][$template] += $params['next'];
        }
        elseif (!empty($params['limit']))
        {
            if (!isset($params['offset']))
                $params['offset'] = 0;

            $data = $this->spliceBlockData($data, $params['offset'], $params['limit'], $params['order'], $params['sort']);
            $this->block_data_pointer[$post_id][$module_id] = $params['offset'] + $params['limit'];
        }
        elseif (!empty($params['order']) || !empty($params['sort']))
            $this->spliceBlockData($data, 0, 999999, $params['order'], $params['sort']);

        if (!$data)
            return;

        if (!empty($params['title']))
            $title = $params['title'];
        else
            $title = '';

        if (!empty($params['cols']))
            $cols = $params['cols'];
        else
            $cols = 0;

        if ($only_return_data)
            return $data;

        // An explicit selection — "Choose products/coupons" binding, or a manual
        // products="A:1,A:2,…" list — should display in the exact order it was
        // listed (pick-order = display-order). Only override this when the user
        // asked for a sort, or the template price-sorts by design ($sorted_templates,
        // e.g. comparison tables); otherwise the default merge would re-sort by
        // drag-order / badge / priority and lose the picked order.
        $explicit_pick = !$use_sources
            && !empty($params['products'])
            && !$user_specified_sort
            && !in_array($params['template'], $sorted_templates, true);

        if ($use_sources && !$user_specified_sort)
            $items = $this->sortBySourceOrder($data);
        elseif ($explicit_pick)
            $items = $this->sortByPickOrder($data, $params['products']);
        else
            $items = TemplateHelper::mergeAndSort($data, $params['order'], $params['sort']);

        $tpl_manager->setParams($params);
        $tpl_manager->setItems($items);

        // The only point where the final $items and the post are both known.
        // Resolving per row would cost one store read and one source call per
        // merchant in the block.
        $coupons = ShopCoupons::resolve($items, $params);
        $tpl_manager->setCoupons($coupons);

        $out = $tpl_manager->render($params['template'], array('data' => $data, 'items' => $items, 'post_id' => $post_id, 'params' => $params, 'title' => $title, 'cols' => $cols, 'sort' => $params['sort'], 'order' => $params['order'], 'groups' => $params['groups'], 'btn_text' => $params['btn_text'], 'atts' => $params, 'content' => $content));

        $coupons_display = ShopCoupons::displayMode($params);

        if ($out && $coupons_display === 'cards')
        {
            // A coupon the template already rendered inside a product row is
            // spoken for. Without this the single-coupon card appears in the
            // row AND below the block, saying the same thing twice a few
            // pixels apart.
            $coupons = ShopCoupons::without($coupons, $tpl_manager->couponsRenderedInRow());

            // After the product render has finished, never inside it: the
            // coupon templates drive setItem() and the manager is a singleton.
            $out .= ShopCoupons::cards($coupons, $items, $params);
        }
        elseif ($out && $coupons_display === 'attached_before')
        {
            $out = ShopCoupons::strip($coupons, $items) . $out;
        }
        elseif ($out && $coupons_display === 'attached' && !$tpl_manager->couponStripRendered())
        {
            // Below-the-block normally renders inside the block, just before
            // the disclaimer - see TemplateManager::renderCouponStrip(). This
            // is the fallback for a template that never calls that partial;
            // appending is worse placement, but better than losing the coupons.
            $out .= ShopCoupons::strip($coupons, $items);
        }

        return $out;
    }

    private function getSourcedBlockData(array $module_ids, array $sources, array $params)
    {
        $data = array();
        $counter = 0;

        foreach ($sources as $source)
        {
            $source_post_id = isset($source['post_id']) ? (int) $source['post_id'] : 0;
            if ($source_post_id <= 0)
                continue;

            $candidates = array();
            foreach ($module_ids as $module_id)
            {
                foreach ($this->getData($module_id, $source_post_id, $params) as $item)
                {
                    $candidates[] = $item;
                }
            }

            if (!empty($source['group']))
            {
                $candidates = array_values(array_filter($candidates, function ($item) use ($source)
                {
                    return !empty($item['group']) && $item['group'] == $source['group'];
                }));
            }

            $candidates = array_values(array_filter($candidates, function ($item)
            {
                return !isset($item['stock_status']) || $item['stock_status'] != ContentProduct::STOCK_STATUS_OUT_OF_STOCK;
            }));

            $candidates = TemplateHelper::sortByPrice($candidates, 'asc');

            $limit = isset($source['limit']) ? (int) $source['limit'] : 1;
            $candidates = array_slice($candidates, 0, $limit);

            if (!empty($source['badge']) && isset($candidates[0]))
                $candidates[0]['badge'] = $source['badge'];

            if (!empty($source['rating']) && isset($candidates[0]))
            {
                $candidates[0]['ratingDecimal'] = (float) $source['rating'];
                $candidates[0]['rating_locked'] = true;
            }

            foreach ($candidates as $item)
            {
                $item['_source_order'] = $counter++;
                $data[$item['module_id']][$item['unique_id']] = $item;
            }
        }

        return $data;
    }

    private function sortBySourceOrder(array $data)
    {
        $items = TemplateHelper::mergeAll($data);

        usort($items, function ($a, $b)
        {
            return ($a['_source_order'] ?? 0) <=> ($b['_source_order'] ?? 0);
        });

        return $items;
    }

    /**
     * Order merged items by their position in an explicit `products=` list, so a
     * picked/listed selection renders in the order it was given. Tokens are matched
     * the same way ProductBindingFilter does: composite "module_id:unique_id" first,
     * then a bare unique_id. Items not in the list sort to the end (the data is
     * already filtered by the same list, so that's only a safety fallback).
     */
    private function sortByPickOrder(array $data, array $products)
    {
        $items = TemplateHelper::mergeAll($data);

        $rank = array();
        foreach (array_values($products) as $i => $token)
            $rank[(string) $token] = $i;

        $rank_of = function ($item) use ($rank)
        {
            $composite = $item['module_id'] . ':' . $item['unique_id'];
            if (isset($rank[$composite]))
                return $rank[$composite];
            if (isset($rank[(string) $item['unique_id']]))
                return $rank[(string) $item['unique_id']];
            return PHP_INT_MAX;
        };

        usort($items, function ($a, $b) use ($rank_of)
        {
            return $rank_of($a) <=> $rank_of($b);
        });

        return $items;
    }

    private function spliceBlockData($data, $offset, $length, $order = null, $sort = null)
    {
        $all_items = TemplateHelper::mergeAndSort($data, $order, $sort);
        $all_items = array_splice($all_items, $offset, $length);

        $results = array();
        foreach ($all_items as $item)
        {
            if (!isset($results[$item['module_id']]))
                $results[$item['module_id']] = array();

            $results[$item['module_id']][$item['unique_id']] = $item;
        }

        return $results;
    }

    private function countBlockData($data)
    {
        $count = 0;
        foreach ($data as $module_id => $module_data)
        {
            $count += count($module_data);
        }
        return $count;
    }
}
