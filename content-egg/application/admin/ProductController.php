<?php

namespace ContentEgg\application\admin;

defined('\ABSPATH') || exit;

use ContentEgg\application\helpers\AdminHelper;
use ContentEgg\application\Plugin;
use ContentEgg\application\models\ProductModel;
use ContentEgg\application\helpers\TemplateHelper;

/**
 * ProductController class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class ProductController
{

    const slug = 'content-egg-product';

    /** Screen hook returned by add_submenu_page(), for the load- action. */
    private $hook = '';

    public function __construct()
    {
        \add_action('admin_menu', array($this, 'add_admin_menu'));
        \add_action('admin_init', array($this, 'remove_http_referer'));

        // set_screen_options() runs in wp-admin/admin.php before
        // set_current_screen() and before any load- hook, so the save filter has
        // to be in place from plugin boot or the value never persists.
        \add_filter('set_screen_option_' . ProductTable::per_page_option, array($this, 'save_per_page'), 10, 3);
    }

    public function remove_http_referer()
    {
        global $pagenow;

        // If we're on an admin page with the referer passed in the QS, prevent it nesting and becoming too long.
        if ($pagenow == 'admin.php' && isset($_GET['page']) && $_GET['page'] == 'content-egg-product' && !empty($_GET['_wp_http_referer']) && isset($_SERVER['REQUEST_URI']))
        {
            \wp_safe_redirect(\remove_query_arg(array('_wp_http_referer', '_wpnonce'), esc_url_raw(\wp_unslash($_SERVER['REQUEST_URI']))));
            exit;
        }
    }

    public function add_admin_menu()
    {
        $this->hook = \add_submenu_page(Plugin::slug, __('All Products', 'content-egg') . ' &lsaquo; Content Egg', __('All Products', 'content-egg'), 'publish_posts', self::slug, array($this, 'actionIndex'));

        if ($this->hook)
        {
            \add_action('load-' . $this->hook, array($this, 'add_screen_options'));
        }
    }

    /**
     * Screen options have to be registered on load-{$hook}: admin-header.php
     * prints the Screen Options panel before the page callback runs, so
     * registering from inside actionIndex() would always be too late.
     */
    public function add_screen_options()
    {
        // The Scan and Bridge Mappings actions render no list table.
        if (isset($_GET['action']))
        {
            return;
        }

        \add_screen_option('per_page', array(
            'label'   => __('Products per page', 'content-egg'),
            'default' => ProductTable::per_page,
            'option'  => ProductTable::per_page_option,
        ));
    }

    /**
     * @param mixed  $screen_option Value to store, or false to skip saving.
     * @param string $option        Option name.
     * @param int    $value         Submitted value.
     * @return int
     */
    public function save_per_page($screen_option, $option, $value)
    {
        return ListTableNav::clampPerPage($value, ProductTable::per_page);
    }

    public function actionIndex()
    {
        if (isset($_GET['action']) && $_GET['action'] === 'bridge-backfill')
        {
            $this->actionBridgeBackfill();
            return;
        }

        \wp_enqueue_script('content-egg-blockUI', \ContentEgg\PLUGIN_RES . '/js/jquery.blockUI.js', array('jquery'));

        if (isset($_GET['action']) && $_GET['action'] === 'scan')
            $forced = true;
        else
            $forced = false;

        ProductModel::model()->maybeScanProducts($forced);

        if ($forced)
        {
            $redirect_url = \admin_url('admin.php?page=' . self::slug);
            AdminHelper::redirect($redirect_url);
            exit;
        }

        $table = new ProductTable(ProductModel::model());
        $table->prepare_items();

        $last_scaned = ProductModel::model()->getLastSync();
        if (time() - $last_scaned <= 3600)
            /* translators: %s: human-readable time difference, e.g. "2 hours" */
            $last_scaned_str = sprintf(__('%s ago', 'content-egg'), \human_time_diff($last_scaned, time()));
        else
            $last_scaned_str = TemplateHelper::dateFormatFromGmt($last_scaned, true);

        \wp_enqueue_style('cegg-bootstrap5-full');

        PluginAdmin::getInstance()->render('product_index', array('table' => $table, 'last_scaned_str' => $last_scaned_str));
    }

    /**
     * Bridge Mappings: report which canonical mappings are missing for pages the
     * import tool created, and offer to create them.
     *
     * Rendering is the dry run — it only reads, so the screen is safe to link to
     * and reload. Writing happens in the POST handlers.
     */
    public function actionBridgeBackfill()
    {
        $apply = isset($_POST['cegg_backfill_apply']);
        $undo  = isset($_POST['cegg_backfill_undo']);

        if ($apply || $undo)
        {
            \check_admin_referer('cegg_bridge_backfill', 'cegg_bridge_backfill_nonce');

            if (!\current_user_can('manage_options'))
            {
                \wp_die(esc_html__('You do not have permission to change Bridge Page mappings.', 'content-egg'), 403);
            }

            $base = \admin_url('admin.php?page=' . self::slug . '&action=bridge-backfill');

            if ($undo)
            {
                $removed = BridgeBackfillService::undo();
                AdminHelper::redirect(\add_query_arg('undone', $removed, $base));
                exit;
            }

            // Re-plan rather than trusting anything posted: the row list is
            // derived server-side, never supplied by the client.
            $result = BridgeBackfillService::apply(BridgeBackfillService::plan());
            AdminHelper::redirect(\add_query_arg('created', $result['created'], $base));
            exit;
        }

        $plan      = BridgeBackfillService::plan();
        $receipt   = BridgeBackfillService::receipt();
        $can_apply = \current_user_can('manage_options');

        $destination = GeneralConfig::getInstance()->option('link_destination', 'affiliate');
        $live        = in_array($destination, array('bridge', 'both'), true);

        \wp_enqueue_style('cegg-bootstrap5-full');

        PluginAdmin::getInstance()->render('bridge_backfill', array(
            'plan'      => $plan,
            'receipt'   => $receipt,
            'can_apply' => $can_apply,
            'live'      => $live,
        ));
    }
}
