<?php

namespace ContentEgg\application\admin;

defined('\ABSPATH') || exit;

use ContentEgg\application\models\ProductModel;
use ContentEgg\application\helpers\TemplateHelper;
use ContentEgg\application\components\ContentProduct;
use ContentEgg\application\helpers\TextHelper;
use ContentEgg\application\components\ModuleManager;
use ContentEgg\application\helpers\ClickStatsHelper;

/**
 * ProductTable class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class ProductTable extends MyListTable
{
    public const per_page = 20;

    /** User meta key behind the "Products per page" screen option. */
    public const per_page_option = 'cegg_products_per_page';

    protected function perPage()
    {
        return ListTableNav::clampPerPage(
            $this->get_items_per_page(self::per_page_option, self::per_page),
            self::per_page
        );
    }

    public function get_columns()
    {
        $cols = array(
            'img'          => '',
            'title'        => ProductModel::model()->getAttributeLabel('title'),
            'module_id'    => __('Module', 'content-egg'),
            'stock_status' => ProductModel::model()->getAttributeLabel('stock_status'),
            'price'        => ProductModel::model()->getAttributeLabel('price'),
            'last_update'  => ProductModel::model()->getAttributeLabel('last_update'),
        );

        if (ClickStatsHelper::isEnabled())
        {
            // Label adjusts to retention if < 30d (e.g., "Clicks (10d)")
            $label30 = ClickStatsHelper::label30();
            $cols['clicks_30d']   = sprintf(__('Clicks (%s)', 'content-egg'), $label30);
            $cols['clicks_total'] = __('Clicks (Total)', 'content-egg');
        }

        return $cols;
    }

    /**
     * The row header is the product title, not its thumbnail.
     *
     * WordPress 7.1 renders the primary column as <th scope="row"> and defaults
     * it to the first column — here the image — which made the thumbnail the row
     * header, put the "Show more details" toggle inside it, and left it with an
     * empty data-colname. The title is what names the row, and it is already
     * where this table renders its row actions.
     */
    protected function get_default_primary_column_name()
    {
        return 'title';
    }

    public function column_img($item)
    {
        echo '<a href="' . \esc_url(\get_edit_post_link($item['post_id'])) . '"><img class="attachment-thumbnail size-thumbnail wp-post-image" src="' . \esc_url($item['img']) . '" /></a>';
    }

    public function column_title($item)
    {
        if (!trim($item['title']))
        {
            $title = __('(no title)', 'content-egg');
        }
        else
        {
            $title = TextHelper::truncate($item['title'], 80);
        }

        $edit_link = \get_edit_post_link($item['post_id']) . '#' . $item['module_id'] . '-' . $item['unique_id'];
        $actions = array(
            'post_id' => sprintf(__('Post ID: %d', 'content-egg'), $item['post_id']),
            'view'    => sprintf('<a href="%s">%s</a>', \get_post_permalink($item['post_id']), __('View', 'content-egg')),
            'edit'    => sprintf('<a href="%s">%s</a>', \esc_url($edit_link), __('Edit', 'content-egg')),
        );
        if (!empty($item['url']))
        {
            $actions['goto'] = sprintf('<a target="_blank" href="%s">%s</a>', \esc_url($item['url']), __('Go to', 'content-egg'));
        }

        return '<strong><a class="row-title" href="' . \esc_url($edit_link) . '">' . \esc_html($title) . '</a></strong>' .
            $this->row_actions($actions);
    }

    public function column_clicks_30d($item)
    {
        $agg = ClickStatsHelper::aggregatesForItem($item);
        if (!$agg || $agg['d30'] === 0)
        {
            return '<span class="na">–</span>';
        }

        // Tooltip clarifies what the window is (e.g., "30d" or "10d" if retention=10)
        $title = sprintf(
            /* translators: %s is the retention-aware 30d label */
            __('Clicks in the last %s', 'content-egg'),
            ClickStatsHelper::label30()
        );

        return '<span title="' . esc_attr($title) . '">' . \number_format_i18n((int) ($agg['d30'] ?? 0)) . '</span>';
    }

    public function column_clicks_total($item)
    {
        $agg = ClickStatsHelper::aggregatesForItem($item);
        if (!$agg || $agg['total'] === 0)
        {
            return '<span class="na">–</span>';
        }

        return \number_format_i18n((int) ($agg['total'] ?? 0));
    }

    public function column_price($item)
    {
        $res  = (float) $item['price_old'] ? '<del>' . \wp_kses_post(TemplateHelper::formatPriceCurrency($item['price_old'], $item['currency_code'])) . '</del>' : '';
        $res .= (float) $item['price'] ? '<ins>' . \wp_kses_post(TemplateHelper::formatPriceCurrency($item['price'], $item['currency_code'])) . '</ins>' : '<span class="na">&ndash;</span>';
        return $res;
    }

    public function column_stock_status($item)
    {
        if ($item['stock_status'] == ContentProduct::STOCK_STATUS_IN_STOCK)
        {
            return '<mark class="instock">' . __('In stock', 'content-egg') . '</mark>';
        }
        elseif ($item['stock_status'] == ContentProduct::STOCK_STATUS_OUT_OF_STOCK)
        {
            return '<mark class="outofstock">' . __('Out of stock', 'content-egg') . '</mark>';
        }
        elseif ($item['stock_status'] == ContentProduct::STOCK_STATUS_UNKNOWN)
        {
            return '<span class="na">&ndash;</span>';
        }
    }

    public function column_module_id($item)
    {
        $module_id = $item['module_id'];
        if (!ModuleManager::getInstance()->moduleExists($module_id))
        {
            return;
        }
        $module = ModuleManager::getInstance()->factory($item['module_id']);
        $output = '<strong>' . esc_html($module->getName()) . '</strong>';

        if (!$module->isActive())
        {
            $output .= '<br><mark class="inactive">' . esc_html(__('inactive', 'content egg')) . '</mark>';
        }

        return $output;
    }

    public function column_last_update($item)
    {
        if (empty($item['last_update']))
            return '<span class="na">&ndash;</span>';

        $last_update_timestamp = strtotime($item['last_update']);
        $show_date_time = TemplateHelper::dateFormatFromGmt($last_update_timestamp, true);

        // last 24 hours?
        if ($last_update_timestamp > strtotime('-1 day', \current_time('timestamp', true)))
        {
            $show_date = sprintf(
                /* translators: %s: human-readable time difference, e.g. "2 hours" */
                __('%s ago', 'content-egg'),
                \human_time_diff($last_update_timestamp, \current_time('timestamp', true))
            );
        }
        else
        {
            $show_date = TemplateHelper::dateFormatFromGmt($last_update_timestamp, false);
        }
        return sprintf(
            '<abbr datetime="%1$s" title="%2$s">%3$s</abbr>',
            esc_attr($show_date_time),
            esc_attr($show_date_time),
            esc_html($show_date)
        );
    }

    public function get_sortable_columns()
    {
        $sortable_columns = array(
            'price'        => array('price', true),
            'title'        => array('title', true),
            'module_id'    => array('module_id', true),
            'stock_status' => array('stock_status', true),
            'last_update'  => array('last_update', true),
        );

        return $sortable_columns;
    }

    public function get_bulk_actions()
    {
        return array();
    }

    protected function extra_tablenav($which)
    {
        if ($which != 'top')
            return;

        echo '<div class="alignleft actions">';

        $this->print_modules_dropdown();
        \submit_button(__('Filter', 'content-egg'), '', 'filter_action', false, array('id' => 'product-query-submit'));

        echo '</div>';
    }

    private function print_modules_dropdown()
    {
        $modules = ModuleManager::getInstance()->getAffiliteModulesList(true);
        $selected_module_id = !empty($_GET['module_id']) ? TextHelper::clear(sanitize_text_field(\wp_unslash($_GET['module_id']))) : '';

        echo '<select name="module_id" id="dropdown_module_id"><option value="">' . \esc_html__('Filter by module', 'content-egg') . '</option>';
        foreach ($modules as $module_id => $module_name)
        {
            echo '<option ' . \selected($module_id, $selected_module_id, false) . ' value="' . \esc_attr($module_id) . '">' . \esc_html($module_name) . '</option>';
        }
        echo '</select>';
    }

    protected function getWhereFilters()
    {
        return implode(' AND ', $this->filterClauses());
    }

    /**
     * WHERE fragments for the current request, keyed by filter name.
     *
     * Kept separate rather than concatenated so the stock-status counts in
     * get_views() can reuse every filter except their own.
     */
    private function filterClauses()
    {
        global $wpdb;

        $clauses = array();

        // search
        if (!empty($_REQUEST['s']))
        {
            $s = trim(sanitize_text_field(wp_unslash($_REQUEST['s'])));

            if (is_numeric($s))
                $clauses['s'] = 'post_id = ' . (int) $s;
            else
                $clauses['s'] = $wpdb->prepare('title LIKE %s', '%' . $wpdb->esc_like(\sanitize_text_field($s)) . '%');
        }

        // filters
        if (isset($_GET['stock_status']) && $_GET['stock_status'] !== '' && $_GET['stock_status'] !== 'all')
        {
            $stock_status = (int) $_GET['stock_status'];

            if (array_key_exists($stock_status, ProductModel::getStockStatuses()))
            {
                $clauses['stock_status'] = $wpdb->prepare('stock_status = %d', $stock_status);
            }
        }

        if (isset($_GET['module_id']) && $_GET['module_id'] !== '')
        {
            $module_id = TextHelper::clear(\sanitize_text_field(\wp_unslash($_GET['module_id'])));
            if (ModuleManager::getInstance()->moduleExists($module_id))
            {
                $clauses['module_id'] = $wpdb->prepare('module_id = %s', $module_id);
            }
        }

        return $clauses;
    }

    /**
     * Every active filter except stock_status — the baseline the per-status
     * counts are measured against, so "Out of stock (12)" means twelve within
     * the module and search you are actually looking at.
     */
    private function getViewCountWhere()
    {
        $clauses = $this->filterClauses();
        unset($clauses['stock_status']);

        return implode(' AND ', $clauses);
    }

    protected function get_views()
    {
        global $wpdb;

        $admin_url = \get_admin_url(\get_current_blog_id(), 'admin.php?page=' . ProductController::slug);

        // Carry the other filters across, so switching stock status narrows the
        // list you are looking at instead of resetting it. `paged` is left out
        // on purpose: a different status is a different result set.
        $carry = ListTableNav::carryArgs($_REQUEST, array('module_id', 's', 'orderby', 'order'));
        $base  = $this->getViewCountWhere();

        $current = isset($_REQUEST['stock_status']) ? \sanitize_text_field(\wp_unslash($_REQUEST['stock_status'])) : '';
        $is_all  = ($current === '' || $current === 'all');

        $status_links = array();

        $status_links['all'] = $this->viewLink(
            \add_query_arg(array_merge($carry, array('stock_status' => 'all')), $admin_url),
            __('All', 'content-egg'),
            (int) ProductModel::model()->count($base !== '' ? $base : null),
            $is_all
        );

        foreach (ProductModel::getStockStatuses() as $status_id => $status_name)
        {
            $clause = $wpdb->prepare('stock_status = %d', $status_id);
            $where  = $base !== '' ? $base . ' AND ' . $clause : $clause;

            $status_links[$status_id] = $this->viewLink(
                \add_query_arg(array_merge($carry, array('stock_status' => (int) $status_id)), $admin_url),
                $status_name,
                (int) ProductModel::model()->count($where),
                !$is_all && $current === (string) $status_id
            );
        }

        return $status_links;
    }

    private function viewLink($url, $label, $count, $current)
    {
        return sprintf(
            '<a href="%1$s"%2$s>%3$s <span class="count">(%4$s)</span></a>',
            \esc_url($url),
            $current ? ' class="current"' : '',
            \esc_html($label),
            \esc_html(\number_format_i18n($count))
        );
    }
}
