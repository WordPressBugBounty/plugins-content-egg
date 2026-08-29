<?php

namespace ContentEgg\application\admin;

defined('\ABSPATH') || exit;

use ContentEgg\application\Plugin;
use ContentEgg\application\helpers\TextHelper;
use ContentEgg\application\admin\GeneralConfig;
use ContentEgg\application\admin\import\ImportQueueApi;
use ContentEgg\application\admin\import\PresetRepository;
use ContentEgg\application\components\ModuleManager;
use ContentEgg\application\components\ModuleApi;
use ContentEgg\application\components\LManager;
use ContentEgg\application\components\ReviewNotice;
use ContentEgg\application\components\FeaturedImage;
use ContentEgg\application\LinkIndexScheduler;
use ContentEgg\application\ModuleUpdateScheduler;
use ContentEgg\application\MaintenanceCron;



/**
 * PluginAdmin class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class PluginAdmin
{

    protected static $instance = null;

    public static function getInstance()
    {
        if (self::$instance == null)
            self::$instance = new self;

        return self::$instance;
    }

    private function __construct()
    {
        if (!\is_admin())
            die('You are not authorized to perform the requested action.');

        \add_action('admin_menu', array($this, 'add_admin_menu'));
        \add_action('admin_enqueue_scripts', array($this, 'admin_load_scripts'));
        \add_filter('parent_file', array($this, 'highlight_admin_menu'));

        if (isset($GLOBALS['pagenow']) && $GLOBALS['pagenow'] == 'plugins.php')
        {
            \add_filter('plugin_row_meta', array($this, 'add_plugin_row_meta'), 10, 2);
        }

        AdminNotice::getInstance()->adminInit();
        if (!Plugin::isFree())
        {
            LManager::getInstance()->adminInit();
        }

        if (Plugin::isFree())
            ReviewNotice::getInstance()->adminInit();

        if (Plugin::isPaidBuild())
        {
            \ContentEgg\application\admin\LicenseNotices::getInstance()->register();
            MaintenanceCron::schedule();
        }

        if ((Plugin::isFree() || (Plugin::isPro() && Plugin::isActivated()) || (Plugin::isEnvato() && Plugin::isActivated())) && !Plugin::isBlocked())
        {
            PresetRepository::init();
            GeneralConfig::getInstance()->adminInit();
            ModuleManager::getInstance()->adminInit();
            new ModuleSettingsContoller;
            new FeedWizardController;
            new ProductImportController;
            new ProductPrefillController;
            // Submenu order follows registration order, so Shops sits directly after
            // Prefill by being constructed here rather than at the end.
            ShopsController::getInstance();
            new ProductController;
            new ClicksStatsController;
            new EggMetabox;
            \ContentEgg\application\ProductManagerLoader::init();
            ProductManagerUiNotice::init();
            new ModuleApi;
            new FeaturedImage;
            new ToolsController;
            new ScrapTestController;
            new AgentAccessController;
            ImportQueueApi::init();
            AeIntegrationConfig::getInstance()->adminInit();
            AeConnectController::register();
            new AutoblogController;
            ProUpsellLinks::init();
            ModuleUpdateScheduler::addScheduleEvent('ten_min');
        }

        if (Plugin::isEnvato() && !Plugin::isActivated() && !\get_option(Plugin::slug . '_env_install'))
            EnvatoConfig::getInstance()->adminInit();
        elseif (Plugin::isPaidBuild())
            LicConfig::getInstance()->adminInit();
    }

    function admin_load_scripts()
    {
        if ($GLOBALS['pagenow'] != 'admin.php' || empty($_GET['page']))
            return;

        $page_pats = explode('-', sanitize_key(wp_unslash($_GET['page'])));

        if (count($page_pats) < 2 || $page_pats[0] . '-' . $page_pats[1] != 'content-egg')
            return;

        \wp_enqueue_script('content_egg_common', \ContentEgg\PLUGIN_RES . '/js/common.js', array('jquery'));
        \wp_localize_script('content_egg_common', 'contenteggL10n', array(
            'are_you_shure' => __('Are you sure?', 'content-egg'),
            'sitelang' => GeneralConfig::getInstance()->option('lang'),
        ));

        \wp_enqueue_style('contentegg-admin', \ContentEgg\PLUGIN_RES . '/css/admin.css', null, '' . Plugin::version());
    }

    public function add_plugin_row_meta(array $links, $file)
    {
        if ($file == plugin_basename(\ContentEgg\PLUGIN_FILE) && (Plugin::isActivated() || Plugin::isFree()))
        {
            return array_merge(
                $links,
                array(
                    '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=content-egg">' . __('Settings', 'content-egg') . '</a>',
                )
            );
        }
        return $links;
    }

    public function add_admin_menu()
    {
        $icon_svg = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" width="20" height="20"><path fill="#a7aaad" fill-rule="evenodd" d="M5.20 10.26L5.52 10.42L5.88 10.47L16.04 10.47L16.34 10.43L16.57 10.35L17.00 10.02L17.20 9.71L17.30 9.36L17.21 8.26L16.88 6.67L16.62 5.87L16.31 5.11L15.68 3.94L14.92 2.92L14.07 2.09L13.11 1.43L12.15 1.01L11.16 0.77L10.10 0.72L9.04 0.87L8.03 1.20L7.08 1.70L6.21 2.37L5.39 3.21L4.67 4.21L4.05 5.34L3.53 6.59L3.13 7.94L2.84 9.49L2.69 11.02L2.75 12.31L2.94 13.47L3.25 14.58L3.70 15.59L4.26 16.51L4.94 17.31L5.73 18.01L6.56 18.54L7.54 18.99L8.58 19.29L9.72 19.45L11.01 19.45L12.25 19.30L13.35 19.00L14.32 18.54L15.13 17.96L15.83 17.23L16.39 16.37L16.71 15.70L16.96 14.98L17.20 13.97L17.21 13.61L17.07 13.16L16.80 12.83L16.39 12.60L15.98 12.53L15.74 12.56L15.46 12.66L15.26 12.78L15.05 12.99L14.85 13.36L14.51 14.64L14.13 15.42L13.85 15.80L13.54 16.11L13.19 16.37L12.78 16.59L12.19 16.81L11.57 16.94L10.87 17.02L10.12 17.03L9.44 16.97L8.82 16.84L8.25 16.65L7.73 16.40L7.10 15.99L6.51 15.43L6.02 14.75L5.65 14.01L5.37 13.18L5.19 12.23L5.13 11.20L5.20 10.26ZM5.62 8.06L5.98 6.99L6.42 6.04L6.96 5.16L7.56 4.44L8.21 3.89L8.88 3.49L9.63 3.24L10.37 3.16L11.10 3.24L11.78 3.49L12.43 3.89L13.03 4.46L13.58 5.18L14.05 6.04L14.44 7.02L14.70 8.03L5.62 8.06Z"/></svg>');
        $title = 'Content Egg';
        if (Plugin::isPro())
            $title .= ' Pro';

        if (class_exists('\\ContentEgg\\application\\Autoupdate', true))
        {
            $locked = (bool) \get_option('cegg_locked', false);
            $at     = (int) \get_option('cegg_locked_at', 0);
            if ($locked && $at > 0 && (time() - $at) >= 259200)
            {
                \add_menu_page($title, $title, 'manage_options', Plugin::slug . '-lic', array(LicConfig::getInstance(), 'settings_page'), $icon_svg);
                return;
            }
        }

        \add_menu_page($title, $title, 'publish_posts', Plugin::slug, null, $icon_svg);
    }

    public static function render($view_name, $_data = null)
    {
        if (is_array($_data))
            extract($_data, EXTR_PREFIX_SAME, 'data');
        else
            $data = $_data;

        include \ContentEgg\PLUGIN_PATH . 'application/admin/views/' . TextHelper::clear($view_name) . '.php';
    }

    /**
     * Highlight menu for hidden submenu item
     */
    function highlight_admin_menu($file)
    {
        global $plugin_page;

        // options.php - hidden submenu items
        if ($file != 'options.php' || substr($plugin_page, 0, strlen(Plugin::slug())) !== Plugin::slug())
            return $file;

        $page_parts = explode('--', $plugin_page);
        if (count($page_parts) > 1)
        {
            $plugin_page = $page_parts[0];
        }
        else
            $plugin_page = Plugin::slug();

        return $file;
    }

    public static function res(string $relativePath): string
    {
        $base = rtrim(\ContentEgg\PLUGIN_RES, '/');
        $path = ltrim($relativePath, '/');

        return "{$base}/{$path}";
    }
}
