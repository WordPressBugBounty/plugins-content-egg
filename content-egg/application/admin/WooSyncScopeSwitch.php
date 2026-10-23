<?php

namespace ContentEgg\application\admin;

defined('\ABSPATH') || exit;

use ContentEgg\application\components\Config;
use ContentEgg\application\components\WooSyncScope;

/**
 * One-click switch of the WooCommerce automatic sync to "all product modules".
 *
 * The button is the hint GeneralConfig::render_woo_sync_scope() shows on a site
 * whose scope is "selected" with no module selected (automatic sync is off).
 * It is a link rather than a form field so it can be followed without saving
 * the rest of the settings page.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class WooSyncScopeSwitch
{
    const ACTION = 'cegg_woo_scope';

    public static function init()
    {
        \add_action('admin_init', array(__CLASS__, 'handle'));
    }

    /**
     * Process the click (nonce-checked), then redirect to strip the query args
     * so a refresh does not repeat it.
     */
    public static function handle()
    {
        if (empty($_GET[self::ACTION]) || !\current_user_can('manage_options'))
            return;

        \check_admin_referer(self::ACTION);

        if (\sanitize_key(\wp_unslash($_GET[self::ACTION])) === WooSyncScope::SCOPE_ALL)
        {
            Config::updateOption('woocommerce_sync_scope', WooSyncScope::SCOPE_ALL, GeneralConfig::getInstance()->option_name());
        }

        \wp_safe_redirect(\remove_query_arg(array(self::ACTION, '_wpnonce')));
        exit;
    }
}
