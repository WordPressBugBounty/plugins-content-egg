<?php

namespace ContentEgg\application\admin;

defined('\ABSPATH') || exit;

use ContentEgg\application\components\ShopStore;

/**
 * CouponsTable class file
 *
 * Every coupon on the site in one list, because the recurring job is not
 * editing shops - it is adding a code that dies in three days and seeing what
 * is live or expiring without opening twenty shops one at a time.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
if (!class_exists('\WP_List_Table'))
{
    require_once(\ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class CouponsTable extends \WP_List_Table
{
    const per_page = 30;

    private $counts = array();

    public function __construct()
    {
        parent::__construct(array(
            'singular' => 'cegg-coupon',
            'plural' => 'cegg-coupons',
            'ajax' => false,
        ));
    }

    public function get_columns()
    {
        return array(
            'shop' => __('Shop', 'content-egg'),
            'code' => __('Code', 'content-egg'),
            'discount' => __('Discount', 'content-egg'),
            'dates' => __('Runs', 'content-egg'),
            'terms' => __('Categories', 'content-egg'),
            'state' => __('State', 'content-egg'),
        );
    }

    public function column_shop($item)
    {
        $edit = \admin_url('admin.php?page=' . ShopsController::SLUG_EDIT . '&domain=' . rawurlencode($item['_domain']));
        $label = $item['_shop_name'] !== '' ? $item['_shop_name'] : $item['_domain'];

        return '<strong><a class="row-title" href="' . \esc_url($edit) . '">' . \esc_html($label) . '</a></strong>'
            . ($item['_shop_name'] !== '' ? '<br /><span class="description">' . \esc_html($item['_domain']) . '</span>' : '');
    }

    public function column_code($item)
    {
        if ($item['code'] !== '')
            return '<code>' . \esc_html($item['code']) . '</code>';

        return '<span class="description">' . \esc_html($item['title'] !== '' ? $item['title'] : __('(deal)', 'content-egg')) . '</span>';
    }

    public function column_discount($item)
    {
        return $item['discount'] !== '' ? \esc_html($item['discount']) : '<span class="description">&mdash;</span>';
    }

    public function column_dates($item)
    {
        $start = ShopsController::timestampToDate($item['start']);
        $end = ShopsController::timestampToDate($item['end']);

        if ($start === '' && $end === '')
            return '<span class="description">' . \esc_html__('always', 'content-egg') . '</span>';

        if ($start === '')
            /* translators: %s = a date */
            return \esc_html(sprintf(__('until %s', 'content-egg'), $end));

        if ($end === '')
            /* translators: %s = a date */
            return \esc_html(sprintf(__('from %s', 'content-egg'), $start));

        return \esc_html($start . ' – ' . $end);
    }

    public function column_terms($item)
    {
        if (!$item['terms'])
            return '<span class="description">' . \esc_html__('everywhere', 'content-egg') . '</span>';

        $names = array();
        foreach ($item['terms'] as $term_id)
        {
            $term = \get_term((int) $term_id);

            // A term the operator has since deleted. Naming the id beats
            // rendering nothing, which would read as "everywhere".
            $names[] = $term && !\is_wp_error($term)
                ? \esc_html($term->name)
                : '<span class="description">#' . (int) $term_id . '</span>';
        }

        return implode(', ', $names);
    }

    public function column_state($item)
    {
        $labels = ShopsController::stateLabels();
        $label = isset($labels[$item['_state']]) ? $labels[$item['_state']] : $item['_state'];

        return '<span class="cegg-coupon-state cegg-coupon-state-' . \esc_attr($item['_state']) . '">'
            . \wp_kses_post($label) . '</span>';
    }

    public function no_items()
    {
        \esc_html_e('No coupons yet. Open a shop to add one.', 'content-egg');
    }

    /**
     * State filters above the table, each carrying its count - so an operator
     * can see at a glance that three coupons expired without reading the list.
     */
    protected function get_views()
    {
        $current = isset($_REQUEST['state']) ? \sanitize_key(\wp_unslash($_REQUEST['state'])) : 'all';
        $base = ShopsController::tabUrl('coupons');

        if ($shop = isset($_REQUEST['shop']) ? \sanitize_text_field(\wp_unslash($_REQUEST['shop'])) : '')
            $base = \add_query_arg('shop', $shop, $base);

        $views = array();
        $labels = array_merge(array('all' => __('All', 'content-egg')), ShopsController::stateLabels());

        foreach ($labels as $key => $label)
        {
            $count = isset($this->counts[$key]) ? $this->counts[$key] : 0;

            if ($key !== 'all' && !$count)
                continue;

            $url = $key === 'all' ? $base : \add_query_arg('state', $key, $base);

            $views[$key] = sprintf(
                '<a href="%s"%s>%s <span class="count">(%s)</span></a>',
                \esc_url($url),
                $current === $key ? ' class="current"' : '',
                \wp_kses_post($label),
                \esc_html(\number_format_i18n($count))
            );
        }

        return $views;
    }

    protected function extra_tablenav($which)
    {
        if ($which !== 'top')
            return;

        $shops = ShopStore::all();

        if (count($shops) < 2)
            return;

        $selected = isset($_REQUEST['shop']) ? \sanitize_text_field(\wp_unslash($_REQUEST['shop'])) : '';

        echo '<div class="alignleft actions">';
        echo '<select name="shop">';
        echo '<option value="">' . \esc_html__('All shops', 'content-egg') . '</option>';

        foreach ($shops as $domain => $shop)
        {
            echo '<option value="' . \esc_attr($domain) . '" ' . \selected($selected, $domain, false) . '>'
                . \esc_html($shop['name'] !== '' ? $shop['name'] . ' (' . $domain . ')' : $domain)
                . '</option>';
        }

        echo '</select>';
        \submit_button(__('Filter', 'content-egg'), '', 'filter_action', false);
        echo '</div>';
    }

    public function prepare_items()
    {
        $this->_column_headers = array($this->get_columns(), array(), array());

        $now = time();
        $types = (string) GeneralConfig::getInstance()->option('coupon_types');

        $rows = ShopsController::allCouponRows($now, $types);

        if ($shop = isset($_REQUEST['shop']) ? \sanitize_text_field(\wp_unslash($_REQUEST['shop'])) : '')
        {
            $shop = ShopStore::normalizeDomain($shop);
            $rows = array_values(array_filter($rows, function ($r) use ($shop)
            {
                return $r['_domain'] === $shop;
            }));
        }

        // Counted BEFORE the state filter, or every view would read its own
        // filtered total and the numbers would move as you click them.
        $this->counts = array('all' => count($rows));
        foreach ($rows as $r)
        {
            if (!isset($this->counts[$r['_state']]))
                $this->counts[$r['_state']] = 0;
            $this->counts[$r['_state']]++;
        }

        if ($state = isset($_REQUEST['state']) ? \sanitize_key(\wp_unslash($_REQUEST['state'])) : '')
        {
            if ($state !== 'all')
            {
                $rows = array_values(array_filter($rows, function ($r) use ($state)
                {
                    return $r['_state'] === $state;
                }));
            }
        }

        // Soonest to expire first, then never-expiring, so what needs attention
        // is at the top.
        usort($rows, function ($a, $b)
        {
            if (!$a['end'] && !$b['end'])
                return strcmp($a['_domain'], $b['_domain']);

            if (!$a['end'])
                return 1;

            if (!$b['end'])
                return -1;

            return $a['end'] === $b['end'] ? strcmp($a['_domain'], $b['_domain']) : ($a['end'] < $b['end'] ? -1 : 1);
        });

        $total = count($rows);
        $paged = max(1, (int) $this->get_pagenum());

        $this->items = array_slice($rows, ($paged - 1) * self::per_page, self::per_page);

        $this->set_pagination_args(array(
            'total_items' => $total,
            'per_page' => self::per_page,
            'total_pages' => (int) ceil($total / self::per_page),
        ));
    }
}
