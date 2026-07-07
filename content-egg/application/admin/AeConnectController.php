<?php

namespace ContentEgg\application\admin;

use ContentEgg\application\Plugin;
use ContentEgg\application\modules\AE\AeModuleId;
use ContentEgg\application\modules\AE\AeSearchUrl;

defined('ABSPATH') || exit;

/**
 * Handles the "Connect new AE module" modal (create/activate an AE-backed module
 * from a domain) and disconnecting one. admin-post endpoints; nonce + capability
 * guarded. Never deletes saved product data on remove.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class AeConnectController
{
    public static function register()
    {
        \add_action('admin_post_cegg_ae_connect', array(__CLASS__, 'handleConnect'));
        \add_action('admin_post_cegg_ae_remove', array(__CLASS__, 'handleRemove'));
        \add_action('admin_notices', array(__CLASS__, 'maybeNotice'));
    }

    private static function modulesUrl($args = array())
    {
        return \add_query_arg(array_merge(array('page' => 'content-egg-modules'), $args), \admin_url('admin.php'));
    }

    private static function fail($message)
    {
        \wp_safe_redirect(self::modulesUrl(array('cegg_ae_error' => \rawurlencode($message))));
        exit;
    }

    public static function handleConnect()
    {
        if (!\current_user_can('manage_options'))
            \wp_die('Insufficient permissions.');
        \check_admin_referer('cegg_ae_connect');

        if (!AeIntegrationConfig::isAEIntegrationPosible())
            self::fail(__('Affiliate Egg is not available.', 'content-egg'));

        $domain_input = isset($_POST['domain']) ? \sanitize_text_field(\wp_unslash($_POST['domain'])) : '';
        $search_uri   = isset($_POST['search_uri']) ? \trim((string) \wp_unslash($_POST['search_uri'])) : '';

        // Auto-insert %KEYWORD% when the user pasted a real search URL (?q=term…).
        if ($search_uri !== '')
            $search_uri = AeSearchUrl::normalize($search_uri);

        $res = AeModuleId::resolve($domain_input);
        if (!$res['ok'])
            self::fail($res['error']);

        if (!$res['known'] && !AeIntegrationConfig::isCustomDomainSupported())
            self::fail(sprintf(__('Connecting a custom domain requires Affiliate Egg %s or newer.', 'content-egg'), AeIntegrationConfig::MIN_AE_VERSION_CUSTOM_DOMAIN));

        if ($search_uri !== '' && !AeSearchUrl::isValidSearchUri($search_uri))
            self::fail(__('The Search URL must include %KEYWORD% where the search term goes (or a recognizable parameter like ?q=). Keyword search will not work without it.', 'content-egg'));

        $config = AeIntegrationConfig::getInstance();
        if ($res['known'])
            $config->addKnownShop($res['key']);
        else
            $config->addCustomDomain($res['key']);

        // Activate the module by default (connecting is an explicit opt-in) and
        // seed the optional Search URL (custom domains only), in a single write.
        $opt = Plugin::slug . '_' . $res['module_id'];
        $cfg = \get_option($opt, array());
        if (!is_array($cfg))
            $cfg = array();
        $cfg['is_active'] = 1;
        if (!$res['known'] && $search_uri !== '')
            $cfg['search_uri'] = $search_uri;
        \update_option($opt, $cfg);

        // Module settings page uses the 'content-egg-modules--<id>' slug (see ModuleConfig::page_slug()).
        \wp_safe_redirect(\admin_url('admin.php?page=content-egg-modules--' . \urlencode($res['module_id'])));
        exit;
    }

    public static function handleRemove()
    {
        if (!\current_user_can('manage_options'))
            \wp_die('Insufficient permissions.');
        \check_admin_referer('cegg_ae_remove');

        $module_id = isset($_GET['module']) ? \sanitize_text_field(\wp_unslash($_GET['module'])) : '';
        $parts = explode('__', $module_id);
        $key = end($parts);
        if ($key !== '' && $key !== false)
            AeIntegrationConfig::getInstance()->removeModuleId($key);

        \wp_safe_redirect(self::modulesUrl());
        exit;
    }

    public static function maybeNotice()
    {
        if (empty($_GET['cegg_ae_error']))
            return;
        $screen = \get_current_screen();
        if (!$screen || strpos($screen->id, 'content-egg-modules') === false)
            return;
        echo '<div class="notice notice-error is-dismissible"><p>'
            . \esc_html(\rawurldecode(\wp_unslash($_GET['cegg_ae_error'])))
            . '</p></div>';
    }
}
