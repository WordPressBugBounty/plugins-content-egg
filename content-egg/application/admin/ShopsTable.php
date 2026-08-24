<?php

namespace ContentEgg\application\admin;

defined('\ABSPATH') || exit;

use ContentEgg\application\components\ShopCoupon;
use ContentEgg\application\components\ShopScan;
use ContentEgg\application\components\ShopStore;

/**
 * ShopsTable class file
 *
 * Extends WP_List_Table directly rather than MyListTable: MyListTable takes a
 * Model and issues SQL, and shops live in an option.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
if (!class_exists('\WP_List_Table'))
{
    require_once(\ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class ShopsTable extends \WP_List_Table
{
    const per_page = 20;

    private $counts = array();

    public function __construct()
    {
        parent::__construct(array(
            'singular' => 'cegg-shop',
            'plural' => 'cegg-shops',
            'ajax' => false,
        ));
    }

    public function get_columns()
    {
        return array(
            'cb' => '<input type="checkbox" />',
            'domain' => __('Shop', 'content-egg'),
            'products' => __('In your content', 'content-egg'),
            'coupons' => __('Coupons', 'content-egg'),
            'logo' => __('Logo', 'content-egg'),
        );
    }

    public function get_sortable_columns()
    {
        return array(
            'domain' => array('domain', false),
            'products' => array('products', true),
            'coupons' => array('coupons', false),
        );
    }

    /**
     * Only a shop that exists can be bulk-deleted. A scanned-but-not-added one
     * has nothing to delete, and a checkbox implying otherwise would be a lie.
     */
    public function column_cb($item)
    {
        if (!$item['stored'])
            return '';

        return sprintf('<input type="checkbox" name="domain[]" value="%s" />', \esc_attr($item['domain']));
    }

    /**
     * How many product rows the scan saw for this shop. This column is the
     * whole reason the screen is named "Shops": without it the page opens empty
     * beside a price comparison full of them.
     */
    public function column_products($item)
    {
        if (!$item['products'])
            return '<span class="description">&mdash;</span>';

        return \esc_html(\number_format_i18n($item['products']));
    }

    public function column_domain($item)
    {
        $edit_url = \admin_url('admin.php?page=' . ShopsController::SLUG_EDIT . '&domain=' . rawurlencode($item['domain']));

        $title = '<strong><a class="row-title" href="' . \esc_url($edit_url) . '">' . \esc_html($item['domain']) . '</a></strong>';

        if ($item['name'] !== '')
            $title .= '<br /><span class="description">' . \esc_html($item['name']) . '</span>';

        if (!$item['stored'])
        {
            return $title . $this->row_actions(array(
                'add' => '<a href="' . \esc_url($edit_url) . '">' . \esc_html__('Customize', 'content-egg') . '</a>',
            ));
        }

        $delete_url = \wp_nonce_url(
            \admin_url('admin.php?page=' . ShopsController::SLUG . '&action=delete&domain=' . rawurlencode($item['domain'])),
            'cegg_shop_delete'
        );

        return $title . $this->row_actions(array(
            'edit' => '<a href="' . \esc_url($edit_url) . '">' . \esc_html__('Edit', 'content-egg') . '</a>',
            'delete' => '<a class="content-egg-delete" href="' . \esc_url($delete_url) . '">' . \esc_html__('Delete', 'content-egg') . '</a>',
        ));
    }

    public function column_coupons($item)
    {
        $total = count($item['coupons']);

        if (!$total)
        {
            $edit_url = \admin_url('admin.php?page=' . ShopsController::SLUG_EDIT . '&domain=' . rawurlencode($item['domain']));

            return '<a href="' . \esc_url($edit_url) . '">' . \esc_html__('Add a coupon', 'content-egg') . '</a>';
        }

        $url = \add_query_arg('shop', rawurlencode($item['domain']), ShopsController::tabUrl('coupons'));

        $label = $item['live'] > 0
            /* translators: %s = number of live coupons */
            ? sprintf(_n('%s live', '%s live', $item['live'], 'content-egg'), \number_format_i18n($item['live']))
            : '<span class="description">' . \esc_html__('none live', 'content-egg') . '</span>';

        $suffix = $item['live'] < $total
            ? ' <span class="description">(' . \esc_html(sprintf(
                /* translators: %s = total number of coupons */
                _n('%s total', '%s total', $total, 'content-egg'),
                \number_format_i18n($total)
            )) . ')</span>'
            : '';

        return '<a href="' . \esc_url($url) . '">' . $label . '</a>' . $suffix;
    }

    /**
     * The one place an operator can see that a provider logo failed. Until the
     * per-shop override existed there was no way to fix one, so there was also
     * no reason to report it.
     */
    public function column_logo($item)
    {
        if (!$item['stored'])
            return '<span class="description">' . \esc_html__('Provider', 'content-egg') . '</span>';

        if ($item['logo'] !== '')
            return '<span class="dashicons dashicons-yes-alt" style="color:#46b450"></span> '
                . \esc_html__('Custom', 'content-egg');

        return '<span class="description">' . \esc_html__('Provider', 'content-egg') . '</span>';
    }

    public function no_items()
    {
        \esc_html_e('No shops found in your product data yet, and none added by hand.', 'content-egg');
    }

    public function prepare_items()
    {
        $this->_column_headers = array($this->get_columns(), array(), $this->get_sortable_columns());
        $this->process_bulk_action();

        $now = time();
        $types = (string) GeneralConfig::getInstance()->option('coupon_types');

        $scan = ShopScan::results();
        $found = isset($scan['domains']) ? $scan['domains'] : array();

        $rows = array();

        foreach (ShopStore::all() as $domain => $shop)
        {
            $live = 0;
            foreach ($shop['coupons'] as $c)
            {
                if (ShopCoupon::stateOf($c, $now, $types) === 'live')
                    $live++;
            }

            $shop['live'] = $live;
            $shop['stored'] = true;
            $shop['products'] = isset($found[$domain]) ? (int) $found[$domain] : 0;

            $rows[] = $shop;
        }

        // Everything the scan saw that has no shop record yet, so the page
        // opens listing the operator's real shops rather than an empty table
        // beside a price comparison full of them.
        foreach ($found as $domain => $count)
        {
            if (ShopStore::get($domain))
                continue;

            $rows[] = array(
                'domain' => $domain,
                'name' => '',
                'logo' => '',
                'info' => '',
                'coupons_html' => '',
                'coupons' => array(),
                'live' => 0,
                'stored' => false,
                'products' => (int) $count,
            );
        }

        if ($search = isset($_REQUEST['s']) ? strtolower(trim(\sanitize_text_field(\wp_unslash($_REQUEST['s'])))) : '')
        {
            $rows = array_values(array_filter($rows, function ($shop) use ($search)
            {
                return strpos($shop['domain'], $search) !== false
                    || strpos(strtolower($shop['name']), $search) !== false;
            }));
        }

        $this->counts = array(
            'all' => count($rows),
            'stored' => count(array_filter($rows, function ($r) { return $r['stored']; })),
            'found' => count(array_filter($rows, function ($r) { return !$r['stored']; })),
        );

        if ($view = isset($_REQUEST['view']) ? \sanitize_key(\wp_unslash($_REQUEST['view'])) : '')
        {
            if ($view === 'stored' || $view === 'found')
            {
                $want = $view === 'stored';
                $rows = array_values(array_filter($rows, function ($r) use ($want)
                {
                    return $r['stored'] === $want;
                }));
            }
        }

        $explicit = isset($_REQUEST['orderby']);

        $orderby = $explicit ? \sanitize_key($_REQUEST['orderby']) : 'products';
        $order = isset($_REQUEST['order'])
            ? (strtolower($_REQUEST['order']) === 'asc' ? 'asc' : 'desc')
            : ($orderby === 'domain' ? 'asc' : 'desc');

        if (!array_key_exists($orderby, $this->get_sortable_columns()))
            $orderby = 'products';

        usort($rows, function ($a, $b) use ($orderby, $order, $explicit)
        {
            // Default order only: a shop the operator customized outranks 80
            // auto-found ones. A shop with coupons and no products would
            // otherwise sit at the very bottom of its own screen.
            if (!$explicit && $a['stored'] !== $b['stored'])
                return $a['stored'] ? -1 : 1;

            if ($orderby === 'coupons')
                $cmp = $a['live'] === $b['live'] ? 0 : ($a['live'] < $b['live'] ? -1 : 1);
            elseif ($orderby === 'products')
                $cmp = $a['products'] === $b['products'] ? 0 : ($a['products'] < $b['products'] ? -1 : 1);
            else
                $cmp = strcmp((string) $a[$orderby], (string) $b[$orderby]);

            if ($cmp === 0)
                $cmp = strcmp($a['domain'], $b['domain']);

            return $order === 'desc' ? -$cmp : $cmp;
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

    protected function get_views()
    {
        $current = isset($_REQUEST['view']) ? \sanitize_key(\wp_unslash($_REQUEST['view'])) : 'all';
        $base = ShopsController::tabUrl();

        $labels = array(
            'all' => __('All', 'content-egg'),
            'stored' => __('Customized', 'content-egg'),
            'found' => __('Found in your content', 'content-egg'),
        );

        $views = array();
        foreach ($labels as $key => $label)
        {
            $count = isset($this->counts[$key]) ? $this->counts[$key] : 0;
            $url = $key === 'all' ? $base : \add_query_arg('view', $key, $base);

            $views[$key] = sprintf(
                '<a href="%s"%s>%s <span class="count">(%s)</span></a>',
                \esc_url($url),
                $current === $key ? ' class="current"' : '',
                \esc_html($label),
                \esc_html(\number_format_i18n($count))
            );
        }

        return $views;
    }

    public function get_bulk_actions()
    {
        return array('delete' => __('Delete', 'content-egg'));
    }

    public function process_bulk_action()
    {
        if ($this->current_action() !== 'delete' || empty($_REQUEST['domain']) || !is_array($_REQUEST['domain']))
            return;

        \check_admin_referer('bulk-' . $this->_args['plural']);

        foreach (\wp_unslash($_REQUEST['domain']) as $domain)
            ShopStore::delete(\sanitize_text_field($domain));
    }
}
