<?php

namespace ContentEgg\application;

defined('\ABSPATH') || exit;

use ContentEgg\application\admin\GeneralConfig;
use ContentEgg\application\components\ModuleManager;
use ContentEgg\application\components\BlockTemplateManager;
use ContentEgg\application\admin\import\PresetRepository;
use ContentEgg\application\components\ai\AiMethods;
use ContentEgg\application\helpers\CurrencyHelper;
use ContentEgg\application\helpers\AdminHelper;
use ContentEgg\application\helpers\ClickStatsHelper;
use ContentEgg\application\Plugin;

/**
 * Enqueues the standalone React Product Manager bundle and mounts it in the
 * chosen presentation (product_manager_ui): a Products PluginSidebar or a
 * metabox roster ("sidebar"), or a full workspace metabox ("workspace"). The
 * deprecated "metabox" value leaves the Angular metabox in charge. The bundle
 * reads the content-egg/products store.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class ProductManagerLoader
{
    const HANDLE = 'content-egg-product-manager';

    public static function init()
    {
        \add_action('enqueue_block_editor_assets', array(__CLASS__, 'enqueue'));
        \add_action('add_meta_boxes', array(__CLASS__, 'addClassicMetabox'), 10, 2);
        \add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueueClassic'));
    }

    /**
     * Normalized product-manager presentation. Any unrecognized/legacy value
     * (including the old "editor") resolves to "metabox" — a safe default that
     * renders the classic Angular metabox rather than a broken empty screen.
     */
    public static function mode(): string
    {
        $mode = GeneralConfig::getInstance()->option('product_manager_ui');
        return ($mode === 'sidebar' || $mode === 'workspace') ? $mode : 'metabox';
    }

    public static function enqueue()
    {
        $screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
        $post_type = $screen ? $screen->post_type : '';
        if (!$post_type || !in_array($post_type, (array) GeneralConfig::getInstance()->option('post_types')))
            return;

        if (self::asset() === null)
            return;

        // Design-system styles load on ANY CE block-editor screen so the shared
        // SearchPanel is styled inside the block binding modals too — not only
        // the active-presentation sidebar/manager.
        self::enqueueStyle();

        // The app loads on block screens whenever a React presentation is active:
        //   sidebar   -> JS registers the PluginSidebar (no metabox node);
        //   workspace -> the workspace mounts into the meta-box-area node emitted
        //                by addClassicMetabox (normal context).
        if (self::mode() === 'metabox')
            return;

        $post = \function_exists('get_post') ? \get_post() : null;
        self::enqueueApp($post ? (int) $post->ID : 0);
    }

    /**
     * Register the React mount metabox in the resolved context on screens that
     * need it (sidebar-classic -> side; workspace -> normal). Block screens in
     * sidebar mode get no metabox (they use the PluginSidebar).
     */
    public static function addClassicMetabox($post_type, $post)
    {
        $context = self::metaboxContextFor($post);
        if (!$context)
            return;

        $title = 'Content Egg';
        if (Plugin::isFree())
            $title .= '&nbsp;&nbsp;&nbsp;<a target="_blank" href="' . Plugin::pluginPricingUrl('ce_metabox', 'go_pro_link') . '">' . __('Go PRO', 'content-egg') . '</a>';
        elseif (Plugin::isPro())
            $title .= ' Pro';

        // In the side column, register at 'core' (with Publish) rather than
        // 'high' (which renders above Publish) and nudge it to sit right after
        // the Publish box. In the normal column, 'high' keeps it at the top.
        $priority = ($context === 'side') ? 'core' : 'high';
        \add_meta_box('cegg_product_manager', $title, array(__CLASS__, 'renderClassicMetabox'), $post_type, $context, $priority);

        if ($context === 'side')
        {
            // Handles two cases so the box lands right after Publish by default:
            //  - no saved user order  -> reorder the registered $wp_meta_boxes;
            //  - a saved user order   -> inject into that order (else WP appends
            //    our new box at the very bottom of the sidebar).
            self::placeAfterPublish($post_type);

            $screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
            $id = $screen ? $screen->id : $post_type;
            \add_filter('get_user_option_meta-box-order_' . $id, array(__CLASS__, 'orderSideAfterPublish'));
        }
    }

    /**
     * Insert our side box right after Publish in the user's SAVED metabox order,
     * but only while it isn't already there — so the first render is right after
     * Publish, yet once the user drags it anywhere their choice sticks (WP then
     * saves an order that already contains our id, and this becomes a no-op).
     */
    public static function orderSideAfterPublish($order)
    {
        if (!is_array($order))
            return $order; // no saved order: registered order (placeAfterPublish) governs

        // Already positioned by the user in any column? Respect their choice.
        if (strpos(implode(',', $order), 'cegg_product_manager') !== false)
            return $order;

        $side = isset($order['side']) ? $order['side'] : '';
        $ids = array_values(array_filter(explode(',', $side)));

        $new = array();
        $inserted = false;
        foreach ($ids as $bid)
        {
            $new[] = $bid;
            if ($bid === 'submitdiv')
            {
                $new[] = 'cegg_product_manager';
                $inserted = true;
            }
        }
        if (!$inserted)
            array_unshift($new, 'cegg_product_manager');

        $order['side'] = implode(',', $new);
        return $order;
    }

    public static function renderClassicMetabox($post)
    {
        echo '<div id="cegg-pm-classic-root"></div>';
    }

    /**
     * Move our side-column box to immediately after the Publish box (submitdiv)
     * in the registered order. Core boxes (Publish, Categories, Tags…) are all
     * registered before add_meta_boxes fires, so submitdiv already exists here.
     * Only affects the default order — a user's saved drag order still wins.
     */
    private static function placeAfterPublish($post_type)
    {
        global $wp_meta_boxes;

        $screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
        $id = $screen ? $screen->id : $post_type;

        if (empty($wp_meta_boxes[$id]['side']['core']))
            return;

        $core = $wp_meta_boxes[$id]['side']['core'];
        if (!isset($core['cegg_product_manager']) || !isset($core['submitdiv']))
            return;

        $ours = $core['cegg_product_manager'];
        unset($core['cegg_product_manager']);

        $reordered = array();
        foreach ($core as $key => $box)
        {
            $reordered[$key] = $box;
            if ($key === 'submitdiv')
                $reordered['cegg_product_manager'] = $ours;
        }
        if (!isset($reordered['cegg_product_manager']))
            $reordered['cegg_product_manager'] = $ours;

        $wp_meta_boxes[$id]['side']['core'] = $reordered;
    }

    /**
     * Enqueue the app on CLASSIC (non-block) screens that host a React node.
     * Block screens are handled by enqueue() (enqueue_block_editor_assets), so
     * this bails on them to avoid a double enqueue.
     */
    public static function enqueueClassic($hook)
    {
        if ($hook !== 'post.php' && $hook !== 'post-new.php')
            return;

        global $post;
        if (!self::metaboxContextFor($post))
            return;
        if (\function_exists('use_block_editor_for_post') && \use_block_editor_for_post($post))
            return; // block screens: enqueue() already loaded the app

        if (self::asset() === null)
            return;

        self::enqueueStyle();
        self::enqueueApp((int) $post->ID);
    }

    /**
     * Which metabox context should host the React mount node on this post's edit
     * screen, or '' for none. Single source of truth shared by the metabox
     * registration and the classic enqueue.
     *   sidebar  : block screens use the PluginSidebar (no node); classic screens
     *              get a side-column roster metabox.
     *   workspace: a normal-context workspace metabox on every screen (on block
     *              editors this lands in the meta-box area).
     */
    private static function metaboxContextFor($post): string
    {
        if (!$post instanceof \WP_Post)
            return '';
        if (!in_array($post->post_type, (array) GeneralConfig::getInstance()->option('post_types')))
            return '';

        $mode = self::mode();
        $isBlock = \function_exists('use_block_editor_for_post') && \use_block_editor_for_post($post);

        if ($mode === 'workspace')
            return 'normal';
        if ($mode === 'sidebar')
            return $isBlock ? '' : 'side';
        return ''; // metabox mode: Angular via EggMetabox
    }

    /** Load the built bundle's asset manifest, or null if it isn't built yet. */
    private static function asset()
    {
        $asset_path = __DIR__ . '/EggBlocks/blocks/product-manager/index.asset.php';
        if (!file_exists($asset_path))
            return null;
        return require $asset_path;
    }

    private static function enqueueStyle()
    {
        $asset = self::asset();
        if ($asset === null)
            return;
        \wp_enqueue_style(
            self::HANDLE,
            \ContentEgg\PLUGIN_DIR_URL . '/application/EggBlocks/blocks/product-manager/style-index.css',
            array('wp-components'),
            $asset['version']
        );
    }

    /** Enqueue the shared app bundle + runtime config for a given post. */
    private static function enqueueApp($post_id)
    {
        $asset = self::asset();
        if ($asset === null)
            return;

        // Load the classic editor (TinyMCE + Quicktags) so the product drawer's
        // "Expand" description editor can mount a WYSIWYG via wp.editor.initialize.
        if (\function_exists('wp_enqueue_editor'))
            \wp_enqueue_editor();

        \wp_enqueue_script(
            self::HANDLE,
            \ContentEgg\PLUGIN_DIR_URL . '/application/EggBlocks/blocks/product-manager/index.js',
            $asset['dependencies'],
            $asset['version'],
            true
        );
        \wp_set_script_translations(self::HANDLE, 'content-egg');

        // The manual-entry module (non-searchable product parser, e.g. Offer) —
        // the "Add product" target. Resolved from ALL product modules, not just
        // active ones, so the button shows even when it's disabled; the add
        // endpoint activates it on first use.
        $manual_module_id = '';
        foreach (ModuleManager::getInstance()->getProductParserModules(false) as $module)
        {
            if (!$module->isSearchable())
            {
                $manual_module_id = $module->getId();
                break;
            }
        }

        // Coupon family: every coupon module id (so the store can classify stored
        // items by family without an async fetch) + the manual-entry coupon
        // module (the "Add coupon manually" target, like Offer for products).
        $coupon_module_ids = ModuleManager::getInstance()->getParserModuleIdsByTypes(
            \ContentEgg\application\components\ParserModule::PARSER_TYPE_COUPON,
            false
        );
        $manual_coupon_module_id = '';
        foreach (ModuleManager::getInstance()->getParserModulesByTypes(
            \ContentEgg\application\components\ParserModule::PARSER_TYPE_COUPON,
            false
        ) as $module)
        {
            if (!$module->isSearchable())
            {
                $manual_coupon_module_id = $module->getId();
                break;
            }
        }

        // Media families: every image/video module id, so the store classifies
        // stored items by family without an async fetch (mirrors couponModuleIds).
        $image_module_ids = ModuleManager::getInstance()->getParserModuleIdsByTypes(
            \ContentEgg\application\components\ParserModule::PARSER_TYPE_IMAGE,
            false
        );
        $video_module_ids = ModuleManager::getInstance()->getParserModuleIdsByTypes(
            \ContentEgg\application\components\ParserModule::PARSER_TYPE_VIDEO,
            false
        );

        // Families that have at least one ACTIVE module — the sidebar hides tabs
        // for empty families (e.g. no active image modules → no Images tab).
        $active_families = array();
        foreach (array(
            \ContentEgg\application\components\ParserModule::PARSER_TYPE_PRODUCT,
            \ContentEgg\application\components\ParserModule::PARSER_TYPE_COUPON,
            \ContentEgg\application\components\ParserModule::PARSER_TYPE_IMAGE,
            \ContentEgg\application\components\ParserModule::PARSER_TYPE_VIDEO,
        ) as $family_type)
        {
            if (ModuleManager::getInstance()->getParserModuleIdsByTypes($family_type, true))
                $active_families[] = $family_type;
        }

        // Bridge-page import: the preset catalog + enqueue nonce, only when the
        // import subsystem is present. Empty presets => the bulk action is omitted.
        $importPresets = array();
        $importNonce = '';
        $importDefaultPresetId = null;
        if (\class_exists('ContentEgg\\application\\admin\\import\\PresetRepository'))
        {
            $importPresets = PresetRepository::getList();
            $importNonce = \wp_create_nonce('cegg_import');
            $importDefaultPresetId = PresetRepository::getDefaultId();
        }

        // Authoritative block-vs-classic signal (the core/block-editor store
        // registers on classic screens too, via the wp-edit-post bundle dep, so
        // the JS can't tell). Insert actions pick block vs shortcode from this.
        $post = \get_post($post_id);
        $isBlockEditor = ($post instanceof \WP_Post
            && \function_exists('use_block_editor_for_post')
            && \use_block_editor_for_post($post));

        // Config the editor app reads at runtime. postId lets the store resolve
        // the current post without core/editor (classic screens). Kept minimal.
        \wp_add_inline_script(
            self::HANDLE,
            'window.ceggPmConfig = ' . \wp_json_encode(array(
                'postId' => (int) $post_id,
                'presentation' => self::mode(),
                'isBlockEditor' => $isBlockEditor,
                // Edition label + upgrade link for the sidebar header. The free
                // build shows a "Go PRO" pill; the URL carries the same utm/GA4
                // source tags as the other Go PRO links (pluginPricingUrl).
                'isPro' => Plugin::isPro(),
                'goProUrl' => Plugin::isFree()
                    ? Plugin::pluginPricingUrl('ce_metabox', 'go_pro_sidebar')
                    : '',
                'isWooProduct' => (\get_post_type($post_id) === 'product' && \class_exists('WooCommerce')),
                'currencies' => CurrencyHelper::getCurrenciesList(),
                'manualModuleId' => $manual_module_id,
                'couponModuleIds' => array_values($coupon_module_ids),
                'manualCouponModuleId' => $manual_coupon_module_id,
                'couponBlockTemplates' => self::couponBlockTemplatesForBuilder(),
                'imageModuleIds' => array_values($image_module_ids),
                'videoModuleIds' => array_values($video_module_ids),
                'activeFamilies' => $active_families,
                'imageBlockTemplates' => self::mediaBlockTemplatesForBuilder('IMAGE'),
                'videoBlockTemplates' => self::mediaBlockTemplatesForBuilder('VIDEO'),
                'aiEnabled' => AdminHelper::isAiEnabled(),
                'aiTitleMethods' => AiMethods::titleMethods(),
                'aiDescriptionMethods' => AiMethods::descriptionMethods(),
                'smartGroupMethods' => AiMethods::smartGroupMethods(),
                'clicksEnabled' => ClickStatsHelper::isEnabled(),
                'clicksLabel30' => ClickStatsHelper::label30(),
                'templatePreviewBaseUrl' => \ContentEgg\PLUGIN_DIR_URL . '/templates/preview/',
                'blockTemplates' => self::blockTemplatesForBuilder(),
                // Default content-egg/products template for the "Add"/drag-in
                // flow (single product) and for a multi-product drop/insert.
                // Filterable so a site can default to e.g. the offers-list
                // layout instead of the single-item card without switching
                // templates on every insert.
                'productSingleTemplate' => \apply_filters('cegg_product_manager_block_template', 'item_simple', $post_id),
                'productMultiTemplate' => \apply_filters('cegg_product_manager_block_template_multi', 'offers_list', $post_id),
                'importPresets' => $importPresets,
                'importNonce' => $importNonce,
                'importDefaultPresetId' => $importDefaultPresetId,
                'ajaxUrl' => \admin_url('admin-ajax.php'),
                // Base for links to another post's edit screen (Bridge Page indicator).
                'postEditUrl' => \admin_url('post.php'),
            )) . ';',
            'before'
        );
    }

    /**
     * Block templates for the Product Manager "Insert block" builder tab.
     * Mirrors ProductBlock's editor payload: id, human label, optional preview
     * webp filename, and whether it is a custom/theme template.
     *
     * @return array<int,array{id:string,name:string,preview:string,isCustom:bool}>
     */
    private static function blockTemplatesForBuilder(): array
    {
        // Include custom/theme templates ($exclude_custom = false). Unlike the
        // block editor (which can't live-preview them), this tab only builds a
        // shortcode to copy, so they are valid choices. They have no preview
        // webp — the card falls back to a placeholder thumb + "Custom" badge.
        // Product templates only — never coupon/image/video templates that ride
        // the same block pipeline but belong to their own family builders. Allowlist
        // (untyped or PRODUCT-typed), custom templates included, so new media
        // families can't leak into the product shortcode builder.
        $templates = BlockTemplateManager::getInstance()->getProductTemplates(false);

        return self::formatTemplatesForBuilder($templates);
    }

    /**
     * Coupon block templates for the Coupons "Shortcode" tab. Same shape as
     * blockTemplatesForBuilder(), scoped to COUPON so the tab lists only coupon
     * templates regardless of screen (works on classic + block editors).
     *
     * @return array<int,array{id:string,name:string,preview:string,isCustom:bool}>
     */
    private static function couponBlockTemplatesForBuilder(): array
    {
        $templates = BlockTemplateManager::getInstance()->getTemplatesByModuleType('COUPON');

        // Surface the default (ticket) first so the Shortcode tab preselects it and
        // lists it at the top, matching the block's DEFAULT_TEMPLATE.
        $default = \ContentEgg\application\blocks\couponblock\CouponBlock::DEFAULT_TEMPLATE;
        if (isset($templates[$default]))
            $templates = array($default => $templates[$default]) + $templates;

        return self::formatTemplatesForBuilder($templates);
    }

    /**
     * Block templates for the media Shortcode builder, by parser family
     * ('IMAGE'|'VIDEO') — the images/videos block templates (block_images,
     * block_videos), formatted like the product/coupon builder lists.
     */
    private static function mediaBlockTemplatesForBuilder(string $type): array
    {
        return self::formatTemplatesForBuilder(
            BlockTemplateManager::getInstance()->getTemplatesByModuleType($type)
        );
    }

    /**
     * Format an [id => label] template map into the builder payload shape.
     *
     * @param array<string,string> $templates
     * @return array<int,array{id:string,name:string,preview:string,isCustom:bool}>
     */
    private static function formatTemplatesForBuilder(array $templates): array
    {
        $out = array();
        foreach ($templates as $key => $name)
        {
            if ($key === 'customizable')
                continue;

            $preview = BlockTemplateManager::isPreviewAvailable($key) ? $key . '.webp' : '';
            $isCustom = BlockTemplateManager::isCustomTemplate($key);

            // Custom/theme labels carry a trailing " [custom]" / " [theme]"
            // location tag (TemplateManager::scanTemplates). Strip it — the card's
            // "Custom" badge already marks these, so the tag would be redundant.
            if ($isCustom)
                $name = preg_replace('/\s*\[[^\]]*\]\s*$/u', '', $name);

            $out[] = array(
                'id'       => $key,
                'name'     => $name,
                'preview'  => $preview,
                'isCustom' => $isCustom,
            );
        }

        return $out;
    }
}
