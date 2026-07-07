<?php

namespace ContentEgg\application\admin;

defined('\ABSPATH') || exit;

use ContentEgg\application\components\Config;
use ContentEgg\application\Plugin;;

/**
 * AeIntegrationConfig class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class AeIntegrationConfig extends Config
{

    const MIN_AE_VERSION = '7.1.0';

    const MIN_AE_VERSION_CUSTOM_DOMAIN = '11.0.0';

    public function page_slug()
    {
        return Plugin::slug . '-ae-integration';
    }

    public function option_name()
    {
        return Plugin::slug . '_ae_integration';
    }

    public function adminInit()
    {
        parent::adminInit();
        \add_action('admin_init', array($this, 'redirectLegacyPage'));
    }

    // Page retired: the standalone "AE Integration" submenu is no longer added.
    // Connecting/managing AE modules now lives on the Modules page.
    public function add_admin_menu()
    {
    }

    public function redirectLegacyPage()
    {
        if (isset($_GET['page']) && $_GET['page'] === $this->page_slug())
        {
            \wp_safe_redirect(\admin_url('admin.php?page=content-egg-modules'));
            exit;
        }
    }

    protected function options()
    {
        if (!self::isAEIntegrationPosible())
            return array();

        $aff_egg_modules = \Keywordrush\AffiliateEgg\ShopManager::getInstance()->getSearchableItemsList(true, false, false);
        return array(
            'modules' => array(
                'title' => __('Activate modules', 'content-egg'),
                'description' => '',
                'checkbox_options' => $aff_egg_modules,
                'callback' => array($this, 'render_checkbox_list'),
                'default' => array(),
                'section' => 'default',
            ),
        );
    }

    public static function isAEIntegrationPosible()
    {
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');

        if (!\is_plugin_active('affiliate-egg/affiliate-egg.php'))
            return false;

        if (!class_exists('\Keywordrush\AffiliateEgg\ShopManager') || !\Keywordrush\AffiliateEgg\LicConfig::getInstance()->option('license_key'))
            return false;

        $v = \Keywordrush\AffiliateEgg\AffiliateEgg::version();

        if (version_compare(self::MIN_AE_VERSION, $v, '>'))
            return false;

        return true;
    }

    /**
     * True when the installed Affiliate Egg can connect an arbitrary
     * (unregistered) domain via its generic parser. Version-gated AND
     * feature-detected so a version mismatch can never fatal.
     */
    public static function isCustomDomainSupported()
    {
        if (!self::isAEIntegrationPosible())
            return false;
        if (version_compare(self::MIN_AE_VERSION_CUSTOM_DOMAIN, \Keywordrush\AffiliateEgg\AffiliateEgg::version(), '>'))
            return false;
        if (!class_exists('\Keywordrush\AffiliateEgg\GenericParser'))
            return false;
        $pm = \Keywordrush\AffiliateEgg\ParserManager::getInstance();
        if (!method_exists($pm, 'domainKey') || !method_exists($pm, 'genericEnabled'))
            return false;
        return (bool) $pm->genericEnabled();
    }

    /**
     * True when the installed Affiliate Egg's parseSearchCatalog() accepts a
     * search-URL override (5th parameter), letting a registered shop's module
     * override the built-in search URL. Reflection-detected so an older AE never
     * shows a Search URL field that wouldn't do anything.
     */
    public static function isSearchUriOverrideSupported()
    {
        if (!self::isAEIntegrationPosible())
            return false;
        try
        {
            $rm = new \ReflectionMethod('\Keywordrush\AffiliateEgg\ParserManager', 'parseSearchCatalog');
            return $rm->getNumberOfParameters() >= 5;
        }
        catch (\Throwable $e)
        {
            return false;
        }
    }

    /**
     * Affiliate Egg landing page, UTM-tagged for GA4 campaign attribution. Mirrors
     * the utm scheme of Plugin::pluginSiteUrl() (which is hardcoded to /contentegg).
     * $content identifies the link location (e.g. 'connect_modal', 'section_notice').
     */
    public static function getAffiliateEggUrl($content = null)
    {
        $params = array(
            'utm_source'   => \ContentEgg\application\Plugin::isFree() ? 'cefree' : 'cepro',
            'utm_medium'   => 'referral',
            'utm_campaign' => 'ae_integration',
            'utm_content'  => $content,
        );
        $params = array_filter($params, function ($v)
        {
            return $v !== null && $v !== '';
        });
        return 'https://www.keywordrush.com/affiliateegg?' . http_build_query($params);
    }

    /** Normalized custom-domain keys (values) of the new custom_domains sub-key. */
    public function getCustomDomains()
    {
        $all = \get_option($this->option_name(), array());
        if (!is_array($all) || empty($all['custom_domains']) || !is_array($all['custom_domains']))
            return array();
        return array_values($all['custom_domains']);
    }

    /** Add a registered shop to the legacy 'modules' sub-key (key===value===shop_id). */
    public function addKnownShop($shop_id)
    {
        $all = \get_option($this->option_name(), array());
        if (!is_array($all))
            $all = array();
        if (empty($all['modules']) || !is_array($all['modules']))
            $all['modules'] = array();
        $all['modules'][$shop_id] = $shop_id;
        \update_option($this->option_name(), $all);
    }

    /** Add an unregistered domain to the new 'custom_domains' sub-key. */
    public function addCustomDomain($domain)
    {
        $all = \get_option($this->option_name(), array());
        if (!is_array($all))
            $all = array();
        if (empty($all['custom_domains']) || !is_array($all['custom_domains']))
            $all['custom_domains'] = array();
        $all['custom_domains'][$domain] = $domain;
        \update_option($this->option_name(), $all);
    }

    /** Remove a module key from BOTH sub-keys (idempotent). Post data is untouched. */
    public function removeModuleId($key)
    {
        $all = \get_option($this->option_name(), array());
        if (!is_array($all))
            return;
        if (isset($all['modules'][$key]))
            unset($all['modules'][$key]);
        if (isset($all['custom_domains'][$key]))
            unset($all['custom_domains'][$key]);
        \update_option($this->option_name(), $all);
    }
}
