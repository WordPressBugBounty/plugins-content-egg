<?php

namespace ContentEgg\application\deals;

defined('\ABSPATH') || exit;

/**
 * DealsAdmin class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class DealsAdmin
{
    const PANEL_HANDLE = 'cegg-deal-panel';

    public static function init(): void
    {
        \add_filter('manage_' . DealPostType::POST_TYPE . '_posts_columns', array(__CLASS__, 'columns'));
        \add_action('manage_' . DealPostType::POST_TYPE . '_posts_custom_column', array(__CLASS__, 'column'), 10, 2);
        \add_action('restrict_manage_posts', array(__CLASS__, 'statusFilter'));
        \add_action('pre_get_posts', array(__CLASS__, 'applyStatusFilter'));
        \add_action('enqueue_block_editor_assets', array(__CLASS__, 'enqueuePanel'));
        \add_action('admin_menu', array(__CLASS__, 'menu'));
    }

    public static function menu(): void
    {
        \add_submenu_page(
            'edit.php?post_type=' . DealPostType::POST_TYPE,
            __('Add deals from links', 'content-egg'),
            __('Add deals', 'content-egg'),
            'manage_options',
            'admin.php?page=content-egg-product-import&tab=bulk&preset=' . DealPreset::id()
        );
    }

    public static function columns(array $columns): array
    {
        $out = array();
        foreach ($columns as $key => $label)
        {
            $out[$key] = $label;
            if ($key === 'title')
            {
                $out['cegg_deal_price'] = __('Price', 'content-egg');
                $out['cegg_deal_discount'] = __('Discount', 'content-egg');
                $out['cegg_deal_status'] = __('Status', 'content-egg');
                $out['cegg_deal_votes'] = __('Votes', 'content-egg');
                $out['cegg_deal_checked'] = __('Checked', 'content-egg');
            }
        }

        return $out;
    }

    public static function column(string $column, int $post_id): void
    {
        $deal = DealView::deal($post_id);

        switch ($column)
        {
            case 'cegg_deal_price':
                echo \esc_html(DealView::price($deal['price'], $deal['currency']) ?: '—');
                break;
            case 'cegg_deal_discount':
                echo $deal['discount'] > 0 ? '-' . (int) $deal['discount'] . '%' : '—';
                break;
            case 'cegg_deal_status':
                if (!\get_post_meta($post_id, DealPostType::META_STATUS, true))
                    echo \esc_html__('No product', 'content-egg');
                elseif ($deal['status'] === DealStatus::EXPIRED)
                    echo \esc_html(DealView::reasonLabel($deal['reason']));
                else
                    echo \esc_html__('Active', 'content-egg');
                break;
            case 'cegg_deal_votes':
                echo (int) $deal['votes_up'] . ' / ' . (int) $deal['votes_down'];
                break;
            case 'cegg_deal_checked':
                echo $deal['verified'] ? \esc_html(sprintf(__('%s ago', 'content-egg'), \human_time_diff($deal['verified'], time()))) : '—';
                break;
        }
    }

    public static function statusFilter($post_type): void
    {
        if ($post_type !== DealPostType::POST_TYPE)
            return;

        $current = isset($_GET['deal_status']) ? \sanitize_key(\wp_unslash($_GET['deal_status'])) : ''; // phpcs:ignore
        echo '<select name="deal_status">';
        echo '<option value="">' . \esc_html__('All statuses', 'content-egg') . '</option>';
        echo '<option value="active"' . \selected($current, 'active', false) . '>' . \esc_html__('Active', 'content-egg') . '</option>';
        echo '<option value="expired"' . \selected($current, 'expired', false) . '>' . \esc_html__('Expired', 'content-egg') . '</option>';
        echo '</select>';
    }

    public static function applyStatusFilter($q): void
    {
        if (!\is_admin() || !$q->is_main_query() || $q->get('post_type') !== DealPostType::POST_TYPE)
            return;

        $status = isset($_GET['deal_status']) ? \sanitize_key(\wp_unslash($_GET['deal_status'])) : ''; // phpcs:ignore
        if (!in_array($status, array(DealStatus::ACTIVE, DealStatus::EXPIRED), true))
            return;

        $q->set('meta_query', array(array('key' => DealPostType::META_STATUS, 'value' => $status)));
    }

    public static function enqueuePanel(): void
    {
        $screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
        if (!$screen || $screen->post_type !== DealPostType::POST_TYPE)
            return;

        $asset_file = \ContentEgg\PLUGIN_PATH . 'res/deals/deal-panel.asset.php';
        if (!file_exists($asset_file))
            return;

        $asset = require $asset_file;
        \wp_enqueue_script(self::PANEL_HANDLE, \ContentEgg\PLUGIN_RES . '/deals/deal-panel.js', (array) $asset['dependencies'], $asset['version'], true);
        \wp_set_script_translations(self::PANEL_HANDLE, 'content-egg');
    }
}
